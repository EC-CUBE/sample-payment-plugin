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

namespace Plugin\SamplePayment44\Tests\Service\AgentCommerce;

use PHPUnit\Framework\TestCase;
use Plugin\SamplePayment44\Service\AgentCommerce\Exception\InvalidPaymentDataException;
use Plugin\SamplePayment44\Service\AgentCommerce\PaymentTokenExtractor;

/**
 * 支払トークン抽出の単体テスト (DB・コンテナ非依存).
 *
 * 「解決できないときに空文字を返さない」ことが本ヘルパの存在理由なので、
 * 異常系 (欠落・空・型違い) を正常系と同じ密度で検証する。
 */
class PaymentTokenExtractorTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function provideResolvableSources(): array
    {
        return [
            'token 直下' => [['token' => 'tok_1'], 'tok_1'],
            'credential が文字列' => [['credential' => 'tok_2'], 'tok_2'],
            'credential 配下の token' => [['credential' => ['token' => 'tok_3']], 'tok_3'],
            'instrument.credential が文字列' => [['instrument' => ['credential' => 'tok_4']], 'tok_4'],
            'instrument.credential 配下の token' => [['instrument' => ['credential' => ['token' => 'tok_5']]], 'tok_5'],
            '前後の空白は除去する' => [['token' => "  tok_6\n"], 'tok_6'],
            'token 直下を優先する' => [['token' => 'tok_7', 'credential' => 'tok_other'], 'tok_7'],
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideResolvableSources')]
    public function testFindTokenResolvesSupportedShapes(array $source, string $expected): void
    {
        $this->assertSame($expected, PaymentTokenExtractor::findToken($source), 'ACP/UCP 双方の支払データ形からトークンを解決する');
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function provideUnresolvableSources(): array
    {
        return [
            '空配列' => [[]],
            'handler_id のみ (トークン無し)' => [['handler_id' => 'card_tokenized']],
            'token が空文字' => [['token' => '']],
            'token が空白のみ' => [['token' => '   ']],
            'token が null' => [['token' => null]],
            'token が数値' => [['token' => 123]],
            'token が真偽値' => [['token' => true]],
            'credential が空配列' => [['credential' => []]],
            'credential の token が空' => [['credential' => ['token' => '']]],
            'instrument.credential が空配列' => [['instrument' => ['credential' => []]]],
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideUnresolvableSources')]
    public function testFindTokenReturnsNullWhenUnresolvable(array $source): void
    {
        $this->assertNull(
            PaymentTokenExtractor::findToken($source),
            '解決できない支払データで空文字を返してはならない (空文字はトークン規約に一致せず「正常な支払」と誤解される)',
        );
    }

    /**
     * @param array<string, mixed> $source
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideUnresolvableSources')]
    public function testRequireTokenThrowsWhenUnresolvable(array $source): void
    {
        $this->expectException(InvalidPaymentDataException::class);
        PaymentTokenExtractor::requireToken($source);
    }

    public function testRequireTokenReturnsResolvedToken(): void
    {
        $this->assertSame('tok_ok', PaymentTokenExtractor::requireToken(['token' => 'tok_ok']));
    }
}
