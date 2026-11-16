const { test, expect, entraComSenha, simp } = require('./apoio');
test('app abre e entra com a senha do exemplo', async ({ page }) => {
  await entraComSenha(page);
  expect(await simp(page, () => window.__simp.ACCT.mode)).toBe('personal');
  await expect(page.locator('.tabbar')).toBeVisible();
});
