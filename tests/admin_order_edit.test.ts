import { test, expect } from '@playwright/test';

/**
 * リグレッションテスト: 受注編集画面の Twig RuntimeError
 *
 * 受注編集画面へ自動注入される order_edit.twig (`@admin/Order/edit.twig` にフック) が、
 * version-less の名前空間 `Plugin\SamplePayment\Entity\CvsPaymentStatus` を
 * `constant()` で参照していたため、以下の RuntimeError (HTTP 500) になっていた:
 *
 *   Constant "Plugin\SamplePayment\Entity\CvsPaymentStatus::COMPLETE" is undefined
 *
 * 正しい名前空間 `Plugin\SamplePayment44` へ修正したことの回帰防止。
 * order_edit.twig の `constant()` は `<div class="d-none">` 内で決済種別に関わらず
 * 常に評価されるため、修正前はコンビニ注文に限らず全注文の受注編集で再現していた。
 * 本テストでは確実性のためコンビニ決済の注文を作成して検証する。
 */

test('受注編集画面が constant() エラーなく描画され決済状況変更リンクが正しい', async ({ page }) => {
  // --- 1. ゲストでコンビニ決済の注文を作成 ---
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

  // --- 2. 管理画面ログイン ---
  await page.goto('/admin/login');
  await page.fill('input[name="login_id"]', 'admin');
  await page.fill('input[name="password"]', 'password');
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL('/admin/');

  // --- 3. 受注一覧から最新 (作成したコンビニ注文) の受注編集を開く ---
  await page.goto('/admin/order');
  const editHref = await page
    .locator('a[href*="/admin/order/"][href*="/edit"]')
    .first()
    .getAttribute('href');
  expect(editHref).toBeTruthy();

  const editResponse = await page.goto(editHref as string);

  // --- 4. 修正前は Twig RuntimeError で HTTP 500 になっていた ---
  expect(editResponse?.status()).toBe(200);
  await expect(page.locator('body')).not.toContainText('is undefined');
  await expect(page.locator('body')).not.toContainText('RuntimeError');

  // --- 5. constant() が解決され、決済状況変更リンクが正しい cvs_status を持つこと ---
  //        CvsPaymentStatus::COMPLETE=3 / EXPIRED=5 / FAILURE=4
  await expect(page.locator('a', { hasText: '決済完了' })).toHaveAttribute('href', /cvs_status=3/);
  await expect(page.locator('a', { hasText: '期限切れ' })).toHaveAttribute('href', /cvs_status=5/);
  await expect(page.locator('a', { hasText: '決済失敗' })).toHaveAttribute('href', /cvs_status=4/);
});
