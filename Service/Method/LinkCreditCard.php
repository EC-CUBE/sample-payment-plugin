<?php
/**
 * Created by PhpStorm.
 * User: hideki_okajima
 * Date: 2018/06/21
 * Time: 13:48
 */

namespace Plugin\SamplePayment\Service\Method;


use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Exception\ShoppingException;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethod;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\ShoppingService;
use Plugin\SamplePayment\Entity\PaymentStatus;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;

class LinkCreditCard implements PaymentMethod
{
    /**
     * @var Order
     */
    private $Order;

    /**
     * @var ShoppingService
     */
    private $shoppingService;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(ShoppingService $shoppingService, EntityManagerInterface $entityManager)
    {
        $this->shoppingService = $shoppingService;
        $this->entityManager = $entityManager;
    }

    public function checkout()
    {
        return new PaymentResult();
    }

    // TODO 呼び出し元の処理が必要
    public function verify()
    {
        // リンク型は使用しない
    }

    /**
     * ここでは決済方法の独自処理を記載する
     * forward先を指定してそちらから決済画面へリダイレクトさせる
     *
     * 決済会社の画面へリダイレクト
     *
     * @return PaymentDispatcher
     * @throws ShoppingException
     */
    public function apply()
    {
        // 決済の独自処理
        // こちらに書いてもいいし、forward先で書いてもいい
        /** @var Order $Order */
        $Order = $this->shoppingService->getOrder();

        if (!$Order) {
            throw new ShoppingException();
        }

        // - 受注ステータスの変更（購入処理中 -> 決済処理中）
        $this->shoppingService->setOrderStatus($Order, OrderStatus::PENDING);

        // - 決済ステータス（なし -> 未決済）
        if ($Order->getSamplePaymentPaymentStatus() == null) {
            $PaymentStatus = $this->entityManager->find(PaymentStatus::class, PaymentStatus::OUTSTANDING);
            $Order->getSamplePaymentPaymentStatus($PaymentStatus);
        }

        // 他のコントローラに移譲等の処理をする
        $dispatcher = new PaymentDispatcher();
        $dispatcher->setForward(true);
        $dispatcher->setRoute('sample_payment_index');

        return $dispatcher;
    }

    /**
     * @param FormTypeInterface
     *
     * TODO FormTypeInterface -> FormInterface
     */
    public function setFormType(FormInterface $form)
    {
        // TODO setOrder()でセットするので不要になる予定
        $this->Order = $form->getData();

        // TODO Orderエンティティにトークンが保持されているのでフォームは不要
        // TODO フォームよりOrderがほしい
        // TODO applyやcheckoutでOrderが渡ってきてほしい.
        // TODO やっぱりFormはいる -> Orderには保持しないデータはFormで引き回す(確認画面とか). 画面に持っていくデータを詰められるオブジェクトがあればいいのかな

    }

    public function setRequest(Request $request)
    {

    }

    // TODO 消す
    public function setApplication($app)
    {
        //
    }

    // TODO Interfaceに追加と呼び出し元の処理が必要
    public function receive()
    {

    }

    /**
     * @param Order
     */
    public function setOrder(Order $Order)
    {
        $this->Order = $Order;
    }
}