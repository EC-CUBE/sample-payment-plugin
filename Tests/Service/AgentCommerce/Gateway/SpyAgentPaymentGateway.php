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

use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\AgentPaymentGatewayInterface;
use Plugin\SamplePayment44\Service\AgentCommerce\Gateway\GatewayResult;

/**
 * 呼び出しを記録し、結果を差し替えられるテスト用ゲートウェイ.
 *
 * ハンドラが「何を PSP へ渡したか」「capture で何を使ったか」を検証するために用いる
 * (モックゲートウェイでは結果しか観測できず、トークンの再償還のような呼び出し側の誤りを追えない)。
 */
final class SpyAgentPaymentGateway implements AgentPaymentGatewayInterface
{
    /** @var array<int, array{currency: string, amount: int, instrument: array<string, mixed>, context: array<string, mixed>}> */
    public array $authorizeCalls = [];

    /** @var array<int, array{currency: string, amount: int, transactionId: string, context: array<string, mixed>}> */
    public array $captureCalls = [];

    public function __construct(
        private readonly GatewayResult|\Throwable $authorizeResult,
        private readonly GatewayResult|\Throwable|null $captureResult = null,
    ) {
    }

    public function authorize(string $currencyCode, int $amount, array $instrument, array $context = []): GatewayResult
    {
        $this->authorizeCalls[] = ['currency' => $currencyCode, 'amount' => $amount, 'instrument' => $instrument, 'context' => $context];

        if ($this->authorizeResult instanceof \Throwable) {
            throw $this->authorizeResult;
        }

        return $this->authorizeResult;
    }

    public function capture(string $currencyCode, int $amount, string $transactionId, array $context = []): GatewayResult
    {
        $this->captureCalls[] = ['currency' => $currencyCode, 'amount' => $amount, 'transactionId' => $transactionId, 'context' => $context];

        if ($this->captureResult instanceof \Throwable) {
            throw $this->captureResult;
        }

        return $this->captureResult ?? GatewayResult::succeeded($transactionId, ['captured' => true]);
    }
}
