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

namespace Plugin\SamplePayment44\Tests\Service\AgentCommerce\Gateway;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayResult;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayStatus;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\MockAgentPaymentGateway;

/**
 * モックゲートウェイの単体テスト (DB・コンテナ非依存).
 *
 * トークン規約の判定・評価順と、実 PSP の制約 (ワンショット償還・capture の前提条件) を検証する。
 * 寛容なモックは呼び出し側の誤りを隠すため、**異常系が主目的**のテストである。
 */
class MockAgentPaymentGatewayTest extends TestCase
{
    private const CURRENCY = 'JPY';

    private const AMOUNT = 3000;

    private MockAgentPaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new MockAgentPaymentGateway();
    }

    public function testAuthorizeWithoutTokenFailsClosed(): void
    {
        $result = $this->authorize([]);

        $this->assertSame(GatewayStatus::FAILED, $result->status, 'トークン無しの与信を成功させてはならない (無与信での受注確定を防ぐ)');
        $this->assertSame('invalid_payment_data', $result->errorCode);
        $this->assertTrue($result->retryable, 'payment_data を直して再送すれば回復するため retryable');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function provideBlankTokens(): array
    {
        return [
            '空文字' => [''],
            '空白のみ' => ['   '],
            'null' => [null],
            '配列' => [[]],
            '数値' => [0],
        ];
    }

    #[DataProvider('provideBlankTokens')]
    public function testAuthorizeWithBlankTokenFailsClosed(mixed $token): void
    {
        $result = $this->authorize(['token' => $token]);

        $this->assertSame(GatewayStatus::FAILED, $result->status, '空・型違いのトークンで与信を成功させてはならない');
        $this->assertSame('invalid_payment_data', $result->errorCode);
    }

    public function testPlainTokenIsAuthorizedButNotCaptured(): void
    {
        $result = $this->authorize(['token' => 'tok_plain']);

        $this->assertSame(GatewayStatus::REQUIRES_CAPTURE, $result->status, '通常のトークンは与信のみ成功し capture を要する');
        $this->assertNotNull($result->transactionId);
        $this->assertSame('mock', $result->metadata['gateway'] ?? null);
    }

    public function testFraudTokenIsUnrecoverableFailure(): void
    {
        $result = $this->authorize(['token' => 'tok-fraud']);

        $this->assertSame(GatewayStatus::FAILED, $result->status);
        $this->assertSame('card_not_supported', $result->errorCode);
        $this->assertFalse($result->retryable, '不正検知は再試行しても回復しない');
    }

    public function testDeclineTokenIsRetryableFailure(): void
    {
        $result = $this->authorize(['token' => 'tok-decline']);

        $this->assertSame(GatewayStatus::FAILED, $result->status);
        $this->assertSame('card_declined', $result->errorCode);
        $this->assertTrue($result->retryable, '与信拒否は別カードでの再試行が可能');
    }

    public function testProcessingTokenIsPending(): void
    {
        $result = $this->authorize(['token' => 'tok-processing']);

        $this->assertSame(GatewayStatus::PROCESSING, $result->status);
    }

    public function testMarkerEvaluationOrderIsDocumentedAndDeterministic(): void
    {
        // 複数マーカーを含むトークンは docblock の表の順 (fraud → decline → 3ds → processing) で解決する。
        $this->assertSame('card_not_supported', $this->authorize(['token' => 'tok-fraud-decline-3ds'])->errorCode, 'fraud が最優先');
        $this->assertSame('card_declined', $this->authorize(['token' => 'tok-decline-3ds'])->errorCode, 'decline は 3ds より優先');
        $this->assertSame(GatewayStatus::REQUIRES_ACTION, $this->authorize(['token' => 'tok-3ds-processing'])->status, '3ds は processing より優先');
    }

    public function testMarkerMatchingIsCaseSensitiveAndHyphenated(): void
    {
        // docblock の規約 (`*-3ds*`・大文字小文字を区別) と実装を一致させる。
        $this->assertSame(GatewayStatus::REQUIRES_CAPTURE, $this->authorize(['token' => 'tok-3DS'])->status, '大文字の 3DS はマーカーに一致しない');
        $this->assertSame(GatewayStatus::REQUIRES_CAPTURE, $this->authorize(['token' => 'tok3ds'])->status, 'ハイフンの無い 3ds はマーカーに一致しない');
        $this->assertSame(GatewayStatus::REQUIRES_ACTION, $this->authorize(['token' => 'tok-3ds'])->status, 'ハイフン付きの -3ds のみ一致する');
    }

    public function test3dsTokenRequiresActionAndCarriesReference(): void
    {
        $result = $this->authorize(['token' => 'tok-3ds']);

        $this->assertSame(GatewayStatus::REQUIRES_ACTION, $result->status);
        $this->assertSame('3ds', $result->actionData['type'] ?? null);
        $this->assertNotNull($result->transactionId, '再開時に辿れるよう取引識別子を返す');
        $this->assertNotSame([], $result->metadata, '中断時も PSP 参照を payment_data へ残せるよう metadata を返す');
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function provideAuthenticationResults(): array
    {
        return [
            '構造化された認証結果' => [['outcome' => 'authenticated'], true],
            '文字列の認証結果' => ['authenticated', true],
            'null' => [null, false],
            '空文字' => ['', false],
            '空白のみ' => ['   ', false],
            '空配列' => [[], false],
            'false' => [false, false],
            '数値の 1' => [1, false],
        ];
    }

    #[DataProvider('provideAuthenticationResults')]
    public function testAuthenticationResultIsEvaluatedStrictly(mixed $authenticationResult, bool $expectAuthenticated): void
    {
        $result = $this->authorize(['token' => 'tok-3ds', 'authentication_result' => $authenticationResult]);

        $this->assertSame(
            $expectAuthenticated ? GatewayStatus::REQUIRES_CAPTURE : GatewayStatus::REQUIRES_ACTION,
            $result->status,
            '認証済み判定は「空でない文字列/配列」のみ。緩い判定は 3DS 中断シナリオを誤って成功させる',
        );
    }

    public function testTransactionIdDependsOnOrderReference(): void
    {
        $first = $this->authorize(['token' => 'tok_same'], ['order_no' => 'A-1']);
        $second = $this->authorize(['token' => 'tok_same'], ['order_no' => 'A-2']);

        $this->assertNotSame($first->transactionId, $second->transactionId, '同額・同トークンでも別注文なら取引識別子は衝突しない');
    }

    public function testTransactionIdIsStableForSameOrder(): void
    {
        // 3DS の中断→再開は別リクエストになるため、同じ入力から同じ識別子が導ける必要がある。
        $first = $this->authorize(['token' => 'tok-3ds'], ['order_no' => 'A-1']);
        $resumed = $this->authorize(['token' => 'tok-3ds', 'authentication_result' => ['outcome' => 'authenticated']], ['order_no' => 'A-1']);

        $this->assertSame($first->transactionId, $resumed->transactionId, '同一注文の再開では同じ取引識別子になる');
    }

    public function testSameTokenCannotBeRedeemedForAnotherOrder(): void
    {
        $this->authorize(['token' => 'tok_shared'], ['order_no' => 'A-1']);
        $result = $this->authorize(['token' => 'tok_shared'], ['order_no' => 'A-2']);

        $this->assertSame(GatewayStatus::FAILED, $result->status, '共有支払トークンは 1 取引限り (実 PSP のワンショット償還を模す)');
        $this->assertSame('token_already_redeemed', $result->errorCode);
        $this->assertFalse($result->retryable);
    }

    public function testSameTokenIsAcceptedForSameOrderOnResume(): void
    {
        $this->authorize(['token' => 'tok-3ds'], ['order_no' => 'A-1']);
        $result = $this->authorize(['token' => 'tok-3ds', 'authentication_result' => ['outcome' => 'authenticated']], ['order_no' => 'A-1']);

        $this->assertSame(GatewayStatus::REQUIRES_CAPTURE, $result->status, '同一注文の再開 complete は同じトークンで続行できる');
    }

    public function testCaptureSucceedsForAuthorizedTransaction(): void
    {
        $authorization = $this->authorize(['token' => 'tok_plain']);
        $result = $this->gateway->capture(self::CURRENCY, self::AMOUNT, (string) $authorization->transactionId);

        $this->assertSame(GatewayStatus::SUCCEEDED, $result->status);
        $this->assertTrue($result->metadata['captured'] ?? false);
    }

    public function testCaptureRejectsUnknownTransaction(): void
    {
        $result = $this->gateway->capture(self::CURRENCY, self::AMOUNT, 'pi_mock_unknown');

        $this->assertSame(GatewayStatus::FAILED, $result->status, '与信していない取引は capture できない');
        $this->assertSame('transaction_not_found', $result->errorCode);
        $this->assertFalse($result->retryable);
    }

    public function testCaptureIsNotIdempotentlyRepeatable(): void
    {
        $authorization = $this->authorize(['token' => 'tok_plain']);
        $this->gateway->capture(self::CURRENCY, self::AMOUNT, (string) $authorization->transactionId);
        $result = $this->gateway->capture(self::CURRENCY, self::AMOUNT, (string) $authorization->transactionId);

        $this->assertSame(GatewayStatus::FAILED, $result->status, '二重 capture は実 PSP で失敗するためモックでも失敗させる');
        $this->assertSame('transaction_already_captured', $result->errorCode);
    }

    public function testCaptureRejectsAmountMismatch(): void
    {
        $authorization = $this->authorize(['token' => 'tok_plain']);
        $result = $this->gateway->capture(self::CURRENCY, self::AMOUNT + 1, (string) $authorization->transactionId);

        $this->assertSame(GatewayStatus::FAILED, $result->status);
        $this->assertSame('capture_amount_mismatch', $result->errorCode);
    }

    public function testCaptureFailTokenAuthorizesThenFailsOnCapture(): void
    {
        $authorization = $this->authorize(['token' => 'tok-capture-fail']);
        $this->assertSame(GatewayStatus::REQUIRES_CAPTURE, $authorization->status, 'capture 失敗シナリオでも与信は成功する');

        $result = $this->gateway->capture(self::CURRENCY, self::AMOUNT, (string) $authorization->transactionId);

        $this->assertSame(GatewayStatus::FAILED, $result->status, '本体の capture 失敗分岐を結合テストで通せるようにする');
        $this->assertSame('capture_failed', $result->errorCode);
        $this->assertTrue($result->retryable, '与信は PSP 側に残るため ready へ戻して再試行・取消を可能にする');
        $this->assertSame($authorization->transactionId, $result->transactionId, '失敗時も取引識別子を返す (照会・取消に必要)');
    }

    /**
     * @param array<string, mixed> $instrument
     * @param array<string, mixed> $context
     */
    private function authorize(array $instrument, array $context = ['order_no' => 'A-1']): GatewayResult
    {
        return $this->gateway->authorize(self::CURRENCY, self::AMOUNT, $instrument, $context);
    }
}
