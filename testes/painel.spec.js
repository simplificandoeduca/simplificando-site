// 📋 Painel do personal + 👩‍🏫 aluno com personal, de ponta a ponta no Firebase de teste:
// o personal cria o acesso, cadastra a aluna e monta o treino; a aluna entra com a senha, treina; o painel vê a evolução.
const { test, expect, EMU, simp, entraComSenha, criaConta, semJanelas, fsLista, arrasta } = require('./apoio');
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

test('treino marcado 2 vezes: o personal altera o tempo de um e exclui o outro; o app da aluna fica igual', async ({ page, browser }) => {
  // a aluna marca de novo o mesmo treino no mesmo dia (2 min, só 1 série)
  await entraComSenha(page, { codigo, emu: true });
  const t = await simp(page, () => { const T = window.__simp, t = T.LETTERS[0], e = T.PLAN[t].ex[0]; T.startSession(t); T.ui.sheet = null; T.renderSheet(); const L = T.getLog(e.id); L.forEach(x => { x.ok = false; }); L[0].ok = true; L[0].kg = '20'; L[0].reps = '10'; T.save(); T.finish(); T.ui.sheet = null; T.renderSheet(); T.save(); return t; });
  const hoje = await simp(page, () => window.__simp.today());
  await expect.poll(async () => { const p = (await fsLista('progresso')).find(x => x.f.aid && x.f.aid.stringValue === aid); return p ? JSON.parse(p.f.s.stringValue).h.filter(h => h.d === hoje && h.t === t).length : 0; }, { timeout: 30000 }).toBe(2);
  // o personal vê os dois na Evolução
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } }); const p2 = await ctx.newPage();
  await entraPainel(p2); await vai(p2, '#aluno--' + aid + '--evolucao');
  const det = p2.locator('details.ev3-h'); await expect.poll(() => det.count(), { timeout: 20000 }).toBeGreaterThanOrEqual(2);
  // check-in sempre de segunda a domingo (o dia de hoje aparece como "hoje")
  const dias = await p2.locator('.ev2-ck thead th').evaluateAll(L => L.slice(1).map(th => th.childNodes[0].textContent));
  const wd = await p2.evaluate(() => (new Date().getDay() + 6) % 7);
  expect(dias).toEqual(['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'].map((d, i) => i === wd ? 'hoje' : d));
  const n0 = await det.count();
  await det.nth(1).locator('summary').click();
  // alterar o tempo do 1º
  await det.nth(0).locator('[data-a="evTempo"]').click();
  await p2.fill('#tp-min', '45'); await p2.locator('#tp-min').dispatchEvent('change');
  await p2.click('[data-a="evTempoOk"]');
  await expect(det.nth(0)).toContainText('45 min');
  await expect(det.nth(0)).toContainText('Tempo mudado por você');
  // excluir o 2º (pede dois toques)
  await det.nth(1).locator('[data-a="evExcluir"]').click(); await det.nth(1).locator('[data-a="evExcluir"]').click();
  await expect.poll(() => det.count()).toBe(n0 - 1);
  await expect.poll(async () => { const a = (await fsLista('appAlunos')).find(x => x.f.aid && x.f.aid.stringValue === aid); return a ? JSON.stringify(a.f.ajustes || {}) : ''; }, { timeout: 20000 }).toContain('del');
  await ctx.close();
  // a aluna abre o app: fica um treino só de hoje, com 45 min; e não volta da nuvem
  const ctx2 = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }); const p3 = await ctx2.newPage();
  await entraComSenha(p3, { codigo, emu: true });
  await expect.poll(() => simp(p3, t => { const T = window.__simp, d = T.today(); return T.S.history.filter(h => h.date === d && h.t === t).map(h => h.min); }, t), { timeout: 15000 }).toEqual([45]);
  await ctx2.close();
});

test('Evolução: tocar em "Treinos" mostra os treinos da semana (fechados) e em "Água" o horário de cada copo', async ({ page, browser }) => {
  await entraComSenha(page, { codigo, emu: true });
  const hoje = await simp(page, () => { const T = window.__simp, S = T.S, t = T.today(); S.wlog[t] = [{ m: 550, ml: 300 }, { m: 615, ml: 500 }, { m: 700, ml: -250 }, { m: 720, ml: 400 }]; S.water[t] = 950; T.save(); return t; });
  await expect.poll(async () => { const p = (await fsLista('progresso')).find(x => x.f.aid && x.f.aid.stringValue === aid); const s = p ? JSON.parse(p.f.s.stringValue) : {}; return s.wl && s.wl[hoje] ? s.wl[hoje].length : 0; }, { timeout: 30000 }).toBe(4);
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } }); const p2 = await ctx.newPage();
  await entraPainel(p2); await vai(p2, '#aluno--' + aid + '--evolucao');
  await p2.locator('[data-a="evAgua"]').click({ timeout: 20000 });
  const sh = p2.locator('#sheet');
  await expect(sh).toContainText('Água de cada dia');
  for (const x of ['09:10', '+300 ml', '10:15', '+500 ml', '−250 ml', '12:00']) await expect(sh).toContainText(x);
  await p2.click('#sheet [data-a="close"]');
  await p2.locator('[data-a="evTreinos"]').click();
  await expect(sh).toContainText('Treinos da semana');
  const tr = sh.locator('details.evt-h'); await expect.poll(() => tr.count()).toBeGreaterThan(0);
  expect(await tr.first().evaluate(el => el.open)).toBe(false); // fechados
  await tr.first().locator('summary').click();
  await expect(tr.first().locator('.evt-r').first()).toBeVisible();
  await ctx.close();
});

test('Perfis mostra quem criou conta sem personal; Ajustes tem "Avisos no celular" (o "Atualizar" foi para o hub)', async ({ page, browser }) => {
  await criaConta(page, { nome: 'Bruna Perfis', emu: true });
  const p2 = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await entraPainel(p2);
  await vai(p2, '#perfis');
  await expect(p2.locator('main').first()).toContainText('Bruna Perfis', { timeout: 20000 });
  await vai(p2, '#ajustes');
  await expect(p2.locator('main').first()).toContainText('Avisos no celular');
  await expect(p2.locator('[data-a="nvOn"]')).toBeVisible();
  await expect(p2.locator('main').first()).not.toContainText('Atualização automática');
  await expect(p2.locator('[data-a="atuGo"]')).toHaveCount(0);
  await p2.close();
});

test('Alunos e Perfis: marquinha laranja "+3 dias sem acessar" e vermelha "+10 dias sem acessar"', async ({ page }) => {
  const FSB = 'http://127.0.0.1:8080/v1/projects/demo-simplificando/databases/(default)/documents/perfis/';
  const muda = (uid, dias) => fetch(FSB + uid + '?updateMask.fieldPaths=ultimoAcesso', { method: 'PATCH', headers: { Authorization: 'Bearer owner', 'Content-Type': 'application/json' }, body: JSON.stringify({ fields: { ultimoAcesso: { integerValue: String(Date.now() - dias * 864e5) } } }) });
  const P = await fsLista('perfis');
  const ana = P.find(x => x.f.aid && x.f.aid.stringValue === aid), bruna = P.find(x => x.f.nome && x.f.nome.stringValue === 'Bruna Perfis');
  expect(ana && bruna).toBeTruthy();
  await muda(ana.id, 5); await muda(bruna.id, 12);
  await page.setViewportSize({ width: 1280, height: 900 });
  await entraPainel(page);
  await vai(page, '#alunos');
  const linhaAna = page.locator('#alunos-list .row', { hasText: 'Ana' }).first();
  await expect(linhaAna.locator('.ina-tag')).toHaveText('+3 dias sem acessar', { timeout: 20000 });
  expect(await linhaAna.locator('.ina-tag').evaluate(el => [getComputedStyle(el).backgroundColor, getComputedStyle(el).color])).toEqual(['rgb(255, 154, 46)', 'rgb(255, 255, 255)']);
  await vai(page, '#perfis');
  const linhaBruna = page.locator('#pf-list .row', { hasText: 'Bruna Perfis' }).first();
  await expect(linhaBruna.locator('.ina-tag')).toHaveText('+10 dias sem acessar', { timeout: 20000 });
  expect(await linhaBruna.locator('.ina-tag').evaluate(el => getComputedStyle(el).backgroundColor)).toBe('rgb(255, 70, 70)');
});

test('Hub (/hub): números reais e um bloco para cada espaço; Números saiu das abas do painel', async ({ page }) => {
  await entraPainel(page);
  await expect(page.locator('#tabs')).not.toContainText('Números');
  await expect(page.locator('#tabs')).toContainText('Painel');
  await page.goto(EMU + '/hub/');
  await expect(page).toHaveURL(/\/hub\/$/); // o endereço fica simplificandoeduca.com/hub
  await expect(page).toHaveTitle('Hub · Simplificando');
  expect(await page.locator('link[rel="icon"]').getAttribute('href')).toBe('icon-hub-192.png');
  await page.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
  await expect(page.locator('.hub h1')).toHaveText('Hub.');
  await expect(page.locator('#tabs')).toBeHidden();
  // cores do anel do logo: App do aluno vermelho, Painel laranja, Números lilás; a hora anda depois da data
  // (espera: o hub se redesenha quando os números chegam)
  await expect.poll(() => page.locator('.hb-bl').evaluateAll(L => L.map(x => getComputedStyle(x).backgroundColor))).toEqual(['rgb(255, 70, 70)', 'rgb(255, 154, 46)', 'rgb(183, 148, 255)', 'rgb(200, 245, 66)']);
  await expect(page.locator('#hb-hora')).toHaveText(/^\d\d:\d\d:\d\d$/);
  const h1 = await page.locator('#hb-hora').textContent();
  await expect.poll(() => page.locator('#hb-hora').textContent(), { timeout: 5000 }).not.toBe(h1); // o relógio anda
  // sem versão nova, o Atualizar fica branco
  await expect(page.locator('.hb-atu')).not.toHaveClass(/nova/);
  const num = /^[1-9]\d*$/;
  await expect(page.locator('.hb-st').nth(0).locator('b')).toHaveText(num);                        // alunos ativos (a Ana)
  await expect(page.locator('.hb-st').nth(2).locator('b')).toHaveText(num, { timeout: 20000 });    // cadastros no mês (a Bruna)
  await expect(page.locator('.hb-bl.red .hb-num b')).toHaveText(num, { timeout: 20000 });          // a Ana treinou hoje
  // o link antigo (/personal/#hub) foi desativado: abre o Painel normal, sem o hub
  const velho = await page.context().newPage(); await velho.goto(EMU + '/personal/#hub');
  await velho.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
  await expect(velho.locator('.hub')).toHaveCount(0); await expect(velho).toHaveURL(/#painel$/); await velho.close();
  // cada bloco abre em outra aba: o app de teste, o painel e Números (o Conteúdo abre no próprio hub)
  expect(await page.locator('.hb-bl').evaluateAll(L => L.map(a => [a.getAttribute('href'), a.getAttribute('target')]))).toEqual([['/app-teste/', '_blank'], ['/personal/#painel', '_blank'], ['/personal/#estatisticas', '_blank'], ['#conteudo', null]]);
  expect(await page.locator('a.hb-ms').evaluateAll(L => L.map(a => a.getAttribute('href')))).toEqual(['/financas/', '/personal/teste/', '/']);
  // Atualizar o site fica no hub, ao lado de Site
  await expect(page.locator('.hb-mais .hb-atu')).toContainText('Atualizar');
  expect(await page.locator('.hb-mais > *').evaluateAll(L => L.map(x => x.getAttribute('href') || x.dataset.a))).toEqual(['/financas/', '/personal/teste/', '/', 'atuGo']); // Atualizar ao lado de Site
  // modo claro e escuro (só do hub)
  await expect(page.locator('body')).not.toHaveClass(/hub-claro/);
  await page.click('[data-a="hubTema"]');
  await expect(page.locator('body')).toHaveClass(/hub-claro/);
  await expect(page.locator('[data-a="hubTema"]')).toContainText('Modo escuro');
  await page.click('[data-a="hubTema"]');
  await expect(page.locator('body')).not.toHaveClass(/hub-claro/);
  // logo do hub: "H." preto no fundo branco
  await expect(page.locator('.hb-av svg')).toHaveAttribute('aria-label', 'Hub');
  // Painel de teste abre a versão de teste (e não volta para o painel normal)
  const [tp] = await Promise.all([page.context().waitForEvent('page'), page.click('.hb-ms[data-teste]')]);
  await tp.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
  await expect(tp).toHaveURL(/\/personal\/teste\/(#.*)?$/);
  await tp.close();
  // Números abre fora das abas, com o botão de voltar ao hub
  const [nt] = await Promise.all([page.context().waitForEvent('page'), page.click('.hb-bl.lilas')]);
  await expect(nt).toHaveURL(/\/personal\/#estatisticas$/);
  await expect(nt.locator('main').first()).toContainText('Avaliação do app', { timeout: 20000 });
  expect(await nt.locator('a[aria-label="Voltar ao hub"]').getAttribute('href')).toBe('/hub/');
  // Painel abre com as abas
  const [pn] = await Promise.all([page.context().waitForEvent('page'), page.click('.hb-bl.orange')]);
  await expect(pn).toHaveURL(/\/personal\/#painel$/);
  await expect(pn.locator('#tabs')).toBeVisible();
  await expect(pn.locator('.hub')).toHaveCount(0);
});

test('Hub › Conteúdo: o post do dia vindo do Drive, com Postar, Copiar legenda e Marcar como feito', async ({ page, context }) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  // o compartilhar do celular (no robô: anota os arquivos que iriam para o Instagram/TikTok)
  await page.addInitScript(() => { navigator.canShare = () => true; navigator.share = async d => { window.__compart = (window.__compart || []).concat([d.files.map(f => [f.name, f.type, f.size])]); }; });
  await entraPainel(page);
  await page.goto(EMU + '/hub/');
  await page.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
  const bl = page.locator('.hb-bl.verde');
  await expect(bl).toContainText('Hoje no Instagram e TikTok');
  await expect(bl).toContainText('Instagram: Cardio em jejum emagrece mais?');
  await expect(bl.locator('.hb-num b')).toHaveText('2');
  await expect(bl.locator('.hb-th.ok')).toHaveCount(2); // as capas do dia
  await bl.click();
  await expect(page).toHaveURL(/\/hub\/#conteudo$/);
  await expect(page.locator('.hub.cd h1')).toHaveText('Conteúdo.');
  await expect(page.locator('.cd-d.on.hj')).toHaveCount(1); // hoje selecionado na semana
  await expect(page.locator('.cd-d')).toHaveCount(7);
  const ig = page.locator('.cd-card').nth(0), tk = page.locator('.cd-card').nth(1);
  await expect(ig.locator('.cd-chip.instagram')).toHaveText('Instagram');
  await expect(ig.locator('.cd-chip.fm')).toHaveText('Carrossel · 7 imagens');
  await expect(ig.locator('.cd-im.ok')).toHaveCount(7);
  await expect(tk.locator('.cd-chip.tiktok')).toHaveText('TikTok');
  await expect(tk.locator('h3')).toHaveText('Mito abdominal queima gordura');
  // copiar a legenda
  await ig.locator('[data-a="cdCopiar"]').click();
  await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toContain('Cardio em jejum emagrece mais?');
  // Postar o carrossel: as 7 imagens vão juntas para o compartilhar, e a legenda fica copiada
  await page.evaluate(() => navigator.clipboard.writeText(''));
  await ig.locator('[data-a="cdPostar"]').click();
  await expect.poll(() => page.evaluate(() => window.__compart && window.__compart[0].length)).toBe(7);
  expect(await page.evaluate(() => window.__compart[0][0][1])).toBe('image/jpeg');
  await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toContain('#simplificando');
  // reels: primeiro baixa o vídeo, depois "Postar agora" abre o compartilhar com o vídeo
  await tk.locator('[data-a="cdPostar"]').click();
  await expect(tk.locator('[data-a="cdPostar"]')).toContainText('Postar agora');
  await tk.locator('[data-a="cdPostar"]').click();
  await expect.poll(() => page.evaluate(() => window.__compart.length)).toBe(2);
  expect(await page.evaluate(() => window.__compart[1])).toEqual([['Reel-Mito-abdominal-queima-gordura.mp4', 'video/mp4', expect.any(Number)]]);
  // a capa do reels vai separada (para escolher como capa no Instagram/TikTok)
  await tk.locator('[data-a="cdCapa"]').click();
  await expect.poll(() => page.evaluate(() => window.__compart.length)).toBe(3);
  expect(await page.evaluate(() => window.__compart[2])).toEqual([['Carrossel-3.jpg', 'image/jpeg', expect.any(Number)]]);
  await expect(ig.locator('[data-a="cdCapa"]')).toHaveCount(0); // carrossel não tem
  // ver o vídeo por cima da tela
  await tk.locator('.cd-reel').click();
  await expect(page.locator('.cd-ver video')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('.cd-ver')).toHaveCount(0);
  // marcar como feito: fica salvo no servidor (volta igual depois de recarregar)
  await ig.locator('[data-a="cdFeito"]').click();
  await expect(ig.locator('.cd-chip.ok')).toHaveText('Postado');
  await page.reload();
  await page.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
  await expect(page.locator('.cd-card').nth(0).locator('.cd-fe')).toHaveText('Postado');
  // semana que vem: nada no Drive ainda
  await page.click('[data-a="cdSem"][data-v="1"]');
  await expect(page.locator('.cd-vz')).toContainText('Nada no Drive');
  await page.click('[data-a="cdSem"][data-v="-1"]');
  await expect(page.locator('.cd-d.on.hj')).toHaveCount(1);
  // volta ao hub: agora falta 1 para postar
  await page.click('.cd-volta');
  await expect(page.locator('.hub h1')).toHaveText('Hub.');
  await expect(page.locator('.hb-bl.verde .hb-num b')).toHaveText('1');
});

test('Hub: entrada com cara de hub, só com a senha do hub (criada pelo dono no próprio hub)', async ({ browser }) => {
  const SENHA = 'robo135790'; // senha só do robô (a senha de verdade nunca fica no código)
  const nova = async () => { const c = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }); return [c, await c.newPage()]; };
  // 1) sem a senha criada: o hub avisa e oferece o e-mail do painel
  let [ctx, page] = await nova();
  await page.goto(EMU + '/hub/'); await page.waitForSelector('#gate.gate-hub #g-hp', { timeout: 20000 });
  await expect(page.locator('#gate #g-u')).toHaveCount(0);
  await expect(page.locator('#gate h1')).toHaveText('Hub');
  await page.fill('#g-hp', SENHA); await page.click('.gate-go');
  await expect(page.locator('#g-err')).toContainText('ainda não foi criada');
  // 2) entra uma vez com o e-mail do painel e cria a senha no hub
  await page.click('[data-gate="hubMail"]'); await page.fill('#g-u', DONO.email); await page.fill('#g-p', DONO.senha); await page.click('.gate-go');
  await page.waitForFunction(() => document.getElementById('gate').hidden === true, null, { timeout: 20000 });
  await expect(page.locator('.hb-senha')).toContainText('Crie a senha do hub');
  await page.fill('#hb-s1', SENHA); await page.fill('#hb-s2', SENHA + 'x'); await page.click('[data-a="hubSenhaOk"]');
  await expect(page.locator('.hb-senha')).toContainText('não são iguais');
  await page.fill('#hb-s2', SENHA); await page.click('[data-a="hubSenhaOk"]');
  await expect(page.locator('.hb-senha')).toHaveCount(0);
  await expect(page.locator('[data-a="hubSenha"]')).toContainText('Senha do hub');
  await ctx.close();
  // 3) outro aparelho: só a senha do hub (errada não entra)
  [ctx, page] = await nova();
  await page.goto(EMU + '/hub/'); await page.waitForSelector('#g-hp', { timeout: 20000 });
  await page.fill('#g-hp', '000000'); await page.click('.gate-go'); await expect(page.locator('#g-err')).toContainText('Senha incorreta');
  await page.fill('#g-hp', SENHA); await page.click('.gate-go');
  await page.waitForFunction(() => document.getElementById('gate').hidden === true, null, { timeout: 20000 });
  await expect(page.locator('.hub h1')).toHaveText('Hub.');
  await page.waitForFunction(() => window.__painel && window.__painel.Store.loaded, null, { timeout: 20000 });
  await expect(page.locator('.hb-st').nth(0).locator('b')).toHaveText(/^[1-9]\d*$/); // vê os dados do painel (é a conta dona)
  await ctx.close();
  // 4) o painel continua com o login dele (e-mail e senha)
  [ctx, page] = await nova();
  await page.goto(EMU + '/personal/'); await page.waitForSelector('#gate #g-u', { timeout: 20000 });
  await expect(page.locator('#gate')).not.toHaveClass(/gate-hub/);
  await ctx.close();
});

test('sem internet: a internet cai no meio do treino e a aluna registra carga, marca a série e finaliza', async ({ page }) => {
  const erros = []; page.on('pageerror', e => erros.push(String(e)));
  await entraComSenha(page, { codigo, emu: true });
  await simp(page, () => { const T = window.__simp; T.setLay('lista'); const t = T.LETTERS[0]; T.ui.t = t; T.startSession(t); T.ui.sheet = null; T.renderSheet(); T.ui.tab = 'treino'; T.render(); });
  await semJanelas(page);
  const antes = await simp(page, () => window.__simp.S.history.length);
  await page.context().setOffline(true);
  const id = await simp(page, () => { const T = window.__simp; return T.PLAN[T.ui.t].ex[0].id; });
  const kg = page.locator('.tpanel .tsteps input').first();
  await kg.fill('42'); await kg.press('Tab');
  await page.evaluate(() => { const r = document.querySelector('.tpanel .slide[data-kind="tset"]').getBoundingClientRect(); window.scrollBy(0, r.top - innerHeight / 2); }); // o botão fica no meio da tela, longe da barra de abas
  await arrasta(page, '.tpanel .slide[data-kind="tset"] .slide-knob');
  expect((await simp(page, id => window.__simp.logOf(id).map(x => (x.ok ? '✓' : '·') + x.kg), id))[0]).toBe('✓42');
  await page.click('[data-a="finish"]'); await page.click('[data-a="finish"]');
  await expect.poll(() => simp(page, () => window.__simp.S.history.length)).toBe(antes + 1);
  expect(await simp(page, () => window.__simp.S.session)).toBeNull();
  expect(erros).toEqual([]);
  await page.context().setOffline(false);
});

test('sem internet desde a abertura: o app instalado abre do celular, registra a série e finaliza', async ({ browser }) => {
  const ctx = await browser.newContext({ serviceWorkers: 'allow', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'pt-BR', timezoneId: 'America/Sao_Paulo' });
  const page = await ctx.newPage(); const erros = []; page.on('pageerror', e => erros.push(String(e)));
  await entraComSenha(page, { codigo, emu: true });
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => true));
  await page.reload(); await page.waitForFunction(() => window.__simp && window.__simp.ACCT && navigator.serviceWorker.controller, null, { timeout: 20000 });
  await ctx.setOffline(true);
  await page.reload();
  await page.waitForFunction(() => window.__simp && window.__simp.ACCT, null, { timeout: 20000 });
  await page.waitForTimeout(4000); // tempo do app tentar falar com o servidor e desistir
  await simp(page, () => { const T = window.__simp; T.setLay('lista'); const t = T.LETTERS[0]; T.ui.t = t; T.startSession(t); T.ui.sheet = null; T.renderSheet(); T.ui.tab = 'treino'; T.render(); });
  await semJanelas(page);
  const antes = await simp(page, () => window.__simp.S.history.length);
  const id = await simp(page, () => { const T = window.__simp; return T.PLAN[T.ui.t].ex[0].id; });
  const kg = page.locator('.tpanel .tsteps input').first();
  await kg.fill('43'); await kg.press('Tab');
  await page.evaluate(() => { const r = document.querySelector('.tpanel .slide[data-kind="tset"]').getBoundingClientRect(); window.scrollBy(0, r.top - innerHeight / 2); }); // o botão fica no meio da tela, longe da barra de abas
  await arrasta(page, '.tpanel .slide[data-kind="tset"] .slide-knob');
  expect((await simp(page, id => window.__simp.logOf(id).map(x => (x.ok ? '✓' : '·') + x.kg), id))[0]).toBe('✓43');
  await page.click('[data-a="finish"]'); await page.click('[data-a="finish"]');
  await expect.poll(() => simp(page, () => window.__simp.S.history.length)).toBe(antes + 1);
  expect(erros).toEqual([]);
  await ctx.close();
});

test('sinal fraco (conectado, mas nada carrega): o app abre do celular, registra a série e finaliza', async ({ browser }) => {
  const ctx = await browser.newContext({ serviceWorkers: 'allow', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'pt-BR', timezoneId: 'America/Sao_Paulo' });
  const page = await ctx.newPage(); const erros = []; page.on('pageerror', e => erros.push(String(e)));
  await entraComSenha(page, { codigo, emu: true });
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => true));
  await page.reload(); await page.waitForFunction(() => window.__simp && window.__simp.ACCT && navigator.serviceWorker.controller, null, { timeout: 20000 });
  await ctx.route('**/*', () => { /* sinal fraco: nenhum pedido responde */ });
  page.reload().catch(() => {});
  await page.waitForTimeout(6000);
  await page.waitForFunction(() => window.__simp && window.__simp.ACCT, null, { timeout: 20000 });
  await page.waitForTimeout(4000); // tempo do app tentar falar com o servidor e desistir
  await simp(page, () => { const T = window.__simp; T.setLay('lista'); const t = T.LETTERS[0]; T.ui.t = t; T.startSession(t); T.ui.sheet = null; T.renderSheet(); T.ui.tab = 'treino'; T.render(); });
  await semJanelas(page);
  const antes = await simp(page, () => window.__simp.S.history.length);
  const id = await simp(page, () => { const T = window.__simp; return T.PLAN[T.ui.t].ex[0].id; });
  const kg = page.locator('.tpanel .tsteps input').first();
  await kg.fill('44'); await kg.press('Tab');
  await page.evaluate(() => { const r = document.querySelector('.tpanel .slide[data-kind="tset"]').getBoundingClientRect(); window.scrollBy(0, r.top - innerHeight / 2); }); // o botão fica no meio da tela, longe da barra de abas
  await arrasta(page, '.tpanel .slide[data-kind="tset"] .slide-knob');
  expect((await simp(page, id => window.__simp.logOf(id).map(x => (x.ok ? '✓' : '·') + x.kg), id))[0]).toBe('✓44');
  await page.click('[data-a="finish"]'); await page.click('[data-a="finish"]');
  await expect.poll(() => simp(page, () => window.__simp.S.history.length)).toBe(antes + 1);
  expect(erros).toEqual([]);
  await ctx.close();
});
