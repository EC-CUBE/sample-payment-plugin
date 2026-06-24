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

namespace Plugin\SamplePayment42\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\AbstractMasterEntity;
use Plugin\SamplePayment42\Repository\CvsPaymentStatusRepository;

/**
 * CvsPaymentStatus
 */
#[ORM\Table(name: 'plg_sample_payment_cvs_payment_status')]
#[ORM\Entity(repositoryClass: CvsPaymentStatusRepository::class)]
class CvsPaymentStatus extends AbstractMasterEntity
{
    /**
     * 定数名は適宜変更してください.
     */

    /**
     * 未決済
     */
    public const OUTSTANDING = 1;
    /**
     * 要求成功
     */
    public const REQUEST = 2;
    /**
     * 決済完了
     */
    public const COMPLETE = 3;
    /**
     * 決済失敗
     */
    public const FAILURE = 4;
    /**
     * 期限切れ
     */
    public const EXPIRED = 5;
}
