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

namespace Plugin\SamplePayment;

use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\PaymentRepository;
use Plugin\SamplePayment\Entity\Config;
use Plugin\SamplePayment\Entity\PaymentStatus;
use Plugin\SamplePayment\Service\Method\CreditCard;
use Plugin\SamplePayment\Service\PaymentService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    public function enable($config, $app, ContainerInterface $container)
    {
        // TODO PluginServiceでインスタンス化されメソッドが呼ばれるので、Injectionできない.
        $paymentRepository = $container->get(PaymentRepository::class);

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo(999);
        $Payment->setVisible(true);
        $Payment->setMethod('サンプル決済(トークン)'); // todo nameでいいんじゃないか
        $Payment->setServiceClass(PaymentService::class);
        $Payment->setMethodClass(CreditCard::class);

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo(999);
        $Payment->setVisible(true);
        $Payment->setMethod('サンプル決済(リンク)'); // todo nameでいいんじゃないか
        $Payment->setServiceClass(LinkPaymentService::class);
        $Payment->setMethodClass(LinkCreditCard::class);

        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager->persist($Payment);
        $entityManager->flush($Payment);

        $Config = $entityManager->find(Config::class, 1);
        if (!$Config) {
            $Config = new Config();
            $Config->setApiId('api-id');
            $Config->setApiPassword('api-password');
            $Config->setApiUrl('https://payment.example/com');
        }

        $entityManager->persist($Config);
        $entityManager->flush($Config);

        $statuses = [
            1 => '未決済',
            2 => '有効性チェック済',
            3 => '仮売上',
            4 => '実売上',
            5 => 'キャンセル',
        ];
        $i = 0;
        foreach ($statuses as $id => $name) {
            $PaymentStatus = $entityManager->find(PaymentStatus::class, $id);
            if (!$PaymentStatus) {
                $PaymentStatus = new PaymentStatus();
            }
            $PaymentStatus->setId($id);
            $PaymentStatus->setName($name);
            $PaymentStatus->setSortNo($i++);
            $entityManager->persist($PaymentStatus);
            $entityManager->flush($PaymentStatus);
        }
    }
}
