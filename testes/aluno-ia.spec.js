// 🤖 Treino pela IA: quem marcou "Estou começando" recebe 3 séries de 10–12 e no máximo 7 exercícios por treino
const { test, expect, simp, abreApp } = require('./apoio');

test('IA para iniciante: 3 séries de 10–12 e no máximo 7 exercícios, mesmo com 75+ min', async ({ page }) => {
  await abreApp(page);
  await page.click('[data-a="lg-self"]');
  await page.fill('#su-nome', 'Bia Começo'); await page.fill('#su-email', 'bia' + Date.now() + '@teste.com'); await page.fill('#su-senha', '135790');
  await page.click('[data-a="su-ck"][data-v="all"]');
  await page.click('.su-go');
  await page.waitForSelector('[data-a="su-sexo"]');
  await page.click('[data-a="su-ed"][data-v="hipertrofia"]'); await page.fill('#su-peso', '70');
  await page.click('[data-a="su-next"]');
  await page.click('[data-a="su-mode"][data-v="ia"]');
  await page.click('[data-a="iq-nivel"][data-v="ini"]'); await page.click('[data-a="iq-local"][data-v="a"]'); await page.click('[data-a="su-next"]');
  await page.click('[data-a="iq-n"][data-v="3"]'); await page.click('[data-a="iq-tempo"][data-v="75"]'); await page.click('[data-a="su-next"]');
  await page.click('[data-a="su-next"]');
  await page.waitForSelector('[data-a="su-done"]');
  await page.click('[data-a="su-done"]');
  await page.waitForFunction(() => window.__simp.ACCT && !document.getElementById('login'), null, { timeout: 20000 });
  const P = await simp(page, () => { const T = window.__simp; return T.LETTERS.map(t => T.PLAN[t].ex.map(e => [e.s, e.r])); });
  expect(P.length).toBe(3);
  for (const ex of P) {
    expect(ex.length).toBeLessThanOrEqual(7);
    for (const [s, r] of ex) { expect(s).toBe(3); expect(r).toMatch(/^10–12|\d\s*s\b/); }
  }
});
