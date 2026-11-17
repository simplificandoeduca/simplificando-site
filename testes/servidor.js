// Servidor do robô de testes: serve a pasta site/ como o site de verdade, com duas diferenças importantes:
//  · NUNCA usa o Firebase real: a configuração do Firebase é trocada aqui.
//      porta 4501 → modo local (sem Firebase: o app funciona só no aparelho)
//      porta 4502 → Firebase de mentira (emulador em 127.0.0.1, projeto demo-simplificando)
//  · os arquivos .php do servidor (avisos, erros, login com senha) respondem de mentira,
//    para o app e o painel funcionarem sem a hospedagem. O push.php de verdade é testado à parte (servidor.spec.js).
const http = require('http'), fs = require('fs'), path = require('path');
const SITE = path.join(__dirname, '..', 'site');
const FS = 'http://127.0.0.1:8080/v1/projects/demo-simplificando/databases/(default)/documents', DONO = { Authorization: 'Bearer owner' };
const CFG = {
  4501: 'window.SIMPLIFICANDO_FIREBASE = null; // robô de testes: modo local, sem Firebase',
  4502: 'window.SIMPLIFICANDO_FIREBASE = { apiKey: "demo-key", authDomain: "demo-simplificando.firebaseapp.com", projectId: "demo-simplificando", appId: "1:1:web:1", emulador: "127.0.0.1" };',
};
const TIPOS = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml', '.json': 'application/json', '.webmanifest': 'application/manifest+json', '.otf': 'font/otf', '.woff2': 'font/woff2' };
const b64 = o => Buffer.from(JSON.stringify(o)).toString('base64url');
const json = (r, o, st = 200) => { r.statusCode = st; r.setHeader('Content-Type', 'application/json'); r.end(JSON.stringify(o)); };

// login do aluno com personal só com a senha: procura o código no emulador e devolve um passe sem assinatura (o emulador aceita)
async function loginFalso(q, r) {
  let body = ''; for await (const c of q) body += c;
  const code = (body.match(/name="codigo"\r\n\r\n(\d{6})/) || [])[1];
  try {
    const L = await (await fetch(FS + '/alunos?pageSize=300', { headers: DONO })).json();
    const d = (L.documents || []).find(x => { const a = x.fields.acesso; return a && a.mapValue.fields.codigo && a.mapValue.fields.codigo.stringValue === code; });
    const uid = d && d.fields.uid && d.fields.uid.stringValue;
    if (!uid) return json(r, { ok: false, erro: 'Senha não encontrada.' });
    const now = Math.floor(Date.now() / 1000);
    const tok = b64({ alg: 'none', typ: 'JWT' }) + '.' + b64({ iss: 'robo@demo', sub: 'robo@demo', aud: 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit', iat: now, exp: now + 3600, uid }) + '.';
    return json(r, { ok: true, token: tok });
  } catch (e) { return json(r, { ok: false, semServidor: true }, 503); }
}

// senha do hub (hub-login.php de mentira): guarda a senha na memória; para entrar, devolve o passe da conta dona do painel (sistema/dono)
let HUB_SENHA = null;
const campo = (body, n) => (body.match(new RegExp('name="' + n + '"\\r\\n\\r\\n([^\\r]*)')) || [])[1];
async function hubFalso(q, r, acao) {
  if (acao === 'ver') return json(r, { ok: true, tem: !!HUB_SENHA });
  let body = ''; for await (const c of q) body += c;
  const senha = campo(body, 'senha') || '';
  if (acao === 'definir') {
    if (!/^Bearer \S{100,}/.test(q.headers.authorization || '')) return json(r, { ok: false, erro: 'Só o dono do painel pode criar a senha do hub.' }, 403);
    if (senha.length < 6) return json(r, { ok: false, erro: 'A senha precisa ter de 6 a 64 caracteres.' }, 400);
    HUB_SENHA = senha; return json(r, { ok: true });
  }
  if (!HUB_SENHA) return json(r, { ok: false, semSenha: true, erro: 'A senha do hub ainda não foi criada. Entre uma vez com o e-mail do painel e crie a senha no hub.' }, 409);
  if (senha !== HUB_SENHA) return json(r, { ok: false, erro: 'Senha incorreta.' }, 401);
  const d = await (await fetch(FS + '/sistema/dono', { headers: DONO })).json();
  const uid = d.fields && d.fields.uid && d.fields.uid.stringValue; const now = Math.floor(Date.now() / 1000);
  return json(r, { ok: true, token: b64({ alg: 'none', typ: 'JWT' }) + '.' + b64({ iss: 'robo@demo', sub: 'robo@demo', aud: 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit', iat: now, exp: now + 3600, uid }) + '.' });
}

// Conteúdo do hub (conteudo.php de mentira): a semana de hoje com posts de exemplo (imagens do site e um vídeo curto do robô)
const CD_FEITO = {};
const dk = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const CD_ARQ = { vid1: [path.join(__dirname, 'midia', 'reel.mp4'), 'video/mp4', 'Reel-Mito-abdominal-queima-gordura.mp4'] };
for (let i = 1; i <= 7; i++) CD_ARQ['img' + i] = [path.join(SITE, 'img', 'post', 'cardio-jejum-' + i + '.jpg'), 'image/jpeg', 'Carrossel-' + i + '.jpg'];
const cdF = id => ({ id, nome: CD_ARQ[id][2], mime: CD_ARQ[id][1], tam: fs.statSync(CD_ARQ[id][0]).size });
async function cdFalso(q, r, url) {
  if (!/^Bearer \S{100,}/.test(q.headers.authorization || '')) return json(r, { ok: false, erro: 'Só o dono do painel.' }, 403);
  const acao = url.searchParams.get('acao');
  if (acao === 'feito') {
    let body = ''; for await (const c of q) body += c;
    const k = campo(body, 'k'); if (campo(body, 'on')) CD_FEITO[k] = Date.now(); else delete CD_FEITO[k];
    return json(r, { ok: true });
  }
  if (acao === 'arquivo') {
    const a = CD_ARQ[url.searchParams.get('id')]; if (!a) return json(r, { ok: false }, 404);
    r.setHeader('Content-Type', a[1]); r.setHeader('Content-Length', fs.statSync(a[0]).size); return fs.createReadStream(a[0]).pipe(r);
  }
  const ini = url.searchParams.get('ini'), hoje = dk(new Date()), amanha = dk(new Date(Date.now() + 864e5)), dias = {};
  for (let i = 0; i < 7; i++) { const d = new Date(ini + 'T12:00:00'); d.setDate(d.getDate() + i); dias[dk(d)] = []; }
  const leg = 'Cardio em jejum emagrece mais? 🤔\n\nSpoiler: o que manda é o total do dia, não a hora. Arrasta pro lado que eu te explico em 7 telas 👉\n\nSalva pra lembrar e manda pra quem vive dizendo que só faz cardio em jejum.\n\n#simplificando #cardio #emagrecimento #treino';
  if (dias[hoje]) dias[hoje] = [
    { plat: 'instagram', tema: 'Cardio em jejum emagrece mais?', tipo: 'carrossel', capa: cdF('img1'), midia: [1, 2, 3, 4, 5, 6, 7].map(i => cdF('img' + i)), legenda: leg, pasta: 'p1' },
    { plat: 'tiktok', tema: 'Mito abdominal queima gordura', tipo: 'reels', capa: cdF('img3'), midia: [cdF('vid1')], legenda: 'Abdominal não queima a gordura da barriga 🔥\n\n#simplificando #mitos', pasta: 'p2' }];
  if (dias[amanha]) dias[amanha] = [{ plat: 'instagram', tema: '', tipo: 'vazio', capa: null, midia: [], legenda: '', pasta: 'p3' }];
  const feito = {}; Object.keys(CD_FEITO).forEach(k => { if (dias[k.split('|')[0]]) feito[k] = CD_FEITO[k]; });
  return json(r, { ok: true, ini, dias, feito });
}

function servidor(porta) {
  http.createServer(async (q, r) => {
    const url = new URL(q.url, 'http://x'), p = decodeURIComponent(url.pathname);
    if (p.endsWith('/firebase-config.js')) { r.setHeader('Content-Type', 'text/javascript'); return r.end(CFG[porta]); }
    if (p === '/login.php' && q.method === 'POST') return loginFalso(q, r);
    if (p === '/hub-login.php') return hubFalso(q, r, url.searchParams.get('acao'));
    if (p === '/conteudo.php') return cdFalso(q, r, url);
    if (p === '/push.php') return json(r, url.searchParams.get('acao') === 'chave' ? { ok: true, pub: 'BOr-robo-chave-publica-de-teste' } : { ok: true, k: 'robo'.repeat(8), novos: [] });
    if (p.endsWith('.php')) return json(r, { ok: true, atual: '', nova: '', erros: [] });
    if (p.startsWith('/__fontes/')) { const ff = path.join(__dirname, 'fontes', path.basename(p)); if (fs.existsSync(ff)) { r.setHeader('Content-Type', 'font/woff2'); r.setHeader('Access-Control-Allow-Origin', '*'); return fs.createReadStream(ff).pipe(r); } }
    let f = path.join(SITE, p);
    if (fs.existsSync(f) && fs.statSync(f).isDirectory()) f = path.join(f, 'index.html');
    if (!f.startsWith(SITE) || !fs.existsSync(f)) { r.statusCode = 404; return r.end('404'); }
    r.setHeader('Content-Type', TIPOS[path.extname(f)] || 'application/octet-stream');
    r.setHeader('Cache-Control', 'no-store');
    fs.createReadStream(f).pipe(r);
  }).listen(porta, '127.0.0.1', () => console.log('robô: site de teste em http://127.0.0.1:' + porta));
}
servidor(4501); servidor(4502);
