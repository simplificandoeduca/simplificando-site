/* Play Space Finanças 2.1: mais usabilidade, como num sistema de gestão financeira (pedido do dono: "as informações mais claras;
   registrar fornecedor"). As telas novas:
   - Painel financeiro: o mês num olhar (receitas, despesas, resultado, o que vence), as contas que vencem, entradas e saídas dia a dia,
     os maiores fornecedores, para onde foi o dinheiro (centros de custo) e os últimos lançamentos.
   - Contas a pagar: as vencidas, as de hoje, as dos próximos 7 dias e as depois; "Pagar" (o dia, a forma, o valor); as pagas do mês.
   - Fornecedores: o cadastro (nome, CNPJ/CPF, contato, Pix, banco, o centro de custo de costume) e a ficha (o pago e o a pagar).
   - A despesa (formulário novo): o fornecedor, "Paga" ou "A pagar", o vencimento, a forma e repetir todo mês.
   Usa o que a tela já tem (index.html e gestao.js): $, $$, esc, brl, brlCurto, int, dataBR, quandoBR, iso, api, aviso, kpi, GS, gFolha… */
'use strict';
const GNOVO_DESP = { receita: 'Nova receita', despesa: 'Nova despesa' };
async function gDepois() { await gSujou(); if (ABA === 'lanc') carregarLanc(); else gestaoDesenhar(ABA); }
const gVar = (a, b) => { if (!(b > 0.004)) return ''; const p = (a - b) / b * 100; return `${p >= 0 ? '+' : '−'}${Math.abs(p).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}% que o mês passado`; };
function gMesNav() { return `<div class="chips"><button class="chip" data-gpm="-1" aria-label="Mês anterior">‹</button><b class="g-mes">${esc(gMesNome(GS.mes))}</b><button class="chip" data-gpm="1" aria-label="Mês seguinte">›</button></div>`; }
function gLigaMesNav(depois) { $$('[data-gpm]').forEach(b => b.onclick = () => { const [a, m] = GS.mes.split('-').map(Number), d = new Date(a, m - 1 + +b.dataset.gpm, 1); GS.mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; GS.dados = null; gGuarda(); depois(); }); }
/* o selo da recorrência ("3/12 · Recorrente mensal") */
function gRec(l) { return l && l.serie_tot > 1 ? `<span class="g-rec" title="Recorrente: ${l.serie_n}ª de ${l.serie_tot}, todo mês"><i>${l.serie_n}/${l.serie_tot}</i>Recorrente<small>mensal</small></span>` : ''; }
function gVence(l) { const h = gHoje(); return l.vencimento < h ? `<span class="selo ruim">Vencida · ${dataBR(l.vencimento).slice(0, 5)}</span>` : l.vencimento === h ? '<span class="selo aviso">Vence hoje</span>' : `<span class="selo neutro">${dataBR(l.vencimento).slice(0, 5)}</span>`; }

/* ---------- o painel financeiro do mês ---------- */
async function carregarPainel() {
  const n = ++GS.n; try { const r = await api('fl_painel', { mes: GS.mes }); if (n !== GS.n) return; GS.painel = r; } catch (e) { aviso(e.message); return; }
  if (ABA === 'fin') gestaoDesenhar('fin');
}
function telaPainel() {
  const P = GS.painel && GS.painel.mes === GS.mes ? GS.painel : null;
  const cab = `<div class="topo"><div><h1>Painel financeiro</h1><div class="sub">O mês num olhar: o que entrou, o que saiu e o que vence · ${esc(gMesNome(GS.mes))}</div></div>
      <div class="lista-acoes"><button class="btn btn-ghost" data-gn="receita">+ Receita</button><button class="btn btn-ghost" data-gn="despesa">+ Despesa</button><button class="btn btn-brand g-novo" data-gn="apagar">+ Conta a pagar</button></div></div>
    <div class="filtros">${gMesNav()}</div>`;
  if (!P) return cab + '<div class="vazio">Carregando…</div>';
  const A = P.atual, B = P.anterior, S = P.apagar;
  const kp = `<div class="grade">${kpi('Receitas do mês', brl(A.receita), gVar(A.receita, B.receita) || 'o faturamento lançado')}${kpi('Despesas do mês', brl(A.despesa), (gVar(A.despesa, B.despesa) || 'custo fixo + variável') + (A.inv > 0.004 ? ` · investimento ${brl(A.inv)} à parte` : ''))}
    ${kpi('Resultado', brl(A.resultado), A.receita > 0.004 ? `margem ${gPct(A.resultado, A.receita)}` : 'receitas menos despesas', A.resultado < 0 ? 'alerta' : '')}${kpi('A pagar (total devido)', brl(S.total.v), (S.vencidas.n ? `<b style="color:var(--bad)">em atraso ${brl(S.vencidas.v)}</b>` : 'nada em atraso') + (S.vencemHoje.n ? ` · hoje ${brl(S.vencemHoje.v)}` : '') + ` · ${int(S.total.n)} ${S.total.n === 1 ? 'conta' : 'contas'}`, S.vencidas.n ? 'alerta' : S.total.n ? 'devido' : '')}</div>`;
  const prox = P.proximas.length ? `<table class="g-lanc"><tbody>${P.proximas.map(l => `<tr data-gpl="${l.id}" tabindex="0" title="Abrir"><td>${gVence(l)}</td><td>${l.fornecedor ? `<b>${esc(l.fornecedor)}</b><br>` : ''}${esc(l.descricao)}${gRec(l)}</td><td class="v">${brl(l.valor)}</td><td class="v"><button class="btn btn-ghost g-mini" data-gpg="${l.id}">Pagar</button></td></tr>`).join('')}</tbody></table>`
    : '<div class="vazio">Nada vencido nem vencendo nos próximos 7 dias. 🎉</div>';
  const top = (L, cor, ir) => { const mx = Math.max(1, ...L.map(x => x.v)); return L.length ? `<div class="g-barras">${L.map(x => `<div class="g-b"${ir && x.id ? ` data-gfo="${x.id}" style="cursor:pointer"` : ''}><span class="g-bn" title="${esc(x.nome)}">${esc(x.nome)}</span><span class="g-bt"><i style="width:${Math.max(0.5, x.v / mx * 100)}%;background:${cor}"></i></span><span class="g-bv num">${brl(x.v)}</span></div>`).join('')}</div>` : '<div class="vazio">Nada no mês.</div>'; };
  const tipoN = { receita: 'Receita', despesa: 'Despesa', recebimento: 'Recebimento', rede: 'Rede' };
  return cab + kp + `<div class="dois"><div class="cartao"><div class="cab-cartao"><div><h2>Contas que vencem</h2><p class="expl">As vencidas e as dos próximos 7 dias.</p></div><button class="link g-ir" data-ir="pagar">Ver todas</button></div>${prox}</div>
      <div class="cartao"><h2>Entradas e saídas, dia a dia</h2><p class="expl">Receitas e despesas lançadas em cada dia do mês.</p><div class="legenda"><span><i style="background:${GCOR.bruto}"></i>Receitas</span><span><i style="background:${GCOR.desp}"></i>Despesas</span></div><div class="grafico" id="g-pdias"></div></div></div>
    <div class="dois"><div class="cartao"><h2>Maiores fornecedores</h2><p class="expl">Quem mais recebeu no mês (toque para ver a ficha).</p>${top(P.fornecedores, GCOR.desp, true)}</div>
      <div class="cartao"><h2>Para onde foi o dinheiro</h2><p class="expl">As despesas do mês por centro de custo (sem o investimento).</p>${top(P.centros, GCOR.desp)}</div></div>
    <div class="cartao"><h2>Últimos lançamentos</h2><p class="expl">O que foi lançado ou corrigido por último, e por quem.</p>${P.ultimos.length ? `<div class="rolar"><table class="g-lanc"><thead><tr><th>Quando</th><th>Quem</th><th>Tipo</th><th>Descrição</th><th class="v">Valor</th></tr></thead><tbody>${P.ultimos.map(l => `<tr data-gpl="${l.id}" data-gpt="${l.tipo}" tabindex="0" title="Abrir"><td>${esc(quandoBR(l.alterado_em > l.criado_em ? l.alterado_em : l.criado_em))}</td><td>${esc(l.alterado_em > l.criado_em ? l.alterado_por : l.criado_por)}</td><td>${tipoN[l.tipo] || l.tipo}</td><td>${l.fornecedor ? `<b>${esc(l.fornecedor)}</b> · ` : ''}${esc(l.descricao || gLocal(l.filial, l.segmento))}</td><td class="v">${brl(l.valor)}</td></tr>`).join('')}</tbody></table></div>` : '<div class="vazio">Nada lançado no sistema ainda.</div>'}</div>`;
}
function ligarPainel() {
  gLigaMesNav(() => { GS.painel = null; gestaoDesenhar('fin'); carregarPainel(); });
  $$('[data-gn]').forEach(b => b.onclick = () => b.dataset.gn === 'receita' ? formLanc('receita') : formDespesa(null, b.dataset.gn === 'apagar' ? 'aberto' : 'pago'));
  $$('[data-ir]').forEach(b => b.onclick = () => { ABA = b.dataset.ir; desenhar(); window.scrollTo(0, 0); });
  $$('[data-gfo]').forEach(b => b.onclick = () => fichaFornecedor(+b.dataset.gfo));
  const P = GS.painel; if (!P) return; const acha = id => P.proximas.concat(P.ultimos).find(x => x.id === id);
  $$('[data-gpg]').forEach(b => b.onclick = e => { e.stopPropagation(); const l = acha(+b.dataset.gpg); if (l) folhaPagar(l); });
  $$('tr[data-gpl]').forEach(tr => { const abre = () => { const l = acha(+tr.dataset.gpl); if (l) l.tipo === 'despesa' ? formDespesa(l) : formLanc(l.tipo, l); }; tr.onclick = abre; tr.onkeydown = e => { if (e.key === 'Enter') abre(); }; });
  gGrafDias();
}
/* as barras de cada dia: receitas (azul) e despesas (laranja), com a dica ao passar o dedo/mouse */
function gGrafDias() {
  const box = $('#g-pdias'), P = GS.painel; if (!box || !P) return; const [a, m] = P.mes.split('-').map(Number), nd = new Date(a, m, 0).getDate(), R = {}, Dp = {};
  P.dias.forEach(([d, t, v]) => { (t === 'receita' ? R : Dp)[d] = v; });
  const W = Math.max(300, box.clientWidth || 520), H = 200, pl = 56, pr = 6, pt = 10, pb = 22, cw = (W - pl - pr) / nd, bw = Math.max(2, Math.min(9, cw * 0.36));
  const mx = Math.max(1, ...Object.values(R), ...Object.values(Dp)), y = v => pt + (H - pt - pb) * (1 - v / mx);
  let s = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Receitas e despesas de cada dia">`;
  [0, 0.5, 1].forEach(k => { s += `<line class="grade-l" x1="${pl}" x2="${W - pr}" y1="${y(mx * k)}" y2="${y(mx * k)}"/><text class="eixo" x="${pl - 6}" y="${y(mx * k) + 4}" text-anchor="end">${brlCurto(mx * k)}</text>`; });
  for (let d = 1; d <= nd; d++) { const cx = pl + cw * (d - 1) + cw / 2;
    s += `<rect class="alvo" data-d="${d}" x="${cx - cw / 2}" y="${pt}" width="${cw}" height="${H - pt - pb}"/><g data-g="${d}" style="pointer-events:none">`;
    for (const [v, cor, lado] of [[R[d] || 0, GCOR.bruto, -1], [Dp[d] || 0, GCOR.desp, 1]]) if (v > 0.004) { const t = y(v), b = y(0), r = Math.min(3, bw / 2, b - t), x0 = lado < 0 ? cx - bw - 0.5 : cx + 0.5;
      s += `<path d="M${x0},${b} V${t + r} Q${x0},${t} ${x0 + r},${t} H${x0 + bw - r} Q${x0 + bw},${t} ${x0 + bw},${t + r} V${b} Z" fill="${cor}"/>`; }
    s += '</g>'; if (d === 1 || d % 5 === 0) s += `<text class="eixo" x="${cx}" y="${H - 6}" text-anchor="middle">${d}</text>`; }
  box.innerHTML = s + '</svg><div class="dica-g" hidden></div>';
  const svg = $('svg', box), dica = $('.dica-g', box);
  $$('.alvo', svg).forEach(al => { const mostra = () => { const d = +al.dataset.d, r = svg.getBoundingClientRect(), k2 = r.width / W; $$('g[data-g]', svg).forEach(e => e.classList.toggle('on', e.dataset.g === String(d)));
    dica.innerHTML = `<b>${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}</b><div><i style="background:${GCOR.bruto}"></i>Receitas: ${brl(R[d] || 0)}</div><div><i style="background:${GCOR.desp}"></i>Despesas: ${brl(Dp[d] || 0)}</div>`;
    dica.hidden = false; const meia = dica.offsetWidth / 2; dica.style.left = Math.min(Math.max((pl + cw * (d - 1) + cw / 2) * k2, meia), r.width - meia) + 'px'; dica.style.top = '30px'; };
    al.addEventListener('mouseenter', mostra); al.addEventListener('click', mostra); });
  box.addEventListener('mouseleave', () => { dica.hidden = true; $$('g[data-g]', svg).forEach(e => e.classList.remove('on')); });
}

/* ---------- contas a pagar ---------- */
async function carregarPagar() {
  const n = ++GS.n; try { const r = await api('ap_listar', GS.pagarVer === 'pagas' ? { ver: 'pagas', mes: GS.mes } : {}); if (n !== GS.n) return; GS.pagar = r; GS.apagar = r.somas; gContaPagar(); } catch (e) { aviso(e.message); return; }
  if (ABA === 'pagar') gestaoDesenhar('pagar');
}
function telaPagar() {
  const P = GS.pagar, pagas = GS.pagarVer === 'pagas';
  const cab = `<div class="topo"><div><h1>Contas a pagar</h1><div class="sub">O que vence e o que já foi pago · toque em <b>Pagar</b> quando pagar uma conta</div></div>
      <div class="lista-acoes"><button class="btn btn-brand g-novo" data-gp="nova">+ Conta a pagar</button></div></div>`;
  const S = P ? P.somas : GS.apagar;
  const nc = n => `${int(n)} ${n === 1 ? 'conta' : 'contas'}`;
  const kp = S ? `<div class="grade">${kpi('Em atraso', brl(S.vencidas.v), S.vencidas.n ? `${nc(S.vencidas.n)} vencidas` : 'nada vencido', S.vencidas.n ? 'alerta' : '')}${kpi('A pagar hoje', brl(S.vencemHoje.v), S.vencemHoje.n ? `${nc(S.vencemHoje.n)} vencem hoje` : 'nada vence hoje', S.vencemHoje.n ? 'devido' : '')}${kpi('Próximos 7 dias', brl(S.semana.v), nc(S.semana.n))}${kpi('Total devido', brl(S.total.v), `${nc(S.total.n)} a pagar, de todos os meses`, S.total.n ? 'devido' : '')}</div>` : '';
  const filtro = `<div class="filtros"><div class="chips"><button class="chip ${!pagas ? 'on' : ''}" data-gpv="abertas">A pagar</button><button class="chip ${pagas ? 'on' : ''}" data-gpv="pagas">Pagas</button>${pagas ? `<span style="width:8px"></span>${gMesNav().replace(/^<div class="chips">|<\/div>$/g, '')}` : ''}</div></div>`;
  if (!P) return cab + kp + filtro + '<div class="vazio">Carregando…</div>';
  const linha = l => `<tr data-gpl="${l.id}" tabindex="0" title="Abrir a conta"><td>${pagas ? `<span class="selo bom">Paga ${dataBR(l.pago_em || l.data).slice(0, 5)}</span>` : gVence(l)}</td><td>${l.fornecedor ? `<b>${esc(l.fornecedor)}</b><br>` : ''}${esc(l.descricao)}${gRec(l)}${gComp(l)}${l.obs ? `<small>${esc(l.obs)}</small>` : ''}</td><td>${l.filial || l.segmento ? esc(gLocal(l.filial, l.segmento)) : '—'}</td><td>${esc(l.centro || l.grupo)}</td><td>${pagas ? esc(GFPAG[l.forma] || '—') : `vence ${dataBR(l.vencimento)}`}</td><td class="v">${brl(l.valor)}</td><td class="v">${gDevido(l)}</td><td class="v g-tog-td">${gTog(l)}${pagas ? '' : `<button class="btn btn-ghost g-mini" data-gpg="${l.id}">Pagar</button>`}</td></tr>`;
  const cabT = `<thead><tr><th>${pagas ? 'Situação' : 'Vencimento'}</th><th>Fornecedor · descrição</th><th>Local</th><th>Centro de custo</th><th>${pagas ? 'Forma' : 'Data'}</th><th class="v">Valor</th><th class="v">Devido</th><th class="v">Pago?</th></tr></thead>`;
  let corpo;
  if (!P.linhas.length) corpo = `<div class="vazio">${pagas ? `Nenhuma conta paga em ${esc(gMesNome(GS.mes).toLowerCase())}.` : 'Nenhuma conta a pagar. Lance as contas (aluguel, luz, fornecedores) com o vencimento para não esquecer nenhuma.'}<br><button class="btn btn-ghost" style="margin-top:12px" data-gp="nova">+ Conta a pagar</button></div>`;
  else if (pagas) corpo = `<div class="rolar"><table class="g-lanc">${cabT}<tbody>${P.linhas.map(linha).join('')}</tbody><tfoot><tr><td colspan="5">Total pago no mês</td><td class="v">${brl(P.linhas.reduce((t, l) => t + l.valor, 0))}</td><td class="v">R$ 0,00</td><td></td></tr></tfoot></table></div>`;
  else { const h = gHoje(), s7 = iso(new Date(Date.now() + 7 * 86400000)), G = [['Vencidas', l => l.vencimento < h], ['Vencem hoje', l => l.vencimento === h], ['Próximos 7 dias', l => l.vencimento > h && l.vencimento <= s7], ['Depois', l => l.vencimento > s7]];
    corpo = `<div class="rolar"><table class="g-lanc">${cabT}<tbody>${G.map(([nome, f]) => { const L = P.linhas.filter(f); return L.length ? `<tr class="g-dia${nome === 'Vencidas' ? ' atraso' : ''}"><td colspan="8"><b>${nome === 'Vencidas' ? 'Em atraso' : nome}</b><span>${int(L.length)} · devido ${brl(L.reduce((t, l) => t + l.valor, 0))}</span></td></tr>` + L.map(linha).join('') : ''; }).join('')}</tbody>
      <tfoot><tr><td colspan="6">Total devido</td><td class="v">${brl(P.linhas.reduce((t, l) => t + l.valor, 0))}</td><td></td></tr></tfoot></table></div>`; }
  return cab + kp + filtro + `<div class="cartao">${corpo}</div>`;
}
function ligarPagar() {
  $$('[data-gp]').forEach(b => b.onclick = () => formDespesa(null, 'aberto'));
  $$('[data-gpv]').forEach(b => b.onclick = () => { GS.pagarVer = b.dataset.gpv; GS.pagar = null; gestaoDesenhar('pagar'); });
  gLigaMesNav(() => { GS.pagar = null; gestaoDesenhar('pagar'); });
  const acha = id => (GS.pagar ? GS.pagar.linhas : []).find(x => x.id === id);
  $$('[data-gpg]').forEach(b => b.onclick = e => { e.stopPropagation(); const l = acha(+b.dataset.gpg); if (l) folhaPagar(l); });
  $$('tr[data-gpl]').forEach(tr => { const abre = () => { const l = acha(+tr.dataset.gpl); if (l) formDespesa(l); }; tr.onclick = abre; tr.onkeydown = e => { if (e.key === 'Enter') abre(); }; });
  gLigaTog(acha);
}
function folhaPagar(l) {
  gFolha('Pagar conta', `<div class="g-resumo-conta"><b>${esc(l.fornecedor || l.descricao)}</b>${l.fornecedor ? `<span>${esc(l.descricao)}</span>` : ''}<span>Vence em ${dataBR(l.vencimento)} · ${brl(l.valor)}</span></div>
    <div class="g-grid">${gCampo('g-pdata', 'Data do pagamento', gInp('g-pdata', gHoje(), 'type="date" required'))}${gCampo('g-pforma', 'Forma de pagamento', `<select class="inp" id="g-pforma">${Object.entries(GFPAG).map(([k, n]) => `<option value="${k}"${k === (l.forma || 'pix') ? ' selected' : ''}>${n}</option>`).join('')}</select>`)}
      ${gCampo('g-pvalor', 'Valor pago (R$) <small>mude se teve juros ou desconto</small>', gInp('g-pvalor', gValor(l.valor), 'inputmode="decimal" required'), true)}</div>`, {
    ok: 'Marcar como paga', fazer: async F => { await api('ap_pagar', { id: l.id, pago_em: $('#g-pdata', F).value, forma: $('#g-pforma', F).value, valor: $('#g-pvalor', F).value }); aviso('Conta paga ✓'); gDepois(); } });
}

/* ---------- a despesa: o fornecedor, paga ou a pagar, o vencimento, a forma, repetir todo mês ---------- */
function formDespesa(l, sitIni) {
  const L = GS.listas, u = GS.ult.despesa || {}, hoje = gHoje();
  const x = l || { data: u.data || (GS.mes === hoje.slice(0, 7) ? hoje : GS.mes + '-01'), filial: u.filial || '', segmento: u.segmento || '', grupo: u.grupo || '', forma: u.forma || '' };
  let sit = l ? (l.situacao === 'aberto' ? 'aberto' : 'pago') : (sitIni || 'pago');
  const pagoEm = l ? (l.pago_em || l.data) : x.data, venc = l ? l.vencimento : (sit === 'aberto' ? x.data : '');
  const c = `<div class="g-c g-larg"><span class="lbl">Situação</span><div class="g-seg2" role="group" aria-label="Situação"><button type="button" data-sit="pago">Paga</button><button type="button" data-sit="aberto">A pagar</button></div><input type="hidden" id="g-sit" value="${sit}"></div>
    ${gCampo('g-forn', 'Fornecedor <small>(quem recebeu; um nome novo é cadastrado sozinho)</small>', gInp('g-forn', l ? l.fornecedor : '', 'list="g-dl-forn" autocomplete="off" placeholder="Opcional: Enel, Sabesp, o nome da loja…"') + gDl('g-dl-forn', GS.forn.map(f => f.nome)), true)}
    ${gCampo('g-desc', 'Descrição', gInp('g-desc', x.descricao, 'required data-limpa placeholder="Ex.: Conta de luz de outubro"'), true)}
    ${gCampo('g-valor', 'Valor (R$)', gInp('g-valor', gValor(x.valor), 'inputmode="decimal" placeholder="0,00" required data-limpa'))}
    <div class="g-c" data-so="pago">${'<label class="lbl" for="g-data">Data do pagamento</label>' + gInp('g-data', pagoEm, 'type="date"')}</div>
    <div class="g-c"><label class="lbl" for="g-venc" id="g-venc-rot">Vencimento</label>${gInp('g-venc', venc, 'type="date"')}</div>
    ${gCampo('g-forma', 'Forma de pagamento', `<select class="inp" id="g-forma"><option value="">—</option>${Object.entries(GFPAG).map(([k, n]) => `<option value="${k}"${k === x.forma ? ' selected' : ''}>${n}</option>`).join('')}</select>`)}
    ${gCampo('g-grupo', 'Grupo de despesa', `<select class="inp" id="g-grupo">${gOpcoes(L.grupo, x.grupo || 'Custo Fixo')}</select>`)}
    ${gCampo('g-centro', 'Centro de custo', gInp('g-centro', x.centro, 'list="g-dl-centro" autocomplete="off" placeholder="RH, Aluguel, Luz…"') + gDl('g-dl-centro', L.centro))}
    ${gCampo('g-filial', 'Filial', gInp('g-filial', x.filial, 'list="g-dl-filial" autocomplete="off" placeholder="Morumbi, Anália Franco…"') + gDl('g-dl-filial', L.filial))}
    ${gCampo('g-seg', 'Segmento', gInp('g-seg', x.segmento, 'list="g-dl-seg" autocomplete="off" placeholder="Play, Casa Tom…"') + gDl('g-dl-seg', L.segmento))}
    ${l ? (l.serie_tot > 1 ? `<div class="g-c g-larg"><div class="g-rec-box">${gRec(l)}<label class="chk" style="margin:0"><input type="checkbox" id="g-prox"> Mudar também as próximas que ainda estão a pagar (o valor, o fornecedor, a descrição…)</label></div></div>` : '')
      : `<div class="g-c g-larg"><div class="g-rec-box"><label class="g-switch"><input type="checkbox" id="g-recorr"><i></i><b>Recorrente</b><span class="sub">repete todo mês (aluguel, salário, internet…)</span></label>
        <span class="g-rec-vezes" hidden><label for="g-rep">Quantas vezes</label><input class="inp" id="g-rep" type="number" min="2" max="60" value="12" inputmode="numeric"><span class="sub">meses · as próximas entram como contas a pagar, no mesmo dia de cada mês</span></span></div></div>`}
    ${gCampo('g-obs', 'Observações', gInp('g-obs', x.obs, 'data-limpa placeholder="Opcional"'), true)}
    ${cpBloco(l)}`;   /* 3.0: o comprovante */
  const quem = l ? `<p class="sub" style="margin:10px 0 0">${l.origem === 'planilha' ? 'Veio da planilha' : 'Lançado'} por ${esc(l.criado_por || '—')} em ${esc(quandoBR(l.criado_em))}${l.alterado_por ? ` · corrigido por ${esc(l.alterado_por)} em ${esc(quandoBR(l.alterado_em))}` : ''}</p>` : '';
  const f = gFolha(l ? 'Corrigir despesa' : sit === 'aberto' ? 'Nova conta a pagar' : 'Nova despesa', `<div class="g-grid">${c}</div>${quem}`, {
    ok: l ? 'Salvar' : 'Lançar', outra: l ? '' : 'Lançar e mais um', remover: l ? 'Tirar este lançamento' : '',
    fazer: async F => { const val = id => ($('#' + id, F) || {}).value || '', s2 = val('g-sit'), vc = val('g-venc'), pe = val('g-data');
      if (!val('g-valor').trim()) throw new Error('Digite o valor.');
      if (s2 === 'aberto' && !vc) throw new Error('Escolha o vencimento.'); if (s2 === 'pago' && !pe) throw new Error('Escolha a data do pagamento.');
      const dados = { situacao: s2, data: s2 === 'aberto' ? vc : (vc || pe), pago_em: s2 === 'pago' ? pe : '', vencimento: vc, valor: val('g-valor'), descricao: val('g-desc'), fornecedor: val('g-forn').trim(), forma: val('g-forma'),
        grupo: val('g-grupo'), centro: val('g-centro'), filial: val('g-filial'), segmento: val('g-seg'), obs: val('g-obs') };
      const rc = $('#g-recorr', F), pr = $('#g-prox', F), rep = rc && rc.checked ? Math.max(2, Math.min(60, +val('g-rep') || 12)) : 1;
      const r = await api('fl_salvar', { id: l ? l.id : 0, tipo: 'despesa', lanc: dados, repetir: rep, proximas: !!(pr && pr.checked) }); GS.listas = r.listas || GS.listas;
      if (!l) await cpDepoisDeLancar(r.lanc);   /* 3.0: o comprovante escolhido antes de lançar */
      if (!l) { GS.ult.despesa = { data: pe || vc, filial: r.lanc.filial, segmento: r.lanc.segmento, grupo: r.lanc.grupo, forma: r.lanc.forma, situacao: s2 }; gGuarda(); }
      if (r.repetidas > 1) aviso(`Recorrente ✓ ${int(r.repetidas)} meses lançados (as próximas, a pagar)`); else if (r.proximas) aviso(`Salvo ✓ e mais ${int(r.proximas)} ${r.proximas === 1 ? 'conta' : 'contas'} da recorrência`); else if (!l && r.lanc.data.slice(0, 7) !== GS.mes) aviso(`Lançado em ${dataBR(r.lanc.data)} (outro mês)`);
      gDepois(); },
    aoRemover: async () => { const pr = $('#g-prox', f), r = await api('fl_remover', { id: l.id, proximas: !!(pr && pr.checked) }); aviso(r.proximas ? `Tirado ✓ e mais ${int(r.proximas)} da recorrência` : 'Lançamento tirado ✓'); gDepois(); } });
  cpLigar(f, l);   /* 3.0 */
  const rc = $('#g-recorr', f); if (rc) rc.onchange = () => { $('.g-rec-vezes', f).hidden = !rc.checked; if (rc.checked) $('#g-rep', f).select(); };
  const pr0 = $('#g-prox', f); if (pr0) pr0.onchange = () => { const t = $('[data-tirar]', f); if (t && !t.dataset.certeza) t.textContent = pr0.checked ? 'Tirar esta e as próximas a pagar' : 'Tirar este lançamento'; };
  const mostra = () => { $$('[data-sit]', f).forEach(b => b.classList.toggle('on', b.dataset.sit === sit)); $('#g-sit', f).value = sit;
    $$('[data-so]', f).forEach(e => { e.hidden = e.dataset.so !== sit; }); $('#g-venc-rot', f).innerHTML = sit === 'aberto' ? 'Vencimento' : 'Vencimento <small>(se tinha)</small>'; };
  $$('[data-sit]', f).forEach(b => b.onclick = () => { sit = b.dataset.sit; const v = $('#g-venc', f), d = $('#g-data', f); if (sit === 'aberto' && !v.value) v.value = d.value || hoje; mostra(); });
  mostra();
  const fo = $('#g-forn', f); fo.addEventListener('change', () => { const k = gNorm(fo.value), F2 = GS.forn.find(x2 => gNorm(x2.nome) === k); if (!F2) return; const ce = $('#g-centro', f), gr = $('#g-grupo', f);
    if (F2.centro && !ce.value) ce.value = F2.centro; if (F2.grupo && [...gr.options].some(o => o.value === F2.grupo)) gr.value = F2.grupo; });
  return f;
}

/* ---------- fornecedores ---------- */
async function carregarForn() {
  const n = ++GS.n; try { const r = await api('fn_listar', { busca: GS.fornBusca }); if (n !== GS.n) return; GS.fornL = r; } catch (e) { aviso(e.message); return; }
  if (ABA === 'forn') { const foco = document.activeElement && document.activeElement.id === 'g-fn-busca', pos = foco ? document.activeElement.selectionStart : 0; gestaoDesenhar('forn'); if (foco) { const b = $('#g-fn-busca'); if (b) { b.focus(); b.setSelectionRange(pos, pos); } } }
}
function telaForn() {
  const R = GS.fornL;
  const cab = `<div class="topo"><div><h1>Fornecedores</h1><div class="sub">Quem recebe as despesas: o cadastro, quanto foi pago e o que está a pagar</div></div>
      <div class="lista-acoes"><button class="btn btn-ghost" data-gfn title="Cadastrar os fornecedores das notas fiscais recebidas (sistema de notas)">Trazer das notas fiscais</button><button class="btn btn-brand g-novo" data-gf="novo">+ Novo fornecedor</button></div></div>
    <div class="filtros"><div class="chips"><input class="chip g-busca" id="g-fn-busca" type="search" placeholder="Buscar fornecedor, CNPJ, contato…" value="${esc(GS.fornBusca)}" aria-label="Buscar"></div></div>`;
  if (!R) return cab + '<div class="vazio">Carregando…</div>';
  if (!R.fornecedores.length) return cab + `<div class="cartao"><div class="vazio">${GS.fornBusca ? 'Nenhum fornecedor com esta busca.' : 'Nenhum fornecedor ainda. Cadastre aqui, ou escreva o nome do fornecedor ao lançar uma despesa: ele entra na lista sozinho.'}<br><button class="btn btn-ghost" style="margin-top:12px" data-gf="novo">+ Novo fornecedor</button></div></div>`;
  return cab + `<div class="cartao"><div class="cab-cartao"><div><h2>${int(R.fornecedores.length)} ${R.fornecedores.length === 1 ? 'fornecedor' : 'fornecedores'}</h2><p class="expl">Toque num fornecedor para ver a ficha (as despesas, o pago e o a pagar).</p></div></div>
    <div class="rolar"><table class="g-lanc"><thead><tr><th>Fornecedor</th><th>Contato</th><th>Centro de custo</th><th class="v">Despesas</th><th class="v">Pago em ${R.ano}</th><th class="v">A pagar</th><th>Última</th></tr></thead><tbody>${R.fornecedores.map(x => `<tr data-gfv="${x.id}" tabindex="0" title="Ver a ficha"><td><b>${esc(x.nome)}</b>${x.documento ? `<small>${esc(x.documento)}</small>` : ''}</td><td>${esc(x.contato || '')}${x.telefone ? `<small>${esc(x.telefone)}</small>` : ''}${x.email ? `<small>${esc(x.email)}</small>` : ''}</td><td>${esc(x.centro || '—')}</td><td class="v">${int(x.n)}</td><td class="v">${brl(x.ano)}</td><td class="v">${x.aberto > 0.004 ? `<span class="selo aviso">${brl(x.aberto)}</span>` : '—'}</td><td>${x.ult ? dataBR(x.ult) : '—'}</td></tr>`).join('')}</tbody></table></div></div>`;
}
function ligarForn() {
  $$('[data-gf]').forEach(b => b.onclick = () => formFornecedor());
  $$('[data-gfn]').forEach(b => b.onclick = () => fornDasNotas(b));   /* 3.7 */
  const bu = $('#g-fn-busca'); if (bu) { let t0 = 0; bu.oninput = () => { clearTimeout(t0); t0 = setTimeout(() => { GS.fornBusca = bu.value.trim(); carregarForn(); }, 300); }; }
  $$('tr[data-gfv]').forEach(tr => { const abre = () => fichaFornecedor(+tr.dataset.gfv); tr.onclick = abre; tr.onkeydown = e => { if (e.key === 'Enter') abre(); }; });
}
function formFornecedor(fo) {
  const x = fo || {}, L = GS.listas;
  gFolha(fo ? 'Corrigir fornecedor' : 'Novo fornecedor', `<div class="g-grid">${gCampo('g-fnome', 'Nome', gInp('g-fnome', x.nome, 'required placeholder="Razão social ou nome"'), true)}
      ${gCampo('g-fdoc', 'CNPJ ou CPF', gInp('g-fdoc', x.documento, 'inputmode="numeric" placeholder="Opcional"'))}${gCampo('g-fcont', 'Contato', gInp('g-fcont', x.contato, 'placeholder="Com quem falar"'))}
      ${gCampo('g-ftel', 'Telefone', gInp('g-ftel', x.telefone, 'inputmode="tel"'))}${gCampo('g-femail', 'E-mail', gInp('g-femail', x.email, 'type="email"'))}
      ${gCampo('g-fpix', 'Chave Pix', gInp('g-fpix', x.pix))}${gCampo('g-fbanco', 'Banco, agência e conta', gInp('g-fbanco', x.banco))}
      ${gCampo('g-fcentro', 'Centro de custo de costume', gInp('g-fcentro', x.centro, 'list="g-dl-fcentro" autocomplete="off" placeholder="Luz, Aluguel…"') + gDl('g-dl-fcentro', L.centro))}
      ${gCampo('g-fgrupo', 'Grupo de costume', `<select class="inp" id="g-fgrupo">${gOpcoes(L.grupo, x.grupo || '', '—')}</select>`)}
      ${gCampo('g-fobs', 'Observações', gInp('g-fobs', x.obs, 'placeholder="Opcional"'), true)}</div><p class="sub" style="margin:10px 0 0">O centro de custo e o grupo de costume já vêm preenchidos quando você escolhe este fornecedor numa despesa.</p>`, {
    ok: 'Salvar', remover: fo ? 'Tirar este fornecedor' : '',
    fazer: async F => { const v = id => $('#' + id, F).value; const r = await api('fn_salvar', { id: fo ? fo.id : 0, fornecedor: { nome: v('g-fnome'), documento: v('g-fdoc'), contato: v('g-fcont'), telefone: v('g-ftel'), email: v('g-femail'), pix: v('g-fpix'), banco: v('g-fbanco'), centro: v('g-fcentro'), grupo: v('g-fgrupo'), obs: v('g-fobs') } });
      aviso(fo ? 'Fornecedor salvo ✓' : 'Fornecedor cadastrado ✓'); GS.listas = r.listas || GS.listas; gDepois(); },
    aoRemover: async () => { await api('fn_remover', { id: fo.id }); aviso('Fornecedor tirado ✓ (as despesas continuam com o nome)'); gDepois(); } });
}
/* 3.7 (1.84): trazer os fornecedores das notas fiscais. Pergunta ao sistema de notas (no mesmo navegador, com o login do Notas: o de
   Emitir nota) os fornecedores das notas recebidas (as que o "Trazer o histórico" trouxe do Emissor Nacional), mostra a lista com tudo
   marcado e cadastra os marcados de uma vez. Quem já está no cadastro só ganha o que faltava. */
async function fornDasNotas(b) {
  if (b) b.disabled = true; let R = null, erro = '';
  try { const x = await fetch('../notas/api/api.php?acao=fornecedores', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: '{}' });
    let j = {}; try { j = await x.json(); } catch (e) { /* resposta vazia */ }
    if (x.status === 401) erro = 'Entre no sistema de notas (no menu: Emitir nota) e toque aqui de novo.';
    else if (x.status === 404) erro = 'Não achei o sistema de notas neste site.';
    else if (!x.ok || j.ok === false) erro = j.erro || 'O sistema de notas não respondeu (erro ' + x.status + ').';
    else R = j; }
  catch (e) { erro = 'Não deu para falar com o sistema de notas. Confira a internet.'; }
  if (b) b.disabled = false;
  if (erro) { aviso(erro); return; }
  const L = R.fornecedores || [];
  if (!L.length) { gFolha('Fornecedores das notas fiscais', '<p class="sub" style="margin:0">Ainda não há nenhuma nota de fornecedor no sistema de notas. Para trazer as notas que a Play Space recebeu: <b>Emitir nota → Conferir no governo → Trazer o histórico</b>. Depois volte aqui.</p>', { ok: 'Entendi', fazer: async () => {} }); return; }
  const dig = s => String(s || '').replace(/\D/g, ''), fdoc = d => { const n = dig(d); return n.length === 14 ? n.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5') : n.length === 11 ? n.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4') : n; };
  const mes = d => { const [a, m] = String(d || '').split('-'); return a && m ? ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][+m - 1] + '/' + a : ''; };
  const obsDe = x => [x.fantasia && gNorm(x.fantasia) !== gNorm(x.nome) ? 'Nome fantasia: ' + x.fantasia + '.' : '', `Das notas fiscais (sistema de notas): ${x.n} ${x.n === 1 ? 'nota' : 'notas'}${x.primeira ? ', de ' + mes(x.primeira) + ' a ' + mes(x.ultima) : ''}, ${brl(x.total)}.`, x.cidade ? x.cidade + (x.uf ? '/' + x.uf : '') + '.' : ''].filter(Boolean).join(' ');
  const cad = ((GS.fornL || {}).fornecedores || []).concat(GS.forn || []), ja = x => cad.some(f => (dig(x.documento) && dig(f.documento) === dig(x.documento)) || gNorm(f.nome) === gNorm(x.nome));
  const nJa = L.filter(ja).length;
  gFolha('Fornecedores das notas fiscais', `<p class="sub" style="margin:0 0 10px">Achei <b>${int(L.length)} ${L.length === 1 ? 'fornecedor' : 'fornecedores'}</b> nas notas que a Play Space recebeu${nJa ? ` (${int(nJa)} já no cadastro)` : ''}. Os marcados entram no cadastro com o nome, o CNPJ, o telefone e o e-mail (quando a nota tem); quem já está só ganha o que faltava.</p>
    <div class="g-fni-l">${L.map((x, i) => `<label class="g-fni"><input type="checkbox" data-fni="${i}" checked><span><b>${esc(x.nome || fdoc(x.documento))}</b><small>${[fdoc(x.documento), `${int(x.n)} ${x.n === 1 ? 'nota' : 'notas'}`, brl(x.total), x.ultima ? 'última ' + dataBR(x.ultima) : ''].filter(Boolean).join(' · ')}${ja(x) ? ' · <em>já no cadastro</em>' : ''}</small></span></label>`).join('')}</div>`, {
    ok: 'Cadastrar os marcados',
    fazer: async F => { const M = $$('[data-fni]', F).filter(c => c.checked).map(c => L[+c.dataset.fni]); if (!M.length) throw new Error('Marque pelo menos um fornecedor.');
      const r = await api('fn_importar', { lista: M.map(x => ({ nome: x.nome || fdoc(x.documento), documento: x.documento, telefone: x.telefone, email: x.email, obs: obsDe(x) })) });
      aviso([r.novos ? `${int(r.novos)} ${r.novos === 1 ? 'fornecedor cadastrado' : 'fornecedores cadastrados'} ✓` : '', r.completos ? `${int(r.completos)} ${r.completos === 1 ? 'completado' : 'completados'}` : '', !r.novos && !r.completos ? 'Todos já estavam no cadastro' : ''].filter(Boolean).join(' · '));
      gDepois(); } });
}
async function fichaFornecedor(id) {
  let R; try { R = await api('fn_ver', { id }); } catch (e) { aviso(e.message); return; }
  const F = R.fornecedor, linha = (r, v) => v ? `<div class="g-info"><span>${r}</span><b>${esc(v)}</b></div>` : '';
  const pago = R.anos.reduce((t, x) => t + x.pago, 0), aberto = R.anos.reduce((t, x) => t + x.aberto, 0);
  const fundo = document.createElement('div'); fundo.className = 'fundo';
  fundo.innerHTML = `<div class="folha g-folha g-ficha" role="dialog" aria-modal="true" aria-label="${esc(F.nome)}"><h3>${esc(F.nome)}</h3>${F.removido ? '<span class="selo ruim">tirado da lista</span>' : ''}
    <div class="grade g-mini-kpi">${kpi('Pago (tudo)', brl(pago), `${int(R.despesas.length)} despesas`)}${kpi('A pagar', brl(aberto), '', aberto > 0.004 ? 'alerta' : '')}</div>
    <div class="g-infos">${linha('CNPJ/CPF', F.documento)}${linha('Contato', F.contato)}${linha('Telefone', F.telefone)}${linha('E-mail', F.email)}${linha('Chave Pix', F.pix)}${linha('Banco', F.banco)}${linha('Centro de custo', F.centro)}${linha('Grupo', F.grupo)}${linha('Observações', F.obs)}</div>
    ${R.anos.length ? `<p class="sub" style="margin:12px 0 4px">Por ano: ${R.anos.map(a => `<b>${a.ano}</b> ${brl(a.pago)}${a.aberto > 0.004 ? ` (+ ${brl(a.aberto)} a pagar)` : ''}`).join(' · ')}</p>` : ''}
    <div class="rolar" style="margin-top:8px">${R.despesas.length ? `<table class="g-lanc"><thead><tr><th>Data</th><th>Descrição</th><th>Situação</th><th class="v">Valor</th></tr></thead><tbody>${R.despesas.map(l => `<tr data-gfd="${l.id}" tabindex="0" title="Abrir"><td>${dataBR(l.data)}</td><td>${esc(l.descricao)}${gRec(l)}</td><td>${gSituacao(l)}</td><td class="v">${brl(l.valor)}</td></tr>`).join('')}</tbody></table>` : '<div class="vazio">Nenhuma despesa deste fornecedor ainda.</div>'}</div>
    <div class="acoes"><button type="button" class="btn btn-ghost" data-fechar>Fechar</button>${F.removido ? '' : '<button type="button" class="btn btn-ghost" data-fed>Corrigir cadastro</button><button type="button" class="btn btn-brand" data-fnd>+ Despesa</button>'}</div></div>`;
  document.body.appendChild(fundo); let ini = null; const fecha = () => { fundo.remove(); document.removeEventListener('keydown', k); }, k = e => { if (e.key === 'Escape') fecha(); };
  document.addEventListener('keydown', k); fundo.addEventListener('pointerdown', e => { ini = e.target; });
  fundo.addEventListener('click', e => { if ((e.target === fundo && ini === fundo && !String(getSelection() || '').trim()) || e.target.hasAttribute('data-fechar')) fecha(); });
  const ed = $('[data-fed]', fundo); if (ed) ed.onclick = () => { fecha(); formFornecedor(F); };
  const nd = $('[data-fnd]', fundo); if (nd) nd.onclick = () => { fecha(); const f2 = formDespesa(null, 'pago'); $('#g-forn', f2).value = F.nome; $('#g-forn', f2).dispatchEvent(new Event('change')); };
  $$('tr[data-gfd]', fundo).forEach(tr => tr.onclick = () => { const l = R.despesas.find(x => x.id === +tr.dataset.gfd); if (l) { fecha(); formDespesa(l); } });
}
