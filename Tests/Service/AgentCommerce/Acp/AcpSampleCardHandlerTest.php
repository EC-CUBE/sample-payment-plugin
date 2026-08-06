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

namespace Plugin\SamplePayment44\Tests\Service\AgentCommerce\Acp;

use Eccube\Entity\Master\AgentProtocol;
use Eccube\Service\AgentCommerce\MinorUnitConverter;
use Eccube\Service\AgentCommerce\Payment\PaymentOutcome;
use Eccube\Service\AgentCommerce\Payment\PaymentOutcomeStatus;
use Plugin\SamplePayment44\Service\AgentCommerce\Acp\AcpSampleCardHandler;
use Plugin\SamplePayment44\Service\AgentCommerce\Exception\InvalidPaymentDataException;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\AgentPaymentGatewayInterface;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayResult;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\MockAgentPaymentGateway;
use Plugin\SamplePayment44\Service\Method\CreditCard;
use Plugin\SamplePayment44\Service\Method\Convenience;
use Plugin\SamplePayment44\Tests\Service\AgentCommerce\AgentCardHandlerTestCase;
use Plugin\SamplePayment44\Tests\Service\AgentCommerce\Gateway\SpyAgentPaymentGateway;
use Psr\Log\NullLogger;

/**
 * ACP 用サンプルカード決済ハンドラの単体テスト.
 */
class AcpSampleCardHandlerTest extends AgentCardHandlerTestCase
{
    public function testSupportsOnlyAcpOrdersWithTokenizedCreditCard(): void
    {
        $handler = $this->handler(new MockAgentPaymentGateway());

        $this->assertTrue($handler->supports($this->createOrder(AgentProtocol::ACP, CreditCard::class)));
        $this->assertFalse($handler->supports($this->createOrder(AgentProtocol::UCP, CreditCard::class)), '別プロトコルの受注は扱わない');
        $this->assertFalse($handler->supports($this->createOrder(AgentProtocol::ACP, Convenience::class)), '別の支払方法は扱わない');
        $this->assertFalse($handler->supports($this->createOrder(null, CreditCard::class)), '通常購入の受注は扱わない');
        $this->assertFalse($handler->supports($this->createOrder(AgentProtocol::ACP, null)), '支払方法未割当の受注は扱わない');
    }

    public function testAuthorizeWithoutTokenFailsClosed(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('should_not_be_used'));
        $outcome = $this->handler($spy)->authorize($this->createOrder(AgentProtocol::ACP, CreditCard::class), ['handler_id' => AcpSampleCardHandler::HANDLER_ID]);

        $this->assertSame(PaymentOutcomeStatus::FAILED, $outcome->status, 'token を伴わない complete を与信成功にしてはならない');
        $this->assertSame('invalid_payment_data', $outcome->errorCode);
        $this->assertTrue($outcome->retryable, 'payment_data を直して再送すれば回復するため ready へ戻す');
        $this->assertSame([], $spy->authorizeCalls, '支払データを解決できない要求は PSP へ送らない');
    }

    public function testAuthorizeReturnsAuthorizedNotCompleted(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('pi_1', ['gateway' => 'spy']));
        $outcome = $this->handler($spy)->authorize($this->createOrder(AgentProtocol::ACP, CreditCard::class), ['token' => 'tok_ok']);

        $this->assertSame(PaymentOutcomeStatus::AUTHORIZED, $outcome->status, '与信のみの結果を COMPLETED に潰さない (auto-capture 型 PSP での二重売上を防ぐ)');
        $this->assertSame('pi_1', $outcome->transactionId);
        $this->assertSame(['gateway' => 'spy'], $outcome->metadata);
        $this->assertSame(self::AMOUNT_IN_MINOR_UNITS, $spy->authorizeCalls[0]['amount'], '金額は minor unit 整数で渡す');
        $this->assertSame('tok_ok', $spy->authorizeCalls[0]['instrument']['token'] ?? null);
    }

    public function testAutoCaptureGatewayIsMappedToCompleted(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::succeeded('ch_1'));
        $outcome = $this->handler($spy)->authorize($this->createOrder(AgentProtocol::ACP, CreditCard::class), ['token' => 'tok_ok']);

        $this->assertSame(PaymentOutcomeStatus::COMPLETED, $outcome->status, '売上まで確定した PSP 応答は COMPLETED (コアは capture を呼ばない)');
    }

    public function testCaptureUsesAuthorizationReferenceWithoutRedeemingTokenAgain(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('pi_2'));
        $handler = new class(new MinorUnitConverter(), $spy, new NullLogger()) extends AcpSampleCardHandler {
            public int $redeemCount = 0;

            public function redeemSharedPaymentToken(array $paymentData): array
            {
                ++$this->redeemCount;

                return parent::redeemSharedPaymentToken($paymentData);
            }
        };

        $order = $this->createOrder(AgentProtocol::ACP, CreditCard::class);
        $authorization = $handler->authorize($order, ['token' => 'tok_ok']);
        $capture = $handler->capture($order, ['token' => 'tok_ok'], $authorization);

        $this->assertSame(1, $handler->redeemCount, 'SPT の償還はワンショット。capture で再償還してはならない');
        $this->assertSame('pi_2', $spy->captureCalls[0]['transactionId'] ?? null, 'capture は authorize が返した取引識別子を使う');
        $this->assertSame(PaymentOutcomeStatus::COMPLETED, $capture->status);
    }

    public function testCaptureFailsWhenAuthorizationHasNoTransactionReference(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('pi_3'));
        $order = $this->createOrder(AgentProtocol::ACP, CreditCard::class);

        $outcome = $this->handler($spy)->capture($order, ['token' => 'tok_ok'], PaymentOutcome::authorized(null));

        $this->assertSame(PaymentOutcomeStatus::FAILED, $outcome->status);
        $this->assertSame('missing_transaction_reference', $outcome->errorCode);
        $this->assertFalse($outcome->retryable, '再試行しても回復しない実装エラー');
        $this->assertSame([], $spy->captureCalls, '取引識別子が無ければ PSP を呼ばない');
    }

    public function testGatewayExceptionOnAuthorizeIsMappedToRetryableFailure(): void
    {
        $spy = new SpyAgentPaymentGateway(new \RuntimeException('connection reset'));
        $outcome = $this->handler($spy)->authorize($this->createOrder(AgentProtocol::ACP, CreditCard::class), ['token' => 'tok_ok']);

        $this->assertSame(PaymentOutcomeStatus::FAILED, $outcome->status, 'PSP 通信例外は 500 でなく決済エラーとして返す');
        $this->assertSame('payment_gateway_error', $outcome->errorCode);
        $this->assertTrue($outcome->retryable);
        $this->assertStringNotContainsString('connection reset', $outcome->errorMessage ?? '', 'PSP の内部メッセージをエージェントへ露出しない');
    }

    public function testGatewayExceptionOnCaptureKeepsTransactionReferenceAndStaysRetryable(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('pi_4'), new \RuntimeException('timeout'));
        $order = $this->createOrder(AgentProtocol::ACP, CreditCard::class);

        $authorization = $this->handler($spy)->authorize($order, ['token' => 'tok_ok']);
        $outcome = $this->handler($spy)->capture($order, ['token' => 'tok_ok'], $authorization);

        $this->assertSame('payment_capture_error', $outcome->errorCode);
        $this->assertTrue($outcome->retryable, '与信は PSP 側に残るため canceled にせず ready へ戻す');
        $this->assertSame('pi_4', $outcome->transactionId, '取消・照会のため取引識別子を残す');
    }

    public function testRequiresActionCarriesActionDataMetadataAndReference(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresAction(['type' => '3ds'], 'pi_5', ['gateway' => 'spy']));
        $outcome = $this->handler($spy)->authorize($this->createOrder(AgentProtocol::ACP, CreditCard::class), ['token' => 'tok-3ds']);

        $this->assertSame(PaymentOutcomeStatus::REQUIRES_ACTION, $outcome->status);
        $this->assertSame(['type' => '3ds'], $outcome->actionData);
        $this->assertSame(['gateway' => 'spy'], $outcome->metadata, '再開時に PSP 参照を辿れるよう metadata を落とさない');
        $this->assertSame('pi_5', $outcome->transactionId);
    }

    public function testRedeemSharedPaymentTokenCarriesAuthenticationResult(): void
    {
        $instrument = $this->handler(new MockAgentPaymentGateway())
            ->redeemSharedPaymentToken(['token' => 'tok-3ds', 'authentication_result' => ['outcome' => 'authenticated']]);

        $this->assertSame('tok-3ds', $instrument['token']);
        $this->assertSame(['outcome' => 'authenticated'], $instrument['authentication_result'], '再開に必要な認証結果を落とさない');
    }

    public function testRedeemSharedPaymentTokenRejectsMissingToken(): void
    {
        $this->expectException(InvalidPaymentDataException::class);
        $this->handler(new MockAgentPaymentGateway())->redeemSharedPaymentToken(['handler_id' => AcpSampleCardHandler::HANDLER_ID]);
    }

    public function testThreeDomainSecureInterruptsThenResumesWithMockGateway(): void
    {
        $handler = $this->handler(new MockAgentPaymentGateway());
        $order = $this->createOrder(AgentProtocol::ACP, CreditCard::class);

        $interrupted = $handler->authorize($order, ['token' => 'e2e-acp-spt-3ds']);
        $this->assertSame(PaymentOutcomeStatus::REQUIRES_ACTION, $interrupted->status, '3DS は失敗でなく中断');

        // 本体は中断時の PSP 参照を payment_data に保持し、再開 complete で第 3 引数として渡す。
        $paymentReference = ['transaction_id' => $interrupted->transactionId];
        $resumed = $handler->authorize($order, ['token' => 'e2e-acp-spt-3ds', 'authentication_result' => ['outcome' => 'authenticated']], $paymentReference);
        $this->assertSame(PaymentOutcomeStatus::AUTHORIZED, $resumed->status, '認証結果を伴う再開で与信が成立する');
        $this->assertSame($interrupted->transactionId, $resumed->transactionId, '再開は中断前と同じ取引を続行する');

        $captured = $handler->capture($order, ['token' => 'e2e-acp-spt-3ds'], $resumed);
        $this->assertSame(PaymentOutcomeStatus::COMPLETED, $captured->status);
    }

    public function testPaymentReferenceFromInterruptedAttemptIsPassedToGateway(): void
    {
        $spy = new SpyAgentPaymentGateway(GatewayResult::requiresCapture('pi_resumed'));
        $order = $this->createOrder(AgentProtocol::ACP, CreditCard::class);

        $this->handler($spy)->authorize($order, ['token' => 'tok_ok'], ['transaction_id' => 'pi_prior', 'gateway' => 'spy']);

        $this->assertSame('pi_prior', $spy->authorizeCalls[0]['instrument']['transaction_id'] ?? null, '再開時は中断前の取引識別子を PSP へ引き継ぐ (トークンの再償還を避ける)');
    }

    private function handler(AgentPaymentGatewayInterface $gateway): AcpSampleCardHandler
    {
        return new AcpSampleCardHandler(new MinorUnitConverter(), $gateway, new NullLogger());
    }
}
