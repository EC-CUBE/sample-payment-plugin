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

namespace Plugin\SamplePayment\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\ShoppingService;
use Eccube\Service\OrderStateMachine;
use Plugin\SamplePayment\Entity\PaymentStatus;
use Plugin\SamplePayment\Entity\CvsPaymentStatus;
use Plugin\SamplePayment\Repository\PaymentStatusRepository;
use Plugin\SamplePayment\Repository\CvsPaymentStatusRepository;
use Plugin\SamplePayment\Service\Method\Convenience;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * リンク式決済の注文/戻る/完了通知を処理する.
 */
class PaymentController extends AbstractController
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var PaymentStatusRepository
     */
    protected $paymentStatusRepository;

    /**
     * @var CvsPaymentStatusRepository
     */
    protected $cvsPaymentStatusRepository;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var OrderStateMachine
     */
    protected $orderStateMachine;


    /**
     * PaymentController constructor.
     *
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param PaymentStatusRepository $paymentStatusRepository
     * @param CvsPaymentStatusRepository $CvsPaymentStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow,
     * @param CartService $cartService
     * @param OrderStateMachine $orderStateMachine
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        CvsPaymentStatusRepository $cvsPaymentStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        CartService $cartService,
        OrderStateMachine $orderStateMachine
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->cvsPaymentStatusRepository = $cvsPaymentStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->cartService = $cartService;
        $this->orderStateMachine = $orderStateMachine;
    }

    /**
     * @Route("/sample_payment_back", name="sample_payment_back")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function back(Request $request)
    {
        $orderNo = $request->get('no');
        $Order = $this->getOrderByNo($orderNo);

        if (!$Order) {
            throw new NotFoundHttpException();
        }

        if ($this->getUser() != $Order->getCustomer()) {
            throw new NotFoundHttpException();
        }

        // 受注ステータスを購入処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $Order->setOrderStatus($OrderStatus);

        // 決済ステータスを未決済へ変更
        $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
        $Order->setSamplePaymentPaymentStatus($PaymentStatus);

        // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
        $this->purchaseFlow->rollback($Order, new PurchaseContext());

        $this->entityManager->flush();

        return $this->redirectToRoute('shopping');
    }

    /**
     * 完了画面へ遷移する.
     *
     * @Route("/sample_payment_complete", name="sample_payment_complete")
     */
    public function complete(Request $request)
    {
        $orderNo = $request->get('no');
        $Order = $this->getOrderByNo($orderNo);

        if (!$Order) {
            throw new NotFoundHttpException();
        }

        if ($this->getUser() != $Order->getCustomer()) {
            throw new NotFoundHttpException();
        }

        // カートを削除する
        $this->cartService->clear();

        // FIXME 完了画面を表示するため, 受注IDをセッションに保持する
        $this->session->set('eccube.front.shopping.order.id', $Order->getId());

        $this->entityManager->flush();

        return $this->redirectToRoute('shopping_complete');
    }

    /**
     * 結果通知URLを受け取る.
     *
     * @Route("/sample_payment_receive_complete", name="sample_payment_receive_complete")
     */
    public function receiveComplete(Request $request)
    {
        // 決済会社から受注番号を受け取る
        $orderNo = $request->get('no');
        $Order = $this->getOrderByNo($orderNo);

        if (!$Order) {
            throw new NotFoundHttpException();
        }

        // 受注ステータスを新規受付へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
        $Order->setOrderStatus($OrderStatus);

        // 決済ステータスを仮売上へ変更
        $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES);
        $Order->setSamplePaymentPaymentStatus($PaymentStatus);

        // 注文完了メールにメッセージを追加
        $Order->appendCompleteMailMessage('');

        // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
        $this->purchaseFlow->commit($Order, new PurchaseContext());

        $this->entityManager->flush();

        return new Response('OK!!');
    }

    /**
     * 結果通知URLを受け取る(コンビニ決済).
     *
     * @Route("/sample_payment_receive_cvs_status", name="sample_payment_receive_cvs_status")
     */
    public function receiveCvsStatus(Request $request)
    {
        // 決済会社から受注番号を受け取る
        $orderNo = $request->get('no');
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
        ]);

        if (!$Order) {
            throw new NotFoundHttpException();
        }

        if ($Order->getPayment()->getMethodClass() !== Convenience::class) {
            throw new BadRequestHttpException();
        }

        $cvs_status = $request->get('cvs_status');

        switch ($cvs_status) {
            // 決済失敗
            case CvsPaymentStatus::FAILURE:
                // 受注ステータスをキャンセルへ変更
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::CANCEL);
                if ($this->orderStateMachine->can($Order, $OrderStatus)) {
                    $this->orderStateMachine->apply($Order, $OrderStatus);

                    // 決済ステータスを決済失敗へ変更
                    $PaymentStatus = $this->cvsPaymentStatusRepository->find(CvsPaymentStatus::FAILURE);
                    $Order->setSamplePaymentCvsPaymentStatus($PaymentStatus);
                } else {
                    throw new BadRequestHttpException();
                }

                break;
            // 期限切れ
            case CvsPaymentStatus::EXPIRED:
                // 受注ステータスをキャンセルへ変更
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::CANCEL);
                if ($this->orderStateMachine->can($Order, $OrderStatus)) {
                    $this->orderStateMachine->apply($Order, $OrderStatus);

                    // 決済ステータスを期限切れへ変更
                    $PaymentStatus = $this->cvsPaymentStatusRepository->find(CvsPaymentStatus::EXPIRED);
                    $Order->setSamplePaymentCvsPaymentStatus($PaymentStatus);
                } else {
                    throw new BadRequestHttpException();
                }

                break;
            // 決済完了
            case CvsPaymentStatus::COMPLETE:
            default:
                // 受注ステータスを対応中へ変更
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::IN_PROGRESS);
                if ($this->orderStateMachine->can($Order, $OrderStatus)) {
                    $this->orderStateMachine->apply($Order, $OrderStatus);

                    // 決済ステータスを決済完了へ変更
                    $PaymentStatus = $this->cvsPaymentStatusRepository->find(CvsPaymentStatus::COMPLETE);
                    $Order->setSamplePaymentCvsPaymentStatus($PaymentStatus);
                } else {
                    throw new BadRequestHttpException();
                }
        }

        $this->entityManager->flush();

        return new Response('OK!!');
    }

    /**
     * 注文番号で受注を検索する.
     *
     * @param $orderNo
     *
     * @return Order
     */
    private function getOrderByNo($orderNo)
    {
        /** @var OrderStatus $pendingOrderStatus */
        $pendingOrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);

        $outstandingPaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);

        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
            'OrderStatus' => $pendingOrderStatus,
            'SamplePaymentPaymentStatus' => $outstandingPaymentStatus,
        ]);

        return $Order;
    }
}
