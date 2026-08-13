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

use Eccube\Entity\Master\AgentProtocol;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use PHPUnit\Framework\TestCase;

/**
 * エージェント決済ハンドラの単体テスト共通基底 (DB・コンテナ非依存).
 *
 * Doctrine エンティティは素の PHP オブジェクトとして組み立てられるため、ハンドラの検証には
 * DB もコンテナも要らない。境界値・異常系はこの層で網羅し、結合 E2E は経路の疎通に絞る。
 */
abstract class AgentCardHandlerTestCase extends TestCase
{
    protected const CURRENCY = 'JPY';

    protected const PAYMENT_TOTAL = '3000';

    protected const AMOUNT_IN_MINOR_UNITS = 3000;

    protected const ORDER_NO = 'A-1';

    /**
     * 検証用の受注を組み立てる.
     *
     * @param int|null    $protocolId  エージェントプロトコル (null なら通常購入の受注)
     * @param string|null $methodClass 支払方法の method_class (null なら支払方法未割当)
     */
    protected function createOrder(?int $protocolId, ?string $methodClass): Order
    {
        $Order = new Order();
        $Order
            ->setCurrencyCode(self::CURRENCY)
            ->setPaymentTotal(self::PAYMENT_TOTAL)
            ->setOrderNo(self::ORDER_NO);

        if ($methodClass !== null) {
            $Order->setPayment((new Payment())->setMethodClass($methodClass));
        }

        if ($protocolId !== null) {
            $Order->setAgentProtocol((new AgentProtocol())->setId($protocolId));
        }

        return $Order;
    }
}
