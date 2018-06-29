<?php
/**
 * Created by PhpStorm.
 * User: hideki_okajima
 * Date: 2018/06/21
 * Time: 14:09
 */

namespace Plugin\SamplePayment\Controller;


use Eccube\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PaymentCompanyController extends AbstractController
{
    /**
     * 決済会社側での処理
     *
     * @Route("/payment_company", name="payment_company")
     * @Template("@SamplePayment/dummy.twig")
     */
    public function index(Request $request)
    {
        $orderCode = $request->get('code');

        if ('POST' === $request->getMethod()) {
            // EC-CUBEの決済完了受付リンク
            $url = '/sample_payment_complete';

            // 注文番号を付与
            $url .= '?code=' . $orderCode;

            // TODO POSTにしたい
            return new RedirectResponse($url);
        }

        return [
            'order_code' => $orderCode,
        ];
    }
}