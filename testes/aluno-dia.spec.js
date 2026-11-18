// 🏃 Cardio · 💧 Água · 📈 Evolução · check-in do dia · ✏️ Editar treino
const { test, expect, simp, entraComSenha, criaConta, semJanelas, arrasta, aba } = require('./apoio');

test.describe('com personal (exemplo)', () => {
  test.beforeEach(async ({ page }) => { await entraComSenha(page); });

  for (const lay of ['toque', 'crono', 'cartoes']) {
    test(`cardio no visual "${lay}" abre e registra`, async ({ page }) => {
      await simp(page, l => { window.__simp.PREF.cardioLayout = l; }, lay);
      await aba(page, 'cardio');
      const antes = await simp(page, () => window.__simp.S.cardio.length);
      if (lay === 'toque') await arrasta(page, '.slide[data-kind="csave"] .slide-knob');
      if (lay === 'crono') { await page.click('[data-a="cstart"]'); expect(await simp(page, () => !!window.__simp.S.cardioLive)).toBe(true); await page.click('[data-a="cend"]'); }
      if (lay === 'cartoes') { await page.locator('[data-a="cs-open"]').first().click(); await page.click('#sheet [data-a="cs-save"]'); }
      await page.waitForFunction(n => window.__simp.S.cardio.length > n, antes);
      const c = await simp(page, () => { const L = window.__simp.S.cardio; return L[L.length - 1]; });
      expect(c.min).toBeGreaterThan(0); expect(c.date).toBe(await simp(page, () => window.__simp.today()));
    });
  }

  test('cardio obrigatório do personal aparece no Hoje e conta quando é feito', async ({ page }) => {
    await simp(page, () => { const T = window.__simp; T.ACCT.aluno.metas.cardio = Object.assign({}, T.ACCT.aluno.metas.cardio, { obrig: [{ local: 'Escada', min: 20, vezes: 2, dias: [] }] }); T.applyAccount(); T.ui.tab = 'hoje'; T.render(); });
    await expect(page.locator('.ob-c')).toContainText('20 min');
    await simp(page, () => { const T = window.__simp; T.S.cardio.push({ date: T.today(), local: 'Escada', min: 25, int: 'Média' }); T.save(); T.render(); });
    await expect(page.locator('.ob-c')).toContainText('Feito hoje');
  });

  test('água: copos, desfazer e meta do dia', async ({ page }) => {
    await aba(page, 'agua');
    const hj = await simp(page, () => window.__simp.today());
    await simp(page, () => { window.__simp.S.waterGoal = 800; window.__simp.render(); });
    await page.locator('[data-a="water"][data-ml="300"]').first().click();
    expect(await simp(page, d => window.__simp.S.water[d], hj)).toBe(300);
    await page.locator('[data-a="water-minus"]').first().click(); // o "−" tira 250 ml
    expect(await simp(page, d => window.__simp.S.water[d] || 0, hj)).toBe(50);
    await page.locator('[data-a="water"][data-ml="500"]').first().click();
    await page.locator('[data-a="water"][data-ml="500"]').first().click();
    expect(await simp(page, d => window.__simp.S.water[d] >= window.__simp.S.waterGoal, hj)).toBe(true);
  });

  test('água: lembrete liga/desliga, horário e intervalo (na aba Água fica uma linha; a configuração abre em Perfil › Avisos)', async ({ page }) => {
    await aba(page, 'agua');
    await simp(page, () => { window.__simp.S.remind.on = false; window.__simp.render(); });
    await expect(page.locator('.rm-line')).toContainText('Desligados');
    await page.click('[data-a="avisos-open"]');
    await expect(page.locator('.sheet')).toContainText('Avisos');
    await page.locator('.sheet [data-sw="remind"]').first().evaluate(el => { el.checked = true; el.dispatchEvent(new Event('change', { bubbles: true })); });
    expect(await simp(page, () => window.__simp.S.remind.on)).toBe(true);
    await page.click('[data-a="remind-ev"][data-v="60"]');
    expect(await simp(page, () => window.__simp.S.remind.every)).toBe(60);
    await page.locator('[data-remind="ini"]').evaluate(el => { el.value = '09:30'; el.dispatchEvent(new Event('change', { bubbles: true })); });
    expect(await simp(page, () => window.__simp.S.remind.ini)).toBe(570);
    await page.click('[data-a="close"]');
    await expect(page.locator('.rm-line')).toContainText('A cada 1 hora, das 9h30');
  });

  test('check-in do dia: sono, energia e dor', async ({ page }) => {
    await simp(page, () => { const T = window.__simp; delete T.S.checkin[T.today()]; T.ui.tab = 'hoje'; T.render(); });
    await page.click('[data-a="ci-sono"][data-v="7"]');
    await page.click('[data-a="ci-energia"][data-v="4"]');
    await page.click('[data-a="ci-dor"][data-v="1"]');
    await page.click('[data-a="ci-save"]');
    expect(await simp(page, () => window.__simp.S.checkin[window.__simp.today()])).toMatchObject({ sono: 7, energia: 4, dor: 1 });
  });

  test('evolução abre com gráficos e recordes', async ({ page }) => {
    await aba(page, 'evo');
    await expect(page.locator('#app')).toContainText('Evolução');
    expect(await page.locator('#app svg').count()).toBeGreaterThan(0);
  });
});

test.describe('sem personal: editar meus treinos', () => {
  test.beforeEach(async ({ page }) => { await criaConta(page); await semJanelas(page); await simp(page, () => window.__simp.setLay('lista')); });

  test('séries, repetições e descanso de um exercício', async ({ page }) => {
    await simp(page, () => window.__simp.mtOpen());
    await page.locator('[data-a="mt-open"]').first().click();
    const s0 = await simp(page, () => window.__simp.S.conta.treinos.A.ex[0].s);
    await page.locator('.mt-ex').first().locator('[data-a="mt-s"][data-d="1"]').click();
    await page.locator('.mt-ex').first().locator('[data-a="mt-d"][data-d="15"]').click();
    const e = await simp(page, () => window.__simp.S.conta.treinos.A.ex[0]);
    expect(e.s).toBe(s0 + 1);
  });

  test('aquecimento: liga, ajusta, modelo pronto e aparece no treino', async ({ page }) => {
    await simp(page, () => window.__simp.mtOpen());
    await page.locator('[data-a="mt-open"]').first().click();
    const ex = page.locator('.mt-ex').first();
    await ex.locator('[data-a="mt-aq"]').click();
    expect(await simp(page, () => window.__simp.S.conta.treinos.A.ex[0].aqW)).toEqual([{ p: 50, r: 10 }, { p: 75, r: 5 }]);
    await ex.locator('[data-a="mt-aqp"][data-j="0"][data-d="5"]').click();
    await ex.locator('[data-a="mt-aqadd"]').click();
    expect(await simp(page, () => window.__simp.S.conta.treinos.A.ex[0].aqW.map(w => w.p))).toEqual([55, 75, 90]);
    await ex.locator('[data-a="mt-aqmod"][data-k="2"]').click();
    expect(await simp(page, () => window.__simp.S.conta.treinos.A.ex[0].aqW)).toEqual([{ p: 40, r: 10 }, { p: 60, r: 6 }, { p: 80, r: 3 }]);
    await simp(page, () => { const T = window.__simp; T.closeSheet(); T.startSession('A'); T.ui.sheet = null; T.renderSheet(); T.ui.t = 'A'; T.ui.tab = 'treino'; T.ui.lopen = T.PLAN.A.ex[0].id; T.render(); });
    await expect(page.locator('.twarm')).toContainText('40% × 10');
  });

  test('apagar treinos: as letras se reorganizam (B e E viram A e B)', async ({ page }) => {
    await simp(page, () => {
      const T = window.__simp, base = JSON.parse(JSON.stringify(T.S.conta.treinos.A)), tr = {};
      ['Peito', 'Costas', 'Pernas', 'Ombros', 'Braços'].forEach((n, i) => { const t = JSON.parse(JSON.stringify(base)); t.nome = n; t.dias = [i]; tr['ABCDE'[i]] = t; });
      T.S.conta.plan = 'meu'; T.S.conta.treinos = tr; T.S.history.push({ date: T.daysAgo(1), t: 'E', min: 45, vol: 900, sets: 10, prs: [] }); T.applyAccount(); T.mtOpen();
    });
    for (const n of ['Peito', 'Pernas', 'Ombros']) {
      await page.locator('[data-a="mt-open"]', { hasText: n }).first().click();
      await page.click('[data-a="mt-del"]'); await page.click('[data-a="mt-del"]');
    }
    await simp(page, () => window.__simp.closeSheet());
    const r = await simp(page, () => ({ plano: Object.keys(window.__simp.PLAN).map(l => l + ':' + window.__simp.PLAN[l].nome), hist: window.__simp.S.history[window.__simp.S.history.length - 1].t }));
    expect(r).toEqual({ plano: ['A:Costas', 'B:Braços'], hist: 'B' });
  });
});
