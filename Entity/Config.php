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

namespace Plugin\SamplePayment44\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Plugin\SamplePayment44\Repository\ConfigRepository;

/**
 * Config
 */
#[ORM\Table(name: 'plg_sample_payment_config')]
#[ORM\Entity(repositoryClass: ConfigRepository::class)]
class Config
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: ['unsigned' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'api_url', type: Types::STRING, length: 1024, nullable: true)]
    private ?string $api_url = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'api_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $api_id = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'api_password', type: Types::STRING, length: 255, nullable: true)]
    private ?string $api_password = null;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->api_url;
    }

    /**
     * @param string $api_url
     *
     * @return $this;
     */
    public function setApiUrl(string $api_url)
    {
        $this->api_url = $api_url;

        return $this;
    }

    /**
     * @return string
     */
    public function getApiId(): string
    {
        return $this->api_id;
    }

    /**
     * @param string $api_id
     *
     * @return $this;
     */
    public function setApiId(string $api_id)
    {
        $this->api_id = $api_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getApiPassword(): string
    {
        return $this->api_password;
    }

    /**
     * @param string $api_password
     *
     * @return $this
     */
    public function setApiPassword(string $api_password)
    {
        $this->api_password = $api_password;

        return $this;
    }
}
