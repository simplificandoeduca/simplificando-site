/* Painel do personal: só os avisos no celular do dono (conta nova no Simplificando).
   Não guarda nada offline e não mexe nos pedidos do painel. O servidor (push.php) só "cutuca";
   aqui o aviso busca o nome de quem entrou com a chave que o painel recebeu ao ligar o aviso. */
const BASE = new URL('./', self.location).pathname;
const DONO = BASE + '__dono';
const ED = { hipertrofia: 'Hipertrofia', emagrecer: 'Emagrecer', corrida: 'Corrida' };
const PL = { ia: 'treino montado pela IA', meu: 'vai montar o próprio treino' };
const hm = s => { const d = new Date(s * 1000); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); };
const json = o => new Response(JSON.stringify(o), { headers: { 'Content-Type': 'application/json' } });
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('push', e => {
  const geral = () => self.registration.showNotification('Nova conta no Simplificando 🎉', { body: 'Toque para ver no painel.', icon: 'icon-p-192.png', badge: 'icon-p-192.png', tag: 'novo', renotify: true, data: { url: BASE + '#perfis' } });
  e.waitUntil(caches.open('simp-dono').then(c => c.match(DONO).then(r => r ? r.json() : null).then(d => {
    if (!d || !d.k) return geral();
    return fetch('/push.php?acao=novos&k=' + encodeURIComponent(d.k) + '&desde=' + (d.ult || 0), { cache: 'no-store' }).then(r => r.json()).then(j => {
      const T = (j && j.novos) || []; if (!T.length) return geral();
      d.ult = T[T.length - 1].em;
      // marcos dos alunos (1º treino, 1ª semana, 15 dias, renovação): um aviso por marco, que abre a lista de alunos
      const MARCO = { treino1: [' fez o 1º treino 💪', 'Mande a mensagem do 1º treino.'], semana1: [' fechou a 1ª semana ✅', 'Mande a análise da 1ª semana.'], dias15: [' completou 15 dias de treino', 'Hora do check-in de 15 dias.'], renova: [': o ciclo está acabando', 'Hora de mandar a mensagem de renovação.'] };
      const M = T.filter(x => x.tipo && MARCO[x.tipo]), L = T.filter(x => !x.tipo);
      const avM = M.map(x => self.registration.showNotification(x.nome + MARCO[x.tipo][0], { body: MARCO[x.tipo][1] + ' · ' + hm(x.em), icon: 'icon-p-192.png', badge: 'icon-p-192.png', tag: 'marco-' + x.tipo + '-' + x.em, data: { url: BASE + '#alunos' } }));
      if (!L.length) return c.put(DONO, json(d)).then(() => Promise.all(avM));
      const u = L[L.length - 1];
      const info = x => [x.nome, ED[x.ed] || '', PL[x.plano] || '', hm(x.em)].filter(Boolean).join(' · ');
      const t = L.length === 1 ? 'Nova conta no Simplificando 🎉' : L.length + ' contas novas no Simplificando 🎉';
      const b = L.length === 1 ? info(u) : L.slice(-3).map(x => x.nome).join(', ') + (L.length > 3 ? ' e mais ' + (L.length - 3) : '') + '.';
      return c.put(DONO, json(d)).then(() => Promise.all(avM.concat([self.registration.showNotification(t, { body: b, icon: 'icon-p-192.png', badge: 'icon-p-192.png', tag: 'novo', renotify: true, data: { url: BASE + '#perfis' } })])));
    });
  })).catch(geral));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || BASE;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(ws => {
    const w = ws.find(c => new URL(c.url).pathname === BASE || new URL(c.url).pathname === BASE + 'index.html');
    if (w) { w.focus(); w.postMessage({ abrir: 'perfis' }); return; }
    return self.clients.openWindow(url);
  }));
});
