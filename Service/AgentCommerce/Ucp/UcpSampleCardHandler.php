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

namespace Plugin\SamplePayment44\Service\AgentCommerce\Ucp;

use Eccube\Entity\Master\AgentProtocol;
use Eccube\Service\AgentCommerce\Payment\UcpPaymentHandlerInterface;
use Plugin\SamplePayment44\Service\AgentCommerce\AbstractAgentCardHandler;

/**
 * UCP (Payment Token Exchange) 向けのサンプルカード決済ハンドラ.
 *
 * UCP discovery profile の `payment_handlers` に {@link getHandlerId()} の逆ドメイン名で広告され、
 * complete の payment.instruments[].handler_id で解決される。交換済みトークンを
 * {@link Gateway\MockAgentPaymentGateway} で課金する。
 */
class UcpSampleCardHandler extends AbstractAgentCardHandler implements UcpPaymentHandlerInterface
{
    /** UCP の逆ドメイン名形式の handler_id (discovery profile / payment.instruments[].handler_id と突合). */
    public const HANDLER_ID = 'dev.ucp.payment.card';

    public function getHandlerId(): string
    {
        return self::HANDLER_ID;
    }

    public function exchangePaymentToken(array $credential): array
    {
        // 実 PSP ではクレデンシャルをゲートウェイトークンへ交換する。サンプルでは中立 instrument へ整形し、
        // モックゲートウェイがトークン規約でシナリオを判定できるようトークンを保持する。
        return [
            'token' => $this->extractToken($credential),
            'exchanged' => true,
        ];
    }

    protected function protocolId(): int
    {
        return AgentProtocol::UCP;
    }

    protected function toGatewayInstrument(array $paymentData): array
    {
        // UCP は controller の resolvePaymentData() で exchangePaymentToken() 済みの中立データを受け取る。
        return $paymentData;
    }

    /**
     * クレデンシャルから支払トークンを取り出す (`token` 直下、または文字列のクレデンシャル).
     *
     * @param array<string, mixed> $credential
     */
    private function extractToken(array $credential): string
    {
        if (is_string($credential['token'] ?? null)) {
            return $credential['token'];
        }

        return '';
    }
}
