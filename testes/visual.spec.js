// 📸 Fotos das telas principais (Android e iPhone, claro e escuro) comparadas com as fotos guardadas em testes/fotos/.
// Se uma tela mudar sem querer, o robô avisa (e mostra a diferença no relatório). Mudou de propósito? Rode: npm run fotos
const { test, expect, LOCAL, UA, simp, entraComSenha, semJanelas } = require('./apoio');

// as fotos guardadas são tiradas no GitHub (mesmo computador da comparação). Fora dele, só compara se pedir: FOTOS=1 npx playwright test visual
test.skip(!process.env.CI && !process.env.FOTOS, 'as fotos são comparadas no GitHub');
const DIA = new Date('2026-11-16T10:00:00-03:00'); // sempre o mesmo dia e hora nas fotos
const APARELHOS = [['android', UA.android], ['iphone', UA.iphone]];
const TEMAS = ['light', 'dark'];
// as partes que se mexem (animações, relógios) ficam cobertas; as mensagens rápidas (toasts) ficam escondidas
async function tira(page, nome) {
  await page.addStyleTag({ content: '.toast,.utoast,#fx{display:none!important}' });
  await expect(page).toHaveScreenshot(nome + '.png', { mask: [page.locator('canvas, .lay-anim, .anim, [data-tick], .ver-pill, .app-ver')], maskColor: '#888888' });
}

for (const [ap, ua] of APARELHOS) for (const tema of TEMAS) {
  test.describe(`${ap} · ${tema === 'light' ? 'claro' : 'escuro'}`, () => {
    test.use({ userAgent: ua, colorScheme: tema, deviceScaleFactor: 1 });
    test.beforeEach(async ({ page }) => {
      await page.clock.setFixedTime(DIA);
      // como o aluno usa: app instalado na tela do celular (sem a janela "Baixe nosso app")
      await page.addInitScript(() => { const mm = window.matchMedia.bind(window); window.matchMedia = q => /standalone/.test(q) ? { matches: true, media: q, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} } : mm(q); });
    });

    test('login', async ({ page }) => {
      await page.goto(LOCAL + '/treino/'); await page.waitForSelector('#lg-pw');
      await tira(page, `${ap}-${tema}-1-login`);
    });

    test('telas do aluno: Hoje, Treino, Cardio, Água, Evolução e Perfil', async ({ page }) => {
      await entraComSenha(page);
      await simp(page, () => { const T = window.__simp; T.PREF.cardioLayout = 'toque'; T.S.aval = { ult: T.today() }; T.save(); }); // avaliação de 15 dias já respondida
      for (const [i, tab] of [['2', 'hoje'], ['3', 'treino'], ['4', 'cardio'], ['5', 'agua'], ['6', 'evo']]) {
        await simp(page, t => { const T = window.__simp; T.ui.tab = t; T.render(); window.scrollTo(0, 0); }, tab);
        await page.waitForTimeout(250);
        await tira(page, `${ap}-${tema}-${i}-${tab}`);
      }
      await simp(page, () => { const T = window.__simp; T.ui.sheet = { k: 'prof' }; T.renderSheet(); });
      await page.waitForTimeout(500);
      await tira(page, `${ap}-${tema}-7-perfil`);
    });
  });
}

test.describe('painel do personal (computador)', () => {
  test.use({ viewport: { width: 1280, height: 860 }, isMobile: false, hasTouch: false, userAgent: undefined, deviceScaleFactor: 1 });
  test.beforeEach(async ({ page }) => { await page.clock.setFixedTime(DIA); });
  test('Painel, Alunos e Evolução de um aluno', async ({ page }) => {
    await page.addInitScript(() => { try { sessionStorage.setItem('simp-teste-ok', '1'); } catch (e) {} });
    await page.goto(LOCAL + '/personal/teste/');
    await page.waitForFunction(() => window.__painel && window.__painel.Store.loaded);
    await page.click('[data-a="demoSeed"]');
    await page.waitForFunction(() => Object.keys(window.__painel.C.alunos).length >= 4);
    for (const [n, h] of [['8-painel', '#painel'], ['9-alunos', '#alunos']]) {
      await page.evaluate(h => { location.hash = h; }, h); await page.waitForTimeout(400);
      await tira(page, 'painel-' + n);
    }
  });
});
