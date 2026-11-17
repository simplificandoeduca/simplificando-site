window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); INST_EV = e; if(ui.sheet && ui.sheet.k==='inst') renderSheet(); });
window.addEventListener('appinstalled', () => { INST_EV = null; if(S && S.inst){ S.inst = 'feito'; save(); } if(ui.sheet && (ui.sheet.k==='inst' || ui.sheet.k==='baixar' || ui.sheet.k==='site')){ closeSheet(); toast('App instalado. Abra pelo ícone na tela do celular.'); } });
const ANDROID = /Android/i.test(navigator.userAgent), iosSafari = () => IOS && !/CriOS|FxiOS|EdgiOS|OPiOS|GSA\//.test(navigator.userAgent);
function instAsk(){
  clearInterval(instT); if(!S || S.inst!=='novo' || S.example) return;
  if(STANDALONE || !(IOS || ANDROID)){ S.inst = 'feito'; save(); return; }
  let n = 0;
  instT = setInterval(() => { // espera as outras perguntas do começo (layout e acompanhamento) saírem da tela
    if(++n > 60 || !S || S.inst!=='novo'){ clearInterval(instT); return; }
    const livre = ACCT && !ui.sheet && !document.getElementById('login') && !document.getElementById('pick') && !document.querySelector('.wel');
    if(livre){ clearInterval(instT); SITE_FEITO = true; ui.sheet = {k:'baixar', st:'ini'}; renderSheet(true); }
  }, 1500);
}
const instPasso = (n, t, extra) => `<li><span class="in-n">${n}</span><span>${t}</span>${extra||''}</li>`;
function instCorpo(){
  let corpo;
  if(ANDROID && INST_EV) corpo = `<button type="button" class="btn primary block in-go" data-a="inst-go">${ic('dl')}Instalar o app</button><p class="foot0">Toque em Instalar e confirme. O ícone do Simplificando aparece na tela do celular.</p>`;
  else if(ANDROID) corpo = `<ol class="in-steps">${instPasso(1, 'Toque nos <b>três pontinhos ⋮</b>, no canto de cima do Chrome.')}${instPasso(2, 'Toque em <b>Instalar app</b> (ou <b>Adicionar à tela inicial</b>).')}${instPasso(3, 'Confirme em <b>Instalar</b>. O ícone aparece na tela do celular.')}</ol>`;
  else corpo = (iosSafari() ? '' : '<p class="in-av">'+ic('flag')+'<span>Abra este link no <b>Safari</b> para conseguir colocar o app na tela de início.</span></p>')+
    `<ol class="in-steps">${instPasso(1, 'Toque em <b>Compartilhar</b>: o quadrado com a seta para cima, na barra de baixo do Safari.', '<span class="in-ic">'+ic('share')+'</span>')}${instPasso(2, 'Role a lista e toque em <b>Adicionar à Tela de Início</b>.', '<span class="in-ic">'+ic('plus')+'</span>')}${instPasso(3, 'Toque em <b>Adicionar</b>, no canto de cima. Depois abra o Simplificando pelo ícone.')}</ol>`;
  return corpo;
}
const instMobile = () => !STANDALONE && (IOS || ANDROID);
function vBaixar(){
  const s2 = ui.sheet, st = s2.st || 'ini', lg = `<span class="cm-logo">${logo()}</span>`;
  const wrap = body => `<div class="sheet-wrap cm-w" data-a="bx-x"><div class="sheet cm" role="dialog" aria-modal="true" aria-labelledby="cm-t" tabindex="-1">${body}</div></div>`;
  if(st==='quase') return wrap(`<span class="cm-ok">${ic('check')}</span><h2 id="cm-t">Quase lá<span class="dot">!</span></h2>
    <p class="cm-sub">Agora é só tocar em <b>Instalar</b> na janelinha que apareceu. O ícone do Simplificando vai para a sua tela.</p>
    <span class="cm-bar"><i></i></span><button type="button" class="btn block cm-b2" data-a="bx-ok">Pronto, já instalei</button>`);
  if(st==='menu') return wrap(`<span class="cm-ok">${ic('check')}</span><h2 id="cm-t">Quase lá<span class="dot">!</span></h2>
    <p class="cm-sub">No Chrome, toque nos <b>três pontinhos ⋮</b> lá em cima e depois em <b>Instalar app</b> (ou <b>Adicionar à tela inicial</b>).</p>
    <div class="cm-chrome" aria-hidden="true"><span class="u">simplificandoeduca.com</span><span class="d">⋮</span></div>
    <button type="button" class="btn block cm-b2" data-a="bx-ok">Pronto, já instalei</button>`);
  if(IOS) return wrap(`${lg}<h2 id="cm-t">Coloque nosso app na sua tela<span class="dot">.</span></h2>
    ${iosSafari() ? '' : '<p class="cm-sub" style="margin-top:6px">Abra este link no <b>Safari</b> para conseguir.</p>'}
    <ol class="cm-st"><li><span class="n">1</span><span class="t">Toque em <b>Compartilhar</b> lá embaixo</span><span class="k">${ic('share')}</span></li>
    <li><span class="n">2</span><span class="t">Toque em <b>Adicionar à Tela de Início</b></span><span class="k">${ic('plus')}</span></li>
    <li><span class="n">3</span><span class="t">Toque em <b>Adicionar</b>. Pronto!</span></li></ol>
    <button type="button" class="btn block cm-b2" data-a="bx-ok">Pronto, já coloquei</button><button type="button" class="btn ghost block cm-g" data-a="bx-x">Agora não</button>`);
  return wrap(`${lg}<h2 id="cm-t">Baixe nosso app<span class="dot">.</span></h2><p class="cm-sub">É grátis e fica na tela do seu celular.</p>
    <button type="button" class="btn primary block cm-b" data-a="bx-go">${ic('dl')}Baixar o app</button><button type="button" class="btn ghost block cm-g" data-a="bx-x">Agora não</button>`);
}
function vInst(){
  const top = sheetTop('Último passo', 'Coloque o app na tela do celular<span class="dot" aria-hidden="true"></span>');
  const pq = '<ul class="in-pq"><li>'+ic('check')+'Abre em tela cheia, como um aplicativo</li><li>'+ic('check')+'Funciona até sem internet na academia</li><li>'+ic('check')+'Recebe o lembrete de água'+(IOS ? ' (no iPhone, só pelo ícone)' : '')+'</li></ul>';
  const corpo = instCorpo();
  return `<div class="sheet-wrap" data-a="inst-x"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${top.replace('data-a="close"','data-a="inst-x"')}${pq}${corpo}
    <button type="button" class="btn ghost block" data-a="inst-ok">${ANDROID && INST_EV ? 'Agora não' : 'Pronto, já coloquei'}</button></div></div>`;
}
let siteT = 0, SITE_FEITO = false;
function siteAsk(){
  clearInterval(siteT); if(SITE_FEITO || !instMobile() || !S || S.example) return;
  let n = 0;
  siteT = setInterval(() => {
    if(++n > 80 || SITE_FEITO || !S){ clearInterval(siteT); return; }
    if(S.inst==='novo' || S.session) return; // a conta recém-criada já recebe o "Último passo"; no meio do treino não atrapalha
    const livre = ACCT && !ui.sheet && !document.getElementById('login') && !document.getElementById('pick') && !document.querySelector('.wel') && (S.acomp || !acompPode() || isPersonal());
    if(livre){ clearInterval(siteT); SITE_FEITO = true; ui.sheet = {k:'baixar', st:'ini'}; renderSheet(true); }
  }, 1500);
}
function vSite(){
  const hs = `<div class="in-hs"><div class="in-grid"><i></i><i></i><i></i><span>${logo()}</span></div><p><b>Não precisa de App Store nem Play Store.</b> Fica na tela do celular, abre em tela cheia e funciona sem internet.</p></div>`;
  return `<div class="sheet-wrap" data-a="site-x"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Você está no site', 'Tenha o Simplificando como app<span class="dot" aria-hidden="true"></span>').replace('data-a="close"','data-a="site-x"')}
    ${hs}${instCorpo()}<button type="button" class="btn ghost block" data-a="site-x">Continuar no site</button></div></div>`;
}
/* avaliação do app: a partir de 14/11, a cada 15 dias ao abrir; estrelas por área (modelo 2: símbolo branco no quadrado colorido) */
const AVAL_INI = '2026-11-14', AVAL_DIAS = 15;
const AVAL_AREAS = [
  ['treino','Treino','#E5484D','<rect x="2.5" y="9" width="3" height="6" rx="1.2"/><rect x="5" y="6.5" width="3.4" height="11" rx="1.5"/><rect x="15.6" y="6.5" width="3.4" height="11" rx="1.5"/><rect x="18.5" y="9" width="3" height="6" rx="1.2"/><rect x="8" y="10.8" width="8" height="2.4" rx="1"/>'],
  ['cardio','Cardio','#2E9E3E','<path d="M12 20.6S3.4 15.4 3.4 9.3a4.4 4.4 0 0 1 8.6-1.3 4.4 4.4 0 0 1 8.6 1.3c0 6.1-8.6 11.3-8.6 11.3z"/><path class="k" d="M6.6 12.6h2.9l1.5-2.5 2 4 1.4-1.5h3"/>'],
  ['agua','Água','#1E73E0','<path d="M12 3s6.4 6.8 6.4 11.4a6.4 6.4 0 0 1-12.8 0C5.6 9.8 12 3 12 3z"/><path class="k" d="M9.3 14.7a2.8 2.8 0 0 0 2.5 2.5"/>'],
  ['facil','Fácil de usar','#7E57C9','<circle cx="12" cy="12" r="9"/><path class="k" d="M7.8 12.3l2.8 2.8 5.6-5.8"/>'],
  ['visual','Visual','#E08A00','<path d="M10.5 2.5l2 5.4 5.4 2-5.4 2-2 5.4-2-5.4-5.4-2 5.4-2z"/><path d="M18 13.5l1 2.6 2.6 1-2.6 1-1 2.6-1-2.6-2.6-1 2.6-1z"/><circle cx="5.5" cy="18.5" r="1.7"/>']
];
const avIc = (svg, cor) => `<span class="av-ic" style="--c:${cor}"><svg viewBox="0 0 24 24" aria-hidden="true">${svg}</svg></span>`;
let avalT = 0, AVAL_FEITO = false;
function avalUsoIni(){ // primeiro dia em que a pessoa usou o app (treino, cardio ou água)
  const D = [].concat((S.history||[]).map(h => h.date), (S.cardio||[]).map(c => c.date), Object.keys(S.water||{}).filter(k => +S.water[k]>0)).filter(Boolean).sort();
  return D[0] || '';
}
function avalPode(){ const t = today(); if(!S || S.example || t < AVAL_INI) return false;
  if(!S.aval){ const ini = avalUsoIni(); if(!ini || diffDays(t, ini) < 7){ S.aval = { ult:t, novo:1 }; save(); return false; } } // conta nova: a 1ª avaliação vem depois de 15 dias de uso
  const g = S.aval || {}; if(g.ign===t) return false; // ignorou hoje: pede de novo amanhã (até avaliar)
  return !g.ult || diffDays(t, g.ult) >= AVAL_DIAS; }
function avalAsk(){
  clearInterval(avalT); if(AVAL_FEITO || !avalPode()) return;
  let n = 0;
  avalT = setInterval(() => {
    if(++n > 120 || AVAL_FEITO || !avalPode()){ clearInterval(avalT); return; }
    if(S.session || S.inst==='novo' || SITE_FEITO) return; // no meio do treino, logo depois de criar a conta ou na mesma abertura do "Baixe nosso app", não
    const livre = ACCT && !ui.sheet && !document.getElementById('login') && !document.getElementById('pick') && !document.querySelector('.wel') && !document.querySelector('.stp') && (S.acomp || !acompPode() || isPersonal());
    if(livre){ clearInterval(avalT); AVAL_FEITO = true; ui.sheet = {k:'aval', notas:{}}; renderSheet(true); }
  }, 2000);
}
function vAval(){
  const n = ui.sheet.notas || {};
  return `<div class="sheet-wrap" data-a="av-x"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Avaliação rápida', 'Dá uma nota pra gente?').replace('data-a="close"','data-a="av-x"')}
    <p class="foot0" style="margin-top:-6px">Toque nas estrelas. Leva 10 segundos.</p>
    <div class="av-rows">${AVAL_AREAS.map(([k,l,c,svg]) => `<div class="av-r"><b>${avIc(svg,c)}${l}</b><span class="av-st" role="radiogroup" aria-label="Nota para ${l}">${[1,2,3,4,5].map(v => `<button type="button" class="${(n[k]||0)>=v?'on':''}" data-a="av-n" data-k="${k}" data-v="${v}" role="radio" aria-checked="${n[k]===v}" aria-label="${v} estrela${v>1?'s':''}">★</button>`).join('')}</span></div>`).join('')}</div>
    <label class="flabel" for="av-txt" style="margin-top:12px">Algo pra melhorar? <span style="font-weight:500;color:var(--muted)">(opcional)</span></label>
    <textarea id="av-txt" class="inp av-txt" maxlength="1000" placeholder="Escreva aqui…"></textarea>
    <button type="button" class="btn primary block" data-a="av-go"${Object.keys(n).length ? '' : ' disabled'}>Enviar notas</button>
    <button type="button" class="btn ghost block" data-a="av-x">Agora não</button></div></div>`;
}
function avalFim(enviou){ const g = S.aval || (S.aval = {}); if(enviou){ g.ult = today(); g.env = today(); delete g.ign; } else g.ign = today(); save(); } // "Agora não": volta amanhã; avaliou: só daqui a 15 dias
function avalEnviar(b){
  const s2 = ui.sheet; if(!s2 || s2.k!=='aval') return;
  const notas = Object.assign({}, s2.notas || {}); if(!Object.keys(notas).length){ toast('Toque nas estrelas de pelo menos uma área.'); return; }
  const t = document.getElementById('av-txt'), texto = t ? t.value.trim().slice(0,1000) : '';
  avalFim(true); closeSheet();
  if(!FB){ toast('Valeu pela avaliação! 💪'); return; }
  FB.evento('avaliacoes', Object.assign(quem(), { notas, texto, em:Date.now(), ver:APPV })).then(ok => {
    if(ok===true) toast('Valeu pela avaliação! 💪');
    else { const g = S.aval || {}; delete g.ult; delete g.env; save(); toast('Não deu para enviar agora. Vamos pedir de novo na próxima vez.'); if(ok==='permission-denied') errSend('Regras do Firebase desatualizadas: a avaliação do app foi bloqueada. Cole o REGRAS-FIRESTORE.txt novo no Firebase.', 'avaliacoes', 0, 0, ''); }
  });
}
function acompAsk(){ if(!acompPode() || S.acomp || S.example || noPlan() || (S.inst==='novo' && instMobile())) return; setTimeout(() => { if(ACCT && !ui.sheet && !S.acomp && !document.getElementById('login') && !document.getElementById('pick') && !document.querySelector('.wel')){ ui.sheet = {k:'acomp'}; renderSheet(true); } }, 1600); }
const CM_USERS = '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M18 14.4c1.8.8 3 2.6 3 4.6"/></svg>';
function vAcomp(){
  return `<div class="sheet-wrap cm-w" data-a="acomp-no"><div class="sheet cm" role="dialog" aria-modal="true" aria-labelledby="cm-t" tabindex="-1">
    <span class="cm-ic">${CM_USERS}</span><h2 id="cm-t">Quer compartilhar seus treinos com o Simplificando<span class="dot">?</span></h2>
    <p class="cm-sub">Assim a gente acompanha sua evolução e te ajuda quando precisar.</p>
    <button type="button" class="btn primary block cm-b" data-a="acomp-sim">${ic('check')}Quero compartilhar</button>
    <button type="button" class="btn block cm-b2" data-a="acomp-no">Não quero</button></div></div>`;
}
/* resumo da conta para o painel do administrador (nome, tipo, último acesso, aceite e se permitiu o acompanhamento) */
let PERFIL_AT = 0;
function perfilSync(force){
  if(!FB || !ACCT || (!force && Date.now()-PERFIL_AT < 30*60e3)) return;
  PERFIL_AT = Date.now();
  const ac = S.aceite ? { v:S.aceite.v, em:S.aceite.em } : null;
  if(ACCT.mode==='self' && ACCT.uid) FB.perfil({ tipo:'autonomo', nome:S.nome||'', email:(S.conta&&S.conta.email)||'', sexo:ACCT.sexo, objetivo:S.ed, plano:(S.conta&&S.conta.plan)||'', treinos:LETTERS.length, ultimoAcesso:Date.now(), aceite:ac, acomp:acompOn(), ver:APPV });
  else if(ACCT.mode==='personal') FB.perfil({ tipo:'personal', aid:ACCT.code, nome:ACCT.nome||'', sexo:ACCT.sexo, ultimoAcesso:Date.now(), aceite:ac, ver:APPV });
}

