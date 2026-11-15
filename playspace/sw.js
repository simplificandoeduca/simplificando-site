// Play Space 1.32: o serviço de avisos do aparelho (Web Push). Ele só mostra o aviso que o servidor mandou (um pedido de autorização) e, no
// toque, abre o Play Space nas Aprovações. Não guarda nada em cache e não mexe no carregamento da tela (não há "fetch" aqui).
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('push', e => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = { b: e.data ? e.data.text() : '' }; }
  const titulo = d.t || 'Play Space', op = { body: d.b || 'Há um pedido esperando a sua autorização.', tag: d.tag || 'ps-pedido', renotify: true, icon: 'img/icone-192.png', data: { url: d.url || './#aprovar' } };
  e.waitUntil(self.registration.showNotification(titulo, op));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = new URL((e.notification.data && e.notification.data.url) || './#aprovar', self.registration.scope).href;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(L => {
    for (const c of L) if (c.url.startsWith(self.registration.scope) && 'focus' in c) { c.postMessage({ ps: 'aviso', url }); return c.focus(); }   // o Play Space já aberto: vai para as Aprovações
    return self.clients.openWindow(url);
  }));
});
