/* Simplificando: guarda o app no celular para abrir sem internet (ex.: academia sem sinal).
   - A página: tenta a internet primeiro (pega a versão nova); se a internet falhar ou demorar, abre a cópia guardada.
   - Arquivos fixos (Firebase, ícones, fontes): usa a cópia guardada e atualiza por trás.
   - Banco de dados, login e e-mails não passam por aqui: o próprio Firebase cuida de reenviar quando o sinal volta. */
const VERSAO = '27 nov · 188'; // muda a cada versão (o build grava): assim o celular sempre percebe que tem sw.js novo
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

/* avisos com o app fechado: o servidor (push.php) só "cutuca"; a mensagem é montada aqui.
   Fim do descanso (o app guarda os dados em simp-estado quando o descanso começa): "Bora, Joana! 💪";
   treino aberto há quase 3h (uma vez por treino): "Seu treino ainda está aberto";
   dia de treino a partir da 2ª semana, no horário daquele dia (uma vez no dia, se ainda não treinou): "Bora treinar, Joana? 💪"; senão, o lembrete de água. */
const FRASES = ['Hora de beber água 💧', 'Um copo agora? 💧', 'Sua garrafinha está te chamando 💧', 'Pausa rápida para a água 💧'];
const DESC = BASE + '__descanso';
const TREINO = BASE + '__treino';
const LEMB = BASE + '__lembrete';
const hojeTxt = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const durTxt = ms => { const m = Math.round(ms / 60000), h = Math.floor(m / 60), r = m % 60; return h ? h + 'h' + (r ? String(r).padStart(2, '0') : '') : r + ' min'; };
const agua = () => self.registration.showNotification(FRASES[Math.floor(Math.random() * FRASES.length)], { body: 'Toque para registrar o copo no Simplificando.', icon: 'icon-192.png', badge: 'icon-192.png', tag: 'agua', renotify: true, data: { url: BASE + '#agua', abrir: 'agua' } });
const json = o => new Response(JSON.stringify(o), { headers: { 'Content-Type': 'application/json' } });
self.addEventListener('push', e => {
  e.waitUntil(caches.open('simp-estado').then(c => c.match(DESC).then(r => r ? r.json() : null).catch(() => null).then(d => {
    const ag = Date.now();
    if (d && d.fim && !d.av && ag >= d.fim - 20000 && ag <= d.fim + 180000) {
      d.av = 1;
      return c.put(DESC, json(d)).then(() => self.registration.showNotification('Bora' + (d.nome ? ', ' + d.nome : '') + '! 💪',
        { body: 'Seu descanso acabou.' + (d.ex ? ' Próxima: ' + d.ex + ', série ' + d.s + ' de ' + d.n + '.' : ' Bora para a próxima série.'), icon: 'icon-192.png', badge: 'icon-192.png', tag: 'descanso', renotify: true, vibrate: [120, 60, 120],
          actions: [{ action: 'abrir', title: 'Abrir treino' }, { action: 'mais', title: '+15 s' }], data: { url: BASE + '#treino', abrir: 'treino' } }));
    }
    return c.match(TREINO).then(r => r ? r.json() : null).catch(() => null).then(tr => {
      if (tr && tr.ini && !tr.av && ag - tr.ini >= 3 * 3600e3 - 20 * 60e3) {
        tr.av = 1;
        return c.put(TREINO, json(tr)).then(() => self.registration.showNotification('Seu treino ainda está aberto ⏱️', { body: 'Treino ' + tr.t + (tr.nome ? ' · ' + tr.nome : '') + ': ' + durTxt(ag - tr.ini) + ' de treino. Esqueceu de finalizar? Toque para salvar e ajustar o tempo.', icon: 'icon-192.png', badge: 'icon-192.png', tag: 'treino', renotify: true, requireInteraction: true, data: { url: BASE + '#treino-aberto', abrir: 'treino' } }));
      }
      return c.match(LEMB).then(r => r ? r.json() : null).catch(() => null).then(lb => {
        const d = new Date(), dia = hojeTxt(d), min = d.getHours() * 60 + d.getMinutes();
        const lh = lb ? +((lb.hs || {})[d.getDay()] ?? lb.h) : 0;
        // lembrete para voltar (o servidor manda a partir das 18h30, uma vez por etapa): 1 dia sem o 1º treino; 4 e 10 dias sem treinar
        const vt = lb && lb.vt, ref = vt && (vt.ult || vt.cad), dd = ref ? Math.floor((Date.parse(dia + 'T12:00:00Z') - Date.parse(ref + 'T12:00:00Z')) / 864e5) : 0;
        const et = !vt || !vt.on || !ref ? '' : vt.ult ? (dd >= 10 ? 't10' : dd >= 4 ? 't4' : '') : (dd >= 10 ? 'n10' : dd >= 4 ? 'n4' : dd >= 1 ? 'n1' : ''), vk = et && et + '|' + ref;
        if (et && min >= 18 * 60 + 25 && lb.vtav !== vk) {
          lb.vtav = vk; const nm = lb.nome || '';
          const [tt, bd] = et[0] === 'n' ? ['Seu treino já está pronto 💪', 'Oi' + (nm ? ', ' + nm : '') + '! Tudo bem? Estamos esperando você para o primeiro treino. Abra o app e arraste “Começar treino”.']
            : et === 't4' ? ['Sentimos sua falta 👀', (nm ? nm + ', f' : 'F') + 'az ' + dd + ' dias do seu último treino. Bora voltar hoje?' + (lb.t ? ' O treino ' + lb.t + ' está te esperando.' : '')]
            : ['Ainda dá tempo de retomar 🔥', (nm ? nm + ', s' : 'S') + 'ão ' + dd + ' dias sem treinar. Recomece leve hoje: o app ajusta as cargas para você.'];
          return c.put(LEMB, json(lb)).then(() => self.registration.showNotification(tt, { body: bd, icon: 'icon-192.png', badge: 'icon-192.png', tag: 'volta', renotify: true, data: { url: BASE + '#treino', abrir: 'treino' } }));
        }
        if (lb && lb.on && !(lb.desde && dia < lb.desde) && (lb.dias || []).includes(d.getDay()) && min >= lh - 5 && min - lh < 180 && lb.av !== dia && lb.feito !== dia && !(lb.pausa && lb.pausa >= dia)) {
          lb.av = dia;
          return c.put(LEMB, json(lb)).then(() => self.registration.showNotification('Bora treinar' + (lb.nome ? ', ' + lb.nome : '') + '? 💪', { body: 'Hoje é dia do Treino ' + lb.t + (lb.tn ? ' · ' + lb.tn : '') + '. Toque para começar.', icon: 'icon-192.png', badge: 'icon-192.png', tag: 'lembrete', renotify: true, data: { url: BASE + '#treino', abrir: 'treino' } }));
        }
        return agua();
      });
    });
  })).catch(agua));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const dd = e.notification.data || {};
  // +15 s (Android): pede ao servidor um aviso novo daqui a 15 segundos, sem abrir o app
  if (e.action === 'mais') {
    e.waitUntil(caches.open('simp-estado').then(c => c.match(DESC).then(r => r ? r.json() : null).then(d => {
      if (!d || !d.id) return;
      d.fim = Date.now() + 15000; d.k = Math.random().toString(36).slice(2, 10); d.av = 0;
      return c.put(DESC, json(d)).then(() => fetch('/push.php?acao=descanso', { method: 'POST', body: JSON.stringify({ id: d.id, fim: d.fim, k: d.k }) })).catch(() => null);
    })));
    return;
  }
  const url = dd.url || BASE, abrir = dd.abrir || 'agua';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(ws => {
    const w = ws.find(c => new URL(c.url).pathname.startsWith(BASE));
    if (w) { w.focus(); w.postMessage({ abrir }); return; }
    return self.clients.openWindow(url);
  }));
});
