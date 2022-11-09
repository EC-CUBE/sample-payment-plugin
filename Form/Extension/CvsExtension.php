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

namespace Plugin\SamplePayment\Form\Extension;

use Doctrine\ORM\EntityRepository;
use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Repository\PaymentRepository;
use Plugin\SamplePayment\Entity\CvsType;
use Plugin\SamplePayment\Repository\CvsTypeRepository;
use Plugin\SamplePayment\Service\Method\Convenience;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * 注文手続き画面のFormを拡張し、コンビニ選択フォームを追加する.
 * 支払い方法に応じてエクステンションを作成する.
 */
class CvsExtension extends AbstractTypeExtension
{
    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var CvsTypeRepository
     */
    protected $cvsTypeRepository;

    public function __construct(
        CvsTypeRepository $cvsTypeRepository,
        PaymentRepository $paymentRepository
    ) {
        $this->cvsTypeRepository = $cvsTypeRepository;
        $this->paymentRepository = $paymentRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // ShoppingController::checkoutから呼ばれる場合は, フォーム項目の定義をスキップする.
        if ($options['skip_add_form']) {
            return;
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            /** @var Order $data */
            $data = $event->getData();
            $form = $event->getForm();

            $form->add('SamplePaymentCvsType', EntityType::class, [
                'class' => CvsType::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->orderBy('p.id', 'ASC');
                },
                'choice_label' => 'name',
                'multiple' => false,
                'expanded' => true,
            ]);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            // サンプル決済では使用しないが、支払い方法に応じて処理を行う場合は
            // $event->getData()ではなく、$event->getForm()->getData()でOrderエンティティを取得できる

            /** @var Order $Order */
            $Order = $event->getForm()->getData();
            $Order->getPayment()->getId();

            $Payment = $this->paymentRepository->findOneBy(['method_class' => Convenience::class]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return OrderType::class;
    }
}
