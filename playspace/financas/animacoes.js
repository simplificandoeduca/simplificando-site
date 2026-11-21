/* Play Space Finanças 3.1 (Play Space 1.72): as animações do painel.
   Pedido do dono: "quero o financeiro com essas animações" (o vídeo "Dashboard V18": os cartões que entram um a um e sobem ao passar o
   mouse, os números que correm até o valor, os gráficos que crescem). Vale para todas as abas, sem mexer em como cada tela é desenhada:
   - a entrada: ao abrir uma aba, os cartões (os números de cima e os quadros) sobem e aparecem um depois do outro;
   - os números dos cartões correm do valor anterior até o novo (na primeira vez, do zero). Quando a tela é redesenhada com o mesmo
     número (o recarregar de cada minuto), nada se mexe;
   - os gráficos: as barras crescem de baixo para cima, a linha do resultado se desenha e as bolinhas aparecem; só quando o gráfico muda;
   - ao passar o mouse, o cartão do número sobe um pouco (no CSS).
   Quem pediu menos movimento no aparelho (Windows, Mac, celular: "reduzir movimento") não vê nenhuma animação. */
'use strict';
(() => {
  const calmo = () => !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
  const VISTO = new Map();          /* aba|rótulo do cartão → o último número mostrado */
  const GRAF = new Map();           /* aba|qual gráfico → a assinatura do último desenhado */
  const CORRENDO = new WeakSet();   /* os números que estão correndo agora */
  let abaVista = null, pend = false;
  const abaAgora = () => { try { return typeof ABA !== 'undefined' ? String(ABA) : ''; } catch (e) { return ''; } };
  /* "R$ 76.520,00", "141", "−R$ 40,00", "12,5%": o que vem antes, o número e o que vem depois */
  const FMT = /^(\D*?)([-−]?)(\d{1,3}(?:\.\d{3})+|\d+)(,\d+)?(\s*%?)$/;
  function ler(t) {
    const m = FMT.exec(String(t).trim()); if (!m) return null;
    const dec = m[4] ? m[4].length - 1 : 0, n = +(m[3].replace(/\./g, '') + (m[4] ? '.' + m[4].slice(1) : ''));
    return isFinite(n) ? { pre: m[1], neg: m[2], n: m[2] ? -n : n, dec, suf: m[5] } : null;
  }
  const escrever = (f, v) => f.pre + (v < 0 ? (f.neg || '-') : '') + Math.abs(v).toLocaleString('pt-BR', { minimumFractionDigits: f.dec, maximumFractionDigits: f.dec }) + f.suf;
  const suave = k => 1 - Math.pow(1 - k, 3);
  function contar(el, aba) {
    if (CORRENDO.has(el) || el.childNodes.length !== 1 || el.firstChild.nodeType !== 3) return;
    const card = el.closest('.kpi'), rot = card && card.querySelector('.rot') ? card.querySelector('.rot').textContent.trim() : '', chave = aba + '|' + rot;
    const fim = el.firstChild.nodeValue, antes = VISTO.get(chave); VISTO.set(chave, fim);
    if (antes === fim) return;
    const f = ler(fim); if (!f) return;
    const a = antes != null ? ler(antes) : null, de = a && a.pre === f.pre && a.suf === f.suf ? a.n : 0;
    if (de === f.n) return;
    const dur = 900, t0 = performance.now(), no = el.firstChild; CORRENDO.add(el);
    const passo = agora => {
      if (!el.isConnected || no.parentNode !== el) { CORRENDO.delete(el); return; }
      const k = Math.min(1, (agora - t0) / dur);
      no.nodeValue = k < 1 ? escrever(f, de + (f.n - de) * suave(k)) : fim;
      if (k < 1) requestAnimationFrame(passo); else CORRENDO.delete(el);
    };
    no.nodeValue = escrever(f, de); requestAnimationFrame(passo);
  }
  function entrar(cards) {
    cards.slice(0, 40).forEach((c, i) => {
      c.style.setProperty('--fx-i', Math.min(i, 10)); c.classList.add('fx-entra');
      const tira = e => { if (e.target !== c) return; c.classList.remove('fx-entra'); c.removeEventListener('animationend', tira); };
      c.addEventListener('animationend', tira);
    });
  }
  function graficos(tela, aba, entrada) {
    tela.querySelectorAll('svg').forEach((svg, n) => {
      const gs = svg.querySelectorAll('g[data-g]'); if (!gs.length) return;
      const chave = aba + '|' + (svg.getAttribute('aria-label') || n), sig = gs.length + '|' + svg.innerHTML.length;
      if (!entrada && GRAF.get(chave) === sig) return; GRAF.set(chave, sig);
      if (svg.classList.contains('fx-cresce')) return;
      const passo = Math.max(8, Math.min(40, 520 / gs.length));
      gs.forEach((g, i) => g.style.setProperty('--fx-d', Math.round(120 + i * passo) + 'ms'));
      svg.querySelectorAll('polyline').forEach(l => { try { const L = Math.ceil(l.getTotalLength()); l.style.setProperty('--fx-l', L); } catch (e) { /* sem o tamanho */ } });
      svg.querySelectorAll(':scope > circle').forEach((c, i) => c.style.setProperty('--fx-d', Math.round(500 + i * 60) + 'ms'));
      svg.classList.add('fx-cresce'); setTimeout(() => svg.classList.remove('fx-cresce'), 2200);
    });
  }
  function passar() {
    pend = false; if (calmo()) return;
    const tela = document.getElementById('tela'); if (!tela) return;
    const aba = abaAgora(), cards = [...tela.querySelectorAll('.kpi, .cartao')];
    const entrada = cards.length > 0 && aba !== abaVista; if (cards.length) abaVista = aba;   /* a entrada só quando a aba muda */
    if (entrada) { entrar(cards); for (const k of [...VISTO.keys()]) if (k.startsWith(aba + '|')) VISTO.delete(k); }   /* ao abrir a aba, os números correm do zero */
    tela.querySelectorAll('.kpi .val').forEach(v => contar(v, aba));
    graficos(tela, aba, entrada);
  }
  const olhar = () => { if (!pend) { pend = true; requestAnimationFrame(passar); } };
  /* a página inteira: a área da tela (#tela) só nasce depois de entrar */
  const comecar = () => { new MutationObserver(olhar).observe(document.body, { childList: true, subtree: true }); olhar(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', comecar); else comecar();
})();
