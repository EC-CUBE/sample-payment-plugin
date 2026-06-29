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
 * 決済ゲートウェイ (authorize/capture) の処理結果 DTO.
 *
 * PSP 固有のレスポンスを {@link GatewayStatus} へ正規化して保持し、ハンドラが
 * コアの {@link \Eccube\Service\AgentCommerce\Payment\PaymentOutcome} へ写像する境界となる。
 */
final readonly class GatewayResult
{
    /**
     * @param array<string, mixed> $metadata   payment_data へ保持する PSP 参照等 (機微情報はマスキング済)
     * @param array<string, mixed> $actionData REQUIRES_ACTION 時の追加認証データ (3DS authentication_metadata 等)
     */
    public function __construct(
        public GatewayStatus $status,
        public ?string $transactionId = null,
        public array $metadata = [],
        public array $actionData = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $retryable = true,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function succeeded(string $transactionId, array $metadata = []): self
    {
        return new self(GatewayStatus::SUCCEEDED, $transactionId, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function requiresCapture(string $transactionId, array $metadata = []): self
    {
        return new self(GatewayStatus::REQUIRES_CAPTURE, $transactionId, $metadata);
    }

    /**
     * @param array<string, mixed> $actionData
     * @param array<string, mixed> $metadata
     */
    public static function requiresAction(array $actionData, ?string $transactionId = null, array $metadata = []): self
    {
        return new self(GatewayStatus::REQUIRES_ACTION, $transactionId, $metadata, $actionData);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function processing(?string $transactionId = null, array $metadata = []): self
    {
        return new self(GatewayStatus::PROCESSING, $transactionId, $metadata);
    }

    public static function failed(string $errorCode, string $errorMessage = '', bool $retryable = true): self
    {
        return new self(GatewayStatus::FAILED, null, [], [], $errorCode, $errorMessage, $retryable);
    }
}
