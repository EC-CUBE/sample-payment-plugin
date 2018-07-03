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

namespace Plugin\SamplePayment\Service\Method;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethod;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\SamplePayment\Entity\PaymentStatus;
use Symfony\Component\Form\FormInterface;

/**
 * クレジットカード(トークン決済)の決済処理を行う.
 */
class CreditCard implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * CreditCard constructor.
     *
     * @param OrderStatusRepository $orderStatusRepository
     * @param EntityManagerInterface $entityManager
     * @param PurchaseFlow $purchaseFlow
     */
    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        EntityManagerInterface $entityManager,
        PurchaseFlow $purchaseFlow
    ) {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->entityManager = $entityManager;
        $this->purchaseFlow = $purchaseFlow;
    }

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * クレジットカードの有効性チェックを行う.
     *
     * @return PaymentResult
     * @throws \Eccube\Service\PurchaseFlow\PurchaseException
     */
    public function verify()
    {
        // 決済サーバとの通信処理(有効性チェックやカード番号の下4桁取得)
        // ...
        //

        if (true) {
            $result = new PaymentResult();
            $result->setSuccess(true);
            $this->Order->setSamplePaymentCardNoLast4('****-*****-****-1234');
        } else {
            $result = new PaymentResult();
            $result->setSuccess(false);
            $result->setErrors([trans('sample_payment.shopping.verify.error')]);
        }

        return $result;
    }

    /**
     * 注文時に呼び出される.
     *
     * 受注ステータス, 決済ステータスを更新する.
     * ここでは決済サーバとの通信は行わない.
     *
     * @return PaymentDispatcher|null
     */
    public function apply()
    {
        // 受注ステータスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(9); // TODO 決済処理中ステータスを定数化
        $this->Order->setOrderStatus($OrderStatus);

        // 決済ステータスを未決済へ変更
        $PaymentStatus = $this->entityManager->find(PaymentStatus::class, PaymentStatus::OUTSTANDING);
        $this->Order->setSamplePaymentPaymentStatus($PaymentStatus);

        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());
    }

    /**
     * 注文時に呼び出される.
     *
     * クレジットカードの決済処理を行う.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        // 決済サーバに仮売上のリクエスト送る(設定等によって送るリクエストは異なる)
        // ...
        //
        $token = $this->Order->getSamplePaymentToken();

        if (true) {
            // 受注ステータスを新規受付へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $this->Order->setOrderStatus($OrderStatus);

            // 決済ステータスを仮売上へ変更
            $PaymentStatus = $this->entityManager->find(PaymentStatus::class, PaymentStatus::PROVISIONAL_SALES);
            $this->Order->setSamplePaymentPaymentStatus($PaymentStatus);

            // 注文完了画面/注文完了メールにメッセージを追加
            $this->Order->appendCompleteMessage('トークン -> '.$token);
            $this->Order->appendCompleteMailMessage('トークン -> '.$token);

            // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
            $this->purchaseFlow->commit($this->Order, new PurchaseContext());

            $result = new PaymentResult();
            $result->setSuccess(true);
        } else {
            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $this->Order->setOrderStatus($OrderStatus);

            // 決済ステータスを未決済へ変更
            $PaymentStatus = $this->entityManager->find(PaymentStatus::class, PaymentStatus::OUTSTANDING);
            $this->Order->setSamplePaymentPaymentStatus($PaymentStatus);

            // 失敗時はpurchaseFlow::commitを呼び出す.
            $this->purchaseFlow->rollback($this->Order, new PurchaseContext());

            $result = new PaymentResult();
            $result->setSuccess(false);
            $result->setErrors([trans('sample_payment.shopping.checkout.error')]);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormType(FormInterface $form)
    {
        $this->form = $form;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder(Order $Order)
    {
        $this->Order = $Order;
    }
}
