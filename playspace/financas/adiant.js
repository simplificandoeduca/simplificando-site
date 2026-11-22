/* Play Space Finanças 4.3 (Play Space 1.90): o Adiantamento por unidade (logo abaixo dos Freelancers).
   Pedido do dono: "uma aba adiantamento por unidade. No Anália eu coloco 700 e no Morumbi 1500. E eu tenho que ir discriminando do que
   foi esses valores e lá ele me dá o quanto sobrou". Um cartão por unidade: quanto sobrou (grande), o que vinha do mês anterior, o colocado
   e o gasto no mês; "Colocar valor" e "Lançar gasto"; embaixo, cada lançamento do mês (tocar muda; "Tirar" para o lançado por engano). */
'use strict';
const AD = { mes: '', d: null, n: 0 };
try { const g = JSON.parse(localStorage.getItem('psfin-adiant') || 'null'); if (g && /^\d{4}-\d{2}$/.test(g.mes || '')) AD.mes = g.mes; } catch (e) { /* sem o guardado */ }
if (!AD.mes) { const h = new Date(); AD.mes = `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}`; }
const adGuarda = () => { try { localStorage.setItem('psfin-adiant', JSON.stringify({ mes: AD.mes })); } catch (e) { /* sem espaço */ } };
const adDia = d => { const [a, m, x] = String(d || '').split('-'); return x ? `${x}/${m}` : ''; };
(() => { const css = document.createElement('style');   /* o visual do Adiantamento */
  css.textContent = '.ad-grade{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:16px;margin-top:6px}'
    + '.ad-c{background:var(--card);border-radius:var(--r,20px);box-shadow:var(--sombra);padding:18px 18px 12px;display:flex;flex-direction:column;gap:12px;min-width:0}'
    + '.ad-c header{display:flex;align-items:center;gap:8px;font:800 15px/1.2 var(--display)}.ad-c header i{width:10px;height:10px;border-radius:50%;flex:none}'
    + '.ad-sobrou{display:flex;align-items:baseline;justify-content:space-between;gap:10px;padding:12px 14px;border-radius:16px;background:var(--ok-bg)}.ad-sobrou small{font-weight:700;color:var(--ink-2)}'
    + '.ad-sobrou b{font:800 26px/1 var(--display);color:var(--ok)}.ad-sobrou.neg{background:var(--brand-bg)}.ad-sobrou.neg b{color:var(--brand-2)}.ad-sobrou.zero{background:rgba(59,59,59,.05)}.ad-sobrou.zero b{color:var(--ink-2)}'
    + '.ad-conta{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px}.ad-conta span{display:flex;flex-direction:column;gap:2px;font-size:12.5px;color:var(--ink-2);padding:8px 10px;border:1px solid var(--line);border-radius:12px}.ad-conta b{font:800 14.5px/1.1 var(--display);color:var(--ink)}'
    + '.ad-conta .pos{color:var(--ok)}.ad-conta .neg{color:var(--brand-2)}.ad-conta b{white-space:nowrap;font-size:13.5px}.ad-bts{display:flex;gap:8px;flex-wrap:wrap;align-items:stretch}.ad-bts .btn{flex:1 1 140px;margin:0;min-height:44px;transform:none}'
    + '.ad-tab{width:100%;border-collapse:collapse;font-size:14px}.ad-tab td{padding:8px 4px;border-top:1px solid var(--line);vertical-align:top}.ad-tab tr.ad-l{cursor:pointer}.ad-tab tr.ad-l:hover td{background:rgba(226,91,63,.05)}'
    + '.ad-tab .d{width:48px;color:var(--ink-2);font-variant-numeric:tabular-nums}.ad-tab .v{text-align:right;white-space:nowrap;font-weight:800;font-variant-numeric:tabular-nums}.ad-tab .v.pos{color:var(--ok)}.ad-tab .v.neg{color:var(--brand-2)}'
    + '.ad-tab .t small{display:block;color:var(--ink-3);font-size:12px}.ad-tab .x{width:44px;text-align:right}.ad-tab .x button{border:0;background:none;color:var(--ink-3);font:700 12px var(--texto);cursor:pointer;padding:2px 4px}.ad-tab .x button:hover{color:var(--brand-2)}'
    + '.ad-vazio{color:var(--ink-3);font-size:13.5px;padding:6px 2px}.ad-tipo{display:flex;gap:8px}.ad-tipo label{flex:1;display:flex;align-items:center;gap:8px;padding:10px 12px;border:1.5px solid var(--line);border-radius:14px;cursor:pointer;font-weight:800}'
    + '.ad-tipo label:has(input:checked){border-color:var(--brand);background:var(--brand-bg)}@media (max-width:520px){.ad-grade{grid-template-columns:1fr}.ad-conta{grid-template-columns:1fr 1fr}}';
  document.head.appendChild(css); })();
function adiantDesenhar() {
  const t = $('#tela'); if (!t) return;
  t.innerHTML = `<div class="ad-tela"><div class="topo"><div><h1>Adiantamento</h1><div class="sub">O dinheiro colocado em cada unidade, no que foi gasto e quanto sobrou</div></div></div>
    <div class="filtros"><div class="chips"><button class="chip" data-admes="-1" aria-label="Mês anterior">‹</button><b class="g-mes">${esc(fsMesNome(AD.mes))}</b><button class="chip" data-admes="1" aria-label="Mês seguinte">›</button></div></div>
    <div id="ad-corpo">${AD.d && AD.d.mes === AD.mes ? adCorpo() : '<div class="vazio">Carregando o adiantamento…</div>'}</div></div>`;
  $$('[data-admes]').forEach(b => b.onclick = () => { const [a, m] = AD.mes.split('-').map(Number), d = new Date(a, m - 1 + +b.dataset.admes, 1); AD.mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; adGuarda(); adiantDesenhar(); });
  if (AD.d && AD.d.mes === AD.mes) adLigar(); else adCarregar();
}
async function adCarregar() {
  const n = ++AD.n, mes = AD.mes;
  try { const r = await api('ad_listar', { mes }); if (n !== AD.n || ABA !== 'adiant') return; AD.d = r; }
  catch (e) { if (n === AD.n) { aviso(e.message); const c = $('#ad-corpo'); if (c) c.innerHTML = `<div class="vazio">${esc(e.message)}</div>`; } return; }
  if (AD.mes === mes) adiantDesenhar();
}
function adRedesenha() { const c = $('#ad-corpo'); if (c && AD.d) { c.innerHTML = adCorpo(); adLigar(); } }
function adCorpo() {
  const U = AD.d.unis || []; if (!U.length) return '<div class="vazio">Nenhuma unidade cadastrada.</div>';
  return `<div class="ad-grade">${U.map(u => { const cls = u.sobrou < -0.004 ? 'neg' : u.sobrou > 0.004 ? '' : 'zero';
    return `<section class="ad-c" data-adu="${u.u}"><header><i style="background:${corUni(u.u)}"></i>${esc(u.nome)}</header>
      <div class="ad-sobrou ${cls}"><small>${u.sobrou < -0.004 ? 'Gastou a mais' : 'Sobrou'}</small><b class="num" data-adsobrou>${brl(Math.abs(u.sobrou))}</b></div>
      <div class="ad-conta"><span>Do mês anterior<b class="num">${brl(u.antes)}</b></span><span>Colocado no mês<b class="num pos">${u.colocado ? '+ ' : ''}${brl(u.colocado)}</b></span><span>Gasto no mês<b class="num neg">${u.gasto ? '− ' : ''}${brl(u.gasto)}</b></span></div>
      <div class="ad-bts"><button class="btn btn-brand" data-adnovo="entrada" data-u="${u.u}">+ Colocar valor</button><button class="btn btn-ghost" data-adnovo="gasto" data-u="${u.u}">+ Lançar gasto</button></div>
      ${u.movs.length ? `<table class="ad-tab"><tbody>${u.movs.map(m => `<tr class="ad-l" data-adm="${m.id}" data-u="${u.u}" title="Tocar para mudar"><td class="d">${esc(adDia(m.dia))}</td>
        <td class="t">${m.tipo === 'entrada' ? `<b>Valor colocado</b>${m.desc ? `<small>${esc(m.desc)}</small>` : ''}` : esc(m.desc)}<small>${esc(String(m.por || '').split(' ')[0])}${m.mudou ? ' · mudado' : ''}</small></td>
        <td class="v num ${m.tipo === 'entrada' ? 'pos' : 'neg'}">${m.tipo === 'entrada' ? '+' : '−'} ${brl(m.valor)}</td><td class="x"><button type="button" data-adtira="${m.id}" data-u="${u.u}" aria-label="Tirar">Tirar</button></td></tr>`).join('')}</tbody></table>`
        : `<p class="ad-vazio">Nada lançado em ${esc(fsMesNome(AD.mes).toLowerCase())}.</p>`}</section>`; }).join('')}</div>`;
}
function adAcha(u, id) { const x = (AD.d.unis || []).find(y => y.u === +u); return x ? x.movs.find(m => m.id === +id) : null; }
function adLigar() {
  $$('[data-adnovo]').forEach(b => b.onclick = () => adFolha(+b.dataset.u, b.dataset.adnovo, null));
  $$('[data-adm]').forEach(r => r.onclick = e => { if (e.target.closest('[data-adtira]')) return; adFolha(+r.dataset.u, null, adAcha(r.dataset.u, r.dataset.adm)); });
  $$('[data-adtira]').forEach(b => b.onclick = () => { const m = adAcha(b.dataset.u, b.dataset.adtira); if (!m) return;
    folha('Tirar o lançamento', `<p class="dica">Tirar <b>${m.tipo === 'entrada' ? 'o valor colocado' : esc(m.desc)}</b> de ${esc(adDia(m.dia))} (${brl(m.valor)}) de <b>${esc(nomeUni(+b.dataset.u))}</b>? Use se foi lançado por engano: fica guardado quem tirou.</p>`, 'Tirar',
      async () => { AD.d = await api('ad_tirar', { id: m.id, mes: AD.mes }); adRedesenha(); aviso('Lançamento tirado ✓'); }); });
}
function adFolha(u, tipo, m) {
  const v = m || { tipo, dia: AD.mes === (AD.d && AD.d.hoje || '').slice(0, 7) ? AD.d.hoje : AD.mes + '-01', valor: null, desc: '' };
  folha(m ? 'Mudar o lançamento' : v.tipo === 'entrada' ? 'Colocar valor · ' + nomeUni(u) : 'Lançar gasto · ' + nomeUni(u), `<div class="fz-form">
      ${m ? `<div class="ad-tipo"><label><input type="radio" name="ad-tipo" value="entrada" ${v.tipo === 'entrada' ? 'checked' : ''}> Valor colocado</label><label><input type="radio" name="ad-tipo" value="gasto" ${v.tipo === 'gasto' ? 'checked' : ''}> Gasto</label></div>`
        : `<p class="dica">${v.tipo === 'entrada' ? 'O dinheiro que você deixou na unidade (ex.: 700,00). Soma com o que já sobrou.' : 'No que o dinheiro foi usado: sai do que sobrou.'}</p>`}
      <div class="fz-2"><label class="fz-c"><span class="lbl">Valor (R$)</span><input class="inp" id="ad-valor" inputmode="decimal" autocomplete="off" value="${v.valor != null ? esc(cxFmt(v.valor)) : ''}" placeholder="${v.tipo === 'entrada' ? 'ex.: 700,00' : 'ex.: 45,90'}"></label>
        <label class="fz-c"><span class="lbl">Dia</span><input class="inp" type="date" id="ad-dia" value="${esc(v.dia)}"></label></div>
      <label class="fz-c"><span class="lbl" id="ad-desc-l">${v.tipo === 'entrada' ? 'Observação' : 'No que foi gasto'}</span><input class="inp" id="ad-desc" autocomplete="off" maxlength="160" value="${esc(v.desc)}" placeholder="${v.tipo === 'entrada' ? 'opcional' : 'ex.: mercado, gás, conserto do portão'}"></label></div>`,
    m ? 'Salvar' : v.tipo === 'entrada' ? 'Colocar' : 'Lançar o gasto', async () => {
      const tp = m ? (($('[name="ad-tipo"]:checked') || {}).value || v.tipo) : v.tipo, n = cxNum($('#ad-valor').value);
      if (Number.isNaN(n) || !(n > 0)) throw new Error(tp === 'entrada' ? 'Escreva o valor colocado (ex.: 700,00).' : 'Escreva quanto foi gasto (ex.: 45,90).');
      AD.d = await api('ad_salvar', { id: m ? m.id : 0, u, tipo: tp, valor: n, dia: $('#ad-dia').value, desc: $('#ad-desc').value, mes: AD.mes });
      adRedesenha(); aviso(m ? 'Salvo ✓' : tp === 'entrada' ? 'Valor colocado ✓' : 'Gasto lançado ✓');
    });
  if (m) $$('[name="ad-tipo"]').forEach(r => r.onchange = () => { const e = r.value === 'entrada', l = $('#ad-desc-l'), i = $('#ad-desc'); if (l) l.textContent = e ? 'Observação' : 'No que foi gasto'; if (i) i.placeholder = e ? 'opcional' : 'ex.: mercado, gás, conserto do portão'; });
}
