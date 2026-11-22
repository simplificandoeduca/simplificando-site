/* Play Space Finanças 3.4 (Play Space 1.80): a meta do dia.
   Pedido do dono: quando a loja bater a meta do dia (o valor que o gestor coloca em Ajustes, no sistema), "todos os computadores que estiverem
   com o sistema ligado, inclusive o financeiro: meta do dia batida e vários foguinhos". Com as Finanças abertas (e com alguém dentro), a
   página pergunta ao servidor a cada 20 segundos; o que ainda não comemorou, comemora (uma vez neste aparelho). A comemoração é a mesma do
   sistema. 3.5 (1.81): e os dois alertas ("Falta pouco para a meta!"), uma vez cada.
   4.0 (1.87): as metas do mês, por unidade: a meta do dia de cada unidade (com o nome dela) e, maior, a do mês ("Meta do mês batida!"). */
'use strict';
/* A comemoração da meta do dia (a mesma no sistema e nas Finanças). Por cima de tudo, SEM travar a tela: os toques passam para o que está
   embaixo, e um toque em qualquer lugar (ou o Esc) fecha. A tela escurece um pouco, os foguetes sobem e estouram em fogos por uns 7
   segundos, e no meio o recado "Meta do dia batida!", com a meta e a hora. Com "reduzir movimento" (ou "Animações: leves"), só o recado.
   1.87: com o nome da unidade (o.onde) e a do mês: "Meta do mês batida!" (o.titulo, o.mes), maior (o.grande: mais foguetes, uns 10 s). */
var MF_TROFEU = '<svg viewBox="0 0 64 64" width="76" height="76"><path d="M20 8h24v14a12 12 0 0 1-24 0z" fill="#F2B33D" stroke="#3B3B3B" stroke-width="2.2" stroke-linejoin="round"/><path d="M20 12h-7a7 7 0 0 0 9 11M44 12h7a7 7 0 0 1-9 11" fill="none" stroke="#3B3B3B" stroke-width="2.2" stroke-linecap="round"/><path d="M28 34h8v8h-8z" fill="#F2B33D" stroke="#3B3B3B" stroke-width="2.2" stroke-linejoin="round"/><rect x="19" y="42" width="26" height="10" rx="3" fill="#E25B3F" stroke="#3B3B3B" stroke-width="2.2"/><path d="M32 12.5l2.3 4.6 5 .7-3.6 3.5.9 5-4.6-2.4-4.6 2.4.9-5-3.6-3.5 5-.7z" fill="#FFF8EF"/></svg>';
var MF_CSS = '.mf{position:fixed;inset:0;z-index:2147483000;pointer-events:none;display:grid;place-items:center;background:radial-gradient(ellipse at 50% 40%,rgba(28,22,20,.58),rgba(16,12,11,.8));opacity:0;transition:opacity .45s ease}'
  + '.mf.on{opacity:1}.mf.sai{opacity:0;transition:opacity .6s ease}'
  + '.mf-c{position:absolute;inset:0;width:100%;height:100%}'
  + '.mf-card{position:relative;display:flex;flex-direction:column;align-items:center;gap:6px;max-width:calc(100vw - 32px);padding:26px 40px 24px;border-radius:30px;background:#FFFBF4;color:#3B3B3B;text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.35),0 0 0 6px rgba(255,251,244,.35);transform:scale(.6) translateY(24px);opacity:0;transition:transform .7s cubic-bezier(.2,1.5,.4,1),opacity .35s ease}'
  + '.mf.on .mf-card{transform:none;opacity:1}'
  + '.mf-ic{width:76px;height:76px;animation:mf-pula 1.3s ease-in-out .5s infinite}'
  + '.mf-card b{font-family:Poppins,Nunito,system-ui,sans-serif;font-weight:800;font-size:clamp(26px,5.2vw,40px);line-height:1.1;color:#E25B3F;letter-spacing:-.01em}'
  + '.mf-card span{font-size:17px;font-weight:700}.mf-card small{font-size:14px;opacity:.7}.mf-card em.mf-onde{font-style:normal;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#6B655E}'
  + '.mf-grande .mf-card{box-shadow:0 30px 80px rgba(0,0,0,.35),0 0 0 6px #F2B33D,0 0 0 12px rgba(255,251,244,.35)}.mf-grande .mf-ic{width:92px;height:92px}.mf-grande .mf-ic svg{width:92px;height:92px}'
  + '@keyframes mf-pula{0%,100%{transform:rotate(0) scale(1)}25%{transform:rotate(-8deg) scale(1.06)}50%{transform:rotate(0) scale(1)}75%{transform:rotate(8deg) scale(1.06)}}'
  + '@media (prefers-reduced-motion:reduce){.mf-ic{animation:none}.mf-card{transition:opacity .3s ease}}';
function mfEsc(t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
function festaMeta(o) {
  o = o || {}; document.querySelectorAll('.mf').forEach(function (x) { x.remove(); });
  if (!document.getElementById('mf-css')) { var st = document.createElement('style'); st.id = 'mf-css'; st.textContent = MF_CSS; document.head.appendChild(st); }
  var leve = !!o.leve || !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  var v = +o.valor || 0, real = function (n) { return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }); };
  var em = String(o.em || ''), h = em.slice(11, 16).replace(/[^\d:]/g, ''), dm = em.slice(8, 10) + '/' + em.slice(5, 7), grande = !!o.grande;
  var linha = [v > 0 ? 'Meta ' + (o.mes ? 'de ' + o.mes + ': ' : 'de ') + real(v) : '', h ? (o.mes && /^\d\d\/\d\d$/.test(dm) ? 'batida em ' + dm + ' às ' + h : 'batida às ' + h) : ''].filter(Boolean).join(' · ');
  var box = document.createElement('div'); box.className = 'mf' + (grande ? ' mf-grande' : ''); box.setAttribute('role', 'status'); box.setAttribute('aria-live', 'polite');
  box.innerHTML = '<canvas class="mf-c" aria-hidden="true"></canvas><div class="mf-card"><div class="mf-ic" aria-hidden="true">' + MF_TROFEU + '</div>' + (o.onde ? '<em class="mf-onde">' + mfEsc(o.onde) + '</em>' : '') + '<b>' + mfEsc(o.titulo || 'Meta do dia batida!') + '</b>' + (linha ? '<span>' + linha + '</span>' : '') + '<small>Parabéns, equipe!</small></div>';
  document.body.appendChild(box);
  var raf = 0, fim = 0, acabou = false;
  function fechar(rapido) {
    if (acabou) return; acabou = true; clearTimeout(fim); document.removeEventListener('pointerdown', toque, true); document.removeEventListener('keydown', tecla, true);
    box.classList.add('sai'); setTimeout(function () { cancelAnimationFrame(raf); box.remove(); }, rapido ? 450 : 650);
  }
  function toque() { fechar(true); }
  function tecla(e) { if (e.key === 'Escape') fechar(true); }
  setTimeout(function () { document.addEventListener('pointerdown', toque, true); document.addEventListener('keydown', tecla, true); }, 0);
  requestAnimationFrame(function () { requestAnimationFrame(function () { box.classList.add('on'); }); });
  fim = setTimeout(function () { fechar(false); }, leve ? 4500 : grande ? 10500 : 7600);
  var cv = box.querySelector('canvas'), ctx = !leve && cv.getContext ? cv.getContext('2d') : null;
  if (!ctx) { cv.remove(); return box; }
  var W = 0, H = 0;
  function tam() { var d = Math.min(2, window.devicePixelRatio || 1); W = innerWidth; H = innerHeight; cv.width = Math.round(W * d); cv.height = Math.round(H * d); ctx.setTransform(d, 0, 0, d, 0, 0); }
  tam();
  var CORES = ['#E25B3F', '#F2B33D', '#6FC3B2', '#B9A6F0', '#FF8FB1', '#5FB8F0', '#FFE27A'], G = Math.max(380, H * 0.62);
  var subindo = [], fagulhas = [], n = 0, total = Math.round((W < 600 ? 18 : 28) * (grande ? 1.7 : 1)), proximo = 0, ult = performance.now(), t0 = ult;
  function lancar() {
    var x = W * (0.1 + Math.random() * 0.8), alvo = H * (0.1 + Math.random() * 0.34);
    subindo.push({ x: x, y: H + 8, vx: (W / 2 - x) * 0.05 * Math.random(), vy: -Math.sqrt(2 * G * (H + 8 - alvo)), cor: CORES[n % CORES.length] }); n++;
  }
  function estourar(f) {
    var q = 90 + Math.floor(Math.random() * 40), vel = Math.min(W, H) * (0.46 + Math.random() * 0.2), outra = CORES[(CORES.indexOf(f.cor) + 3) % CORES.length];
    for (var i = 0; i < q; i++) { var a = Math.PI * 2 * i / q + Math.random() * 0.2, s = vel * (0.35 + Math.random() * 0.65);
      fagulhas.push({ x: f.x, y: f.y, vx: Math.cos(a) * s, vy: Math.sin(a) * s, vida: 1, dura: 1.3 + Math.random() * 0.9, cor: i % 4 === 0 ? outra : f.cor, r: 2 + Math.random() * 2 }); }
  }
  function passo(t) {
    var dt = Math.min(0.05, (t - ult) / 1000); ult = t; var e = t - t0;
    if (n < total && e >= proximo) { lancar(); if (n < 3 || Math.random() < 0.3) { lancar(); } proximo = e + 150 + Math.random() * 230; }
    ctx.globalCompositeOperation = 'destination-out'; ctx.fillStyle = 'rgba(0,0,0,' + Math.min(1, dt * 7) + ')'; ctx.fillRect(0, 0, W, H); ctx.globalCompositeOperation = 'source-over';
    for (var i = subindo.length - 1; i >= 0; i--) { var f = subindo[i]; f.vy += G * dt; f.x += f.vx * dt; f.y += f.vy * dt;
      ctx.fillStyle = '#FFF3D6'; ctx.beginPath(); ctx.arc(f.x, f.y, 2.6, 0, 7); ctx.fill();
      ctx.fillStyle = f.cor; ctx.globalAlpha = 0.6; ctx.beginPath(); ctx.arc(f.x - f.vx * 0.02, f.y + 7, 2, 0, 7); ctx.fill(); ctx.globalAlpha = 1;
      if (f.vy >= -20) { subindo.splice(i, 1); estourar(f); } }
    var arr = Math.pow(0.18, dt);
    for (var j = fagulhas.length - 1; j >= 0; j--) { var p = fagulhas[j]; p.vx *= arr; p.vy = p.vy * arr + G * 0.32 * dt; p.x += p.vx * dt; p.y += p.vy * dt; p.vida -= dt / p.dura;
      if (p.vida <= 0) { fagulhas.splice(j, 1); continue; }
      ctx.globalAlpha = Math.min(1, p.vida * 1.6); ctx.fillStyle = p.cor; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 7); ctx.fill();
      if (p.vida > 0.82) { ctx.fillStyle = '#FFF6E0'; ctx.beginPath(); ctx.arc(p.x, p.y, p.r * 0.4, 0, 7); ctx.fill(); } }
    ctx.globalAlpha = 1;
    if (!acabou || subindo.length || fagulhas.length) raf = requestAnimationFrame(passo);
  }
  raf = requestAnimationFrame(passo);
  addEventListener('resize', tam, { once: true });
  return box;
}
/* Os alertas da meta (1.81): "quando faltar R$ 500" e "quando faltar R$ 200". Quando o dia chega a um alerta, cada tela mostra no alto o
   aviso "Falta pouco para a meta!" com quanto falta, por uns 6 segundos, com uns confetes. Também não trava nada (um toque ou o Esc fecha).
   1.87: com o nome da unidade (o.onde). metaDecidirTodas diz o que uma tela ainda não mostrou, unidade por unidade: a meta do mês batida
   hoje (a festa maior), a meta do dia batida (que já cobre os alertas) ou o alerta mais novo. */
var MA_ALVO = '<svg viewBox="0 0 48 48" width="46" height="46"><circle cx="22" cy="26" r="19" fill="#FFFBF4" stroke="#3B3B3B" stroke-width="2"/><circle cx="22" cy="26" r="12.5" fill="#E25B3F" stroke="#3B3B3B" stroke-width="2"/><circle cx="22" cy="26" r="6.5" fill="#FFFBF4" stroke="#3B3B3B" stroke-width="2"/><circle cx="22" cy="26" r="2.4" fill="#3B3B3B"/><path d="M22 26L40 8" stroke="#3B3B3B" stroke-width="2.4" stroke-linecap="round"/><path d="M37 4l7 0-3 3 3 3-7 0z" fill="#F2B33D" stroke="#3B3B3B" stroke-width="1.5" stroke-linejoin="round"/></svg>';
var MA_CSS = '.ma{position:fixed;left:50%;top:16px;z-index:2147482999;pointer-events:none;width:max-content;max-width:calc(100vw - 32px);transform:translate(-50%,-150%);opacity:0;transition:transform .6s cubic-bezier(.2,1.4,.4,1),opacity .35s ease}'
  + '.ma.on{transform:translate(-50%,0);opacity:1}.ma.sai{transform:translate(-50%,-150%);opacity:0;transition:transform .5s ease-in,opacity .5s ease}'
  + '.ma-card{position:relative;display:flex;align-items:center;gap:14px;padding:14px 24px 14px 16px;border-radius:22px;background:#FFFBF4;color:#3B3B3B;box-shadow:0 18px 50px rgba(0,0,0,.28),0 0 0 3px #F2B33D}'
  + '.ma-card b{display:block;font-family:Poppins,Nunito,system-ui,sans-serif;font-weight:800;font-size:22px;line-height:1.15;color:#E25B3F}'
  + '.ma-card span{display:block;margin-top:3px;font-size:15px;font-weight:700}'
  + '.ma-ic{flex:none;width:46px;height:46px;animation:ma-mexe 1.2s ease-in-out .5s infinite}'
  + '.ma-p{position:absolute;left:50%;top:55%;width:8px;height:12px;border-radius:2px;opacity:0;animation:ma-cai 1.7s cubic-bezier(.2,.6,.4,1) forwards}'
  + '@keyframes ma-mexe{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}'
  + '@keyframes ma-cai{0%{opacity:1;transform:translate(0,0) rotate(0)}100%{opacity:0;transform:translate(var(--dx),var(--dy)) rotate(var(--rr))}}'
  + '@media (prefers-reduced-motion:reduce){.ma-ic{animation:none}.ma-p{display:none}}';
function mfReal(n) { n = +n || 0; return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }); }
function avisoMeta(o) {
  o = o || {}; document.querySelectorAll('.ma').forEach(function (x) { x.remove(); });
  if (!document.getElementById('ma-css')) { var st = document.createElement('style'); st.id = 'ma-css'; st.textContent = MA_CSS; document.head.appendChild(st); }
  var leve = !!o.leve || !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches), f = +o.falta || 0;
  var box = document.createElement('div'); box.className = 'ma'; box.setAttribute('role', 'status'); box.setAttribute('aria-live', 'polite');
  box.innerHTML = '<div class="ma-card"><div class="ma-ic" aria-hidden="true">' + MA_ALVO + '</div><div><b>Falta pouco para a meta!</b><span>' + (f > 0 ? 'Faltam ' + mfReal(f) + ' para a meta do dia' : 'A meta do dia está quase lá') + (o.onde ? ' · ' + mfEsc(o.onde) : '') + '</span></div></div>';
  document.body.appendChild(box);
  if (!leve) { var card = box.querySelector('.ma-card'), CORES = ['#E25B3F', '#F2B33D', '#6FC3B2', '#B9A6F0', '#FF8FB1', '#5FB8F0'];
    for (var i = 0; i < 18; i++) { var p = document.createElement('i'); p.className = 'ma-p'; p.setAttribute('aria-hidden', 'true');
      p.style.cssText = 'background:' + CORES[i % CORES.length] + ';--dx:' + Math.round((Math.random() - 0.5) * 360) + 'px;--dy:' + Math.round(40 + Math.random() * 130) + 'px;--rr:' + Math.round((Math.random() - 0.5) * 720) + 'deg;animation-delay:' + Math.round(350 + Math.random() * 300) + 'ms';
      card.appendChild(p); } }
  var fim = 0, acabou = false;
  function fechar() { if (acabou) return; acabou = true; clearTimeout(fim); document.removeEventListener('pointerdown', fechar, true); document.removeEventListener('keydown', tecla, true); box.classList.add('sai'); setTimeout(function () { box.remove(); }, 550); }
  function tecla(e) { if (e.key === 'Escape') fechar(); }
  setTimeout(function () { document.addEventListener('pointerdown', fechar, true); document.addEventListener('keydown', tecla, true); }, 0);
  requestAnimationFrame(function () { requestAnimationFrame(function () { box.classList.add('on'); }); });
  fim = setTimeout(fechar, 6500);
  return box;
}
/* 1.87: as metas do mês, por unidade (a mesma conta do servidor, metaPlanoDias em lib.php). A meta de cada dia: os dias fixados pelo gestor
   ficam como estão; o resto da meta do mês vai para os outros dias pelo peso (dia de semana 1; sábado, domingo e feriado, o peso do fim de
   semana), em valores redondos de R$ 10; os restos para os dias de fração maior; os centavos, se houver, para o dia de peso maior. */
var META_MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
var META_FERIADOS = { '01-01': 'Ano-novo', '01-25': 'Aniversário de SP', '04-21': 'Tiradentes', '05-01': 'Dia do Trabalho', '07-09': 'Revolução de 32', '09-07': 'Independência',
  '10-12': 'N. Sra. Aparecida', '11-02': 'Finados', '11-15': 'República', '11-20': 'Consciência Negra', '12-25': 'Natal' };
function metaPascoa(a) {   /* o domingo de Páscoa (Meeus/Jones/Butcher): [mês, dia] */
  var b = a % 19, c = Math.floor(a / 100), d = a % 100, e = Math.floor(c / 4), f = c % 4, g = Math.floor((c + 8) / 25), h = Math.floor((c - g + 1) / 3);
  var i = (19 * b + c - e - h + 15) % 30, k = Math.floor(d / 4), l = d % 4, m = (32 + 2 * f + 2 * k - i - l) % 7, n = Math.floor((b + 11 * i + 22 * m) / 451);
  return [Math.floor((i + m - 7 * n + 114) / 31), ((i + m - 7 * n + 114) % 31) + 1];
}
function metaDiaIso(a, m, d) { var x = new Date(Date.UTC(a, m - 1, d)); return x.getUTCFullYear() + '-' + ('0' + (x.getUTCMonth() + 1)).slice(-2) + '-' + ('0' + x.getUTCDate()).slice(-2); }
function metaFeriadoNome(dia) {
  var md = dia.slice(5, 10); if (META_FERIADOS[md]) return META_FERIADOS[md];
  var a = +dia.slice(0, 4), p = metaPascoa(a), mov = [[-48, 'Carnaval'], [-47, 'Carnaval'], [-2, 'Sexta-feira Santa'], [60, 'Corpus Christi']];
  for (var j = 0; j < mov.length; j++) if (metaDiaIso(a, p[0], p[1] + mov[j][0]) === dia) return mov[j][1];
  return '';
}
function metaPlanoDias(p, mes) {
  p = p || {}; var a = +mes.slice(0, 4), m = +mes.slice(5, 7), n = new Date(Date.UTC(a, m, 0)).getUTCDate(), fds = Math.round(Math.min(5, Math.max(1, +(p.fds == null ? 2 : p.fds) || 1)) * 10);
  var fixos = p.fixos || {}, D = [], somaF = 0, W = 0;
  for (var i = 1; i <= n; i++) {
    var dia = mes + '-' + ('0' + i).slice(-2), sd = new Date(Date.UTC(a, m - 1, i)).getUTCDay(), fer = metaFeriadoNome(dia), fv = fixos[dia];
    var f = Object.prototype.hasOwnProperty.call(fixos, dia) && fv !== null && fv !== '' && isFinite(+fv) && +fv >= 0;
    var x = { dia: dia, sem: sd, fer: fer, fixo: f, w: (sd === 0 || sd === 6 || fer) ? fds : 10, c: f ? Math.round(+fv * 100) : 0, u: 0, r: 0 };
    if (f) somaF += x.c; else W += x.w;
    D.push(x);
  }
  var resto = Math.max(0, Math.round((+p.valor || 0) * 100) - somaF), U = Math.floor(resto / 1000), auto = D.filter(function (x) { return !x.fixo; });
  if (auto.length) {
    var dist = 0; auto.forEach(function (x) { x.u = Math.floor(U * x.w / W); x.r = (U * x.w) % W; dist += x.u; });
    auto.slice().sort(function (p1, p2) { return (p2.r - p1.r) || (p1.dia < p2.dia ? -1 : 1); }).slice(0, U - dist).forEach(function (x) { x.u++; });
    auto.forEach(function (x) { x.c = x.u * 1000; });
    var sobra = resto - U * 1000; if (sobra) { var mk = auto[0]; auto.forEach(function (x) { if (x.w > mk.w) mk = x; }); mk.c += sobra; }
  }
  return D.map(function (x) { return { dia: x.dia, sem: x.sem, fer: x.fer, fixo: x.fixo, v: Math.round(x.c) / 100 }; });
}
/* o que esta tela ainda não mostrou (M: as metas de hoje; so: as unidades desta tela, ou null para todas): a meta do mês batida hoje, a meta
   do dia batida ou o alerta mais novo. ks: as marcas para não mostrar de novo; avisos: os alertas que ficam vistos junto. */
function metaDecidirTodas(M, hoje, vistas, vistos, so) {
  if (!M || !Array.isArray(M.unis) || (hoje && M.dia !== hoje)) return null;
  vistas = vistas || []; vistos = vistos || [];
  var U = M.unis.filter(function (u) { return !so || so.indexOf(+u.uni) >= 0; }), i, u;
  var kDia = function (u) { return ['dia', M.dia, u.uni, u.valor, u.em].join('|'); }, kMes = function (u) { return ['mes', u.mes.mes, u.uni, u.mes.valor, u.mes.em].join('|'); };
  var kAv = function (u, a) { return ['av', M.dia, u.uni, u.valor, a.falta, a.em].join('|'); };
  var novos = function (u) { return (u.avisos || []).filter(function (a) { return a.em && vistos.indexOf(kAv(u, a)) < 0; }).map(function (a) { return kAv(u, a); }); };
  for (i = 0; i < U.length; i++) { u = U[i]; if (u.mes && u.mes.batida && u.mes.em && String(u.mes.em).slice(0, 10) === M.dia && vistas.indexOf(kMes(u)) < 0) return { tipo: 'mes', u: u, ks: [kMes(u)].concat(u.batida && u.em ? [kDia(u)] : []), avisos: novos(u) }; }
  for (i = 0; i < U.length; i++) { u = U[i]; if (u.batida && u.em && vistas.indexOf(kDia(u)) < 0) return { tipo: 'meta', u: u, ks: [kDia(u)], avisos: novos(u) }; }
  for (i = 0; i < U.length; i++) { u = U[i]; var nv = novos(u); if (nv.length && !u.batida) return { tipo: 'aviso', u: u, ks: [], avisos: nv, falta: u.resta != null ? +u.resta : Math.min.apply(null, u.avisos.map(function (a) { return +a.falta; })) }; }
  return null;
}
function metaMostrar(x, leve, comNome) {   /* a comemoração (ou o alerta) de uma decisão de metaDecidirTodas */
  var u = x.u || {}, m = u.mes || {}, onde = comNome ? u.nome || '' : '';
  if (x.tipo === 'mes') return festaMeta({ titulo: 'Meta do mês batida!', onde: onde, valor: m.valor, em: m.em, mes: META_MESES[+String(m.mes || '').slice(5, 7) - 1] || '', grande: true, leve: leve });
  if (x.tipo === 'meta') return festaMeta({ onde: onde, valor: u.valor, em: u.em, leve: leve });
  return avisoMeta({ falta: x.falta, onde: onde, leve: leve });
}
(() => {
  let ocupado = false;
  const dentro = () => { try { return !!EU; } catch (e) { return false; } };
  const lerL = (k, p) => { try { const v = JSON.parse(localStorage.getItem(k) || 'null'); return Array.isArray(v) ? v : p; } catch (e) { return p; } };
  async function olhar() {
    if (document.hidden || ocupado || !dentro()) return; ocupado = true;
    try { const m = (await api('meta')).meta, vistas = lerL('psfin-meta-vistas', []), vistos = lerL('psfin-meta-avisos', []);
      const x = metaDecidirTodas(m, '', vistas, vistos, null); if (!x) return;
      try { if (x.avisos.length) localStorage.setItem('psfin-meta-avisos', JSON.stringify(vistos.concat(x.avisos).slice(-60))); if (x.ks.length) localStorage.setItem('psfin-meta-vistas', JSON.stringify(vistas.concat(x.ks).slice(-60))); } catch (e) { /* sem memória */ }
      metaMostrar(x, false, true); }
    catch (e) { /* sem internet ou fora: tenta de novo depois */ } finally { ocupado = false; }
  }
  setInterval(olhar, 20000); setTimeout(olhar, 3000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) olhar(); });
})();
