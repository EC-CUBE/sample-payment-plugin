# sample-payment-plugin
EC-CUBE 3.nの決済プラグインサンプルです。
リンク型とトークン型の２種類のクレジットカード決済方法を追加できます。
EC-CUBE3.nは開発中であり、APIの仕様は変更になる場合があります。

# EC-CUBE3.n

- [本体ソースコード](https://github.com/EC-CUBE/ec-cube/tree/experimental/sf)
- [開発ドキュメント・マニュアル](http://doc3n.ec-cube.net/)

## EC-CUBEのインストール手順

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
DATABASE_URL=postgresql://postgres@127.0.0.1:5432/eccube
DATABASE_URL=mysql://root:password@127.0.0.1:3306/eccube
```

# プラグイン導入方法

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
- プラグインサンプルの生成
`bin/console eccube:plugin:generate`

## プラグインカスタマイズ

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

### PaymentMethodの拡張

各決済ごとに `PaymentMethod` を実装することで決済に独自の処理を追加できます。

- `verify()`
  - 注文手続き画面でsubmitされた時に実行する処理を記載します。
- `apply()`
  - 注文確認画面でsubmitされた時に処理を他のcontrollerへ移譲する処理を記載します。
- `checkout()` :
  - 注文確認画面でsubmitされた時に決済完了処理を記載します。

### PurchaseFlowの解説


## 決済プラグインについて

### シーケンス図（リンク型、トークン型）

#### リンク型決済

![リンク型決済シーケンス図](https://github.com/okazy/sample-payment-plugin/raw/images/LinkPaymentSequenceDiagram.png "リンク型決済シーケンス図")



### ステートマシン図（リンク型、トークン型）


