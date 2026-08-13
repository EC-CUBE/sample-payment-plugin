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
use Plugin\SamplePayment44\Service\AgentCommerce\PaymentTokenExtractor;

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
        // トークンを解決できない payment_data は例外にする (fail-closed)。
        return [
            'token' => PaymentTokenExtractor::requireToken($paymentData),
            'authentication_result' => $paymentData['authentication_result'] ?? null,
            'redeemed' => true,
        ];
    }

    protected function protocolId(): int
    {
        return AgentProtocol::ACP;
    }

    /**
     * ACP の capture 失敗は**再試行させない**.
     *
     * ready からの再 complete は新規 authorize から始まるが、その入口である
     * {@link redeemSharedPaymentToken()} は Shared Payment Token の償還であり、SPT はワンショットで
     * 2 度目が失敗する。再試行を許しても必ず失敗し、与信だけが PSP 側に残るため canceled にする
     * (与信の取消は PSP 側の運用に委ねる)。
     */
    protected function captureFailureIsRetryable(): bool
    {
        return false;
    }

    protected function toGatewayInstrument(array $paymentData): array
    {
        // SPT の償還はワンショットなので authorize からの 1 度だけ。capture は与信結果を使う
        // (基底が capture でこのメソッドを呼ばないことでそれを保証している)。
        return $this->redeemSharedPaymentToken($paymentData);
    }
}
