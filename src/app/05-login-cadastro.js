/* ===== treinar sem personal: conta, perfil e treino (pela IA ou montado pelo aluno) ===== */
/* modo de teste (sem Firebase): a conta fica neste aparelho */
const LAKEY = 'simplificando-contas';
const lhash = s => { let h = 2166136261; for(let i=0; i<s.length; i++){ h ^= s.charCodeAt(i); h = Math.imul(h, 16777619) >>> 0; } return h.toString(36); };
function laccts(){ try { return JSON.parse(localStorage.getItem(LAKEY)||'{}') || {}; } catch(e){ return {}; } }
function localSignUp(email, pw){
  const a = laccts(), k = email.toLowerCase();
  if(a[k]) return { err:'Esse e-mail já tem conta neste aparelho. Toque em “Entrar”, logo acima.' };
  const uid = 'l'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
  a[k] = { uid, h:lhash(k+'|'+pw) };
  try { localStorage.setItem(LAKEY, JSON.stringify(a)); } catch(e){ return { err:'Não deu para salvar neste navegador.' }; }
  return { uid };
}
function localSignIn(email, pw){ const k = email.toLowerCase(), x = laccts()[k]; return x && x.h===lhash(k+'|'+pw) ? { uid:x.uid } : { err:'E-mail ou senha não conferem.' }; }
const autoKey = uid => KEY+':auto:'+uid;
function autoLocal(uid){ try { const v = JSON.parse(localStorage.getItem(autoKey(uid))||'null'); return v && v.v===2 ? v : null; } catch(e){ return null; } }
function legacySelf(){ try { const v = JSON.parse(localStorage.getItem(KEY+':self')||'null'); return v && v.conta ? v : null; } catch(e){ return null; } }
const mailOk = v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v||'').trim());
const cleanName = s => String(s||'').replace(/[<>&"]/g,'').replace(/\s+/g,' ').trim().slice(0,40);
const joinPt = a => a.length<2 ? (a[0]||'') : a.slice(0,-1).join(', ')+' e '+a[a.length-1];
const focoTxt = gs => joinPt(gs.map((g,i) => i ? GNOME[g].toLowerCase() : GNOME[g]));
const normTx = s => String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');

/* catálogo de exercícios do aluno sem personal (os mesmos da biblioteca do painel e mais opções para casa)
   eq: a academia · h casa com halteres · c sem equipamento   t: c composto · i isolado   lim: j joelho · o ombro · l lombar */
const GRUPOS = [['quad','Quadríceps'],['glut','Glúteo'],['post','Posterior'],['pant','Panturrilha'],['peito','Peito'],['costas','Costas'],['ombro','Ombro'],['bic','Bíceps'],['tri','Tríceps'],['abd','Abdômen']];
const GNOME = Object.fromEntries(GRUPOS);
const CAT = [
  ['agach','Agachamento livre','quad','a','c','jl',['Pés na largura dos ombros, pontas levemente para fora.','Desça levando o quadril para trás até a coxa passar da linha do joelho.','Joelhos seguem a direção dos pés. Suba soltando o ar.'],'Tirar o calcanhar do chão ou deixar o joelho cair para dentro.'],
  ['leg','Leg press 45°','quad','a','c','',['Lombar e quadril encostados no banco o tempo todo.','Desça até o joelho chegar perto de 90°.','Empurre com o pé inteiro, sem travar o joelho no alto.'],'Descer tanto que o quadril sai do banco.'],
  ['goblet','Agachamento goblet','quad','ah','c','j',['Halter ou kettlebell junto ao peito, cotovelos para baixo.','Desça entre as pernas com o tronco alto.','Suba empurrando o chão com o pé inteiro.'],'Inclinar o tronco à frente e perder o peso do peito.'],
  ['agachpc','Agachamento sem peso','quad','c','c','j',['Pés na largura dos ombros, mãos nos ombros ou atrás da cabeça.','Desça levando o quadril para trás, como quem senta numa cadeira.','Suba empurrando o chão com o pé inteiro.'],'Deixar os joelhos caírem para dentro.'],
  ['extensora','Cadeira extensora','quad','a','i','j',['Joelho alinhado ao eixo da máquina.','Estenda até quase travar e segure 1 segundo.','Volte devagar, sem deixar o peso bater.'],'Dar impulso com o quadril.'],
  ['pelvica','Elevação pélvica','glut','a','c','',['Escápulas apoiadas no banco, barra na dobra do quadril.','Suba até o tronco ficar alinhado com as coxas.','Segure 2 segundos no alto contraindo o glúteo.'],'Arquear a lombar em vez de subir com o quadril.'],
  ['pelvicah','Elevação pélvica com halter','glut','h','c','',['Costas apoiadas no sofá ou num banco firme, halter na dobra do quadril.','Suba até o tronco ficar alinhado com as coxas.','Segure 2 segundos no alto contraindo o glúteo.'],'Arquear a lombar em vez de subir com o quadril.',{anim:'pelvica'}],
  ['afundo','Afundo com halteres','glut','ah','c','j',['Passo longo, tronco levemente inclinado à frente.','Desça até o joelho de trás quase tocar o chão.','Suba empurrando com o calcanhar da perna da frente.'],'Passo curto demais, que joga a carga no joelho.',{g2:'quad'}],
  ['bulgaroinc','Agachamento búlgaro (tronco inclinado)','glut','ah','c','j',["Peito do pé de trás no banco e um passo longo à frente.", "Incline o tronco à frente com as costas retas e leve o quadril para trás na descida, até a coxa da frente ficar paralela ao chão.", "Suba empurrando pelo calcanhar da frente, sentindo o glúteo, sem empurrar com a perna de trás."],"Arredondar as costas para inclinar ou deixar o joelho da frente cair para dentro.",{anim:'bulgaroinc', g2:'quad', noIa:1}],
  ['bulgaro','Agachamento búlgaro','glut','ah','c','j',['Pé de trás apoiado num banco ou cadeira firme.','Desça reto até a coxa da frente ficar paralela ao chão.','Suba pela perna da frente, sem empurrar com a de trás.'],'Ficar perto demais do banco.',{g2:'quad'}],
  ['afundopc','Afundo sem peso','glut','c','c','j',['Passo longo, braços soltos ao lado do corpo.','Desça até o joelho de trás quase tocar o chão.','Suba empurrando com o calcanhar da perna da frente.'],'Passo curto demais, que joga a carga no joelho.',{g2:'quad'}],
  ['ponte','Elevação pélvica no solo (ponte)','glut','hc','i','',['Deitado de barriga para cima, joelhos dobrados e pés no chão perto do quadril.','Suba o quadril até alinhar joelhos, quadril e ombros, contraindo o glúteo.','Segure 2 segundos no alto e desça devagar, sem encostar o quadril no chão.'],'Empurrar com a lombar em vez do glúteo.',{anim:'pelvicasolo', g2:'post'}],
  ['abdutorainc','Cadeira abdutora inclinada','glut','a','i','',["Sente na ponta do banco e incline o tronco à frente, com as costas retas e as mãos nos pegadores.", "Abra as pernas de forma controlada e segure 1 segundo aberto.", "Volte devagar, sem deixar as placas baterem e sem levantar o tronco."],'Arredondar as costas ou voltar o tronco para trás durante a série.',{anim:'abdutora', noIa:1}],
  ['abdutora','Cadeira abdutora','glut','a','i','',['Costas apoiadas e quadril no fundo do banco.','Abra as pernas de forma controlada.','Volte devagar, sem deixar as placas baterem.'],'Balançar o tronco para abrir.'],
  ['stiff','Stiff com halteres','post','ah','c','l',['Joelhos levemente dobrados e fixos.','Leve o quadril para trás descendo os halteres rente às pernas.','Suba contraindo glúteo e posterior, sem arredondar as costas.'],'Arredondar a lombar para descer mais.',{g2:'glut'}],
  ['flexora','Mesa flexora','post','a','i','',['Quadril colado no banco durante todo o movimento.','Flexione trazendo o calcanhar em direção ao glúteo.','Volte devagar até quase esticar a perna.'],'Tirar o quadril do banco para ajudar.'],
  ['panturrilha','Panturrilha em pé','pant','ahc','i','',['Ponta dos pés na borda do degrau.','Desça o calcanhar até alongar bem.','Suba o máximo e segure 2 segundos.'],'Fazer meio movimento, rápido demais.',{r:'15'}],
  ['supino','Supino reto com barra','peito','a','c','o',['Escápulas juntas e para baixo, pés firmes no chão.','Desça a barra até a linha do mamilo.','Empurre numa linha levemente diagonal, em direção aos olhos.'],'Tirar o quadril do banco para empurrar mais.'],
  ['inclinado','Supino inclinado com halteres','peito','a','c','',['Banco entre 30° e 45°.','Cotovelos a cerca de 45° do tronco.','Suba aproximando os halteres, sem bater um no outro.'],'Abrir demais os cotovelos.'],
  ['flexao','Flexão de braço','peito','ahc','c','',['Mãos um pouco mais abertas que os ombros.','Corpo reto da cabeça ao calcanhar, abdômen firme.','Desça até o peito quase tocar o chão e empurre.'],'Deixar o quadril cair.',{r:'8–12'}],
  ['supchao','Supino no chão com halteres','peito','h','c','',['Deitado no chão, joelhos dobrados.','Desça os halteres até o cotovelo encostar de leve no chão.','Empurre para cima aproximando os halteres.'],'Abrir os cotovelos a 90° do tronco.',{anim:'none'}],
  ['voador','Voador (peck deck)','peito','a','i','o',['Costas apoiadas, cotovelos na altura dos ombros.','Feche os braços contraindo o peito.','Abra devagar até sentir alongar.'],'Abrir além da linha do ombro.'],
  ['pulldownmaq',"Pulldown na máquina",'costas','a','c','',["Sente com as coxas presas no apoio e segure as pegadas com os braços esticados acima da cabeça.", "Puxe as pegadas para baixo levando os cotovelos em direção às costelas, peito para cima.", "Volte devagar até esticar os braços, sem deixar o peso bater."],"Inclinar o tronco para trás e puxar com o embalo do corpo.",{anim:'pulldownmaq', noIa:1}],
  ['puxada','Puxada frente','costas','a','c','',['Pegada um pouco mais aberta que os ombros.','Puxe a barra até o alto do peito, peito para cima.','Suba controlando até esticar os braços.'],'Puxar atrás da nuca ou balançar o tronco.'],
  ['baixa','Remada baixa','costas','a','c','',['Joelhos levemente dobrados, tronco reto.','Puxe o triângulo até o abdômen.','Volte deixando as escápulas abrirem.'],'Levar o tronco para trás a cada repetição.'],
  ['remada','Remada curvada','costas','a','c','l',['Tronco inclinado a 45°, coluna neutra.','Puxe a barra até o umbigo.','Junte as escápulas no alto.'],'Arredondar a lombar.'],
  ['serrote','Remada unilateral (serrote)','costas','ah','c','',['Joelho e mão do mesmo lado apoiados no banco ou cadeira.','Puxe o halter em direção ao quadril.','Desça até esticar o braço, sem girar o tronco.'],'Girar o tronco para subir o peso.'],
  ['remadainv','Remada invertida na mesa','costas','c','c','',['Deite embaixo de uma mesa firme e segure a borda.','Corpo reto, puxe o peito em direção à mesa.','Desça devagar até esticar os braços.'],'Deixar o quadril cair.',{anim:'none'}],
  ['superman','Superman','costas','hc','i','',['Deitado de barriga para baixo, braços à frente.','Tire braços e pernas do chão ao mesmo tempo.','Segure 2 segundos e desça devagar.'],'Jogar a cabeça para trás.',{anim:'none', g2:'post', r:'12–15'}],
  ['desenv','Desenvolvimento com halteres','ombro','ah','c','o',['Sentado, costas apoiadas e abdômen firme.','Halteres na altura das orelhas.','Empurre para cima sem arquear a lombar.'],'Jogar o tronco para trás.'],
  ['lateral','Elevação lateral','ombro','ah','i','',['Cotovelos levemente flexionados.','Suba até a linha do ombro, mãos na altura dos cotovelos.','Desça devagar, sem balançar.'],'Subir com impulso do tronco.'],
  ['lateralpc','Elevação lateral com garrafas','ombro','c','i','',['Uma garrafa de água cheia em cada mão.','Suba até a linha do ombro com os cotovelos levemente dobrados.','Desça devagar, sem balançar.'],'Subir com impulso do tronco.',{anim:'lateral', r:'15–20'}],
  ['pike','Flexão pike (ombro)','ombro','c','c','o',['Mãos e pés no chão, quadril alto formando um V.','Desça a cabeça em direção ao chão entre as mãos.','Empurre de volta estendendo os braços.'],'Baixar o quadril e virar uma flexão comum.',{anim:'none', r:'6–10'}],
  ['facepull','Face pull','ombro','a','i','',['Polia na altura do rosto.','Puxe a corda em direção à testa, abrindo as mãos.','Cotovelos altos o tempo todo.'],'Usar carga alta e perder a postura.',{r:'12–15'}],
  ['rosca','Rosca direta','bic','a','i','',['Cotovelos fixos ao lado do corpo.','Suba sem balançar o tronco.','Desça até quase esticar o braço.'],'Usar o quadril para levantar a barra.'],
  ['martelo','Rosca martelo','bic','ah','i','',['Palmas viradas uma para a outra.','Suba alternando, cotovelo parado.','Desça devagar até esticar.'],'Balançar os halteres com o corpo.'],
  ['roscapc','Rosca com mochila','bic','c','i','',['Mochila com peso segura pelas alças, ou garrafas cheias.','Suba sem balançar o tronco, cotovelos parados.','Desça devagar até esticar o braço.'],'Usar o corpo para subir.',{anim:'none', r:'12–15'}],
  ['triceps','Tríceps na polia com corda','tri','a','i','',['Cotovelos colados ao tronco.','Estenda abrindo a corda no final.','Volte até 90° sem mexer o ombro.'],'Deixar os cotovelos subirem.'],
  ['frances','Tríceps francês com halter','tri','ah','i','o',['Sentado, halter seguro com as duas mãos acima da cabeça.','Desça o halter atrás da cabeça mantendo os cotovelos apontados para cima.','Estenda os braços sem abrir os cotovelos.'],'Abrir os cotovelos para os lados.'],
  ['banco','Tríceps no banco (mergulho)','tri','hc','i','o',['Mãos na borda de um banco ou cadeira firme, quadril perto dele.','Desça dobrando os cotovelos até cerca de 90°.','Empurre de volta sem tirar os ombros do lugar.'],'Descer demais e forçar o ombro.'],
  ['flexfech','Flexão fechada','tri','hc','c','',['Mãos mais juntas, logo abaixo do peito.','Cotovelos rentes ao corpo na descida.','Corpo reto da cabeça ao calcanhar.'],'Abrir os cotovelos.',{anim:'flexao', g2:'peito', r:'6–12'}],
  ['prancha','Prancha','abd','ahc','i','',['Antebraços sob os ombros.','Contraia abdômen e glúteo, corpo em linha reta.','Respire curto sem soltar a barriga.'],'Deixar o quadril subir ou cair.',{r:'30–45 s'}],
  ['deadbug','Dead bug','abd','ahc','i','',['Deitado, lombar colada no chão.','Estenda braço e perna opostos devagar.','Volte e troque o lado sem tirar a lombar do chão.'],'Arquear a lombar ao estender a perna.',{r:'10 cada lado'}],
  ['abdominal','Abdominal no chão','abd','ahc','i','',['Deitado, joelhos dobrados e pés no chão.','Suba só até tirar as escápulas do chão.','Desça devagar, sem puxar o pescoço.'],'Puxar a cabeça com as mãos.',{r:'15–20'}],
  // variações em máquina, polia e barra, mais abdominais
  ["agachsmith", "Agachamento no Smith", "quad", "a", "c", "j", ["Barra apoiada no alto das costas, pés um pouco à frente do corpo.", "Desça controlando até a coxa ficar paralela ao chão.", "Suba empurrando o chão, sem travar o joelho no alto."], "Deixar os pés para trás, jogando o joelho à frente."],
  ["sumo", "Agachamento sumô com halter", "quad", "ah", "c", "j", ["Pés bem afastados, pontas para fora, halter junto ao peito.", "Desça reto, com os joelhos seguindo a direção dos pés.", "Suba contraindo glúteo e a parte de dentro da coxa."], "Deixar os joelhos caírem para dentro.", {anim:"goblet", g2:"glut"}],
  ["hack", "Agachamento no hack", "quad", "a", "c", "j", ["Costas inteiras no encosto, pés na largura dos ombros.", "Desça até o joelho chegar perto de 90°.", "Suba empurrando com o pé inteiro."], "Tirar o quadril do encosto."],
  ["legh", "Leg press horizontal", "quad", "a", "c", "", ["Costas inteiras no encosto, pés no meio da plataforma.", "Dobre os joelhos até chegarem perto de 90°.", "Empurre com o pé inteiro, sem travar o joelho."], "Tirar o quadril do banco no fim da descida.", {anim:"none"}],
  ["cadflexora", "Cadeira flexora", "post", "a", "i", "", ["Joelho alinhado ao eixo da máquina, apoio firme sobre as coxas.", "Puxe o rolo para baixo e para trás até dobrar bem o joelho.", "Volte devagar até quase esticar a perna."], "Tirar o quadril do banco."],
  ["stiffbarra", "Stiff com barra", "post", "a", "c", "l", ["Barra rente às pernas, joelhos levemente dobrados.", "Leve o quadril para trás até sentir alongar atrás da coxa.", "Suba contraindo glúteo e posterior."], "Arredondar as costas para descer mais.", {g2:"glut"}],
  ["pelvicamaq", "Elevação pélvica na máquina", "glut", "a", "c", "", ["Costas no encosto, apoio da máquina na dobra do quadril.", "Suba até o tronco alinhar com as coxas.", "Segure 2 segundos no alto e desça devagar."], "Arquear a lombar no alto."],
  ["coicepoliainc", "Coice na polia (variação)", "glut", "a", "i", "", ["Tornozeleira na polia baixa, tronco inclinado à frente e mãos firmes na máquina.", "Comece com o joelho subindo à frente, perto da altura do quadril.", "Chute a perna para trás e para cima até esticar, sem girar o quadril, e volte devagar."], "Arquear a lombar ou girar o quadril para levar a perna mais alto.", {anim:"coicepoliainc", r:"12–15 cada perna", noIa:1}],
  ["coicepolia", "Glúteo na polia (coice)", "glut", "a", "i", "", ["Tornozeleira presa na polia baixa, mãos apoiadas na máquina.", "Leve a perna para trás e para cima, sem girar o quadril.", "Volte devagar, sem encostar o pé no chão."], "Arquear a lombar para subir mais a perna.", {r:"12–15"}],
  ["adutora", "Cadeira adutora", "glut", "a", "i", "", ["Costas apoiadas, almofadas na parte de dentro dos joelhos.", "Feche as pernas de forma controlada.", "Volte devagar até sentir alongar."], "Deixar as placas baterem na volta.", {alvo:"Adutores", r:"12–15"}],
  ["pantsentado", "Panturrilha sentado", "pant", "a", "i", "", ["Apoio sobre as coxas, perto dos joelhos, ponta dos pés na plataforma.", "Desça o calcanhar até alongar bem.", "Suba o máximo e segure 1 segundo."], "Fazer meio movimento.", {anim:"none", r:"15–20"}],
  ["pantleg", "Panturrilha no leg press", "pant", "a", "i", "", ["Ponta dos pés na borda da plataforma, joelhos quase esticados.", "Empurre a plataforma só com a ponta dos pés.", "Volte devagar até alongar."], "Dobrar os joelhos para ajudar.", {r:"15–20"}],
  ["supinohalter", "Supino reto com halteres", "peito", "ah", "c", "", ["Deitado no banco, halteres na linha do peito.", "Desça até os cotovelos ficarem um pouco abaixo do banco.", "Empurre aproximando os halteres no alto."], "Bater um halter no outro e perder o controle."],
  ["supmaq", "Supino reto na máquina", "peito", "a", "c", "", ["Ajuste o banco para a pegada ficar na linha do meio do peito.", "Costas e cabeça encostadas, empurre até quase esticar os braços.", "Volte devagar até sentir alongar o peito."], "Tirar as costas do encosto para empurrar."],
  ["supmaqinc", "Supino inclinado na máquina", "peito", "a", "c", "", ["Pegada na linha da parte alta do peito.", "Empurre para cima e para a frente, sem tirar as costas do encosto.", "Volte controlando, sem deixar o peso bater."], "Subir os ombros em direção às orelhas."],
  ["inclbarra", "Supino inclinado com barra", "peito", "a", "c", "o", ["Banco entre 30° e 45°, escápulas juntas.", "Desça a barra até a parte alta do peito.", "Empurre até quase esticar os braços."], "Tirar o quadril do banco."],
  ["crossalta", "Crossover na polia alta", "peito", "a", "i", "", ["Polias no alto, um passo à frente, tronco levemente inclinado.", "Traga as mãos para baixo e para a frente até se encontrarem na altura do quadril.", "Volte devagar até sentir alongar o peito."], "Dobrar e esticar os cotovelos, virando um empurrar.", {r:"12–15"}],
  ["crossmedia", "Crossover na polia média", "peito", "a", "i", "", ["Polias na altura do peito, um passo à frente.", "Feche os braços como num abraço até as mãos se encontrarem na frente do peito.", "Volte devagar com os cotovelos levemente dobrados."], "Deixar os ombros irem para a frente no fim.", {r:"12–15"}],
  ["crossbaixa", "Crossover na polia baixa", "peito", "a", "i", "", ["Polias embaixo, palmas para cima, um passo à frente.", "Suba as mãos em arco até a altura do queixo.", "Desça devagar, sem soltar os ombros."], "Usar o impulso das costas para subir.", {r:"12–15"}],
  ["crucifixo", "Crucifixo com halteres", "peito", "ah", "i", "o", ["Deitado, halteres acima do peito, cotovelos levemente dobrados.", "Abra os braços em arco até sentir alongar o peito.", "Feche o arco de volta, como num abraço."], "Descer demais e forçar o ombro."],
  ["remadamaq", "Remada na máquina", "costas", "a", "c", "", ["Peito apoiado no encosto, pés firmes.", "Puxe as pegadas trazendo os cotovelos para trás.", "Junte as escápulas e volte devagar."], "Tirar o peito do apoio."],
  ["puxadatri", "Puxada com triângulo", "costas", "a", "c", "", ["Pegada fechada no triângulo, peito para cima.", "Puxe até o triângulo chegar perto do peito.", "Suba controlando até esticar os braços."], "Jogar o tronco muito para trás.", {anim:"puxada"}],
  ["puxadasup", "Puxada supinada", "costas", "a", "c", "", ["Palmas viradas para você, mãos na largura dos ombros.", "Puxe a barra até o alto do peito.", "Suba devagar até esticar os braços."], "Puxar só com os braços e esquecer as costas.", {anim:"puxada"}],
  ["remadacav", "Remada cavalinho", "costas", "a", "c", "l", ["Tronco inclinado, coluna neutra, joelhos levemente dobrados.", "Puxe a pegada em direção ao abdômen.", "Desça controlando até esticar os braços."], "Arredondar a lombar."],
  ["pulldownreto", "Pulldown com braços estendidos", "costas", "a", "i", "", ["Polia alta, braços quase esticados, tronco levemente inclinado.", "Leve a barra ou a corda até as coxas usando as costas.", "Volte devagar até os braços passarem da linha da cabeça."], "Dobrar os cotovelos e virar um tríceps.", {r:"12–15"}],
  ["barrafixa", "Barra fixa", "costas", "a", "c", "", ["Pegada um pouco mais aberta que os ombros, corpo pendurado.", "Suba até o queixo passar da barra.", "Desça devagar até esticar os braços."], "Balançar o corpo para subir.", {r:"6–10", noIa:1}],
  ["desenvmaq", "Desenvolvimento na máquina", "ombro", "a", "c", "o", ["Costas inteiras no encosto, pegada na altura das orelhas.", "Empurre para cima sem travar os cotovelos.", "Desça devagar até a linha do queixo."], "Arquear a lombar para empurrar."],
  ["lateralpolia", "Elevação lateral unilateral na polia", "ombro", "a", "i", "", ["De lado para a polia baixa, cabo na mão de fora passando na frente do corpo; a outra mão segura a máquina.", "Suba o braço pela lateral até a linha do ombro, cotovelo levemente dobrado.", "Desça devagar até a mão voltar na frente do quadril. Termine as repetições e troque o lado."], "Inclinar o tronco para longe da polia para subir.", {r:"12–15 cada lado"}],
  ["frontal", "Elevação frontal com halteres", "ombro", "ah", "i", "", ["Halteres na frente das coxas, cotovelos levemente dobrados.", "Suba até a altura dos ombros.", "Desça devagar, sem balançar o tronco."], "Subir acima da cabeça ou dar impulso."],
  ["frontalpolia", "Elevação frontal na polia", "ombro", "a", "i", "", ["De costas para a polia baixa, cabo entre as pernas.", "Suba a pegada até a altura dos ombros.", "Desça controlando, sem jogar o tronco para trás."], "Jogar o tronco para trás para subir."],
  ["voadorinv", "Crucifixo inverso na máquina", "ombro", "a", "i", "", ["Peito no encosto, braços na altura dos ombros.", "Abra os braços para trás juntando as escápulas.", "Volte devagar, sem deixar o peso bater."], "Usar carga demais e encolher os ombros.", {r:"12–15"}],
  ["encolh", "Encolhimento com halteres", "ombro", "ah", "i", "", ["Halteres ao lado do corpo, braços esticados.", "Suba os ombros em direção às orelhas.", "Segure 1 segundo e desça devagar."], "Girar os ombros ou dobrar os cotovelos.", {alvo:"Trapézio", r:"12–15"}],
  ["roscahalter", "Rosca alternada com halteres", "bic", "ah", "i", "", ["Halteres ao lado do corpo, palmas para a frente.", "Suba um braço de cada vez, cotovelo parado.", "Desça devagar até esticar."], "Balançar o tronco."],
  ["roscapolia", "Rosca na polia", "bic", "a", "i", "", ["Polia baixa, barra reta ou corda, cotovelos colados no corpo.", "Suba até perto dos ombros.", "Desça devagar até esticar os braços."], "Levar os cotovelos para a frente."],
  ["scott", "Rosca Scott", "bic", "a", "i", "", ["Braços apoiados no banco Scott, axilas encostadas no apoio.", "Suba a barra sem tirar os braços do apoio.", "Desça devagar até quase esticar."], "Soltar o peso de uma vez na descida."],
  ["concentrada", "Rosca concentrada", "bic", "ah", "i", "", ["Sentado, cotovelo apoiado na parte de dentro da coxa.", "Suba o halter em direção ao ombro.", "Desça devagar até esticar."], "Mexer o ombro para ajudar.", {anim:"none"}],
  ["tricepsbarra", "Tríceps na polia com barra", "tri", "a", "i", "", ["Cotovelos colados ao tronco, barra reta ou V.", "Estenda os braços até o fim.", "Volte até 90° sem mexer os ombros."], "Inclinar o tronco sobre a barra."],
  ["testa", "Tríceps testa", "tri", "ah", "i", "o", ["Deitado, barra acima do peito, braços esticados.", "Dobre só os cotovelos, levando a barra em direção à testa.", "Estenda de volta sem abrir os cotovelos."], "Mexer os braços junto, virando um supino."],
  ["coicetri", "Tríceps coice com halter", "tri", "ah", "i", "", ["Tronco inclinado com apoio no banco, braço colado ao corpo.", "Estenda o cotovelo levando o halter para trás.", "Volte até 90° sem mexer o braço."], "Balançar o braço inteiro."],
  ["paralelas", "Mergulho nas paralelas", "tri", "a", "c", "o", ["Mãos nas barras, corpo levemente inclinado.", "Desça até os cotovelos ficarem perto de 90°.", "Empurre de volta até esticar os braços."], "Descer demais e forçar os ombros.", {anim:"mergulho", r:"6–12", g2:"peito", noIa:1}],
  ["infra", "Abdominal infra (elevação de pernas)", "abd", "ahc", "i", "l", ["Deitado, mãos ao lado do corpo ou sob o quadril.", "Suba as pernas esticadas até apontarem para o teto.", "Desça devagar, sem encostar os pés no chão e sem tirar a lombar do chão."], "Arquear a lombar na descida.", {anim:"legraise", r:"10–15"}],
  ["abdcarga", "Abdominal com anilha", "abd", "ah", "i", "", ["Deitado, joelhos dobrados e pés no chão, anilha deitada no centro do peito, segura com os braços cruzados.", "Solte o ar e enrole o tronco: suba até as escápulas e o meio das costas saírem do chão, perto de 45°.", "Segure 1 segundo em cima e desça devagar, vértebra por vértebra, sem soltar a cabeça no chão."], "Puxar o pescoço ou dar impulso; a anilha fica parada no peito e quem sobe é o tronco.", {anim:"crunchanilha", r:"12–15"}],
  ["abdmaq", "Abdominal na máquina", "abd", "a", "i", "", ["Ajuste o banco e segure as pegadas junto ao peito.", "Enrole o tronco para a frente contraindo o abdômen.", "Volte devagar, sem deixar o peso bater."], "Puxar com os braços em vez de usar o abdômen.", {r:"12–15"}],
  ["abdpolia", "Abdominal na polia (ajoelhado)", "abd", "a", "i", "", ["Ajoelhado de frente para a polia alta, corda ao lado da cabeça.", "Enrole o tronco levando os cotovelos em direção às coxas.", "Volte devagar, sem sentar nos calcanhares."], "Mexer o quadril em vez de enrolar o tronco.", {r:"12–15"}],
  ["infrabarra", "Elevação de pernas na barra fixa", "abd", "a", "i", "l", ["Pendurado na barra, ombros firmes, sem balançar.", "Suba as pernas até a altura do quadril.", "Desça devagar, controlando o balanço."], "Balançar o corpo para subir as pernas.", {r:"8–12"}],
  ["infracad", "Elevação de joelhos no apoio", "abd", "a", "i", "", ["Antebraços apoiados, costas no encosto.", "Suba os joelhos até a altura do quadril.", "Desça devagar, sem balançar."], "Tirar as costas do encosto.", {r:"10–15"}],
  ["pranchalat", "Prancha lateral", "abd", "ahc", "i", "", ["Antebraço embaixo do ombro, pés um sobre o outro.", "Suba o quadril até o corpo ficar em linha reta.", "Segure sem deixar o quadril cair."], "Deixar o quadril cair ou ir para trás.", {anim:"none", r:"20–40 s cada lado"}],
  ["bicicleta", "Abdominal bicicleta", "abd", "ahc", "i", "", ["Deitado, mãos atrás da cabeça sem puxar o pescoço.", "Leve o cotovelo em direção ao joelho oposto, esticando a outra perna.", "Alterne os lados devagar."], "Puxar a cabeça com as mãos.", {anim:"none", r:"10 cada lado"}],
  ["remador", "Abdominal remador", "abd", "ahc", "i", "l", ["Deitado com braços acima da cabeça e pernas esticadas.", "Suba o tronco e traga os joelhos ao peito ao mesmo tempo.", "Volte devagar até deitar de novo."], "Jogar os braços para ganhar impulso.", {anim:"none", r:"10–15"}],
  ["russo", "Giro russo (russian twist)", "abd", "ahc", "i", "l", ["Sentado, tronco inclinado para trás, pés no chão ou no ar.", "Gire o tronco levando as mãos de um lado para o outro.", "Mantenha a coluna reta o tempo todo."], "Girar só os braços, sem girar o tronco.", {anim:"none", r:"10 cada lado"}],
  ["escalador", "Escalador (mountain climber)", "abd", "ahc", "i", "", ["Na posição de flexão, mãos embaixo dos ombros.", "Traga um joelho em direção ao peito e troque rápido.", "Quadril baixo, sem subir o bumbum."], "Subir o quadril e perder a linha do corpo.", {anim:"none", r:"20–30 s"}],
  ["declinado", "Abdominal no banco inclinado", "abd", "a", "i", "l", ["Deite no banco inclinado com os pés presos no apoio e as mãos cruzadas no peito.", "Suba o tronco enrolando a coluna até ficar de frente para os joelhos.", "Desça devagar até quase encostar as costas no banco."], "Puxar o pescoço com as mãos ou subir com impulso.", {anim:"abdinclinado", r:"10–15"}],
  ["abdutorapolia", "Abdutora reta na polia", "glut", "a", "i", "", ["Tornozeleira na perna de fora, de lado para a polia baixa; a mão de dentro segura a máquina.", "Tronco reto, leve a perna para o lado até sentir o glúteo trabalhar.", "Volte devagar, sem deixar o peso bater e sem girar o pé para fora."], "Inclinar o tronco para o lado para subir mais a perna.", {r:"12–15 cada lado"}],
  ["abdutorapoliainc", "Abdutora inclinada na polia", "glut", "a", "i", "", ["De lado para a polia baixa, segure a máquina com o braço esticado e incline o corpo em direção a ela.", "Com a tornozeleira na perna de fora, leve a perna para o lado e um pouco para trás.", "Volte devagar, mantendo o corpo inclinado e o quadril parado."], "Dobrar o tronco ou girar o quadril para trás.", {r:"12–15 cada lado"}],
  ["vsquat", "Agachamento no V-squat", "quad", "a", "c", "j", ["Ombros embaixo das almofadas, costas inteiras no encosto, pés na largura dos ombros.", "Desça até a coxa ficar paralela à plataforma, joelhos na direção dos pés.", "Suba empurrando com o pé inteiro, sem travar o joelho no alto."], "Tirar o quadril do encosto ou deixar o calcanhar sair da plataforma.", {g2:"glut"}],
  ["francespolia", "Tríceps francês na polia", "tri", "a", "i", "o", ["De costas para a polia baixa, corda presa embaixo; segure as pontas atrás da cabeça.", "Cotovelos apontados para cima, perto da cabeça: estenda os braços para a frente e para cima até o fim.", "Volte devagar, deixando a corda ir atrás da cabeça sem abrir os cotovelos."], "Abrir os cotovelos para os lados ou mexer os ombros.", {r:"12–15"}],
  ["tricepsunil", "Tríceps pulley unilateral", "tri", "a", "i", "", ["De frente para a polia alta, pegador numa mão só; a outra mão na cintura.", "Cotovelo colado ao corpo, estenda o braço até o fim.", "Volte até 90° sem mexer o ombro. Termine as repetições e troque o braço."], "Deixar o cotovelo abrir ou subir junto.", {r:"12–15 cada lado"}],
  ["rodinha", "Abdominal com rodinha", "abd", "ah", "i", "l", ["Ajoelhado, mãos na rodinha embaixo dos ombros.", "Role para a frente mantendo o abdômen firme.", "Volte puxando com o abdômen, sem arquear a lombar."], "Ir longe demais e arquear a lombar.", {anim:"none", r:"8–12"}],
  ["pallof", "Pallof press (antirrotação na polia)", "abd", "a", "i", "", ["De lado para a polia, pegada junto ao peito.", "Estenda os braços à frente sem deixar o tronco girar.", "Segure 2 segundos e volte."], "Deixar o tronco girar em direção à polia.", {anim:"none", r:"10 cada lado"}],
  ["lenhador", "Lenhador na polia", "abd", "a", "i", "", ["De lado para a polia alta, braços esticados.", "Puxe em diagonal até o quadril do outro lado, girando o tronco.", "Volte devagar, controlando o giro."], "Puxar só com os braços.", {anim:"none", r:"10–12 cada lado"}],
  // ranking Jeff Nippard: os melhores de cada grupo que faltavam no catálogo
  ["crossent", "Crucifixo sentado na polia", "peito", "a", "i", "", ["Sentado no banco entre as polias, cabos na altura do peito.", "Feche os braços em arco até as mãos se encontrarem na frente do peito.", "Volte devagar até sentir alongar o peito, sem perder a postura."], "Dobrar e esticar os cotovelos, virando um supino.", {r:"10–15"}],
  ["mergulhopeito", "Mergulho nas paralelas (peito)", "peito", "a", "c", "o", ["Mãos nas barras, tronco inclinado à frente e cotovelos um pouco abertos.", "Desça até sentir alongar o peito, com o ombro abaixo do cotovelo.", "Empurre de volta sem travar os cotovelos no alto."], "Descer demais e forçar a frente do ombro.", {anim:"mergulho", r:"6–12", noIa:1}],
  ["supinosmith", "Supino reto no Smith", "peito", "a", "c", "", ["Banco no meio do Smith, barra na linha do meio do peito.", "Desça a barra até encostar de leve no peito.", "Empurre até quase esticar os braços."], "Banco fora de posição, com a barra descendo no pescoço."],
  ["inclsmith", "Supino inclinado no Smith", "peito", "a", "c", "", ["Banco entre 30° e 45° no Smith, barra na parte alta do peito.", "Desça controlando até perto do peito.", "Empurre até quase esticar os braços."], "Tirar o quadril do banco."],
  ["flexaodef", "Flexão com déficit", "peito", "ahc", "c", "", ["Mãos apoiadas em dois apoios altos (anilhas, livros ou halteres).", "Desça o peito abaixo da linha das mãos.", "Empurre com o corpo reto da cabeça ao calcanhar."], "Deixar o quadril cair.", {anim:"flexao", r:"8–15", noIa:1}],
  ["hexpress", "Supino hex (halteres colados)", "peito", "ah", "c", "", ["Deitado, halteres colados um no outro sobre o peito.", "Desça mantendo os halteres apertados.", "Empurre sem deixar os halteres se separarem."], "Usar carga demais e perder o aperto.", {anim:"supinohalter", noIa:1}],
  ["platepress", "Plate press (anilha no peito)", "peito", "ah", "i", "", ["Em pé, aperte uma anilha entre as palmas na frente do peito.", "Empurre a anilha para a frente sem soltar o aperto.", "Volte devagar até perto do peito."], "Fazer só o movimento dos braços, sem apertar a anilha.", {noIa:1}],
  ["pullover", "Pullover com halter", "costas", "ah", "i", "o", ["Deitado no banco, halter seguro com as duas mãos acima do peito.", "Leve o halter para trás da cabeça com os braços quase esticados.", "Volte em arco até acima do peito."], "Dobrar os cotovelos e transformar em tríceps.", {r:"10–12"}],
  ["baixaaberta", "Remada baixa com pegada aberta", "costas", "a", "c", "", ["Barra larga, joelhos levemente dobrados, tronco reto.", "Puxe a barra até a parte alta da barriga, abrindo os cotovelos.", "Volte esticando os braços e deixando as escápulas abrirem."], "Levar o tronco para trás a cada repetição.", {anim:"baixa"}],
  ["puxadauni", "Puxada unilateral ajoelhado", "costas", "a", "c", "", ["Ajoelhado de lado para a polia alta, um braço só no pegador.", "Puxe o cotovelo em direção ao quadril.", "Suba devagar até sentir a dorsal alongar."], "Girar o tronco para puxar.", {anim:"puxada", r:"10–12 cada lado"}],
  ["meadows", "Remada Meadows", "costas", "a", "c", "l", ["Barra presa num canto (landmine), de lado para ela, segurando a ponta com uma mão.", "Puxe a barra em direção ao quadril com o tronco inclinado.", "Desça até esticar o braço e sentir alongar."], "Arredondar a lombar.", {anim:"remadacav", r:"8–12 cada lado", noIa:1}],
  ["barrasup", "Barra fixa supinada (chin-up)", "costas", "a", "c", "", ["Palmas viradas para você, mãos na largura dos ombros.", "Suba até o queixo passar da barra.", "Desça devagar até esticar os braços."], "Balançar o corpo para subir.", {anim:"barrafixa", r:"6–10", noIa:1}],
  ["terra", "Levantamento terra", "post", "a", "c", "l", ["Barra rente às canelas, pés na largura do quadril.", "Suba empurrando o chão, com a barra rente às pernas e a coluna neutra.", "Desça levando o quadril para trás."], "Arredondar a lombar.", {anim:"terra", g2:"costas", r:"4–8", noIa:1}],
  ["rackpull", "Rack pull", "costas", "a", "c", "l", ["Barra apoiada no suporte na altura dos joelhos.", "Suba estendendo o quadril, coluna neutra.", "Volte a barra ao suporte com controle."], "Puxar com as costas arredondadas.", {anim:"stiffbarra", r:"5–8", noIa:1}],
  ["yraise", "Elevação em Y na polia", "ombro", "a", "i", "", ["Cabos cruzados das polias baixas, um em cada mão.", "Suba os braços em Y, um pouco acima da linha dos ombros.", "Desça devagar, sem encolher os ombros."], "Usar carga demais e subir com o trapézio.", {r:"12–15"}],
  ["crossinv", "Crucifixo inverso na polia", "ombro", "a", "i", "", ["Cabos cruzados na altura dos ombros, mãos na frente do peito.", "Abra os braços para trás com os cotovelos quase retos.", "Volte devagar até as mãos se cruzarem."], "Puxar com as costas em vez dos ombros.", {r:"12–15"}],
  ["lateralmaq", "Elevação lateral na máquina", "ombro", "a", "i", "", ["Sentado, almofadas encostadas na parte de fora dos braços.", "Suba os braços pela lateral até a linha dos ombros.", "Desça devagar, sem deixar o peso bater."], "Encolher os ombros para subir.", {r:"12–15"}],
  ["lateralincl", "Elevação lateral inclinada com halter", "ombro", "ah", "i", "", ["Segure num apoio e incline o corpo para longe dele.", "Suba o halter pela lateral até a linha do ombro.", "Desça devagar, mantendo a inclinação."], "Balançar o tronco para subir.", {anim:"lateral", r:"12–15 cada lado"}],
  ["militar", "Desenvolvimento militar em pé", "ombro", "a", "c", "ol", ["Barra na altura do queixo, mãos um pouco mais abertas que os ombros.", "Empurre para cima, tirando a cabeça do caminho.", "Desça devagar até o queixo."], "Arquear a lombar para empurrar.", {r:"6–10", noIa:1}],
  ["desenvbarra", "Desenvolvimento com barra sentado", "ombro", "a", "c", "o", ["Sentado com as costas apoiadas, barra na altura do queixo.", "Empurre até quase esticar os braços.", "Desça devagar até o queixo."], "Tirar as costas do encosto.", {r:"6–10"}],
  ["remadaalta", "Remada alta na polia", "ombro", "a", "i", "o", ["Corda na polia baixa, de frente para ela.", "Puxe a corda rente ao corpo até o peito, cotovelos acima das mãos.", "Desça devagar até esticar os braços."], "Subir demais e apertar o ombro.", {r:"12–15"}],
  ["francespoliabarra", "Tríceps francês na polia com barra", "tri", "a", "i", "o", ["De costas para a polia baixa, barra reta atrás da cabeça.", "Cotovelos apontados para cima: estenda os braços até o fim.", "Volte devagar até sentir alongar o tríceps."], "Abrir os cotovelos para os lados.", {r:"10–15"}],
  ["francesuni", "Tríceps francês unilateral com halter", "tri", "ah", "i", "o", ["Sentado, halter numa mão só, braço ao lado da cabeça.", "Desça o halter atrás da cabeça, cotovelo apontado para cima.", "Estenda o braço até o fim."], "Deixar o cotovelo abrir para o lado.", {anim:"frances", r:"10–15 cada lado"}],
  ["testahalter", "Tríceps testa com halteres", "tri", "ah", "i", "o", ["Deitado, halteres acima do peito, braços esticados.", "Dobre só os cotovelos, levando os halteres ao lado da cabeça.", "Estenda de volta sem mexer os braços."], "Mexer os braços junto, virando um supino."],
  ["supinofechado", "Supino fechado", "tri", "a", "c", "", ["Mãos na largura dos ombros, cotovelos perto do corpo.", "Desça a barra até a parte baixa do peito.", "Empurre até esticar os braços."], "Mãos fechadas demais, forçando o punho.", {anim:"supino", g2:"peito", r:"6–10"}],
  ["coicetripolia", "Tríceps coice na polia", "tri", "a", "i", "", ["Tronco inclinado, cabo da polia baixa numa mão, braço colado ao corpo.", "Estenda o cotovelo levando a mão para trás.", "Volte até 90° sem mexer o braço."], "Balançar o braço inteiro.", {r:"12–15 cada lado"}],
  ["tricepsinv", "Tríceps na polia pegada invertida", "tri", "a", "i", "", ["Barra reta com as palmas para cima, cotovelos colados ao corpo.", "Estenda os braços até o fim.", "Volte até 90° sem mexer os ombros."], "Usar carga demais e dobrar os punhos.", {anim:"tricepsbarra"}],
  ["mergulhomaq", "Mergulho na máquina", "tri", "a", "c", "o", ["Sentado, cotovelos para trás e colados ao corpo.", "Empurre as pegadas para baixo até esticar os braços.", "Volte devagar até perto de 90°."], "Subir os ombros em direção às orelhas.", {anim:"mergulho"}],
  ["pendulo", "Agachamento no pêndulo", "quad", "a", "c", "j", ["Ombros nas almofadas, costas no encosto, pés no meio da plataforma.", "Desça fundo, com os joelhos indo à frente.", "Suba empurrando com o pé inteiro."], "Encurtar a descida e perder o alongamento.", {anim:"vsquat", g2:"glut"}],
  ["agachfrontal", "Agachamento frontal", "quad", "a", "c", "jl", ["Barra apoiada na frente dos ombros, cotovelos altos.", "Desça com o tronco alto até a coxa passar da paralela.", "Suba sem deixar os cotovelos caírem."], "Deixar os cotovelos caírem e a barra rolar.", {r:"6–10", noIa:1}],
  ["passada", "Passada (afundo andando)", "glut", "ah", "c", "j", ["Halteres ao lado do corpo, passos longos para a frente.", "Desça até o joelho de trás quase tocar o chão, tronco levemente inclinado.", "Empurre com a perna da frente e já emende o próximo passo."], "Passos curtos, que jogam a carga no joelho.", {anim:"afundo", g2:"quad", r:"10–12 cada perna"}],
  ["afundosmith", "Afundo no Smith", "glut", "a", "c", "j", ["Barra do Smith nas costas, um pé à frente e outro atrás.", "Para mais amplitude, apoie o pé da frente numa anilha ou step baixo.", "Desça reto até o joelho de trás quase tocar o chão e suba pela perna da frente."], "Ficar com os pés na mesma linha e perder o equilíbrio.", {g2:"quad", r:"8–12 cada perna"}],
  ["hiperext", "Hiperextensão 45° (foco glúteo)", "glut", "a", "i", "l", ["Quadril apoiado na almofada, pés presos, costas levemente arredondadas.", "Desça o tronco até sentir alongar atrás da coxa.", "Suba contraindo o glúteo até alinhar o tronco com as pernas, sem arquear a lombar."], "Subir demais e jogar a lombar para trás.", {g2:"post", r:"12–15"}],
  ["stepup", "Subida no banco (step-up)", "glut", "ahc", "c", "j", ["Caixote ou banco firme na altura do joelho, um pé em cima.", "Suba empurrando pelo calcanhar da perna de cima, sem impulso da de baixo.", "Desça devagar e termine as repetições antes de trocar a perna."], "Dar impulso com a perna de baixo.", {g2:"quad", r:"8–12 cada perna"}],
  ["kbswing", "Kettlebell swing", "glut", "ah", "c", "l", ["Pés um pouco mais abertos que os ombros, kettlebell entre as pernas.", "Leve o quadril para trás e projete-o para a frente com força.", "Os braços só acompanham: o peso sobe até a altura do peito."], "Agachar em vez de levar o quadril para trás.", {g2:"post", r:"15–20", noIa:1}],
  ["coice4", "Coice de glúteo em quatro apoios", "glut", "ahc", "i", "", ["Em quatro apoios, mãos sob os ombros e joelhos sob o quadril.", "Suba uma perna para trás com o joelho dobrado, sola do pé para o teto.", "Desça devagar sem arquear a lombar."], "Arquear a lombar para subir mais.", {r:"15 cada perna", noIa:1}],
  ["bayesian", "Rosca Bayesian na polia", "bic", "a", "i", "", ["De costas para a polia baixa, um passo à frente, pegador numa mão.", "Com o braço atrás do corpo, suba só o antebraço até perto do ombro.", "Desça devagar até sentir alongar o bíceps."], "Levar o cotovelo para a frente e perder o alongamento.", {r:"10–15 cada braço"}],
  ["scottmaq", "Rosca Scott na máquina", "bic", "a", "i", "", ["Axilas encostadas no apoio, cotovelos alinhados ao eixo da máquina.", "Suba a pegada sem tirar os braços do apoio.", "Desça devagar até quase esticar os braços."], "Soltar o peso de uma vez na descida."],
  ["scottmartelo", "Rosca martelo no banco Scott", "bic", "a", "i", "", ["Braço apoiado no banco Scott, halter com a palma virada para dentro.", "Suba o halter sem tirar o braço do apoio.", "Desça devagar até quase esticar."], "Girar o punho durante a subida.", {r:"10–12 cada braço"}],
  ["roscainc", "Rosca no banco inclinado", "bic", "ah", "i", "o", ["Costas no banco a cerca de 45°, braços pendurados para trás.", "Suba os halteres sem levar os cotovelos para a frente.", "Desça devagar até esticar e sentir alongar."], "Tirar as costas do banco para subir."],
].map(([id,nome,g,eq,t,lim,cues,erro,x]) => Object.assign({id, nome, g, eq, t, lim, cues, erro}, x||{}));
const catOf = id => CAT.find(x => x.id===id);
const eqTxt = eq => eq.includes('c') ? 'sem equipamento' : eq.includes('h') ? 'halteres' : 'academia';
function presc(x, ed, nivel){
  const comp = x.t==='c', ini = nivel==='ini';
  let s, r, d;
  if(ed==='emagrecer'){ s = 3; r = comp ? '12' : '15'; d = comp ? 60 : 45; }
  else if(ed==='corrida'){ s = ini ? 2 : 3; r = '10–12'; d = 60; }
  else { s = ini || !comp ? 3 : 4; r = ini || !comp ? '10–12' : '8–10'; d = comp ? (ini ? 75 : 90) : 60; }
  // iniciante: sempre 3 séries de 10–12 (só mantém o que é por tempo ou por lado, ex.: prancha, "cada perna")
  if(ini){ const rr = x.r || '', lado = (rr.match(/cada \S+/)||[])[0]; return { s:3, r:/\d\s*s\b|seg|min/i.test(rr) ? rr : '10–12'+(lado ? ' '+lado : ''), d }; }
  return { s, r:x.r || r, d };
}
function exFromCat(x, ed, nivel){
  const p = presc(x, ed, nivel), e = { id:x.id, nome:x.nome, alvo:x.alvo || GNOME[x.g], s:p.s, r:p.r, d:p.d, cues:x.cues.slice(), erro:x.erro };
  if(x.anim) e.anim = x.anim; if(x.semEq) e.semEq = true;
  return e;
}
const estMin = ex => !ex || !ex.length ? 0 : Math.round((ex.reduce((a,e) => a+(e.s||3)*(45+(+e.d||60)), 0)+60*ex.length)/60+5);

/* "IA": monta o treino com as regras de um personal (divisão por dias, volume por nível, tempo, local e limitações) */
const NIVEIS = [['ini','Estou começando','Nunca treinei ou faz menos de 6 meses.'],['int','Já treino','De 6 meses a 2 anos de treino.'],['ava','Treino há bastante tempo','Mais de 2 anos. Conheço bem os exercícios.']];
const LOCS = [['a','Academia','Máquinas, barras, polias e halteres.'],['h','Em casa com halteres','Halteres e, se tiver, um banco ou cadeira firme.'],['c','Em casa, sem equipamento','Só o peso do corpo.']];
const TEMPOS = [30,45,60,75];
const FOCOS = [['pernas','Pernas e glúteo'],['peito','Peito'],['costas','Costas'],['ombro','Ombros'],['bracos','Braços'],['abd','Abdômen']];
const FOCO_G = { pernas:['glut','quad'], peito:['peito'], costas:['costas'], ombro:['ombro'], bracos:['bic','tri'], abd:['abd'] };
const LIMS = [['j','Joelho'],['o','Ombro'],['l','Lombar']];
const IQ0 = () => ({ nivel:'ini', local:'a', n:3, dias:[], tempo:45, foco:[], lim:[] });
// quantos treinos por semana: os dias marcados (se marcou) ou o número escolhido; marcar os dias é opcional
const iqN = q => (q.dias||[]).length || +q.n || 3;
let IQ = IQ0();
const TPL = {
  corpo1:['Corpo todo 1',['quad','peito','costas','post','ombro','abd','tri','bic']],
  corpo2:['Corpo todo 2',['glut','costas','peito','quad','ombro','abd','bic','tri']],
  corpo3:['Corpo todo 3',['quad','costas','peito','glut','ombro','abd','tri','pant']],
  inf:['Pernas e glúteo',['quad','glut','post','quad','glut','pant','abd','post']],
  inf2:['Glúteo e posterior',['glut','post','quad','glut','post','pant','abd','quad']],
  sup:['Superiores 1',['peito','costas','ombro','peito','costas','tri','bic','abd']],
  sup2:['Superiores 2',['costas','peito','ombro','costas','ombro','bic','tri','abd']],
  emp:['Peito, ombro e tríceps',['peito','peito','ombro','ombro','tri','peito','tri','abd']],
  pux:['Costas e bíceps',['costas','costas','costas','ombro','bic','bic','abd','costas']]
};
function iaSplit(n, nivel, legs){
  if(n<=1) return ['corpo1'];
  if(n===2) return ['corpo1','corpo2'];
  if(n===3) return nivel==='ini' ? ['corpo1','corpo2','corpo3'] : legs ? ['inf','sup','inf2'] : ['inf','emp','pux'];
  if(n===4) return ['inf','sup','inf2','sup2'];
  if(n===5) return legs ? ['inf','emp','inf2','pux','sup2'] : ['inf','emp','pux','inf2','sup2'];
  return ['inf','emp','pux']; // 6 ou 7 dias: A B C duas vezes
}
function iaPlan(q, ed){
  const limHit = x => q.lim.some(k => x.lim.includes(k));
  let pri = [...new Set(q.foco.flatMap(k => FOCO_G[k]||[]))];
  if(ed==='corrida' && !pri.length) pri = ['glut','abd'];
  const split = iaSplit(iqN(q), q.nivel, q.foco.includes('pernas')), used = {}, out = {}, N = {30:4, 45:5, 60:6, 75:7}[q.tempo] || 5;
  split.forEach((key, ti) => {
    const [nome, gs] = TPL[key], body = key.indexOf('corpo')===0, slots = gs.slice(0, N);
    // prioridade: mais um exercício do grupo escolhido, no lugar do último que não é prioridade
    pri.forEach(g => {
      if((!body && !gs.includes(g)) || slots.filter(x => x===g).length>=2) return;
      let j = -1; for(let i=slots.length-1; i>0; i--) if(!pri.includes(slots[i])){ j = i; break; }
      if(j<0) return; slots.splice(j,1); slots.splice(Math.min(2, slots.length), 0, g);
    });
    const ex = [], seen = {};
    slots.forEach(g => {
      const nth = seen[g] = (seen[g]||0)+1, ok = x => x.eq.includes(q.local) && !x.noIa && !limHit(x) && !ex.some(e => e.id===x.id);
      let cand = CAT.filter(x => x.g===g && ok(x));
      if(!cand.length) cand = CAT.filter(x => x.g2===g && ok(x));
      if(!cand.length) return;
      const want = nth===1 ? 'c' : 'i'; // primeiro do grupo: composto; o segundo, isolado. Entre os treinos, varia.
      cand.sort((a,b) => ((a.anim==='none'?1:0)-(b.anim==='none'?1:0)) || ((a.t===want?0:1)-(b.t===want?0:1)) || ((used[a.id]||0)-(used[b.id]||0)) || (CAT.indexOf(a)-CAT.indexOf(b)));
      const x = cand[0]; used[x.id] = (used[x.id]||0)+1;
      const e = exFromCat(x, ed, q.nivel);
      if(nth===1 && pri.includes(g) && ed!=='corrida' && q.nivel!=='ini') e.s = Math.min(5, e.s+1);
      ex.push(e);
    });
    // completa o tempo escolhido com os próximos grupos do modelo (até 8 exercícios)
    const pick = g => { const ok = x => x.eq.includes(q.local) && !x.noIa && !limHit(x) && !ex.some(e => e.id===x.id);
      let c = CAT.filter(x => x.g===g && ok(x)); if(!c.length) c = CAT.filter(x => x.g2===g && ok(x)); if(!c.length) return null;
      c.sort((a,b) => ((a.anim==='none'?1:0)-(b.anim==='none'?1:0)) || ((a.t==='i'?0:1)-(b.t==='i'?0:1)) || ((used[a.id]||0)-(used[b.id]||0)) || (CAT.indexOf(a)-CAT.indexOf(b))); return c[0]; };
    const extra = gs.slice(N).concat(pri, gs);
    const maxEx = q.nivel==='ini' ? 7 : 8; // iniciante: no máximo 7 exercícios por treino
    for(let k=0; k<extra.length && ex.length<maxEx && (estMin(ex)<q.tempo-9 || ex.length<4); k++){ const x = pick(extra[k]); if(!x) continue; used[x.id] = (used[x.id]||0)+1; ex.push(exFromCat(x, ed, q.nivel)); slots.push(extra[k]); }
    for(let k=0; k<ex.length && estMin(ex)<q.tempo-7; k++){ const c = catOf(ex[k].id); if(c && c.t==='c' && ex[k].s<(q.nivel==='ini'?3:5)) ex[k].s++; }
    // cabe no tempo escolhido: tira séries dos acessórios, depois o último exercício
    while(q.nivel==='ini' && ex.length>7) ex.pop();
    const lim = q.tempo + 8;
    for(let guard=0; estMin(ex)>lim && guard<60; guard++){
      const k = ex.findIndex((e,i) => i>0 && e.s>3);
      if(k>0){ ex[k].s--; continue; }
      if(ex.length>4){ ex.pop(); continue; }
      const j = ex.findIndex(e => e.s>2); if(j>=0){ ex[j].s--; continue; }
      break;
    }
    const c0 = ex.find(e => (catOf(e.id)||{}).t==='c'); if(c0 && ed!=='emagrecer') c0.aq = true;
    const g = [...new Set(slots)].filter(x => ex.some(e => e.alvo===GNOME[x]));
    out['ABCDEF'[ti]] = { nome, foco:focoTxt(g.slice(0,3)), g, min:estMin(ex), dias:[], ex };
  });
  // dias: na ordem dos treinos, repetindo quando há mais dias que treinos
  const ls = Object.keys(out);
  q.dias.slice().sort((a,b) => a-b).forEach((d,i) => out[ls[i % ls.length]].dias.push(d));
  return out;
}
function iaNotes(q, ed){
  const n = [];
  if(q.lim.includes('j')) n.push('Joelho: ficaram de fora os agachamentos, os afundos e a cadeira extensora.');
  if(q.lim.includes('o')) n.push('Ombro: sem supino com barra e sem exercícios acima da cabeça.');
  if(q.lim.includes('l')) n.push('Lombar: sem agachamento com barra, remada curvada e stiff.');
  if(q.lim.length) n.push('Se algum exercício doer, pare e procure um profissional.');
  if(q.nivel==='ini') n.push('Começando: termine cada série com 2 ou 3 repetições sobrando. O app sugere quando subir a carga.');
  if(ed==='emagrecer') n.push('Para emagrecer, junte o treino com o cardio da semana (meta de 150 min).');
  if(ed==='corrida') n.push('Força para quem corre: menos séries e mais glúteo e abdômen, para sobrar perna para a corrida.');
  return n;
}
/* perguntas da IA: usadas no cadastro e no perfil (data-a="iq-…") */
const radioCard = (a,v,on,n,dsc) => `<button type="button" role="radio" aria-checked="${on}" class="lg-plan${on?' on':''}" data-a="${a}" data-v="${v}"><span class="lay-ck" aria-hidden="true">${ic('check')}</span><span><b>${n}</b><span>${dsc}</span></span></button>`;
const togBtn = (a,v,on,l,cls) => `<button type="button" class="${cls||'lg-opt'}${on?' on':''}" aria-pressed="${on}" data-a="${a}" data-v="${v}">${l}</button>`;
function iqQ(k){
  const q = IQ;
  if(k===1) return `<div class="field"><span class="flabel" id="iq-nv">Há quanto tempo você treina?</span><div class="lg-plans" role="radiogroup" aria-labelledby="iq-nv">${NIVEIS.map(([v,n,d]) => radioCard('iq-nivel',v,q.nivel===v,n,d)).join('')}</div></div>
    <div class="field"><span class="flabel" id="iq-lc">Onde você vai treinar?</span><div class="lg-plans" role="radiogroup" aria-labelledby="iq-lc">${LOCS.map(([v,n,d]) => radioCard('iq-local',v,q.local===v,n,d)).join('')}</div></div>`;
  if(k===2) return `<div class="field"><span class="flabel" id="iq-n">Quantos dias por semana?</span><div class="lg-ns" role="group" aria-labelledby="iq-n">${[2,3,4,5,6].map(n => togBtn('iq-n',n,iqN(q)===n,n,' ')).join('')}</div></div>
    <div class="field"><span class="flabel" id="iq-d">Quais dias? <small>(opcional)</small></span><div class="tdays" role="group" aria-labelledby="iq-d">${TDAYS_L.map((l,i) => togBtn('iq-day',i,q.dias.includes(i),l,' ')).join('')}</div><p class="foot0">${q.dias.length ? q.dias.length+' dia'+(q.dias.length===1?'':'s')+' por semana: '+joinPt(q.dias.map(i => TDAYS_L[i]))+'.' : 'Sem dia fixo? Pode pular. Você treina no dia que der, e o app conta '+iqN(q)+' treinos na semana.'}</p></div>
    <div class="field"><span class="flabel" id="iq-t">Quanto tempo por treino?</span><div class="lg-ns n4" role="group" aria-labelledby="iq-t">${TEMPOS.map(m => togBtn('iq-tempo',m,q.tempo===m,m===75?'75+<small>min</small>':m+'<small>min</small>',' ')).join('')}</div></div>`;
  return `<div class="field"><span class="flabel" id="iq-f">Quer dar prioridade a alguma parte? <small>(até 2)</small></span><div class="lg-opts" role="group" aria-labelledby="iq-f">${togBtn('iq-foco','eq',!q.foco.length,'Corpo equilibrado')}${FOCOS.map(([v,l]) => togBtn('iq-foco',v,q.foco.includes(v),l)).join('')}</div></div>
    <div class="field"><span class="flabel" id="iq-l">Sente dor ou tem limitação em alguma parte?</span><div class="lg-opts" role="group" aria-labelledby="iq-l">${togBtn('iq-lim','no',!q.lim.length,'Nenhuma')}${LIMS.map(([v,l]) => togBtn('iq-lim',v,q.lim.includes(v),l)).join('')}</div><p class="foot0">Com dor forte ou lesão, fale com um médico ou fisioterapeuta antes de começar.</p></div>`;
}
function iqClick(a,d,b){
  const q = IQ, v = d.v;
  switch(a){
    case 'iq-nivel': q.nivel = v; break;
    case 'iq-local': q.local = v; break;
    case 'iq-n': q.n = +v; if(q.dias.length && q.dias.length!==q.n) q.dias = []; break;
    case 'iq-day': { const n = +v, k = q.dias.indexOf(n); if(k>=0) q.dias.splice(k,1); else q.dias.push(n); q.dias.sort((x,y) => x-y); if(q.dias.length) q.n = q.dias.length; break; }
    case 'iq-tempo': q.tempo = +v; break;
    case 'iq-foco': if(v==='eq') q.foco = []; else { const k = q.foco.indexOf(v); if(k>=0) q.foco.splice(k,1); else { q.foco.push(v); if(q.foco.length>2) q.foco.shift(); } } break;
    case 'iq-lim': if(v==='no') q.lim = []; else { const k = q.lim.indexOf(v); if(k>=0) q.lim.splice(k,1); else q.lim.push(v); } break;
  }
  const sel = `[data-a="${a}"][data-v="${v}"]`;
  if(document.getElementById('login')) renderLogin(sel); else { renderSheet(); refocus(sel); }
}
function iaResult(P, ed, q){
  const ls = Object.keys(P), mins = ls.length ? Math.round(ls.reduce((a,l) => a+P[l].min, 0)/ls.length) : 0, notes = iaNotes(q, ed);
  return `<div class="iq-hero"><div><b>${ls.length}</b><span>treino${ls.length===1?'':'s'}</span></div><div><b>${iqN(q)}x</b><span>por semana</span></div><div><b>~${mins}</b><span>min cada</span></div></div>
    ${notes.length ? `<p class="iq-dica">${ic('spark')}<span>${notes.map(esc).join('<br>')}</span></p>` : ''}
    ${ls.map(l => { const t = P[l]; return `<section class="iq-t wk-${['A','B','C','D','E'].includes(l)?l:'A'}"><div class="iq-th"><span class="iq-l">${l}</span><div><b>${esc(t.nome)}</b><span>${t.ex.length} exercícios · ${t.dias.length ? joinPt(t.dias.map(i => TDAYS_L[i]))+' · ' : ''}cerca de ${t.min} min</span></div></div>
      <ol>${t.ex.map(e => `<li><span>${esc(e.nome)}</span><em>${e.s} × ${esc(e.r)}</em></li>`).join('')}</ol></section>`; }).join('')}
    <p class="iq-aviso">${ic('spark')}<span><b>Gerado por IA.</b> Montado automaticamente, sem revisão de um personal. Siga as dicas de execução e pare se sentir dor.</span></p>`;
}

const genView = () => `<div class="iq-gen" role="status" aria-live="polite"><span class="iq-orb" aria-hidden="true">${ic('spark')}</span><h1 class="lg-h">Montando seu treino<span class="dot" aria-hidden="true"></span></h1><ul><li>Lendo suas respostas</li><li>Escolhendo os exercícios</li><li>Ajustando séries, repetições e descanso</li></ul></div>`;

/* cadastro: 1 conta · 2 perfil · 3 como montar · 4–6 perguntas da IA · 7 resultado */
const LGF0 = () => ({ nome:'', email:'', senha:'', show:false, sexo:'F', ed:'hipertrofia', peso:'', plan:null, ck:{t:false, s:false, p:false} });
let AFTER = null; // abre "Meus treinos" assim que o app carregar
const TESTE_LOCAL = location.protocol==='file:' || /^(localhost|127\.0\.0\.1)$/.test(location.hostname); // só no computador de teste o app funciona sem o servidor
function selfStart(){ LG.view = 'setup'; LG.step = 1; LG.serr = ''; LG.gen = false; renderLogin('#su-nome'); }
const waterSug = () => { const p = num(LG.f.peso); return p>=30 && p<=250 ? Math.round(p*35/100)*100 : 3000; };
const suWaterTxt = () => num(LG.f.peso)>=30 ? 'Meta de água: '+fmtL(waterSug())+' por dia (35 ml por kg).' : 'Sem o peso, a meta de água fica em 3,0 L por dia.';
const suOk1 = () => !!(LG.f.nome.trim() && mailOk(LG.f.email) && /^\d{6}$/.test(LG.f.senha) && ckOk(LG.f.ck, true));
function suTop(){ const w = document.getElementById('login'); if(w) w.scrollTop = 0; }
function vSetup(){
  const f = LG.f, st = LG.step;
  const cnt = st<=3 ? 'Passo '+st+' de 4' : st===8 ? 'Passo 4 de 4' : st<=6 ? 'Pergunta '+(st-3)+' de 3' : '';
  const pct = st<=3 ? st/4 : st===8 ? 1 : st<=6 ? (st-3)/3 : 1;
  const top = `<div class="lg-top"><button type="button" class="linkbtn pk-back" data-a="su-back">${ic('chevL')}${st===1?'Entrar com senha':st===7?'Mudar respostas':'Voltar'}</button><span class="lg-stp">${cnt}</span></div><div class="tprog" aria-hidden="true"><i style="width:${Math.round(pct*100)}%"></i></div>`;
  let body;
  if(st===1){ const leg = legacySelf();
    body = `<p class="eyebrow">Treinar sem personal</p><h1 class="lg-h">Crie sua conta<span class="dot" aria-hidden="true"></span></h1>${LG.semConta ? '<p class="lg-leg warn">'+ic('lock')+'<span>Agora o Simplificando funciona com conta. Crie a sua em 1 minuto: seus treinos ficam salvos e dá para entrar de qualquer celular.</span></p>' : ''}
    <p class="muted">${FB || !TESTE_LOCAL ? 'Seu treino e seu histórico ficam salvos na conta. Dá para entrar de qualquer celular.' : 'Modo de teste: a conta fica salva só neste aparelho.'} Já tem conta? <button type="button" class="linkbtn in" data-a="su-entrar">Entrar</button></p>
    <form class="lg-form su-form" data-lg="conta" novalidate>
      <label class="flabel" for="su-nome">Como você quer ser chamado?</label><div class="inp-wrap"><input class="inp" id="su-nome" autocomplete="given-name" maxlength="40" placeholder="Seu nome" value="${esc(f.nome)}" data-su="nome"></div>
      <label class="flabel" for="su-email">E-mail</label><div class="inp-wrap"><input class="inp" id="su-email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false" maxlength="80" placeholder="voce@email.com" value="${esc(f.email)}" data-su="email"></div>
      <label class="flabel" for="su-senha">Crie uma senha de 6 números</label><div class="inp-wrap pw"><input class="inp" id="su-senha" type="${f.show?'text':'password'}" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" autocapitalize="off" spellcheck="false" maxlength="6" placeholder="6 números" value="${esc(f.senha)}" data-su="senha"><button type="button" class="lg-eye" data-a="su-eye" aria-pressed="${f.show}" aria-label="${f.show?'Esconder senha':'Mostrar senha'}">${ic(f.show?'eyeOff':'eye')}</button></div>
      ${aceiteBoxes(f.ck, true, 'su-ck')}
      <p class="lg-err" id="su-err" role="alert">${esc(LG.serr||'')}</p>
      <button type="submit" class="btn primary block lg-go su-go"${suOk1()?'':' disabled'}>Criar conta${ic('chev')}</button>
    </form>
    ${leg ? `<p class="lg-leg">${ic('check')}<span>Achamos treinos salvos neste aparelho${leg.nome ? ' ('+esc(firstName(leg.nome))+')' : ''}. Eles vão junto para a sua conta nova.</span></p>` : ''}`;
  }
  else if(st===2) body = `<h1 class="lg-h">Seu perfil<span class="dot" aria-hidden="true"></span></h1><p class="muted">Rapidinho. Dá para mudar tudo depois.</p>
    <div class="field"><span class="flabel" id="su-bn">Seu boneco</span><div class="iseg" role="group" aria-labelledby="su-bn">${[['F','Feminino'],['M','Masculino']].map(([v,l]) => `<button type="button" class="${f.sexo===v?'on':''}" aria-pressed="${f.sexo===v}" data-a="su-sexo" data-v="${v}">${l}</button>`).join('')}</div><p class="foot0">É quem aparece nas animações de cardio.</p></div>
    <div class="field"><span class="flabel" id="su-ed">Seu objetivo</span><div class="lg-opts" role="group" aria-labelledby="su-ed">${SELF_ED.map(k => togBtn('su-ed',k,f.ed===k,ED[k].nome)).join('')}</div></div>
    <div class="field"><label class="flabel" for="su-peso">Seu peso (opcional)</label><div class="inp-wrap"><input class="inp" id="su-peso" inputmode="decimal" autocomplete="off" placeholder="70" value="${esc(f.peso)}" data-su="peso"><span>kg</span></div><p class="foot0" data-su-wt>${suWaterTxt()}</p></div>
    <button type="button" class="btn primary block" data-a="su-next">Continuar${ic('chev')}</button>`;
  else if(st===3) body = `<h1 class="lg-h">Como você quer montar seu treino?<span class="dot" aria-hidden="true"></span></h1>
    <div class="su-modes">
      <button type="button" class="su-mode ia" data-a="su-mode" data-v="ia"><span class="su-mi" aria-hidden="true">${ic('spark')}</span><span class="su-mt"><b>Deixe a IA montar seu treino</b><em>Recomendado</em><span>Responda 3 perguntas rápidas. A IA monta os treinos, os dias, as séries e o descanso para ${ED[f.ed].nome.toLowerCase()}.</span></span>${ic('chev','chev')}</button>
      <button type="button" class="su-mode" data-a="su-mode" data-v="meu"><span class="su-mi" aria-hidden="true">${ic('edit')}</span><span class="su-mt"><b>Montar o meu treino</b><span>Você escolhe os dias, dá nome a cada treino e adiciona os exercícios que quiser.</span></span>${ic('chev','chev')}</button>
    </div><p class="foot0">Sem personal, o treino não tem acompanhamento profissional: ele é gerado automaticamente pela IA ou montado por você. Dá para trocar depois no perfil.</p>`;
  else if(st<=6) body = `<p class="eyebrow">IA · ${ED[f.ed].nome}</p><h1 class="lg-h">${['Sua experiência','Seus dias','Seu foco'][st-4]}<span class="dot" aria-hidden="true"></span></h1>${iqQ(st-3)}
    <button type="button" class="btn primary block" data-a="su-next">${st===6 ? ic('spark')+'Montar meu treino' : 'Continuar'+ic('chev')}</button>`;
  else if(st===8) body = suInstView();
  else if(LG.gen || !f.plan) body = genView();
  else body = `<p class="eyebrow">Feito pela IA</p><h1 class="lg-h">Seu treino está pronto<span class="dot" aria-hidden="true"></span></h1>${iaResult(f.plan, f.ed, IQ)}
    <p class="foot0">Dá para trocar exercícios, dias e nomes quando quiser em Perfil › Meus treinos.</p>
    <button type="button" class="btn primary block" data-a="su-done">Começar a treinar${ic('chev')}</button>
    <button type="button" class="btn block ghost" data-a="su-back">Mudar respostas</button>`;
  return `<div class="lg-in su">${top}${body}</div>`;
}
function vEntrar(){
  const e = LG.en;
  return `<div class="lg-in su"><div class="lg-top"><button type="button" class="linkbtn pk-back" data-a="su-criar">${ic('chevL')}Criar conta</button></div>
    <p class="eyebrow">Treinar sem personal</p><h1 class="lg-h">Entre na sua conta<span class="dot" aria-hidden="true"></span></h1>
    <form class="lg-form su-form" data-lg="entrar" novalidate>
      <label class="flabel" for="en-email">E-mail</label><div class="inp-wrap"><input class="inp" id="en-email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false" maxlength="80" placeholder="voce@email.com" value="${esc(e.email)}" data-en="email"></div>
      <label class="flabel" for="en-senha">Senha</label><div class="inp-wrap pw"><input class="inp" id="en-senha" type="${e.show?'text':'password'}" autocomplete="current-password" autocapitalize="off" spellcheck="false" maxlength="64" placeholder="Sua senha de 6 números" value="${esc(e.senha)}" data-en="senha"><button type="button" class="lg-eye" data-a="su-eye2" aria-pressed="${e.show}" aria-label="${e.show?'Esconder senha':'Mostrar senha'}">${ic(e.show?'eyeOff':'eye')}</button></div>
      <p class="lg-err" id="en-err" role="alert">${esc(LG.serr||'')}</p>
      <button type="submit" class="btn primary block lg-go">Entrar${ic('chev')}</button>
    </form>
    ${e.msg ? `<p class="warn" role="status">${ic('msg')}<span>${esc(e.msg)}</span></p>` : ''}
    <div class="lg-links"><button type="button" class="linkbtn" data-a="su-esq">Esqueci a senha</button><button type="button" class="linkbtn" data-a="lg-back">Tenho personal</button></div></div>`;
}
function suErr(msg, id){
  LG.serr = msg; const e = document.getElementById(id||'su-err'); if(e) e.textContent = msg;
  vibrate([40,40,40]);
}
function suBusy(on){ const g = document.querySelector('#login .lg-go'); if(g){ g.disabled = on; g.classList.toggle('busy', on); } }
async function suConta(){
  const f = LG.f, email = f.email.trim().toLowerCase();
  if(!cleanName(f.nome)) return suErr('Digite seu nome.');
  if(!mailOk(email)) return suErr('Confira o e-mail. Ex.: voce@email.com');
  if(!/^\d{6}$/.test(f.senha)) return suErr('A senha tem que ter 6 números.');
  if(!ckOk(f.ck, true)) return suErr('Para criar a conta, marque as três caixas de aceite.');
  if(LG.uid && LG.uidMail===email){ LG.step = 2; renderLogin(); suTop(); return; }
  if(!FB && !TESTE_LOCAL) return suErr('Sem conexão com o servidor agora. Confira a internet e tente de novo.');
  suBusy(true);
  const r = FB ? await FB.signUp(email, f.senha, cleanName(f.nome)) : localSignUp(email, f.senha);
  suBusy(false);
  if(r.err) return suErr(r.err);
  if(legacySelf()) return suMigra(r.uid, email); // quem usava sem conta: os treinos do aparelho vão para a conta nova
  LG.uid = r.uid; LG.uidMail = email; LG.serr = ''; LG.step = 2; renderLogin(); suTop();
  if(FB) FB.perfil({ tipo:'autonomo', nome:cleanName(f.nome), email, plano:'', treinos:0, criadoEm:Date.now(), ultimoAcesso:Date.now(), ver:APPV });
}
/* conta nova: avisa o celular do dono (push.php manda a notificação; nada sai daqui além do nome, objetivo e tipo de treino) */
function novoAviso(nome, ed, plano){ if(TESTE_LOCAL) return; try { fetch('/push.php?acao=novo', {method:'POST', keepalive:true, body:JSON.stringify({nome:String(nome||'').slice(0,40), ed:ed||'', plano:plano||''})}).catch(() => {}); } catch(e){ /* sem internet: segue */ } }
/* perfil antigo, salvo só no aparelho (de antes de existir conta): vira uma conta com todo o histórico */
function suMigra(uid, email){
  const s = legacySelf(); if(!s) return;
  const nome = cleanName(LG.f.nome) || s.nome || 'Você';
  s.nome = nome; s.conta = Object.assign({}, s.conta, { email }); s.aceite = aceiteNovo(true); s._em = Date.now();
  try { localStorage.setItem(autoKey(uid), JSON.stringify(s)); localStorage.setItem(KEY+':self:migrado', localStorage.getItem(KEY+':self')||''); localStorage.removeItem(KEY+':self'); } catch(e){ /* sem armazenamento */ }
  if(FB){ FB.autoSave(uid, s, true); FB.perfil({ tipo:'autonomo', nome, email, sexo:(s.conta||{}).sexo||'F', objetivo:s.ed, plano:(s.conta||{}).plan||'', criadoEm:Date.now(), ultimoAcesso:Date.now(), ver:APPV }); }
  writeSess({mode:'self', uid}); LG.view = 'login'; LG.step = 1; LG.f = LGF0(); LG.uid = null; LG.uidMail = '';
  novoAviso(nome, s.ed, (s.conta||{}).plan==='meu' || (s.conta||{}).plan==='ia' ? s.conta.plan : '');
  welcome(firstName(nome), (s.conta||{}).sexo, 'Seus treinos deste aparelho vieram junto.');
}
async function suEntrar(){
  const e = LG.en, email = e.email.trim().toLowerCase();
  if(!FB && !TESTE_LOCAL) return suErr('Sem conexão com o servidor agora. Confira a internet e tente de novo.', 'en-err');
  if(!mailOk(email)) return suErr('Confira o e-mail. Ex.: voce@email.com', 'en-err');
  if(!e.senha) return suErr('Digite sua senha.', 'en-err');
  suBusy(true);
  const r = FB ? await FB.signIn(email, e.senha) : localSignIn(email, e.senha);
  if(r.err){ suBusy(false); return suErr(r.err, 'en-err'); }
  let data = autoLocal(r.uid);
  if(FB){
    const rm = await FB.autoLoad();
    if(rm.err || rm.out){ suBusy(false); await FB.logout(); return suErr('Não deu para carregar seus dados agora. Confira a internet e tente de novo.', 'en-err'); }
    if(rm.s && rm.s.v===2){ if(!data || (rm.s._em||0)>=(data._em||0)){ autoJunta(rm.s, data); data = rm.s; } else autoJunta(data, rm.s); } // junta o aparelho e a nuvem
  }
  suBusy(false);
  if(data && data.conta){
    try { localStorage.setItem(autoKey(r.uid), JSON.stringify(data)); } catch(err){ /* sem armazenamento */ }
    writeSess({mode:'self', uid:r.uid}); LG.view = 'login'; LG.serr = '';
    const inp = document.activeElement; if(inp && inp.blur) inp.blur();
    welcome(firstName(data.nome)||'de volta', data.conta.sexo); return;
  }
  // conta criada, mas o perfil ficou pela metade: segue o cadastro
  LG.uid = r.uid; LG.uidMail = email; LG.f.email = email; if(!LG.f.nome) LG.f.nome = r.nome || '';
  if(FB) FB.perfil({ tipo:'autonomo', nome:cleanName(LG.f.nome)||'', email, ultimoAcesso:Date.now(), ver:APPV });
  LG.view = 'setup'; LG.step = 2; LG.serr = ''; renderLogin(); suTop();
}
async function suForgot(){
  const email = String(LG.en.email||'').trim();
  if(!FB){ LG.en.msg = 'No modo de teste a conta fica só neste aparelho. Se esqueceu a senha, crie uma conta nova.'; renderLogin(); return; }
  if(!mailOk(email)) return suErr('Digite seu e-mail acima para receber o link.', 'en-err');
  const r = await siteReset(email, false);
  if(r.err) return suErr(r.err, 'en-err');
  LG.serr = ''; LG.en.msg = 'Pronto. Se esse e-mail tiver conta, chega um link para criar uma senha nova. Olhe também a caixa de spam.'; renderLogin();
}
function suNext(){
  const st = LG.step;
  if(st===6){
    LG.step = 7; LG.gen = true; LG.f.plan = null; renderLogin(); suTop();
    setTimeout(() => { LG.f.plan = iaPlan(IQ, LG.f.ed); LG.gen = false; if(LG.view==='setup' && LG.step===7){ renderLogin('[data-a="su-done"]'); suTop(); } }, RM() ? 300 : 1700);
    return;
  }
  LG.step = Math.min(6, st+1); renderLogin(); suTop();
}
/* passo 4 de 4: instalar o app (no lugar das janelas depois do cadastro). Quem quiser segue pelo navegador. */
function suInst(mode){ LG.f.mode = mode; LG.step = 8; renderLogin(); suTop(); }
function suInstView(){
  const f = LG.f, mob = instMobile(), p = (n, t) => `<li><span class="si-n">${n}</span><span>${t}</span></li>`;
  let passos, go;
  if(!mob){ passos = p(1, 'No celular, abra <b>simplificandoeduca.com/treino</b>')+p(2, 'Entre com o seu <b>e-mail e senha</b>')+p(3, 'Coloque o app na tela do celular e comece o treino'); go = `<button type="button" class="btn primary block lg-go" data-a="su-inst-x">Começar${ic('chev')}</button>`; }
  else if(ANDROID && INST_EV){ passos = p(1, 'Toque em <b>Instalar o app</b> aqui embaixo')+p(2, 'Confirme em <b>Instalar</b>')+p(3, 'Abra o <b>Simplificando</b> pelo ícone e comece o treino'); go = `<button type="button" class="btn primary block lg-go" data-a="su-inst">${ic('dl')}Instalar o app</button>`; }
  else if(ANDROID){ passos = p(1, 'Toque nos <b>três pontinhos ⋮</b>, no canto de cima do Chrome')+p(2, 'Toque em <b>Instalar app</b> (ou <b>Adicionar à tela inicial</b>)')+p(3, 'Abra o <b>Simplificando</b> pelo ícone e comece o treino'); go = `<button type="button" class="btn primary block lg-go" data-a="su-inst-ok">Pronto, já instalei</button>`; }
  else { passos = p(1, 'Toque em <b>Compartilhar</b>, o quadrado com a seta, lá embaixo no Safari')+p(2, 'Toque em <b>Adicionar à Tela de Início</b>')+p(3, 'Abra o <b>Simplificando</b> pelo ícone e comece o treino'); go = `<button type="button" class="btn primary block lg-go" data-a="su-inst-ok">Pronto, já coloquei</button>`; }
  return `<p class="eyebrow">Último passo</p><h1 class="lg-h">${mob ? 'Instale o app' : 'Use no celular'}<span class="dot" aria-hidden="true"></span></h1>
    <p class="muted">${mob ? 'Fica na tela do celular, abre em 1 toque e avisa a hora do treino e da água. É grátis.' : 'O Simplificando foi feito para usar na academia, pelo celular.'}</p>
    ${mob && IOS && !iosSafari() ? `<p class="lg-leg warn">${ic('flag')}<span>Abra este link no <b>Safari</b> para conseguir colocar o app na tela de início.</span></p>` : ''}
    <div class="si-box"><p class="si-h">${!mob ? 'Como fazer' : ANDROID ? 'No Android (Chrome)' : 'No iPhone (Safari)'}</p><ol class="si-st">${passos}</ol></div>
    ${acompPodeSu() ? `<div class="sw-row si-ac"><div><b>Compartilhar meus treinos com o Simplificando</b><span>Opcional. Ajuda a melhorar o app. Dá para mudar no Perfil.</span></div><label class="switch"><input type="checkbox" data-su-acomp${f.acomp?' checked':''}><span class="knob"></span><span class="sr">Compartilhar meus treinos com o Simplificando</span></label></div>` : ''}
    ${go}${mob ? `<button type="button" class="linkbtn si-x" data-a="su-inst-x">Continuar no navegador</button>` : ''}`;
}
const acompPodeSu = () => !!FB && !!LG.uid;
function suFinish(mode){
  const f = LG.f, nome = cleanName(f.nome) || 'Você', uid = LG.uid, sexo = f.sexo==='M' ? 'M' : 'F';
  if(!uid){ LG.step = 1; renderLogin(); return; }
  const s = fresh(false); s.nome = nome; s.ed = f.ed; s.waterGoal = waterSug(); s.cardioGoal = f.ed==='emagrecer' ? 150 : 120;
  s.conta = { sexo, peso:num(f.peso)||null, email:LG.uidMail||'', plan:mode, treinos: mode==='ia' && f.plan ? f.plan : {}, ia: mode==='ia' ? JSON.parse(JSON.stringify(IQ)) : null, semana: mode==='ia' ? iqN(IQ) : 0, dias:[] };
  s.aceite = aceiteNovo(true); s._em = Date.now(); s.inst = LG.step===8 ? 'feito' : 'novo'; // passou pelo "Instale o app": não pergunta de novo agora
  if(LG.step===8 && acompPodeSu()) s.acomp = { on:!!f.acomp, em:Date.now() };
  if(LG.step===8) SITE_FEITO = true; // já viu o "Instale o app": nada de "Baixe nosso app" nesta visita
  try { localStorage.setItem(autoKey(uid), JSON.stringify(s)); } catch(e){ /* sem armazenamento */ }
  if(FB){ FB.autoSave(uid, s, true); FB.perfil({ tipo:'autonomo', nome, email:LG.uidMail||'', sexo, objetivo:f.ed, plano:mode, treinos:mode==='ia' && f.plan ? Object.keys(f.plan).length : 0, criadoEm:Date.now(), ultimoAcesso:Date.now(), aceite:{ v:s.aceite.v, em:s.aceite.em }, acomp:!!(s.acomp && s.acomp.on), ver:APPV }); }
  writeSess({mode:'self', uid}); LG.view = 'login'; LG.step = 1; LG.f = LGF0(); LG.uid = null; LG.uidMail = '';
  novoAviso(nome, f.ed, mode);
  if(mode==='meu') AFTER = 'meus';
  welcome(firstName(nome), sexo, mode==='meu' ? 'Agora é só montar seu treino.' : 'Seu treino está pronto.');
}

/* "Meus treinos": o aluno monta, nomeia e distribui os treinos na semana */
const ownPlan = () => !!ACCT && ACCT.mode==='self' && !!S.conta && (S.conta.plan==='meu' || S.conta.plan==='ia');
