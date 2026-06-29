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

namespace Plugin\SamplePayment44\Service\AgentCommerce\Acp;

use Eccube\Entity\Master\AgentProtocol;
use Eccube\Service\AgentCommerce\Payment\AcpPaymentHandlerInterface;
use Plugin\SamplePayment44\Service\AgentCommerce\AbstractAgentCardHandler;

/**
 * ACP (Shared Payment Token) 向けのサンプルカード決済ハンドラ.
 *
 * 通常購入のトークン決済 ({@link \Plugin\SamplePayment44\Service\Method\CreditCard}) を流用し、
 * ACP の complete で渡される SPT を {@link Gateway\MockAgentPaymentGateway} で課金する。
 * 実 PSP 連携は {@link \Plugin\SamplePayment44\Service\AgentCommerce\Gateway\AgentPaymentGatewayInterface}
 * の実装差し替えで対応する (stripe-payment-plugin への移植点)。
 */
class AcpSampleCardHandler extends AbstractAgentCardHandler implements AcpPaymentHandlerInterface
{
    /** ACP の payment_data.handler_id と突合する識別子. */
    public const HANDLER_ID = 'card_tokenized';

    public function getHandlerId(): string
    {
        return self::HANDLER_ID;
    }

    public function redeemSharedPaymentToken(array $paymentData): array
    {
        // 実 PSP では SPT を課金可能な参照へ償還する。サンプルでは中立 instrument へ整形し、
        // モックゲートウェイがトークン規約でシナリオ (成功/3DS/拒否) を判定できるようトークンを保持する。
        return [
            'token' => $this->extractToken($paymentData),
            'authentication_result' => $paymentData['authentication_result'] ?? null,
            'redeemed' => true,
        ];
    }

    protected function protocolId(): int
    {
        return AgentProtocol::ACP;
    }

    protected function toGatewayInstrument(array $paymentData): array
    {
        return $this->redeemSharedPaymentToken($paymentData);
    }

    /**
     * payment_data から支払トークンを取り出す (`token` 直下、または `instrument.credential` 配下).
     *
     * @param array<string, mixed> $paymentData
     */
    private function extractToken(array $paymentData): string
    {
        if (is_string($paymentData['token'] ?? null)) {
            return $paymentData['token'];
        }

        $credential = $paymentData['instrument']['credential'] ?? null;
        if (is_array($credential) && is_string($credential['token'] ?? null)) {
            return $credential['token'];
        }
        if (is_string($credential)) {
            return $credential;
        }

        return '';
    }
}
