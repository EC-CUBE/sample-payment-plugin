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

namespace Plugin\SamplePayment44;

use Eccube\Common\EccubeTwigBlock;

class SamplePaymentTwigBlock implements EccubeTwigBlock
{
    /**
     * @return array
     */
    public static function getTwigBlock(): array
    {
        return [
            '@SamplePayment44/credit.twig',
            '@SamplePayment44/credit_confirm.twig',
        ];
    }
}
