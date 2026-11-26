// Ajudantes do robô de testes: abrir o app/painel, entrar, cadastrar, capturar os avisos que iriam para o servidor.
// Cada teste roda num navegador limpo. Tudo que é de fora do computador de teste é bloqueado (inclusive o Firebase real).
const { test: base, expect } = require('@playwright/test');

const LOCAL = 'http://127.0.0.1:4501';   // sem Firebase (o app funciona só no aparelho)
const EMU = 'http://127.0.0.1:4502';     // Firebase de mentira (emulador)
const UA = {
  android: 'Mozilla/5.0 (Linux; Android 14; SM-S921B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36',
  iphone: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1',
};
// as fontes do Google viram as cópias da pasta fontes/ (as fotos saem iguais em qualquer computador)
const FONTES = [['Inter', 400, 'Regular'], ['Inter', 500, 'Medium'], ['Inter', 600, 'SemiBold'], ['Inter', 700, 'Bold'], ['Inter', 800, 'ExtraBold'], ['Inter', 900, 'Black'],
  ['JetBrains Mono', 600, 'SemiBold'], ['JetBrains Mono', 800, 'ExtraBold']]
  .map(([f, w, n]) => `@font-face{font-family:'${f}';font-style:normal;font-weight:${w};font-display:block;src:url(http://127.0.0.1:4501/__fontes/${f.replace(' ', '')}-${n}.woff2) format('woff2')}`).join('\n');

async function prepara(context) {
  await context.route(/^https?:\/\/(?!127\.0\.0\.1[:/])/, route => {
    const u = route.request().url();
    if (/fonts\.googleapis\.com\/css2/.test(u)) return route.fulfill({ contentType: 'text/css', body: FONTES });
    return route.abort(); // nada sai do computador de teste: nem Firebase real, nem Google, nem GitHub
  });
}

// No painel, as caixinhas das abas do aluno começam minimizadas. O robô abre todas (a não ser que o teste peça o contrário), para os testes antigos acharem os campos.
const ABRE_CX = () => { try { const k = 'simplificando-admin-ui', v = JSON.parse(localStorage.getItem(k) || '{}') || {}; if (v.cxAbertas == null) { v.cxAbertas = true; localStorage.setItem(k, JSON.stringify(v)); } } catch (e) { /* sem armazenamento */ } };
const test = base.extend({
  browser: [async ({ browser }, use) => { const novo = browser.newContext.bind(browser); browser.newContext = async (...a) => { const c = await novo(...a); await c.addInitScript(ABRE_CX); return c; }; await use(browser); }, { scope: 'worker' }],
  context: async ({ context }, use) => { await context.addInitScript(ABRE_CX); await prepara(context); await use(context); },
  page: async ({ page }, use) => {
    const erros = [];
    page.on('pageerror', e => erros.push('erro: ' + e.message));
    page.on('console', m => { if (m.type() !== 'error') return; const t = m.text(); if (!/Failed to load resource|net::ERR|ERR_FAILED|@firebase\/firestore|Could not reach Cloud Firestore/.test(t)) erros.push('console: ' + t); });
    page.erros = erros;
    await use(page);
    if (!page.aceitaErros) expect(erros, 'o app não pode ter erro escondido no console').toEqual([]);
  },
});

// ---------- app do aluno ----------
const simp = (page, fn, arg) => page.evaluate(fn, arg);
async function abreApp(page, { emu = false, hash = '' } = {}) {
  await page.goto((emu ? EMU : LOCAL) + '/treino/' + hash);
  await page.waitForFunction(() => !!window.__simp, null, { timeout: 15000 });
}
async function semJanelas(page) {
  await page.waitForFunction(() => !document.querySelector('.wel'), null, { timeout: 15000 }).catch(() => {});
  await simp(page, () => { const k = document.getElementById('pick'); if (k) k.remove(); document.body.classList.remove('locked'); const T = window.__simp; if (T.ui.sheet) T.closeSheet(); T.ui.sheet = null; T.renderSheet(); });
}
// aluno com personal de exemplo (Lucas, senha 482913) no modo local
async function entraComSenha(page, { codigo = '482913', layout = 'lista', emu = false } = {}) {
  await abreApp(page, { emu });
  await page.fill('#lg-pw', codigo); await page.keyboard.press('Enter');
  await page.waitForSelector('[data-a="lg-ck"]', { timeout: 15000 });
  const n = await page.locator('[data-a="lg-ck"]').count();
  for (let i = 0; i < n; i++) await page.locator('[data-a="lg-ck"]').nth(i).click();
  await page.click('[data-a="lg-ac-ok"]');
  await page.waitForFunction(() => window.__simp.ACCT && !document.getElementById('login'), null, { timeout: 15000 });
  if (layout) await simp(page, l => window.__simp.setLay(l), layout);
  await semJanelas(page);
}
// cadastro de quem treina sem personal (IA monta o treino)
async function criaConta(page, { nome = 'Joana Lima', email, senha = '135790', objetivo = 'emagrecer', peso = '68', modo = 'ia', emu = false } = {}) {
  email = email || 'robo' + Date.now() + Math.floor(Math.random() * 1e4) + '@teste.com';
  await abreApp(page, { emu });
  await page.click('[data-a="lg-self"]');
  await page.fill('#su-nome', nome); await page.fill('#su-email', email); await page.fill('#su-senha', senha);
  await page.click('[data-a="su-ck"][data-v="all"]'); // um aceite só
  await page.click('.su-go');
  await page.waitForSelector('[data-a="su-sexo"]', { timeout: 15000 });
  await page.click(`[data-a="su-ed"][data-v="${objetivo}"]`);
  if (peso) await page.fill('#su-peso', peso);
  await page.click('[data-a="su-next"]');
  await page.click(`[data-a="su-mode"][data-v="${modo}"]`);
  const passo4 = async () => { await page.waitForSelector('[data-a="su-inst-x"]', { timeout: 15000 }); await page.click('[data-a="su-inst-x"]'); }; // passo 4 de 4 (Instale o app): segue no navegador
  if (modo === 'ia') {
    for (let i = 0; i < 10; i++) {
      if (await page.$('[data-a="su-done"]')) break;
      const o = await page.$('#login [data-a^="iq-"]:not(.on)'); if (o) await o.click().catch(() => {});
      const nx = await page.$('[data-a="su-next"]'); if (nx) await nx.click().catch(() => {});
      await page.waitForTimeout(250);
    }
    await page.waitForSelector('[data-a="su-done"]', { timeout: 15000 });
    await page.click('[data-a="su-done"]');
  }
  await passo4();
  await page.waitForFunction(() => window.__simp.ACCT && !document.getElementById('login'), null, { timeout: 20000 });
  return { email, senha };
}
// guarda o que o app mandaria para o push.php (avisos no celular), sem mandar nada de verdade
async function capturaAvisos(page) {
  const L = [];
  await page.route('**/push.php*', async route => {
    const u = new URL(route.request().url()), acao = u.searchParams.get('acao');
    let corpo = null; try { corpo = JSON.parse(route.request().postData() || 'null'); } catch (e) {}
    L.push({ acao, corpo });
    route.fulfill({ contentType: 'application/json', body: JSON.stringify(acao === 'chave' ? { ok: true, pub: 'BOr' } : { ok: true }) });
  });
  return L;
}
// liga os "avisos no celular" de mentira (o navegador de teste não tem notificação de verdade)
const ligaAvisos = page => simp(page, () => { window.__simpPush = true; const T = window.__simp; T.PREF.pushOn = true; T.PREF.pushId = 'robo1234567890ab'; });

// arrasta o botão redondo de um "arraste para…" (começar treino, série feita, salvar cardio) até o fim
async function arrasta(page, sel) {
  await page.waitForTimeout(300); // a tela pode estar se redesenhando (ex.: depois de digitar o peso)
  const k = page.locator(sel).first(); await k.waitFor(); await k.scrollIntoViewIfNeeded();
  const kb = await k.boundingBox(), sl = await k.locator('xpath=ancestor::*[contains(@class,"slide")][1]').boundingBox();
  await page.mouse.move(kb.x + kb.width / 2, kb.y + kb.height / 2); await page.mouse.down();
  for (let i = 1; i <= 12; i++) { await page.mouse.move(kb.x + kb.width / 2 + sl.width * i / 12, kb.y + kb.height / 2); await page.waitForTimeout(15); }
  await page.mouse.up(); await page.waitForTimeout(700);
}
// lê o Firebase de teste (emulador) com o acesso de administrador do próprio emulador
async function fsLista(colecao) {
  const r = await (await fetch('http://127.0.0.1:8080/v1/projects/demo-simplificando/databases/(default)/documents/' + colecao + '?pageSize=300', { headers: { Authorization: 'Bearer owner' } })).json();
  return (r.documents || []).map(d => ({ id: d.name.split('/').pop(), f: d.fields || {} }));
}
const aba = (page, v) => page.click(`.tabbar [data-v="${v}"]`);
// dedo arrastando na tela (toque de verdade: touchstart/touchend), de (x0,y0) até (x1,y1)
async function desliza(page, x0, y0, x1, y1) {
  await page.evaluate(([x0, y0, x1, y1]) => {
    const el = document.elementFromPoint(x0, y0) || document.body, t = (x, y) => new Touch({ identifier: 1, target: el, clientX: x, clientY: y });
    el.dispatchEvent(new TouchEvent('touchstart', { bubbles: true, touches: [t(x0, y0)], changedTouches: [t(x0, y0)] }));
    el.dispatchEvent(new TouchEvent('touchend', { bubbles: true, touches: [], changedTouches: [t(x1, y1)] }));
  }, [x0, y0, x1, y1]);
  await page.waitForTimeout(250);
}
module.exports = { desliza, fsLista, arrasta, aba, test, expect, LOCAL, EMU, UA, simp, abreApp, semJanelas, entraComSenha, criaConta, capturaAvisos, ligaAvisos };
