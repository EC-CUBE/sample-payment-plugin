# sample-payment-plugin
EC-CUBE 3.nの決済プラグインサンプルです。
リンク型とトークン型の２種類のクレジットカード決済方法を追加できます。
EC-CUBE3.nは開発中であり、APIの仕様は変更になる場合があります。

# EC-CUBE3.n

- [本体ソースコード](https://github.com/EC-CUBE/ec-cube/tree/experimental/sf)
- [開発ドキュメント・マニュアル](http://doc3n.ec-cube.net/)

## EC-CUBEのインストール手順
TODO: マニュアルに移動させてリンクを貼る

利用できるPostgresかMySQLを立ち上げておきます。

1. [こちら](https://github.com/EC-CUBE/ec-cube)からEC-CUBEのリポジトリをclone
```git clone https://github.com/EC-CUBE/ec-cube.git```
1. ディレクトリを移動
```cd ec-cube```
1. `experimental/sf` のブランチをチェックアウト
```git checkout experimental/sf```
1. ec-cubeのインストールコマンドを実行。
```bin/console eccube:install```
1. DATABASE_URLを入力、他はそのままエンターでOK。
1. サーバの起動
```bin/console server:run```
1. ブラウザでアクセス
http://127.0.0.1:8000/

### DBごとのDATABASE_URL設定例

```
## PostgreSQL
DATABASE_URL=postgresql://db_user:db_password@127.0.0.1:5432/db_name

## MySQL
DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3306/db_name
```

# プラグイン導入方法

## プラグインファイルの配置

`/app/Plugin/` にプラグインのファイルを配置してください。

本サンプルプラグインの場合は以下となります。

`/app/Plugin/sample-payment-plugin`

## コマンドラインインタフェース

### 利用例
- インストール
`bin/console eccube:plugin:install --code=SamplePayment`
- 有効化
`bin/console eccube:plugin:enable --code=SamplePayment`
- 無効化
`bin/console eccube:plugin:disable --code=SamplePayment`
- 削除
`bin/console eccube:plugin:uninstall --code=SamplePayment`

### プラグインジェネレータ

以下のコマンドで推奨ディレクトリ構成のプラグインサンプルが生成できます。

`bin/console eccube:plugin:generate`

## プラグインカスタマイズ

### 推奨ディレクトリ構成

プラグインのディレクトリ構成ですが、極力EC-CUBE3本体のディレクトリ構成に合わせる事を推奨します。但し、全てのディレクトリが必要ではなく必要に応じてディレクトリをプラグイン側に作成してください。

- ディレクトリ例

```
[プラグインコード]
  ├── Controller
  │   └── XXXXController.php
  ├── Entity
  │   └── XXXX.php
  ├── Form
  │   ├── Extension
  │   │   └── XXXXTypeExtension.php
  │   └── Type
  │           └── XXXXType.php
  ├── Repository
  │   └── XXXXRepository.php
  ├── Resource
  │   ├── assets
  │   │   ├── css
  │   │   │   └── xxxx.css
  │   │   ├── img
  │   │   │   ├── xxxx.gif
  │   │   │   ├── xxxx.jpg
  │   │   │   └── xxxx.png
  │   │   └── js
  │   │       └── xxxx.js
  │   ├── locale
  │   │   └── messages.ja.yaml
  │   │   └── validators.ja.yaml
  │   └── template
  │           ├── Block
  │           │   └── XXXX.twig
  │           ├── admin
  │           │   └── XXXX.twig
  │           └── XXXX.twig
  ├── Service
  │   └── XXXXService.php
  ├── PluginManager.php
  ├── LICENSE.txt
  ├── XXXXEvent.php
  ├── XXXXNav.php
  ├── XXXXTwigBlock.php
  └── config.yml
```

### 命名規約は以下のissueを参照

https://github.com/EC-CUBE/sample-payment-plugin/issues/6

### ルーティングの追加

TODO

### Entity拡張

TODO

#### proxyファイルについて


### FormType拡張

TODO

### イベントの追加

`EventSubscriberInterface` を実装し、イベントを追加します。
1. `Event.php` ファイルの `getSubscribedEvents()` メソッドの戻り値で追加するイベントを指定します。（3.0系の `event.yml` の内容に相当）
1. `Event.php` に呼び出すメソッドを定義します。

```php
class Event implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return ['eventName' => 'methodName'];
    }

    public function methodName(Event $event)
    {
    }
}
```


### 管理画面ナビの拡張

管理画面にプラグインのメニューを追加します。
以下のようにEccubeNavを実装すると, 対象メニュー内の最下部に追加されます。
プラグインの場合、有効時のみ表示されます。

```php
class Nav implements EccubeNav
{
    public static function getNav()
    {
        return [
            'order' => [
                'id' => 'sample_payment_admin_payment_status',
                'name' => 'sample_payment.admin.nav.payment_list',
                'url' => 'sample_payment_admin_payment_status',
            ],
        ];
    }
}
```

本体の管理画面ナビは `/app/config/eccube/packages/eccube_nav.yaml` で定義されています。

### Twigユーザ定義関数の読み込み

`EccubeTwigBlock`を実装し、対象のテンプレートファイルを読み込みます。

```php
class TwigBlock impletemts EccubeTwigBlock
{
    public static function getTwigBlocks()
    {
        return ['@SamplePayment/hello_block.twig']
    }
}
```

`/Resource/template/` 配下にblockの定義ファイルを作成します。

```html
{% block hello %}
    <h1>Hello, {{ name }}!</h1>
{% endblock %}
```

twigファイルに以下のように記載することでBlockが呼び出せます。

```
{{ eccube_block_hello({ name: 'hoge'}) }}
```

### PaymentMethodInterface の拡張

各決済ごとに `PaymentMethodInterface` を実装することで決済に独自の処理を追加できます。

#### `verify()`

注文手続き画面でsubmitされた時に実行する処理を実装します。
主に、クレジットカード決済の有効性チェックをするために使用します。
このメソッドは、 `PaymentResult` を返します。
`PaymentResult` には、実行結果、エラーメッセージなどを設定します。
`Response` を設定して、他の画面にリダイレクトしたり、独自の出力を実装することも可能です。
 
#### `apply()`

注文確認画面でsubmitされた時に、他の Controller へ処理を移譲する実装をします。
主にリンク型決済や、キャリア決済など、決済会社の画面へ遷移する必要がある場合に使用します。
また、独自に作成した Controller に遷移する場合にも使用できます。
このメソッドは `PaymentDispatcher` を返します。
`PaymentDispatcher` は、他の Controller へ `Redirect` もしくは `Forward` させるための情報を設定します。
決済会社の画面など、サイト外へ遷移させる場合は、 `Response` を設定します。

#### `checkout()`

注文確認画面でsubmitされた時に決済完了処理を記載します。
このメソッドは、 `PaymentResult` を返します。
`PaymentResult` には、実行結果、エラーメッセージなどを設定します。
3Dセキュア決済の場合は、 `Response` を設定して、独自の出力を実装することも可能です。

### PurchaseFlowの処理の流れ

TODO

### メッセージIDについて

多言語対応ができる旨とtrans関数、メッセージファイル、について、メッセージIDの命名ルールについて記載

### プラグインのインストール・有効化・無効化・削除の考え方

#### インストール
ファイルの配置のみ

#### 有効化
DBの更新

#### 無効化
DBは変更しない

#### 削除
DBの更新とファイルの削除

### DBの更新方法

1. Entity拡張のORMアノテーションでDBの設定を更新
1. コマンドラインからプロキシファイルを作成 `bin/console eccube:generate:proxies`
1. DBの更新内容の確認 `bin/console doctrine:schema:update --dump-sql`
1. DBの更新を実行 `bin/console doctrine:schema:update --dump-sql --force`

## 決済プラグインについて

### ファイルごとの概要

#### [Plugin\SamplePayment\Service\Method\CreditCard](https://github.com/EC-CUBE/sample-ugin/blob/master/Service/Method/CreditCard.php)

トークン型クレジットカード払い用のビジネスロジッククラス

#### [Plugin\SamplePayment\Service\Method\LinkCreditCard](https://github.com/EC-CUBE/sample-ugin/blob/master/Service/Method/LinkCreditCard.php)

リンク型クレジットカード払い用のビジネスロジッククラス

#### [Plugin\SamplePayment\Controller\Admin\ConfigController](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Controller/Admin/ConfigController.php)

プラグイン設定画面のコントローラクラス。

#### [Plugin\SamplePayment\Controller\Admin\OrderController](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Controller/Admin/OrderController.php)

受注編集画面から Ajax で通信するコントローラクラス。
主に管理画面の操作と連動して、決済サーバーとの通信を実装する

#### [Plugin\SamplePayment\Controller\Admin\PaymentStatusController](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Controller/Admin/PaymentStatusController.php)

決済ステータス一括変更画面のコントローラクラス

#### [Plugin\SamplePayment\Controller\PaymentCompanyController](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Controller/PaymentCompanyController.php)

リンク型決済のダミー画面。決済会社のカード入力フォームに相当する。

#### [Plugin\SamplePayment\Controller\PaymentController](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Controller/PaymentController.php)

リンク型決済と連携するためのコントローラクラス。

- 戻りURL
- 完了URL
- 決済完了通知先URL

などを実装する。

#### [Plugin\SamplePayment\Entity\Config](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Entity/Config.php)

プラグイン設定画面のエンティティクラス。

#### [Plugin\SamplePayment\Entity\CustomerTrait](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Entity/CustomerTrait.php)

Customer 拡張用のトレイト。決済会社から取得した、クレジットカード等の JSON データを格納する。

#### [Plugin\SamplePayment\Entity\OrderTrait](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Entity/OrderTrait.php)

Order 拡張用のトレイト。クレジットカードのトークンや、決済ステータスなどを格納する。

#### [Plugin\SamplePayment\Entity\PaymentStatus](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Entity/PaymentStatus.php)

決済ステータスのエンティティクラス。

#### [Plugin\SamplePayment\Event](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Event.php)

プラグインで使用する `EventSubscriber`
管理画面のテンプレートを拡張するために使用している。

#### [Plugin\SamplePayment\Form\Extension\CreditCardExtention](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Form/Extension/CreditCardExtention.php)

クレジットカード払い用のフォームエクステンション。
ご注文情報入力画面に、クレジットカード入力フォームを実装するために使用する。

#### [Plugin\SamplePayment\Form\Type\Admin\ConfigType](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Form/Type/Admin/ConfigType.php)

プラグイン設定画面用のフォームタイプ

#### [Plugin\SamplePayment\Form\Type\Admin\SearchPaymentType](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Form/Type/Admin/SearchPaymentType.php)

決済ステータス一括変更画面用のフォームタイプ

#### [Plugin\SamplePayment\Nav](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Nav.php)

管理画面ナビ拡張用クラス

#### [Plugin\SamplePayment\PluginManager](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/PluginManager.php)

PluginManager クラス。 install/uninstall/enable/disable の処理を実装する。

#### [Plugin\SamplePayment\PluginManager\Repository\ConfigRepository](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/ConfigRepository.php)

プラグイン設定画面用のリポジトリクラス

#### [Plugin\SamplePayment\PluginManager\Repository\PaymentStatusRepository](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/PaymentStatusRepository.php)

決済ステータス用のリポジトリクラス

#### [Plugin\SamplePayment\TwigBlock](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/TwigBlock.php)

TwigBlock定義用クラス

#### [config.yml](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/config.yml)

プラグイン定義ファイル

#### [Resource/config/services.yaml](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Resource/config/services.yaml)

パラメータ定義用設定ファイル

#### [Resource/locale/messages.ja.yaml](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Resource/locale/messages.ja.yaml)

メッセージ翻訳ファイル

#### [Resource/locale/validators.ja.yaml](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Resource/locale/validators.ja.yaml)

エラーメッセージ翻訳ファイル


#### [Resource/template/*.twig](https://github.com/EC-CUBE/sample-payment-plugin/blob/master/Resource/template)

各種テンプレートファイル


### シーケンス図

#### リンク型決済

![リンク型決済シーケンス図](https://github.com/okazy/sample-payment-plugin/raw/images/LinkPaymentSequenceDiagram.png "リンク型決済シーケンス図")

#### トークン型決済

![トークン型決済シーケンス図](https://github.com/okazy/sample-payment-plugin/raw/images/TokenPaymentSequenceDiagram.png "トークン型決済シーケンス図")

#### トークン型決済（3Dセキュア）

![トークン型決済シーケンス図](https://github.com/okazy/sample-payment-plugin/raw/images/TokenPaymentSequenceDiagram_3D.png "トークン型決済シーケンス図")

### 受注ステータスステートマシン図

#### リンク型決済

![リンク型決済ステートマシン図](https://github.com/okazy/sample-payment-plugin/raw/images/LinkPaymentStateMachineDiagram.png "リンク型決済ステートマシン図")

#### トークン型決済

![トークン型決済ステートマシン図](https://github.com/okazy/sample-payment-plugin/raw/images/TokenPaymentStateMachineDiagram.png "トークン型決済ステートマシン図")


