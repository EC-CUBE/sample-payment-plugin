# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## このリポジトリについて

EC-CUBE 4 系の**決済プラグイン実装サンプル**。実際の決済代行会社向けプラグインを実装する開発者の参考実装として、3 種類の決済方式を 1 プラグインに収めている。

- **リンク型クレジットカード決済** (`LinkCreditCard`) — 外部決済サーバの入力画面へリダイレクトする方式
- **トークン型クレジットカード決済** (`CreditCard`) — トークンを受け取り自サイト内で完結する方式
- **コンビニ決済** (`Convenience`) — 入金待ちステータスを持つ方式

プラグインコードは `SamplePayment42`、Composer パッケージ名は `ec-cube/samplepayment42`。コード中の Twig 名前空間・クラス名前空間・トランス キーはすべて `SamplePayment42` 接頭辞を使う。

### ブランチ運用

ブランチ名が対応する EC-CUBE 本体バージョンを表す (`4.2`, `support-4.3`, `4.4` など)。`4.2` がデフォルトブランチ。本体 API の差異に応じて各バージョン用ブランチを保守している。各バージョンで動作する Docker イメージは `docker-compose.yml` の `image:` タグ (例 `ghcr.io/ec-cube/ec-cube-php:7.4-apache-4.2`) で固定されている。

## 開発・テストコマンド

このプラグイン単体では動作せず、**EC-CUBE 本体に組み込んだ状態**で開発・テストする。本体への組み込みと有効化は `docker-compose.dev.yml` の entrypoint が自動実行する (`eccube:composer:require` → `eccube:plugin:enable` → `dtb_payment_option` への INSERT)。

```bash
# 開発環境 (SQLite) の起動 — 本体取得・プラグイン有効化まで自動
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --wait

# MySQL / PostgreSQL で起動する場合は対応ファイルを重ねる
docker compose -f docker-compose.yml -f docker-compose.mysql.yml -f docker-compose.dev.yml up -d --wait
docker compose -f docker-compose.yml -f docker-compose.pgsql.yml -f docker-compose.dev.yml up -d --wait

# 起動確認・ログ
docker compose logs ec-cube
```

起動後のフロント URL は `https://localhost:4430` (自己署名証明書のため `ignoreHTTPSErrors`)、メールは MailCatcher (`http://localhost:1080`) で受信。

### E2E テスト (Playwright)

テストは `tests/*.test.ts` の Playwright E2E のみ (PHPUnit は無い)。`baseURL` は `https://localhost:4430`、対象は起動中の Docker 環境。

```bash
npm ci                              # 依存インストール
npx playwright install              # ブラウザ取得
yarn playwright test                # 全 E2E 実行
yarn playwright test tests/guest_credit_token.test.ts   # 単一ファイル実行
npx playwright test -g "テスト名"   # タイトル一致で単一テスト実行
```

テストファイルは決済方式ごと: `guest_credit_link` (リンク型) / `guest_credit_token` (トークン型) / `guest_convini` (コンビニ)。CI (`.github/workflows/playwright.yml`) は mysql / pgsql / sqlite3 のマトリクスで実行される。

## 決済プラグインのアーキテクチャ

### 決済処理ライフサイクル (最重要)

各決済方式は `Eccube\Service\Payment\PaymentMethodInterface` を実装する `Service/Method/*.php` で、本体の購入フロー (`PurchaseFlow`) と連携して 3 段階で呼ばれる。**この 3 メソッドの責務分担が決済プラグイン実装の核心**:

1. **`verify()`** — 注文確認画面遷移時。カードの有効性チェック等。`PaymentResult` を返す。トークン型はここでカード下 4 桁を取得・保持する。リンク型は実質何もしない。
2. **`apply()`** — 注文確定時、決済実行**前**。受注ステータスを「決済処理中(PENDING)」、決済ステータスを「未決済(OUTSTANDING)」に変更し `purchaseFlow->prepare()` を呼ぶ。**リンク型はここで `PaymentDispatcher` に `RedirectResponse` を載せて返し、外部決済画面へ遷移させる**(`checkout()` は使わない)。
3. **`checkout()`** — 注文確定時、決済実行。決済成功時は受注ステータスを NEW、決済ステータスを「仮売上(PROVISIONAL_SALES)」にして `purchaseFlow->commit()`、失敗時は `purchaseFlow->rollback()` してエラーを `PaymentResult` に詰める。

成功/失敗は必ず `PaymentResult::setSuccess()` で表現し、`purchaseFlow` の `prepare`/`commit`/`rollback` と受注・決済ステータス更新を**対で**行うのが規約。各 Method は本体の `PurchaseFlow $shoppingPurchaseFlow` を DI で受け取る。

### リンク型決済のリダイレクトフロー

リンク型は `apply()` でのリダイレクト後、外部決済サーバとのやり取りを `Controller/PaymentController.php` (注文/戻る/完了通知) と `Controller/PaymentCompanyController.php` (決済会社画面の模擬) で処理する。本物の決済プラグインではここが Webhook / コールバック受信に相当する。

### Entity 拡張 (trait + @EntityExtension)

本体の既存 Entity にカラムを追加する際は `Entity/*Trait.php` に trait を定義し、クラス DocComment に `@EntityExtension("Eccube\Entity\Order")` を付与する。本サンプルでは:

- `OrderTrait` — `Order` にトークン・カード下 4 桁・コンビニ種別・決済ステータスを追加 (`dtb_order.sample_payment_*` カラム)。下 4 桁のみ永続化せず確認画面表示用。
- `CustomerTrait` — `Customer` にカード情報変更機能用のカラムを追加。

プラグイン独自 Entity (`Config`, `PaymentStatus`, `CvsPaymentStatus`, `CvsType`) は通常の Doctrine Entity として `Entity/` に置き、対応する `Repository/` を持つ。

### 画面への介入 (TemplateEvent)

`SamplePaymentEvent.php` (`EventSubscriberInterface`) が `getSubscribedEvents()` でフックする Twig を宣言し、`TemplateEvent::addSnippet()` で `Resource/template/*.twig` を差し込む。本体テンプレートを直接編集せずに購入画面・確認画面・管理画面注文編集・マイページナビへ UI を追加する。`SamplePaymentNav.php` が管理画面メニュー、`SamplePaymentTwigBlock.php` がブロックを追加する。

### PluginManager (インストール時処理)

`PluginManager.php` の `enable()` が有効化時に実行される。決済方法 (`Payment` レコード) 3 種・初期設定・各種マスタ (PaymentStatus / CvsPaymentStatus / CvsType) ・マイページのカード情報変更ページ (`createPages()`) を登録する。決済方法と `Service/Method/*` クラスの紐付けもここで行う。

### スロットリング設定

`Resource/config/services.yaml` の `eccube.rate_limiter` でルート単位のレート制限を宣言できる (本サンプルではカード情報変更 `sample_payment_mypage_card_info` を ip/customer で 60 分 5 回に制限)。EC-CUBE 4.2+ のレートリミッタ機能を使う実装例。

### 開発ツール設定ファイルは `Resource/` 配下に置く (rector.php / .php-cs-fixer.dist.php)

`rector.php` や `.php-cs-fixer.dist.php` を**プラグインのルート直下に置いてはならない**。`Resource/rector.php` のように `Resource/` 配下に置く。

**理由**: EC-CUBE 本体の `config/eccube/services.yaml` がプラグインを丸ごと PSR-4 サービス検出対象として読み込む:

```yaml
Plugin\:
    resource: '../../../app/Plugin/*'
    exclude: '../../../app/Plugin/*/{Entity,Resource,ServiceProvider,Tests,Codeception,DoctrineMigrations}'
```

`app/Plugin/SamplePayment42/` 直下のすべての `*.php` が「サービスクラス」として読み込まれるため、ルートに `rector.php` を置くと Symfony が `Plugin\SamplePayment42\rector` クラスを期待し、見つからず **EC-CUBE 全体が 500 エラー**になる (実際に遭遇したエラー):

```
Expected to find class "Plugin\SamplePayment42\rector" in file
".../app/Plugin/SamplePayment42/rector.php" while importing services from
resource "../../../app/Plugin/*", but it was not found!
```

上記 `exclude` に `Resource` が含まれるため、`Resource/` 配下に置けばサービス検出から外れ衝突しない。

**本体では問題にならない理由**: 本体の autoconfigure 対象は `src/Eccube/*` で、プロジェクトルートはその外。ルート直下の `rector.php` / `.php-cs-fixer.dist.php` はグロブにかからないため本体では慣習どおりルートに置ける。プラグインは「ルートディレクトリ自体が PSR-4 ルートかつサービス検出対象」という点が決定的に異なる。

**トレードオフ / 運用上の注意**:
- 代償として `__DIR__` 基準のパスを `dirname(__DIR__)` に変更し、実行時に `--config=Resource/rector.php` を明示する必要がある。
- 代替案 (ルートに置いて本体の `exclude` に追記) は本体改変が必要でプラグインの独立性を損なうため不可。プラグイン側の `services.yaml` では本体の `Plugin\:` 定義を上書きできない。
- `.php-cs-fixer.dist.php` はドット始まりのため単体ではグロブに当たらない可能性もあるが (未検証)、`rector.php` と配置・運用を揃える一貫性のため同じ `Resource/` に置く。
- **将来「本体に合わせてルートへ戻す」とリグレッションするため、この配置を変更しないこと。**

## 規約メモ

- 命名規約は本体の `eccube:plugin:generate` が生成する推奨ディレクトリ構成に合わせる (詳細は README.md および [issue #6](https://github.com/EC-CUBE/sample-payment-plugin/issues/6))。
- 翻訳は `Resource/locale/messages.ja.yaml` / `validators.ja.yaml`。コードからは `trans('sample_payment.xxx')` で参照する。
- 全 PHP ファイル冒頭に EC-CUBE 標準のライセンスヘッダを付与する。
