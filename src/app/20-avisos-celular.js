/* ---------- fim do descanso com o app fechado (opção 2: "Bora, Joana! 💪") ----------
   Quando o descanso começa, o app avisa o push.php da hora em que ele termina e guarda o texto para o service worker.
   Se a pessoa continua no app, cancela 2 s antes do fim (o próprio app avisa na tela); se saiu, o servidor manda a notificação na hora. */
const RPS = { fim:0, k:'', cx:0 };
function restPush(){
  if(!PREF.pushId || !pushLigado()) return;
  const r = ui.rest, fim = r && !r.done && PREF.avDesc!==false ? r.end : 0;
  if(fim===RPS.fim || (fim && fim===RPS.cx)) return;
  RPS.fim = fim; RPS.k = fim ? Math.random().toString(36).slice(2,10) : '';
  let ex = '', se = 0, n = 0; const nx = r && r.next, f = nx && findEx(nx.id); if(f){ ex = f.e.nome; se = nx.i+1; n = exN(f.e); }
  const url = new URL('__descanso', location.href).href, dado = { fim, k:RPS.k, id:PREF.pushId, nome:firstName(S.nome||''), ex, s:se, n, av:0 };
  try { if(window.caches) caches.open('simp-estado').then(c => fim ? c.put(url, new Response(JSON.stringify(dado), {headers:{'Content-Type':'application/json'}})) : c.delete(url)).catch(() => {}); } catch(e){ /* sem cache */ }
  pushPost('descanso', {fim, k:RPS.k});
}
function restPerto(){ const r = ui.rest; if(!r || r.done || !RPS.fim || RPS.fim!==r.end || document.visibilityState!=='visible') return; if(r.end-Date.now()<=2000){ RPS.cx = r.end; RPS.fim = 0; pushPost('descanso', {fim:0, k:''}); } }
/* convite (uma vez, no começo de um treino) para quem ainda não ligou os avisos no celular */
function dsAsk(){
  if(PREF.dsAsk || !pushPode() || pushLigado() || (window.Notification && Notification.permission==='denied')) return;
  const tenta = () => { if(!S.session || PREF.dsAsk) return; if(ui.sheet || document.getElementById('pick')){ setTimeout(tenta, 1500); return; } PREF.dsAsk = 1; savePref(); ui.sheet = {k:'dsask'}; renderSheet(true); };
  setTimeout(tenta, 1500);
}
function vDsAsk(){
  return `<div class="sheet-wrap cm-w" data-a="ds-no"><div class="sheet cm" role="dialog" aria-modal="true" aria-labelledby="cm-t" tabindex="-1">
    <span class="cm-ic">${ic('bell')}</span><h2 id="cm-t">Quer ser avisado quando o descanso acabar<span class="dot">?</span></h2>
    <p class="cm-sub">Se você sair do app durante o descanso, a gente te avisa na hora da próxima série.</p>
    <button type="button" class="btn primary block cm-b" data-a="ds-sim">${ic('bell')}Quero ser avisado</button>
    <button type="button" class="btn block cm-b2" data-a="ds-no">Agora não</button></div></div>`;
}
/* ---------- treino esquecido aberto: aviso no celular com 3h de treino e janela para salvar com o tempo certo ---------- */
const ESQ_MS = 3*3600e3;
const sesIni = s => s ? (s.ini || (s.run ? s.start-s.acc : Date.now()-s.acc)) : 0;
const esqLongo = () => !!S.session && Date.now()-sesIni(S.session) >= ESQ_MS;
function sesUlt(){ const s = S && S.session; if(!s) return; const L = S.logs[s.d||today()] || {}; let n = 0; Object.keys(L).forEach(id => (L[id]||[]).forEach(x => { if(x && x.ok) n++; })); if(n>(s.nOk||0)) s.ult = Date.now(); s.nOk = n; }
const durTxt = m => { m = Math.max(0, Math.round(m)); const h = Math.floor(m/60), r = m%60; return h ? h+'h'+(r ? String(r).padStart(2,'0') : '') : r+' min'; };
/* sugestão: até a última série feita + 5 min; sem série, o tempo do cronômetro */
function esqSug(){ const s = S.session; if(!s) return 60; const ini = sesIni(s), m = s.ult && s.ult>ini ? (s.ult-ini)/60000+5 : elapsed()/60000; return Math.max(5, Math.min(300, Math.round(m/5)*5)); }
function esqAbre(fim){ if(!S.session) return; ui.arm = null; ui.sheet = { k:'esq', min:esqSug(), fim:!!fim }; renderSheet(true); }
function esqAsk(){ const s = S.session; if(!ACCT || !s || ui.sheet || !esqLongo()) return; if(s.esqOk && Date.now()-s.esqOk < 2*3600e3) return; esqAbre(false); }
function vEsq(){
  const s = S.session, sh = ui.sheet, p = PLAN[s.t] || {}, ini = new Date(sesIni(s)), hj = today(), dd = s.d && s.d!==hj ? (s.d===daysAgo(1) ? 'ontem' : 'em '+s.d.slice(8,10)+'/'+s.d.slice(5,7)) : 'hoje';
  const hr = String(ini.getHours()).padStart(2,'0')+':'+String(ini.getMinutes()).padStart(2,'0'), aberto = durTxt((Date.now()-sesIni(s))/60000), sug = esqSug(), ok = (s.nOk||0);
  return `<div class="sheet-wrap cm-w"${sh.fim ? ' data-a="esq-x"' : ''}><div class="sheet cm esq" role="dialog" aria-modal="true" aria-labelledby="cm-t" tabindex="-1">
    <span class="cm-ic">${ic('timer')}</span><h2 id="cm-t">${sh.fim ? 'Confira o tempo do treino' : 'Seu treino ainda está aberto'}<span class="dot">${sh.fim ? '.' : '?'}</span></h2>
    <p class="cm-sub">Treino ${s.t}${p.nome ? ' · '+esc(p.nome) : ''} começou ${dd} às ${hr} e está aberto há <b>${aberto}</b>${ok ? ' ('+ok+' série'+(ok===1?'':'s')+' feita'+(ok===1?'':'s')+')' : ''}.</p>
    <p class="esq-l">Quanto tempo você treinou de verdade?</p>
    <div class="esq-st"><button type="button" class="btn round" data-a="esq-m" data-d="-5" aria-label="Menos 5 minutos">${ic('minus')}</button><b aria-live="polite">${durTxt(sh.min)}</b><button type="button" class="btn round" data-a="esq-m" data-d="5" aria-label="Mais 5 minutos">${ic('plus')}</button></div>
    <p class="cm-g">${s.ult ? 'Pela sua última série, sugerimos '+durTxt(sug)+'.' : 'Ajuste de 5 em 5 minutos.'}</p>
    <button type="button" class="btn primary block cm-b" data-a="esq-ok">${ic('check')}Salvar treino com ${durTxt(sh.min)}</button>
    ${sh.fim ? `<button type="button" class="btn block cm-b2" data-a="esq-x">Voltar ao treino</button>` : `<button type="button" class="btn block cm-b2" data-a="esq-cont">Ainda estou treinando</button>`}
    ${ok ? '' : (ui.arm==='esq-del' ? `<button type="button" class="btn ghost block esq-del on" data-a="esq-del">Toque de novo para descartar</button>` : `<button type="button" class="btn ghost block esq-del" data-a="esq-del">Descartar este treino</button>`)}</div></div>`;
}
function esqClick(a, d){
  const sh = ui.sheet; if(!sh || sh.k!=='esq' || !S.session) return false;
  if(a==='esq-m'){ sh.min = Math.max(5, Math.min(600, sh.min + +d.d)); renderSheet(); refocus(`[data-a="esq-m"][data-d="${d.d}"]`); return true; }
  if(a==='esq-ok'){ S.session.fix = sh.min*60000; ui.sheet = null; renderSheet(); finish(); if(S.session) S.session.fix = 0; save(); render(); return true; }
  if(a==='esq-cont'){ S.session.esqOk = Date.now(); ui.sheet = null; save(); renderSheet(); toast('Beleza. Bom treino!'); return true; }
  if(a==='esq-x'){ ui.sheet = null; renderSheet(); return true; }
  if(a==='esq-del'){ if(ui.arm==='esq-del'){ ui.arm = null; ui.sheet = null; renderSheet(); cancelSession(); render(); } else { ui.arm = 'esq-del'; renderSheet(); refocus('[data-a="esq-del"]'); } return true; }
  return false;
}
/* o celular guarda o início do treino aberto (o service worker lê no aviso) e avisa o push.php para mandar a notificação com 3h */
function treinoAviso(){
  const s = S && S.session, ini = s && PREF.avTreino!==false ? sesIni(s) : 0, url = new URL('__treino', location.href).href;
  try { if(window.caches) caches.open('simp-estado').then(c => c.match(url).then(r => r ? r.json() : null).catch(() => null).then(o => ini ? c.put(url, new Response(JSON.stringify({ ini, t:s.t, nome:(PLAN[s.t]||{}).nome||'', av:o && o.ini===ini ? o.av||0 : 0 }), {headers:{'Content-Type':'application/json'}})) : c.delete(url))).catch(() => {}); } catch(e){ /* sem cache */ }
  if(pushLigado() && PREF.pushId && PREF.trIni!==ini){ PREF.trIni = ini; savePref(); pushPost('treino', {ini}); }
}
/* lembrete de água com o app fechado: o celular se inscreve no push.php e o servidor avisa no horário */
const IOS = /iPhone|iPad|iPod/.test(navigator.userAgent), STANDALONE = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone===true;
const simPush = () => TESTE_LOCAL && !!window.__simpPush; // robô de testes: finge que os avisos do celular estão ligados (só no computador de teste)
const pushPode = () => simPush() || !!(FB && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);
const pushLigado = () => simPush() ? !!PREF.pushOn : pushPode() && PREF.pushOn && Notification.permission==='granted';
const b64u8 = s => { const b = atob((s+'='.repeat((4-s.length%4)%4)).replace(/-/g,'+').replace(/_/g,'/')); return Uint8Array.from(b, c => c.charCodeAt(0)); };
const pushPost = (acao, dados) => fetch('/push.php?acao='+acao, {method:'POST', body:JSON.stringify(Object.assign({id:PREF.pushId}, dados||{})), keepalive:true}).then(r => r.json()).catch(() => ({ok:false}));
function pushDados(sub){ const meta = (S.water[today()]||0)>=S.waterGoal ? today() : ''; return Object.assign(sub ? {sub} : {}, {ini:hhmm(RINI()), fim:hhmm(RFIM()), cada:S.remind.every, tz:new Date().getTimezoneOffset(), meta, ag:S.remind.on ? 1 : 0}); }
async function pushEnable(soDescanso){
  if(!pushPode()){ toast(IOS && !STANDALONE ? 'No iPhone, adicione o app à tela de início e abra por lá para ligar os avisos.' : 'Este navegador não manda avisos com o app fechado.'); return; }
  try {
    const perm = await Notification.requestPermission();
    if(perm!=='granted'){ toast('Avisos bloqueados. Libere as notificações do Simplificando nos ajustes do celular.'); return; }
    const reg = await navigator.serviceWorker.ready, k = await fetch('/push.php?acao=chave',{cache:'no-store'}).then(r => r.json());
    if(!k || !k.pub) throw new Error('sem chave');
    let sub = await reg.pushManager.getSubscription();
    if(!sub) sub = await reg.pushManager.subscribe({userVisibleOnly:true, applicationServerKey:b64u8(k.pub)});
    if(!PREF.pushId) PREF.pushId = Array.from(crypto.getRandomValues(new Uint8Array(12)), b => (b%36).toString(36)).join('')+Date.now().toString(36);
    const r = await pushPost('assinar', pushDados(sub.toJSON()));
    if(!r.ok) throw new Error('servidor');
    PREF.pushOn = true; savePref(); PREF.trIni = null; treinoAviso(); PREF.lbK = null; LBC = ''; lbSync();
    if(soDescanso){ if(S.remind.on) pushPost('assinar', pushDados(sub.toJSON())); toast('Pronto. Se sair do app no descanso, a gente te avisa na hora da próxima série.'); render(); return; }
    if(!S.remind.on){ S.remind.on = true; save(); pushPost('assinar', pushDados(sub.toJSON())); }
    toast('Pronto. Os lembretes de água chegam no celular mesmo com o app fechado.'); render();
  } catch(e){ toast('Não deu para ligar os avisos agora. Confira a internet e tente de novo.'); }
}
async function pushDisable(manter){
  if(PREF.pushId) await pushPost('sair');
  if(!manter){ PREF.pushOn = false; savePref(); try { const reg = await navigator.serviceWorker.ready, sub = await reg.pushManager.getSubscription(); if(sub) await sub.unsubscribe(); } catch(e){ /* nada a desfazer */ } }
  render();
}
function pushSave(){ if(!pushLigado()) return; navigator.serviceWorker.ready.then(reg => reg.pushManager.getSubscription()).then(sub => { if(sub) pushPost('assinar', pushDados(sub.toJSON())); }).catch(() => {}); }
function pushCopo(){ if(pushLigado() && PREF.pushId) pushPost('copo', {meta:(S.water[today()]||0)>=S.waterGoal ? today() : ''}); }
function pushCard(){
  if(pushLigado()) return `<p class="zone">${ic('bell')}<span><b>Avisos no celular ligados.</b> Chegam mesmo com o app fechado, das ${hmTxt(RINI())} às ${hmTxt(RFIM())}, e param quando a meta do dia é batida.</span></p><button type="button" class="btn ghost small self-start" data-a="push-off">Desligar avisos no celular</button>`;
  if(IOS && !STANDALONE) return `<p class="foot0">No iPhone, para receber o aviso com o app fechado: toque em ${ic('up')} Compartilhar → <b>Adicionar à Tela de Início</b> e abra o Simplificando pelo ícone. Depois ligue os avisos aqui.</p>`;
  if(pushPode()) return `<button type="button" class="btn block rm-push" data-a="push-on">${ic('bell')}Avisar mesmo com o app fechado</button><p class="foot0">Sem isso, os lembretes aparecem só com o app aberto.</p>`;
  return `<p class="foot0">Os lembretes aparecem enquanto o app estiver aberto.</p>`;
}
/* versão nova: o app confere no site se tem versão mais nova e mostra o aviso com o botão Atualizar agora */
let NOVAV = '', novaAt = 0;
async function versaoCheca(force){
  if(NOVAV){ versaoAviso(); if(!force) return 'nova'; }
  if(!navigator.onLine) return 'off';
  if(!force && Date.now()-novaAt < 20e3) return ''; novaAt = Date.now();
  try { const rg = navigator.serviceWorker && await navigator.serviceWorker.getRegistration(); if(rg) rg.update().catch(() => null); } catch(e){ /* sem service worker */ }
  try {
    const txt = await fetch(location.pathname+'?v='+Date.now(), { cache:'no-store' }).then(r => r.ok ? r.text() : '');
    const m = txt.match(/const\s+APPV = '([^']+)'/); if(!m) return 'erro'; if(m[1]===APPV) return 'igual';
    NOVAV = m[1]; versaoAviso(); return 'nova';
  } catch(e){ return 'off'; }
}
// botão do Perfil: procura na hora; se tiver versão nova, já atualiza
async function versaoPerfil(b){
  if(b){ b.disabled = true; b.innerHTML = ic('refresh')+'Procurando…'; }
  const r = await versaoCheca(true);
  if(r==='nova'){ versaoAtualiza(); return; }
  if(b){ b.disabled = false; b.innerHTML = ic('refresh')+'Procurar atualização'; }
  toast(r==='igual' ? 'Você já está na versão mais nova ('+APPV+').' : r==='off' ? 'Sem internet. Conecte e tente de novo.' : 'Não deu para conferir agora. Tente de novo.');
}
function versaoAviso(){
  if(!NOVAV) return; let el = document.getElementById('novav');
  if(!el){ el = document.createElement('div'); el.id = 'novav'; el.setAttribute('role','status'); document.body.appendChild(el); }
  el.innerHTML = `<span><b>Versão nova do app</b><small>${esc(NOVAV)} · você está na ${esc(APPV)}</small></span><button type="button" id="novav-go">${ic('refresh')}Atualizar agora</button>`;
  el.querySelector('#novav-go').onclick = versaoAtualiza;
}
async function versaoAtualiza(){
  const b = document.getElementById('novav-go'); if(b){ b.disabled = true; b.textContent = 'Atualizando…'; }
  try { save(); } catch(e){ /* nada para salvar */ }
  try { const r = navigator.serviceWorker && await navigator.serviceWorker.getRegistration(); if(r) await r.update(); } catch(e){ /* sem service worker */ }
  try { if(window.caches){ const ks = await caches.keys(); await Promise.all(ks.filter(k => k.startsWith('simp-treino-')).map(k => caches.open(k).then(c => c.keys().then(rs => Promise.all(rs.filter(q => new URL(q.url).pathname===location.pathname).map(q => c.delete(q))))))); } } catch(e){ /* sem cache */ }
  location.replace(location.pathname+'?atualizar='+Date.now()+location.hash);
}
function versaoSetup(){
  try { const q = new URLSearchParams(location.search); if(q.has('atualizar')){ history.replaceState(null,'',location.pathname+location.hash); setTimeout(() => toast('App atualizado: versão '+APPV), 900); } } catch(e){ /* sem histórico */ }
  // confere logo que o app abre e toda vez que a pessoa volta para ele, em qualquer aba
  setTimeout(() => versaoCheca(true), 800);
  document.addEventListener('visibilitychange', () => { if(document.visibilityState==='visible') versaoCheca(); });
  window.addEventListener('pageshow', () => versaoCheca()); window.addEventListener('focus', () => versaoCheca());
  window.addEventListener('online', () => versaoCheca(true));
  setInterval(() => { if(document.visibilityState==='visible') versaoCheca(); }, 5*60e3);
  // o service worker novo assumiu (versão nova chegou): recarrega sozinho; vale também para o app da tela inicial do iPhone
  try { if(navigator.serviceWorker){ const tinha = !!navigator.serviceWorker.controller; let foi = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => { if(!tinha || foi) return; foi = true; const el = document.activeElement;
      if(el && /INPUT|TEXTAREA/.test(el.tagName)){ versaoCheca(true); return; } versaoAtualiza(); }); } } catch(e){ /* sem service worker */ }
  // o sw.js avisa quando uma versão nova acabou de ser baixada
  try { navigator.serviceWorker && navigator.serviceWorker.addEventListener('message', ev => { if(ev.data && ev.data.nova) versaoCheca(true); }); } catch(e){ /* sem service worker */ }
}
/* sem internet: o app fica guardado no celular (sw.js na pasta do app) e avisa quando o sinal cai ou volta */
function offlineSetup(){
  const ok = 'serviceWorker' in navigator && FB && (location.protocol==='https:' || location.hostname==='localhost' || location.hostname==='127.0.0.1');
  const reg = () => navigator.serviceWorker.register('sw.js').catch(() => { /* sem guardar offline */ });
  if(ok){ if(document.readyState==='complete') reg(); else window.addEventListener('load', reg); }
  try { navigator.serviceWorker && navigator.serviceWorker.addEventListener('message', ev => { if(ev.data && ev.data.abrir==='agua' && ACCT){ ui.tab = 'agua'; ui.enter = true; render(); } if(ev.data && ev.data.abrir==='treino' && ACCT){ ui.tab = 'treino'; if(S.session) ui.t = S.session.t; render(); esqAsk(); } }); } catch(e){ /* sem mensagens */ }
  if(location.hash==='#treino' && ACCT){ ui.tab = 'treino'; if(S.session) ui.t = S.session.t; render(); history.replaceState(null,'',location.pathname+location.search); }
  if(location.hash==='#treino-aberto'){ history.replaceState(null,'',location.pathname+location.search); if(ACCT && S.session){ ui.tab = 'treino'; ui.t = S.session.t; render(); } }
  /* treino aberto há 3h ou mais: pergunta ao abrir ou voltar para o app (e confere a cada minuto) */
  setTimeout(() => { if(ACCT){ treinoAviso(); lbSync(); esqAsk(); } }, 1800);
  document.addEventListener('visibilitychange', () => { if(document.visibilityState==='visible' && ACCT) setTimeout(esqAsk, 600); });
  setInterval(() => { if(ACCT && document.visibilityState==='visible') esqAsk(); }, 60000);
  if(location.hash==='#agua' && ACCT){ ui.tab = 'agua'; render(); history.replaceState(null,'',location.pathname+location.search); }
  window.addEventListener('offline', () => toast('Sem internet. Pode treinar normalmente: tudo fica salvo no celular e sobe quando o sinal voltar.'));
  window.addEventListener('online', () => { toast('Internet de volta. Salvando seus treinos.'); try { if(typeof progSync==='function' && ACCT && ACCT.mode==='personal') progSync(true); if(typeof fbRefresh==='function' && ACCT) fbRefresh(); } catch(e){ /* sem sincronizar agora */ } });
}
