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
    const t = el && el.getAttribute('data-dica'); if (!t || !nav || getComputedStyle(nav).display === 'none' || nav.classList.contains('aberta')) return;   /* 4.5: aberto, o nome já está à vista */
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
    requestAnimationFrame(() => { poeAtivo(true); pontas(); }); ligarAbre();   /* 4.5 */
  }

  /* 4.5 (1.94): o menu do lado como o vídeo "Glassy Sidebar" do dono: a logo abre a barra num painel (por cima da tela), com os nomes; a luz
     clara desliza atrás do item sob o mouse; escolher uma tela, tocar fora ou Esc fecha. Aberto, a etiqueta do nome não aparece */
  const aberta = () => !!(nav && nav.classList.contains('aberta'));
  function fecha() {
    const f = document.getElementById('rail-fundo'); if (f) f.remove(); document.querySelectorAll('nav.lateral.aberta').forEach(x => { if (x !== nav) x.classList.remove('aberta'); });
    if (!aberta()) return; nav.classList.remove('aberta');
    const m = nav.querySelector('.marca'); if (m) { m.setAttribute('aria-expanded', 'false'); m.setAttribute('data-dica', m.dataset.dica0 || m.getAttribute('data-dica') || ''); }
    const luz = nav.querySelector('.rail-luz'); if (luz) luz.classList.remove('on'); poeAtivo(true); pontas();
  }
  function abre() {
    if (!nav || aberta() || innerWidth <= 820) return; some(); nav.classList.add('aberta');
    const f = document.createElement('div'); f.id = 'rail-fundo'; f.className = 'rail-fundo'; f.setAttribute('aria-hidden', 'true'); f.addEventListener('click', fecha); document.body.appendChild(f);
    const m = nav.querySelector('.marca'); if (m) m.setAttribute('aria-expanded', 'true'); poeAtivo(true); pontas();
  }
  function ligarAbre() {
    const f0 = document.getElementById('rail-fundo'); if (f0 && !aberta()) f0.remove();   /* a barra foi trocada por uma nova (fechada): o fundo da antiga sai */
    const m = nav.querySelector('.marca'); if (!m || m.dataset.abre) return; m.dataset.abre = '1'; m.dataset.dica0 = m.getAttribute('data-dica') || '';
    m.setAttribute('role', 'button'); m.setAttribute('tabindex', '0'); m.setAttribute('aria-expanded', 'false'); m.setAttribute('aria-label', 'Abrir ou fechar o menu');
    m.addEventListener('click', () => { if (aberta()) fecha(); else abre(); });
    m.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (aberta()) fecha(); else abre(); } });
    nav.addEventListener('click', e => { if (aberta() && e.target.closest('.rail-lista [data-aba], .quem')) setTimeout(fecha, 0); });
    const L = nav.querySelector('.rail-lista');
    if (L) L.addEventListener('mouseover', e => { if (!aberta()) return; const a = e.target.closest('.aba'); let luz = L.querySelector('.rail-luz');
      if (!luz) { luz = document.createElement('span'); luz.className = 'rail-luz'; luz.setAttribute('aria-hidden', 'true'); L.prepend(luz); }
      if (!a) return; luz.style.setProperty('--y', a.offsetTop + 'px'); luz.style.setProperty('--h', a.offsetHeight + 'px'); luz.classList.add('on'); });
    if (L) L.addEventListener('mouseleave', () => { const luz = L.querySelector('.rail-luz'); if (luz) luz.classList.remove('on'); });
  }
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && aberta()) fecha(); });
  const procura = () => { const n = document.querySelector('nav.lateral'); if (n && n !== nav && n.querySelector('.rail-lista')) ligar(n); };
  window.addEventListener('resize', () => { some(); if (innerWidth <= 820) fecha(); poeAtivo(true); pontas(); });
  const comecar = () => { new MutationObserver(procura).observe(document.body, { childList: true, subtree: true }); procura(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', comecar); else comecar();
})();
