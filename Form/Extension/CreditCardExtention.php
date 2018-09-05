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

namespace Plugin\SamplePayment\Form\Extension;

use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Repository\PaymentRepository;
use Plugin\SamplePayment\Service\Method\CreditCard;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * 注文手続き画面のFormを拡張し、カード入力フォームを追加する.
 * 支払い方法に応じてエクステンションを作成する.
 */
class CreditCardExtention extends AbstractTypeExtension
{
    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    public function __construct(PaymentRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            /** @var Order $data */
            $data = $event->getData();
            $form = $event->getForm();

            // 支払い方法が一致する場合
            if ($data->getPayment()->getMethodClass() === CreditCard::class) {
                $form->add('sample_payment_token', HiddenType::class, [
                    'required' => false,
                    'mapped' => true, // Orderエンティティに追加したカラムなので、mappedはtrue
                ]);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $options = $event->getForm()->getConfig()->getOptions();

            // 注文確認->注文処理時はフォームは定義されない.
            if ($options['skip_add_form']) {

                // サンプル決済では使用しないが、支払い方法に応じて処理を行う場合は
                // $event->getData()ではなく、$event->getForm()->getData()でOrderエンティティを取得できる

                /** @var Order $Order */
                $Order = $event->getForm()->getData();
                $Order->getPayment()->getId();

                return;
            } else {

                $Payment = $this->paymentRepository->findOneBy(['method_class' => CreditCard::class]);

                $data = $event->getData();
                $form = $event->getForm();

                // 支払い方法が一致しなければremove
                if ($Payment->getId() != $data['Payment']) {
                    $form->remove('sample_payment_token');
                }

            }
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
