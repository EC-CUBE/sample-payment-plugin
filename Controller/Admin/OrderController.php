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

namespace Plugin\SamplePayment44\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class OrderController extends AbstractController
{
    /**
     * 受注編集 > 決済のキャンセル処理
     */
    #[Route(path: '/%eccube_admin_route%/sample_payment/order/cancel/{id}', requirements: ['id' => '\d+'], name: 'sample_payment_admin_order_cancel', methods: ['POST'])]
    public function cancel(Request $request, Order $Order): JsonResponse
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
     */
    #[Route(path: '/%eccube_admin_route%/sample_payment/order/change_price/{id}', requirements: ['id' => '\d+'], name: 'sample_payment_admin_order_change_price', methods: ['POST'])]
    public function changePrice(Request $request, Order $Order): JsonResponse
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            // 通信処理

            // 決済金額の計算は, 浮動小数点演算の丸め誤差を避けるため bcmath を使用する.
            // EC-CUBE 本体も金額計算を bcmath で行っており, bcmath 拡張が無い環境では
            // nanasess/bcmath-polyfill が関数を提供する (本体が依存に含むため別途要求は不要).
            // 値は文字列で受け渡し, 第3引数 scale で小数桁を明示するのが本体の慣習.
            // 以下はコンビニ決済手数料を例にした加減乗除 (bcadd/bcsub/bcmul/bcdiv) のサンプル.
            $paymentTotal = $Order->getPaymentTotal(); // 本体の getPaymentTotal(): string

            // 乗算・除算: 手数料 = 決済総額 × 手数料率(3.5%) ÷ 100 (小数以下切り捨て)
            $feeRate = '3.5';
            $fee = bcdiv(bcmul($paymentTotal, $feeRate, 4), '100', 0);

            // 加算: 手数料を加えた金額
            $totalWithFee = bcadd($paymentTotal, $fee, 0);

            // 減算: キャンペーン割引(固定100円)を差し引いた最終請求額
            $discount = '100';
            $newPrice = bcsub($totalWithFee, $discount, 0);

            // 実際のプラグインでは, ここで決済サーバへ変更後の金額を通知し,
            // PurchaseFlow で受注金額を再計算・確定する.
            // 本サンプルでは計算結果を返すのみで受注金額は変更しない.

            $this->addSuccess('sample_payment.admin.order.change_price.success', 'admin');

            return $this->json(['price' => $newPrice]);
        }

        throw new BadRequestHttpException();
    }
}
