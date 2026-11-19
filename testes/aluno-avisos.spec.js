// ⚙️ Perfil e avisos no celular (descanso, treino esquecido, lembrete do dia, água) e o que vai para o Firebase de teste
const { test, expect, simp, entraComSenha, criaConta, semJanelas, capturaAvisos, ligaAvisos, fsLista, aba } = require('./apoio');

const abrePerfil = page => simp(page, () => { const T = window.__simp; T.ui.sheet = { k: 'prof', p: 'avisos' }; T.renderSheet(); }); // Perfil › Avisos
const vaiPerfil = (page, p) => simp(page, p => { const T = window.__simp; T.ui.sheet = { k: 'prof', p }; T.renderSheet(); }, p);
const chave = (page, k, on) => page.locator(`#sheet [data-sw="${k}"]`).first().evaluate((el, on) => { el.checked = on; el.dispatchEvent(new Event('change', { bubbles: true })); }, on);

test.describe('com personal (exemplo)', () => {
  test.beforeEach(async ({ page }) => { await entraComSenha(page); });

  test('Perfil › Notificações: avisos desligados mostram o botão para ligar', async ({ page }) => {
    await simp(page, () => { window.__simpPush = true; window.__simp.PREF.pushOn = false; });
    await abrePerfil(page);
    await expect(page.locator('#sheet .nta')).toContainText('Avisos no celular desligados');
    await expect(page.locator('#sheet [data-a="ntf-push"]')).toBeVisible();
    for (const t of ['Lembrete do treino', 'Lembrete de água', 'Fim do descanso', 'Treino esquecido aberto']) await expect(page.locator('#sheet .nta')).toContainText(t);
  });

  test('fim do descanso: avisa o servidor ao começar, cancela perto do fim com o app aberto e respeita a chave', async ({ page }) => {
    const L = await capturaAvisos(page); await ligaAvisos(page);
    await simp(page, () => { const T = window.__simp, t = T.LETTERS[0], e = T.PLAN[t].ex[0]; T.ui.t = t; T.startSession(t); T.ui.sheet = null; T.renderSheet(); T.getLog(e.id); T.confirmSet(e.id, 0); });
    await expect.poll(() => L.filter(x => x.acao === 'descanso' && x.corpo.fim > 0).length).toBe(1);
    const d = L.find(x => x.acao === 'descanso'); expect(d.corpo.k).toMatch(/^[a-z0-9]{4,16}$/);
    const guardado = await simp(page, async () => { const c = await caches.open('simp-estado'); const r = await c.match(new URL('__descanso', location.href).href); return r && r.json(); });
    expect(guardado).toMatchObject({ s: 2, ex: expect.any(String) });
    await simp(page, () => { window.__simp.ui.rest.end = Date.now() + 1500; });
    await expect.poll(() => L.filter(x => x.acao === 'descanso' && x.corpo.fim === 0).length, { timeout: 8000 }).toBe(1);
    // desligando a chave no perfil, o próximo descanso não é avisado
    await semJanelas(page); await abrePerfil(page); await chave(page, 'avdesc', false); await semJanelas(page);
    const n = L.length;
    await simp(page, () => { const T = window.__simp, e = T.PLAN[T.ui.t].ex[0]; T.confirmSet(e.id, 1); });
    await page.waitForTimeout(500);
    expect(L.slice(n).filter(x => x.acao === 'descanso' && x.corpo.fim > 0)).toHaveLength(0);
  });

  test('treino esquecido: o servidor recebe o início do treino; a chave desliga', async ({ page }) => {
    const L = await capturaAvisos(page); await ligaAvisos(page);
    await simp(page, () => { const T = window.__simp; T.startSession(T.LETTERS[0]); T.ui.sheet = null; T.renderSheet(); });
    await expect.poll(() => L.filter(x => x.acao === 'treino' && x.corpo.ini > 0).length).toBe(1);
    await semJanelas(page); await abrePerfil(page); await chave(page, 'avtreino', false);
    await expect.poll(() => L.filter(x => x.acao === 'treino' && x.corpo.ini === 0).length).toBe(1);
  });

  test('lembretes para voltar: o celular recebe a data do cadastro e do último treino; a chave do perfil desliga', async ({ page }) => {
    const L = await capturaAvisos(page); await ligaAvisos(page);
    const ult = () => (L.filter(x => x.acao === 'lembrete').pop() || { corpo: {} }).corpo.vt;
    await simp(page, () => window.__simp.lbSync());
    await expect.poll(ult).toMatchObject({ on: 1, cad: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/) });
    await semJanelas(page); await abrePerfil(page);
    await expect(page.locator('#sheet .nta')).toContainText('Lembretes para voltar');
    await chave(page, 'avvolta', false);
    await expect.poll(() => (ult() || {}).on).toBe(0);
  });

  test('lembrete do treino: só depois da 1ª semana, no horário aprendido de cada dia (30 min antes)', async ({ page }) => {
    const L = await capturaAvisos(page); await ligaAvisos(page);
    // conta nova: começa só daqui a 7 dias
    const r1 = await simp(page, () => { const T = window.__simp, S = T.S; S.history = []; S.cardio = []; S.water = {}; S.aceite.em = new Date().toISOString(); T.lbSync(); return { sub: T.lbSubTxt(), desde: T.lbAPartir(), hj: T.today() }; });
    expect(r1.sub).toContain('Começa na 2ª semana'); expect(r1.desde).toBe(await simp(page, d => window.__simp.addDays(d, 7), r1.hj));
    // 3 semanas treinando: segunda às 7h e quinta às 19h
    const r2 = await simp(page, () => {
      const T = window.__simp, S = T.S, dow = d => new Date(d + 'T12:00').getDay(); S.history = [];
      for (let i = 1; i <= 21; i++) { const d = T.daysAgo(i), w = dow(d); if (w === 1) S.history.push({ date: d, t: 'A', min: 60, hi: '07:00', hr: '08:00', sets: 10, vol: 1000, prs: [] }); if (w === 4) S.history.push({ date: d, t: 'B', min: 70, hi: '19:00', hr: '20:10', sets: 10, vol: 1000, prs: [] }); }
      S.history.sort((a, b) => a.date < b.date ? -1 : 1); S.aceite.em = new Date(Date.now() - 21 * 864e5).toISOString(); T.lbSync(); return T.lbHoras();
    });
    expect(r2.hs['1']).toBe(390); expect(r2.hs['4']).toBe(1110);
    const ult = L.filter(x => x.acao === 'lembrete').pop(); expect(ult.corpo).toMatchObject({ on: 1, dias: [1, 2, 4, 5] });
    // começou a treinar hoje: o servidor fica sabendo para não avisar
    await simp(page, () => { const T = window.__simp; T.startSession(T.LETTERS[0]); T.ui.sheet = null; T.renderSheet(); T.save(); });
    await expect.poll(() => (L.filter(x => x.acao === 'lembrete').pop() || {}).corpo.feito).toBe(r1.hj);
  });

  test('convite a partir do 2º treino (no 1º não interrompe): "Quer ser avisado quando o descanso acabar?"', async ({ page }) => {
    // celular que ainda não respondeu se aceita notificações (o navegador de teste vem sempre como "bloqueado")
    await simp(page, () => { try { Object.defineProperty(Notification, 'permission', { get: () => 'default', configurable: true }); } catch (e) {} });
    // 1º treino: não pergunta
    await simp(page, () => { window.__simpPush = true; const T = window.__simp; T.PREF.pushOn = false; delete T.PREF.dsAsk; T.S.history = []; T.closeSheet(); T.startSession(T.LETTERS[0]); });
    await page.waitForFunction(() => window.__simp.ui.sheet && window.__simp.ui.sheet.k === 'gym');
    await simp(page, () => window.__simp.closeSheet());
    await page.waitForTimeout(3000);
    expect(await simp(page, () => window.__simp.ui.sheet && window.__simp.ui.sheet.k)).not.toBe('dsask');
    // 2º treino: pergunta
    await simp(page, () => { const T = window.__simp; T.S.session = null; T.S.history = [{ date: T.daysAgo(2), t: T.LETTERS[0], min: 40, sets: 10, vol: 1000, prs: [], ex: [] }]; T.closeSheet(); T.startSession(T.LETTERS[0]); });
    await page.waitForFunction(() => window.__simp.ui.sheet && window.__simp.ui.sheet.k === 'gym');
    await simp(page, () => window.__simp.closeSheet());
    await page.waitForFunction(() => window.__simp.ui.sheet && window.__simp.ui.sheet.k === 'dsask', null, { timeout: 8000 });
    await page.click('#sheet button[data-a="ds-no"]');
    expect(await simp(page, () => [window.__simp.ui.sheet, window.__simp.PREF.dsAsk])).toEqual([null, 1]);
  });
});

test.describe('sem personal', () => {
  test('perfil: cor, Liquid Glass, boneco e meta de água', async ({ page }) => {
    await criaConta(page); await semJanelas(page); await vaiPerfil(page, 'visual');
    const cor = await page.locator('#sheet [data-a="accent"]').nth(1).getAttribute('data-v');
    await page.locator('#sheet [data-a="accent"]').nth(1).click();
    expect(await simp(page, () => window.__simp.PREF.accent)).toBe(cor);
    await chave(page, 'glass', true); expect(await simp(page, () => window.__simp.PREF.glass)).toBe(true);
    await page.click('#sheet [data-a="boneco"][data-v="M"]'); expect(await simp(page, () => window.__simp.S.conta.sexo)).toBe('M');
    await vaiPerfil(page, 'plano');
    const g = await simp(page, () => window.__simp.S.waterGoal);
    await page.click('#sheet [data-a="goal"][data-d="250"]'); expect(await simp(page, () => window.__simp.S.waterGoal)).toBe(g + 250);
    await vaiPerfil(page, 'conta');
    await page.click('#sheet [data-a="doc-open"][data-v="termos"]');
    await expect(page.locator('#sheet')).toContainText('Termos');
  });

  test('Firebase de teste: compartilhar, sugestão e avaliação chegam no painel', async ({ page }) => {
    const c = await criaConta(page, { emu: true }); await semJanelas(page);
    const uid = await simp(page, () => window.__simp.ACCT.uid);
    // compartilhar (chave no perfil)
    await abrePerfil(page); await chave(page, 'acomp', true);
    await expect.poll(async () => { const p = (await fsLista('perfis')).find(x => x.id === uid); return p && p.f.acomp && p.f.acomp.booleanValue; }, { timeout: 10000 }).toBe(true);
    // sugestão
    await vaiPerfil(page, 'sug');
    await page.fill('#sug-txt', 'Robô: queria ver o gráfico do peso.'); await page.click('#sheet [data-a="sug-send"]');
    await expect.poll(async () => (await fsLista('sugestoes')).some(x => x.f.uid && x.f.uid.stringValue === uid), { timeout: 10000 }).toBe(true);
    // avaliação (estrelas)
    await semJanelas(page); await simp(page, () => { const T = window.__simp; T.ui.sheet = { k: 'aval' }; T.renderSheet(); });
    await page.locator('#sheet [data-a="av-n"][data-v="5"]').first().click(); await page.click('#sheet [data-a="av-go"]');
    await expect.poll(async () => (await fsLista('avaliacoes')).some(x => x.f.uid && x.f.uid.stringValue === uid), { timeout: 10000 }).toBe(true);
    expect(c.email).toContain('@teste.com');
  });
});

test('lembrete do treino: quem aceita os termos à noite (22h30) começa 7 dias depois do mesmo dia, não do seguinte', async ({ page }) => {
  await page.clock.install({ time: new Date('2026-11-16T22:30:00-03:00') }); // já é dia 17 no horário UTC
  await entraComSenha(page);
  const r = await simp(page, () => { const T = window.__simp; T.S.history = []; T.S.aceite.em = new Date().toISOString(); return [T.today(), T.lbAPartir()]; });
  expect(r).toEqual(['2026-11-16', '2026-11-23']);
});
