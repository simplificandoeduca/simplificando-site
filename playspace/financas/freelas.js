/* Play Space Finanças 2.6 (Play Space 1.65): os Freelancers (logo abaixo das Festas).
   Pedido do dono: o nome, os dias que trabalhou, o valor combinado, se foi pago (e quanto), se o vale-transporte foi pago e o pagamento
   total; às vezes o freelancer trabalha o mês inteiro. Cada freelancer num cartão com os dias do mês; lançar os dias é tocar no calendário;
   "Pagar o que falta" paga o mês de uma vez. Digita como numa planilha (o valor fica salvo ao sair do campo). E o PDF bonito do mês.
   Também aqui: pdfBaixar (o download dos PDFs bonitos, usado pelo caixa e pelas festas). */
'use strict';
const FZ = { mes: '', u: 'todas', busca: '', d: null, n: 0, vista: 'freelas' };   /* vista (2.9): 'freelas' ou 'fluxo' (o fluxo de caixa) */
try { const g = JSON.parse(localStorage.getItem('psfin-freelas') || 'null'); if (g && /^\d{4}-\d{2}$/.test(g.mes || '')) FZ.mes = g.mes; if (g && g.u) FZ.u = g.u; if (g && g.vista === 'fluxo') FZ.vista = 'fluxo'; } catch (e) { /* sem o guardado */ }
if (!FZ.mes) { const h = new Date(); FZ.mes = `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}`; }
const fzGuarda = () => { try { localStorage.setItem('psfin-freelas', JSON.stringify({ mes: FZ.mes, u: FZ.u, vista: FZ.vista })); } catch (e) { /* sem espaço */ } };
const FZ_SEM = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const fzIniciais = n => String(n || '?').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase();
const fzHoje = () => { const h = new Date(); return `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}-${String(h.getDate()).padStart(2, '0')}`; };

/* o download de um PDF bonito (o caixa, as festas, os freelancers) */
async function pdfBaixar(b, dados, nome) {
  const rot = b ? b.innerHTML : ''; if (b) { b.disabled = true; b.innerHTML = 'Gerando o PDF…'; }
  try { let r; try { r = await fetch('api.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Fin': '1' }, body: JSON.stringify(dados) }); } catch (e) { throw new Error('Sem conexão com o servidor. Confira a internet e tente de novo.'); }
    if (!r.ok || !/pdf/.test(r.headers.get('Content-Type') || '')) { let j = {}; try { j = await r.json(); } catch (e) { /* sem JSON */ } throw new Error(j.erro || 'Não deu para gerar o PDF.'); }
    gBaixa(await r.blob(), nome); aviso('PDF baixado ✓'); }
  catch (e) { aviso(e.message); } finally { if (b) { b.disabled = false; b.innerHTML = rot; } }
}
const PDF_IC = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>';

/* os freelancers que aparecem: os que trabalharam no mês (na unidade escolhida) e, embaixo, os outros ativos (para lançar); com a busca */
function fzLinhas() {
  const D = FZ.d; if (!D) return { com: [], sem: [] }; const b = fsNorm(FZ.busca).trim();
  const L = D.freelas.filter(f => !b || fsNorm(f.nome + ' ' + f.funcao).includes(b));
  return { com: L.filter(f => f.dias.length), sem: L.filter(f => !f.dias.length && f.ativo) };
}
function freelasDesenhar() {
  const t = $('#tela'); if (!t) return;
  const unis = UNIS.filter(x => x.ativo).map(x => x.id);
  t.innerHTML = `<div class="fz-tela"><div class="topo"><div><h1>Freelancers</h1><div class="sub">Os dias trabalhados, o combinado, o vale-transporte e o que já foi pago</div></div>
      <div class="lista-acoes"><button class="btn btn-ghost" id="fz-pdf">${PDF_IC} PDF do mês</button>${FZ.vista === 'fluxo' ? '<button class="btn btn-brand" id="ff-novo">+ Fluxo de caixa</button>' : '<button class="btn btn-ghost" id="fz-excel">Exportar Excel</button><button class="btn btn-ghost" id="fz-novo">+ Freelancer</button><button class="btn btn-brand" id="fz-lancar">Lançar dias</button>'}</div></div>
    <div class="chips fz-vistas" role="tablist" aria-label="Freelancers"><button class="chip ${FZ.vista !== 'fluxo' ? 'on' : ''}" role="tab" aria-selected="${FZ.vista !== 'fluxo'}" data-fzvista="freelas">Freelancers</button><button class="chip ${FZ.vista === 'fluxo' ? 'on' : ''}" role="tab" aria-selected="${FZ.vista === 'fluxo'}" data-fzvista="fluxo">Fluxo de caixa${FZ.d && FZ.d.fluxoTot && FZ.d.fluxoTot.abertos ? ` <b class="ff-n">${FZ.d.fluxoTot.abertos}</b>` : ''}</button></div>
    <div class="filtros"><div class="chips"><button class="chip" data-fzmes="-1" aria-label="Mês anterior">‹</button><b class="g-mes">${esc(fsMesNome(FZ.mes))}</b><button class="chip" data-fzmes="1" aria-label="Mês seguinte">›</button>
      ${unis.length > 1 ? `<button class="chip ${FZ.u === 'todas' ? 'on' : ''}" data-fzu="todas">Todas as unidades</button>${unis.map(u => `<button class="chip ${String(FZ.u) === String(u) ? 'on' : ''}" data-fzu="${u}"><i style="background:${corUni(u)}"></i>${esc(nomeUni(u))}</button>`).join('')}` : ''}
      <input class="chip g-busca" id="fz-busca" type="search" placeholder="Buscar freelancer…" value="${esc(FZ.busca)}" aria-label="Buscar freelancer"></div></div>
    <div id="fz-corpo">${FZ.d && FZ.d.mes === FZ.mes ? fzCorpo() : '<div class="vazio">Carregando os freelancers…</div>'}</div></div>`;
  $$('[data-fzmes]').forEach(b => b.onclick = () => { const [a, m] = FZ.mes.split('-').map(Number), d = new Date(a, m - 1 + +b.dataset.fzmes, 1); FZ.mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; fzGuarda(); freelasDesenhar(); });
  $$('[data-fzu]').forEach(b => b.onclick = () => { FZ.u = b.dataset.fzu === 'todas' ? 'todas' : +b.dataset.fzu; fzGuarda(); FZ.d = null; freelasDesenhar(); });
  const bu = $('#fz-busca'); bu.oninput = () => { FZ.busca = bu.value; fzRedesenha(); };
  const on = (id, fn) => { const b = $(id); if (b) b.onclick = fn; };
  on('#fz-novo', () => fzFreelaFolha(null)); on('#fz-lancar', () => fzLancarFolha(null)); on('#fz-excel', fzExcel); on('#ff-novo', () => ffFolha(null));
  $('#fz-pdf').onclick = e => pdfBaixar(e.currentTarget, { acao: 'fz_pdf', mes: FZ.mes, u: FZ.u }, `play-space-freelancers-${FZ.mes}.pdf`);
  if (FZ.d && FZ.d.mes === FZ.mes) fzLigar(); else fzCarregar();
}
function fzRedesenha() { const c = $('#fz-corpo'); if (c && FZ.d) { c.innerHTML = fzCorpo(); fzLigar(); } }
async function fzCarregar() {
  const n = ++FZ.n, mes = FZ.mes, c = $('#fz-corpo'); if (c) c.classList.add('carregando');
  try { const r = await api('fz_listar', { mes, u: FZ.u }); if (n !== FZ.n || ABA !== 'freelas') return; FZ.d = r; }
  catch (e) { if (n === FZ.n) { aviso(e.message); const c2 = $('#fz-corpo'); if (c2) { c2.classList.remove('carregando'); c2.innerHTML = `<div class="vazio">${esc(e.message)}</div>`; } } return; }
  if (FZ.mes === mes) freelasDesenhar();
}
function fzKpis() {
  const T = FZ.d.total;
  return `${kpi('Total do mês', brl(T.devido), T.dias ? `${int(T.dias)} ${T.dias === 1 ? 'dia' : 'dias'} · diárias ${brl(T.combinado)} + VT ${brl(T.vt)}` : 'nenhum dia lançado')}
    ${kpi('Pago', brl(T.totalPago), T.dias ? `${int(T.diasPagos)} de ${int(T.dias)} diárias · ${int(T.vtPagos)} de ${int(T.comVt)} VT` : '', 'lucro')}
    ${kpi('Falta pagar', brl(T.falta), T.falta > 0.004 ? 'diárias e VT em aberto' : 'nada em aberto', T.falta > 0.004 ? 'alerta' : '')}
    ${kpi('Freelancers', int(T.freelas), T.freelas ? 'trabalharam no mês' : 'ninguém lançado ainda')}`;
}
function fzCorpo() {
  if (FZ.vista === 'fluxo') return ffCorpo();   /* 2.9: o fluxo de caixa */
  const { com, sem } = fzLinhas();
  const vazio = !com.length ? `<div class="cartao"><div class="vazio">${FZ.d.freelas.length ? `Nenhum dia de freelancer em ${esc(fsMesNome(FZ.mes).toLowerCase())}${FZ.busca ? ' com essa busca' : ''}. Toque em <b>Lançar dias</b>.` : 'Nenhum freelancer cadastrado. Toque em <b>+ Freelancer</b> e depois em <b>Lançar dias</b>.'}</div></div>` : '';
  return `${ffAviso()}<div class="grade fs-kpis" id="fz-kpis">${fzKpis()}</div>${vazio}${com.map(fzCartao).join('')}
    ${sem.length ? `<section class="cartao fz-sem"><h3>Sem dias em ${esc(fsMesNome(FZ.mes).toLowerCase())}</h3><div class="fz-sem-l">${sem.map(f => `<span class="fz-sem-i"><b>${esc(f.nome)}</b>${f.funcao ? `<small>${esc(f.funcao)}</small>` : ''}<button class="link" data-fzlanca="${f.id}">Lançar dias</button><button class="link" data-fzedit="${f.id}">Editar</button></span>`).join('')}</div></section>` : ''}
    <p class="fs-expl"><b>Total pago</b> = a diária paga + o VT pago. <b>Falta</b> = o que ainda não foi pago (a diária e o VT). Escreva o valor pago se foi diferente do combinado; o valor fica salvo ao sair do campo.</p>`;
}
function fzResumo(f) {
  const t = f.tot;
  return `<span class="fz-n"><small>Dias</small><b>${int(t.dias)}</b></span><span class="fz-n"><small>Total</small><b>${brl(t.devido)}</b></span><span class="fz-n ok"><small>Pago</small><b>${brl(t.totalPago)}</b></span>
    ${t.falta > 0.004 ? `<span class="fz-n falta"><small>Falta pagar</small><b>${brl(t.falta)}</b></span>` : '<span class="fz-n ok tudo"><small>Situação</small><b>Tudo pago ✓</b></span>'}`;
}
function fzCartao(f) {
  const info = [f.funcao, f.tel, f.pix ? 'Pix ' + f.pix : ''].filter(Boolean).map(esc).join(' · ');
  const unis = UNIS.filter(x => x.ativo || f.dias.some(d => +d.u === x.id));
  const linha = d => { const x = new Date(d.dia + 'T12:00:00');
    return `<tr data-fzd="${d.id}" class="${d.falta > 0.004 ? 'fz-aberto' : ''}">
      <td class="fz-dia"><b>${FZ_SEM[x.getDay()]} ${dataBR(d.dia).slice(0, 5)}</b></td>
      <td>${unis.length > 1 ? `<select class="fz-uni" data-fzc="u" aria-label="Unidade · ${dataBR(d.dia)}"><option value="">—</option>${unis.map(u => `<option value="${u.id}" ${+d.u === u.id ? 'selected' : ''}>${esc(u.nome)}</option>`).join('')}</select>` : esc(nomeUni(d.u || 0))}</td>
      <td class="v"><input class="fz-in num" data-fzc="combinado" inputmode="decimal" autocomplete="off" value="${esc(cxFmt(d.combinado))}" aria-label="Combinado · ${dataBR(d.dia)}"></td>
      <td class="v"><input class="fz-in num" data-fzc="vt" inputmode="decimal" autocomplete="off" placeholder="—" value="${d.vt ? esc(cxFmt(d.vt)) : ''}" aria-label="Vale-transporte · ${dataBR(d.dia)}"></td>
      <td class="fz-pg"><button class="fz-tg ${d.pago ? 'sim' : ''}" data-fztg="pago" aria-pressed="${d.pago}" title="${d.pago ? 'Pago em ' + dataBR(d.pago_em) + '. Toque para desmarcar' : 'Toque quando pagar a diária'}">${d.pago ? '✓ Paga' : 'Não paga'}</button>
        <input class="fz-in num fz-pv" data-fzc="pago_valor" inputmode="decimal" autocomplete="off" placeholder="valor pago" value="${d.pago ? esc(cxFmt(d.pago_valor)) : ''}" aria-label="Valor pago · ${dataBR(d.dia)}">${d.pago && Math.abs(d.dif) > 0.004 ? `<small class="fz-dif">${d.dif < 0 ? brl(-d.dif) + ' a menos' : brl(d.dif) + ' a mais'}</small>` : ''}</td>
      <td class="fz-pg">${d.vt > 0.004 ? `<button class="fz-tg ${d.vt_pago ? 'sim' : ''}" data-fztg="vt_pago" aria-pressed="${d.vt_pago}" title="${d.vt_pago ? 'VT pago em ' + dataBR(d.vt_pago_em) : 'Toque quando pagar o VT'}">${d.vt_pago ? '✓ Pago' : 'Não pago'}</button>` : '<span class="sub">—</span>'}</td>
      <td class="v"><b data-fzt="pago">${brl(d.totalPago)}</b></td>
      <td class="v fz-falta"><b data-fzt="falta">${d.falta > 0.004 ? brl(d.falta) : '—'}</b></td>
      <td class="fz-x"><button class="link" data-fztira aria-label="Tirar o dia ${dataBR(d.dia)}" title="Tirar este dia (lançado por engano)">×</button></td></tr>`; };
  const t = f.tot;
  return `<section class="cartao fz-cartao" data-fzf="${f.id}">
    <div class="fz-cab"><span class="fz-av">${esc(fzIniciais(f.nome))}</span><div class="fz-quem"><b>${esc(f.nome)}</b>${info ? `<small>${info}</small>` : ''}${f.diaria != null ? `<small>Combinado: ${brl(f.diaria)} a diária${f.vt ? ' + ' + brl(f.vt) + ' de VT' : ''}</small>` : ''}</div>
      <div class="fz-res" data-fzres>${fzResumo(f)}</div></div>
    <div class="fz-acoes"><button class="btn btn-ghost" data-fzlanca="${f.id}">+ Lançar dias</button>${t.falta > 0.004 ? `<button class="btn btn-brand" data-fzpaga="${f.id}">Pagar o que falta · ${brl(t.falta)}</button>` : ''}<button class="link" data-fzedit="${f.id}">Editar</button></div>
    <div class="rolar"><table class="fz-tab"><thead><tr><th>Dia</th><th>Unidade</th><th class="v">Combinado</th><th class="v">VT</th><th>Diária paga</th><th>VT pago</th><th class="v">Total pago</th><th class="v">Falta</th><th></th></tr></thead>
      <tbody>${f.dias.map(linha).join('')}</tbody>
      <tfoot><tr data-fztot><td>Total · ${int(t.dias)} ${t.dias === 1 ? 'dia' : 'dias'}</td><td></td><td class="v">${brl(t.combinado)}</td><td class="v">${brl(t.vt)}</td><td>${int(t.diasPagos)} de ${int(t.dias)} pagas</td><td>${t.comVt ? `${int(t.vtPagos)} de ${int(t.comVt)}` : ''}</td><td class="v">${brl(t.totalPago)}</td><td class="v fz-falta">${t.falta > 0.004 ? brl(t.falta) : '—'}</td><td></td></tr></tfoot></table></div></section>`;
}
function fzLigar() {
  $$('[data-fzvista]').forEach(b => b.onclick = () => { FZ.vista = b.dataset.fzvista === 'fluxo' ? 'fluxo' : 'freelas'; fzGuarda(); freelasDesenhar(); window.scrollTo(0, 0); });
  ffLigar();
  $$('[data-fzlanca]').forEach(b => b.onclick = () => fzLancarFolha(+b.dataset.fzlanca));
  $$('[data-fzedit]').forEach(b => b.onclick = () => fzFreelaFolha(FZ.d.freelas.find(f => f.id === +b.dataset.fzedit)));
  $$('[data-fzpaga]').forEach(b => b.onclick = () => fzPagarFolha(FZ.d.freelas.find(f => f.id === +b.dataset.fzpaga)));
  $$('tr[data-fzd]').forEach(tr => {
    $$('input[data-fzc]', tr).forEach(inp => {
      fsSeleciona(inp);
      inp.addEventListener('input', () => inp.classList.toggle('cx-ruim', Number.isNaN(cxNum(inp.value))));
      inp.addEventListener('change', () => fzGuardar(tr, inp));
      inp.addEventListener('keydown', e => {   /* como numa planilha: Enter (ou ↓) desce, Shift+Enter (ou ↑) sobe, na mesma coluna e no mesmo cartão */
        const desce = (e.key === 'Enter' && !e.shiftKey) || e.key === 'ArrowDown', sobe = (e.key === 'Enter' && e.shiftKey) || e.key === 'ArrowUp';
        if (!desce && !sobe) return; e.preventDefault();
        const linhas = $$('tr[data-fzd]', tr.closest('tbody')), i = linhas.indexOf(tr), alvo = linhas[i + (desce ? 1 : -1)];
        const prox = alvo && $(`input[data-fzc="${inp.dataset.fzc}"]`, alvo); if (prox) prox.focus(); else inp.blur();
      });
    });
    const su = $('select[data-fzc="u"]', tr); if (su) su.onchange = () => fzGuardar(tr, su);
    $$('[data-fztg]', tr).forEach(b => b.onclick = () => fzGuardar(tr, b));
    const x = $('[data-fztira]', tr); if (x) x.onclick = () => fzTirar(+tr.dataset.fzd);
  });
}
async function fzGuardar(tr, el) {
  const id = +tr.dataset.fzd, campo = el.dataset.fzc || el.dataset.fztg; let valor;
  if (el.dataset.fztg) valor = el.getAttribute('aria-pressed') === 'true' ? 0 : 1;
  else if (el.tagName === 'SELECT') valor = el.value;
  else { const n = cxNum(el.value); if (Number.isNaN(n)) { aviso('Digite o valor em reais, ex.: 150,00'); return; } valor = n; }
  tr.classList.add('fs-salvando');
  const foco = document.activeElement && document.activeElement.closest('tr[data-fzd]') ? { id: document.activeElement.closest('tr[data-fzd]').dataset.fzd, c: document.activeElement.dataset.fzc } : null;
  try { FZ.d = await api('fz_dia_salvar', { id, campo, valor, u_filtro: FZ.u }); fzRedesenha();
    const n = $(`tr[data-fzd="${id}"]`); if (n) { n.classList.add('fs-salvo'); setTimeout(() => n.classList.remove('fs-salvo'), 900); }
    if (foco && foco.c) { const a = $(`tr[data-fzd="${foco.id}"] [data-fzc="${foco.c}"]`); if (a) a.focus(); } }
  catch (e) { tr.classList.remove('fs-salvando'); aviso(e.message); fzRedesenha(); }
}
function fzTirar(id) {
  let f = null, d = null; for (const x of FZ.d.freelas) { const y = x.dias.find(z => z.id === id); if (y) { f = x; d = y; } } if (!d) return;
  folha('Tirar este dia?', `<p class="dica">O dia <b>${dataBR(d.dia)}</b> de <b>${esc(f.nome)}</b> sai da conta (${brl(d.devido)}). Fica guardado que você tirou. Use só para o dia lançado por engano.</p>`, 'Tirar o dia', async () => {
    FZ.d = await api('fz_dia_tirar', { id, u_filtro: FZ.u }); fzRedesenha(); aviso('Dia tirado ✓'); });
}
/* o cadastro (novo ou editar) */
function fzFreelaFolha(f) {
  const v = f || { nome: '', tel: '', pix: '', funcao: '', diaria: null, vt: null, obs: '', ativo: true };
  folha(f ? 'Editar o freelancer' : 'Novo freelancer', `<div class="fz-form">
      <label class="fz-c"><span class="lbl">Nome</span><input class="inp" id="fzf-nome" autocomplete="off" maxlength="80" value="${esc(v.nome)}" placeholder="Nome completo"></label>
      <div class="fz-2"><label class="fz-c"><span class="lbl">Telefone</span><input class="inp" id="fzf-tel" inputmode="tel" autocomplete="off" maxlength="40" value="${esc(v.tel)}" placeholder="(11) 9 ..."></label>
        <label class="fz-c"><span class="lbl">Função</span><input class="inp" id="fzf-funcao" autocomplete="off" maxlength="60" value="${esc(v.funcao)}" placeholder="ex.: recreadora, garçom"></label></div>
      <label class="fz-c"><span class="lbl">Chave Pix</span><input class="inp" id="fzf-pix" autocomplete="off" maxlength="120" value="${esc(v.pix)}" placeholder="para pagar"></label>
      <div class="fz-2"><label class="fz-c"><span class="lbl">Diária combinada (R$)</span><input class="inp" id="fzf-diaria" inputmode="decimal" autocomplete="off" value="${v.diaria != null ? esc(cxFmt(v.diaria)) : ''}" placeholder="ex.: 150,00"></label>
        <label class="fz-c"><span class="lbl">Vale-transporte por dia (R$)</span><input class="inp" id="fzf-vt" inputmode="decimal" autocomplete="off" value="${v.vt != null ? esc(cxFmt(v.vt)) : ''}" placeholder="ex.: 20,00"></label></div>
      <label class="fz-c"><span class="lbl">Observação</span><input class="inp" id="fzf-obs" autocomplete="off" maxlength="300" value="${esc(v.obs)}" placeholder="opcional"></label>
      ${f ? `<label class="fz-chk"><input type="checkbox" id="fzf-ativo" ${v.ativo ? 'checked' : ''}> Ativo (aparece para lançar os dias)</label>` : ''}
      <p class="dica">A diária e o VT combinados vêm preenchidos quando você lança os dias (e dá para mudar em cada dia).</p></div>`, f ? 'Salvar' : 'Cadastrar', async () => {
    const num = id => { const n = cxNum($(id).value); if (Number.isNaN(n)) throw new Error('Confira o valor em reais (ex.: 150,00).'); return n; };
    const r = await api('fz_freela_salvar', { id: f ? f.id : 0, nome: $('#fzf-nome').value, tel: $('#fzf-tel').value, funcao: $('#fzf-funcao').value, pix: $('#fzf-pix').value, diaria: num('#fzf-diaria'), vt: num('#fzf-vt'), obs: $('#fzf-obs').value, ...(f ? { ativo: $('#fzf-ativo').checked } : {}), mes: FZ.mes, u: FZ.u });
    FZ.d = r; fzRedesenha(); aviso(f ? 'Freelancer salvo ✓' : 'Freelancer cadastrado ✓');
    if (!f) setTimeout(() => fzLancarFolha(r.id), 250);
  });
}
/* lançar os dias: o freelancer, o calendário do mês (tocar nos dias), a unidade, o combinado e o VT */
function fzLancarFolha(fid) {
  const ativos = FZ.d ? FZ.d.freelas.filter(f => f.ativo) : []; if (!ativos.length) { aviso('Cadastre o freelancer primeiro (+ Freelancer).'); fzFreelaFolha(null); return; }
  const sel = new Set(); let f = ativos.find(x => x.id === fid) || (ativos.length === 1 ? ativos[0] : null);
  const [a, m] = FZ.mes.split('-').map(Number), ult = new Date(a, m, 0).getDate(), pri = new Date(a, m - 1, 1).getDay();
  const ja = () => new Set(f ? f.dias.map(d => d.dia) : []);
  const unis = UNIS.filter(x => x.ativo);
  const cal = () => { const J = ja(); let h = FZ_SEM.map(s => `<span class="fz-cs">${s}</span>`).join('') + '<span></span>'.repeat(pri);
    for (let d = 1; d <= ult; d++) { const iso = `${FZ.mes}-${String(d).padStart(2, '0')}`, dow = new Date(a, m - 1, d).getDay();
      h += `<button type="button" class="fz-cd ${sel.has(iso) ? 'on' : ''} ${J.has(iso) ? 'ja' : ''} ${dow % 6 === 0 ? 'fds' : ''}" data-fzcd="${iso}" ${J.has(iso) ? 'disabled title="Já lançado"' : ''} aria-pressed="${sel.has(iso)}">${d}</button>`; }
    return h; };
  const conta = () => { const c = cxNum($('#fzl-comb').value), v = cxNum($('#fzl-vt').value) || 0, n = sel.size; return n && !Number.isNaN(c) ? `<b>${n} ${n === 1 ? 'dia' : 'dias'}</b> · ${brl(n * c)} de diárias${v ? ' + ' + brl(n * v) + ' de VT' : ''} = <b>${brl(n * (c + v))}</b>` : n ? `<b>${n} ${n === 1 ? 'dia' : 'dias'}</b>` : 'Toque nos dias que ele trabalhou.'; };
  folha('Lançar os dias trabalhados', `<div class="fz-form">
      <label class="fz-c"><span class="lbl">Freelancer</span><select class="inp" id="fzl-f">${f ? '' : '<option value="">Escolha…</option>'}${ativos.map(x => `<option value="${x.id}" ${f && f.id === x.id ? 'selected' : ''}>${esc(x.nome)}</option>`).join('')}</select></label>
      <div class="fz-cal-c"><div class="fz-cal-t"><b>${esc(fsMesNome(FZ.mes))}</b><span class="fz-atalhos"><button type="button" class="link" data-fzat="uteis">Seg a sex</button><button type="button" class="link" data-fzat="fds">Sáb e dom</button><button type="button" class="link" data-fzat="todos">Todos</button><button type="button" class="link" data-fzat="limpa">Limpar</button></span></div>
        <div class="fz-cal" id="fzl-cal">${cal()}</div></div>
      ${unis.length > 1 ? `<label class="fz-c"><span class="lbl">Unidade</span><select class="inp" id="fzl-u">${unis.map(u => `<option value="${u.id}" ${String(FZ.u) === String(u.id) ? 'selected' : ''}>${esc(u.nome)}</option>`).join('')}</select></label>` : ''}
      <div class="fz-2"><label class="fz-c"><span class="lbl">Combinado por dia (R$)</span><input class="inp" id="fzl-comb" inputmode="decimal" autocomplete="off" value="${f && f.diaria != null ? esc(cxFmt(f.diaria)) : ''}" placeholder="ex.: 150,00"></label>
        <label class="fz-c"><span class="lbl">Vale-transporte por dia (R$)</span><input class="inp" id="fzl-vt" inputmode="decimal" autocomplete="off" value="${f && f.vt != null ? esc(cxFmt(f.vt)) : ''}" placeholder="—"></label></div>
      <p class="fz-conta" id="fzl-conta">${'Toque nos dias que ele trabalhou.'}</p></div>`, 'Lançar', async () => {
    if (!f) throw new Error('Escolha o freelancer.'); if (!sel.size) throw new Error('Toque nos dias que ele trabalhou.');
    const c = cxNum($('#fzl-comb').value), v = cxNum($('#fzl-vt').value); if (Number.isNaN(c) || c === null) throw new Error('Escreva o valor combinado por dia.'); if (Number.isNaN(v)) throw new Error('Confira o valor do vale-transporte.');
    const u = $('#fzl-u') ? $('#fzl-u').value : (unis[0] ? unis[0].id : '');
    const r = await api('fz_lancar', { freela: f.id, datas: [...sel].sort(), u, combinado: c, vt: v || 0, mes: FZ.mes, u_filtro: FZ.u });
    FZ.d = r; fzRedesenha(); aviso(`${r.lancados} ${r.lancados === 1 ? 'dia lançado' : 'dias lançados'} ✓${r.pulados && r.pulados.length ? ` · ${r.pulados.length} já estava${r.pulados.length === 1 ? '' : 'm'} lançado${r.pulados.length === 1 ? '' : 's'}` : ''}`);
  });
  const pinta = () => { $('#fzl-cal').innerHTML = cal(); ligaCal(); $('#fzl-conta').innerHTML = conta(); };
  const ligaCal = () => $$('[data-fzcd]').forEach(b => b.onclick = () => { const d = b.dataset.fzcd; if (sel.has(d)) sel.delete(d); else sel.add(d); pinta(); });
  ligaCal();
  $$('[data-fzat]').forEach(b => b.onclick = () => { const J = ja(), k = b.dataset.fzat; if (k === 'limpa') sel.clear(); else for (let d = 1; d <= ult; d++) { const iso = `${FZ.mes}-${String(d).padStart(2, '0')}`, dow = new Date(a, m - 1, d).getDay(); if (J.has(iso)) continue; if (k === 'todos' || (k === 'uteis' && dow % 6 !== 0) || (k === 'fds' && dow % 6 === 0)) sel.add(iso); } pinta(); });
  $('#fzl-f').onchange = e => { f = ativos.find(x => x.id === +e.target.value) || null; sel.clear(); if (f) { $('#fzl-comb').value = f.diaria != null ? cxFmt(f.diaria) : ''; $('#fzl-vt').value = f.vt != null ? cxFmt(f.vt) : ''; } pinta(); };
  ['#fzl-comb', '#fzl-vt'].forEach(s => { const i = $(s); fsSeleciona(i); i.oninput = () => { $('#fzl-conta').innerHTML = conta(); }; });
}
/* pagar o que falta do mês: a data do pagamento e se o VT vai junto */
function fzPagarFolha(f) {
  if (!f) return; const t = f.tot, diar = f.dias.filter(d => !d.pago), vts = f.dias.filter(d => d.vt > 0.004 && !d.vt_pago);
  const sd = diar.reduce((s, d) => s + d.combinado, 0), sv = vts.reduce((s, d) => s + d.vt, 0);
  folha('Pagar o que falta', `<p class="dica"><b>${esc(f.nome)}</b> · ${esc(fsMesNome(FZ.mes))}</p>
    <div class="fz-pagar"><div><span>${int(diar.length)} ${diar.length === 1 ? 'diária' : 'diárias'} (pelo combinado)</span><b>${brl(sd)}</b></div>
      ${vts.length ? `<label class="fz-chk"><input type="checkbox" id="fzp-vt" checked> Vale-transporte de ${int(vts.length)} ${vts.length === 1 ? 'dia' : 'dias'}<b>${brl(sv)}</b></label>` : ''}
      <div class="tot"><span>Total a pagar</span><b id="fzp-tot">${brl(sd + sv)}</b></div></div>
    <label class="fz-c"><span class="lbl">Data do pagamento</span><input class="inp" type="date" id="fzp-data" value="${fzHoje()}" max="${fzHoje()}"></label>
    ${f.pix ? `<p class="dica">Chave Pix: <b>${esc(f.pix)}</b></p>` : ''}
    <p class="dica">Se pagou um valor diferente num dia, escreva no campo <b>valor pago</b> daquele dia.</p>`, 'Marcar como pago', async () => {
    const vt = $('#fzp-vt') ? $('#fzp-vt').checked : true;
    const r = await api('fz_pagar', { freela: f.id, mes: FZ.mes, u: FZ.u, data: $('#fzp-data').value || fzHoje(), vt });
    FZ.d = r; fzRedesenha(); aviso(`Pago ✓ · ${brl(r.valor)}`);
  });
  const ch = $('#fzp-vt'); if (ch) ch.onchange = () => { $('#fzp-tot').textContent = brl(sd + (ch.checked ? sv : 0)); };
}
function fzExcel() {
  const { com } = fzLinhas(); if (!com.length) { aviso('Nenhum dia para exportar.'); return; }
  const dias = []; com.forEach(f => f.dias.forEach(d => dias.push([f.nome, f.funcao, d.dia, nomeUni(d.u || 0), d.combinado, d.vt, d.pago ? 'sim' : 'não', d.pago ? d.pago_valor : '', d.pago_em || '', d.vt > 0.004 ? (d.vt_pago ? 'sim' : 'não') : '', d.totalPago, d.falta])));
  const res = com.map(f => [f.nome, f.funcao, f.tel, f.pix, f.tot.dias, f.tot.combinado, f.tot.vt, f.tot.devido, f.tot.totalPago, f.tot.falta]);
  const soma = (L, i) => Math.round(L.reduce((t, l) => t + (+l[i] || 0), 0) * 100) / 100;
  gBaixa(gXlsx([{ nome: 'Resumo', cols: [['Freelancer', 26, 'txt'], ['Função', 16, 'txt'], ['Telefone', 16, 'txt'], ['Chave Pix', 22, 'txt'], ['Dias', 8, 'int'], ['Diárias', 13, 'brl'], ['VT', 11, 'brl'], ['Total', 13, 'brl'], ['Pago', 13, 'brl'], ['Falta pagar', 13, 'brl']], linhas: res,
      total: ['Total', '', '', '', soma(res, 4), soma(res, 5), soma(res, 6), soma(res, 7), soma(res, 8), soma(res, 9)] },
    { nome: 'Dias', cols: [['Freelancer', 26, 'txt'], ['Função', 16, 'txt'], ['Dia', 12, 'data'], ['Unidade', 16, 'txt'], ['Combinado', 12, 'brl'], ['VT', 10, 'brl'], ['Diária paga', 11, 'txt'], ['Valor pago', 12, 'brl'], ['Pago em', 12, 'data'], ['VT pago', 9, 'txt'], ['Total pago', 12, 'brl'], ['Falta', 11, 'brl']], linhas: dias,
      total: ['Total', '', '', '', soma(dias, 4), soma(dias, 5), '', soma(dias, 7), '', '', soma(dias, 10), soma(dias, 11)] }]), `play-space-freelancers-${FZ.mes}.xlsx`);
  aviso('Planilha baixada ✓');
}

/* ---------- 2.9 (Play Space 1.68): o Fluxo de caixa (o dinheiro entregue ao gerente para pagar os freelancers) ----------
   Pedido do dono: o valor que ela passa ao gerente para o fim de semana; conforme os freelancers são marcados como pagos, o valor vai
   sendo debitado; fica o que o gerente precisa devolver por Pix. Fica só aqui, nos Freelancers (não entra no resto das Finanças). */
const ffDias = f => { const a = new Date(f.de + 'T12:00:00'), b = new Date(f.ate + 'T12:00:00'); return f.de === f.ate ? `${FZ_SEM[a.getDay()]} ${dataBR(f.de).slice(0, 5)}` : `${FZ_SEM[a.getDay()]} ${dataBR(f.de).slice(0, 5)} a ${FZ_SEM[b.getDay()]} ${dataBR(f.ate).slice(0, 5)}`; };
const ffSituacao = f => f.acertado ? { cls: 'ok', rot: 'Voltou por Pix', v: brl(f.devolvido) } : f.saldo >= 0 ? { cls: 'falta', rot: 'Falta voltar (Pix)', v: brl(f.saldo) } : { cls: 'falta', rot: 'O gerente pagou a mais', v: brl(-f.saldo) };
function ffAviso() {
  const F = (FZ.d && FZ.d.fluxos || []).filter(f => !f.acertado); if (!F.length) return '';
  return `<button class="ff-aviso" data-fzvista="fluxo">${F.map(f => { const s = ffSituacao(f); return `<span><b>Fluxo de caixa</b> · ${esc(f.gerente)} · ${esc(ffDias(f))}: entregue ${brl(f.valor)}, pago ${brl(f.usado)} · <b>${esc(s.rot.toLowerCase())} ${s.v}</b></span>`; }).join('')}<em>Ver ›</em></button>`;
}
function ffCorpo() {
  const F = FZ.d.fluxos || [], T = FZ.d.fluxoTot || { valor: 0, usado: 0, aDevolver: 0, devolvido: 0, aMais: 0, abertos: 0 };
  const kpis = `${kpi('Entregue ao gerente', brl(T.valor), F.length ? `${int(F.length)} ${F.length === 1 ? 'fluxo' : 'fluxos'} no mês` : 'nada lançado no mês')}
    ${kpi('Pago aos freelancers', brl(T.usado), 'o que foi marcado como pago nesses dias')}
    ${kpi('Falta voltar (Pix)', brl(T.aDevolver), T.aMais > 0.004 ? `e ${brl(T.aMais)} que o gerente pagou a mais` : T.aDevolver > 0.004 ? 'o gerente devolve por Pix' : 'nada a voltar', T.aDevolver > 0.004 || T.aMais > 0.004 ? 'alerta' : '')}
    ${kpi('Voltou por Pix', brl(T.devolvido), 'os fluxos já acertados', 'lucro')}`;
  return `<div class="grade fs-kpis">${kpis}</div>${F.length ? F.map(ffCartao).join('') : `<div class="cartao"><div class="vazio">Nenhum fluxo de caixa em ${esc(fsMesNome(FZ.mes).toLowerCase())}. Toque em <b>+ Fluxo de caixa</b> para lançar o valor entregue ao gerente.</div></div>`}
    <p class="fs-expl"><b>Como funciona:</b> lance o valor entregue ao gerente e os dias. Na aba <b>Freelancers</b>, cada diária ou VT marcado como <b>pago</b> nesses dias (na unidade do fluxo) sai do valor entregue. O que sobra é o que o gerente devolve por Pix. O fluxo de caixa e os freelancers ficam só aqui: não entram no resto das Finanças.</p>`;
}
function ffCartao(f) {
  const s = ffSituacao(f), pct = f.valor > 0 ? Math.min(100, Math.round(f.usado / f.valor * 100)) : 0;
  const linhas = f.pagos.map(p => `<tr><td class="fz-dia"><b>${FZ_SEM[new Date(p.dia + 'T12:00:00').getDay()]} ${esc(dataBR(p.dia).slice(0, 5))}</b></td><td>${esc(p.nome)}</td><td class="v">${p.diaria > 0.004 ? brl(p.diaria) : '—'}</td><td class="v">${p.vt > 0.004 ? brl(p.vt) : '—'}</td><td class="v"><b>${brl(p.total)}</b></td></tr>`).join('');
  return `<section class="cartao fz-cartao ff-cartao" data-ffid="${f.id}">
    <div class="fz-cab"><span class="fz-av ff-av">R$</span><div class="fz-quem"><b>Entregue a ${esc(f.gerente)}</b><small>${esc(ffDias(f))} · ${f.u ? esc(nomeUni(f.u)) : 'todas as unidades'}${f.obs ? ' · ' + esc(f.obs) : ''}</small><small>Lançado por ${esc(f.por)}</small></div>
      <div class="fz-res"><span class="fz-n"><small>Entregue</small><b>${brl(f.valor)}</b></span><span class="fz-n"><small>Pago aos freelancers</small><b>${brl(f.usado)}</b></span><span class="fz-n ${s.cls}"><small>${s.rot}</small><b>${s.v}</b></span></div></div>
    <div class="ff-barra" role="img" aria-label="Usado ${pct}% do valor entregue"><i style="width:${pct}%"></i></div><div class="ff-barra-t">${pct}% do valor entregue já foi pago aos freelancers</div>
    <div class="fz-acoes">${f.acertado ? `<span class="ff-ok">✓ Voltou ${brl(f.devolvido)} por Pix em ${esc(dataBR(f.dev_em))}${f.dev_por ? ' · ' + esc(f.dev_por) : ''}${Math.abs(f.dif) > 0.004 ? ` · <b class="ff-dif">${f.dif < 0 ? brl(-f.dif) + ' a menos' : brl(f.dif) + ' a mais'} do que faltava</b>` : ''}</span><button class="link" data-ffdesfaz="${f.id}">Desfazer</button>`
      : `<button class="btn btn-brand" data-ffpix="${f.id}">Recebi o Pix${f.saldo > 0.004 ? ' · ' + brl(f.saldo) : ''}</button>`}<button class="link" data-ffedit="${f.id}">Editar</button><button class="link" data-fftira="${f.id}">Tirar</button></div>
    ${f.pagos.length ? `<div class="rolar"><table class="fz-tab ff-tab"><thead><tr><th>Dia</th><th>Freelancer</th><th class="v">Diária paga</th><th class="v">VT pago</th><th class="v">Total</th></tr></thead><tbody>${linhas}</tbody>
      <tfoot><tr><td>Pago · ${int(f.pagos.length)} ${f.pagos.length === 1 ? 'pagamento' : 'pagamentos'}</td><td></td><td class="v">${brl(f.pagoDiarias)}</td><td class="v">${brl(f.pagoVt)}</td><td class="v">${brl(f.usado)}</td></tr></tfoot></table></div>`
      : '<p class="fs-expl">Nenhum pagamento marcado nestes dias ainda. Na aba <b>Freelancers</b>, marque a diária (ou o VT) como paga: o valor sai daqui.</p>'}</section>`;
}
function ffFimDeSemana() {
  const h = new Date(), d = h.getDay(), sab = new Date(h); if (d !== 0 && d !== 6) sab.setDate(h.getDate() + (6 - d));
  const dom = new Date(sab); if (sab.getDay() === 6) dom.setDate(sab.getDate() + 1);
  const iso = x => `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`; return [iso(sab), iso(dom)];
}
function ffFolha(f) {
  const [s1, s2] = ffFimDeSemana(), v = f || { valor: null, gerente: '', de: s1, ate: s2, u: FZ.u === 'todas' ? null : +FZ.u, obs: '' };
  const unis = UNIS.filter(x => x.ativo || x.id === v.u);
  folha(f ? 'Editar o fluxo de caixa' : 'Fluxo de caixa', `<div class="fz-form">
      <p class="dica">O dinheiro entregue ao gerente para pagar os freelancers nesses dias. Cada diária ou VT marcado como pago nesses dias sai deste valor; o que sobrar, o gerente devolve por Pix.</p>
      <div class="fz-2"><label class="fz-c"><span class="lbl">Valor entregue (R$)</span><input class="inp" id="ff-valor" inputmode="decimal" autocomplete="off" value="${v.valor != null ? esc(cxFmt(v.valor)) : ''}" placeholder="ex.: 1.000,00"></label>
        <label class="fz-c"><span class="lbl">Para quem (o gerente)</span><input class="inp" id="ff-ger" autocomplete="off" maxlength="60" value="${esc(v.gerente)}" placeholder="Nome"></label></div>
      <div class="fz-2"><label class="fz-c"><span class="lbl">Do dia</span><input class="inp" type="date" id="ff-de" value="${esc(v.de)}"></label><label class="fz-c"><span class="lbl">Até o dia</span><input class="inp" type="date" id="ff-ate" value="${esc(v.ate)}"></label></div>
      ${unis.length > 1 ? `<label class="fz-c"><span class="lbl">Unidade</span><select class="inp" id="ff-u"><option value="">Todas as unidades</option>${unis.map(u => `<option value="${u.id}" ${v.u === u.id ? 'selected' : ''}>${esc(u.nome)}</option>`).join('')}</select></label>` : ''}
      <label class="fz-c"><span class="lbl">Observação</span><input class="inp" id="ff-obs" autocomplete="off" maxlength="200" value="${esc(v.obs)}" placeholder="opcional"></label></div>`, f ? 'Salvar' : 'Lançar o fluxo de caixa', async () => {
    const n = cxNum($('#ff-valor').value); if (Number.isNaN(n) || !(n > 0)) throw new Error('Escreva o valor entregue ao gerente (ex.: 1.000,00).');
    const u = $('#ff-u'); const r = await api('ff_salvar', { id: f ? f.id : 0, valor: n, gerente: $('#ff-ger').value, de: $('#ff-de').value, ate: $('#ff-ate').value, u: u ? u.value : '', obs: $('#ff-obs').value, mes: FZ.mes, u_filtro: FZ.u });
    FZ.d = r; FZ.vista = 'fluxo'; fzGuarda(); freelasDesenhar(); aviso(f ? 'Fluxo de caixa salvo ✓' : 'Fluxo de caixa lançado ✓');
  });
}
function ffPixFolha(f) {
  folha('Recebi o Pix', `<div class="fz-form"><p class="dica">Entregue a <b>${esc(f.gerente)}</b>: ${brl(f.valor)} · pago aos freelancers: ${brl(f.usado)} · ${f.saldo >= 0 ? `faltava voltar <b>${brl(f.saldo)}</b>` : `o gerente pagou <b>${brl(-f.saldo)}</b> a mais`}.</p>
      <div class="fz-2"><label class="fz-c"><span class="lbl">Quanto voltou (R$)</span><input class="inp" id="ff-dv" inputmode="decimal" autocomplete="off" value="${esc(cxFmt(Math.max(0, f.saldo)))}"></label>
        <label class="fz-c"><span class="lbl">Quando</span><input class="inp" type="date" id="ff-dd" value="${esc(fzHoje())}"></label></div></div>`, 'Recebi', async () => {
    const n = cxNum($('#ff-dv').value); if (Number.isNaN(n) || n < 0) throw new Error('Escreva quanto voltou (pode ser 0,00).');
    FZ.d = await api('ff_devolver', { id: f.id, valor: n, data: $('#ff-dd').value, mes: FZ.mes, u_filtro: FZ.u }); fzRedesenha(); aviso('Acertado ✓');
  });
}
function ffLigar() {
  const acha = id => (FZ.d.fluxos || []).find(f => f.id === +id);
  $$('[data-ffpix]').forEach(b => b.onclick = () => ffPixFolha(acha(b.dataset.ffpix)));
  $$('[data-ffedit]').forEach(b => b.onclick = () => ffFolha(acha(b.dataset.ffedit)));
  $$('[data-ffdesfaz]').forEach(b => b.onclick = async () => { try { FZ.d = await api('ff_devolver', { id: +b.dataset.ffdesfaz, desfazer: 1, mes: FZ.mes, u_filtro: FZ.u }); fzRedesenha(); aviso('Desfeito ✓'); } catch (e) { aviso(e.message); } });
  $$('[data-fftira]').forEach(b => b.onclick = () => { const f = acha(b.dataset.fftira); folha('Tirar o fluxo de caixa', `<p class="dica">Tirar o fluxo de <b>${brl(f.valor)}</b> entregue a <b>${esc(f.gerente)}</b> (${esc(ffDias(f))})? Use se foi lançado por engano: os pagamentos dos freelancers continuam como estão.</p>`, 'Tirar',
    async () => { FZ.d = await api('ff_tirar', { id: f.id, mes: FZ.mes, u_filtro: FZ.u }); fzRedesenha(); aviso('Fluxo de caixa tirado ✓'); }); });
}
