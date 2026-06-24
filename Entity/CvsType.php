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
use Plugin\SamplePayment42\Repository\CvsTypeRepository;

/**
 * コンビニ種別
 */
#[ORM\Table(name: 'plg_sample_payment_cvs_type')]
#[ORM\Entity(repositoryClass: CvsTypeRepository::class)]
class CvsType extends AbstractMasterEntity
{
    /**
     * 定数名は適宜変更してください.
     */

    /**
     * ローソン
     */
    public const LAWSON = '00001';
    /**
     * ミニストップ
     */
    public const MINISTOP = '00005';

    /**
     * セブンイレブン
     */
    public const SEVENELEVEN = '00007';
}
