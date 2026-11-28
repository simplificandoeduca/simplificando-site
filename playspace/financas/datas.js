/* 2.63 (o dono: "melhore o campo de todas as datas. Não tá conversando com o sistema. A data, ela tem que ser na mesma dinâmica. Tipo,
   bonitinho, meio abaulado"): o calendário do Play Space (o mesmo no Play Space e nas Finanças). Todo campo de data (input type=date)
   ganha um botão com a cara do sistema (abaulado, a data escrita dd/mm/aaaa e o ícone do calendário) e, no toque, o calendário próprio
   (o mês em grade, os dias redondos, o dia escolhido em coral, "Hoje" e "Limpar"), no lugar do calendário do navegador. O campo de verdade
   continua no lugar, escondido: o valor (aaaa-mm-dd), o mínimo, o máximo e os avisos (input e change) são os de sempre. No teclado: as
   setas andam pelos dias, PageUp e PageDown trocam o mês, Enter escolhe, Esc fecha */
(function () {
  if (window.PS_DATAS) return; window.PS_DATAS = 1;
  const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'], SEM = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
  const dois = n => String(n).padStart(2, '0'), iso = d => d.getFullYear() + '-' + dois(d.getMonth() + 1) + '-' + dois(d.getDate());
  const deIso = s => { const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(s || '')); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; };
  const br = s => { const d = deIso(s); return d ? dois(d.getDate()) + '/' + dois(d.getMonth() + 1) + '/' + d.getFullYear() : ''; };
  const hojeIso = () => iso(typeof window.psAgora === 'function' ? window.psAgora() : new Date());
  const ICO = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="3.5"/><path d="M8 3v4M16 3v4M3.5 10.5h17"/><circle cx="12" cy="15.2" r="1.3"/></svg>';
  const SETA = d => `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${d < 0 ? 'M14.5 6l-6 6 6 6' : 'M9.5 6l6 6-6 6'}"/></svg>`;
  const est = document.createElement('style');
  est.textContent = `
.dp-oculto { position: absolute !important; width: 1px !important; height: 1px !important; min-height: 0 !important; opacity: 0 !important; pointer-events: none !important; margin: 0 !important; padding: 0 !important; border: 0 !important; overflow: hidden !important; }
.dp-campo { display: inline-flex; align-items: center; gap: 8px; min-height: 42px; padding: 0 16px 0 12px; border-radius: 999px; border: 1.5px solid var(--borda-2, rgba(59, 59, 59, .16)); background: var(--card, #FFFBF4); color: var(--tinta, var(--ink, #3B3B3B)); font: 700 14.5px/1.1 var(--texto, "Nunito", sans-serif); letter-spacing: .01em; cursor: pointer; text-align: left; white-space: nowrap; font-variant-numeric: tabular-nums; transition: border-color .15s, box-shadow .15s, background .15s; -webkit-tap-highlight-color: transparent; vertical-align: middle; max-width: 100%; }
.dp-campo svg { width: 18px; height: 18px; flex: none; fill: none; stroke: var(--coral, var(--brand, #E25B3F)); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.dp-campo .dp-vazio { color: var(--apagado, var(--ink-3, #A39B91)); font-weight: 600; }
.dp-campo:hover { border-color: var(--coral, var(--brand, #E25B3F)); }
.dp-campo:focus-visible, .dp-campo.aberto { outline: none; border-color: var(--coral, var(--brand, #E25B3F)); box-shadow: 0 0 0 4px var(--coral-2, var(--brand-bg, #F9D9D0)); }
.dp-campo:disabled { opacity: .5; cursor: default; }
.dp-campo.dp-largo { display: flex; width: 100%; }
.dp-campo.dp-liso { border: 0; background: transparent; box-shadow: none; min-height: 0; padding: 0 4px; color: inherit; gap: 6px; }
.dp-campo.dp-liso svg { stroke: currentColor; }
#dp-pop { position: fixed; z-index: 2147483000; width: 304px; max-width: calc(100vw - 24px); box-sizing: border-box; background: var(--card, #FFFBF4); color: var(--tinta, var(--ink, #3B3B3B)); border: 1px solid var(--borda, rgba(59, 59, 59, .09)); border-radius: 22px; box-shadow: 0 2px 6px rgba(59, 59, 59, .06), 0 24px 60px rgba(120, 80, 50, .22); padding: 14px 14px 12px; font-family: var(--texto, "Nunito", sans-serif); animation: dpEntra .18s cubic-bezier(.32, .72, 0, 1); }
@keyframes dpEntra { from { opacity: 0; transform: translateY(-6px) scale(.98); } }
@media (prefers-reduced-motion: reduce) { #dp-pop { animation: none; } }
#dp-pop button { font-family: inherit; -webkit-tap-highlight-color: transparent; }
.dp-cab { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.dp-cab b { flex: 1; text-align: center; font: 700 16px/1.2 var(--display, "Poppins", sans-serif); letter-spacing: -.01em; }
.dp-nav { width: 36px; height: 36px; flex: none; border-radius: 50%; border: 0; background: var(--creme, var(--soft, #F4EDE2)); color: inherit; display: grid; place-items: center; cursor: pointer; padding: 0; }
.dp-nav svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
.dp-nav:hover { background: var(--coral-2, var(--brand-bg, #F9D9D0)); color: var(--coral, var(--brand, #E25B3F)); }
.dp-nav:disabled { opacity: .35; cursor: default; background: transparent; color: inherit; }
.dp-sem, .dp-dias { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 2px; }
.dp-sem span { text-align: center; font: 800 11.5px/28px var(--texto, "Nunito", sans-serif); color: var(--tinta-2, var(--ink-2, #6B655E)); letter-spacing: .04em; }
.dp-dia { width: 100%; max-width: 40px; aspect-ratio: 1; justify-self: center; border-radius: 50%; border: 0; background: transparent; color: inherit; font: 700 14px/1 var(--texto, "Nunito", sans-serif); font-variant-numeric: tabular-nums; cursor: pointer; padding: 0; transition: background .12s, color .12s; }
.dp-dia:hover { background: var(--creme, var(--soft, #F4EDE2)); }
.dp-dia.dp-hj { box-shadow: inset 0 0 0 1.5px var(--coral, var(--brand, #E25B3F)); color: var(--coral, var(--brand, #E25B3F)); }
.dp-dia.sel, .dp-dia.sel:hover { background: var(--coral, var(--brand, #E25B3F)); color: #fff; box-shadow: none; }
.dp-dia:disabled, .dp-dia:disabled:hover { opacity: .32; cursor: default; background: transparent; box-shadow: none; color: inherit; }
.dp-dia:focus-visible, .dp-nav:focus-visible, .dp-pe button:focus-visible { outline: 2px solid var(--coral, var(--brand, #E25B3F)); outline-offset: 2px; }
.dp-pe { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--borda, rgba(59, 59, 59, .09)); }
.dp-pe button { border: 0; border-radius: 999px; padding: 8px 16px; font: 800 13.5px/1 var(--texto, "Nunito", sans-serif); cursor: pointer; }
.dp-hoje { background: var(--coral-2, var(--brand-bg, #F9D9D0)); color: var(--erro, var(--brand-2, #B8402A)); }
.dp-hoje:disabled { opacity: .4; cursor: default; }
.dp-limpa { background: transparent; color: var(--tinta-2, var(--ink-2, #6B655E)); }
.dp-limpa:hover { background: var(--creme, var(--soft, #F4EDE2)); }
@media print { .dp-campo svg { display: none; } #dp-pop { display: none; } }`;
  document.head.appendChild(est);
  let POP = null;
  function rotulo(b, i) {
    const v = br(i.value), vazio = i.getAttribute('placeholder') || 'Escolher a data';
    b.innerHTML = ICO + (v ? '<span>' + v + '</span>' : '<span class="dp-vazio">' + vazio + '</span>'); b.dataset.v = i.value; b.disabled = !!i.disabled;
  }
  function vestir(i) {
    const cs = getComputedStyle(i), pai = i.parentElement, b = document.createElement('button');
    b.type = 'button'; b.className = 'dp-campo'; b.setAttribute('aria-haspopup', 'dialog'); b.setAttribute('aria-expanded', 'false');
    let nome = i.getAttribute('aria-label') || ''; if (!nome && i.labels && i.labels[0]) nome = i.labels[0].textContent.replace(/\s+/g, ' ').trim(); b.setAttribute('aria-label', (nome || 'Data') + ': escolher a data');
    const liso = parseFloat(cs.borderTopWidth) === 0 && /rgba\(0, 0, 0, 0\)|transparent/.test(cs.backgroundColor), r = parseFloat(cs.borderTopLeftRadius) || 0, h = parseFloat(cs.height) || 0;
    if (liso) b.classList.add('dp-liso');
    else {
      const larg = (pai && i.offsetWidth > 0 && Math.abs(i.offsetWidth - pai.clientWidth) < 4) || i.classList.contains('inp') || !!(pai && pai.classList.contains('campo'));
      if (larg) b.classList.add('dp-largo');
      if (h >= 30) b.style.minHeight = Math.round(h) + 'px';
      if (larg && r >= 8) b.style.borderRadius = Math.round(r) + 'px';   /* (no formulário, o mesmo canto dos outros campos; solto, abaulado) */
    }
    i.dataset.dp = '1'; i.classList.add('dp-oculto'); i.tabIndex = -1; b._dpIn = i; i._dpBtn = b; i.after(b); rotulo(b, i);
  }
  function montar() {
    document.querySelectorAll('input[type="date"]:not([data-dp])').forEach(vestir);
    document.querySelectorAll('input[data-dp]').forEach(i => { const b = i._dpBtn; if (b && (b.dataset.v !== i.value || b.disabled !== !!i.disabled)) rotulo(b, i); });
    if (POP && !POP.b.isConnected) { const n = POP.i.id ? document.getElementById(POP.i.id) : null; if (n && n._dpBtn) { POP.i = n; POP.b = n._dpBtn; n._dpBtn.classList.add('aberto'); posicionar(); } else fechar(); }
  }
  function fechar() { if (!POP) return; const P = POP; POP = null; P.p.remove(); P.b.classList.remove('aberto'); P.b.setAttribute('aria-expanded', 'false'); }
  function abrir(i, b) {
    fechar(); if (!i || i.disabled) return; const v = deIso(i.value) || deIso(hojeIso());
    const p = document.createElement('div'); p.id = 'dp-pop'; p.setAttribute('role', 'dialog'); p.setAttribute('aria-label', 'Escolher a data');
    POP = { i, b, p, mes: new Date(v.getFullYear(), v.getMonth(), 1), foco: iso(v) };
    ['pointerdown', 'mousedown', 'touchstart'].forEach(ev => p.addEventListener(ev, e => e.stopPropagation()));
    p.addEventListener('click', clicou); p.addEventListener('keydown', tecla);
    p.addEventListener('focusout', () => setTimeout(() => { if (POP && POP.p === p && !p.contains(document.activeElement) && document.activeElement !== POP.b && document.hasFocus()) fechar(); }, 0));
    document.body.appendChild(p); b.classList.add('aberto'); b.setAttribute('aria-expanded', 'true'); desenhar('dia'); posicionar();
  }
  function desenhar(foco) {
    const P = POP; if (!P) return; const i = P.i, min = i.min || '', max = i.max || '', sel = i.value || '', hj = hojeIso();
    const ano = P.mes.getFullYear(), mes = P.mes.getMonth(), ini = new Date(ano, mes, 1).getDay(), n = new Date(ano, mes + 1, 0).getDate();
    const antes = !min || iso(new Date(ano, mes, 0)) >= min, depois = !max || iso(new Date(ano, mes + 1, 1)) <= max, fora = s => (min && s < min) || (max && s > max);
    let dias = ''; for (let k = 0; k < ini; k++) dias += '<span></span>';
    for (let d = 1; d <= n; d++) { const s = iso(new Date(ano, mes, d));
      dias += `<button type="button" class="dp-dia${s === hj ? ' dp-hj' : ''}${s === sel ? ' sel' : ''}" data-d="${s}" tabindex="${s === P.foco ? 0 : -1}"${fora(s) ? ' disabled' : ''} aria-label="${d} de ${MESES[mes]} de ${ano}"${s === sel ? ' aria-pressed="true"' : ''}>${d}</button>`; }
    const nome = MESES[mes].charAt(0).toUpperCase() + MESES[mes].slice(1);
    P.p.innerHTML = `<div class="dp-cab"><button type="button" class="dp-nav" data-m="-1" aria-label="Mês anterior"${antes ? '' : ' disabled'}>${SETA(-1)}</button><b aria-live="polite">${nome} de ${ano}</b><button type="button" class="dp-nav" data-m="1" aria-label="Próximo mês"${depois ? '' : ' disabled'}>${SETA(1)}</button></div>`
      + `<div class="dp-sem" aria-hidden="true">${SEM.map(x => '<span>' + x + '</span>').join('')}</div><div class="dp-dias">${dias}</div>`
      + `<div class="dp-pe"><button type="button" class="dp-hoje" data-d="${hj}"${fora(hj) ? ' disabled' : ''}>Hoje</button><button type="button" class="dp-limpa" data-limpa="1">Limpar</button></div>`;
    if (foco === 'dia') { const f = P.p.querySelector(`.dp-dia[data-d="${P.foco}"]:not(:disabled)`) || P.p.querySelector('.dp-dia:not(:disabled)') || P.p.querySelector('.dp-nav:not(:disabled)'); if (f) f.focus({ preventScroll: true }); }
    else if (foco) { const f = P.p.querySelector(`.dp-nav[data-m="${foco}"]`); if (f && !f.disabled) f.focus({ preventScroll: true }); else { const d = P.p.querySelector('.dp-dia:not(:disabled)'); if (d) d.focus({ preventScroll: true }); } }
  }
  function mudarMes(k, foco) { const P = POP; if (!P) return; P.mes = new Date(P.mes.getFullYear(), P.mes.getMonth() + k, 1); const f = deIso(P.foco) || P.mes, ult = new Date(P.mes.getFullYear(), P.mes.getMonth() + 1, 0).getDate();
    P.foco = iso(new Date(P.mes.getFullYear(), P.mes.getMonth(), Math.min(f.getDate(), ult))); desenhar(foco); posicionar(); }
  function escolher(v) {
    const P = POP; if (!P) return; let i = P.i; if (!i.isConnected && i.id) i = document.getElementById(i.id) || i;
    i.value = v; i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true }));
    const b = i._dpBtn || P.b; fechar(); if (b && b.isConnected) { rotulo(b, i); b.focus({ preventScroll: true }); }
  }
  function clicou(e) {
    e.stopPropagation(); const t = e.target.closest('button'); if (!t || t.disabled || !POP) return;
    if (t.dataset.m) { mudarMes(+t.dataset.m, t.dataset.m); return; }
    if (t.dataset.limpa) { escolher(''); return; }
    if (t.dataset.d) escolher(t.dataset.d);
  }
  function tecla(e) {
    e.stopPropagation(); if (!POP) return; const t = e.target;
    if (e.key === 'Escape') { e.preventDefault(); const b = POP.b; fechar(); if (b && b.isConnected) b.focus({ preventScroll: true }); return; }
    if (e.key === 'PageUp' || e.key === 'PageDown') { e.preventDefault(); mudarMes(e.key === 'PageUp' ? -1 : 1, 'dia'); return; }
    const passo = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key]; if (!passo || !t.classList.contains('dp-dia')) return;
    e.preventDefault(); const d = deIso(t.dataset.d); d.setDate(d.getDate() + passo); POP.foco = iso(d);
    if (d.getMonth() !== POP.mes.getMonth() || d.getFullYear() !== POP.mes.getFullYear()) POP.mes = new Date(d.getFullYear(), d.getMonth(), 1);
    desenhar('dia');
  }
  function posicionar() {
    const P = POP; if (!P) return; if (!P.b.isConnected) { fechar(); return; }
    const r = P.b.getBoundingClientRect(), w = P.p.offsetWidth, h = P.p.offsetHeight, vw = window.innerWidth, vh = window.innerHeight;
    let x = Math.min(Math.max(12, r.left), vw - w - 12), y = r.bottom + 8;
    if (y + h > vh - 12 && r.top - h - 8 >= 12) y = r.top - h - 8; if (y + h > vh - 12) y = Math.max(12, vh - h - 12);
    P.p.style.left = Math.round(x) + 'px'; P.p.style.top = Math.round(y) + 'px';
  }
  document.addEventListener('click', e => {
    const t = e.target; if (!t || !t.closest) return; if (t.closest('#dp-pop')) return;
    const b = t.closest('.dp-campo'); if (b && b._dpIn) { e.preventDefault(); if (POP && POP.b === b) fechar(); else abrir(b._dpIn, b); return; }
    if (t.matches && t.matches('input[type="date"][data-dp]') && t._dpBtn) { e.preventDefault(); abrir(t, t._dpBtn); return; }
    if (POP) fechar();
  }, true);
  document.addEventListener('input', e => { const i = e.target; if (i && i._dpBtn && i._dpBtn.dataset.v !== i.value) rotulo(i._dpBtn, i); }, true);
  document.addEventListener('change', e => { const i = e.target; if (i && i._dpBtn && i._dpBtn.dataset.v !== i.value) rotulo(i._dpBtn, i); }, true);
  window.addEventListener('resize', () => posicionar());
  document.addEventListener('scroll', () => { if (POP) posicionar(); }, true);
  let pend = false; const mo = new MutationObserver(() => { if (pend) return; pend = true; requestAnimationFrame(() => { pend = false; montar(); }); });
  const ligar = () => { montar(); mo.observe(document.body, { childList: true, subtree: true }); };
  if (document.body) ligar(); else document.addEventListener('DOMContentLoaded', ligar);
  window.psDatasFechar = fechar;
})();
