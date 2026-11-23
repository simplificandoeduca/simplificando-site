// ⚡ Técnicas (rest-pause, myo-reps, drop-set e bi-set): quem treina sem personal escolhe a técnica e as séries em "Meus treinos";
// na ficha do exercício aparecem a explicação e as linhas extras logo abaixo das séries escolhidas, que entram no volume do treino.
const { test, expect, simp, criaConta, semJanelas } = require('./apoio');

test('sem personal: rest-pause na 2ª série, anota a pausa e o volume do treino conta as repetições extras', async ({ page }) => {
  await criaConta(page, { objetivo: 'hipertrofia' }); await semJanelas(page);
  // abre "Meus treinos" no treino A
  await simp(page, () => { const T = window.__simp; T.mtOpen(); const l = Object.keys(T.S.conta.treinos).sort()[0]; T.ui.sheet.ed = l; T.renderSheet(true); });
  await page.click('[data-a="mt-tec"][data-i="0"][data-v="rp"]');
  // vem marcada a última série: liga a 2ª e tira a última
  const ult = await simp(page, () => { const T = window.__simp, l = Object.keys(T.S.conta.treinos).sort()[0]; return T.S.conta.treinos[l].ex[0].s; });
  await page.click('[data-a="mt-tecs"][data-i="0"][data-v="2"]');
  await page.click(`[data-a="mt-tecs"][data-i="0"][data-v="${ult}"]`);
  const { id, tec } = await simp(page, () => { const T = window.__simp, l = Object.keys(T.S.conta.treinos).sort()[0], e = T.S.conta.treinos[l].ex[0]; return { id: e.id, tec: e.tec }; });
  expect(tec).toEqual({ k: 'rp', s: [2] });
  // bi-set: o 2º junta com o 3º
  await page.click('[data-a="mt-tec"][data-i="1"][data-v="bi"]');
  expect(await simp(page, () => { const T = window.__simp, l = Object.keys(T.S.conta.treinos).sort()[0], L = T.S.conta.treinos[l].ex; return L[1].sup === L[2].id; })).toBe(true);
  // ficha do exercício: explicação e o botão da pausa só na 2ª série
  await simp(page, id => { const T = window.__simp; T.closeSheet(); T.setLay('lista'); T.ui.tab = 'treino'; T.render(); T.startSession(Object.keys(T.S.conta.treinos).sort()[0]); T.ui.sheet = { k: 'ex', id }; T.ui.pane = 'series'; T.renderSheet(true); }, id);
  await expect(page.locator('.warn.tec')).toContainText('Rest-pause na 2ª série');
  await expect(page.locator('[data-a="xs-add"]')).toHaveCount(1);
  await expect(page.locator('[data-a="xs-add"]')).toHaveAttribute('data-i', '1');
  await page.click('[data-a="xs-add"]');
  await page.fill('[data-xs="reps"][data-i="1"][data-k="0"]', '4');
  await page.fill('[data-xs="kg"][data-i="1"][data-k="0"]', '20');
  // marca as séries com 20 kg × 10 e finaliza
  await simp(page, id => { const T = window.__simp, L = T.getLog(id); L.forEach(x => { x.kg = '20'; x.reps = '10'; x.ok = true; }); T.save(); T.finish(); }, id);
  const h = await simp(page, id => { const H = window.__simp.S.history.at(-1); const x = H.ex.find(y => y.id === id); return { vol: H.vol, tk: x.tk, xs: x.xs, n: x.s.length }; }, id);
  expect(h.tk).toBe('rp');
  expect(h.xs[1]).toEqual([[20, 4]]);
  expect(h.vol).toBe(h.n * 200 + 80);
});
