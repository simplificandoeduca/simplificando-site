// 📋 Painel do personal (versão de demonstração, com alunos fictícios): telas, receita, agenda, abas do aluno, PDF e Números
const { test, expect, LOCAL } = require('./apoio');
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
