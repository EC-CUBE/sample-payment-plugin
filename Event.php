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

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Event implements EventSubscriberInterface
{
    /**
     * リッスンしたいサブスクライバのイベント名の配列を返します。
     * 配列のキーはイベント名、値は以下のどれかをしてします。
     * - 呼び出すメソッド名
     * - 呼び出すメソッド名と優先度の配列
     * - 呼び出すメソッド名と優先度の配列の配列
     * 優先度を省略した場合は0
     *
     * 例：
     * - array('eventName' => 'methodName')
     * - array('eventName' => array('methodName', $priority))
     * - array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Order/edit.twig' => 'onAdminOrderEditTwig',
        ];
    }

    public function onAdminOrderEditTwig(TemplateEvent $event)
    {
        $search = '{% block main %}';
        $replace = '{% block main %}{{ include("@SamplePayment/admin/order_edit.twig") }}';
        $source = str_replace($search, $replace, $event->getSource());
        $event->setSource($source);
    }
}
