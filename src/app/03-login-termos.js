/* ===== termos de uso, política de privacidade e aceite (LGPD) ===== */
const TERMOS_V = 1; // subir este número quando os textos mudarem: o app pede um novo aceite
const DOC_HTML = { termos:`<section class="doc" id="doc-termos">
<h2>Termos de Uso</h2>
<p class="doc-meta">Versão 1 · em vigor desde 2 de novembro de 2026</p>
<p>Estes termos valem para o app Simplificando (endereço /treino) e para o painel do personal (endereço /personal). Ao marcar “Li e aceito”, você concorda com eles. Se não concordar, não use o app.</p>

<h3>1. O que é o Simplificando</h3>
<p>O Simplificando é um app para registrar treinos, água e cardio. Ele pode ser usado de dois jeitos:</p>
<ul>
<li><b>Com personal:</b> um profissional de Educação Física monta o seu treino no painel do personal e libera o seu acesso com uma senha de 6 números.</li>
<li><b>Sem personal:</b> você cria a sua conta e o treino é gerado automaticamente pelo app (“IA”) ou montado por você mesmo.</li>
</ul>
<p>O responsável pelo Simplificando é Eduardo Matias Marques.</p>

<h3>2. Quem pode usar</h3>
<p>O app é para maiores de 18 anos. Menores só podem usar com autorização e acompanhamento dos pais ou responsáveis.</p>

<h3>3. Saúde e riscos do exercício</h3>
<p>Atividade física envolve riscos, como dores, lesões musculares e articulares e, em casos raros, problemas cardíacos. Antes de começar, procure avaliação médica, principalmente se você:</p>
<ul>
<li>tem ou já teve problema no coração, pressão alta, diabetes ou outra doença crônica;</li>
<li>sente dor no peito, tontura ou falta de ar ao fazer esforço;</li>
<li>tem lesão, dor nas articulações ou na coluna, ou passou por cirurgia recente;</li>
<li>está grávida, voltando de um parto ou parado há muito tempo.</li>
</ul>
<p>Pare o exercício e procure ajuda se sentir dor forte, dor no peito, tontura, falta de ar fora do normal ou mal-estar. Ao usar o app, você declara que está apto a praticar atividade física, ou que tem liberação médica, e que vai informar corretamente as suas dores e limitações.</p>

<h3>4. Treino sem personal (gerado por IA ou montado por você)</h3>
<div class="doc-alert">
<p><b>Se você escolheu treinar sem personal, o seu treino NÃO é montado, revisado nem acompanhado por um personal ou por qualquer profissional de Educação Física.</b> Ele é gerado automaticamente por um sistema de inteligência artificial (regras automáticas do app), a partir das respostas que você dá, ou é montado por você mesmo.</p>
<p>Esse treino tem caráter apenas informativo e orientativo e não substitui avaliação nem acompanhamento profissional. Você é o único responsável por escolher os exercícios, as cargas, a execução e o ritmo, e treina por sua conta e risco.</p>
<p><b>O Simplificando, seu responsável e o app não se responsabilizam por qualquer dano, lesão ou problema de saúde relacionado ao treinamento</b>, incluindo os exercícios sugeridos, as cargas, a execução e as animações, na máxima extensão permitida pela lei brasileira.</p>
<p>Recomendamos treinar com acompanhamento de um profissional de Educação Física registrado no CREF.</p>
</div>

<h3>5. Treino com personal</h3>
<p>Se você entrou com a senha do seu personal, o treino, as metas e as orientações são definidos pelo seu personal, que é o profissional responsável pela prescrição e pelo acompanhamento. O Simplificando é só a ferramenta. Dúvidas sobre o treino, dores ou ajustes devem ser tratadas com o seu personal. Valores, pagamentos e bloqueio do acesso são combinados diretamente entre você e o seu personal.</p>

<h3>6. Animações, dicas e cálculos</h3>
<p>As animações dos exercícios, as dicas de execução, as sugestões de carga, as metas de água e cardio e demais cálculos são ilustrativos e aproximados. Eles não substituem a orientação presencial de um profissional.</p>

<h3>7. Sua conta</h3>
<p>Você é responsável pelas informações que coloca no app e por manter a sua senha em segredo. Não compartilhe a sua senha. Se perceber uso indevido, troque a senha ou fale com o seu personal.</p>

<h3>8. O que não é permitido</h3>
<p>Não é permitido tentar acessar a conta ou os dados de outras pessoas, atrapalhar o funcionamento do app, copiar o app para fins comerciais ou usá-lo para qualquer finalidade ilegal. Contas que fizerem isso podem ser suspensas ou excluídas.</p>

<h3>9. Disponibilidade e mudanças no app</h3>
<p>O app pode passar por manutenção, ficar fora do ar ou mudar funções sem aviso. Fazemos o possível para manter tudo funcionando e seus dados salvos, mas não garantimos funcionamento sem interrupções.</p>

<h3>10. Excluir a conta</h3>
<p>Você pode excluir a sua conta quando quiser, em Perfil › Excluir minha conta. Alunos com personal devem pedir a exclusão ao próprio personal, que pode apagar o cadastro no painel.</p>

<h3>11. Mudanças nestes termos</h3>
<p>Podemos atualizar estes termos. Quando houver mudança importante, o app vai pedir que você leia e aceite a nova versão.</p>

<h3>12. Lei aplicável</h3>
<p>Estes termos seguem as leis do Brasil, incluindo o Código de Defesa do Consumidor e a Lei Geral de Proteção de Dados (Lei 13.709/2018). Fica eleito o foro do domicílio do usuário.</p>
</section>`, privacidade:`<section class="doc" id="doc-privacidade">
<h2>Política de Privacidade</h2>
<p class="doc-meta">Versão 1 · em vigor desde 2 de novembro de 2026</p>
<p>Esta política explica quais dados o Simplificando usa, para quê e como você controla esses dados, conforme a Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018).</p>

<h3>1. Quem cuida dos seus dados</h3>
<p>O controlador dos dados é o Simplificando, sob responsabilidade de Eduardo Matias Marques, que também é o encarregado pelo tratamento de dados. Para alunos com personal, o personal também usa os seus dados para montar e acompanhar o seu treino.</p>

<h3>2. Quais dados usamos</h3>
<ul>
<li><b>Cadastro:</b> nome e e-mail (quem treina sem personal). A senha fica protegida pelo serviço de login do Google Firebase e não é vista por ninguém.</li>
<li><b>Perfil:</b> o boneco escolhido (feminino ou masculino), objetivo e peso.</li>
<li><b>Dados de saúde:</b> dores e limitações informadas, respostas do check-in (sono, energia e dor muscular) e, para alunos com personal, os dados que o personal registra no painel.</li>
<li><b>Treinos:</b> exercícios, séries, cargas, repetições, histórico, cardio e água.</li>
<li><b>Foto de perfil:</b> fica guardada só no seu aparelho e não é enviada para o servidor.</li>
<li><b>Uso do app:</b> data de criação da conta, data do último acesso, versão do app e a data em que você aceitou estes termos.</li>
</ul>

<h3>3. Para que usamos</h3>
<ul>
<li>montar, mostrar e ajustar os seus treinos e as suas metas;</li>
<li>mostrar a sua evolução e salvar o seu histórico na sua conta;</li>
<li>permitir que o seu personal acompanhe você (só para alunos com personal);</li>
<li>gerenciar as contas do app: o administrador vê o nome, o e-mail, o tipo de conta (com ou sem personal), as datas de criação e de último acesso e se os termos foram aceitos. De quem treina sem personal, ele não vê os dados de saúde nem o histórico de treinos, a não ser que a própria pessoa permita o acompanhamento no app (Perfil › Acompanhamento). Com a permissão, ele vê o treino montado, os treinos feitos, cargas, cardio, água, check-in e mensagens, só para acompanhar e ajudar. A permissão pode ser desligada a qualquer momento;</li>
<li>segurança, prevenção de fraudes e suporte.</li>
</ul>
<p>Não vendemos os seus dados e não usamos os seus dados para propaganda.</p>

<h3>4. Bases legais</h3>
<p>Usamos os seus dados para prestar o serviço que você pediu (art. 7º, V, da LGPD) e, no caso dos dados de saúde, com o seu consentimento específico e destacado (art. 11, I), dado ao marcar a caixa de autorização. Você pode revogar esse consentimento a qualquer momento excluindo a sua conta.</p>

<h3>5. Onde os dados ficam</h3>
<p>Os dados da conta ficam no Google Firebase, um serviço de nuvem do Google, que pode guardar informações em servidores fora do Brasil, com as garantias de segurança do próprio provedor. Parte dos dados (como a foto e o histórico de quem treina com personal) fica guardada só no navegador do seu aparelho.</p>

<h3>6. Com quem compartilhamos</h3>
<p>Só com o seu personal (se você tiver um) e com o provedor de nuvem (Google Firebase), que guarda os dados para o app funcionar. Também podemos compartilhar dados quando a lei ou uma ordem judicial exigir.</p>

<h3>7. Por quanto tempo guardamos</h3>
<p>Enquanto a sua conta existir. Quando você exclui a conta, os seus dados de treino e de cadastro são apagados do servidor na hora, e cópias de segurança são eliminadas em até 30 dias, salvo quando a lei exigir guardar algum registro.</p>

<h3>8. Seus direitos</h3>
<p>Pela LGPD, você pode confirmar se tratamos os seus dados, acessar, corrigir, pedir a portabilidade, revogar o consentimento e pedir a exclusão dos seus dados. No app, você corrige os seus dados no Perfil e exclui a conta em Perfil › Excluir minha conta. Para os demais pedidos, fale com o responsável pelos canais oficiais do Simplificando.</p>

<h3>9. Segurança</h3>
<p>Usamos login com senha, conexão protegida e regras que impedem que uma pessoa veja os dados de outra. Nenhum sistema é 100% seguro: se acontecer algum incidente que coloque você em risco, vamos avisar.</p>

<h3>10. Crianças e adolescentes</h3>
<p>O app não é destinado a menores de 18 anos sem autorização e acompanhamento dos responsáveis.</p>

<h3>11. Mudanças nesta política</h3>
<p>Quando esta política mudar de forma importante, o app vai pedir que você leia e aceite a nova versão.</p>
</section>` };
const needAceite = () => !S.aceite || (S.aceite.v||0) < TERMOS_V || !S.aceite.saude || (ACCT && ACCT.mode==='self' && !S.aceite.semPersonal);
const ckOk = (c, self) => !!(c.t && c.s && (!self || c.p));
function aceiteBoxes(c, self, act){
  const box = (k, txt, cls) => `<button type="button" role="checkbox" aria-checked="${!!c[k]}" class="ck${c[k]?' on':''}${cls?' '+cls:''}" data-a="${act}" data-v="${k}"><span class="ck-box" aria-hidden="true">${ic('check')}</span><span>${txt}</span></button>`;
  return `<div class="ck-list">${box('t','Li e aceito os <b>Termos de Uso</b> e a <b>Política de Privacidade</b>.')}
    <div class="ck-links"><button type="button" class="linkbtn" data-a="lg-doc" data-v="termos">Ler os termos de uso</button><button type="button" class="linkbtn" data-a="lg-doc" data-v="privacidade">Ler a política de privacidade</button></div>
    ${box('s','Autorizo o uso dos meus <b>dados de saúde</b> (peso, dores e limitações, sono e cansaço) só para montar e ajustar meus treinos.')}
    ${self ? box('p','Entendo que, <b>treinando sem personal, meu treino não é montado nem acompanhado por um profissional</b>: ele é gerado automaticamente por inteligência artificial ou montado por mim. Treino por minha conta e risco, declaro estar apto(a) para atividade física e sei que o Simplificando e seu responsável não se responsabilizam por danos ou lesões relacionados ao treino.','alert') : ''}</div>`;
}
const aceiteNovo = self => ({ v:TERMOS_V, em:new Date().toISOString(), saude:true, semPersonal:self ? true : undefined });
function vAceite(){
  const self = !!ACCT && ACCT.mode==='self', c = LG.ac;
  return `<div class="lg-in su"><div class="lg-top"><button type="button" class="linkbtn pk-back" data-a="lg-ac-sair">${ic('chevL')}Sair</button></div>
    <p class="eyebrow">Antes de continuar</p><h1 class="lg-h">Termos e privacidade<span class="dot" aria-hidden="true"></span></h1>
    <p class="muted">${S.aceite ? 'Os termos de uso e a política de privacidade foram atualizados.' : 'Para usar o Simplificando, leia e aceite os termos.'} ${self ? 'Você treina sem personal.' : 'Você treina com o seu personal.'}</p>
    ${aceiteBoxes(c, self, 'lg-ck')}
    <button type="button" class="btn primary block lg-go" data-a="lg-ac-ok"${ckOk(c, self)?'':' disabled'}>Aceitar e continuar${ic('chev')}</button></div>`;
}
function vDoc(){
  const k = LG.doc==='privacidade' ? 'privacidade' : 'termos', o = k==='termos' ? 'privacidade' : 'termos';
  return `<div class="lg-in su lg-doc"><div class="lg-top"><button type="button" class="linkbtn pk-back" data-a="lg-doc-back">${ic('chevL')}Voltar</button>
    <button type="button" class="linkbtn" data-a="lg-doc" data-v="${o}">${o==='termos'?'Termos de uso':'Privacidade'}</button></div>${DOC_HTML[k]}
    <button type="button" class="btn primary block" data-a="lg-doc-back">Voltar</button></div>`;
}
function vDocSheet(){
  const k = ui.sheet.d==='privacidade' ? 'privacidade' : 'termos', ac = S.aceite;
  return `<div class="sheet-wrap" data-a="closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Simplificando', 'Termos e privacidade<span class="dot" aria-hidden="true"></span>')}
    ${seg('doc-tab',['termos','privacidade'],k,['Termos de uso','Privacidade'],'Escolher documento')}
    ${ac ? `<p class="foot0">${ic('check')} Você aceitou a versão ${ac.v} em ${dm(ac.em.slice(0,10))}.</p>` : ''}
    ${DOC_HTML[k]}
    <button type="button" class="btn primary block" data-a="close">${ic('check')}Fechar</button></div></div>`;
}
/* excluir conta: quem treina sem personal apaga tudo; aluno com personal pede ao personal e pode apagar o que está no aparelho */
function vExcluir(){
  const sh = ui.sheet, self = ACCT.mode==='self', online = self && !!ACCT.uid && !!FB, armed = ui.arm==='acc-del';
  const body = !self ? `<p class="muted">Sua conta foi criada pelo seu personal. Para excluir a conta e os dados que ficam com ele, peça ao seu personal: ele apaga o seu cadastro no painel.</p>
      <p class="muted">Seu histórico de treinos, água e cardio fica guardado neste aparelho. Esse você mesmo pode apagar aqui.</p>`
    : online ? `<p class="muted">Isso apaga para sempre a sua conta, os seus treinos, o seu histórico e as suas metas, aqui e na nuvem. Não dá para desfazer.</p>
      <div class="field"><label class="flabel" for="ex-pw">Digite a sua senha para confirmar</label><div class="inp-wrap"><input class="inp" id="ex-pw" type="password" autocomplete="current-password" maxlength="64" placeholder="Sua senha" value="${esc(sh.pw||'')}"></div></div>`
    : `<p class="muted">Isso apaga para sempre os seus treinos, o seu histórico e as suas metas guardados neste aparelho. Não dá para desfazer.</p>`;
  const lbl = !self ? 'Apagar os dados deste aparelho' : 'Excluir minha conta';
  return `<div class="sheet-wrap" data-a="closebg"><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="sh-t" tabindex="-1">${sheetTop('Sua conta', (self?'Excluir conta':'Seus dados')+'<span class="dot" aria-hidden="true"></span>')}
    ${body}${sh.err ? `<p class="lg-err" role="alert">${esc(sh.err)}</p>` : ''}
    <button type="button" class="btn block ${armed?'armed':'ghost danger-t'}${sh.busy?' busy':''}" data-a="acc-del"${sh.busy?' disabled':''}>${ic('trash')}${armed ? 'Toque de novo para confirmar' : lbl}</button>
    <button type="button" class="btn primary block" data-a="close">Cancelar</button></div></div>`;
}
async function accDelete(){
  const sh = ui.sheet, self = ACCT.mode==='self', uid = ACCT.uid;
  if(self && uid && FB){
    const pw = sh.pw || (document.getElementById('ex-pw')||{}).value || '';
    if(!pw){ sh.err = 'Digite a sua senha para confirmar.'; renderSheet(); return; }
    sh.busy = true; sh.err = ''; renderSheet();
    const r = await FB.deleteSelf(pw);
    if(r.err){ sh.busy = false; sh.err = r.err; renderSheet(); return; }
  } else if(self && uid){
    const a = laccts(); Object.keys(a).forEach(k => { if(a[k].uid===uid) delete a[k]; }); try { localStorage.setItem(LAKEY, JSON.stringify(a)); } catch(e){ /* sem armazenamento */ }
  }
  afterDelete(self ? 'Sua conta foi excluída.' : 'Os dados deste aparelho foram apagados.');
}
function afterDelete(msg){
  const uk = UKEY, fk = FKEY+':'+uidOf();
  ACCT = null; // daqui em diante nada mais é salvo
  try { localStorage.removeItem(uk); localStorage.removeItem(fk); } catch(e){ /* sem armazenamento */ }
  if(FB) FB.logout();
  ui.rest = null; renderRest(); ui.sheet = null; $sheet.innerHTML = ''; writeSess(null); $app.innerHTML = ''; uiReset();
  const dk = document.getElementById('rdock'); if(dk) dk.remove(); document.body.classList.remove('has-dock');
  LG.view = 'login'; LG.code = ''; LG.err = ''; renderLogin(); toast(msg);
}
/* acompanhamento: quem treina sem personal pode deixar o Simplificando ver o treino, a evolução e a água no painel */
const acompOn = () => !!(S && S.acomp && S.acomp.on);
const acompPode = () => !!(FB && ACCT && ACCT.mode==='self' && ACCT.uid);
function acompSet(on){ S.acomp = { on:!!on, em:Date.now() }; save(); perfilSync(true); }
/* depois de criar a conta (sem personal): mostrar como colocar o app na tela do celular (uma vez) */
let INST_EV = null, instT = 0;
