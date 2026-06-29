<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * https://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SamplePayment44\Service\AgentCommerce;

use Eccube\Entity\Order;
use Eccube\Service\AgentCommerce\MinorUnitConverter;
use Eccube\Service\AgentCommerce\Payment\PaymentOutcome;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\AgentPaymentGatewayInterface;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayResult;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayStatus;
use Plugin\SamplePayment44\Service\Method\CreditCard;

/**
 * ACP/UCP 共通のカード決済ハンドラ基底.
 *
 * コアの決済ハンドラ契約 (authorize/capture/supports) の共通ロジックを提供し、PSP 連携は
 * {@link AgentPaymentGatewayInterface} (サンプルは {@link Gateway\MockAgentPaymentGateway}) に委譲する。
 * プロトコル固有の差分 (handler_id・トークン償還/交換・対象プロトコル) は派生クラスが実装する。
 *
 * 本基底は **コアの決済ハンドラインターフェイスを直接 implements しない**。派生クラス側で
 * {@link \Eccube\Service\AgentCommerce\Payment\AcpPaymentHandlerInterface} 等を実装することで、
 * コアの `_instanceof` 自動タグ付与 (`agent_commerce.payment_handler`) が**具象のみ**に効き、
 * 抽象クラスがタグ付き iterator に混入するのを避ける。
 */
abstract class AbstractAgentCardHandler
{
    public function __construct(
        private readonly MinorUnitConverter $minorUnitConverter,
        private readonly AgentPaymentGatewayInterface $gateway,
    ) {
    }

    /**
     * このハンドラが扱うエージェントプロトコル ({@link \Eccube\Entity\Master\AgentProtocol} の定数).
     */
    abstract protected function protocolId(): int;

    /**
     * complete リクエストの中立支払データを、ゲートウェイへ渡す instrument へ整形する.
     *
     * ACP は Shared Payment Token の償還、UCP は交換済みトークンの受け渡しを行う。
     *
     * @param array<string, mixed> $paymentData
     *
     * @return array<string, mixed>
     */
    abstract protected function toGatewayInstrument(array $paymentData): array;

    /**
     * 本サンプルは通常購入の {@link CreditCard} (トークン決済) を流用するため、
     * その method_class が割り当たり、かつ注文が自プロトコルのエージェント注文のときに扱う。
     */
    public function supports(Order $order): bool
    {
        $payment = $order->getPayment();
        if ($payment === null || $payment->getMethodClass() !== CreditCard::class) {
            return false;
        }

        return $order->getAgentProtocol()?->getId() === $this->protocolId();
    }

    /**
     * @param array<string, mixed> $paymentData
     */
    public function authorize(Order $order, array $paymentData): PaymentOutcome
    {
        $result = $this->gateway->authorize(
            $order->getCurrencyCode(),
            $this->amount($order),
            $this->toGatewayInstrument($paymentData),
            $this->context($order),
        );

        return $this->toOutcome($result);
    }

    /**
     * @param array<string, mixed> $paymentData
     */
    public function capture(Order $order, array $paymentData): PaymentOutcome
    {
        $result = $this->gateway->capture(
            $order->getCurrencyCode(),
            $this->amount($order),
            $this->toGatewayInstrument($paymentData),
            $this->context($order),
        );

        return $this->toOutcome($result);
    }

    /**
     * 注文の支払総額を minor unit 整数へ変換する.
     */
    private function amount(Order $order): int
    {
        return $this->minorUnitConverter->toMinorUnits($order->getPaymentTotal(), $order->getCurrencyCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Order $order): array
    {
        return [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
        ];
    }

    /**
     * ゲートウェイ結果をコアの {@link PaymentOutcome} へ写像する.
     */
    private function toOutcome(GatewayResult $result): PaymentOutcome
    {
        return match ($result->status) {
            GatewayStatus::SUCCEEDED, GatewayStatus::REQUIRES_CAPTURE => PaymentOutcome::completed($result->transactionId, $result->metadata),
            GatewayStatus::REQUIRES_ACTION => PaymentOutcome::requiresAction($result->actionData, $result->metadata),
            GatewayStatus::PROCESSING => PaymentOutcome::pending($result->metadata),
            GatewayStatus::FAILED => PaymentOutcome::failed($result->errorCode ?? 'payment_failed', $result->errorMessage ?? '', $result->retryable),
        };
    }
}
