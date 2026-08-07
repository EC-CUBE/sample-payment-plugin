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
use Plugin\SamplePayment44\Service\AgentCommerce\Exception\InvalidPaymentDataException;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\AgentPaymentGatewayInterface;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayResult;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayStatus;
use Plugin\SamplePayment44\Service\Method\CreditCard;
use Psr\Log\LoggerInterface;

/**
 * ACP/UCP 共通のカード決済ハンドラ基底.
 *
 * コアの決済ハンドラ契約 (authorize/capture/supports) の共通ロジックを提供し、PSP 連携は
 * {@link AgentPaymentGatewayInterface} (サンプルは {@link Gateway\MockAgentPaymentGateway}) に委譲する。
 * プロトコル固有の差分 (handler_id・トークン償還/交換・対象プロトコル) は派生クラスが実装する。
 *
 * 本基底は **コアの決済ハンドラインターフェイスを直接 implements しない**。プロトコル固有の
 * インターフェイス ({@link \Eccube\Service\AgentCommerce\Payment\AcpPaymentHandlerInterface} /
 * {@link \Eccube\Service\AgentCommerce\Payment\UcpPaymentHandlerInterface}) は派生クラスが実装し、
 * `agent_commerce.payment_handler` タグは本体 `Kernel::build()` の registerForAutoconfiguration が
 * 具象サービスへ付与する (services.yaml の `_instanceof` はファイルスコープのため、services.php で
 * 登録されるプラグインの具象クラスには届かない)。
 */
abstract class AbstractAgentCardHandler
{
    public function __construct(
        private readonly MinorUnitConverter $minorUnitConverter,
        private readonly AgentPaymentGatewayInterface $gateway,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * このハンドラが扱うエージェントプロトコル ({@link \Eccube\Entity\Master\AgentProtocol} の定数).
     */
    abstract protected function protocolId(): int;

    /**
     * capture 失敗を再試行可能 (ready へ戻す) として返してよいか.
     *
     * コアは **capture 単独の再実行入口を持たない**。ready からの再 complete は新規 {@link authorize()}
     * から始まり、保持した PSP 参照はハンドラへ渡らない
     * ({@link \Eccube\Service\AgentCommerce\Payment\AgentCheckoutPaymentHandlerInterface::capture()})。
     * したがって「同じ $paymentData から instrument を作り直せるか」がそのまま再試行可否になる。
     *
     * 作り直せないのに true を返すと、再試行は必ず失敗したうえ与信だけが PSP 側に残る。
     */
    abstract protected function captureFailureIsRetryable(): bool;

    /**
     * complete リクエストの中立支払データを、ゲートウェイへ渡す instrument へ整形する.
     *
     * ACP は Shared Payment Token の償還、UCP は交換済みトークンの受け渡しを行う。
     * **{@link authorize()} からのみ呼ばれる**。ACP の償還はワンショットのため、capture では
     * 再実行せず与信結果 ({@link PaymentOutcome::$transactionId}) を用いる。
     *
     * 支払トークンを解決できない場合は {@link InvalidPaymentDataException} を投げること
     * (空のトークンを返すと、どのトークン規約にも一致しないぶん「正常な支払」と解釈され、
     * 無与信のまま受注が確定しうる)。
     *
     * @param array<string, mixed> $paymentData
     *
     * @return array<string, mixed>
     *
     * @throws InvalidPaymentDataException
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
     * @param array<string, mixed> $paymentReference 中断前の complete で保持した PSP 参照 (再開時のみ非空)
     */
    public function authorize(Order $order, array $paymentData, array $paymentReference = []): PaymentOutcome
    {
        try {
            $instrument = $this->toGatewayInstrument($paymentData);
        } catch (InvalidPaymentDataException $e) {
            // 支払データを解決できない要求は与信成功にしない (fail-closed)。エージェントが
            // payment_data を直して再送すれば回復できるため retryable (セッションは ready へ戻る)。
            return PaymentOutcome::failed('invalid_payment_data', $e->getMessage(), true);
        }

        // 再開 complete では中断前の取引を続行する。実 PSP では「既存の PaymentIntent を confirm する」に
        // 相当し、支払トークンの再償還を避けるための情報。エージェント入力ではなくサーバ側の記録。
        $transactionId = $paymentReference['transaction_id'] ?? null;
        if (is_string($transactionId) && '' !== $transactionId) {
            $instrument['transaction_id'] = $transactionId;
        }

        try {
            $result = $this->gateway->authorize(
                $order->getCurrencyCode(),
                $this->amount($order),
                $instrument,
                $this->context($order),
            );
        } catch (\Throwable $e) {
            // PSP 通信の失敗。コントローラは AgentCheckoutException しか捕捉しないため、ここで
            // 捕まえないと 500 になりエージェントへ決済エラーとして返らない。与信は成立していないので retryable。
            $this->logger->error('Agent payment authorization failed.', ['exception' => $e, 'order_no' => $order->getOrderNo()]);

            return PaymentOutcome::failed('payment_gateway_error', 'The payment gateway could not be reached.', true);
        }

        return $this->toAuthorizeOutcome($result);
    }

    /**
     * @param array<string, mixed> $paymentData
     */
    public function capture(Order $order, array $paymentData, PaymentOutcome $authorization): PaymentOutcome
    {
        $transactionId = $authorization->transactionId;
        if ($transactionId === null || $transactionId === '') {
            // 取引識別子を返さない与信は capture できない (ハンドラ実装の誤り)。再試行しても回復しない。
            return PaymentOutcome::failed('missing_transaction_reference', 'The authorization did not return a transaction reference.', false);
        }

        try {
            $result = $this->gateway->capture(
                $order->getCurrencyCode(),
                $this->amount($order),
                $transactionId,
                $this->context($order),
            );
        } catch (\Throwable $e) {
            // 与信は PSP 側に残るため、取消・照会できるよう取引識別子と metadata を引き継ぐ。
            // 再試行可否は「新規 authorize をやり直せるか」で決まる ({@link captureFailureIsRetryable()})。
            $this->logger->error('Agent payment capture failed.', ['exception' => $e, 'order_no' => $order->getOrderNo(), 'transaction_id' => $transactionId]);

            return PaymentOutcome::failed(
                'payment_capture_error',
                'The payment gateway could not be reached.',
                $this->captureFailureIsRetryable(),
                $transactionId,
                $authorization->metadata,
            );
        }

        return $this->toCaptureOutcome($result, $order);
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
     * capture のゲートウェイ結果をコアの {@link PaymentOutcome} へ写像する.
     *
     * **コアの契約は「capture の戻り値は COMPLETED か FAILED のみ」**。中間状態を返してもコアは
     * 失敗として扱い在庫を回収するため、authorize 用の {@link toAuthorizeOutcome()} は流用せず
     * ここで終端 2 値へ畳む。REQUIRES_CAPTURE / REQUIRES_ACTION / PROCESSING が返るのは
     * ゲートウェイ実装の誤りなので、ログに残したうえで失敗にする (fail-closed)。
     */
    private function toCaptureOutcome(GatewayResult $result, Order $order): PaymentOutcome
    {
        if ($result->status === GatewayStatus::SUCCEEDED) {
            return PaymentOutcome::completed($result->transactionId, $result->metadata);
        }

        if ($result->status === GatewayStatus::FAILED) {
            return PaymentOutcome::failed(
                $result->errorCode ?? 'capture_failed',
                $result->errorMessage ?? '',
                // ゲートウェイが不可逆と判断した失敗 (金額不一致等) は、再 authorize できても再試行させない。
                $result->retryable && $this->captureFailureIsRetryable(),
                $result->transactionId,
                $result->metadata,
            );
        }

        $this->logger->error('The payment gateway returned a non-terminal status from capture.', [
            'order_no' => $order->getOrderNo(),
            'status' => $result->status->value,
            'transaction_id' => $result->transactionId,
        ]);

        return PaymentOutcome::failed(
            'capture_unexpected_status',
            'The payment could not be captured.',
            $this->captureFailureIsRetryable(),
            $result->transactionId,
            $result->metadata,
        );
    }

    /**
     * authorize のゲートウェイ結果をコアの {@link PaymentOutcome} へ写像する.
     *
     * 与信のみ (REQUIRES_CAPTURE) と売上確定済 (SUCCEEDED) を区別する点が要。潰して COMPLETED に
     * すると、auto-capture 型 PSP へ差し替えたときにコアが capture を二重発行する。
     */
    private function toAuthorizeOutcome(GatewayResult $result): PaymentOutcome
    {
        return match ($result->status) {
            GatewayStatus::REQUIRES_CAPTURE => PaymentOutcome::authorized($result->transactionId, $result->metadata),
            GatewayStatus::SUCCEEDED => PaymentOutcome::completed($result->transactionId, $result->metadata),
            GatewayStatus::REQUIRES_ACTION => PaymentOutcome::requiresAction($result->actionData, $result->metadata, $result->transactionId),
            GatewayStatus::PROCESSING => PaymentOutcome::pending($result->metadata, $result->transactionId),
            GatewayStatus::FAILED => PaymentOutcome::failed(
                $result->errorCode ?? 'payment_failed',
                $result->errorMessage ?? '',
                $result->retryable,
                $result->transactionId,
                $result->metadata,
            ),
        };
    }
}
