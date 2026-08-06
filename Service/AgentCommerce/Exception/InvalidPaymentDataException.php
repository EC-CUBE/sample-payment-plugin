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

namespace Plugin\SamplePayment44\Service\AgentCommerce\Exception;

/**
 * エージェントから渡された支払データを解決できないことを表す例外.
 *
 * 支払トークンの欠落・空文字など、**決済を試みる前に確定する入力エラー**に用いる。
 * 決済ハンドラはこれを捕捉し、コアの {@link \Eccube\Service\AgentCommerce\Payment\PaymentOutcome::failed()}
 * (retryable) へ写像する。エージェントが payment_data を直して再送すれば回復できるため、
 * セッションは canceled ではなく ready へ戻す。
 */
class InvalidPaymentDataException extends \InvalidArgumentException
{
}
