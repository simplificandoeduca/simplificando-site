// 🔔 Servidor de avisos (site/push.php) e de erros (site/erros.php), testados com o PHP de verdade numa pasta temporária.
// Nada é mandado para o Google/Apple: com SIMP_TESTE_PUSH, o push.php só anota o que mandaria.
const { test, expect } = require('./apoio');
const { spawn, execFileSync } = require('child_process');
const fs = require('fs'), path = require('path'), os = require('os');
test.describe.configure({ mode: 'serial' });

const DIR = fs.mkdtempSync(path.join(os.tmpdir(), 'simp-php-')), SITE = path.join(DIR, 'site'), LOG = path.join(DIR, 'enviados.log');
const PORTA = 4511, URL0 = `http://127.0.0.1:${PORTA}/`;
let srv;
const ENV = { ...process.env, SIMP_TESTE_PUSH: LOG, PHP_CLI_SERVER_WORKERS: '4' };
const post = (acao, corpo, extra = {}) => fetch(URL0 + 'push.php?acao=' + acao, { method: 'POST', body: JSON.stringify(corpo), ...extra }).then(r => r.json().then(j => ({ st: r.status, j })));
const enviados = () => fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').trim().split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
const limpaLog = () => { try { fs.unlinkSync(LOG); } catch (e) {} };
const cron = () => JSON.parse(execFileSync('php', ['push.php', 'cron'], { cwd: SITE, env: ENV }).toString().trim().split('\n').pop());
const arq = n => JSON.parse(fs.readFileSync(path.join(DIR, n), 'utf8'));
const EP = n => 'https://fcm.googleapis.com/fcm/send/robo-' + n;
// hora local de quem tem fuso UTC-3 (tz = 180), como o app manda
const local = () => { const d = new Date(Date.now() - 180 * 60e3); return { min: d.getUTCHours() * 60 + d.getUTCMinutes(), wd: d.getUTCDay(), dia: d.toISOString().slice(0, 10) }; };
const diaMais = n => new Date(Date.now() - 180 * 60e3 + n * 864e5).toISOString().slice(0, 10);

test.beforeAll(async () => {
  fs.mkdirSync(path.join(SITE, 'treino'), { recursive: true });
  for (const f of ['push.php', 'dono.inc.php', 'erros.php', 'hub-login.php']) fs.copyFileSync(path.join(__dirname, '..', 'site', f), path.join(SITE, f));
  fs.writeFileSync(path.join(SITE, 'treino', 'firebase-config.js'), 'window.SIMPLIFICANDO_FIREBASE = { apiKey: "demo-key", projectId: "demo-simplificando" };');
  srv = spawn('php', ['-S', '127.0.0.1:' + PORTA, '-t', SITE], { env: ENV, stdio: 'ignore' });
  for (let i = 0; i < 50; i++) { try { await fetch(URL0 + 'push.php?acao=chave'); break; } catch (e) { await new Promise(r => setTimeout(r, 100)); } }
});
test.afterAll(() => { if (srv) srv.kill(); });

test('chave pública e inscrição do celular (aceita só serviço de aviso conhecido)', async () => {
  const k = await (await fetch(URL0 + 'push.php?acao=chave')).json();
  expect(k.pub).toMatch(/^[A-Za-z0-9_-]{80,}$/);
  expect((await post('assinar', { id: 'celular00000001', sub: { endpoint: 'https://exemplo.com/x' } })).st).toBe(400);
  expect((await post('assinar', { id: 'celular00000001', sub: { endpoint: EP(1) }, ini: '00:00', fim: '23:59', cada: 30, tz: 180, ag: 1 })).j.ok).toBe(true);
  expect(arq('.simplificando-push.json').celular00000001).toMatchObject({ e: EP(1), ag: 1, cada: 30 });
});

test('lembrete de água: o agendamento manda no horário; com o lembrete desligado, não manda', async () => {
  limpaLog();
  await post('assinar', { id: 'celular00000002', sub: { endpoint: EP(2) }, ini: '00:00', fim: '23:59', cada: 30, tz: 180, ag: 0 });
  cron();
  const e = enviados().map(x => x.e);
  expect(e).toContain(EP(1)); expect(e).not.toContain(EP(2));
});

test('fim do descanso: responde na hora, espera e avisa; cancelado, não avisa', async () => {
  limpaLog();
  const t0 = Date.now();
  const r = await post('descanso', { id: 'celular00000001', fim: Date.now() + 2000, k: 'abc123' });
  expect(r.j.ok).toBe(true); expect(Date.now() - t0).toBeLessThan(1500);
  await expect.poll(() => enviados().filter(x => x.urg === 'high').length, { timeout: 8000 }).toBe(1);
  limpaLog();
  await post('descanso', { id: 'celular00000001', fim: Date.now() + 2000, k: 'def456' });
  await post('descanso', { id: 'celular00000001', fim: 0, k: '' });
  await new Promise(r => setTimeout(r, 3500));
  expect(enviados()).toHaveLength(0);
});

test('treino esquecido: avisa uma vez com 3 h de treino aberto', async () => {
  limpaLog();
  await post('treino', { id: 'celular00000002', ini: Date.now() - 3 * 3600e3 - 60e3 });
  cron(); cron();
  expect(enviados().filter(x => x.e === EP(2))).toHaveLength(1);
});

test('lembrete do treino do dia: não manda na 1ª semana, nem antes da hora, nem se já treinou', async () => {
  const L = local();
  test.skip(L.min < 20 || L.min > 1380, 'perto da meia-noite o "10 min antes" cai no outro dia');
  const lb = extra => post('lembrete', { id: 'celular00000002', on: 1, h: 1200, hs: { [L.wd]: L.min - 10 }, dias: [L.wd], feito: '', pausa: '', desde: diaMais(-1), ...extra });
  const conta = () => enviados().filter(x => x.e === EP(2)).length;
  // reinscreve (zera "já avisou hoje")
  const reset = async () => { const v = arq('.simplificando-push.json'); delete v.celular00000002.lbav; fs.writeFileSync(path.join(DIR, '.simplificando-push.json'), JSON.stringify(v)); limpaLog(); };
  await reset(); await lb({ desde: diaMais(3) }); cron(); expect(conta()).toBe(0);
  await reset(); await lb({ hs: { [L.wd]: Math.min(1439, L.min + 40) } }); cron(); expect(conta()).toBe(0);
  await reset(); await lb({ feito: L.dia }); cron(); expect(conta()).toBe(0);
  await reset(); await lb({}); cron(); cron(); expect(conta()).toBe(1);
});

test('conta nova: avisa o celular do dono, com limite contra abuso; o nome só sai com a chave', async () => {
  limpaLog();
  fs.writeFileSync(path.join(DIR, '.simplificando-dono-push.json'), JSON.stringify({ donocelular0001: { e: EP('dono'), k: 'k'.repeat(32), em: 1 } }));
  expect((await post('novo', { nome: 'Joana Lima', ed: 'emagrecer', plano: 'ia' })).j.ok).toBe(true);
  expect(enviados().filter(x => x.e === EP('dono'))).toHaveLength(1);
  const n = await (await fetch(URL0 + 'push.php?acao=novos&k=' + 'k'.repeat(32))).json();
  expect(n.novos.map(x => x.nome)).toContain('Joana Lima');
  expect((await fetch(URL0 + 'push.php?acao=novos&k=errada')).status).toBe(403);
  for (let i = 0; i < 4; i++) await post('novo', { nome: 'Spam ' + i, ed: 'hipertrofia', plano: 'meu' });
  expect(arq('.simplificando-novos.json').novos.filter(x => /Spam/.test(x.nome)).length).toBe(2); // 3 por internet a cada 10 min
});

test('só o dono liga o aviso de conta nova', async () => {
  const r = await post('dono-assinar', { id: 'abcdefghijkl123', sub: { endpoint: EP(9) } });
  expect(r.st).toBe(403);
});

test('erros do app: qualquer celular registra; só o dono vê', async () => {
  const r = await fetch(URL0 + 'erros.php?acao=registrar', { method: 'POST', body: JSON.stringify({ msg: 'teste do robô', src: 'app', line: 1 }) });
  expect((await r.json()).ok).toBe(true);
  expect((await fetch(URL0 + 'erros.php?acao=ver')).status).toBe(403);
});

test('senha do hub (hub-login.php): só o dono cria; senha errada não entra; muitas tentativas bloqueiam', async () => {
  const CH = path.join(DIR, 'simplificando-hub-senha.txt'), TT = path.join(DIR, '.simplificando-hub-tentativas.json');
  const hub = (acao, campos = {}) => { const f = new FormData(); Object.entries(campos).forEach(([k, v]) => f.append(k, v)); return fetch(URL0 + 'hub-login.php?acao=' + acao, { method: 'POST', body: f }).then(r => r.json().then(j => ({ st: r.status, j }))); };
  expect((await (await fetch(URL0 + 'hub-login.php?acao=ver')).json()).tem).toBe(false);
  expect((await hub('entrar', { senha: 'robo-abc123' })).j.semSenha).toBe(true);
  expect((await hub('definir', { senha: 'robo-abc123' })).st).toBe(403); // sem o login do dono
  expect(fs.existsSync(CH)).toBe(false);
  // a senha fica só embaralhada (password_hash), fora do site
  fs.writeFileSync(CH, execFileSync('php', ['-r', 'echo password_hash("robo-abc123", PASSWORD_DEFAULT);']).toString());
  expect(fs.readFileSync(CH, 'utf8')).not.toContain('robo-abc123');
  expect((await (await fetch(URL0 + 'hub-login.php?acao=ver')).json()).tem).toBe(true);
  // senha certa passa da conferência (aqui para na chave do Firebase, que o robô não tem)
  expect((await hub('entrar', { senha: 'robo-abc123' })).j.semServidor).toBe(true);
  for (let i = 0; i < 6; i++) expect((await hub('entrar', { senha: 'errada' + i })).st).toBe(401);
  expect((await hub('entrar', { senha: 'robo-abc123' })).st).toBe(429); // 6 erros na hora: bloqueia até a certa
  fs.unlinkSync(CH); fs.unlinkSync(TT);
});
