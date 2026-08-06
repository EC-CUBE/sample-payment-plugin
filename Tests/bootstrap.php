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

// 本プラグインは EC-CUBE 本体へ組み込んだ状態 (app/Plugin/SamplePayment44) で実行する前提のため、
// 本体の autoload / .env を読み込む。
$loader = require __DIR__.'/../../../../vendor/autoload.php';

$envFile = __DIR__.'/../../../../.env';
if (file_exists($envFile)) {
    (new Symfony\Component\Dotenv\Dotenv())->load($envFile);
}
