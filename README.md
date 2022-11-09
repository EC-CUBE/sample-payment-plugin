# sample-payment-plugin
EC-CUBE4の決済プラグインサンプルです。
リンク型クレジットカード決済とトークン型クレジットカード決済およびコンビニ決済の3種類の決済方法を追加できます。

- [EC-CUBE4](#ec-cube4)
  - [EC-CUBEのインストール手順](#ec-cubeのインストール手順)
- [プラグイン導入方法](#プラグイン導入方法)
  - [プラグインファイルの配置](#プラグインファイルの配置)
  - [コマンドラインインタフェース](#コマンドラインインタフェース)
  - [プラグインカスタマイズ](#プラグインカスタマイズ)
- [決済プラグインについて](#決済プラグインについて)
  - [ファイルごとの概要](#ファイルごとの概要)
  - [シーケンス図](#シーケンス図)
  - [受注ステータスステートマシン図](#受注ステータスステートマシン図)

# EC-CUBE4

- [本体ソースコード](https://github.com/EC-CUBE/ec-cube)
- [開発ドキュメント・マニュアル](http://doc4.ec-cube.net/)

## EC-CUBEのインストール手順

EC-CUBEのインストール手順は、以下を参照してください。

http://doc4.ec-cube.net/quickstart_install

# プラグイン導入方法

## プラグインファイルの配置

`/app/Plugin/` にディレクトリ名がプラグインコードとなるディレクトリを作成し、そこにプラグインのファイルを配置してください。

本サンプルプラグインの場合は以下のようになります。

`/app/Plugin/SamplePayment`

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

プラグインのディレクトリ構成ですが、極力EC-CUBE4本体のディレクトリ構成に合わせる事を推奨します。但し、全てのディレクトリが必要ではなく必要に応じてディレクトリをプラグイン側に作成してください。

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
  └── composer.json
```

命名規約は[こちら](https://github.com/EC-CUBE/sample-payment-plugin/issues/6)のissueを参照

### ルーティングの追加

`@Route` アノテーションを付与したクラスファイルを `Controller` 以下に配置することで、サイトに新しいルーティングを追加することが可能です。

Controllerファイルについては開発ドキュメント・マニュアルの[Controllerのカスタマイズ](http://doc4.ec-cube.net/customize_controller)ページをご確認ください。

### Entity拡張

クラスファイルを `Entity` 以下に配置することで新しいEntityを追加可能です。

traitと `@EntityExtension` アノテーションを使用して、既存Entityのフィールドを拡張可能です。

また、`@EntityExtension` アノテーションで拡張したフィールドに `@FormAppend` アノテーションを追加することで、フォームを自動生成できます。

Entityファイルについては開発ドキュメント・マニュアルの[Entityのカスタマイズ](http://doc4.ec-cube.net/customize_entity)ページをご確認ください。

### FormType拡張

FormExtensionの仕組みを利用すれば、既存のフォームをカスタマイズすることができます。

`Form/Extension` に `AbstractTypeExtension` を継承したクラスファイルを作成することで、FormExtensionとして認識されます。

FormExtensionについては開発ドキュメント・マニュアルの[FormTypeのカスタマイズ](http://doc4.ec-cube.net/customize_formtype)ページをご確認ください。

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
以下のようにEccubeNavを実装すると, メニューの上書き/追加ができます。
追加の場合は対象メニュー内の最下部に追加されます。
構造は本体のナビ定義も参考にしてください。
プラグインの場合、有効時のみ表示されます。

```php
class Nav implements EccubeNav
{
    public static function getNav()
    {
        return [
            'product' => [
                'children' => [
                    'hoge' => [
                        'name' => '商品管理の子（追加）',
                        'url' => 'admin_homepage',
                    ],
                ],
            ],
            'piyo' => [
                'name' => '1階層メニュー（追加）',
                'icon' => 'fa-cube',
                'children' => [
                    'piyopiyo1' => [
                        'name' => '2階層メニュー（子なし）',
                        'url' => 'admin_homepage',
                    ],
                    'piyopiyo2' => [
                        'name' => '2階層メニュー（子あり）',
                        'children' => [
                            'piyopiyopiyo1' => [
                                'name' => '3階層メニュー1',
                                'url' => 'admin_homepage',
                            ],
                            'piyopiyopiyo2' => [
                                'name' => '3階層メニュー2',
                                'url' => 'admin_homepage',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
```

本体の管理画面ナビは `/app/config/eccube/packages/eccube_nav.yaml` で定義されています。

### フロント画面へのパーツ埋め込み

フロント画面はtwigにtagを埋め込むカスタマイズ方法を推奨します。

`/Resource/template/` 配下にテンプレートファイルを作成します。

```twig
<h1>Hello, {{ name }}!</h1>
```

twigファイルに以下のように記載することで作成したテンプレートが呼び出せます。

```twig
{{ include('@プラグインコード/xxx.twig', ignore_missing=true) }}
```

twig内で変数を使用する場合は、TemplateEventで渡します。

```php
class Event implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'xxx.twig' => 'onXxxTwig',
        ];
    }
    
    public function onXxxTwig(TemplateEvent $event)
    {
        $event->setParameter('name', '球部太郎');
    }
}
```

本サンプルプラグインではトークン型クレジットカード決済とコンビニ決済の入力フォームと確認フォームのタグを手動で埋め込む必要があります。

管理画面のページ管理からテンプレートファイルの適当な箇所に以下のタグを追加してください。

- 商品購入ページ
```twig
{{ include('@SamplePayment/credit.twig', ignore_missing=true) }}
{{ include('@SamplePayment/cvs.twig', ignore_missing=true) }}
```
- 商品購入/ご注文確認ページ
```twig
{{ include('@SamplePayment/credit_confirm.twig', ignore_missing=true) }}
{{ include('@SamplePayment/cvs_confirm.twig', ignore_missing=true) }}
```

### 画面への介入について

EC-CUBE3.0系では画面の拡張をする場合、直接Twigファイルを書き換えたりしていましたが、
EC-CUBE4からはTemplateEventに新たな関数を用意し、それを利用することでJavaScriptを使って簡単に制御することが可能となります。


* TemplateEvent抜粋
```php
/**
 * アセットを追加する
 *
 * ここで追加したコードは, <head></head>内に出力される
 * javascriptの読み込みやcssの読み込みに利用する.
 *
 * @param $asset
 * @param bool $include twigファイルとしてincludeするかどうか
 *
 * @return $this
 */
public function addAsset($asset, $include = true)
{
    $this->assets[$asset] = $include;

    $this->setParameter('plugin_assets', $this->assets);

    return $this;
}

/**
 * スニペットを追加する.
 *
 * ここで追加したコードは, </body>タグ直前に出力される
 *
 * @param $snippet
 * @param bool $include twigファイルとしてincludeするかどうか
 *
 * @return $this
 */
public function addSnippet($snippet, $include = true)
{
    $this->snippets[$snippet] = $include;

    $this->setParameter('plugin_snippets', $this->snippets);

    return $this;
}
```

 このプラグインを利用する方法は以下の通りです。例として商品一覧に対して列を追加する方法となります。

* AdminSampleEvent を作成
```php
<?php
namespace Plugin\AdminSample;

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdminSampleEvent implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Product/index.twig' => 'productList',
        ];
    }

    public function productList(TemplateEvent $event)
    {
        $twig = '@AdminSample/product_list.twig';
        $event->addSnippet($twig);
    }
}
```

というEventクラスを作成し、

`app/Plugin/AdminSample/Resource/template/product_list.twig` というファイルを作成後、

```php
{% for p in pagination %}
    <div class="p{{ loop.index }}" data-status="{{ p.Status.id }}">{{ p.name }}</div>
{% endfor %}

<script>
    $(function() {
        $('table tr').each(function(i) {
            if (i != 0) {
                $elem = $('.p' + i);
                if ($elem.data('status') == '2') {
                    $(this).addClass('table-secondary');
                }
                $('td:eq(4)', this).after('<td class="align-middle">' + $elem.text() + '</td>');
                $('td:eq(5)', this).after('<td class="align-middle"><button type="button" class="btn btn-light" data-hoge="' + i + '">ボタン' + i + '</button></td>');
                $elem.remove();
            } else {
                $('th:eq(4)', this).after('<th class="border-top-0 pt-2 pb-2">名称</th>');
                $('th:eq(5)', this).after('<th class="border-top-0 pt-2 pb-2">ボタン</th>');
            }
        });

        $(document).on('click', '.btn-light', function() {
            alert($(this).data('hoge'));
        })
    });
</script>
```

と記述することで簡単に画面要素の介入が可能となります。`product_list.twig`ファイルには何を記述しても構いません。

### PaymentMethodInterface の拡張

各決済ごとに `PaymentMethodInterface` を実装することで決済に独自の処理を追加できます。また、 `PaymentMethodInterface` の実装クラスは `PurchaseFlow` を使用して購入処理中の受注データを確定させる必要があります。

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

このメソッドでは購入処理中の受注データを仮確定させるために `PurchaseFlow#prepare()` を実行します。仮確定してサイト外へ遷移した後に、遷移先でユーザが決済をキャンセルした場合は、仮確定を取り消す必要があります。仮確定の取り消しには、まずサイト外から決済をキャンセルして戻ってくるためのControllerを用意しておきます。このControllerの処理の中で `PurchaseFlow#rollback()` を実行します。仮確定取消後は注文手続き画面に遷移させるようにします。

サイト外へ遷移後、ユーザが決済処理を完了したときは、決済手続き完了用のControllerに遷移させます。このタイミングで購入処理中の受注を確定できる場合は `PurchaseFlow#commit()` を実行します。この遷移タイミングではなく、決済サーバからの完了通知を受けて購入処理を確定する場合は、完了通知用のControllerを実装し `PurchaseFlow#commit()` を実行するようにします。

#### `checkout()`

注文確認画面でsubmitされた時に決済完了処理を記載します。
このメソッドは、 `PaymentResult` を返します。
`PaymentResult` には、実行結果、エラーメッセージなどを設定します。
3Dセキュア決済の場合は、 `Response` を設定して、独自の出力を実装することも可能です。

決済処理をこのメソッドで完了できる場合は、`PurchaseFlow#commit()` を実行して購入処理中の受注データを確定します。3Dセキュア決済などで他サイトへの遷移が必要な場合は、他サイトでの処理を完了して呼び戻されるControllerの中で `PurchaseFlow#commit()` を実行します。他サイトでの処理がキャンセルされたときは `PurchaseFlow#rollback()` を呼び出す必要があります。

### PurchaseFlowについて

EC-CUBE4ではPurchaseFlowをカスタマイズすることで購入フローのカスタマイズが可能になります。

PurchaseFlowについては開発ドキュメント・マニュアルの[Serviceのカスタマイズ](http://doc4.ec-cube.net/customize_service#%E8%B3%BC%E5%85%A5%E3%83%95%E3%83%AD%E3%83%BC%E3%81%AE%E3%82%AB%E3%82%B9%E3%82%BF%E3%83%9E%E3%82%A4%E3%82%BA-2424)ページをご確認ください。

※PurchaseFlowは改善が進められており、ドキュメントの内容に古い部分があります。随時更新していきます。

### メッセージIDについて

メッセージファイルを `Resource/locale` 以下に配置すると多言語対応が可能です。

- messages.ja.yaml: 日本語のメッセージファイル
- validators.ja.yaml: 日本語のバリデーションメッセージファイル

例えば `messages.en.yaml` ファイルを用意し、EC-CUBE本体の `.env` ファイルで `ECCUBE_LOCALE=en` と設定すると読み込まれるメッセージファイルが切り替わります。

phpのソースコード内でメッセージを使用する場合にはグローバル関数の `trans()` が利用できます。

```php
trans('message.id');
```

twigのソースコード内でメッセージを使用する場合には `trans` フィルタが利用できます。

```twig
{{ 'message.id'|trans }}
```

重複防止のためプラグイン内で利用するメッセージIDにはプラグインコードのプレフィックスをつけてください。

その他の命名規則については[こちら](https://github.com/EC-CUBE/ec-cube/issues/2597#issuecomment-345912583)のissueを確認してください。

### DBの更新方法

1. Entity拡張のORMアノテーションでDBの設定を更新
1. コマンドラインからプロキシファイルを作成 `bin/console eccube:generate:proxies`
1. DBの更新内容の確認 `bin/console doctrine:schema:update --dump-sql`
1. DBの更新を実行 `bin/console doctrine:schema:update --dump-sql --force`

# 決済プラグインについて

## ファイルごとの概要

### [Plugin\SamplePayment\Service\Method\CreditCard](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Service/Method/CreditCard.php)

トークン型クレジットカード払い用のビジネスロジッククラス

### [Plugin\SamplePayment\Service\Method\LinkCreditCard](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Service/Method/LinkCreditCard.php)

リンク型クレジットカード払い用のビジネスロジッククラス

### [Plugin\SamplePayment\Service\Method\Convenience](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Service/Method/Convenience.php)

コンビニ決済用のビジネスロジッククラス

### [Plugin\SamplePayment\Controller\Admin\ConfigController](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Controller/Admin/ConfigController.php)

プラグイン設定画面のコントローラクラス。

### [Plugin\SamplePayment\Controller\Admin\OrderController](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Controller/Admin/OrderController.php)

受注編集画面から Ajax で通信するコントローラクラス。
主に管理画面の操作と連動して、決済サーバーとの通信を実装する

### [Plugin\SamplePayment\Controller\Admin\PaymentStatusController](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Controller/Admin/PaymentStatusController.php)

決済ステータス一括変更画面のコントローラクラス

### [Plugin\SamplePayment\Controller\PaymentCompanyController](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Controller/PaymentCompanyController.php)

リンク型決済のダミー画面。決済会社のカード入力フォームに相当する。

### [Plugin\SamplePayment\Controller\PaymentController](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Controller/PaymentController.php)

リンク型決済およびコンビニ決済と連携するためのコントローラクラス。

- 戻りURL
- 完了URL
- 決済完了通知先URL

などを実装する。

### [Plugin\SamplePayment\Entity\Config](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Entity/Config.php)

プラグイン設定画面のエンティティクラス。

### [Plugin\SamplePayment\Entity\CustomerTrait](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Entity/CustomerTrait.php)

Customer 拡張用のトレイト。決済会社から取得した、クレジットカード等の JSON データを格納する。

### [Plugin\SamplePayment\Entity\OrderTrait](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Entity/OrderTrait.php)

Order 拡張用のトレイト。クレジットカードのトークンや、決済ステータス、コンビニ種別などを格納する。

### [Plugin\SamplePayment\Entity\PaymentStatus](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Entity/PaymentStatus.php)

決済ステータスのエンティティクラス。

### [Plugin\SamplePayment\Entity\CvsPaymentStatus](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Entity/CvsPaymentStatus.php)

コンビニ決済の決済ステータスのエンティティクラス。

### [Plugin\SamplePayment\Entity\CvsType](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Entity/CvsType.php)

コンビニの種別のエンティティクラス。

### [Plugin\SamplePayment\Event](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Event.php)

プラグインで使用する `EventSubscriber`
管理画面のテンプレートを拡張するために使用している。

### [Plugin\SamplePayment\Form\Extension\CreditCardExtention](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Form/Extension/CreditCardExtention.php)

クレジットカード払い用のフォームエクステンション。
ご注文情報入力画面に、クレジットカード入力フォームを実装するために使用する。

### [Plugin\SamplePayment\Form\Extension\CvsExtension](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Form/Extension/CvsExtension.php)

コンビニ決済用のフォームエクステンション。
ご注文情報入力画面に、コンビニ選択フォームを実装するために使用する。

### [Plugin\SamplePayment\Form\Type\Admin\ConfigType](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Form/Type/Admin/ConfigType.php)

プラグイン設定画面用のフォームタイプ

### [Plugin\SamplePayment\Form\Type\Admin\SearchPaymentType](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Form/Type/Admin/SearchPaymentType.php)

決済ステータス一括変更画面用のフォームタイプ

### [Plugin\SamplePayment\Nav](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Nav.php)

管理画面ナビ拡張用クラス

### [Plugin\SamplePayment\PluginManager](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/PluginManager.php)

PluginManager クラス。 install/uninstall/enable/disable の処理を実装する。

### [Plugin\SamplePayment\PluginManager\Repository\ConfigRepository](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Repository/ConfigRepository.php)

プラグイン設定画面用のリポジトリクラス

### [Plugin\SamplePayment\PluginManager\Repository\PaymentStatusRepository](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Repository/PaymentStatusRepository.php)

決済ステータス用のリポジトリクラス

### [Plugin\SamplePayment\PluginManager\Repository\CvsPaymentStatusRepository](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Repository/CvsPaymentStatusRepository.php)

コンビニ決済ステータス用のリポジトリクラス

### [Plugin\SamplePayment\PluginManager\Repository\CvsTypeRepository](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Repository/CvsTypeRepository.php)

コンビニ種別用のリポジトリクラス

### [Plugin\SamplePayment\TwigBlock](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/TwigBlock.php)

TwigBlock定義用クラス

### [composer.json](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/composer.json)

プラグイン定義ファイル

### [Resource/config/services.yaml](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Resource/config/services.yaml)

パラメータ定義用設定ファイル

### [Resource/locale/messages.ja.yaml](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Resource/locale/messages.ja.yaml)

メッセージ翻訳ファイル

### [Resource/locale/validators.ja.yaml](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Resource/locale/validators.ja.yaml)

エラーメッセージ翻訳ファイル


### [Resource/template/*.twig](https://github.com/EC-CUBE/sample-payment-plugin/blob/4.0/Resource/template)

各種テンプレートファイル


## シーケンス図

[こちら](https://github.com/EC-CUBE/sample-payment-plugin/issues/11)のissueを参照

## 受注ステータスステートマシン図

[こちら](https://github.com/EC-CUBE/sample-payment-plugin/issues/10)のissueを参照
