// Robô de testes do Simplificando. Rodar: cd testes && npm test   (sobe o Firebase de mentira e roda tudo)
const { defineConfig } = require('@playwright/test');
const { UA } = require('./apoio');
const CI = !!process.env.CI;

module.exports = defineConfig({
  testDir: '.',
  testMatch: '*.spec.js',
  outputDir: 'resultados',
  timeout: 60000,
  expect: { timeout: 10000, toHaveScreenshot: { maxDiffPixelRatio: 0.02, animations: 'disabled', caret: 'hide' } },
  fullyParallel: true,
  workers: CI ? 3 : 4,
  retries: CI ? 1 : 0,
  reporter: [['list'], ['html', { outputFolder: 'relatorio', open: 'never' }]],
  snapshotPathTemplate: '{testDir}/fotos/{arg}{ext}',
  use: {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
    userAgent: UA.android,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    serviceWorkers: 'block',
    trace: 'off',
    screenshot: 'only-on-failure',
    // no computador do Claude o navegador já vem instalado; no GitHub o Playwright instala o dele
    launchOptions: process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {},
  },
  webServer: { command: 'node servidor.js', url: 'http://127.0.0.1:4501/treino/', reuseExistingServer: !CI, timeout: 20000 },
});
