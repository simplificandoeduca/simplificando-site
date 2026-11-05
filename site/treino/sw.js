/* Simplificando: guarda o app no celular para abrir sem internet (ex.: academia sem sinal).
   - A página: tenta a internet primeiro (pega a versão nova); se a internet falhar ou demorar, abre a cópia guardada.
   - Arquivos fixos (Firebase, ícones, fontes): usa a cópia guardada e atualiza por trás.
   - Banco de dados, login e e-mails não passam por aqui: o próprio Firebase cuida de reenviar quando o sinal volta. */
const CACHE = 'simp-treino-v2';
const CORE = ['./', './manifest.webmanifest', './icon-192.png', './icon-512.png', './icon-180.png', './firebase-config.js',
  './fb/firebase-app-compat.js', './fb/firebase-auth-compat.js', './fb/firebase-firestore-compat.js'];
const BASE = new URL('./', self.location).pathname;
const FONTES = ['fonts.googleapis.com', 'fonts.gstatic.com'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => Promise.all(CORE.map(u => c.add(new Request(u, {cache: 'reload'})).catch(() => null)))).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k.startsWith('simp-treino-') && k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

function guarda(req, res) {
  if (res && (res.ok || res.type === 'opaque')) { const cp = res.clone(); caches.open(CACHE).then(c => c.put(req, cp)).catch(() => null); }
  return res;
}
/* página: internet primeiro, mas sem esperar mais de 4 s com sinal fraco */
function pagina(req) {
  const chave = new Request(BASE);
  const rede = fetch(req).then(res => { if (res && res.ok) guarda(chave, res.clone()); return res; });
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
    if (req.mode === 'navigate' || u.pathname === BASE || u.pathname === BASE + 'index.html') { e.respondWith(pagina(req)); return; }
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
