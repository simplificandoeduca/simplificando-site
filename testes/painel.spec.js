// 📋 Painel do personal + 👩‍🏫 aluno com personal, de ponta a ponta no Firebase de teste:
// o personal cria o acesso, cadastra a aluna e monta o treino; a aluna entra com a senha, treina; o painel vê a evolução.
const { test, expect, EMU, simp, entraComSenha, criaConta, semJanelas, fsLista } = require('./apoio');
test.describe.configure({ mode: 'serial' }); // um passo depende do anterior (mesmo personal e mesma aluna)

const DONO = { email: 'dono@teste.com', senha: 'segredo123' };
let codigo = '', aid = '';

async function entraPainel(page) {
  await page.goto(EMU + '/personal/');
  await page.waitForSelector('#g-u', { timeout: 20000 });
  await page.fill('#g-u', DONO.email); await page.fill('#g-p', DONO.senha);
  if (await page.$('#g-p2')) await page.fill('#g-p2', DONO.senha); // 1º acesso: cria o dono do painel
  await page.click('.gate-go');
  await page.waitForFunction(() => document.getElementById('gate').hidden === true, null, { timeout: 20000 });
  await page.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
}
const vai = (page, h) => page.evaluate(h => { location.hash = h; }, h);

test('o personal entra no painel (1º acesso cria o dono)', async ({ page }) => {
  await entraPainel(page);
  await expect(page.locator('main').first()).toContainText('Painel');
  // no 1º acesso a biblioteca pronta é salva no Firebase: espera terminar antes de fechar a página
  const n = await page.evaluate(() => Object.keys(window.__painel.C.exercicios).length);
  expect(n).toBeGreaterThan(100);
  await expect.poll(async () => (await fsLista('exercicios')).length, { timeout: 30000 }).toBe(n);
});

test('versão de teste: usuário "teste" e senha 123456 abre o painel de demonstração', async ({ page }) => {
  await page.goto(EMU + '/personal/'); await page.waitForSelector('#g-u');
  await page.fill('#g-u', 'teste'); await page.fill('#g-p', '123456');
  if (await page.$('#g-p2')) await page.fill('#g-p2', '123456');
  await page.click('.gate-go');
  await page.waitForURL(/\/personal\/teste\//);
});

test('cadastra a aluna e monta o treino com aquecimento e cardio obrigatório', async ({ page }) => {
  await entraPainel(page);
  await vai(page, '#biblioteca'); await page.waitForTimeout(500);
  const sl = await page.$('[data-a="starterLib"]'); if (sl) { await sl.click(); await page.waitForTimeout(1200); }
  await vai(page, '#alunos'); await page.click('[data-a="novo"]');
  await page.fill('#nv-nome', 'Ana Robô'); await page.fill('#nv-email', 'ana' + Date.now() + '@teste.com');
  await page.click('[data-d="novo.sexo"][data-v="F"]');
  await page.click('[data-a="createAluno"]');
  await expect.poll(() => page.evaluate(() => (document.getElementById('toast').textContent.match(/\d{6}/) || [''])[0])).toMatch(/^\d{6}$/);
  codigo = await page.evaluate(() => document.getElementById('toast').textContent.match(/\d{6}/)[0]);
  aid = await page.evaluate(() => (location.hash.match(/aluno--(.+?)--/) || [])[1]);
  expect(aid).toBeTruthy();
  // treino A com 3 exercícios da biblioteca, aquecimento "Pesado" no 1º
  await vai(page, '#aluno--' + aid + '--treino'); await page.waitForTimeout(500);
  await page.click('[data-a="addTreino"]');
  for (const id of ['ex-agach', 'ex-supino', 'ex-triceps']) { await page.click('[data-a="pick"]'); await page.click(`[data-a="pickEx"][data-id="${id}"]`); await page.waitForTimeout(300); }
  await page.locator('.exc input[data-b$=".aq"]').first().evaluate(el => { el.checked = true; el.dispatchEvent(new Event('change', { bubbles: true })); });
  await page.waitForSelector('.aqw');
  await page.click('[data-a="aqMod"][data-k="2"]');
  // cardio obrigatório: escada 40 min
  await vai(page, '#aluno--' + aid + '--metas'); await page.waitForTimeout(500);
  await page.click('[data-a="obAdd"]');
  await page.selectOption('select[data-b$="obrig.0.local"]', 'Escada');
  const mn = page.locator('input[data-b$="obrig.0.min"]'); await mn.fill('40'); await mn.blur();
  // a conta do app da aluna fica pronta e o treino vai para o Firebase
  await vai(page, '#aluno--' + aid + '--acesso');
  await expect.poll(async () => {
    const a = (await fsLista('appAlunos')).find(x => x.f.aid && x.f.aid.stringValue === aid);
    return !!(a && a.f.ativo && a.f.ativo.booleanValue && JSON.stringify(a.f).includes('ex-agach') && JSON.stringify(a.f).includes('Escada'));
  }, { timeout: 30000 }).toBe(true);
});

test('a aluna entra com a senha de 6 números e vê o treino e o cardio obrigatório', async ({ page }) => {
  expect(codigo).toMatch(/^\d{6}$/);
  await entraComSenha(page, { codigo, emu: true });
  await simp(page, () => { const T = window.__simp; T.ui.tab = 'hoje'; T.render(); });
  await expect(page.locator('.ob-c')).toContainText('40 min');
  await simp(page, () => { const T = window.__simp; T.ui.tab = 'treino'; T.render(); });
  for (const n of ['Agachamento', 'Supino']) await expect(page.locator('#app')).toContainText(n);
  expect(await simp(page, () => { const T = window.__simp, e = T.PLAN[T.LETTERS[0]].ex[0]; return [e.aq, e.aqW.length]; })).toEqual([true, 3]);
});

test('a aluna treina e o painel vê na Evolução (com o quadro do cardio obrigatório)', async ({ page, browser }) => {
  await entraComSenha(page, { codigo, emu: true });
  await simp(page, () => { const T = window.__simp, t = T.LETTERS[0]; T.ui.t = t; T.startSession(t); T.ui.sheet = null; T.renderSheet(); T.PLAN[t].ex.forEach(e => T.markAll(e.id)); T.S.cardio.push({ date: T.today(), local: 'Escada', min: 40, int: 'Forte' }); T.ui.tab = 'treino'; T.render(); });
  await semJanelas(page);
  await page.click('[data-a="finish"]'); await page.click('[data-a="finish"]');
  await expect.poll(async () => { const p = (await fsLista('progresso')).find(x => x.f.aid && x.f.aid.stringValue === aid); return p ? JSON.parse(p.f.s.stringValue).h.length : 0; }, { timeout: 30000 }).toBeGreaterThan(0);
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } }); const p2 = await ctx.newPage();
  await entraPainel(p2);
  await vai(p2, '#aluno--' + aid + '--evolucao');
  await expect(p2.locator('main').first()).toContainText('Cardio obrigatório', { timeout: 20000 });
  await expect(p2.locator('.ob2')).toContainText('Escada · 40 min');
  await ctx.close();
});

test('Perfis mostra quem criou conta sem personal; Ajustes tem "Avisos no celular" e "Atualizar o site"', async ({ page, browser }) => {
  await criaConta(page, { nome: 'Bruna Perfis', emu: true });
  const p2 = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await entraPainel(p2);
  await vai(p2, '#perfis');
  await expect(p2.locator('main').first()).toContainText('Bruna Perfis', { timeout: 20000 });
  await vai(p2, '#ajustes');
  await expect(p2.locator('main').first()).toContainText('Avisos no celular');
  await expect(p2.locator('[data-a="nvOn"]')).toBeVisible();
  await expect(p2.locator('main').first()).toContainText('Atualizar');
  await p2.close();
});

test('Hub (/hub): números reais e um bloco para cada espaço; Números saiu das abas do painel', async ({ page }) => {
  await entraPainel(page);
  await expect(page.locator('#tabs')).not.toContainText('Números');
  await expect(page.locator('#tabs')).toContainText('Painel');
  await page.goto(EMU + '/hub/');
  await page.waitForURL(/\/personal\/#hub$/);
  await page.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
  await expect(page.locator('.hub h1')).toHaveText('Hub.');
  await expect(page.locator('#tabs')).toBeHidden();
  const num = /^[1-9]\d*$/;
  await expect(page.locator('.hb-st').nth(0).locator('b')).toHaveText(num);                        // alunos ativos (a Ana)
  await expect(page.locator('.hb-st').nth(2).locator('b')).toHaveText(num, { timeout: 20000 });    // cadastros no mês (a Bruna)
  await expect(page.locator('.hb-bl.red .hb-num b')).toHaveText(num, { timeout: 20000 });          // a Ana treinou hoje
  // cada bloco abre em outra aba: o app de teste, o painel e Números
  expect(await page.locator('.hb-bl').evaluateAll(L => L.map(a => [a.getAttribute('href'), a.getAttribute('target')]))).toEqual([['/app-teste/', '_blank'], ['/personal/#painel', '_blank'], ['/personal/#estatisticas', '_blank']]);
  expect(await page.locator('.hb-ms').evaluateAll(L => L.map(a => a.getAttribute('href')))).toEqual(['/financas/', '/personal/teste/', '/']);
  // Números abre fora das abas, com o botão de voltar ao hub
  const [nt] = await Promise.all([page.context().waitForEvent('page'), page.click('.hb-bl.blue')]);
  await expect(nt).toHaveURL(/\/personal\/#estatisticas$/);
  await expect(nt.locator('main').first()).toContainText('Avaliação do app', { timeout: 20000 });
  await nt.click('a[href="#hub"]');
  await expect(nt.locator('.hub')).toBeVisible();
  // Painel abre com as abas
  const [pn] = await Promise.all([page.context().waitForEvent('page'), page.click('.hb-bl.lime')]);
  await expect(pn).toHaveURL(/\/personal\/#painel$/);
  await expect(pn.locator('#tabs')).toBeVisible();
  await expect(pn.locator('.hub')).toHaveCount(0);
});
