# Play Space Horas — instalação e configuração (v0.30)

Sistema de controle de horas da Play Space: banco de horas por criança, entrada e saída pelo rosto (iDFace da Control iD), conversas com as famílias pelo WhatsApp (Evolution API), agenda e equipe com três papéis. Tudo em PHP + SQLite, sem banco MySQL, feito para rodar na hospedagem da Gama (HostGator) ou em qualquer cPanel.

## 1. O que precisa no servidor

- PHP 7.4 ou mais novo (8.x de preferência) com as extensões **pdo_sqlite**, **curl**, **zip**, **mbstring** e **gd** (todas vêm ligadas por padrão no HostGator; confira em cPanel → *Selecionar versão do PHP*).
- A pasta `playspace/` dentro de `public_html/` (ou de um subdomínio).
- O site em **HTTPS** (o navegador exige para a câmera, a PWA e o cookie de login).

Os dados ficam fora da pasta pública, em `~/playspace-dados/` (criada sozinha no primeiro acesso: o banco `playspace.sqlite`, as sessões e o log). Se o servidor não deixar criar ali, usa `playspace/api/dados/` como reserva. Para fazer backup, copie essa pasta.

## 2. Subir pela primeira vez

1. No cPanel → Gerenciador de Arquivos, envie a pasta `playspace/` inteira do repositório (index.html, manifest.json, versao.json, atualizar.php, `img/`, `api/`, `ponte/`).
2. Abra `https://seusite.com.br/playspace/`. Como ainda não há ninguém cadastrado, aparece **"Bem-vinda ao Play Space"**: crie a conta da gestora (nome, login curto, senha de 6+ caracteres). Pronto, está dentro.
3. Dali em diante, **Atualizar** no topo da tela baixa as versões novas direto do GitHub. Só a gestora ou a supervisora veem o botão.

## 2b. Teste com 30 crianças (botão no rodapé)

Para ver como a tela fica com o espaço cheio, a gestora toca em **Teste: 30 crianças**, no rodapé de qualquer tela (ou em Ajustes → Dados de exemplo). Entram 33 crianças fictícias:

- **30 no espaço agora**, com nomes, responsáveis e horários de entrada diferentes: umas acabaram de entrar (com o atalho de cancelar), outras estão no tempo, acabando, **na tolerância** e três **já com cobrança por atraso**; duas são **PCD** e duas entraram com **voucher**; algumas têm restrição anotada e duas estão sem sapateira.
- **3 fora do espaço** (uma com a entrada cancelada, para a supervisão conferir; duas que já saíram hoje, uma delas com cobrança por atraso), para testar a busca.
- Vendas e registros do dia, conversas no WhatsApp e compromissos na agenda.

Elas se comportam como crianças de verdade: o tempo corre, a cobrança por atraso nasce sozinha, dá para dar saída, cancelar e vender. Nada vai para o iDFace nem para o WhatsApp. Nas crianças de teste o relógio não para na hora de fechar a loja, para o teste funcionar a qualquer hora.

Para tirar, o mesmo botão vira **Tirar o teste**: saem as 33 crianças e tudo o que nasceu delas (vendas, cobranças, linhas do dia, conversas, agenda). Os cadastros de verdade ficam. Só a gestora coloca e tira o teste; a equipe vê as crianças de teste na tela enquanto elas estiverem lá.

Na prévia (sem servidor) o mesmo botão existe e faz a mesma coisa na própria tela.

## 3. Equipe e papéis (Ajustes → Equipe)

| Papel | O que faz |
|---|---|
| **Gestor** | Tudo: regra das horas, pacotes, equipe, WhatsApp, iDFace. Pode remover crianças. |
| **Supervisor** | Tudo da loja (horas, cadastro, WhatsApp, agenda) e *vê* os Ajustes, mas não os altera. **Confere as entradas canceladas**, cancela uma entrada mesmo fora do prazo e **corrige a hora de uma saída esquecida**. |
| **Atendente** | Horas, cadastro de criança, WhatsApp e agenda. Não vê Ajustes. Cancela uma entrada sem cobrar só dentro do prazo (9 min), escrevendo o motivo. |

A gestora cadastra cada pessoa com nome, login e senha inicial. Cada um troca a própria senha no avatar (canto inferior esquerdo no computador, aba **Conta** no celular) → *Trocar a senha*. Para redefinir a senha de alguém, a gestora edita a pessoa e digita uma senha nova.

## 4. WhatsApp (Ajustes → WhatsApp da loja)

Usa a mesma Evolution API da Sophi CRM.

1. Toque em **WhatsApp da loja**. Preencha o **endereço do servidor** (já vem `http://143.95.164.89`), o **nome da instância** (`playspace`, uma instância só para a loja) e a **chave (apikey)** da Evolution. *Salvar conexão*.
2. Toque em **Conectar pelo QR Code**. No celular da loja: WhatsApp → *Dispositivos conectados* → *Conectar dispositivo* → aponte para o código. Ele renova sozinho a cada 20 s; quando conectar, a folha fecha e o chip fica verde.
3. O **webhook** (a Evolution avisar o sistema das mensagens que chegam) é configurado sozinho na hora em que conecta. O endereço aparece na mesma folha, caso precise conferir no painel da Evolution.

**Nada sai sozinho.** A partir da 0.75 os avisos automáticos ficam desligados (Ajustes → Avisos automáticos no WhatsApp → *Nenhum*): a loja não manda aviso de chegada, de saída, de tempo acabando nem de cobrança, para o responsável não ficar de olho no relógio. As dicas do sistema também não mandam chamar ninguém por causa do tempo de quem está no espaço, e as respostas prontas não falam de tempo nem de saldo. O WhatsApp fica para a equipe falar com a família quando quiser (e para emergência). Se a gestora ligar os avisos, eles dizem só que a criança chegou ou saiu.

As respostas dos responsáveis entram na aba **WhatsApp**, organizada por dia (Hoje, Ontem, escolher a data), com quem da equipe respondeu. A conversa é ligada pela **telefone do responsável** no cadastro da criança: por isso o telefone precisa estar certo (com DDD).

## 4b. Sem o iDFace: entrada e saída na tela

Enquanto a loja não usa o iDFace, a **leitura fica desligada** (Ajustes → iDFace; a versão 0.72 já desliga sozinha) e a equipe marca tudo na tela:

- No **atendimento**, ao registrar uma venda com Recreação, a opção **Dar entrada agora** já vem marcada: a venda é gravada e o tempo começa a contar. Para quem está comprando para outro dia, é só desmarcar.
- Quem já tem **um período inteiro guardado** ganha o botão **Entrada** (na busca da tela inicial e na ficha). Sem um período comprado, a entrada não é aceita: aparece **Vender** (ou **Atender**, na busca).
- Quem está no espaço tem o botão **Saída** na linha da lista e na ficha. O toque abre a **folha da saída** (de quem é, quanto ficou, o que debita e, se passou do tempo, o valor a receber) e o segundo toque confirma. São dois toques de propósito: com 40 crianças na lista, um toque errado não tira a criança errada. A saída libera a sapateira e avisa onde está o sapato.
- Qualquer pessoa da equipe (inclusive Atendente) marca entrada e saída, e os avisos têm **Desfazer**. O Desfazer é para o toque errado e só vale nos instantes seguintes (o servidor aceita até 2 minutos); depois disso, tirar uma entrada é pelo **cancelamento com motivo** (seção 6e).
- Somem da tela as coisas que só fazem sentido com o aparelho: acesso autorizado/bloqueado, "falta o rosto" e os avisos de aparelho sem contato.

Para passar a usar o aparelho: Ajustes → iDFace → **Ligar o iDFace**. Os botões Entrada e Saída somem (fica só a saída de correção para Gestor e Supervisor) e o rosto volta a mandar.

## 5. iDFace (Ajustes → iDFace)

O aparelho reconhece o rosto da criança: fora → entra; dentro → sai e debita as horas da regra. Duas leituras seguidas em menos de 90 s contam uma vez só.

**Cadastro do rosto**: com o aparelho conectado, ao salvar uma criança nova o sistema abre "Rosto no iDFace": o aparelho entra no modo de cadastro e a criança só precisa olhar para a câmera. A tela acompanha (na fila → esperando o rosto → cadastrado). Dá para refazer pela ficha (*Cadastrar rosto no iDFace*). A foto pelo celular ficou opcional: ela vai no aviso de chegada do WhatsApp e, sem aparelho conectado, continua servindo para cadastrar o rosto.

**Ligar o aparelho ao site** (modo *push*: o iDFace chama o site a cada 3 s; não precisa abrir porta no roteador):

- **Jeito fácil (Windows na loja)**: em Ajustes → iDFace, preencha o IP do aparelho e o usuário (`admin`), *Salvar*, e **Baixar "Conectar o iDFace" (.bat)**. Rode o arquivo num computador da mesma rede do aparelho, digite a senha do iDFace e ele configura tudo. O arquivo já vem com o endereço e a chave do site dentro.
- **Sem computador**: no painel do iDFace (navegador, `http://IP-do-aparelho`), em *Configurações → Modo de operação → Push*, cole o endereço que aparece em **"Sem computador: colar no painel do iDFace"** e deixe o intervalo em 5 s.

A folha mostra se o aparelho está **Conectado** (falou nos últimos 2 min), **Sem contato** ou **Ainda não conectado**, e o que está na fila para mandar. *Gerar chave nova* invalida a anterior (rode o conector de novo). **Desligar leitura** pausa tudo sem perder leituras: ao religar, as que ficaram acumuladas entram (só as das últimas 6 h geram entrada/saída).

**Acesso no iDFace (a chavinha)**: cada criança tem uma chavinha no cartão e na ficha. *Autorizado*, o aparelho aceita o rosto e as horas começam a contar na leitura; *bloqueado*, o aparelho recusa o rosto (se ela estiver dentro, a saída ainda conta). Quando o pacote zera, o site bloqueia sozinho (regra "Quando o pacote zerar" em Ajustes → Horas) e vender horas libera de novo. Entrada e saída à mão existem só para gestor e supervisor, na ficha, para corrigir; o controle das horas é do rosto, não do funcionário.

**Testar sem aparelho**: *Simular um rosto na câmera* na mesma folha faz a criança entrar ou sair como se tivesse olhado para o iDFace, com aviso no WhatsApp e tudo.

## 6. Regra das horas (Ajustes → Horas)

O tempo é debitado **sempre em períodos inteiros** (o período da Recreação: 30 min). Não existe período quebrado: quem ficou 11 min debita 30, e toda visita debita pelo menos um período (a única saída sem cobrança é o cancelamento em até 9 min, seção 6e). Os serviços também são cobrados por período cheio: o carrinho fusquinha devolvido com 21 min custa os mesmos R$ 15.

- **Tolerância depois do tempo comprado** (9 min de fábrica): com 30 min comprados, até 39:00 continua valendo um período. Para o responsável a equipe fala em 5 minutos; os 9 só aparecem em Ajustes.
- **Cobrança por atraso**: passou da tolerância, o sistema registra sozinho mais um período (seção 6g).
- **A loja fecha às** (22h de fábrica): depois dessa hora o tempo para de contar, para uma saída esquecida não virar cobrança a noite toda.
- **Marcar "acabando"** (10 min): a criança fica em amarelo na lista; nada é enviado ao responsável.
- **Cancelar a entrada sem cobrar** (seção 6e) e **Quando o pacote zerar** continuam como antes. Uma linha no fim do cartão mostra na prática quanto cada tempo debita. Os preços ficam em **Serviços e preços** (seção 6a).

## 6a. Cadastro, cliente e histórico

**Cadastrar** (botão de mais) pede primeiro o **responsável** e depois a **criança**, nesta ordem: documento do responsável (**CPF ou RNE**, para estrangeiro: o seletor fica ao lado do rótulo), nome do responsável, telefone (WhatsApp) e um **outro telefone** opcional; depois o nome da criança, a marca **Criança PCD** (seção 6h), a sapateira e as **restrições e observações**. **O cadastro só fecha com quatro dados** (marcados com asterisco): o documento do responsável (CPF válido, ou RNE), o nome do responsável, **um telefone com DDD** (qualquer um dos dois campos) e o nome da criança. Enquanto falta algum, a linha acima do botão diz o que falta ("Falta o CPF (ou RNE), um telefone…") e, se a pessoa tentar cadastrar assim mesmo, os campos que faltam ficam marcados em vermelho com a explicação embaixo. O servidor confere a mesma coisa. Ao **editar**, nenhum desses dados pode ser apagado; um cadastro antigo, feito antes desta regra, continua podendo ser salvo como está (para anotar uma alergia, por exemplo), mas o que for mexido precisa ficar certo. O cadastro **não vende pacote** (as horas entram depois, pelo botão **Vender**) e **não pede foto nem rosto**: quando for usar o iDFace, a foto e o rosto são feitos na ficha da criança. Se só o segundo telefone for preenchido, ele vira o principal. Mensagem que chega no WhatsApp é reconhecida por qualquer um dos dois números.

- **Restrições** (alergia, se pode ou não fazer pintura no rosto, se pode ou não mexer no cabelo) ficam no campo de observações e são opcionais. Os atalhos embaixo do campo só escrevem mais rápido: tocar de novo num atalho tira o que ele escreveu (inclusive a alergia), e **Sem restrição** limpa tudo. O texto é livre e pode ser mudado depois em **Editar**. Elas aparecem em destaque na ficha e no histórico.
- **Cliente já cadastrado**: ao completar um CPF (ou RNE) que já existe, o cadastro avisa na hora: mostra quem é o responsável e as crianças dele (última vinda, horas consumidas, horas disponíveis), preenche os dados do responsável e **abre sozinho o histórico do cliente ao lado**, sem precisar tocar em nada (no celular, aparece o aviso "Este CPF já tem cadastro" com o atalho **Ver o histórico**). Se o documento for trocado, o histórico fecha. Para cadastrar um irmão, é só escrever o nome da nova criança. A mesma criança não entra duas vezes no mesmo CPF.
- **Histórico do cliente**: abre numa aba à direita (no celular, na tela toda) e junta tudo do responsável: quanto pagou ao todo, horas compradas, consumidas e disponíveis; cada criança com o saldo, as restrições e os botões **Vender** e **Chamar para renovar**; **todas as compras** (pacote, o que veio junto, carrinho e outros produtos, valor, quem vendeu) e **todas as visitas**. Abre pelo botão **Histórico** no cadastro, na ficha da criança ou tocando na "Última contratação" do Banco de horas. O cliente é reconhecido pelo CPF ou pelo RNE; sem documento, pelo WhatsApp.
- **Sem horas, sem entrada**: com a regra "Quando o pacote zerar → Bloquear o acesso sozinho" (o padrão), a criança recém-cadastrada começa com o acesso **bloqueado** no iDFace e é liberada sozinha na primeira venda de horas. Vender só um produto (carrinho) não libera. Desfazer uma venda que deixa o saldo em zero bloqueia de novo.

**Sapateiras** (1 a 44): a criança tira o sapato para brincar e ele fica numa sapateira numerada.

- No **cadastro**, a grade de números aparece logo depois do nome da criança: um toque escolhe. As que estão **em uso aparecem hachuradas** e não podem ser escolhidas (tocar numa delas mostra com quem está e oferece **Liberar**, para o caso de uma esquecida).
- Para quem já é cadastrado: na **ficha** (linha "Sapateira", botão Escolher ou Trocar), no **número da sapateira** no começo da linha de quem está no espaço (na tela inicial), ou no botão **Sapateira** do aviso que aparece quando o rosto é lido na entrada.
- O número fica à vista no começo da linha da criança, na lista do espaço. Na **saída**, a sapateira **fica livre sozinha** e o aviso diz onde está o sapato ("Helena saiu · sapato na sapateira 12"); o mesmo fica anotado em "Hoje na recepção".
- **Ajustes → Sapateiras** mostra o mapa (quem está com qual), libera uma esquecida e muda a quantidade (44 por padrão).

**Atendimento e serviços**: ao terminar o cadastro, o sistema abre o **atendimento** da criança: no meio, os serviços com o preço e o **total a pagar**; ao lado (em tela larga), os dados e o histórico de compras do cliente. O mesmo atendimento abre pelo botão **Vender** (ficha, Banco de horas, histórico).

- Os serviços de fábrica são **Recreação** (R$ 80 a cada 30 min), **Carrinho fusquinha** (R$ 15 a cada 30 min) e **Carrinho de bebê** (R$ 30, das 10h às 22h). Em cada linha, o **+** põe mais um período (ou mais uma unidade) e o **−** tira; o valor da linha e o total aparecem na hora. **Registrar a venda** grava tudo de uma vez; o aviso tem **Desfazer**.
- O tempo de **Recreação** vendido entra no saldo de brincar da criança (meia hora conta) e libera o acesso dela; os carrinhos ficam só anotados na venda, com a duração.
- **Ajustes → Serviços e preços** muda o nome, o valor e a forma de cobrar (valor único ou a cada período de tantos minutos), cria serviços novos (uma meia, um lanche) e diz qual deles é tempo de brincar. O valor cobrado sai sempre dessa tabela.
- **Pacotes de horas** são opcionais (Ajustes → Pacotes de horas): horas vendidas de uma vez com preço fechado. Se houver, aparecem no atendimento, abaixo dos serviços. **Outro item**, no pé do atendimento, registra algo fora da tabela.
- **Avisar quando restar** (Ajustes → Controle de horas) começa em 10 minutos, porque a recreação é vendida de 30 em 30.

## 6b. Banco de horas (aba)

A aba **Banco de horas** mostra todas as crianças com o saldo, a **última contratação** (quantas horas, valor e dia; tocar nela abre o histórico do cliente) e a situação. A busca acha por criança, responsável, telefone ou CPF. Quem foi cadastrado e ainda não comprou aparece como **Sem horas**. **"Para renovar" vale só para quem comprou pacote de horas** e ficou sem um período guardado, e só depois que saiu: quem compra a recreação avulsa não entra na lista, e ninguém é chamado enquanto a criança está no espaço. No topo, **Para renovar** lista quem zerou ou está acabando, com o aviso pronto: "As horas do Miguel acabaram. Chame a Carla (mãe) no WhatsApp para ver se quer renovar." O botão **Chamar no WhatsApp** abre a conversa com a mensagem de renovação já escrita (com os pacotes e preços de Ajustes): é conferir e enviar. Depois de chamada, o cartão mostra "Já chamada hoje às 14:20 por Beatriz", para ninguém chamar duas vezes. Quando o iDFace registra uma saída que zera o pacote, a tela avisa na hora com o mesmo botão. O selo na aba mostra quantas crianças estão para renovar.

## 6c. Dicas do sistema

Na tela inicial, logo abaixo da lista de quem está no espaço, o próprio sistema sugere o que fazer agora, uma frase e um botão por dica:

- **Cobrança por atraso** de quem ainda está no espaço: "Alice passou do tempo comprado: nova cobrança por atraso. R$ 80 a receber de Juliana na saída."
- **Entradas canceladas** para a supervisão conferir.
- **Pacote de horas acabado** (só para quem comprou pacote e já saiu), com o botão **Mandar mensagem**. Nenhuma dica manda chamar o responsável por causa do tempo de quem está no espaço.
- **Família esperando resposta** no WhatsApp há mais de 3 minutos, com o botão **Responder**.
- **iDFace sem contato** ou **WhatsApp desconectado**: o que impede o sistema de trabalhar sozinho.
- **Agenda**: o que começa nas próximas 2 horas.
- **Cadastro**: criança sem rosto no iDFace, ou com o acesso bloqueado mas com saldo (só com o iDFace ligado em Ajustes).
- **Quem sumiu**: criança com horas guardadas que não vem há 14 dias ou mais.

Aparecem as 3 mais importantes, com "Ver as N dicas" para o resto. Cada dica pode ser dispensada no X (vale para o dia, naquele aparelho). Quem já foi chamado para renovar nos últimos 3 dias não volta a aparecer.

## 6d. Bonequinhos (Ajustes → Aparência)

Os bonequinhos (o ursinho, o balão, o cavalinho, o despertador, o guindaste da tela de entrar) seguem o aparelho:

- Em **Automático**, eles se mexem normalmente. Se o aparelho estiver com as animações do sistema desligadas (no Windows: *Mostrar animações no Windows* desligado, ou o ajuste "melhor desempenho"; no iPhone: *Reduzir Movimento*), eles ficam **quietos**, cada um parado na sua pose. Nesse caso aparece a dica "Os bonequinhos estão quietos neste computador", com o botão **Animar os bonequinhos**: um toque e eles voltam a se mexer naquele aparelho. O X da dica dispensa de vez.
- **Animados**: mexem sempre, mesmo com as animações do sistema desligadas.
- **Quietos**: ficam parados sempre.

A escolha vale só para aquele aparelho (fica guardada no navegador).

## 6e. Cancelar a entrada sem cobrar

Para quando a criança entra e desiste logo (chorou, não quis ficar, o responsável mudou de ideia) ou a entrada foi lançada errada.

- **Prazo**: até **9 minutos** depois da entrada (9 minutos cheios, contados do momento em que entrou), **o próprio atendente cancela**. O prazo corre à vista: na linha da criança, na lista do espaço, aparece o atalho com um **X e o tempo que falta** (8:42), e a ficha mostra até que horas dá. Quando o prazo acaba, o atalho some sozinho.
- **Motivo obrigatório**: a folha "Cancelar a entrada" só aceita com o motivo escrito (há atalhos: "A criança chorou", "O responsável desistiu"…). Sem motivo, não cancela.
- **O que deixa de ser cobrado**: a folha lista as vendas desta visita (as de hoje, feitas depois da última saída). Já vem marcada a **Recreação vendida junto com a entrada**; carrinho e o que foi comprado mais cedo ficam desmarcados (continuam cobrados), e dá para marcar se também não forem cobrados. O que for marcado sai das vendas do dia e do saldo da criança; se o cliente já pagou, o valor é devolvido. Nenhum tempo é descontado e a sapateira fica livre.
- **Fora do prazo**, só **Gestor ou Supervisor** cancela (pela ficha da criança), também com motivo, e fica anotado "fora do prazo".
- **A supervisão vê tudo**: cada cancelamento vira uma dica no alto da tela de Gestor e Supervisor ("João cancelou a entrada de Helena, R$ 80 não cobrado. Motivo: …") e, se a supervisora estiver com o sistema aberto, um aviso na hora. O botão **Conferir** abre **Entradas canceladas**: quem, quando entrou, quanto ficou, o que não foi cobrado, o motivo e quem cancelou; **Marcar como visto** registra quem conferiu. A mesma lista abre em "Hoje na recepção" e em Ajustes → Regra das horas. O cancelamento também aparece na linha do dia (com a venda marcada "cancelada") e no **histórico do cliente**.
- Se os avisos automáticos estão em "Entrada e saída", o responsável recebe no WhatsApp que a entrada foi cancelada e nada foi cobrado.
- **Ajustes → Regra das horas → Cancelar a entrada sem cobrar** muda o prazo (5, 9, 10 ou 15 min) ou deixa só para a supervisão.
- Com o iDFace ligado, o rosto lido nos 10 minutos seguintes ao cancelamento não vira entrada de novo (é a criança saindo).

## 6f. Tela inicial: quem está no espaço

A primeira tela (aba **Espaço**) é a **lista de todas as crianças que estão no espaço**, uma linha por criança, com o tempo correndo. Num computador de 1366 x 768 cabem 30 sem rolar, em três colunas; o resto aparece rolando um pouco.

- Cada linha mostra: a **sapateira**, o **nome** (com um sininho se há restrição ou observação), a **hora da entrada**, o **responsável**, o **tempo que a criança está no espaço** (correndo segundo a segundo) e **até que horas vai o tempo comprado**. Uma barrinha embaixo enche conforme o tempo passa.
- **Cores**: verde = no tempo; amarelo = acabando (faltam 10 min ou menos); vermelho = passou do tempo comprado (a tolerância está correndo, e a linha mostra "passou 3:12"). Quem ganhou cobrança por atraso fica com o selo **+ R$ 80**.
- De saída a lista vem por **Tempo**: quem está mais perto de estourar fica em cima. Dá para ordenar por **Entrada**, **Nome** ou **Sapateira**, e filtrar por *Acabando*, *Passou do tempo* e *Com cobrança*.
- **Tocar na criança abre a ficha**: responsável, telefones, documento, restrições, sapateira, hora da entrada, até quando vai o tempo, quanto debita se sair agora, cobranças por atraso, histórico e os botões.
- **Buscar** (criança, responsável, telefone ou CPF) filtra a lista e também acha quem tem cadastro e não está no espaço, com os botões **Entrada** (se tem um período guardado) e **Atender** (vender e dar entrada). A lista continua se atualizando sozinha com o cursor na busca.
- Nos primeiros 9 minutos a linha traz o atalho de **cancelar** com o tempo que falta; o botão **Saída** abre a folha da saída.
- Abaixo da lista ficam as dicas, os números do dia (horas vendidas, horas usadas, cobranças por atraso) e o atalho para o Banco de horas, onde está quem não está no espaço.

## 6g. Cobrança por atraso

Quando a criança continua no espaço depois do **tempo comprado mais a tolerância** (30 min + 9 = 39:00), o sistema registra sozinho **mais um período** (Recreação +30 min, R$ 80). Se ela continuar, depois de mais 30 min e da tolerância (1h09) nasce outro, e assim por diante.

- **Todo mundo fica sabendo**: aviso em todas as telas abertas ("Nova cobrança por atraso: Alice passou do tempo comprado · R$ 80 a receber na saída"), uma dica, o selo **+ R$ 80** na linha da criança e a linha "Nova cobrança por atraso: ficou além do tempo comprado" em Hoje na recepção. A venda entra no histórico do cliente marcada como *cobrança por atraso*.
- **Na saída**, a folha mostra o valor a receber e o botão vira **Recebi R$ 80 · dar saída**.
- Quem gera a cobrança é o servidor, a cada poucos segundos enquanto alguma tela estiver aberta, e de novo na saída: mesmo com tudo fechado, a conta fecha certa.
- **Saída esquecida** (a criança foi embora e ninguém marcou): Gestor ou Supervisor usa **Saída com outra hora** (na ficha ou na folha da saída), escreve a hora em que ela saiu de verdade e o motivo. O tempo é refeito e as cobranças por atraso que sobraram são desfeitas. Se a saída já foi marcada, a ficha tem **Corrigir** ao lado da visita (últimos dois dias). Tudo fica anotado em Hoje na recepção.
- **Na atualização para a 0.75**, quem já estava marcado como "no espaço" não ganha cobrança para trás: a regra vale para as entradas feitas depois de atualizar.
- O responsável não é avisado de nada disso pelo WhatsApp.

## 6h. Criança PCD e voucher

**Criança PCD: 50% de desconto na recreação.**

- No cadastro (e em **Editar**), marque **Criança PCD**. A partir daí todo tempo de recreação dela sai pela metade: a Recreação no atendimento (R$ 40 a cada 30 min), os pacotes de horas e a **cobrança por atraso**. Os carrinhos e outros produtos continuam com o preço cheio.
- O atendimento mostra o preço já com o desconto e o selo **PCD 50%**; a lista do espaço e a ficha mostram o selo **PCD**.
- Cada venda com desconto fica anotada ("Recreação · 30 min · PCD 50% · R$ 40") em Hoje na recepção e no histórico do cliente. **Marcar ou desmarcar PCD também fica anotado no dia**, com o nome de quem fez, porque muda o preço.

**Voucher: período de recreação sem cobrança.**

- No atendimento, abaixo dos serviços, a linha **Voucher** tem o mesmo + e −: cada toque no + põe um período (30 min) de Recreação sem cobrar. O **código do voucher (ou de onde ele veio) é obrigatório**.
- Dá para juntar na mesma venda um período por voucher e outros pagos (ex.: 30 min de voucher + 30 min pagos = 1 h, R$ 80).
- A venda fica anotada com o código ("Recreação · 30 min · voucher SHOP-0042 · R$ 0"). **Se o mesmo código já foi usado**, o sistema avisa na hora e marca na linha por quem e quando, para a supervisão conferir; ele não bloqueia, porque alguns vouchers não têm número único.
- Passou do tempo do voucher mais a tolerância, a cobrança por atraso é normal (R$ 80, ou R$ 40 se a criança for PCD).
- Em **Hoje na recepção** (bloco "Horas vendidas hoje"), um resumo mostra quantos vouchers e quantas vendas com desconto PCD houve nas últimas 24 h e quanto isso representou.

## 7. Atualizar

Botão **Atualizar** ao lado da versão. Ele baixa a versão nova do GitHub (ramo principal ou de trabalho, a mais nova), confere a versão dentro do arquivo e troca os arquivos de forma atômica. Não mexe em `~/playspace-dados/`. Se o GitHub demorar a refletir (cache de ~5 min), espere e toque de novo.

## 8. Se der errado

- **"Sem ligação com o servidor"** na tela de entrada: `api/api.php` não respondeu. Veja se a pasta `api/` subiu e se o PHP tem `pdo_sqlite`.
- **A Evolution recusou a chave**: a apikey está errada ou é de outra instância. Confira em Ajustes → WhatsApp.
- **iDFace "Sem contato"**: aparelho desligado, sem internet, ou o endereço push ficou errado no aparelho. Rode o conector de novo.
- **Mensagem do responsável não aparece**: o telefone no cadastro não bate com o número de onde veio (precisa dos últimos 8 dígitos iguais). Ajuste o telefone na ficha.
- **Os bonequinhos estão parados num computador**: as animações do sistema estão desligadas nele. Toque em **Animar os bonequinhos** na dica, ou escolha **Animados** em Ajustes → Aparência (seção 6d).
- Erros do servidor vão para o log de erros do PHP (cPanel → *Erros*) com o prefixo `[playspace]`.
