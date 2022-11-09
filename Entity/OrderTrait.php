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
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * トークンを保持するカラム.
     *
     * dtb_order.sample_payment_token
     *
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $sample_payment_token;

    /**
     * クレジットカード番号の末尾4桁.
     * 永続化は行わず, 注文確認画面で表示する.
     *
     * @var string
     */
    private $sample_payment_card_no_last4;

    /**
     * コンビニ用種別を保持するカラム.
     *
     * dtb_order.sample_payment_cvs_type_id
     *
     * @var CvsType
     * @ORM\ManyToOne(targetEntity="Plugin\SamplePayment\Entity\CvsType")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="sample_payment_cvs_type_id", referencedColumnName="id")
     * })
     */
    private $SamplePaymentCvsType;


    /**
     * 決済ステータスを保持するカラム.
     *
     * dtb_order.sample_payment_payment_status_id
     *
     * @var SamplePaymentPaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\SamplePayment\Entity\PaymentStatus")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="sample_payment_payment_status_id", referencedColumnName="id")
     * })
     */
    private $SamplePaymentPaymentStatus;

    /**
     * コンビニ用決済ステータスを保持するカラム.
     *
     * dtb_order.sample_payment_payment_status_id
     *
     * @var SamplePaymentCvsPaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\SamplePayment\Entity\CvsPaymentStatus")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="sample_payment_cvs_payment_status_id", referencedColumnName="id")
     * })
     */
    private $SamplePaymentCvsPaymentStatus;

    /**
     * @return string
     */
    public function getSamplePaymentToken()
    {
        return $this->sample_payment_token;
    }

    /**
     * @param string $sample_payment_token
     *
     * @return $this
     */
    public function setSamplePaymentToken($sample_payment_token)
    {
        $this->sample_payment_token = $sample_payment_token;

        return $this;
    }

    /**
     * @return string
     */
    public function getSamplePaymentCardNoLast4()
    {
        return $this->sample_payment_card_no_last4;
    }

    /**
     * @param string $sample_payment_card_no_last4
     */
    public function setSamplePaymentCardNoLast4($sample_payment_card_no_last4)
    {
        $this->sample_payment_card_no_last4 = $sample_payment_card_no_last4;
    }

    /**
     * @return CvsType
     */
    public function getSamplePaymentCvsType()
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
     * @return PaymentStatus
     */
    public function getSamplePaymentPaymentStatus()
    {
        return $this->SamplePaymentPaymentStatus;
    }

    /**
     * @param PaymentStatus $SamplePaymentPaymentStatus|null
     */
    public function setSamplePaymentPaymentStatus(PaymentStatus $SamplePaymentPaymentStatus = null)
    {
        $this->SamplePaymentPaymentStatus = $SamplePaymentPaymentStatus;
    }

    /**
     * @return CvsPaymentStatus
     */
    public function getSamplePaymentCvsPaymentStatus()
    {
        return $this->SamplePaymentCvsPaymentStatus;
    }

    /**
     * @param CvsPaymentStatus $SamplePaymentCvsPaymentStatus|null
     */
    public function setSamplePaymentCvsPaymentStatus(CvsPaymentStatus $SamplePaymentCvsPaymentStatus = null)
    {
        $this->SamplePaymentCvsPaymentStatus = $SamplePaymentCvsPaymentStatus;
    }
}
