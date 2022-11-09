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
  await page.getByPlaceholder('セイ').fill('イシ');
  await page.getByPlaceholder('メイ').fill('キュウブ');
  await page.getByLabel('会社名').fill('イーシーキューブ');
  await page.getByPlaceholder('例：5300001').fill('5430033');
  await page.locator('select[name="nonmember\\[address\\]\\[pref\\]"]').selectOption('1');
  await page.getByPlaceholder('市区町村名(例：大阪市北区)').fill('aaa');
  await page.getByPlaceholder('番地・ビル名(例：西梅田1丁目6-8)').fill('bbb');
  await page.getByPlaceholder('例：11122223333').fill('09011113333');
  await page.getByPlaceholder('例：ec-cube@example.com').fill('user@example.com');
  await page.getByPlaceholder('確認のためもう一度入力してください').fill('user@example.com');
  await page.getByRole('button', { name: '次へ' }).click();
  await expect(page).toHaveURL('/shopping');

  await page.getByText('サンプル決済(トークン)').click();
  await expect(page).toHaveURL('/shopping');
  await page.locator('input[type="text"]').fill('4444111122221234');

  await page.getByRole('button', { name: '確認する' }).click();
  await expect(page).toHaveURL('/shopping/confirm');

  await expect(page.locator('#credit')).toHaveText(/-1234/);

  await page.getByRole('button', { name: '注文する' }).click();
  await expect(page).toHaveURL('/shopping/complete');

  await expect(page.locator('.ec-cartCompleteRole')).toHaveText(/トークン -> aaabbbccc123456/);

  await page.getByRole('link', { name: 'トップページへ' }).click();
  await expect(page).toHaveURL('/');
});
