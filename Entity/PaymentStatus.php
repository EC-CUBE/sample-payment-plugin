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

namespace Plugin\SamplePayment44\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\AbstractMasterEntity;
use Plugin\SamplePayment44\Repository\PaymentStatusRepository;

/**
 * PaymentStatus
 */
#[ORM\Table(name: 'plg_sample_payment_payment_status')]
#[ORM\Entity(repositoryClass: PaymentStatusRepository::class)]
class PaymentStatus extends AbstractMasterEntity
{
    /**
     * 定数名は適宜変更してください.
     */

    /**
     * 未決済
     */
    public const OUTSTANDING = 1;
    /**
     * 有効性チェック済
     */
    public const ENABLED = 2;
    /**
     * 仮売上
     */
    public const PROVISIONAL_SALES = 3;
    /**
     * 実売上
     */
    public const ACTUAL_SALES = 4;
    /**
     * キャンセル
     */
    public const CANCEL = 5;
}
