// 📸 Stories do fim do treino (versão nova, por enquanto só no app de teste e aqui no robô):
// Corpo sempre (no lugar do Cronômetro), Recordes só com recorde no treino e Sequência só com 3 semanas ou mais na meta.
const { test, expect, simp, criaConta, semJanelas } = require('./apoio');

// histórico de exemplo: 4 treinos por semana nas últimas semanas; o de hoje com ou sem recorde
const semeia = (page, { recorde, semanas }) => simp(page, ({ recorde, semanas }) => {
  const T = window.__simp, S = T.S, P = T.PLAN, L = T.LETTERS, t0 = T.today();
  S.history = []; S.cardio = []; S.water = {};
  for (let k = semanas * 7; k >= 0; k--) {
    const d = T.addDays(t0, -k), wd = (new Date(d + 'T12:00').getDay() + 6) % 7;
    if (k && ![0, 1, 2, 3].includes(wd)) continue;
    const t = L[S.history.length % L.length], ex = P[t].ex.map((e, j) => ({ id: e.id, n: e.nome, s: [[20 + j * 5, 10], [25 + j * 5, 8]] }));
    S.history.push({ t, date: d, min: 52, sec: 52 * 60, sets: ex.length * 2, vol: 5000, ex, prs: k === 0 && recorde ? [ex[0].id] : [] });
  }
  S.cardio.push({ date: t0, local: 'Esteira', min: 30, int: 'Moderada' }); S.water[t0] = 9999;
  T.save(); T.closeSheet(); T.ui.sheet = { k: 'sum', i: S.history.length - 1 }; T.renderSheet(true);
}, { recorde, semanas });
const modelos = page => page.locator('[data-a="sum-st"]').evaluateAll(bs => bs.map(b => b.dataset.v));

test('com recorde e 3+ semanas na meta: Corpo, Recordes, Sequência, Cupom e Foto (sem o Cronômetro)', async ({ page }) => {
  const erros = []; page.on('pageerror', e => erros.push(e.message));
  await criaConta(page, { objetivo: 'hipertrofia' }); await semJanelas(page);
  await semeia(page, { recorde: true, semanas: 4 });
  expect(await modelos(page)).toEqual(['mapa', 'recorde', 'seq', 'cupom', 'foto']);
  for (const m of ['mapa', 'recorde', 'seq']) {
    await simp(page, () => { const T = window.__simp; T.ui.sheet = { k: 'sum', i: T.S.history.length - 1 }; T.renderSheet(true); });
    await page.click(`[data-a="sum-st"][data-v="${m}"]`);
    await expect(page.locator('#st-cv.on')).toBeVisible();
    await expect(page.locator(`.stm-c.on[data-v="${m}"]`)).toBeVisible();
    // Corpo e Recordes: só Preto ou Branco; Sequência tem cor fixa
    await expect(page.locator('[data-a="st-tom"]')).toHaveCount(m === 'seq' ? 0 : 2);
    await expect(page.locator('[data-a="st-cor"]')).toHaveCount(0);
  }
  for (const m of ['mapa', 'recorde']) {
    await simp(page, () => { const T = window.__simp; T.ui.sheet = { k: 'sum', i: T.S.history.length - 1 }; T.renderSheet(true); });
    await page.click(`[data-a="sum-st"][data-v="${m}"]`);
    await page.click('[data-a="st-tom"][data-v="branco"]');
    await expect(page.locator('[data-a="st-tom"][data-v="branco"]')).toHaveAttribute('aria-pressed', 'true');
    expect(await simp(page, () => window.__simp.PREF.stTom)).toBe('branco');
  }
  expect(erros).toEqual([]);
});

test('sem recorde e sem sequência: só Corpo, Cupom e Foto', async ({ page }) => {
  await criaConta(page, { objetivo: 'hipertrofia' }); await semJanelas(page);
  await semeia(page, { recorde: false, semanas: 0 });
  expect(await modelos(page)).toEqual(['mapa', 'cupom', 'foto']);
});
