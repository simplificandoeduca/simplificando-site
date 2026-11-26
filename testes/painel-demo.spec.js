// 📋 Painel do personal (versão de demonstração, com alunos fictícios): telas, receita, agenda, abas do aluno, PDF e Números
const { test, expect, LOCAL, desliza } = require('./apoio');
test.use({ viewport: { width: 1280, height: 900 }, isMobile: false, hasTouch: false, userAgent: undefined });

async function abreDemo(page) {
  await page.addInitScript(() => { try { sessionStorage.setItem('simp-teste-ok', '1'); } catch (e) {} });
  await page.goto(LOCAL + '/personal/teste/');
  await page.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
  if (await page.$('[data-a="demoSeed"]')) { await page.click('[data-a="demoSeed"]'); }
  await page.waitForFunction(() => Object.keys(window.__painel.C.alunos).length >= 4, null, { timeout: 10000 });
}
const vai = (page, h) => page.evaluate(h => { location.hash = h; }, h);
const main = page => page.locator('main').first();

test('cliente teste cria alunos fictícios e o painel mostra a agenda e a receita do mês', async ({ page }) => {
  await abreDemo(page);
  await vai(page, '#painel'); await page.waitForTimeout(400);
  await expect(main(page)).toContainText('receita');
  await page.click('[data-a="receita"]');
  await expect(page.locator('#sheet')).toContainText('R$');
});

test('lista de alunos e todas as abas de um aluno abrem sem erro', async ({ page }) => {
  await abreDemo(page);
  await vai(page, '#alunos'); await page.waitForTimeout(300);
  const ids = await page.evaluate(() => Object.keys(window.__painel.C.alunos));
  const nome = await page.evaluate(id => window.__painel.C.alunos[id].perfil.nome, ids[0]);
  await expect(main(page)).toContainText(nome.split(' ')[0]);
  for (const tab of ['perfil', 'saude', 'treino', 'metas', 'acesso', 'evolucao', 'financeiro', 'notas', 'avaliacoes']) {
    await vai(page, `#aluno--${ids[0]}--${tab}`); await page.waitForTimeout(250);
    await expect(main(page)).not.toBeEmpty();
  }
});

test('montar treino: aquecimento com modelo pronto e cardio obrigatório no editor', async ({ page }) => {
  await abreDemo(page);
  const id = await page.evaluate(() => Object.keys(window.__painel.C.alunos)[0]);
  await vai(page, `#aluno--${id}--treino`); await page.waitForTimeout(400);
  const sw = page.locator('.exc input[data-b$=".aq"]').first();
  if (!(await sw.isChecked())) { await sw.evaluate(el => { el.checked = true; el.dispatchEvent(new Event('change', { bubbles: true })); }); }
  await page.waitForSelector('.aqw');
  await page.locator('[data-a="aqMod"][data-k="1"]').first().click();
  await expect(page.locator('.aqw').first()).toContainText('40');
  await vai(page, `#aluno--${id}--metas`); await page.waitForTimeout(400);
  await page.click('[data-a="obAdd"]');
  await expect(page.locator('.obw')).toContainText('Cardio obrigatório');
});

test('baixar o treino em PDF', async ({ page }) => {
  await abreDemo(page);
  const id = await page.evaluate(() => Object.keys(window.__painel.C.alunos)[0]);
  await vai(page, `#aluno--${id}--treino`); await page.waitForTimeout(400);
  const [d] = await Promise.all([page.waitForEvent('download', { timeout: 15000 }), page.locator('[data-a="pdfTreino"]').first().click()]);
  expect(d.suggestedFilename()).toMatch(/\.pdf$/i);
});

test('Números: avaliações, sugestões e stories dos alunos', async ({ page }) => {
  await abreDemo(page);
  await vai(page, '#estatisticas'); await page.waitForTimeout(800);
  await expect(page.locator('#side')).toContainText('Estatísticas'); // na versão de teste, continua sendo uma aba (lá não tem hub)
  await expect(main(page)).toContainText('Avalia');
  await expect(main(page)).toContainText('Sugest');
});

test('trocar de perfil (sair da versão de teste)', async ({ page }) => {
  await abreDemo(page);
  await page.evaluate(() => { const P = window.__painel; P.ui.sheet = { k: 'trocaPerfil' }; P.render(); });
  await expect(page.locator('#sheet')).toContainText('Trocar de perfil');
});

test.describe('no celular', () => {
  test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  test('arrastar o dedo para o lado troca as abas do aluno e as do painel', async ({ page }) => {
    await abreDemo(page);
    const id = await page.evaluate(() => Object.keys(window.__painel.C.alunos)[0]);
    await vai(page, `#aluno--${id}--perfil`); await page.waitForTimeout(500);
    await desliza(page, 320, 600, 80, 610);
    expect(await page.evaluate(() => window.__painel.ui.tab)).toBe('saude');
    await desliza(page, 80, 600, 320, 605);
    expect(await page.evaluate(() => window.__painel.ui.tab)).toBe('perfil');
    await vai(page, '#painel'); await page.waitForTimeout(400);
    await desliza(page, 320, 500, 80, 505);
    expect(await page.evaluate(() => window.__painel.ui.view)).toBe('alunos');
  });
});

test('abas do aluno: as caixinhas começam minimizadas, abrem ao tocar no título e "Abrir tudo" abre todas', async ({ page }) => {
  await page.addInitScript(() => { try { localStorage.setItem('simp-teste-ui', JSON.stringify({ cxAbertas: false })); } catch (e) {} });
  await abreDemo(page);
  const id = await page.evaluate(() => Object.keys(window.__painel.C.alunos)[0]);
  await vai(page, '#aluno--' + id + '--metas');
  const cx = page.locator('.tabpane section.card.cx');
  await expect(cx.first()).toBeVisible();
  const n = await cx.count(); expect(n).toBeGreaterThan(1);
  await expect(page.locator('.tabpane section.card.cx.cx-min')).toHaveCount(n);
  await cx.first().locator('.cx-h').click();
  await expect(page.locator('.tabpane section.card.cx.cx-min')).toHaveCount(n - 1);
  await page.click('[data-cx-todas="1"]');
  await expect(page.locator('.tabpane section.card.cx.cx-min')).toHaveCount(0);
  await page.click('[data-cx-todas="0"]');
  await expect(page.locator('.tabpane section.card.cx.cx-min')).toHaveCount(n);
});

test('novo aluno: colar a resposta do primeiro contato preenche o cadastro e a ficha', async ({ page }) => {
  await abreDemo(page);
  await vai(page, '#alunos'); await page.click('[data-a="novo"]');
  await page.fill('#nv-resp', '1) Mariana Costa de Souza, pode me chamar de Mari, (11) 98765-4321, 12/03/1995, mari.souza@email.com, 62 kg, 1,65\n2) quero emagrecer e definir\n4) treino ha 1 ano, mas estou parada faz 3 meses\n5) 4x por semana, 1 hora\n7) academia\n8) nao');
  await page.click('[data-a="nvLer"]');
  await expect(page.locator('#nv-nome')).toHaveValue('Mariana Costa de Souza');
  await expect(page.locator('#nv-apelido')).toHaveValue('Mari');
  await expect(page.locator('#nv-wa')).toHaveValue('11987654321');
  await expect(page.locator('#nv-email')).toHaveValue('mari.souza@email.com');
  await page.click('[data-a="createAluno"]');
  const p = await page.evaluate(() => { const C = window.__painel.C.alunos, a = Object.values(C).find(x => x.perfil.nome === 'Mariana Costa de Souza'); return a && { ...a.perfil, freq: a.plano.frequencia }; });
  expect(p).toMatchObject({ apelido: 'Mari', sexo: 'F', nascimento: '1995-03-12', peso: 62, altura: 165, objetivo: 'emagrecer', freq: 4, tempoSessao: 60, local: 'academia' });
});
