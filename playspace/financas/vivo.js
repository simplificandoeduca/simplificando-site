/* Play Space Finanças 3.8 (Play Space 1.85): as Finanças ao vivo.
   Pedido do dono: "tudo atualizando automaticamente, rapidamente". A cada 3 segundos, com alguém dentro e a página à vista, as Finanças
   perguntam ao servidor (o pulso, leve) se mudou alguma coisa: uma venda, um pagamento, um cancelamento, um lançamento, o caixa... Mudou,
   a aba aberta busca os números de novo e se redesenha no lugar, sem apagar a tela, sem voltar para o alto e sem repetir a entrada (os
   números que mudaram correm até o valor novo). De minuto em minuto, busca de novo de qualquer jeito.
   Não mexe enquanto alguém está escrevendo (um campo da tela com o cursor), com uma janela aberta (um lançamento, uma ficha), nas regras de
   comissão ainda não salvas, nas páginas a mais da lista de lançamentos, nem nas abas Emitir nota, Planilhas e Conta. */
'use strict';
(() => {
  let sig = '', ocupado = false, ult = 0;
  const dentro = () => { try { return !!EU; } catch (e) { return false; } };
  function parado() {
    if (!dentro() || document.hidden || document.querySelector('.fundo')) return true;
    const a = document.activeElement; if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) && a.closest('#tela') && a.type !== 'search') return true;
    if (['emitir', 'plan', 'conta'].includes(ABA)) return true;
    if (ABA === 'comis' && CM.sujo) return true;
    if (ABA === 'lanc' && GS.pag > 0) return true;
    return false;
  }
  async function atualizar() {
    document.body.classList.add('fin-quieto'); const y = scrollY;
    try {
      if (ABA === 'caixa') await cxCarregar(); else if (ABA === 'festas') await fsCarregar(); else if (ABA === 'freelas') await fzCarregar(); else if (ABA === 'comis') await cmCarregar();
      else if (ABAS_GESTAO.includes(ABA)) { try { gPoeListas(await api('fl_listas')); } catch (e) { /* fica a de antes */ }
        const f = { lanc: () => carregarLanc(true), relat: carregarRel, fin: carregarPainel, pagar: carregarPagar, forn: carregarForn }[ABA]; if (f) await f(); }
      else await carregar();
    } catch (e) { /* tenta de novo no próximo pulso */ }
    finally { if (Math.abs(scrollY - y) > 2) scrollTo(0, y); setTimeout(() => document.body.classList.remove('fin-quieto'), 60); }
  }
  async function olhar() {
    if (ocupado || window.FIN_SEM_VIVO || parado()) return; ocupado = true;   /* (FIN_SEM_VIVO: os roteiros de teste antigos) */
    try { const p = (await api('pulso')).p || '';
      if (sig && (p !== sig || Date.now() - ult > 60000)) { await atualizar(); ult = Date.now(); } else if (!sig) ult = Date.now();
      sig = p; }
    catch (e) { /* sem internet: tenta depois */ } finally { ocupado = false; }
  }
  setInterval(olhar, 3000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) olhar(); });
})();
