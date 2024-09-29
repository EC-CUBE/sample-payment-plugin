<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\SamplePayment43\Controller;

use Eccube\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MypageController extends AbstractController
{
    /**
     * @Route("/mypage/sample_payment_card_info", name="sample_payment_mypage_card_info", methods={"GET", "POST"})
     * @Template("@SamplePayment43/card_info.twig")
     */
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

    /**
     * @Route("/mypage/sample_payment_card_info_complete", name="sample_payment_mypage_card_info_complete", methods={"GET"})
     * @Template("@SamplePayment43/card_info_complete.twig")
     */
    public function complete(Request $request)
    {
        return [];
    }
}
