// 🔁 Trocar exercício: só quem treina sem personal troca um exercício por um parecido (ex.: sem halter para a elevação lateral)
const { test, expect, simp, criaConta, entraComSenha, semJanelas } = require('./apoio');

const abreEx = (page, id) => simp(page, id => { const T = window.__simp; T.setLay('lista'); T.ui.tab = 'treino'; T.render(); T.ui.sheet = { k: 'ex', id }; T.ui.pane = 'exec'; T.renderSheet(true); }, id);

test('sem personal: troca por um parecido, a troca fica no treino e dá para voltar ao de antes', async ({ page }) => {
  await criaConta(page, { objetivo: 'hipertrofia' }); await semJanelas(page);
  const { t, id, nome } = await simp(page, () => { const T = window.__simp, t = T.LETTERS[0], e = T.PLAN[t].ex[0]; return { t, id: e.id, nome: e.nome }; });
  await abreEx(page, id);
  await page.click('[data-a="troca-open"]');
  await expect(page.locator('#sh-t')).toContainText('Trocar exercício');
  await expect(page.locator('.sheet')).toContainText('Mais parecidos');
  const novo = await page.locator('[data-a="troca"]').first().getAttribute('data-v');
  await page.locator('[data-a="troca"]').first().click();
  // a ficha do exercício novo abre, e o treino guarda a troca no mesmo lugar, com as mesmas séries
  await expect(page.locator('#sh-t')).not.toHaveText(nome);
  const p = await simp(page, t => window.__simp.PLAN[t].ex.map(e => [e.id, e.orig || '']), t);
  expect(p[0]).toEqual([novo, id]);
  expect(['meu', 'ia']).toContain(await simp(page, () => window.__simp.S.conta.plan)); // treino da pessoa (não volta ao modelo pronto)
  // volta para o de antes
  await page.click('[data-a="troca-open"]');
  await expect(page.locator('.sheet')).toContainText('Voltar para o exercício de antes');
  await page.locator(`[data-a="troca"][data-v="${id}"]`).first().click();
  expect(await simp(page, t => window.__simp.PLAN[t].ex[0].id, t)).toBe(id);
  expect(await simp(page, t => window.__simp.PLAN[t].ex[0].orig, t)).toBeUndefined();
});

test('sem personal: elevação lateral sugere primeiro outras elevações laterais e o desenvolvimento', async ({ page }) => {
  await criaConta(page, { objetivo: 'hipertrofia' }); await semJanelas(page);
  // coloca a elevação lateral no treino A, como se a IA tivesse escolhido
  const id = await simp(page, () => { const T = window.__simp; T.mtOpen(); T.closeSheet(); const c = T.S.conta, l = Object.keys(c.treinos).sort()[0];
    c.treinos[l].ex = c.treinos[l].ex.filter(e => e.id !== 'lateral'); c.treinos[l].ex.unshift({ id: 'lateral', nome: 'Elevação lateral', alvo: 'Ombro', s: 3, r: '12–15', d: 60, cues: [], erro: '' }); T.applyAccount(); return 'lateral'; });
  await abreEx(page, id);
  await page.click('[data-a="troca-open"]');
  const top = await page.locator('h3:has-text("Mais parecidos") + .list b').allTextContents();
  expect(top.length).toBeGreaterThan(1);
  expect(top.slice(0, 3).every(n => /lateral/i.test(n))).toBe(true);
  expect(top.some(n => /Desenvolvimento/.test(n))).toBe(true); // o desenvolvimento aparece entre os mais parecidos
});

test('com personal: não aparece "Trocar exercício" (o treino é do personal)', async ({ page }) => {
  await entraComSenha(page);
  const id = await simp(page, () => { const T = window.__simp; return T.PLAN[T.LETTERS[0]].ex[0].id; });
  await abreEx(page, id);
  await expect(page.locator('.presc')).toBeVisible();
  await expect(page.locator('[data-a="troca-open"]')).toHaveCount(0);
});
