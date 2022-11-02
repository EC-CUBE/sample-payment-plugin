import { test, expect } from '@playwright/test';

test('test', async ({ page }) => {

  await page.goto('/');

  await page.getByRole('link', { name: '新入荷' }).click();
  await expect(page).toHaveURL('/products/list?category_id=2');

  await page.locator('li:has-text("チェリーアイスサンド ￥3,080 数量 カートに入れる")').getByRole('button', { name: 'カートに入れる' }).click();

  await page.getByRole('link', { name: 'カートへ進む' }).click();
  await expect(page).toHaveURL('/cart');

  await page.getByRole('link', { name: 'レジに進む' }).click();
  await expect(page).toHaveURL('/shopping/login');

  await page.getByRole('link', { name: 'ゲスト購入' }).click();
  await expect(page).toHaveURL('/shopping/nonmember');

  await page.getByPlaceholder('姓').fill('石');

  await page.getByRole('textbox', { name: '名' }).fill('九部');

  await page.getByPlaceholder('セイ').fill('イーシー');

  await page.getByPlaceholder('メイ').fill('キューブ');

  await page.getByLabel('会社名').fill('イーシーキューブ');

  await page.getByPlaceholder('例：5300001').fill('5420001');

  await page.locator('select[name="nonmember\\[address\\]\\[pref\\]"]').selectOption('1');

  await page.getByPlaceholder('市区町村名(例：大阪市北区)').click();

  await page.getByPlaceholder('市区町村名(例：大阪市北区)').fill('aaa');

  await page.getByPlaceholder('番地・ビル名(例：西梅田1丁目6-8)').fill('bbb');

  await page.getByPlaceholder('例：11122223333').fill('0601234567');

  await page.getByPlaceholder('例：ec-cube@example.com').fill('user@example.com');

  await page.getByPlaceholder('確認のためもう一度入力してください').fill('user@example.com');

  await page.getByRole('button', { name: '次へ' }).click();
  await expect(page).toHaveURL('/shopping');

  await page.getByText('サンプル決済(リンク)').click();
  await expect(page).toHaveURL('/shopping');

  await page.getByRole('button', { name: '確認する' }).click();
  await expect(page).toHaveURL('/shopping/confirm');

  await page.getByRole('button', { name: '注文する' }).click();
  await expect(page).toHaveURL(/payment_company/);

  await page.getByRole('row', { name: 'カード番号' }).locator('input[type="text"]').fill('411111111111');

  await page.getByRole('row', { name: 'セキュリティコード' }).locator('input[type="text"]').fill('543');

  await page.locator('select').nth(1).selectOption('02');

  await page.locator('input[type="text"]').nth(2).fill('EC');

  await page.locator('input[type="text"]').nth(3).fill('CUBE');

  await page.getByRole('button', { name: '購入完了' }).click();
  await expect(page).toHaveURL('/shopping/complete');

  await page.getByRole('link', { name: 'トップページへ' }).click();
  await expect(page).toHaveURL('/');

});
