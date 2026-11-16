// 🏋️ Treino (aluno com personal de exemplo, senha 482913)
const { test, expect, simp, entraComSenha, semJanelas, arrasta, aba } = require('./apoio');

// começa o treino A pelo código (sem a janela da academia) e abre a aba Treino no visual pedido
async function comeca(page, layout = 'lista') {
  await simp(page, l => { const T = window.__simp; T.setLay(l); const t = T.LETTERS[0]; T.ui.t = t; T.startSession(t); T.ui.sheet = null; T.renderSheet(); T.ui.tab = 'treino'; T.render(); }, layout);
  await semJanelas(page);
  return simp(page, () => { const T = window.__simp, e = T.PLAN[T.ui.t].ex[0]; return { t: T.ui.t, id: e.id }; });
}
const log = (page, id) => simp(page, id => window.__simp.logOf(id).map(x => (x.ok ? '✓' : '·') + x.kg + 'x' + x.reps), id);

test.beforeEach(async ({ page }) => { await entraComSenha(page); });

test('começar treino arrastando, escolher a academia e cancelar sem registrar', async ({ page }) => {
  await aba(page, 'treino');
  const antes = await simp(page, () => window.__simp.S.history.length);
  await arrasta(page, '.slide:not([data-kind]) .slide-knob');
  await page.waitForSelector('#gym-in');
  await page.fill('#gym-in', 'Academia Robô'); await page.click('[data-a="gym-ok"]');
  const s = await simp(page, () => window.__simp.S.session);
  expect(s && s.gym).toBe('Academia Robô');
  await page.click('[data-a="ses-cancel"]'); await page.click('[data-a="ses-cancel"]');
  expect(await simp(page, () => [window.__simp.S.session, window.__simp.S.history.length])).toEqual([null, antes]);
});

test('marcar série: digitar o peso, arrastar "Série feita" e o descanso (+15 s e pular)', async ({ page }) => {
  const { id } = await comeca(page);
  const kg = page.locator('.tpanel .tsteps input').first();
  await kg.fill('50'); await kg.press('Tab');
  await arrasta(page, '.tpanel .slide[data-kind="tset"] .slide-knob');
  expect((await log(page, id))[0]).toMatch(/^✓50x/);
  const r1 = await simp(page, () => window.__simp.ui.rest && window.__simp.ui.rest.total);
  expect(r1).toBeGreaterThan(0);
  await page.click('[data-a="rest-add"]');
  expect(await simp(page, () => window.__simp.ui.rest.total)).toBe(r1 + 15);
  await page.click('[data-a="skiprest"]');
  expect(await simp(page, () => window.__simp.ui.rest)).toBeNull();
});

test('deslizar marca todas as séries; desmarcar volta uma', async ({ page }) => {
  const { id } = await comeca(page);
  const id2 = await simp(page, () => { const T = window.__simp; return T.PLAN[T.ui.t].ex[1].id; }); // exercício fechado na lista
  await page.locator(`[data-a="lall"][data-ex="${id2}"]`).evaluate(b => b.click());
  expect((await log(page, id2)).every(x => x.startsWith('✓'))).toBe(true);
  await simp(page, id => window.__simp.markAll(id), id);
  await simp(page, id => { window.__simp.ui.lopen = id; window.__simp.ui.tsel = { id, i: 0 }; window.__simp.render(); }, id);
  await page.click('[data-a="tun"]');
  expect((await log(page, id))[0].startsWith('·')).toBe(true);
});

for (const lay of ['lista', 'foco', 'folha', 'grade']) {
  test(`visual do treino "${lay}" abre e mostra o exercício`, async ({ page }) => {
    await comeca(page, lay);
    const nome = await simp(page, () => { const T = window.__simp; return T.PLAN[T.ui.t].ex[0].nome; });
    await expect(page.locator('#app')).toContainText(nome);
  });
}

test('séries do dia seguem o plano editado (o peso não some e a última série não trava)', async ({ page }) => {
  const { t, id } = await comeca(page);
  await simp(page, ({ t, id }) => { const T = window.__simp, e = T.PLAN[t].ex[0]; e.s = 3; T.getLog(id).forEach(x => { x.ok = true; x.kg = '10'; x.reps = '10'; }); e.s = 4; T.ui.lopen = id; T.ui.tsel = null; T.render(); }, { t, id });
  expect(await log(page, id)).toEqual(['✓10x10', '✓10x10', '✓10x10', '·10x']);
  const kg = page.locator('.tpanel .tsteps input').first(); await kg.fill('12'); await kg.press('Tab');
  await arrasta(page, '.tpanel .slide[data-kind="tset"] .slide-knob');
  expect((await log(page, id))[3]).toMatch(/^✓12x/);
  // plano diminui: as séries não feitas que sobram somem
  const e2 = await simp(page, t => { const T = window.__simp, e = T.PLAN[t].ex[1]; e.s = 5; const L = T.getLog(e.id); [0, 1, 2].forEach(i => { L[i].ok = true; }); e.s = 3; return { n: T.exN(e), L: T.logOf(e.id).length }; }, t);
  expect(e2).toEqual({ n: 3, L: 3 });
});

test('finalizar salva o treino e abre a tela de treino concluído', async ({ page }) => {
  const { id } = await comeca(page);
  const antes = await simp(page, () => window.__simp.S.history.length);
  await simp(page, id => window.__simp.markAll(id), id);
  await page.click('[data-a="finish"]'); await page.click('[data-a="finish"]');
  await page.waitForFunction(() => window.__simp.ui.sheet && window.__simp.ui.sheet.k === 'stpick');
  await expect(page.locator('#sheet')).toContainText('Treino concluído');
  const h = await simp(page, () => { const H = window.__simp.S.history; return { n: H.length, ult: H[H.length - 1] }; });
  expect(h.n).toBe(antes + 1); expect(h.ult.sets).toBeGreaterThan(0); expect(h.ult.hi).toMatch(/^\d\d:\d\d$/);
});

test('treino que passa da meia-noite fica no dia em que começou', async ({ page }) => {
  const { t, id } = await comeca(page);
  const r = await simp(page, ({ t, id }) => {
    const T = window.__simp, S = T.S, ontem = T.daysAgo(1);
    S.session.d = ontem; S.session.ini = Date.now() - 40 * 60e3; S.session.start = S.session.ini;
    T.getLog(id).forEach(x => { x.ok = true; x.kg = '50'; x.reps = '10'; }); T.save(); T.tidy();
    return { ontem, feitas: T.logOf(id).filter(x => x.ok).length };
  }, { t, id });
  expect(r.feitas).toBeGreaterThan(0);
  await page.click('[data-a="finish"]'); await page.click('[data-a="finish"]');
  const h = await simp(page, () => { const H = window.__simp.S.history; return H[H.length - 1]; });
  expect(h.date).toBe(r.ontem); expect(h.min).toBe(40);
});

test('treino esquecido aberto (3 h): janela ao abrir, ajusta o tempo e salva', async ({ page }) => {
  const { id } = await comeca(page);
  await simp(page, id => { const T = window.__simp, S = T.S; T.getLog(id).forEach(x => { x.ok = true; x.kg = '30'; x.reps = '12'; }); T.save();
    S.session.ini = Date.now() - 190 * 60e3; S.session.start = S.session.ini; S.session.ult = Date.now() - 120 * 60e3; T.render(); T.esqAsk(); }, id);
  await page.waitForSelector('#sheet .esq');
  await expect(page.locator('#sheet')).toContainText('Seu treino ainda está aberto');
  const sug = await simp(page, () => window.__simp.ui.sheet.min); expect(sug).toBe(75);
  await page.click('[data-a="esq-m"][data-d="5"]');
  await page.click('[data-a="esq-ok"]');
  const h = await simp(page, () => { const H = window.__simp.S.history; return [H[H.length - 1].min, !!window.__simp.S.session]; });
  expect(h).toEqual([80, false]);
});

test('Finalizar com 3 h ou mais pede para conferir o tempo', async ({ page }) => {
  await comeca(page);
  await simp(page, () => { const S = window.__simp.S; S.session.ini = Date.now() - 200 * 60e3; S.session.start = S.session.ini; S.session.esqOk = Date.now(); });
  await page.click('[data-a="finish"]');
  await expect(page.locator('#sheet')).toContainText('Confira o tempo do treino');
  await page.click('#sheet .esq [data-a="esq-x"]');
  expect(await simp(page, () => [window.__simp.ui.sheet, !!window.__simp.S.session])).toEqual([null, true]);
});

test('aquecimento em % da carga aparece antes da 1ª série', async ({ page }) => {
  const { t } = await comeca(page);
  await simp(page, t => { const T = window.__simp, e = T.PLAN[t].ex[0]; e.aq = true; e.aqW = [{ p: 40, r: 10 }, { p: 60, r: 6 }]; T.ui.lopen = e.id; T.render(); }, t);
  // com a carga do dia conhecida, o app já mostra o peso de cada série (40% e 60% da carga)
  await expect(page.locator('.twarm')).toContainText('Aquecimento');
  await expect(page.locator('.twarm')).toContainText('× 10');
  await expect(page.locator('.twarm')).toContainText('× 6');
});

test('"Como foi?" guarda o comentário do exercício', async ({ page }) => {
  const { id } = await comeca(page);
  await page.locator(`.tpanel [data-a="exq-open"][data-ex="${id}"]`).click();
  await page.locator('#sheet [data-a="exq"]').first().click();
  await page.click('#sheet [data-a="exq-done"]');
  await expect(page.locator(`.tpanel [data-a="exq-open"][data-ex="${id}"]`)).toContainText('Comentado');
});
