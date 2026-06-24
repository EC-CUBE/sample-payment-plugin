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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Eccube\Attribute\EntityExtension;
use Eccube\Entity\Customer;

#[EntityExtension(Customer::class)]
trait CustomerTrait
{
    /**
     * カードの記憶用カラム.
     *
     * @var string
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    public ?int $sample_payment_cards = null;
}
