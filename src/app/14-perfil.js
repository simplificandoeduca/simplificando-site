/* ---------- Notificações (perfil): tudo o que o app avisa, cada um com uma chave; e o compartilhar com o Simplificando ---------- */
const swN = (k,on,l) => `<label class="switch"><input type="checkbox" data-sw="${k}"${on?' checked':''}><span class="knob"></span><span class="sr">${l}</span></label>`;
const cadaTxt = m => +m===30 ? '30 min' : +m===60 ? '1 hora' : '1h30';
function ntfBanner(){
  if(pushLigado()) return `<div class="nt-ban">${ic('bell')}<span><b>Avisos no celular ligados.</b> Chegam mesmo com o app fechado.</span></div>`;
  if(pushPode()) return `<div class="nt-ban off">${ic('bell')}<span><b>Avisos no celular desligados.</b> Sem isso, os avisos só aparecem com o app aberto.</span></div><button type="button" class="btn block rm-push" data-a="ntf-push">${ic('bell')}Ligar avisos no celular</button>`;
  if(IOS && !STANDALONE) return `<div class="nt-ban off">${ic('bell')}<span><b>No iPhone, coloque o app na Tela de Início</b> e abra pelo ícone para receber avisos com o app fechado.</span></div>`;
  return `<div class="nt-ban off">${ic('bell')}<span>Este navegador não manda avisos com o app fechado. Eles aparecem com o app aberto.</span></div>`;
}
function ntfBloco(pers){
  const L = [
    ['avdia', PREF.avDia!==false && !noPlan(), '#7E57C9', ic('dumbbell'), 'Lembrete do treino', lbSubTxt()],
    ['remind', S.remind.on, '#1A73E8', ic('drop'), 'Lembrete de água', S.remind.on ? 'A cada '+cadaTxt(S.remind.every)+', das '+hmTxt(RINI())+' às '+hmTxt(RFIM()) : 'Desligado'],
    ['avdesc', PREF.avDesc!==false, '#E8650A', ic('timer'), 'Fim do descanso', 'Quando você sai do app no descanso'],
    ['avtreino', PREF.avTreino!==false, '#E5484D', ic('flag'), 'Treino esquecido aberto', 'Um aviso com 3 horas de treino'],
  ];
  const row = ([k,on,c,icn,t,sub]) => `<div class="nt-row"><span class="nt-ic" style="--c:${c}">${icn}</span><div><b>${t}</b><span class="s">${sub}</span></div>${swN(k,on,t)}</div>`;
  return `<div class="prefs nta"><div class="pref-h"><b>Notificações</b><span>O que o app pode te avisar. Toque para ligar ou desligar.</span></div>${ntfBanner()}
    ${L.map(row).join('')}
    ${!pers && acompPode() ? `<p class="nt-sub">Privacidade</p>${row(['acomp', acompOn(), '#1E9E4A', CM_USERS, 'Compartilhar meus treinos com o Simplificando', 'Treinos, cargas, cardio, água e check-in'])}` : ''}</div>`;
}
/* ---------- Seu perfil: 4 blocos (plano, avisos, visual, sugestão) e o resto numa lista curta; cada bloco abre a sua tela ---------- */
const PF_PAGS = { plano:1, avisos:1, visual:1, sug:1, conta:1 };
function pfVolta(t){ return `<div class="sheet-top" data-drag><div class="grabber" aria-hidden="true"></div><div class="sheet-h"><button type="button" class="pf-back" data-a="pf-go" data-v="">${ic('chevL')}Perfil</button><button type="button" class="btn round" data-a="close" aria-label="Fechar">${ic('x')}</button></div></div><h2 class="pf-h" id="sh-t">${t}<span class="dot" aria-hidden="true"></span></h2>`; }
function vProfile(){
  const pg = ui.sheet && PF_PAGS[ui.sheet.p] ? ui.sheet.p : '';
  const body = pg==='plano' ? pfPlano() : pg==='avisos' ? pfAvisos() : pg==='visual' ? pfVisual() : pg==='sug' ? pfSug() : pg==='conta' ? pfConta() : pfHome();
  return `<div class="sheet-wrap" data-a="closebg"><div class="sheet pf${pg?' pf-sub':''}" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${body}</div></div>`;
}
function pfHome(){
  const pers = isPersonal(), nome = pers ? ACCT.nome : (S.nome || 'Você'), n = S.history.length;
  const late = pers && ACCT.pagoAte && ACCT.pagoAte < today();
  const pay = pers && ACCT.pagoAte ? `<span class="pf-chip ${late?'bad':'ok'}">${ic(late?'bell':'check')}${late ? 'Mensalidade em aberto desde '+dm(ACCT.pagoAte) : 'Mensalidade em dia até '+dm(ACCT.pagoAte)}</span>` : '';
  const nOn = [PREF.avDia!==false && !noPlan(), S.remind.on, PREF.avDesc!==false, PREF.avTreino!==false].filter(Boolean).length;
  const dias = noPlan() ? 'Nenhum treino montado' : diasLivres() ? 'qualquer dia' : tDays().length+'x por semana';
  const obj = ED[S.ed] ? ED[S.ed].nome : '';
  const tl = TLAYS.find(o => o.id===tLay()).n, cl = LAYS.find(o => o.id===layOf()).n, cor = (ACC[PREF.accent] || ACC.vermelho)[0];
  const tile = (pg, cls, icn, t, sub) => `<button type="button" class="pf-tile ${cls}" data-a="pf-go" data-v="${pg}">${ic(icn)}<b>${t}</b><span>${sub}</span></button>`;
  const row = (pg, icn, cls, t) => `<button type="button" class="pf-row" data-a="pf-go" data-v="${pg}"><span class="pf-ic ${cls}">${ic(icn)}</span><b>${t}</b>${ic('chev','pf-ch')}</button>`;
  return `${sheetTop('Seu perfil', 'Perfil<span class="dot" aria-hidden="true"></span>')}
    <div class="pf-id"><span class="avatar" aria-hidden="true">${avatarIn()}</span><div><b>${esc(nome)}</b><span class="pf-st">${pers?'Com seu personal':'Treinando sem personal'} · ${n} treino${n===1?'':'s'} salvo${n===1?'':'s'}</span>
      <div class="pf-chips">${pay}<input type="file" id="prof-foto" accept="image/*" class="sr-file"><label for="prof-foto" class="pf-chip">${ic('camera')}${FOTO?'Trocar foto':'Adicionar foto'}</label>${FOTO?`<button type="button" class="pf-chip" data-a="foto-del">${ui.arm==='foto-del'?'Toque de novo para remover':'Remover foto'}</button>`:''}</div></div></div>
    <div class="pf-tiles">
      ${tile('plano', 't-red', 'dumbbell', pers ? 'Meu plano' : 'Meus treinos', [obj, dias].filter(Boolean).join(' · '))}
      ${tile('avisos', 't-blue', 'bell', 'Avisos', nOn+' de 4 ligados')}
      ${tile('visual', 't-lime', 'layers', 'Visual', tl+' · '+cl+' · '+cor)}
      ${tile('sug', 't-orange', 'msg', 'Sugestão', 'Fale com a gente')}
    </div>
    <div class="pf-list">${row('conta', 'lock', '', 'Termos de uso e privacidade')}${row('conta', 'trash', 'red', pers ? 'Apagar registros ou excluir dados' : 'Apagar registros ou excluir a conta')}</div>
    <button type="button" class="pf-list pf-out${ui.arm==='logout'?' armed':''}" data-a="logout">${ui.arm==='logout'?'Toque de novo para sair':'Sair'}</button>
    <div class="pf-ver"><span class="ver-pill">${ic('check')}App na versão ${APPV}</span><button type="button" class="pf-link ver-btn${NOVAV?' nova':''}" data-a="ver-checar">${ic('refresh')}${NOVAV ? 'Atualizar para a '+esc(NOVAV) : 'Procurar atualização'}</button><span hidden></span></div>`;
}
function pfPlano(){
  const pers = isPersonal();
  if(pers){
    const dias = diasLivres() ? 'Qualquer dia · '+SEMANA_TREINOS+'x' : tDays().map(i => TDAYS_L[i]).join(', ');
    return pfVolta('Meu plano')+`<div class="prefs"><div class="pref-h"><b>Seu plano</b><span>${esc(ACCT.plano||'')}</span></div>
    <div class="plan-ro"><div><b>${ED[S.ed]?ED[S.ed].nome:'—'}</b><span>objetivo</span></div><div><b>${dias}</b><span>dias de treino</span></div><div><b>${fmtL(S.waterGoal)}</b><span>água por dia</span></div><div><b>${S.cardioGoal} min</b><span>cardio na semana · ${CARDIO.zona}</span></div></div>
    <p class="lock-note">${ic('lock')}Definido pelo seu personal.</p></div>`;
  }
  return pfVolta('Meus treinos')+`<div class="field"><label class="flabel" for="prof-nome">Seu nome</label><div class="inp-wrap"><input class="inp" id="prof-nome" autocomplete="given-name" placeholder="Como você quer ser chamado" value="${esc(S.nome||'')}" data-prof="nome"></div></div>
    <div class="field"><span class="flabel">Seu foco</span>${seg('ed',SELF_ED,S.ed,SELF_ED.map(k => ED[k].nome),'Seu foco')}</div>
    <div class="prefs"><div class="pref-h"><b>Seus treinos</b><span>${noPlan() ? 'Nenhum treino montado ainda.' : LETTERS.length+' treino'+(LETTERS.length===1?'':'s')+' · '+esc(ACCT.plano||'')+' · '+(tDays().length===7 && ownPlan() ? 'qualquer dia' : joinPt(tDays().map(i => TDAYS_L[i])))}</span></div>
    <button type="button" class="btn block pf-mt" data-a="mt-open-sheet">${ic('edit')}Editar meus treinos</button><button type="button" class="btn block pf-mt" data-a="ia-open">${ic('spark')}Refazer com a IA</button></div>
    <div class="prefs"><div class="pref-h"><b>Suas metas</b><span>Água por dia e cardio por semana.</span></div>
    <div class="stepper"><button type="button" class="btn round" data-a="goal" data-d="-250" aria-label="Diminuir meta de água">${ic('minus')}</button><output>${fmtL(S.waterGoal)}<small> água</small></output><button type="button" class="btn round" data-a="goal" data-d="250" aria-label="Aumentar meta de água">${ic('plus')}</button></div>
    <div class="stepper"><button type="button" class="btn round" data-a="cgoal" data-d="-30" aria-label="Diminuir meta de cardio">${ic('minus')}</button><output>${S.cardioGoal}<small> min cardio</small></output><button type="button" class="btn round" data-a="cgoal" data-d="30" aria-label="Aumentar meta de cardio">${ic('plus')}</button></div></div>`;
}
function pfAvisos(){ return pfVolta('Avisos')+ntfBloco(isPersonal()); }
function pfVisual(){
  const pers = isPersonal();
  return pfVolta('Visual')+`<div class="prefs"><div class="pref-h"><b>Cor do app</b><span>Destaques, faixa e botões. O branco neutro vira preto no modo claro. Água fica sempre azul e cardio sempre verde.</span></div>
    <div class="dots" role="group" aria-label="Cor do app">${Object.keys(ACC).map(k => `<button type="button" class="cdot${ACC[k][1]==='n'?' neutral':''}${PREF.accent===k?' on':''}" style="--c:${ACC[k][1]==='n'?'#FFFFFF':ACC[k][1]}" data-a="accent" data-v="${k}" aria-label="${ACC[k][0]}" aria-pressed="${PREF.accent===k}">${PREF.accent===k?ic('check'):''}</button>`).join('')}</div>
    <div class="sw-row"><div><b>Ícones em Liquid Glass</b><span>Barra de abas, botões redondos e selos ficam de vidro.</span></div><label class="switch"><input type="checkbox" data-sw="glass"${PREF.glass?' checked':''}><span class="knob"></span><span class="sr">Ícones em Liquid Glass</span></label></div>
    <div class="glass-demo" aria-hidden="true"><span class="num">${ic('dumbbell')}</span><span class="num">${ic('drop')}</span><span class="num">${ic('pulse')}</span><span class="num">${ic('trophy')}</span></div></div>
    <div class="prefs"><div class="pref-h"><b id="ct-t">Treino</b><span>Agora: ${TLAYS.find(o => o.id===tLay()).n}. Toque em outro para trocar.</span></div>
    <div class="lay-list t4" role="radiogroup" aria-labelledby="ct-t">${TLAYS.map(o => layCard(o, o.id===tLay(), 'set-tlay', o.id===tLay()?'Atual':'', tlThumb)).join('')}</div></div>
    <div class="prefs"><div class="pref-h"><b id="cc-t">Cardio</b><span>Agora: ${LAYS.find(o => o.id===layOf()).n}. Toque em outro para trocar.</span></div>
    <div class="lay-list" role="radiogroup" aria-labelledby="cc-t">${LAYS.map(o => layCard(o, o.id===layOf(), 'set-clay', o.id===layOf()?'Atual':'')).join('')}</div></div>
    ${pers ? `<div class="prefs"><div class="pref-h"><b>Seu boneco</b><span>${sexOf()==='M'?'Masculino':'Feminino'}</span></div><p class="lock-note">${ic('lock')}Definido no seu cadastro.</p></div>`
      : `<div class="prefs"><div class="pref-h"><b id="bn-t">Seu boneco</b><span>Quem aparece correndo, pedalando e subindo escada.</span></div>
    <div class="iseg" role="group" aria-labelledby="bn-t">${[['F','Feminino'],['M','Masculino']].map(([v,l]) => `<button type="button" class="${sexOf()===v?'on':''}" aria-pressed="${sexOf()===v}" data-a="boneco" data-v="${v}">${l}</button>`).join('')}</div></div>`}`;
}
function pfSug(){
  return pfVolta('Sugestão')+`<div class="prefs sug-box"><div class="pref-h"><b>Sugestões para o app</b><span>Tem uma ideia, sentiu falta de algo ou algo não funcionou? Escreve aqui: vai direto para a equipe do Simplificando.</span></div>
    <label class="sr" for="sug-txt">Sua sugestão</label><textarea id="sug-txt" class="sug-t" rows="4" maxlength="1000" placeholder="Ex.: queria ver o gráfico do meu peso no app">${esc(ui.sugD||'')}</textarea>
    <button type="button" class="btn block" data-a="sug-send">${ic('msg')}Enviar sugestão</button></div>`;
}
function pfConta(){
  const pers = isPersonal();
  return pfVolta('Conta')+`<div class="prefs"><div class="pref-h"><b>Sua conta</b><span>${S.aceite ? 'Termos aceitos em '+dm(diaLocal(S.aceite.em))+'.' : 'Termos ainda não aceitos.'}</span></div>
    <button type="button" class="btn block pf-mt" data-a="doc-open" data-v="termos">${ic('lock')}Termos de uso e privacidade</button></div>
    <div class="prefs"><div class="pref-h"><b>Seus dados</b><span>Apagar os registros deixa o app como novo neste celular. Excluir ${pers?'os dados':'a conta'} apaga tudo de vez.</span></div>
    <button type="button" class="btn block ${ui.arm==='wipe'?'armed':'ghost danger'}" data-a="wipe">${ui.arm==='wipe'?'Toque de novo para apagar tudo':'Apagar meus registros'}</button>
    <button type="button" class="btn block pf-mt danger-t" data-a="acc-open">${ic('trash')}${pers ? 'Excluir meus dados' : 'Excluir minha conta'}</button></div>`;
}
