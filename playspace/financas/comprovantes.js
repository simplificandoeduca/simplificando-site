/* Play Space Finanças 3.0 (Play Space 1.69): o comprovante das despesas (os lançamentos e as contas a pagar).
   Pedido do dono: "o financeiro consegue adicionar o comprovante; tem um campo para ele adicionar, e esse comprovante vai bater o valor com
   o que está escrito lá; ele tem que conferir". Na despesa: anexar (foto ou PDF), o valor que está no comprovante (o PDF vem lido quando dá),
   Conferir → ✓ confere / ✗ não bate; baixar; trocar. Na lista, a situação de cada despesa. */
'use strict';
let CP_PENDENTE = null;   /* o arquivo escolhido numa despesa nova: vai junto quando ela for lançada */
const cpTam = n => n >= 1048576 ? (n / 1048576).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
const gComp = l => !l || l.tipo !== 'despesa' ? '' : !l.comp ? '<small class="cp-chip cp-falta">sem comprovante</small>'
  : l.comp.sit === 'confere' ? '<small class="cp-chip cp-ok">📎 ✓ comprovante confere</small>' : l.comp.sit === 'nao_bate' ? `<small class="cp-chip cp-nb">📎 ✗ comprovante de ${brl(l.comp.conf)}: não bate</small>` : '<small class="cp-chip cp-conf">📎 falta conferir o comprovante</small>';
function cpSitTxt(c, valor) {
  if (!c) return '';
  if (c.sit === 'confere') return `<p class="cp-sit cp-ok">✓ <b>Confere:</b> o comprovante é de ${brl(c.conf)}, o mesmo valor da despesa${c.conf_por ? ` (${c.conf_por === 'lido do PDF' ? 'lido do PDF' : 'conferido por ' + esc(c.conf_por)})` : ''}.</p>`;
  if (c.sit === 'nao_bate') return `<p class="cp-sit cp-nb">✗ <b>Não bate:</b> o comprovante é de ${brl(c.conf)} e a despesa é de ${brl(valor)} (${c.dif > 0 ? brl(c.dif) + ' a mais no comprovante' : brl(-c.dif) + ' a menos no comprovante'}). Confira o valor da despesa ou o comprovante.</p>`;
  return `<p class="cp-sit cp-conf">${c.lido != null ? `O PDF mostra ${brl(c.lido)}, diferente da despesa (${brl(valor)}). ` : ''}<b>Falta conferir:</b> olhe o comprovante e escreva o valor que está nele.</p>`;
}
function cpCorpo(c, l) {
  const arq = `<input type="file" id="cp-arq" accept="image/*,application/pdf" hidden>`;
  if (!c) return `<div class="cp-linha"><label class="btn btn-ghost cp-anexar">${arq}📎 Anexar o comprovante</label><span class="sub" id="cp-pend">${l ? 'foto ou PDF, até 8 MB' : CP_PENDENTE ? '📎 ' + esc(CP_PENDENTE.name) + ' · vai junto quando lançar' : 'foto ou PDF, até 8 MB · vai junto quando lançar'}</span></div>`;
  const v = c.conf != null ? c.conf : c.lido;
  return `<div class="cp-linha cp-atual"><b>📎 ${esc(c.nome)}</b><small>${cpTam(c.tamanho)} · anexado por ${esc(c.por)} em ${esc(quandoBR(c.em))}</small><button type="button" class="link" id="cp-baixa">Baixar</button><label class="link cp-troca">${arq}Trocar</label></div>
    <div class="cp-linha"><label class="cp-v"><span class="lbl">Valor no comprovante (R$)</span><input class="inp" id="cp-valor" inputmode="decimal" autocomplete="off" value="${v != null ? esc(gValor(v)) : ''}" placeholder="o valor escrito no comprovante"></label><button type="button" class="btn btn-ghost" id="cp-conferir">Conferir</button></div>
    ${cpSitTxt(c, l ? l.valor : 0)}`;
}
const cpBloco = l => `<div class="g-c g-larg cp-box"><span class="lbl">Comprovante <small>(o valor do comprovante é conferido com o valor da despesa)</small></span><div id="cp-corpo">${cpCorpo(l ? l.comp || null : null, l)}</div></div>`;
const cpLer = f => new Promise((ok, erro) => { const r = new FileReader(); r.onload = () => ok(String(r.result).replace(/^data:[^,]*,/, '')); r.onerror = () => erro(new Error('Não consegui ler o arquivo.')); r.readAsDataURL(f); });
async function cpEnviar(lanc, f) {
  if (f.size > 8 * 1024 * 1024) throw new Error('Arquivo grande demais (até 8 MB).');
  return (await api('cp_anexar', { lanc, nome: f.name, arquivo: await cpLer(f) })).comprovante;
}
function cpLigar(box, l) {
  CP_PENDENTE = null;
  const corpo = $('#cp-corpo', box); if (!corpo) return;
  if (l && l.comp === undefined) api('cp_ver', { lanc: l.id }).then(r => { l.comp = r.comprovante; corpo.innerHTML = cpCorpo(l.comp, l); liga(); }).catch(() => { /* fica o anexar */ });
  const redesenha = c => { if (l) { l.comp = c; } corpo.innerHTML = cpCorpo(c, l); liga(); };
  const liga = () => {
    const a = $('#cp-arq', corpo); if (a) a.onchange = async () => { const f = a.files && a.files[0]; if (!f) return;
      if (!l) { CP_PENDENTE = f; const p = $('#cp-pend', corpo); if (p) p.textContent = '📎 ' + f.name + ' · vai junto quando lançar'; return; }
      corpo.classList.add('carregando'); try { const c = await cpEnviar(l.id, f); redesenha(c); aviso(c.sit === 'confere' ? 'Comprovante anexado ✓ o valor confere' : 'Comprovante anexado ✓ agora confira o valor'); gComprovanteMudou(); } catch (e) { aviso(e.message); } corpo.classList.remove('carregando'); };
    const bx = $('#cp-baixa', corpo); if (bx) bx.onclick = () => cpBaixar(bx, l.comp);
    const cf = $('#cp-conferir', corpo), vi = $('#cp-valor', corpo);
    if (cf && vi) { const vai = async () => { if (!vi.value.trim()) { aviso('Escreva o valor que está no comprovante.'); vi.focus(); return; } cf.disabled = true;
        try { const r = await api('cp_conferir', { lanc: l.id, valor: vi.value }); redesenha(r.comprovante); aviso(r.comprovante.sit === 'confere' ? 'Confere ✓' : 'Não bate ✗ confira o valor'); gComprovanteMudou(); } catch (e) { aviso(e.message); cf.disabled = false; } };
      cf.onclick = vai; vi.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); vai(); } }; }
  };
  liga();
}
/* a despesa nova com o comprovante escolhido: vai depois de lançar */
async function cpDepoisDeLancar(lanc) {
  const f = CP_PENDENTE; CP_PENDENTE = null; if (!f || !lanc) return;
  try { const c = await cpEnviar(lanc.id, f); aviso(c.sit === 'confere' ? 'Comprovante anexado ✓ o valor confere' : 'Comprovante anexado ✓ abra a despesa para conferir o valor'); }
  catch (e) { aviso('A despesa foi lançada, mas o comprovante não: ' + e.message); }
}
let gComprovanteMudou = () => { try { if (typeof GS !== 'undefined') { GS.dados = null; GS.pagar = null; } } catch (e) { /* sem a lista */ } };
async function cpBaixar(b, c) {
  if (!c) return; const rot = b.textContent; b.disabled = true; b.textContent = 'Baixando…';
  try { const r = await fetch('api.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Fin': '1' }, body: JSON.stringify({ acao: 'cp_baixar', id: c.id }) });
    if (!r.ok || /json/.test(r.headers.get('Content-Type') || '')) { let j = {}; try { j = await r.json(); } catch (e) { /* sem JSON */ } throw new Error(j.erro || 'Não deu para baixar o comprovante.'); }
    gBaixa(await r.blob(), c.nome || 'comprovante'); }
  catch (e) { aviso(e.message); } finally { b.disabled = false; b.textContent = rot; }
}
