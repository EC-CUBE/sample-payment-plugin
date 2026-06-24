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

namespace Plugin\SamplePayment44\Service\Method;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Exception\ShoppingException;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\SamplePayment44\Entity\CvsPaymentStatus;
use Plugin\SamplePayment44\Repository\CvsPaymentStatusRepository;
use Symfony\Component\Form\FormInterface;

/**
 * コンビニ払いの決済処理を行う
 */
class Convenience implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    private Order $Order;

    /**
     * @var FormInterface
     */
    private FormInterface $form;

    /**
     * LinkCreditCard constructor.
     *
     * @param OrderStatusRepository $orderStatusRepository
     * @param CvsPaymentStatusRepository $cvsPaymentStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     */
    public function __construct(private readonly OrderStatusRepository $orderStatusRepository, private readonly CvsPaymentStatusRepository $cvsPaymentStatusRepository, private readonly PurchaseFlow $shoppingPurchaseFlow)
    {
    }

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * コンビニ決済は使用しない.
     *
     * @return PaymentResult|bool
     */
    public function verify(): PaymentResult|bool
    {
        return false;
    }

    /**
     * 注文時に呼び出される.
     *
     * 決済サーバのカード入力画面へリダイレクトする.
     *
     * @return PaymentDispatcher|bool
     *
     * @throws ShoppingException
     */
    public function apply(): PaymentDispatcher|bool
    {
        // 受注ステータスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);

        // 決済ステータスを未決済へ変更
        $PaymentStatus = $this->cvsPaymentStatusRepository->find(CvsPaymentStatus::OUTSTANDING);
        $this->Order->setSamplePaymentCvsPaymentStatus($PaymentStatus);

        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->shoppingPurchaseFlow->prepare($this->Order, new PurchaseContext());

        return false;
    }

    /**
     * 注文時に呼び出される.
     *
     * @return PaymentResult
     */
    public function checkout(): PaymentResult
    {
        // 決済サーバとの通信処理(コンビニ払い込み情報等の取得)
        // ...
        //

        if (true) {
            $result = new PaymentResult();
            $result->setSuccess(true);

            // 受注ステータスを新規受付へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $this->Order->setOrderStatus($OrderStatus);

            $PaymentStatus = $this->cvsPaymentStatusRepository->find(CvsPaymentStatus::REQUEST);
            $this->Order->setSamplePaymentCvsPaymentStatus($PaymentStatus); // 決済要求成功に変更
            $message = 'コンビニ払込票番号：7192771999999';
            $this->Order->appendCompleteMessage($message);
            $this->Order->appendCompleteMailMessage($message);

            // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
            $this->shoppingPurchaseFlow->commit($this->Order, new PurchaseContext());
        } else {
            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $this->Order->setOrderStatus($OrderStatus);

            $result = new PaymentResult();
            $result->setSuccess(false);
            $PaymentStatus = $this->cvsPaymentStatusRepository->find(CvsPaymentStatus::FAILURE);
            $this->Order->setSamplePaymentCvsPaymentStatus($PaymentStatus); // 決済失敗
            $result->setErrors([trans('sample_payment.shopping.cvs.error')]);

            // 失敗時はpurchaseFlow::rollbackを呼び出す.
            $this->shoppingPurchaseFlow->rollback($this->Order, new PurchaseContext());
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormType(FormInterface $form): PaymentMethodInterface
    {
        $this->form = $form;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder(Order $Order): PaymentMethodInterface
    {
        $this->Order = $Order;

        return $this;
    }
}
