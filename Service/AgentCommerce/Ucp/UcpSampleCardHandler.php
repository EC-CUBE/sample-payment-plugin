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
use Plugin\SamplePayment44\Service\AgentCommerce\PaymentTokenExtractor;

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
        //
        // 本メソッドは complete の状態機械の外側 (controller のペイロード解決時) で呼ばれるため
        // **例外を投げない**。解決できないトークンは null のまま返し、authorize 側 (toGatewayInstrument)
        // で fail-closed に失敗させる (ここで投げるとビジネス系エラーでなく HTTP 500 になる)。
        return [
            'token' => PaymentTokenExtractor::findToken($credential),
            // UCP は ACP と異なり、追加認証の結果もクレデンシャル経由でしか届かない。ここで落とすと
            // 再開 complete で認証済みと判定できず、requires_action から永久に復帰できなくなる。
            'authentication_result' => $credential['authentication_result'] ?? null,
            'exchanged' => true,
        ];
    }

    protected function protocolId(): int
    {
        return AgentProtocol::UCP;
    }

    protected function toGatewayInstrument(array $paymentData): array
    {
        // UCP は controller の resolvePaymentData() が exchangePaymentToken() 済みの中立データを渡す。
        // ただし handler_id を解決できないときは空配列が渡るため、トークンの検証はここでも行う (fail-closed)。
        return array_merge($paymentData, [
            'token' => PaymentTokenExtractor::requireToken($paymentData),
        ]);
    }
}
