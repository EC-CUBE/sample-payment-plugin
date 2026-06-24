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
use Eccube\Entity\Order;

#[EntityExtension(Order::class)]
trait OrderTrait
{
    /**
     * トークンを保持するカラム.
     *
     * dtb_order.sample_payment_token
     *
     * @var string
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $sample_payment_token = null;

    /**
     * クレジットカード番号の末尾4桁.
     * 永続化は行わず, 注文確認画面で表示する.
     *
     * @var string
     */
    private string $sample_payment_card_no_last4;

    /**
     * コンビニ用種別を保持するカラム.
     *
     * dtb_order.sample_payment_cvs_type_id
     *
     * @var CvsType
     */
    #[ORM\JoinColumn(name: 'sample_payment_cvs_type_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: CvsType::class)]
    private ?CvsType $SamplePaymentCvsType = null;

    /**
     * 決済ステータスを保持するカラム.
     *
     * dtb_order.sample_payment_payment_status_id
     *
     * @var SamplePaymentPaymentStatus
     */
    #[ORM\JoinColumn(name: 'sample_payment_payment_status_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: PaymentStatus::class)]
    private ?PaymentStatus $SamplePaymentPaymentStatus = null;

    /**
     * コンビニ用決済ステータスを保持するカラム.
     *
     * dtb_order.sample_payment_payment_status_id
     *
     * @var SamplePaymentCvsPaymentStatus
     */
    #[ORM\JoinColumn(name: 'sample_payment_cvs_payment_status_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: CvsPaymentStatus::class)]
    private ?CvsPaymentStatus $SamplePaymentCvsPaymentStatus = null;

    /**
     * @return string|null
     */
    public function getSamplePaymentToken(): ?string
    {
        return $this->sample_payment_token;
    }

    /**
     * @param string $sample_payment_token
     *
     * @return $this
     */
    public function setSamplePaymentToken(string $sample_payment_token)
    {
        $this->sample_payment_token = $sample_payment_token;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSamplePaymentCardNoLast4(): ?string
    {
        return $this->sample_payment_card_no_last4 ?? null;
    }

    /**
     * @param string $sample_payment_card_no_last4
     */
    public function setSamplePaymentCardNoLast4(string $sample_payment_card_no_last4)
    {
        $this->sample_payment_card_no_last4 = $sample_payment_card_no_last4;
    }

    /**
     * @return CvsType|null
     */
    public function getSamplePaymentCvsType(): ?CvsType
    {
        return $this->SamplePaymentCvsType;
    }

    /**
     * @param CvsType $SamplePaymentCvsType
     */
    public function setSamplePaymentCvsType(CvsType $SamplePaymentCvsType)
    {
        $this->SamplePaymentCvsType = $SamplePaymentCvsType;
    }

    /**
     * @return PaymentStatus|null
     */
    public function getSamplePaymentPaymentStatus(): ?PaymentStatus
    {
        return $this->SamplePaymentPaymentStatus;
    }

    /**
     * @param PaymentStatus|null $SamplePaymentPaymentStatus
     */
    public function setSamplePaymentPaymentStatus(?PaymentStatus $SamplePaymentPaymentStatus = null)
    {
        $this->SamplePaymentPaymentStatus = $SamplePaymentPaymentStatus;
    }

    /**
     * @return CvsPaymentStatus|null
     */
    public function getSamplePaymentCvsPaymentStatus(): ?CvsPaymentStatus
    {
        return $this->SamplePaymentCvsPaymentStatus;
    }

    /**
     * @param CvsPaymentStatus|null $SamplePaymentCvsPaymentStatus
     */
    public function setSamplePaymentCvsPaymentStatus(?CvsPaymentStatus $SamplePaymentCvsPaymentStatus = null)
    {
        $this->SamplePaymentCvsPaymentStatus = $SamplePaymentCvsPaymentStatus;
    }
}
