// 🔔 Servidor de avisos (site/push.php) e de erros (site/erros.php), testados com o PHP de verdade numa pasta temporária.
// Nada é mandado para o Google/Apple: com SIMP_TESTE_PUSH, o push.php só anota o que mandaria.
const { test, expect } = require('./apoio');
const { spawn, execFileSync } = require('child_process');
const fs = require('fs'), path = require('path'), os = require('os');
test.describe.configure({ mode: 'serial' });

const DIR = fs.mkdtempSync(path.join(os.tmpdir(), 'simp-php-')), SITE = path.join(DIR, 'site'), LOG = path.join(DIR, 'enviados.log');
const PORTA = 4511, URL0 = `http://127.0.0.1:${PORTA}/`;
let srv;
const DONO_TK = 'robo-dono-' + 'x'.repeat(40), DRIVE = path.join(DIR, 'drive.json');
const ENV = { ...process.env, SIMP_TESTE_PUSH: LOG, PHP_CLI_SERVER_WORKERS: '4', SIMP_TESTE_DRIVE: DRIVE, SIMP_TESTE_DONO: DONO_TK };
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
  for (const f of ['push.php', 'dono.inc.php', 'erros.php', 'hub-login.php', 'lembretes.php', 'email.inc.php', 'conteudo.php', 'conta-excluir.php']) fs.copyFileSync(path.join(__dirname, '..', 'site', f), path.join(SITE, f));
  fs.writeFileSync(path.join(SITE, 'treino', 'firebase-config.js'), 'window.SIMPLIFICANDO_FIREBASE = { apiKey: "demo-key", projectId: "demo-simplificando" };');
  fs.writeFileSync(DRIVE, '{}');
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

test('lembrete para voltar (celular): 1 dia sem o 1º treino, 4 e 10 dias sem treinar; uma vez por etapa, a partir das 18h30', async () => {
  const cronEm = ep => execFileSync('php', ['push.php', 'cron'], { cwd: SITE, env: { ...ENV, SIMP_AGORA: String(ep) } });
  const d0 = new Date(Date.now() - 180 * 60e3), Y = d0.getUTCFullYear(), M = d0.getUTCMonth(), D = d0.getUTCDate();
  const em = (h, m) => Math.floor(Date.UTC(Y, M, D, h + 3, m) / 1000); // hora local de quem tem fuso UTC-3
  const dia = n => new Date(Date.UTC(Y, M, D + n)).toISOString().slice(0, 10);
  expect((await post('assinar', { id: 'celular00000003', sub: { endpoint: EP(3) }, ini: '08:00', fim: '22:00', cada: 30, tz: 180, ag: 0 })).j.ok).toBe(true);
  const vt = x => post('lembrete', { id: 'celular00000003', on: 0, h: 1080, hs: {}, dias: [], feito: '', pausa: '', desde: '', vt: x });
  const conta = () => enviados().filter(x => x.e === EP(3)).length;
  limpaLog();
  await vt({ on: 1, cad: dia(-1), ult: '' });
  cronEm(em(17, 0)); expect(conta()).toBe(0);                  // antes das 18h30
  cronEm(em(18, 40)); cronEm(em(18, 50)); expect(conta()).toBe(1); // 1 dia sem o 1º treino: um aviso só
  await vt({ on: 1, cad: dia(-30), ult: dia(-5) }); cronEm(em(19, 0)); cronEm(em(19, 10)); expect(conta()).toBe(2);  // 4+ dias sem treinar
  await vt({ on: 1, cad: dia(-30), ult: dia(-11) }); cronEm(em(19, 20)); expect(conta()).toBe(3);                   // 10+ dias
  await vt({ on: 1, cad: dia(-30), ult: dia(-1) }); cronEm(em(19, 30)); expect(conta()).toBe(3);                    // treinou ontem: nada
  await vt({ on: 0, cad: dia(-30), ult: dia(-20) }); cronEm(em(19, 40)); expect(conta()).toBe(3);                   // desligado
  cronEm(em(22, 0)); expect(conta()).toBe(3);
});

test('lembretes por e-mail: 1 dia sem o 1º treino, 4 e 10 dias sem treinar; uma vez por etapa; quem desligou, conta interna e o dono não recebem', async () => {
  const LOGE = path.join(DIR, 'emails.log'), FX = path.join(DIR, 'contas.json');
  const agora = Math.floor(Date.now() / 1000), dia = n => new Date((agora - 3 * 3600 + n * 86400) * 1000).toISOString().slice(0, 10);
  const c = (uid, nome, email, cad, treinos) => ({ uid, nome, email, cad, treinos, plano: { A: 'Inferiores', B: 'Empurrar', C: 'Puxar' } });
  fs.writeFileSync(FX, JSON.stringify({ dono: 'donoUid1', contas: [
    c('novaUid01', 'Joana Lima', 'joana@teste.com', dia(-1), []),
    c('quatroUid1', 'Bia', 'bia@teste.com', dia(-30), [{ d: dia(-5), t: 'C', min: 48 }]),
    c('dezUid0001', 'Caio', 'caio@teste.com', dia(-40), [{ d: dia(-12), t: 'A', min: 50 }]),
    c('ontemUid01', 'Davi', 'davi@teste.com', dia(-40), [{ d: dia(-1), t: 'A', min: 50 }]),
    c('internaUid', 'Eva', 'a123456@alunos.demo.firebaseapp.com', dia(-3), []),
    c('donoUid1', 'Dono', 'dono@teste.com', dia(-3), []),
    c('sairUid001', 'Fia', 'fia@teste.com', dia(-2), []) ] }));
  const roda = () => JSON.parse(execFileSync('php', ['lembretes.php', 'cron'], { cwd: SITE, env: { ...ENV, SIMP_TESTE_PUSH: LOGE, SIMP_TESTE_LEMB: FX, SIMP_AGORA: String(agora) } }).toString().trim());
  const lidos = () => fs.existsSync(LOGE) ? fs.readFileSync(LOGE, 'utf8').trim().split('\n').filter(Boolean).map(l => JSON.parse(l)) : [];
  // quem desligou pelo link do e-mail (link certo; o errado não desliga)
  roda(); fs.unlinkSync(LOGE); fs.unlinkSync(path.join(DIR, '.simplificando-lembretes.json'));
  const seg = fs.readFileSync(path.join(DIR, '.simplificando-lembretes-segredo'), 'utf8').trim();
  const tk = require('crypto').createHmac('sha256', seg).update('sair:sairUid001').digest('hex').slice(0, 32);
  expect(await (await fetch(URL0 + 'lembretes.php?acao=sair&u=sairUid001&t=errado')).text()).toContain('Link inválido');
  expect(await (await fetch(URL0 + 'lembretes.php?acao=sair&u=sairUid001&t=' + tk)).text()).toContain('lembretes desligados');
  expect((await fetch(URL0 + 'lembretes.php?acao=pref', { method: 'POST' })).status).toBe(403); // a chave do app: só com o login da pessoa
  const r = roda();
  expect(r.enviados).toBe(3);
  const L = lidos(), por = Object.fromEntries(L.map(x => [x.email, x]));
  expect(Object.keys(por).sort()).toEqual(['bia@teste.com', 'caio@teste.com', 'joana@teste.com']);
  expect(por['joana@teste.com']).toMatchObject({ etapa: 'n1', assunto: 'Joana, seu treino já está pronto 💪' });
  expect(por['bia@teste.com']).toMatchObject({ etapa: 't4', assunto: 'Bia, bora voltar? 👀' });
  expect(por['caio@teste.com']).toMatchObject({ etapa: 't10', assunto: 'Caio, vamos recomeçar? 🔥' });
  expect(roda().enviados).toBe(0); // uma vez por etapa
});

test('Conteúdo do hub (conteudo.php): lê as pastas do Drive por dia, só para o dono, e só entrega arquivos das pastas de posts', async () => {
  const b = x => Buffer.from(x).toString('base64'), F = 'application/vnd.google-apps.folder';
  const dm = k => k.slice(8) + '/' + k.slice(5, 7), hoje = diaMais(0), amanha = diaMais(1);
  const jpg = fs.readFileSync(path.join(__dirname, '..', 'site', 'img', 'post', 'cardio-jejum-1.jpg'));
  const D = {
    rIG: { nome: 'Instagram', mime: F }, rTK: { nome: 'TikTok', mime: F }, outra: { nome: 'Fotos da academia', mime: F },
    dIG: { nome: dm(hoje), mime: F, pai: 'rIG' }, fix: { nome: 'Fixados', mime: F, pai: 'rIG' },
    capa: { nome: 'Capa-Mito-abdominal-queima-gordura.jpg', mime: 'image/jpeg', pai: 'dIG', dados: jpg.toString('base64') },
    reel: { nome: 'Reel-Mito-abdominal-queima-gordura.mp4', mime: 'video/mp4', pai: 'dIG', dados: b('video-de-teste') },
    leg: { nome: 'Legendas-Emagrecer.txt', mime: 'text/plain', pai: 'dIG', dados: b('﻿Abdominal não queima gordura 🔥\r\n\r\n#simplificando') },
    dTK: { nome: dm(amanha) + ' - Você na academia usando o app (15s)', mime: F, pai: 'rTK' },
    c2: { nome: 'Carrossel-2.jpg', mime: 'image/jpeg', pai: 'dTK', dados: b('2') }, c10: { nome: 'Carrossel-10.jpg', mime: 'image/jpeg', pai: 'dTK', dados: b('10') }, c1: { nome: 'Carrossel-1.jpg', mime: 'image/jpeg', pai: 'dTK', dados: b('1') },
    fora: { nome: 'segredo.jpg', mime: 'image/jpeg', pai: 'outra', dados: b('x') },
  };
  fs.writeFileSync(DRIVE, JSON.stringify(D));
  const H = { Authorization: 'Bearer ' + DONO_TK };
  const sem = (h = H) => fetch(URL0 + 'conteudo.php?acao=semana&ini=' + hoje, { headers: h }).then(r => r.json().then(j => ({ st: r.status, j })));
  expect((await sem({})).st).toBe(403);                                        // sem login
  expect((await sem({ Authorization: 'Bearer ' + 'y'.repeat(60) })).st).toBe(403); // login de outra pessoa
  const { j } = await sem();
  expect(j.ok).toBe(true);
  expect(Object.keys(j.dias)).toHaveLength(7);
  const ig = j.dias[hoje][0];
  expect(ig).toMatchObject({ plat: 'instagram', tipo: 'reels', tema: 'Mito abdominal queima gordura', legenda: 'Abdominal não queima gordura 🔥\n\n#simplificando' });
  expect(ig.capa.id).toBe('capa'); expect(ig.midia.map(f => f.id)).toEqual(['reel']);
  const tk = j.dias[amanha][0];
  expect(tk).toMatchObject({ plat: 'tiktok', tipo: 'carrossel', tema: 'Você na academia usando o app (15s)', legenda: '' });
  expect(tk.midia.map(f => f.nome)).toEqual(['Carrossel-1.jpg', 'Carrossel-2.jpg', 'Carrossel-10.jpg']); // na ordem certa
  // arquivos: a capa e o vídeo saem; a legenda (.txt) e o que está fora das pastas de posts, não
  const arqv = (id, h = H) => fetch(URL0 + 'conteudo.php?acao=arquivo&id=' + id, { headers: h });
  const rc = await arqv('capa'); expect(rc.status).toBe(200); expect(rc.headers.get('content-type')).toBe('image/jpeg'); expect(Buffer.from(await rc.arrayBuffer()).equals(jpg)).toBe(true);
  expect((await arqv('reel')).headers.get('content-type')).toBe('video/mp4');
  expect((await arqv('leg')).status).toBe(404);
  expect((await arqv('fora')).status).toBe(404);
  expect((await arqv('capa', {})).status).toBe(403);
  // marcar como postado
  const fe = (k, on) => { const f = new FormData(); f.append('k', k); if (on) f.append('on', '1'); return fetch(URL0 + 'conteudo.php?acao=feito', { method: 'POST', body: f, headers: H }); };
  expect((await fe(hoje + '|instagram', true)).status).toBe(200);
  expect((await fe('qualquer|coisa', true)).status).toBe(400);
  expect((await sem()).j.feito).toEqual({ [hoje + '|instagram']: expect.any(Number) });
  await fe(hoje + '|instagram', false);
  expect((await sem()).j.feito).toEqual({});
  // nenhuma pasta compartilhada ainda: avisa, com o e-mail para compartilhar
  fs.writeFileSync(DRIVE, JSON.stringify({ outra: D.outra }));
  expect((await sem()).j).toMatchObject({ ok: false, erro: 'semPastas', email: 'robo@teste' });
  // nada do conteúdo fica dentro da pasta do site
  expect(fs.readdirSync(SITE).filter(f => f.startsWith('.simplificando'))).toEqual([]);
});

test('excluir conta (conta-excluir.php): só o dono; a conta dona do painel não pode ser excluída', async () => {
  const ex = (uid, h = { Authorization: 'Bearer ' + DONO_TK }) => { const f = new FormData(); f.append('uid', uid); return fetch(URL0 + 'conta-excluir.php', { method: 'POST', body: f, headers: h }).then(r => r.json().then(j => ({ st: r.status, j }))); };
  limpaLog();
  expect((await ex('contaDuplicada01', {})).st).toBe(403);                                     // sem login
  expect((await ex('contaDuplicada01', { Authorization: 'Bearer ' + 'z'.repeat(60) })).st).toBe(403); // login de outra pessoa
  expect((await ex('../perfis')).st).toBe(400);                                                // conta inválida
  expect((await ex('donoTeste01')).j.erro).toContain('conta dona do painel');                  // o dono não se exclui
  expect((await fetch(URL0 + 'conta-excluir.php')).status).toBe(405);
  const r = await ex('contaDuplicada01');
  expect(r.j.ok).toBe(true);
  expect(enviados()).toEqual([{ excluir: 'contaDuplicada01' }]);
});
