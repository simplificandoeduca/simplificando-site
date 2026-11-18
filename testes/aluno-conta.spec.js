// 🧑 Aluno sem personal: conta e primeiro acesso
const { test, expect, LOCAL, simp, abreApp, criaConta, semJanelas, aba } = require('./apoio');

test('cria conta e a IA monta o treino', async ({ page }) => {
  await criaConta(page, { nome: 'Joana Lima', objetivo: 'emagrecer' });
  const r = await simp(page, () => { const T = window.__simp; return { modo: T.ACCT.mode, plano: T.S.conta.plan, treinos: T.LETTERS.length, nome: T.S.nome, aceite: !!T.S.aceite }; });
  expect(r.modo).toBe('self'); expect(r.plano).toBe('ia'); expect(r.treinos).toBeGreaterThanOrEqual(2);
  expect(r.nome).toBe('Joana Lima'); expect(r.aceite).toBe(true);
});

test('conta nova: um aceite só (termos, privacidade, dados de saúde e treino automático); sem ele não cadastra', async ({ page }) => {
  await abreApp(page);
  await page.click('[data-a="lg-self"]');
  await page.fill('#su-nome', 'Ana'); await page.fill('#su-email', 'ana@teste.com'); await page.fill('#su-senha', '135790');
  await expect(page.locator('[data-a="su-ck"]')).toHaveCount(1);
  await expect(page.locator('[data-a="su-ck"]')).toContainText('dados de saúde');
  await expect(page.locator('.su-go')).toBeDisabled();
  await page.click('[data-a="su-ck"][data-v="all"]');
  await expect(page.locator('.su-go')).toBeEnabled();
  await page.click('[data-a="su-ck"][data-v="all"]');
  await expect(page.locator('.su-go')).toBeDisabled();
});

test('"Montar o meu treino": depois do Baixe nosso app, abre o editor de treinos', async ({ page }) => {
  await criaConta(page, { modo: 'meu' });
  await page.waitForFunction(() => window.__simp.ui.sheet && window.__simp.ui.sheet.k === 'baixar', null, { timeout: 15000 });
  await page.click('#sheet [data-a="bx-x"] >> text=Agora não');
  await page.waitForFunction(() => window.__simp.ui.sheet && window.__simp.ui.sheet.k === 'meus', null, { timeout: 10000 });
});

test('sair e entrar de novo com e-mail e senha', async ({ page }) => {
  const c = await criaConta(page);
  await semJanelas(page);
  await simp(page, () => { const T = window.__simp; T.ui.sheet = { k: 'prof' }; T.renderSheet(); });
  await page.click('#sheet [data-a="logout"]'); await page.click('#sheet [data-a="logout"]');
  await page.waitForSelector('#login');
  await page.click('[data-a="su-entrar"], [data-a="lg-self"]').catch(() => {});
  const entrar = page.locator('[data-a="su-entrar"]'); if (await entrar.count()) await entrar.first().click();
  await page.fill('#en-email', c.email); await page.fill('#en-senha', c.senha);
  await page.keyboard.press('Enter');
  await page.waitForFunction(() => window.__simp.ACCT && !document.getElementById('login'), null, { timeout: 15000 });
  expect(await simp(page, () => window.__simp.S.nome)).toBe('Joana Lima');
});

test('quem usava sem conta: o perfil do aparelho vira conta com os treinos', async ({ page }) => {
  await abreApp(page);
  await simp(page, () => {
    const H = []; for (let i = 1; i <= 6; i++) H.push({ date: new Date(Date.now() - i * 2 * 864e5).toISOString().slice(0, 10), t: 'A', min: 50, vol: 3000, sets: 12, prs: [], rpe: 0, note: '' });
    localStorage.setItem('simplificando-pro-v2:self', JSON.stringify({ v: 2, nome: 'Carla', ed: 'hipertrofia', waterGoal: 2500, cardioGoal: 120, water: {}, wlog: {}, remind: { on: true, every: 90 }, history: H, cardio: [], logs: {}, warm: {}, pr: {}, last: {}, loadHist: {}, checkin: {}, conta: { sexo: 'F', plan: 'abc', dias: [0, 2, 4] } }));
  });
  await page.reload(); await page.waitForFunction(() => !!window.__simp);
  await page.click('[data-a="lg-self"]');
  await expect(page.locator('.lg-leg')).toContainText('vão junto');
  await page.fill('#su-nome', 'Carla Souza'); await page.fill('#su-email', 'carla' + Date.now() + '@teste.com'); await page.fill('#su-senha', '135790');
  await page.click('[data-a="su-ck"][data-v="all"]');
  await page.click('.su-go');
  await page.waitForFunction(() => window.__simp.ACCT && window.__simp.ACCT.uid && !document.getElementById('login'), null, { timeout: 15000 });
  const r = await simp(page, () => ({ treinos: window.__simp.S.history.length, nome: window.__simp.S.nome, antigo: !!localStorage.getItem('simplificando-pro-v2:self') }));
  expect(r).toEqual({ treinos: 6, nome: 'Carla Souza', antigo: false });
});

test('primeiro acesso: uma janela por vez, na ordem certa (Firebase de teste)', async ({ page }) => {
  await criaConta(page, { emu: true });
  // 1º: Baixe nosso app (nada de compartilhar nem de escolher visual ainda)
  await page.waitForFunction(() => window.__simp.ui.sheet && window.__simp.ui.sheet.k === 'baixar', null, { timeout: 15000 });
  expect(await page.locator('#pick').count()).toBe(0);
  await page.click('#sheet [data-a="bx-x"] >> text=Agora não');
  // 2º: Quer compartilhar? (o treino já está montado pela IA)
  await page.waitForFunction(() => window.__simp.ui.sheet && window.__simp.ui.sheet.k === 'acomp', null, { timeout: 10000 });
  await page.click('#sheet [data-a="acomp-sim"]');
  await page.waitForTimeout(800);
  expect(await simp(page, () => window.__simp.ui.sheet)).toBeNull();
  // 3º: na 1ª vez na aba Treino, escolhe o visual do treino (4 modelos) e depois o do cardio (3 modelos)
  await aba(page, 'treino');
  await page.waitForSelector('#pick [data-a="tlay"]');
  expect(await page.locator('#pick [data-a="tlay"]').count()).toBe(4);
  await page.locator('#pick [data-a="tlay"]').first().click(); await page.click('[data-a="tlay-go"]');
  await page.waitForSelector('#pick [data-a="clay"]');
  expect(await page.locator('#pick [data-a="clay"]').count()).toBe(3);
  await page.locator('#pick [data-a="clay"]').first().click(); await page.click('[data-a="clay-go"]');
  await expect(page.locator('#pick')).toHaveCount(0);
  const p = await simp(page, () => [window.__simp.PREF.treinoLayout, window.__simp.PREF.cardioLayout, window.__simp.S.acomp]);
  expect(p[0]).toBeTruthy(); expect(p[1]).toBeTruthy(); expect(p[2]).toBeTruthy();
});

test('app de teste (/app-teste): entra direto com o aluno de exemplo, sem senha e sem o Firebase, com os dados separados do app de verdade', async ({ page }) => {
  const reqs = []; page.on('request', r => reqs.push(r.url()));
  await page.goto(LOCAL + '/app-teste/');
  await page.waitForFunction(() => window.__simp && window.__simp.ACCT && window.__simp.ACCT.code === '482913', null, { timeout: 15000 });
  await expect(page.locator('#login')).toHaveCount(0);
  await expect(page.locator('#app')).toBeVisible();
  const ks = await page.evaluate(() => Object.keys(localStorage));
  expect(ks.some(k => k.startsWith('simpteste-aluno-'))).toBe(true);
  expect(ks.filter(k => /^simplificando-|^simp-app-v$/.test(k))).toEqual([]);
  expect(await page.evaluate(() => window.SIMPLIFICANDO_FIREBASE)).toBeNull();
  expect(reqs.filter(u => /firebase|\.php/.test(u))).toEqual([]);
  // sem sessão guardada (ex.: depois de sair), abrir de novo entra direto outra vez
  await page.evaluate(() => { localStorage.removeItem('simpteste-aluno-sessao'); });
  await page.reload();
  await page.waitForFunction(() => window.__simp && window.__simp.ACCT && window.__simp.ACCT.code === '482913', null, { timeout: 15000 });
});

test('Seu perfil: 4 blocos abrem as telas de dentro e voltam; sem personal, "Meus treinos" edita nome, foco e metas', async ({ page }) => {
  await criaConta(page, { nome: 'Paula Perfil' });
  await semJanelas(page);
  await simp(page, () => { const T = window.__simp; T.ui.sheet = { k: 'prof' }; T.renderSheet(); });
  await expect(page.locator('#sheet .pf-tile')).toHaveCount(4);
  await expect(page.locator('#sheet .pf-id')).toContainText('Paula');
  for (const [v, t] of [['plano', 'Meus treinos'], ['avisos', 'Avisos'], ['visual', 'Visual'], ['sug', 'Sugestão'], ['conta', 'Conta']]) {
    await page.click(`#sheet [data-a="pf-go"][data-v="${v}"]`);
    await expect(page.locator('#sheet .pf-h')).toHaveText(t);
    if (v === 'plano') {
      await expect(page.locator('#sheet #prof-nome')).toHaveValue('Paula Perfil');
      const antes = await simp(page, () => window.__simp.S.waterGoal);
      await page.click('#sheet [data-a="goal"][data-d="250"]');
      expect(await simp(page, () => window.__simp.S.waterGoal)).toBe(antes + 250);
      await expect(page.locator('#sheet .pf-h')).toHaveText('Meus treinos'); // continua na mesma tela
    }
    if (v === 'visual') { await page.click('#sheet [data-a="set-tlay"][data-v="foco"]'); expect(await simp(page, () => window.__simp.PREF.treinoLayout)).toBe('foco'); await expect(page.locator('#sheet .pf-h')).toHaveText('Visual'); }
    if (v === 'conta') { await page.click('#sheet [data-a="wipe"]'); await expect(page.locator('#sheet [data-a="wipe"]')).toContainText('Toque de novo'); }
    await page.click('#sheet .pf-back');
    await expect(page.locator('#sheet .pf-tile')).toHaveCount(4);
  }
});

test('avaliação do app: "Agora não" pede de novo no dia seguinte (até avaliar); depois de avaliar, só daqui a 15 dias', async ({ page }) => {
  await page.clock.install({ time: new Date('2026-11-17T10:00:00-03:00') });
  await criaConta(page, { nome: 'Lia Aval' }); await semJanelas(page);
  await simp(page, () => { const T = window.__simp; T.S.aval = { ult: T.daysAgo(20) }; T.save(); });
  expect(await simp(page, () => window.__simp.avalPode())).toBe(true);
  await simp(page, () => { const T = window.__simp; T.ui.sheet = { k: 'aval', notas: {} }; T.renderSheet(); });
  await page.click('#sheet button[data-a="av-x"]');                   // "Agora não"
  expect(await simp(page, () => window.__simp.avalPode())).toBe(false); // hoje, não pede mais
  await page.clock.fastForward(24 * 3600e3);
  expect(await simp(page, () => window.__simp.avalPode())).toBe(true);  // amanhã, pede de novo
  await simp(page, () => window.__simp.avalFim(true));                  // avaliou
  await page.clock.fastForward(24 * 3600e3);
  expect(await simp(page, () => window.__simp.avalPode())).toBe(false);
  await page.clock.fastForward(14 * 24 * 3600e3);                          // +14 dias (15 desde a avaliação)
  expect(await simp(page, () => window.__simp.avalPode())).toBe(true);
});
