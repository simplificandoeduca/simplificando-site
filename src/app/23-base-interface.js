/* lista de resultados com barrinha de rolagem própria (sobe e desce, dá para arrastar) e janela que acompanha o teclado */
function pkSync(box){
  const L = box.querySelector('.pk-res'), bar = box.querySelector('.pk-bar'), th = bar && bar.firstElementChild; if(!L || !th) return;
  const over = L.scrollHeight - L.clientHeight; bar.hidden = over < 4; if(over < 4) return;
  const tr = bar.clientHeight, h = Math.max(36, tr * L.clientHeight / L.scrollHeight);
  th.style.height = h + 'px'; th.style.transform = 'translateY(' + ((tr - h) * L.scrollTop / over) + 'px)';
}
const pkAll = () => document.querySelectorAll('.pk-box').forEach(pkSync);
let pkDrag = null;
function pkMove(e){ const { bar, L } = pkDrag, r = bar.getBoundingClientRect(), h = bar.firstElementChild.offsetHeight, y = Math.min(1, Math.max(0, (e.clientY - r.top - h / 2) / Math.max(1, r.height - h))); L.scrollTop = y * (L.scrollHeight - L.clientHeight); }
document.addEventListener('scroll', e => { const b = e.target && e.target.closest && e.target.closest('.pk-box'); if(b) pkSync(b); }, true);
document.addEventListener('pointerdown', e => { const bar = e.target.closest && e.target.closest('.pk-bar'); if(!bar) return; e.preventDefault(); pkDrag = { bar, L:bar.closest('.pk-box').querySelector('.pk-res') }; bar.classList.add('on'); pkMove(e); try { bar.setPointerCapture(e.pointerId); } catch(x){ /* sem captura */ } });
document.addEventListener('pointermove', e => { if(pkDrag) pkMove(e); });
['pointerup','pointercancel'].forEach(k => document.addEventListener(k, () => { if(pkDrag){ pkDrag.bar.classList.remove('on'); pkDrag = null; } }));
window.addEventListener('resize', pkAll);
(function(){ // com o teclado aberto, a folha encolhe para caber na parte visível da tela
  const vv = window.visualViewport; if(!vv) return;
  const set = () => { const r = document.documentElement.style; r.setProperty('--vvh', vv.height + 'px'); r.setProperty('--vvt', vv.offsetTop + 'px'); pkAll(); };
  vv.addEventListener('resize', set); vv.addEventListener('scroll', set); set();
})();
new MutationObserver(() => requestAnimationFrame(pkAll)).observe(document.body, { childList:true, subtree:true });
