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

namespace Plugin\SamplePayment44\Service\AgentCommerce;

use Plugin\SamplePayment44\Service\AgentCommerce\Exception\InvalidPaymentDataException;

/**
 * ACP / UCP の支払データから支払トークンを取り出すヘルパ.
 *
 * ACP の `payment_data` と UCP の `payment.instruments[].credential` は形が異なるが、
 * どちらも「トークンを 1 つ持つ」点は共通なので抽出規則を 1 箇所へ集約する
 * (プロトコルごとに実装すると対応範囲がずれ、片方だけ検証が緩くなる)。
 *
 * **見つからない場合に空文字を返さない**のが要点。空文字を下流へ流すと、トークン規約に
 * 一致しないぶん「正常な支払」と見なされ、無与信のまま受注が確定しうる。解決できない入力は
 * {@link requireToken()} で例外にし、呼び出し側が fail-closed で失敗へ写像する。
 */
final class PaymentTokenExtractor
{
    /**
     * 支払トークンを取り出す. 解決できない場合は例外を投げる (fail-closed).
     *
     * @param array<string, mixed> $source ACP の payment_data / UCP の credential
     *
     * @throws InvalidPaymentDataException トークンが存在しない、または空のとき
     */
    public static function requireToken(array $source): string
    {
        $token = self::findToken($source);
        if ($token === null) {
            throw new InvalidPaymentDataException('A payment token is required but was not present in the payment data.');
        }

        return $token;
    }

    /**
     * 支払トークンを取り出す. 解決できない場合は null を返す.
     *
     * 対応する形 (先に見つかったものを採用):
     * - `['token' => 'tok_x']`
     * - `['credential' => 'tok_x']` / `['credential' => ['token' => 'tok_x']]`
     * - `['instrument' => ['credential' => 'tok_x']]` / `['instrument' => ['credential' => ['token' => 'tok_x']]]`
     *
     * @param array<string, mixed> $source
     */
    public static function findToken(array $source): ?string
    {
        $candidates = [
            $source['token'] ?? null,
            $source['credential'] ?? null,
            $source['instrument']['credential'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $token = self::normalize($candidate);
            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }

    /**
     * 文字列またはトークンを持つ配列を、空でないトークン文字列へ正規化する.
     */
    private static function normalize(mixed $candidate): ?string
    {
        if (is_array($candidate)) {
            $candidate = $candidate['token'] ?? null;
        }

        if (!is_string($candidate)) {
            return null;
        }

        $token = trim($candidate);

        return $token === '' ? null : $token;
    }
}
