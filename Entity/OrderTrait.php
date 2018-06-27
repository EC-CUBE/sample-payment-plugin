<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
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
     *
     * TODO 命名規約.いったんプラグインコードのスネークケースで.
     * TODO 文字長はどれくらいが適切？
     */
    private $sample_payment_token;

    /**
     * 決済ステータスを保持するカラム.
     *
     * dtb_order.sample_payment_payment_status_id
     *
     * @var PaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\SamplePayment\Entity\PaymentStatus")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="sample_payment_payment_id", referencedColumnName="id")
     * })
     */
    private $SamplePaymentPaymentStatus;

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
     * トークンを保持するカラム.
     *
     * dtb_order.link_payment_token
     *
     * @var string
     * @ORM\Column(type="string", nullable=true)
     *
     * TODO 命名規約.いったんプラグインコードのスネークケースで.
     * TODO 文字長はどれくらいが適切？
     */
    private $link_payment_token;

    // TODO カラム名、変数名が不適切
    /**
     * 決済ステータスを保持するカラム.
     *
     * dtb_order.link_payment_payment_status_id
     *
     * @var PaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\LinkPayment\Entity\PaymentStatus")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="link_payment_payment_id", referencedColumnName="id")
     * })
     */
    private $LinkPaymentPaymentStatus;

    /**
     * @return string
     */
    public function getLinkPaymentToken()
    {
        return $this->link_payment_token;
    }

    /**
     * @param string $link_payment_token
     *
     * @return $this
     */
    public function setLinkPaymentToken($link_payment_token)
    {
        $this->link_payment_token = $link_payment_token;

        return $this;
    }

    /**
     * @return PaymentStatus
     */
    public function getLinkPaymentPaymentStatus()
    {
        return $this->LinkPaymentPaymentStatus;
    }

    /**
     * @param PaymentStatus $LinkPaymentPaymentStatus|null
     */
    public function setLinkPaymentPaymentStatus(PaymentStatus $LinkPaymentPaymentStatus = null)
    {
        $this->LinkPaymentPaymentStatus = $LinkPaymentPaymentStatus;
    }
}
