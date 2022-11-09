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

namespace Plugin\SamplePayment\Repository;

use Eccube\Repository\AbstractRepository;
use Plugin\SamplePayment\Entity\CvsPaymentStatus;
use Symfony\Bridge\Doctrine\RegistryInterface;

class CvsPaymentStatusRepository extends AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CvsPaymentStatus::class);
    }
}
