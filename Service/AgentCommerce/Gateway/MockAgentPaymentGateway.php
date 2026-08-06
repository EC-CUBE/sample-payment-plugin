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
 * ## トークン規約
 *
 * `instrument['token']` に対する **大文字小文字を区別する部分一致**で判定する
 * (`e2e-acp-spt-3ds` は `-3ds` を含むので追加認証シナリオ。`3DS` や `3ds` 単体では一致しない)。
 * 複数のマーカーを含むトークンのために**評価順を規定**する。上から順に最初に一致したものを採用する:
 *
 * | 順 | マーカー          | authorize の結果                                     |
 * |----|-------------------|------------------------------------------------------|
 * | 1  | `-fraud`          | 不正検知 (FAILED・再試行不可 → セッションは canceled) |
 * | 2  | `-decline`        | 与信拒否 (FAILED・再試行可 → セッションは ready)      |
 * | 3  | `-3ds`            | 追加認証 (REQUIRES_ACTION)。※下記                     |
 * | 4  | `-processing`     | 非同期処理中 (PROCESSING)                             |
 * | 5  | `-capture-fail`   | 与信は成功 (REQUIRES_CAPTURE) し capture で失敗する    |
 * | 6  | それ以外          | 与信成功 (REQUIRES_CAPTURE) → capture で SUCCEEDED    |
 *
 * ※ `-3ds` は `authentication_result` を伴わない場合のみ REQUIRES_ACTION。伴う場合は認証済みとして
 *   与信へ進む (再開 complete)。`authentication_result` は**空でない文字列または空でない配列**のみ
 *   認証済みと見なす (`null` / `''` / `[]` / `false` は未認証)。
 *
 * ## 実 PSP の制約を模す
 *
 * 冪等で寛容なモックは呼び出し側の誤りを隠す。以下は実 PSP で必ず失敗する操作なので、モックでも失敗させる:
 *
 * - トークン無しの与信 (ハンドラ側の検証漏れに対する二次防衛)
 * - 同一トークンを別注文で再償還する (共有支払トークンは 1 取引限り)
 * - 与信していない取引 / 既に capture 済みの取引に対する capture
 * - 与信額と異なる金額での capture
 *
 * 台帳はインスタンス変数のためリクエスト内でのみ有効 (プロセスを跨ぐ検証はユニットテストで行う)。
 */
class MockAgentPaymentGateway implements AgentPaymentGatewayInterface
{
    private const MARKER_FRAUD = '-fraud';

    private const MARKER_DECLINE = '-decline';

    private const MARKER_3DS = '-3ds';

    private const MARKER_PROCESSING = '-processing';

    private const MARKER_CAPTURE_FAIL = '-capture-fail';

    /**
     * 与信済み取引の台帳.
     *
     * @var array<string, array{token: string, currency: string, amount: int, captured: bool}>
     */
    private array $transactions = [];

    /**
     * 償還済みトークン → 紐づく注文参照 (共有支払トークンのワンショット性を模す).
     *
     * @var array<string, string>
     */
    private array $redeemedTokens = [];

    public function authorize(string $currencyCode, int $amount, array $instrument, array $context = []): GatewayResult
    {
        $token = $this->token($instrument);
        if ($token === '') {
            return GatewayResult::failed('invalid_payment_data', 'A payment token is required to authorize.', true);
        }

        $orderReference = $this->orderReference($context);
        $redeemedFor = $this->redeemedTokens[$token] ?? null;
        if ($redeemedFor !== null && $redeemedFor !== $orderReference) {
            // 共有支払トークンは 1 取引にしか使えない。別注文での再利用は不可逆な失敗とする。
            return GatewayResult::failed('token_already_redeemed', 'The payment token has already been redeemed for another order.', false);
        }
        $this->redeemedTokens[$token] = $orderReference;

        // 再開 complete では中断前の取引を続行する (実 PSP の「既存 PaymentIntent を confirm」に相当)。
        // 与えられなければ入力から決定的に導出する。
        $transactionId = $this->existingTransactionId($instrument)
            ?? $this->transactionId($currencyCode, $amount, $token, $orderReference);
        $metadata = $this->metadata($transactionId, $currencyCode, $amount);

        if (str_contains($token, self::MARKER_FRAUD)) {
            return GatewayResult::failed('card_not_supported', 'The card was blocked by fraud detection.', false, $transactionId);
        }

        if (str_contains($token, self::MARKER_DECLINE)) {
            return GatewayResult::failed('card_declined', 'The card was declined.', true, $transactionId);
        }

        if (str_contains($token, self::MARKER_3DS) && !$this->isAuthenticated($instrument)) {
            // 追加認証の中断。再開時に PSP 参照を辿れるよう metadata も返す。
            return GatewayResult::requiresAction($this->authenticationActionData(), $transactionId, $metadata);
        }

        if (str_contains($token, self::MARKER_PROCESSING)) {
            return GatewayResult::processing($transactionId, $metadata);
        }

        $this->transactions[$transactionId] = [
            'token' => $token,
            'currency' => $currencyCode,
            'amount' => $amount,
            'captured' => false,
        ];

        return GatewayResult::requiresCapture($transactionId, $metadata);
    }

    public function capture(string $currencyCode, int $amount, string $transactionId, array $context = []): GatewayResult
    {
        $transaction = $this->transactions[$transactionId] ?? null;
        if ($transaction === null) {
            return GatewayResult::failed('transaction_not_found', 'No authorized transaction matches the given reference.', false, $transactionId);
        }

        if ($transaction['captured']) {
            return GatewayResult::failed('transaction_already_captured', 'The transaction has already been captured.', false, $transactionId);
        }

        if ($transaction['currency'] !== $currencyCode || $transaction['amount'] !== $amount) {
            return GatewayResult::failed('capture_amount_mismatch', 'The capture amount does not match the authorized amount.', false, $transactionId);
        }

        if (str_contains($transaction['token'], self::MARKER_CAPTURE_FAIL)) {
            // 与信は通ったが売上確定に失敗する系。与信自体は PSP 側に残るため再試行可とする。
            return GatewayResult::failed('capture_failed', 'The capture was rejected by the gateway.', true, $transactionId);
        }

        $this->transactions[$transactionId]['captured'] = true;

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

        return is_string($token) ? trim($token) : '';
    }

    /**
     * 追加認証が完了しているか.
     *
     * ACP の `authentication_result` は文字列とも構造体とも成り得るため、**空でない文字列**または
     * **空でない配列**のみ認証済みと見なす。`null` / `''` / `[]` / `false` / 数値は未認証扱い
     * (緩い判定は 3DS 中断シナリオを誤って成功させる)。
     *
     * @param array<string, mixed> $instrument
     */
    private function isAuthenticated(array $instrument): bool
    {
        $result = $instrument['authentication_result'] ?? null;

        if (is_string($result)) {
            return trim($result) !== '';
        }

        if (is_array($result)) {
            return $result !== [];
        }

        return false;
    }

    /**
     * 中断前の complete から引き継がれた取引識別子 (再開時のみ存在する).
     *
     * @param array<string, mixed> $instrument
     */
    private function existingTransactionId(array $instrument): ?string
    {
        $transactionId = $instrument['transaction_id'] ?? null;
        if (!is_string($transactionId) || '' === trim($transactionId)) {
            return null;
        }

        return trim($transactionId);
    }

    /**
     * 取引識別子を決定的に導出する (状態を持たずに authorize と capture・再開 complete で一致させる).
     *
     * 通貨・金額・トークンだけでは、同額・同トークンの別注文が同じ識別子になってしまうため
     * 注文参照も混ぜる。同一注文なら値は変わらないので、3DS 再開時にも同じ識別子が得られる。
     */
    private function transactionId(string $currencyCode, int $amount, string $token, string $orderReference): string
    {
        $seed = implode(':', [$currencyCode, (string) $amount, $token, $orderReference]);

        return 'pi_mock_'.substr(hash('sha256', $seed), 0, 24);
    }

    /**
     * 注文を識別する文字列 (受注番号 → 受注 ID の順に採用).
     *
     * @param array<string, mixed> $context
     */
    private function orderReference(array $context): string
    {
        foreach (['order_no', 'order_id'] as $key) {
            $value = $context[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_int($value)) {
                return (string) $value;
            }
        }

        return '';
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
