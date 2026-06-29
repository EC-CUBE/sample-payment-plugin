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
 * エージェント決済のゲートウェイ抽象 (PSP 隔離レイヤ).
 *
 * プロトコル層 (ACP/UCP ハンドラ) と PSP 実装を分離するための境界。サンプル実装は
 * {@link MockAgentPaymentGateway} で、これを {@link StripeAgentPaymentGateway} 等へ差し替えれば
 * stripe-payment-plugin などへ移植できる (ハンドラ・プロトコル層は無改変)。
 *
 * 金額は **minor unit 整数** (コアの {@link \Eccube\Service\AgentCommerce\MinorUnitConverter} で変換済)
 * で受け取り、通貨はゼロデシマル判定のため別途渡す。
 */
interface AgentPaymentGatewayInterface
{
    /**
     * 与信 (オーソリ) を行う.
     *
     * @param string               $currencyCode ISO 4217 (例: "JPY"/"USD")
     * @param int                  $amount       minor unit 整数 (JPY 等ゼロデシマルはそのままの数値)
     * @param array<string, mixed> $instrument   中立な支払データ (token/credential・3DS の authentication_result 等)
     * @param array<string, mixed> $context      注文番号等の付帯情報 (冪等キー生成・追跡用)
     */
    public function authorize(string $currencyCode, int $amount, array $instrument, array $context = []): GatewayResult;

    /**
     * 売上確定 (キャプチャ) を行う. {@link authorize()} が成功した取引に対してのみ呼ぶ.
     *
     * @param string               $currencyCode ISO 4217
     * @param int                  $amount       minor unit 整数
     * @param array<string, mixed> $instrument   authorize と同一の中立な支払データ (取引識別の導出に用いる)
     * @param array<string, mixed> $context      付帯情報
     */
    public function capture(string $currencyCode, int $amount, array $instrument, array $context = []): GatewayResult;
}
