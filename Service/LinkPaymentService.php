<?php
/**
 * Created by PhpStorm.
 * User: hideki_okajima
 * Date: 2018/06/21
 * Time: 13:52
 */

namespace Plugin\SamplePayment\Service;


use Eccube\Entity\Order;
use Eccube\Entity\OrderItem;
use Eccube\Service\Payment\PaymentMethod;
use Eccube\Service\ShoppingService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Eccube\Service\PaymentService as BasePaymentService;
use Symfony\Component\HttpFoundation\RequestStack;

class LinkPaymentService extends BasePaymentService
{
    /**
     * @var ShoppingService
     */
    private $shoppingService;

    public function __construct(RequestStack $requestStack, ShoppingService $shoppingService)
    {
        parent::__construct($requestStack);
        $this->shoppingService = $shoppingService;
    }

    /**
     * ここでは決済会社の共通処理を記載する
     *
     * @param PaymentMethod $method
     * @return RedirectResponse
     */
    public function dispatch(PaymentMethod $method)
    {
        // 以下は共通処理
        // - 在庫を減らす
        // TODO そもそも本体に在庫を減らす処理がないので本体に処理を追加する
        /** @var Order $Order */
        $Order = $this->shoppingService->getOrder();
        $OrderItems = $Order->getProductOrderItems();
        /** @var OrderItem $OrderItem */
        foreach ($OrderItems as $OrderItem) {
            $ProductClass = $OrderItem->getProductClass();

            if ($ProductClass->isStockUnlimited()) {
                continue;
            }

            $quantity = $OrderItem->getQuantity();
            $stock = $ProductClass->getProductStock()->getStock() - $quantity;
            // TODO stockの管理を１箇所にしたい
            $ProductClass->setStock($stock);
            $ProductClass->getProductStock()->setStock($stock);
        }

        // PaymentMethod->apply に処理を移譲する
        // 別のコントローラに forward など
        $request = $this->requestStack->getCurrentRequest();

        return $method->apply($request);
    }
}