// ✏️ Treino com exercícios sem registro (ex.: o celular desligou no meio do treino):
// 1) a janela do treino aberto mostra o que faltou e leva até o exercício; 2) o resumo tem "Editar séries" para corrigir depois de salvo.
const { test, expect, simp, criaConta, semJanelas } = require('./apoio');

// treino aberto há 3h20, com séries marcadas em todos os exercícios menos nos 2 últimos
const abreTreino = page => simp(page, () => {
  const T = window.__simp, S = T.S, t0 = T.today(), L = T.LETTERS[0], ex = T.PLAN[L].ex; T.closeSheet();
  S.logs[t0] = {}; ex.slice(0, ex.length - 2).forEach(e => { S.logs[t0][e.id] = Array.from({ length: e.s }, () => ({ kg: '20', reps: '10', ok: true, rir: 1 })); });
  const ini = Date.now() - 3.3 * 3600e3; S.session = { t: L, d: t0, start: ini, acc: 0, run: true, ini, ult: ini + 50 * 60e3, nOk: ex.slice(0, ex.length - 2).reduce((n, e) => n + e.s, 0), gym: '' };
  T.save(); T.ui.tab = 'hoje'; T.render(); T.esqAsk();
  return ex.slice(-2).map(e => e.id);
});

test('treino aberto: a janela mostra os exercícios sem registro e "Registrar o que faltou" abre o primeiro, guardando o tempo', async ({ page }) => {
  await criaConta(page, { objetivo: 'hipertrofia' }); await semJanelas(page);
  const faltam = await abreTreino(page);
  await expect(page.locator('.esq-falta')).toContainText('Faltam 2 exercícios sem registro');
  await page.click('[data-a="esq-falta"]');
  expect(await simp(page, () => [window.__simp.ui.sheet && window.__simp.ui.sheet.k, window.__simp.ui.sheet && window.__simp.ui.sheet.id, window.__simp.S.session.fix])).toEqual(['ex', faltam[0], 50 * 60e3 + 5 * 60e3]);
});

test('treino salvo com exercícios sem registro: "Editar séries" no resumo completa as cargas e refaz séries, volume e evolução', async ({ page }) => {
  await criaConta(page, { objetivo: 'hipertrofia' }); await semJanelas(page);
  const faltam = await abreTreino(page);
  await page.click('[data-a="esq-ok"]'); // salva assim, sem os 2 últimos
  await expect(page.locator('[data-a="hed-open"]')).toContainText('2 exercícios sem registro');
  const antes = await simp(page, () => { const h = window.__simp.S.history.at(-1); return { sets: h.sets, vol: h.vol }; });
  await page.click('[data-a="hed-open"]');
  await expect(page.locator('#sh-t')).toContainText('Editar séries');
  await expect(page.locator('.hed-c.falta')).toHaveCount(2);
  for (const inp of await page.locator('.hed-c.falta input[data-hed="0"]').all()) await inp.fill('30');
  for (const inp of await page.locator('.hed-c.falta input[data-hed="1"]').all()) await inp.fill('10');
  await page.click('[data-a="hed-save"]');
  await expect(page.locator('#sh-t')).toContainText('Treino concluído');
  await expect(page.locator('[data-a="hed-open"]')).not.toContainText('sem registro');
  const depois = await simp(page, ids => { const S = window.__simp.S, h = S.history.at(-1); return { sets: h.sets, vol: h.vol, hist: ids.map(id => (S.loadHist[id] || []).at(-1)?.kg), ex: ids.map(id => (h.ex.find(x => x.id === id) || { s: [] }).s.length) }; }, faltam);
  expect(depois.sets).toBeGreaterThan(antes.sets);
  expect(depois.vol).toBe(antes.vol + depois.ex.reduce((t, n) => t + n * 300, 0));
  expect(depois.hist).toEqual([30, 30]);
  expect(depois.ex.every(n => n > 0)).toBe(true);
});
