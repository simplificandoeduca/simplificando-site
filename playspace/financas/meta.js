/* Play Space Finanças 3.4 (Play Space 1.80): a meta do dia.
   Pedido do dono: quando a loja bater a meta do dia (o valor que o gestor coloca em Ajustes, no sistema), "todos os computadores que estiverem
   com o sistema ligado, inclusive o financeiro: meta do dia batida e vários foguinhos". Com as Finanças abertas (e com alguém dentro), a
   página pergunta ao servidor a cada 20 segundos; na primeira vez do dia em que a meta aparece batida, comemora (uma vez por dia neste
   aparelho). A comemoração é a mesma do sistema. */
'use strict';
/* A comemoração da meta do dia (a mesma no sistema e nas Finanças). Por cima de tudo, SEM travar a tela: os toques passam para o que está
   embaixo, e um toque em qualquer lugar (ou o Esc) fecha. A tela escurece um pouco, os foguetes sobem e estouram em fogos por uns 7
   segundos, e no meio o recado "Meta do dia batida!", com a meta e a hora. Com "reduzir movimento" (ou "Animações: leves"), só o recado. */
var MF_TROFEU = '<svg viewBox="0 0 64 64" width="76" height="76"><path d="M20 8h24v14a12 12 0 0 1-24 0z" fill="#F2B33D" stroke="#3B3B3B" stroke-width="2.2" stroke-linejoin="round"/><path d="M20 12h-7a7 7 0 0 0 9 11M44 12h7a7 7 0 0 1-9 11" fill="none" stroke="#3B3B3B" stroke-width="2.2" stroke-linecap="round"/><path d="M28 34h8v8h-8z" fill="#F2B33D" stroke="#3B3B3B" stroke-width="2.2" stroke-linejoin="round"/><rect x="19" y="42" width="26" height="10" rx="3" fill="#E25B3F" stroke="#3B3B3B" stroke-width="2.2"/><path d="M32 12.5l2.3 4.6 5 .7-3.6 3.5.9 5-4.6-2.4-4.6 2.4.9-5-3.6-3.5 5-.7z" fill="#FFF8EF"/></svg>';
var MF_CSS = '.mf{position:fixed;inset:0;z-index:2147483000;pointer-events:none;display:grid;place-items:center;background:radial-gradient(ellipse at 50% 40%,rgba(28,22,20,.58),rgba(16,12,11,.8));opacity:0;transition:opacity .45s ease}'
  + '.mf.on{opacity:1}.mf.sai{opacity:0;transition:opacity .6s ease}'
  + '.mf-c{position:absolute;inset:0;width:100%;height:100%}'
  + '.mf-card{position:relative;display:flex;flex-direction:column;align-items:center;gap:6px;max-width:calc(100vw - 32px);padding:26px 40px 24px;border-radius:30px;background:#FFFBF4;color:#3B3B3B;text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.35),0 0 0 6px rgba(255,251,244,.35);transform:scale(.6) translateY(24px);opacity:0;transition:transform .7s cubic-bezier(.2,1.5,.4,1),opacity .35s ease}'
  + '.mf.on .mf-card{transform:none;opacity:1}'
  + '.mf-ic{width:76px;height:76px;animation:mf-pula 1.3s ease-in-out .5s infinite}'
  + '.mf-card b{font-family:Poppins,Nunito,system-ui,sans-serif;font-weight:800;font-size:clamp(26px,5.2vw,40px);line-height:1.1;color:#E25B3F;letter-spacing:-.01em}'
  + '.mf-card span{font-size:17px;font-weight:700}.mf-card small{font-size:14px;opacity:.7}'
  + '@keyframes mf-pula{0%,100%{transform:rotate(0) scale(1)}25%{transform:rotate(-8deg) scale(1.06)}50%{transform:rotate(0) scale(1)}75%{transform:rotate(8deg) scale(1.06)}}'
  + '@media (prefers-reduced-motion:reduce){.mf-ic{animation:none}.mf-card{transition:opacity .3s ease}}';
function festaMeta(o) {
  o = o || {}; document.querySelectorAll('.mf').forEach(function (x) { x.remove(); });
  if (!document.getElementById('mf-css')) { var st = document.createElement('style'); st.id = 'mf-css'; st.textContent = MF_CSS; document.head.appendChild(st); }
  var leve = !!o.leve || !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  var v = +o.valor || 0, real = function (n) { return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }); };
  var h = String(o.em || '').slice(11, 16).replace(/[^\d:]/g, ''), linha = [v > 0 ? 'Meta de ' + real(v) : '', h ? 'batida às ' + h : ''].filter(Boolean).join(' · ');
  var box = document.createElement('div'); box.className = 'mf'; box.setAttribute('role', 'status'); box.setAttribute('aria-live', 'polite');
  box.innerHTML = '<canvas class="mf-c" aria-hidden="true"></canvas><div class="mf-card"><div class="mf-ic" aria-hidden="true">' + MF_TROFEU + '</div><b>Meta do dia batida!</b>' + (linha ? '<span>' + linha + '</span>' : '') + '<small>Parabéns, equipe!</small></div>';
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
  fim = setTimeout(function () { fechar(false); }, leve ? 4500 : 7600);
  var cv = box.querySelector('canvas'), ctx = !leve && cv.getContext ? cv.getContext('2d') : null;
  if (!ctx) { cv.remove(); return box; }
  var W = 0, H = 0;
  function tam() { var d = Math.min(2, window.devicePixelRatio || 1); W = innerWidth; H = innerHeight; cv.width = Math.round(W * d); cv.height = Math.round(H * d); ctx.setTransform(d, 0, 0, d, 0, 0); }
  tam();
  var CORES = ['#E25B3F', '#F2B33D', '#6FC3B2', '#B9A6F0', '#FF8FB1', '#5FB8F0', '#FFE27A'], G = Math.max(380, H * 0.62);
  var subindo = [], fagulhas = [], n = 0, total = W < 600 ? 18 : 28, proximo = 0, ult = performance.now(), t0 = ult;
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
(() => {
  let ocupado = false;
  const dentro = () => { try { return !!EU; } catch (e) { return false; } };
  async function olhar() {
    if (document.hidden || ocupado || !dentro()) return; ocupado = true;
    try { const m = (await api('meta')).meta; if (!m || !m.batida || !m.em) return;
      const k = [m.dia, m.em, m.valor || ''].join('|'); let ja = ''; try { ja = localStorage.getItem('psfin-meta-vista') || ''; } catch (e) { /* sem memória */ }
      if (ja === k) return; try { localStorage.setItem('psfin-meta-vista', k); } catch (e) { /* sem memória */ }
      festaMeta({ valor: m.valor, em: m.em }); }
    catch (e) { /* sem internet ou fora: tenta de novo depois */ } finally { ocupado = false; }
  }
  setInterval(olhar, 20000); setTimeout(olhar, 3000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) olhar(); });
})();
