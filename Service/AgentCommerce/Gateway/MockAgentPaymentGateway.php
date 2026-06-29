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
 * テスト・サンプル用のモック決済ゲートウェイ.
 *
 * 外部 PSP へ接続せず、支払トークンの規約で結果を**決定的に**分岐する。CI の結合 E2E
 * (本体↔プラグイン) を Stripe/ChatGPT 非依存で緑化するための実装であり、stripe-payment-plugin
 * では本クラスを {@link AgentPaymentGatewayInterface} の Stripe 実装へ差し替える。
 *
 * トークン規約 (instrument['token'] の部分一致):
 * - `*-3ds*`        : 追加認証 (REQUIRES_ACTION)。再開時に `authentication_result` を伴えば成功。
 * - `*-decline*`    : 与信拒否 (FAILED・再試行可)。
 * - `*-fraud*`      : 不正検知 (FAILED・再試行不可)。
 * - `*-processing*` : 非同期処理中 (PROCESSING)。
 * - それ以外        : 与信成功 (REQUIRES_CAPTURE) → capture で SUCCEEDED。
 */
class MockAgentPaymentGateway implements AgentPaymentGatewayInterface
{
    public function authorize(string $currencyCode, int $amount, array $instrument, array $context = []): GatewayResult
    {
        $token = $this->token($instrument);
        $transactionId = $this->transactionId($currencyCode, $amount, $token);

        // 3DS challenge の再開: authentication_result を伴えば認証完了として与信する。
        $hasAuthenticationResult = ($instrument['authentication_result'] ?? null) !== null
            && $instrument['authentication_result'] !== '';

        if (str_contains($token, 'fraud')) {
            return GatewayResult::failed('card_not_supported', 'The card was blocked by fraud detection.', false);
        }

        if (str_contains($token, 'decline')) {
            return GatewayResult::failed('card_declined', 'The card was declined.', true);
        }

        if (str_contains($token, '3ds') && !$hasAuthenticationResult) {
            return GatewayResult::requiresAction($this->authenticationActionData(), $transactionId);
        }

        if (str_contains($token, 'processing')) {
            return GatewayResult::processing($transactionId, $this->metadata($transactionId, $currencyCode, $amount));
        }

        return GatewayResult::requiresCapture($transactionId, $this->metadata($transactionId, $currencyCode, $amount));
    }

    public function capture(string $currencyCode, int $amount, array $instrument, array $context = []): GatewayResult
    {
        $token = $this->token($instrument);
        $transactionId = $this->transactionId($currencyCode, $amount, $token);

        return GatewayResult::succeeded(
            $transactionId,
            array_merge($this->metadata($transactionId, $currencyCode, $amount), ['captured' => true]),
        );
    }

    /**
     * @param array<string, mixed> $instrument
     */
    private function token(array $instrument): string
    {
        $token = $instrument['token'] ?? '';

        return is_string($token) ? $token : '';
    }

    /**
     * 取引識別子を決定的に導出する (authorize と capture で一致させ、状態を持たないため).
     */
    private function transactionId(string $currencyCode, int $amount, string $token): string
    {
        return 'pi_mock_'.substr(hash('sha256', $currencyCode.':'.$amount.':'.$token), 0, 24);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(string $transactionId, string $currencyCode, int $amount): array
    {
        return [
            'gateway' => 'mock',
            'transaction_id' => $transactionId,
            'currency' => $currencyCode,
            'amount' => $amount,
        ];
    }

    /**
     * EMV-3DS の追加認証メタデータ (ACP の `authentication_metadata` に整合).
     *
     * @return array<string, mixed>
     */
    private function authenticationActionData(): array
    {
        return [
            'type' => '3ds',
            'authentication_required' => true,
            'authentication_metadata' => [
                'acquirer_details' => [
                    'acquirer_bin' => '000000',
                    'merchant_id' => 'mock_merchant',
                ],
                'directory_server' => [
                    'name' => 'visa',
                    'id' => 'A000000003',
                ],
            ],
        ];
    }
}
