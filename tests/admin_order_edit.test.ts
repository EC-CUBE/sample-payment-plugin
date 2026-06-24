import { test, expect, type Page } from '@playwright/test';

/**
 * 受注編集まわりのリグレッションテスト。
 *
 * 1. order_edit.twig の Twig RuntimeError 回帰防止
 *    受注編集画面へ自動注入される order_edit.twig (`@admin/Order/edit.twig` にフック) が、
 *    version-less の名前空間 `Plugin\SamplePayment\Entity\CvsPaymentStatus` を
 *    `constant()` で参照していたため "Constant ... is undefined" の RuntimeError (HTTP 500)
 *    になっていた。正しい名前空間 `Plugin\SamplePayment44` へ修正したことの回帰防止。
 *    order_edit.twig の `constant()` は `<div class="d-none">` 内で決済種別に関わらず
 *    常に評価されるため、修正前はコンビニ注文に限らず全注文の受注編集で再現していた。
 *
 * 2. changePrice の bcmath 金額計算サンプルが実行時に動くことの確認
 *    OrderController::changePrice は bcmath (bcadd/bcsub/bcmul/bcdiv) で金額を計算する
 *    サンプル。bcmath 拡張が無い環境でも nanasess/bcmath-polyfill 経由で動作することを
 *    エンドポイント経由で確認する。
 */

/** ゲストでコンビニ決済の注文を 1 件作成する */
async function createConviniOrder(page: Page): Promise<void> {
  await page.goto('/');

  await page.getByRole('link', { name: '新入荷' }).click();
  await expect(page).toHaveURL('/products/list?category_id=2');
  await page.locator('li:has-text("チェリーアイスサンド ￥3,080 数量 カートに入れる")').getByRole('button', { name: 'カートに入れる' }).click();

  // EC-CUBE 4.4 ではカートボタンが AJAX リクエストを送るため完了を待ってから遷移
  await page.waitForLoadState('networkidle');
  await page.goto('/cart');

  await page.getByRole('link', { name: 'レジに進む' }).click();
  await expect(page).toHaveURL('/shopping/login');

  await page.getByRole('link', { name: 'ゲスト購入' }).click();
  await expect(page).toHaveURL('/shopping/nonmember');

  await page.getByPlaceholder('姓').fill('石');
  await page.getByPlaceholder('名', { exact: true }).fill('九部');
  await page.getByPlaceholder('セイ').fill('イーシー');
  await page.getByPlaceholder('メイ').fill('キューブ');
  await page.getByLabel('会社名').fill('イーシーキューブ');
  await page.getByPlaceholder('例：5300001').fill('5430001');
  await page.locator('select[name="nonmember\\[address\\]\\[pref\\]"]').selectOption('1');
  await page.getByPlaceholder('市区町村名(例：大阪市北区)').fill('aaa');
  await page.getByPlaceholder('番地・ビル名(例：西梅田1丁目6-8)').fill('1');
  await page.getByPlaceholder('例：11122223333').fill('0633334444');
  await page.getByPlaceholder('例：ec-cube@example.com').fill('user@example.com');
  await page.getByPlaceholder('確認のためもう一度入力してください').fill('user@example.com');

  await page.getByRole('button', { name: '次へ' }).click();
  await expect(page).toHaveURL('/shopping');

  await page.getByText('コンビニ決済').click();

  await page.getByRole('button', { name: '確認する' }).click();
  await expect(page).toHaveURL('/shopping/confirm');

  await page.getByRole('button', { name: '注文する' }).click();
  await expect(page).toHaveURL('/shopping/complete');
}

/** 管理画面にログインする */
async function adminLogin(page: Page): Promise<void> {
  await page.goto('/admin/login');
  await page.fill('input[name="login_id"]', 'admin');
  await page.fill('input[name="password"]', 'password');
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL('/admin/');
}

/** 受注一覧から最新の受注編集リンクと受注ID を取得する */
async function latestOrderEditHref(page: Page): Promise<{ href: string; id: string }> {
  await page.goto('/admin/order');
  const href = await page
    .locator('a[href*="/admin/order/"][href*="/edit"]')
    .first()
    .getAttribute('href');
  expect(href).toBeTruthy();
  const matched = (href as string).match(/\/admin\/order\/(\d+)\/edit/);
  expect(matched).toBeTruthy();
  return { href: href as string, id: (matched as RegExpMatchArray)[1] };
}

test('受注編集画面が constant() エラーなく描画され決済状況変更リンクが正しい', async ({ page }) => {
  await createConviniOrder(page);
  await adminLogin(page);

  const { href } = await latestOrderEditHref(page);
  const editResponse = await page.goto(href);

  // 修正前は Twig RuntimeError で HTTP 500 になっていた
  expect(editResponse?.status()).toBe(200);
  await expect(page.locator('body')).not.toContainText('is undefined');
  await expect(page.locator('body')).not.toContainText('RuntimeError');

  // constant() が解決され、決済状況変更リンクが正しい cvs_status を持つこと
  // CvsPaymentStatus::COMPLETE=3 / EXPIRED=5 / FAILURE=4
  await expect(page.locator('a', { hasText: '決済完了' })).toHaveAttribute('href', /cvs_status=3/);
  await expect(page.locator('a', { hasText: '期限切れ' })).toHaveAttribute('href', /cvs_status=5/);
  await expect(page.locator('a', { hasText: '決済失敗' })).toHaveAttribute('href', /cvs_status=4/);
});

test('決済金額変更(changePrice)が bcmath で計算され HTTP 200 を返す', async ({ page }) => {
  await createConviniOrder(page);
  await adminLogin(page);

  const { id } = await latestOrderEditHref(page);

  // 管理画面の AJAX は ECCUBE-CSRF-TOKEN ヘッダで CSRF を検証する
  const token = await page.getAttribute('meta[name="eccube-csrf-token"]', 'content');
  expect(token).toBeTruthy();

  // changePrice は決済総額から bcmath で手数料(+)・割引(-)を計算して返すサンプル。
  // bcmath 拡張が無くても nanasess/bcmath-polyfill 経由で計算できる (= 500 にならない)。
  const response = await page.request.post(`/admin/sample_payment/order/change_price/${id}`, {
    headers: {
      'ECCUBE-CSRF-TOKEN': token as string,
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  expect(response.status()).toBe(200);
  const body = await response.json();
  // bcmath で算出された請求額 (正の整数文字列) が返ること
  expect(String(body.price)).toMatch(/^\d+$/);
  expect(Number(body.price)).toBeGreaterThan(0);
});
