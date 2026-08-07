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

namespace Plugin\SamplePayment44\Tests\Service\AgentCommerce\Ucp;

use Eccube\Entity\Master\AgentProtocol;
use Eccube\Service\AgentCommerce\MinorUnitConverter;
use Eccube\Service\AgentCommerce\Payment\PaymentOutcomeStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\AgentPaymentGatewayInterface;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayResult;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\MockAgentPaymentGateway;
use Plugin\SamplePayment44\Service\AgentCommerce\Ucp\UcpSampleCardHandler;
use Plugin\SamplePayment44\Service\Method\Convenience;
use Plugin\SamplePayment44\Service\Method\CreditCard;
use Plugin\SamplePayment44\Tests\Service\AgentCommerce\AgentCardHandlerTestCase;
use Plugin\SamplePayment44\Tests\Service\AgentCommerce\Gateway\SpyAgentPaymentGateway;
use Psr\Log\NullLogger;

/**
 * UCP 用サンプルカード決済ハンドラの単体テスト.
 *
 * UCP は ACP と異なり、再開 complete の入力も `payment.instruments[].credential` 経由でしか届かない
 * (本体 UcpCheckoutController::resolvePaymentData)。exchangePaymentToken() が認証結果を落とすと
 * requires_action から復帰できなくなるため、ACP と対称のシナリオで検証する。
 */
class UcpSampleCardHandlerTest extends AgentCardHandlerTestCase
{
    public function testSupportsOnlyUcpOrdersWithTokenizedCreditCard(): void
    {
        $handler = $this->handler(new MockAgentPaymentGateway());

        $this->assertTrue($handler->supports($this->createOrder(AgentProtocol::UCP, CreditCard::class)));
        $this->assertFalse($handler->supports($this->createOrder(AgentProtocol::ACP, CreditCard::class)), '別プロトコルの受注は扱わない');
        $this->assertFalse($handler->supports($this->createOrder(AgentProtocol::UCP, Convenience::class)), '別の支払方法は扱わない');
        $this->assertFalse($handler->supports($this->createOrder(null, CreditCard::class)), '通常購入の受注は扱わない');
    }

    public function testExchangePaymentTokenCarriesAuthenticationResult(): void
    {
        $exchanged = $this->handler(new MockAgentPaymentGateway())
            ->exchangePaymentToken(['token' => 'tok-3ds', 'authentication_result' => ['outcome' => 'authenticated']]);

        $this->assertSame('tok-3ds', $exchanged['token']);
        $this->assertSame(
            ['outcome' => 'authenticated'],
            $exchanged['authentication_result'],
            'UCP は認証結果もクレデンシャル経由でしか届かない。落とすと requires_action から復帰できない',
        );
    }

    public function testExchangePaymentTokenDoesNotThrowOnMissingToken(): void
    {
        // 本体 UcpCheckoutController::resolvePaymentData() は complete の状態機械の外側で本メソッドを
        // 呼ぶため、ここで例外を投げるとビジネス系エラーでなく HTTP 500 になる。
        $exchanged = $this->handler(new MockAgentPaymentGateway())->exchangePaymentToken(['type' => 'card']);

        $this->assertNull($exchanged['token'], 'トークンを解決できなくても例外にせず null で返す');
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function provideUnresolvablePaymentData(): array
    {
        return [
            'handler_id を解決できず空配列が渡る' => [[]],
            'credential に token が無い' => [['type' => 'card']],
            'token が空文字' => [['token' => '']],
        ];
    }

    /**
     * @param array<string, mixed> $paymentData
     */
    #[DataProvider('provideUnresolvablePaymentData')]
    public function testAuthorizeWithUnresolvablePaymentDataFailsClosed(array $paymentData): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('should_not_be_used'));
        $handler = $this->handler($spy);
        $outcome = $handler->authorize($this->createOrder(AgentProtocol::UCP, CreditCard::class), $handler->exchangePaymentToken($paymentData));

        $this->assertSame(PaymentOutcomeStatus::FAILED, $outcome->status, 'credential 不在の complete を与信成功にしてはならない');
        $this->assertSame('invalid_payment_data', $outcome->errorCode);
        $this->assertTrue($outcome->retryable, 'credential を直して再送すれば回復するため ready へ戻す');
        $this->assertSame([], $spy->authorizeCalls, '支払データを解決できない要求は PSP へ送らない');
    }

    public function testAuthorizeReturnsAuthorizedNotCompleted(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('pi_1'));
        $handler = $this->handler($spy);
        $order = $this->createOrder(AgentProtocol::UCP, CreditCard::class);

        $outcome = $handler->authorize($order, $handler->exchangePaymentToken(['token' => 'tok_ok']));

        $this->assertSame(PaymentOutcomeStatus::AUTHORIZED, $outcome->status, '与信のみの結果を COMPLETED に潰さない');
        $this->assertSame('tok_ok', $spy->authorizeCalls[0]['instrument']['token'] ?? null);
    }

    public function testThreeDomainSecureInterruptsThenResumesThroughCredential(): void
    {
        $handler = $this->handler(new MockAgentPaymentGateway());
        $order = $this->createOrder(AgentProtocol::UCP, CreditCard::class);

        // 初回 complete: 認証結果なしのクレデンシャル。
        $interrupted = $handler->authorize($order, $handler->exchangePaymentToken(['token' => 'e2e-ucp-3ds']));
        $this->assertSame(PaymentOutcomeStatus::REQUIRES_ACTION, $interrupted->status, '3DS は失敗でなく中断');

        // 再開 complete: エージェントは credential に認証結果を載せて再送する。
        $resumed = $handler->authorize($order, $handler->exchangePaymentToken([
            'token' => 'e2e-ucp-3ds',
            'authentication_result' => ['outcome' => 'authenticated'],
        ]));
        $this->assertSame(PaymentOutcomeStatus::AUTHORIZED, $resumed->status, 'UCP でも認証結果を伴えば再開できる');

        $captured = $handler->capture($order, [], $resumed);
        $this->assertSame(PaymentOutcomeStatus::COMPLETED, $captured->status);
    }

    public function testCaptureFailureIsRetryableAndKeepsReference(): void
    {
        $handler = $this->handler(new MockAgentPaymentGateway());
        $order = $this->createOrder(AgentProtocol::UCP, CreditCard::class);

        $authorization = $handler->authorize($order, $handler->exchangePaymentToken(['token' => 'e2e-ucp-capture-fail']));
        $this->assertSame(PaymentOutcomeStatus::AUTHORIZED, $authorization->status);

        $captured = $handler->capture($order, [], $authorization);
        $this->assertSame(PaymentOutcomeStatus::FAILED, $captured->status, 'capture 失敗の分岐を検証できる規約を持つ');
        $this->assertSame('capture_failed', $captured->errorCode);
        // UCP はエージェントが complete のたびに credential を送り直すため、ready からの再試行で
        // exchange → authorize をやり直せる (ACP の SPT と非対称なのはここ)。
        $this->assertTrue($captured->retryable, 'credential を再送すれば新規 authorize からやり直せる');
        $this->assertSame($authorization->transactionId, $captured->transactionId);
    }

    public function testCaptureNeverReturnsNonTerminalOutcome(): void
    {
        // コアの契約は「capture の戻り値は COMPLETED か FAILED のみ」。
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('pi_u1'), GatewayResult::processing('pi_u1'));
        $order = $this->createOrder(AgentProtocol::UCP, CreditCard::class);

        $authorization = $this->handler($spy)->authorize($order, ['token' => 'tok_ok']);
        $outcome = $this->handler($spy)->capture($order, [], $authorization);

        $this->assertSame(PaymentOutcomeStatus::FAILED, $outcome->status);
        $this->assertSame('capture_unexpected_status', $outcome->errorCode);
        $this->assertTrue($outcome->retryable, 'UCP は再 authorize できるため契約違反でも ready へ戻す');
    }

    private function handler(AgentPaymentGatewayInterface $gateway): UcpSampleCardHandler
    {
        return new UcpSampleCardHandler(new MinorUnitConverter(), $gateway, new NullLogger());
    }
}
