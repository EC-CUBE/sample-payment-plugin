<?php
/**
 * Created by PhpStorm.
 * User: hideki_okajima
 * Date: 2018/06/21
 * Time: 15:41
 */

namespace Plugin\SamplePayment\Controller;

use Eccube\Annotation\ForwardOnly;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Customer;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\OrderItem;
use Eccube\Repository\OrderRepository;
use Eccube\Service\ShoppingService;
use Plugin\SamplePayment\Entity\PaymentStatus;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentController extends AbstractController
{

    /**
     * @var OrderRepository
     */
    protected $orderRepository;
    /**
     * @var ShoppingService
     */
    private $shoppingService;

    /**
     * PaymentController constructor.
     * @param OrderRepository $orderRepository
     * @param ShoppingService $shoppingService
     */
    public function __construct(OrderRepository $orderRepository, ShoppingService $shoppingService)
    {
        $this->orderRepository = $orderRepository;
        $this->shoppingService = $shoppingService;
    }


    /**
     * @ForwardOnly
     * @Route("sample_payment_index", name="sample_payment_index")
     */
    public function index()
    {
        /** @var Order $Order */
        $Order = $this->shoppingService->getOrder();

        // 決済会社の決済画面へのリンク
        $url = '/payment_company';

        // 注文番号を付与
        $orderCode = $Order->getOrderCode();
        $url .= '?code=' . $orderCode;

        return new RedirectResponse($url);

    }

    /**
     * @Route("/sample_payment_back", name="sample_payment_back")
     * @param Request $request
     * @return RedirectResponse
     */
    public function back(Request $request)
    {
        $orderCode = $request->get('code');

        $Order = $this->getOrderByCode($orderCode);

        if ($this->getUser() != $Order->getCustomer()) {
            throw new NotFoundHttpException();
        }

        // 受注ステータスを戻す（決済処理中 -> 購入処理中）
        $this->shoppingService->setOrderStatus($Order, OrderStatus::PROCESSING);

        // 在庫を戻す
        /** @var Order $Order */
        $OrderItems = $Order->getProductOrderItems();
        /** @var OrderItem $OrderItem */
        foreach ($OrderItems as $OrderItem) {
            $ProductClass = $OrderItem->getProductClass();

            if ($ProductClass->isStockUnlimited()) {
                continue;
            }

            $quantity = $OrderItem->getQuantity();
            $stock = (int)$ProductClass->getProductStock()->getStock() + (int)$quantity;
            // TODO stockの管理を１箇所にしたい
            $ProductClass->setStock($stock);
            $ProductClass->getProductStock()->setStock($stock);
        }

        // ポイントを戻す
        /** @var Customer $Customer */
        $Customer = $this->getUser();
        $point = $Customer->getPoint();
        $usePoint = $Order->getUsePoint();
        $Customer->setPoint((int)$point + (int)$usePoint);

        $this->entityManager->flush();

        return $this->redirectToRoute("shopping");
    }

    /**
     * @Route("/sample_payment_complete", name="sample_payment_complete")
     */
    public function complete(Request $request)
    {
        $orderCode = $request->get('code');

        $Order = $this->getOrderByCode($orderCode);

        if ($this->getUser() != $Order->getCustomer()) {
            throw new NotFoundHttpException();
        }

        // カード情報を保存するなどあればここに処理を追加

        // TODO カートを削除する

        // TODO 受注番号を完了画面に送って画面に表示させたい
        return $this->redirectToRoute("shopping_complete");
    }

    /**
     * @Route("/sample_payment_receive_complete", name="sample_payment_receive_complete")
     *
     * TODO この処理は本体に移動させたい
     */
    public function receiveComplete(Request $request)
    {
        // 決済会社から受注番号を受け取る
        $orderCode = $request->get('code');

        $Order = $this->getOrderByCode($orderCode);

        // 独自処理
        // 受注ステータス更新（決済処理中 -> 新規受付）
        $this->shoppingService->setOrderStatus($Order, OrderStatus::NEW);

        // 決済ステータス更新（未決済 -> 仮売上済み）
        $provisionalSalesPaymentStatus = $this->entityManager->find(PaymentStatus::class, PaymentStatus::PROVISIONAL_SALES);
        $Order->getSamplePaymentPaymentStatus($provisionalSalesPaymentStatus);


        // 共通処理
        // 完了メール送信
        $this->shoppingService->sendOrderMail($Order);

        // TODO 残っていればカート削除

        $this->entityManager->flush();

        return new Response("OK!!");
    }

    /**
     * @param $orderCode
     * @return Order
     */
    private function getOrderByCode($orderCode)
    {
        /** @var OrderStatus $pendingOrderStatus */
        $pendingOrderStatus = $this->entityManager->find(OrderStatus::class, OrderStatus::PENDING);

        $outstandingPaymentStatus = $this->entityManager->find(PaymentStatus::class, PaymentStatus::OUTSTANDING);

        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy(['order_code' => $orderCode, 'OrderStatus' => $pendingOrderStatus, 'SamplePaymentPaymentStatus' => $outstandingPaymentStatus]);

        if (is_null($Order)) {
            throw new NotFoundHttpException();
        }

        return $Order;
    }
}
