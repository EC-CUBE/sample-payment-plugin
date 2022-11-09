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

namespace Plugin\SamplePayment\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrderController extends AbstractController
{
    /**
     * 受注編集 > 決済のキャンセル処理
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/sample_payment/order/cancel/{id}", requirements={"id" = "\d+"}, name="sample_payment_admin_order_cancel")
     */
    public function cancel(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            // 通信処理

            $this->addSuccess('sample_payment.admin.order.cancel.success', 'admin');

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * 受注編集 > 決済の金額変更
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/sample_payment/order/change_price/{id}", requirements={"id" = "\d+"}, name="sample_payment_admin_order_change_price")
     */
    public function changePrice(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            // 通信処理

            $this->addSuccess('sample_payment.admin.order.change_price.success', 'admin');

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }
}
