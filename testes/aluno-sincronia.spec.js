// 🔄 Conta sem personal em dois lugares (dois celulares, ou o app da tela inicial e o navegador, que guardam separado):
// o treino feito num não pode sumir quando o outro grava ou abre no dia seguinte. As duas cópias são juntadas.
const { test, expect, simp, criaConta, semJanelas, abreApp, EMU } = require('./apoio');

const entra = async (page, c) => {
  await abreApp(page, { emu: true });
  await page.click('[data-a="su-entrar"], [data-a="lg-self"]').catch(() => {});
  const en = page.locator('[data-a="su-entrar"]'); if (await en.count()) await en.first().click();
  await page.fill('#en-email', c.email); await page.fill('#en-senha', c.senha); await page.keyboard.press('Enter');
  await page.waitForFunction(() => window.__simp.ACCT && !document.getElementById('login'), null, { timeout: 20000 });
  await semJanelas(page);
};
const treino = (page, date, hi) => simp(page, ([date, hi]) => { const T = window.__simp, S = T.S, L = T.LETTERS[0], e = T.PLAN[L].ex[0];
  S.history.push({ date, t: L, min: 50, sec: 3000, hr: '19:00', hi, vol: 1000, sets: 3, prs: [], prd: [], rpe: 0, note: '', gym: '', ex: [{ id: e.id, n: e.nome, s: [[30, 10], [30, 10], [40, 10]], q: [], c: '' }] });
  S.loadHist[e.id] = (S.loadHist[e.id] || []).concat([{ d: date, kg: 40 }]); T.save(); }, [date, hi]);
const datas = page => simp(page, () => window.__simp.S.history.map(h => h.date + ' ' + h.hi).sort());

test('dois aparelhos: o treino de um aparece no outro e nenhum apaga o do outro ao gravar', async ({ browser }) => {
  const A = await (await browser.newContext()).newPage(), B = await (await browser.newContext()).newPage();
  const c = await criaConta(A, { emu: true, objetivo: 'hipertrofia' }); await semJanelas(A);
  await entra(B, c);
  // A treina; depois B (que ainda não tem esse treino) registra outro treino e grava
  await treino(A, '2026-11-20', '07:00'); await A.waitForTimeout(2500);
  await treino(B, '2026-11-21', '08:00'); await B.waitForTimeout(3000);
  // B juntou o treino de A ao gravar; A, ao voltar para o app, recebe o de B
  expect(await datas(B)).toEqual(['2026-11-20 07:00', '2026-11-21 08:00']);
  await A.evaluate(() => document.dispatchEvent(new Event('visibilitychange'))); await A.waitForTimeout(2500);
  expect(await datas(A)).toEqual(['2026-11-20 07:00', '2026-11-21 08:00']);
  // um terceiro aparelho que entra agora vê os dois
  const C = await (await browser.newContext()).newPage(); await entra(C, c);
  expect(await datas(C)).toEqual(['2026-11-20 07:00', '2026-11-21 08:00']);
});
