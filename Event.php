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
