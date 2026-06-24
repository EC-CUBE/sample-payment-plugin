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

namespace Plugin\SamplePayment44\Controller;

use Eccube\Controller\AbstractController;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class MypageController extends AbstractController
{
    #[Route(path: '/mypage/sample_payment_card_info', name: 'sample_payment_mypage_card_info', methods: ['GET', 'POST'])]
    #[Template(template: '@SamplePayment44/card_info.twig')]
    public function index(Request $request)
    {
        $builder = $this->formFactory->createBuilder();
        $builder->add('cardno', TextType::class);
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // カード番号の更新処理
            // カード番号は非保持化する必要があります。実際にはやり取りしないようにしてください。

            return $this->redirectToRoute('sample_payment_mypage_card_info_complete');
        }

        return [
            'form' => $form->createView(),
        ];
    }

    #[Route(path: '/mypage/sample_payment_card_info_complete', name: 'sample_payment_mypage_card_info_complete', methods: ['GET'])]
    #[Template(template: '@SamplePayment44/card_info_complete.twig')]
    public function complete(): array
    {
        return [];
    }
}
