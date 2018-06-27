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

namespace Plugin\SamplePayment\Controller\Admin;

use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\PaginatorInterface;
use Plugin\SamplePayment\Form\Type\Admin\SearchPaymentType;
use Plugin\SamplePayment\Repository\PaymentStatusRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaymentController extends AbstractController
{
    /*
     * 受注編集 > 決済のキャンセル処理
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/sample_payment/order/cancel/{id}", requirements={"id" = "\d+"}, name="admin_sample_payment_order_cancel")
     */
    public function cancel(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            // 通信処理

            $this->addSuccess('取消処理を行いました', 'admin');

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * 受注編集 > 決済の金額変更
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/sample_payment/order/change_price/{id}", requirements={"id" = "\d+"}, name="admin_sample_payment_order_change_price")
     */
    public function changePrice(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            // 通信処理

            $this->addSuccess('金額変更処理を行いました', 'admin');

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }
}
