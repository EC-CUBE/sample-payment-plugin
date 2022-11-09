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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * リンク式決済のカード番号入力画面.
 */
class PaymentCompanyController extends AbstractController
{
    /**
     * 決済サーバのカード入力画面.
     *
     * @Route("/payment_company")
     * @Template("@SamplePayment/dummy.twig")
     */
    public function index(Request $request)
    {
        $orderNo = $request->get('no');

        if ('POST' === $request->getMethod()) {
            // EC-CUBEの決済完了受付リンク
            $url = '/sample_payment_complete';

            // 注文番号を付与
            $url .= '?no='.$orderNo;

            return new RedirectResponse($url);
        }

        return [
            'order_no' => $orderNo,
        ];
    }
}
