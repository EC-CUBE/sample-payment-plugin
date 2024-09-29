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

namespace Plugin\SamplePayment43;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Layout;
use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\PaymentRepository;
use Plugin\SamplePayment43\Entity\Config;
use Plugin\SamplePayment43\Entity\CvsPaymentStatus;
use Plugin\SamplePayment43\Entity\CvsType;
use Plugin\SamplePayment43\Entity\PaymentStatus;
use Plugin\SamplePayment43\Service\Method\Convenience;
use Plugin\SamplePayment43\Service\Method\CreditCard;
use Plugin\SamplePayment43\Service\Method\LinkCreditCard;
use Psr\Container\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    private $pages = [
        [
            'name' => 'カード情報変更',
            'url' => 'sample_payment_mypage_card_info',
            'filename' => 'SamplePayment43/Resource/template/card_info.twig',
        ],
        [
            'name' => 'カード情報変更(完了)',
            'url' => 'sample_payment_mypage_card_info_complete',
            'filename' => 'SamplePayment43/Resource/template/card_info_complete.twig',
        ],
    ];

    public function enable(array $meta, ContainerInterface $container)
    {
        $this->createTokenPayment($container);
        $this->createLinkPayment($container);
        $this->createCvsPayment($container);
        $this->createConfig($container);
        $this->createPaymentStatuses($container);
        $this->createCvsPaymentStatuses($container);
        $this->createCvsTypes($container);
        $this->createPages($container);
    }

    private function createTokenPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $entityManager->getRepository(Payment::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('サンプル決済(トークン)'); // todo nameでいいんじゃないか
        $Payment->setMethodClass(CreditCard::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createLinkPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $entityManager->getRepository(Payment::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => LinkCreditCard::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('サンプル決済(リンク)'); // todo nameでいいんじゃないか
        $Payment->setMethodClass(LinkCreditCard::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createCvsPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $entityManager->getRepository(Payment::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => Cvs::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('コンビニ決済');
        $Payment->setMethodClass(Convenience::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createConfig(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $Config = $entityManager->find(Config::class, 1);
        if ($Config) {
            return;
        }

        $Config = new Config();
        $Config->setApiId('api-id');
        $Config->setApiPassword('api-password');
        $Config->setApiUrl('https://payment.example/com');

        $entityManager->persist($Config);
        $entityManager->flush($Config);
    }

    private function createMasterData(ContainerInterface $container, array $statuses, $class)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $i = 0;
        foreach ($statuses as $id => $name) {
            $PaymentStatus = $entityManager->find($class, $id);
            if (!$PaymentStatus) {
                $PaymentStatus = new $class;
            }
            $PaymentStatus->setId($id);
            $PaymentStatus->setName($name);
            $PaymentStatus->setSortNo($i++);
            $entityManager->persist($PaymentStatus);
            $entityManager->flush($PaymentStatus);
        }
    }

    private function createPaymentStatuses(ContainerInterface $container)
    {
        $statuses = [
            PaymentStatus::OUTSTANDING => '未決済',
            PaymentStatus::ENABLED => '有効性チェック済',
            PaymentStatus::PROVISIONAL_SALES => '仮売上',
            PaymentStatus::ACTUAL_SALES => '実売上',
            PaymentStatus::CANCEL => 'キャンセル',
        ];
        $this->createMasterData($container, $statuses, PaymentStatus::class);
    }

    private function createCvsPaymentStatuses(ContainerInterface $container)
    {
        $statuses = [
            CvsPaymentStatus::OUTSTANDING => '未決済',
            CvsPaymentStatus::REQUEST => '要求成功',
            CvsPaymentStatus::COMPLETE => '決済完了',
            CvsPaymentStatus::FAILURE => '決済失敗',
            CvsPaymentStatus::EXPIRED => '期限切れ',
        ];
        $this->createMasterData($container, $statuses, CvsPaymentStatus::class);
    }

    private function createCvsTypes(ContainerInterface $container)
    {
        $statuses = [
            CvsType::LAWSON => 'ローソン',
            CvsType::MINISTOP => 'ミニストップ',
            CvsType::SEVENELEVEN => 'セブンイレブン',
        ];
        $this->createMasterData($container, $statuses, CvsType::class);
    }

    private function createPages(ContainerInterface $container)
    {
        $em = $container->get('doctrine.orm.entity_manager');

        foreach ($this->pages as $pageInfo) {
            $Page = $em->getRepository(Page::class)->findOneBy(['url' => $pageInfo['url']]);
            if (null === $Page) {
                $this->createPage($em, $pageInfo['name'], $pageInfo['url'], $pageInfo['filename']);
            }
        }
    }

    private function createPage(EntityManagerInterface $em, $name, $url, $filename)
    {
        $Page = new Page();
        $Page->setEditType(Page::EDIT_TYPE_DEFAULT);
        $Page->setName($name);
        $Page->setUrl($url);
        $Page->setFileName($filename);

        $em->persist($Page);
        $em->flush($Page);
        $Layout = $em->find(Layout::class, Layout::DEFAULT_LAYOUT_UNDERLAYER_PAGE);
        $PageLayout = new PageLayout();
        $PageLayout->setPage($Page)
            ->setPageId($Page->getId())
            ->setLayout($Layout)
            ->setLayoutId($Layout->getId())
            ->setSortNo(0);
        $em->persist($PageLayout);
        $em->flush($PageLayout);
    }
}
