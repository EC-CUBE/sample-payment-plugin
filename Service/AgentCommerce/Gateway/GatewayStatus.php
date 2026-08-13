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

namespace Plugin\SamplePayment44\Service\AgentCommerce\Gateway;

/**
 * 決済ゲートウェイの処理結果ステータス.
 *
 * PSP 非依存だが、実装の移植容易性のため **Stripe PaymentIntent.status の語彙に整合**させている
 * (stripe-payment-plugin への移植時、本 enum をそのまま PaymentIntent.status の写像先にできる)。
 * ハンドラ ({@link \Plugin\SamplePayment44\Service\AgentCommerce\AbstractAgentCardHandler}) が
 * これをコアの {@link \Eccube\Service\AgentCommerce\Payment\PaymentOutcome} へ変換する。
 */
enum GatewayStatus: string
{
    /** 売上確定済 (capture 成功). PaymentIntent.status=succeeded 相当. */
    case SUCCEEDED = 'succeeded';

    /** 与信済・キャプチャ待ち (authorize 成功). PaymentIntent.status=requires_capture 相当. */
    case REQUIRES_CAPTURE = 'requires_capture';

    /** 追加認証が必要 (EMV-3DS challenge 等). PaymentIntent.status=requires_action 相当. */
    case REQUIRES_ACTION = 'requires_action';

    /** 非同期処理中 (PSP の確定通知待ち). PaymentIntent.status=processing 相当. */
    case PROCESSING = 'processing';

    /** 拒否・エラー. PaymentIntent.status=requires_payment_method / canceled 相当. */
    case FAILED = 'failed';
}
