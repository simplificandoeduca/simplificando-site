/* Simplificando: guarda o app no celular para abrir sem internet (ex.: academia sem sinal).
   - A página: tenta a internet primeiro (pega a versão nova); se a internet falhar ou demorar, abre a cópia guardada.
   - Arquivos fixos (Firebase, ícones, fontes): usa a cópia guardada e atualiza por trás.
   - Banco de dados, login e e-mails não passam por aqui: o próprio Firebase cuida de reenviar quando o sinal volta. */
const VERSAO = '13 nov · 119'; // muda a cada versão (o build grava): assim o celular sempre percebe que tem sw.js novo
const CACHE = 'simp-treino-v4';
let ATUALIZANDO = false; // true quando já tinha uma versão instalada (atualização, não primeira vez)
const CORE = ['./', './manifest.webmanifest', './icon-192.png', './icon-512.png', './icon-180.png', './firebase-config.js',
  './fb/firebase-app-compat.js', './fb/firebase-auth-compat.js', './fb/firebase-firestore-compat.js'];
const BASE = new URL('./', self.location).pathname;
const FONTES = ['fonts.googleapis.com', 'fonts.gstatic.com'];

self.addEventListener('install', e => {
  ATUALIZANDO = !!self.registration.active;
  e.waitUntil(caches.open(CACHE).then(c => Promise.all(CORE.map(u => c.add(new Request(u, {cache: 'reload'})).catch(() => null)))).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k.startsWith('simp-treino-') && k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())
    // versão nova: avisa o app aberto (o próprio app recarrega quando percebe que o service worker novo assumiu)
    .then(() => ATUALIZANDO ? self.clients.matchAll({ type:'window' }) : [])
    .then(cs => cs.forEach(c => c.postMessage({ nova:true }))));
});

function guarda(req, res) {
  if (res && (res.ok || res.type === 'opaque')) { const cp = res.clone(); caches.open(CACHE).then(c => c.put(req, cp)).catch(() => null); }
  return res;
}
/* página: internet primeiro, mas sem esperar mais de 4 s com sinal fraco */
function pagina(e) {
  const req = e.request, chave = new Request(BASE);
  const rede = fetch(req).then(res => { if (res && res.ok) return caches.open(CACHE).then(c => c.put(chave, res.clone())).catch(() => null).then(() => res); return res; });
  // o iPhone encerra o service worker logo depois de responder: segura até a página nova ficar guardada
  e.waitUntil(rede.catch(() => null));
  const espera = new Promise(r => setTimeout(r, 4000)).then(() => caches.match(chave));
  return Promise.race([rede.catch(() => caches.match(chave)), espera.then(c => c || rede)])
    .then(res => res || rede)
    .catch(() => caches.match(chave).then(c => c || new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><body style="font-family:sans-serif;padding:32px;text-align:center"><h2>Sem internet</h2><p>Abra o app uma vez com internet para ele ficar guardado no seu celular.</p></body>', {headers: {'Content-Type': 'text/html; charset=utf-8'}})));
}
/* arquivos fixos: cópia guardada na hora e atualização por trás */
function fixo(req) {
  return caches.match(req).then(c => {
    const rede = fetch(req).then(res => guarda(req, res)).catch(() => c);
    return c || rede;
  });
}

self.addEventListener('fetch', e => {
  const req = e.request; if (req.method !== 'GET') return;
  const u = new URL(req.url);
  if (u.origin === self.location.origin) {
    if (!u.pathname.startsWith(BASE) || u.pathname.endsWith('.php') || u.pathname.endsWith('/sw.js')) return;
    if (u.searchParams.has('v')) return; // o app conferindo se tem versão nova: vai direto ao site, sem cópia guardada
    if (req.mode === 'navigate' || u.pathname === BASE || u.pathname === BASE + 'index.html') { e.respondWith(pagina(e)); return; }
    e.respondWith(fixo(req)); return;
  }
  if (FONTES.includes(u.hostname)) e.respondWith(fixo(req));
});

/* lembrete de água com o app fechado: o servidor (push.php) só "cutuca"; a mensagem é montada aqui */
const FRASES = ['Hora de beber água 💧', 'Um copo agora? 💧', 'Sua garrafinha está te chamando 💧', 'Pausa rápida para a água 💧'];
self.addEventListener('push', e => {
  const t = FRASES[Math.floor(Math.random() * FRASES.length)];
  e.waitUntil(self.registration.showNotification(t, { body: 'Toque para registrar o copo no Simplificando.', icon: 'icon-192.png', badge: 'icon-192.png', tag: 'agua', renotify: true, data: { url: BASE + '#agua' } }));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || BASE;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(ws => {
    const w = ws.find(c => new URL(c.url).pathname.startsWith(BASE));
    if (w) { w.focus(); w.postMessage({ abrir: 'agua' }); return; }
    return self.clients.openWindow(url);
  }));
});
