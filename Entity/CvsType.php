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

namespace Plugin\SamplePayment\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\AbstractMasterEntity;

/**
 * コンビニ種別
 *
 * @ORM\Table(name="plg_sample_payment_cvs_type")
 * @ORM\Entity(repositoryClass="Plugin\SamplePayment\Repository\CvsTypeRepository")
 */
class CvsType extends AbstractMasterEntity
{
    /**
     * 定数名は適宜変更してください.
     */

    /**
     * ローソン
     */
    const LAWSON = '00001';
    /**
     * ミニストップ
     */
    const MINISTOP = '00005';

    /**
     * セブンイレブン
     */
    const SEVENELEVEN = '00007';
}
