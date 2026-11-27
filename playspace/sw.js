// Play Space: o serviço do aparelho.
// (1) 1.32: os avisos de pedidos (Web Push): mostra o aviso que o servidor mandou e, no toque, abre o Play Space nas Aprovações.
// (2) 1.40: o app guardado no aparelho, para abrir sem internet. A tela vem sempre da internet quando há (a versão nova chega na hora; se a
//     internet demora, vem a guardada); as imagens e as letras vêm do que está guardado. A API e a atualização nunca passam por aqui.
// (3) 1.47: só o que é do Play Space passa por aqui (a tela, o manifesto, as imagens e as letras). Outra pasta do mesmo site (o /notas,
//     o emissor de notas) vai direto para a internet: antes, os arquivos dele ficavam guardados e a tela dele tomava o lugar da do Play Space.
// (4) 1.58: abrir rápido: a tela vem do que está guardado (na hora) e a da internet fica guardada para a próxima vez. Logo depois de
//     atualizar (o endereço chega com ?v=), a tela vem da internet. A tela confere a versão do servidor e recarrega se a guardada ficou velha.
const CACHE = 'ps-app-2';
self.addEventListener('install', e => e.waitUntil(caches.open(CACHE).then(c => c.addAll(['./', 'manifest.json', 'img/icone-192.png', 'img/icone-512.png']).catch(() => { })).then(() => self.skipWaiting())));
self.addEventListener('activate', e => e.waitUntil(caches.keys().then(K => Promise.all(K.filter(k => k.startsWith('ps-app-') && k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())));
const comPrazo = (p, ms) => new Promise((ok, nao) => { const t = setTimeout(() => nao(new Error('demorou')), ms); p.then(x => { clearTimeout(t); ok(x); }, e => { clearTimeout(t); nao(e); }); });
self.addEventListener('fetch', e => {
  const r = e.request; if (r.method !== 'GET') return; const u = new URL(r.url), letras = /^fonts\.(googleapis|gstatic)\.com$/.test(u.hostname);
  if (u.origin !== self.location.origin && !letras) return;
  if (!letras && (/\/api\//.test(u.pathname) || /\.php$/.test(u.pathname) || /versao\.json$/.test(u.pathname) || /sw\.js$/.test(u.pathname))) return;
  const rel = u.pathname.slice(new URL(self.registration.scope).pathname.length);   // 1.47: o caminho dentro da pasta do Play Space
  if (!letras && !(rel === '' || rel === 'index.html' || rel === 'manifest.json' || rel.startsWith('img/'))) return;   // outra pasta do site (o /notas): direto para a internet
  e.respondWith((async () => {
    const c = await caches.open(CACHE);
    if (r.mode === 'navigate') {
      const rede = comPrazo(fetch(r), 7000).then(x => { if (x && x.ok) c.put('./', x.clone()); return x; });
      if (!u.searchParams.has('v')) { const g = await c.match('./'); if (g) { e.waitUntil(rede.catch(() => { })); return g; } }
      try { return await rede; } catch (er) { return (await c.match('./')) || (await c.match(r)) || Response.error(); }
    }
    const g = await c.match(r), rede = fetch(r).then(x => { if (x && (x.ok || x.type === 'opaque')) c.put(r, x.clone()); return x; }).catch(() => null);
    return g || (await rede) || Response.error();
  })());
});
self.addEventListener('push', e => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = { b: e.data ? e.data.text() : '' }; }
  const titulo = d.t || 'Play Space', op = { body: d.b || 'Há um pedido esperando a sua autorização.', tag: d.tag || 'ps-pedido', renotify: true, icon: 'img/icone-192.png', data: { url: d.url || './#aprovar' } };
  e.waitUntil(self.registration.showNotification(titulo, op));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = new URL((e.notification.data && e.notification.data.url) || './#aprovar', self.registration.scope).href;
  const insta = x => new URL(x).pathname.startsWith(new URL('insta/', self.registration.scope).pathname), alvoInsta = insta(url);   /* 2.70: o aviso do Insta abre o Insta (não o Play Space) */
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(L => {
    for (const c of L) if (c.url.startsWith(self.registration.scope) && insta(c.url) === alvoInsta && 'focus' in c) { c.postMessage({ ps: 'aviso', url }); return c.focus(); }   // o Play Space já aberto: vai para as Aprovações
    return self.clients.openWindow(url);
  }));
});
