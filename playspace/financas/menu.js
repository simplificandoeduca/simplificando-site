/* Play Space Finanças 3.2 (Play Space 1.73): o menu da barra escura.
   Pedido do dono: "esse menu" (o vídeo "Dashboard V18": a barra escura e arredondada do lado, só com os ícones). No computador:
   - a barra cresce quando as Finanças abrem, e os ícones aparecem um depois do outro;
   - o item aberto fica num círculo claro, que desliza até o item novo quando a aba muda;
   - ao passar o mouse (ou com o Tab do teclado), o nome aparece numa etiqueta escura ao lado do ícone.
   O menu continua com os mesmos botões (data-aba) e os nomes ficam no botão para o leitor de tela. No celular, a barra de baixo de sempre.
   Com "reduzir movimento" no aparelho, a barra já aparece pronta e o círculo pula direto para o item. */
'use strict';
(() => {
  const calmo = () => !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  let nav = null, dica = null, obs = null;
  function poeAtivo(direto) {
    if (!nav) return; const L = nav.querySelector('.rail-lista'), mk = nav.querySelector('.rail-ativo'), a = nav.querySelector('.rail-lista .aba.ativa'); if (!L || !mk) return;
    if (!a) { mk.classList.remove('on'); return; }
    const pula = direto || calmo() || !mk.classList.contains('on'); if (pula) mk.classList.remove('pronto');
    mk.style.setProperty('--y', a.offsetTop + 'px'); mk.classList.add('on');
    if (pula) requestAnimationFrame(() => requestAnimationFrame(() => { if (!calmo()) mk.classList.add('pronto'); }));
    const t = a.offsetTop, b = t + a.offsetHeight; if (t < L.scrollTop || b > L.scrollTop + L.clientHeight) L.scrollTo({ top: Math.max(0, t - L.clientHeight / 2), behavior: calmo() ? 'auto' : 'smooth' });
  }
  function mostra(el) {
    const t = el && el.getAttribute('data-dica'); if (!t || !nav || getComputedStyle(nav).display === 'none') return;
    if (!dica) { dica = document.createElement('div'); dica.className = 'rail-dica'; dica.setAttribute('role', 'tooltip'); dica.setAttribute('aria-hidden', 'true'); document.body.appendChild(dica); }
    const r = el.getBoundingClientRect(), rn = nav.getBoundingClientRect(); dica.textContent = t;
    dica.style.left = Math.round(rn.right + 12) + 'px'; dica.style.top = Math.round(r.top + r.height / 2) + 'px'; dica.classList.add('on');
  }
  const some = () => { if (dica) dica.classList.remove('on'); };
  function pontas() { const L = nav && nav.querySelector('.rail-lista'); if (!L) return; L.classList.toggle('tem-cima', L.scrollTop > 2); L.classList.toggle('tem-baixo', L.scrollTop + L.clientHeight < L.scrollHeight - 2); }   /* a lista que não cabe: a ponta esmaece */
  function ligar(n) {
    nav = n; if (obs) obs.disconnect();
    const itens = [...nav.querySelectorAll('.marca, .rail-lista > *:not(.rail-ativo), .quem')]; itens.forEach((e, i) => e.style.setProperty('--k', Math.min(i, 24)));
    if (!calmo()) { nav.classList.add('rail-entra'); setTimeout(() => { if (nav === n) nav.classList.remove('rail-entra'); }, 1700); }
    nav.addEventListener('mouseover', e => { const el = e.target.closest('[data-dica]'); if (el && nav.contains(el)) mostra(el); });
    nav.addEventListener('mouseleave', some); nav.addEventListener('focusin', e => { const el = e.target.closest('[data-dica]'); if (el) mostra(el); }); nav.addEventListener('focusout', some);
    nav.addEventListener('click', some); const L = nav.querySelector('.rail-lista'); if (L) L.addEventListener('scroll', () => { some(); pontas(); }, { passive: true });
    obs = new MutationObserver(recs => { if (recs.some(r => r.target.classList && r.target.classList.contains('aba'))) poeAtivo(false); });   /* só a troca de aba (não as classes do próprio círculo) */
    obs.observe(nav, { attributes: true, attributeFilter: ['class'], subtree: true });
    requestAnimationFrame(() => { poeAtivo(true); pontas(); });
  }
  const procura = () => { const n = document.querySelector('nav.lateral'); if (n && n !== nav && n.querySelector('.rail-lista')) ligar(n); };
  window.addEventListener('resize', () => { some(); poeAtivo(true); pontas(); });
  const comecar = () => { new MutationObserver(procura).observe(document.body, { childList: true, subtree: true }); procura(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', comecar); else comecar();
})();
