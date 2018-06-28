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

## プラグインカスタマイズ方法

### PurchaseFlowの解説

### PaymentService, PaymentMethod周辺の解説

## 決済プラグインについて

### シーケンス図（リンク型、トークン型）

### ステートマシン図（リンク型、トークン型）