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

## 2b. Teste com 10 crianças (botão no rodapé)

Para ver como a tela fica com movimento, a gestora toca em **Teste: 10 crianças**, no rodapé de qualquer tela (ou em Ajustes → Dados de exemplo). Entram 10 crianças fictícias, todas no espaço:

- **10 no espaço agora**, com nomes, responsáveis e horários de entrada diferentes: umas acabaram de entrar (com o atalho de cancelar), outras estão há mais de uma hora; quase todas estão na **recreação avulsa** (1.15: acertam na saída, e duas já somam mais de um período), dois irmãos têm **pacote de horas**; uma é **PCD** e uma entrou com **voucher**; duas têm restrição anotada e uma está sem sapateira. Há **uma família com duas crianças** (aparecem juntas na lista).
- **2 crianças com pacote de horas** (ELISA e FELIPE, irmãos) e as outras na recreação avulsa, para ver o filtro **Pacotes / Horas avulsas**.
- **3 carrinhos fora da loja**: dois fusquinhas (um dentro do tempo e um que já passou) e um carrinho de bebê, para ver as linhas com o botão **Devolveu** (seção 6o).
- Vendas e registros do dia e compromissos na agenda.

Elas se comportam como crianças de verdade: o tempo corre, os períodos da recreação avulsa nascem sozinhos, dá para dar saída, cancelar e vender. Nada vai para o iDFace. Nas crianças de teste o relógio não para na hora de fechar a loja, para o teste funcionar a qualquer hora.

Para tirar, o mesmo botão vira **Tirar o teste**: saem as 10 crianças e tudo o que nasceu delas (vendas, cobranças, carrinhos fora, linhas do dia, agenda). Os cadastros de verdade ficam. Só a gestora coloca e tira o teste; a equipe vê as crianças de teste na tela enquanto elas estiverem lá.

Na prévia (sem servidor) o mesmo botão existe e faz a mesma coisa na própria tela. Até a 0.97 o teste colocava 30 crianças no espaço (e 4 fora); a loja pediu só 10.

**Quem ficou de pagar depois, no teste (1.02):** das dez crianças, a ANA CLARA e o PEDRO ficaram de pagar na saída, e o fusquinha do GAEL, na devolução. As outras pagaram na entrada. Desde a 1.06 a lista não mostra mais os selos PAGO / NÃO PAGO: o que ficou para pagar aparece na ficha ("A receber") e é cobrado na saída ou na devolução. As dez nascem na **unidade** do aparelho que pediu o teste (seção 6v).

## 3. Equipe e papéis (Ajustes → Equipe)

| Papel | O que faz |
|---|---|
| **Gestor** | Tudo: regra das horas, serviços, pacotes, produtos, equipe, iDFace. Pode remover crianças; **cadastro com horas guardadas, só o Gestor remove, e confirmando** (seção 6r). |
| **Supervisor** | Tudo da loja (horas, cadastro, agenda) e *vê* os Ajustes, mas não os altera. **Confere as entradas canceladas**, cancela uma entrada mesmo fora do prazo e **corrige a hora de uma saída esquecida**. |
| **Atendente** | Horas, cadastro de criança e agenda. Não vê Ajustes. Cancela uma entrada sem cobrar só dentro do prazo (9 min), escrevendo o motivo. |

A gestora cadastra cada pessoa com nome, login e senha inicial. Cada um troca a própria senha no avatar (canto inferior esquerdo no computador, aba **Conta** no celular) → *Trocar a senha*. Para redefinir a senha de alguém, a gestora edita a pessoa e digita uma senha nova.

## 4. WhatsApp (desligado na 0.89)

**A partir da 0.89 o módulo do WhatsApp fica desligado**, porque a loja não vai usar essa parte por agora. Com ele desligado:

- some a aba **WhatsApp** do menu;
- somem os cartões **WhatsApp da loja** e **Avisos automáticos** dos Ajustes;
- somem os botões **WhatsApp**, **Chamar para renovar**, **Mandar mensagem** e **Divulgar no WhatsApp** (comanda, histórico, Banco de horas e agenda);
- somem do sininho os avisos que mandavam chamar o responsável (pacote acabando, quem sumiu, família esperando resposta, WhatsApp desconectado);
- o cadastro pede só **Telefone**; e **nenhuma mensagem automática sai**, mesmo que a regra antiga mandasse.

Nada foi apagado: as conversas antigas e a conexão continuam guardadas. Para religar, basta pedir: são duas chaves no código (`COM_WHATS` na tela e `MODULO_WHATS` em `api/lib.php`) e tudo volta como era. O que vem abaixo vale para quando o módulo estiver ligado.

Usa a mesma Evolution API da Sophi CRM.

1. Toque em **WhatsApp da loja**. Preencha o **endereço do servidor** (já vem `http://143.95.164.89`), o **nome da instância** (`playspace`, uma instância só para a loja) e a **chave (apikey)** da Evolution. *Salvar conexão*.
2. Toque em **Conectar pelo QR Code**. No celular da loja: WhatsApp → *Dispositivos conectados* → *Conectar dispositivo* → aponte para o código. Ele renova sozinho a cada 20 s; quando conectar, a folha fecha e o chip fica verde.
3. O **webhook** (a Evolution avisar o sistema das mensagens que chegam) é configurado sozinho na hora em que conecta. O endereço aparece na mesma folha, caso precise conferir no painel da Evolution.

**Nada sai sozinho.** A partir da 0.75 os avisos automáticos ficam desligados (Ajustes → Avisos automáticos no WhatsApp → *Nenhum*): a loja não manda aviso de chegada, de saída, de tempo acabando nem de cobrança, para o responsável não ficar de olho no relógio. As dicas do sistema também não mandam chamar ninguém por causa do tempo de quem está no espaço, e as respostas prontas não falam de tempo nem de saldo. O WhatsApp fica para a equipe falar com a família quando quiser (e para emergência). Se a gestora ligar os avisos, eles dizem só que a criança chegou ou saiu.

As respostas dos responsáveis entram na aba **WhatsApp**, organizada por dia (Hoje, Ontem, escolher a data), com quem da equipe respondeu. A conversa é ligada pela **telefone do responsável** no cadastro da criança: por isso o telefone precisa estar certo (com DDD).

## 4b. Sem o iDFace: entrada e saída na tela

Enquanto a loja não usa o iDFace, a **leitura fica desligada** (Ajustes → iDFace; a versão 0.72 já desliga sozinha) e a equipe marca tudo na tela:

- No **atendimento** de quem não tem horas, o primeiro botão é **Dar entrada · Recreação avulsa** (1.15, seção 6g): um toque e a criança entra, sem comprar nada; o valor é acertado na saída. Quem tem horas guardadas tem, no mesmo lugar, **Dar entrada com o saldo**.
- Vendendo alguma coisa junto (um pacote, um produto, o carrinho), a opção **Dar entrada agora** já vem marcada: a venda é gravada e o tempo começa a contar. Para quem está comprando para outro dia, é só desmarcar.
- **Todo cliente tem o botão Entrada** na busca da tela inicial e na ficha: quem tem horas usa as horas; quem não tem entra na recreação avulsa.
- **Depois de cada entrada pela tela abre a pergunta da sapateira (1.16, seção 6a)**: um toque no número, ou **Sem sapateira**; em seguida sai o cupom da entrada.
- Quem está no espaço tem o botão **Saída** na linha da lista e na ficha. O toque abre o **Pagamento da saída** (seção 6r): de quem é, quanto ficou e como a visita é paga: com as horas do cliente ou, na recreação avulsa, com o valor do tempo que ficou e a forma de pagamento. O segundo toque confirma. São dois toques de propósito: com 40 crianças na lista, um toque errado não tira a criança errada. A saída libera a sapateira e avisa onde está o sapato.
- Qualquer pessoa da equipe (inclusive Atendente) marca entrada e saída; logo depois de cada uma aparece, por alguns segundos, o botão **Desfazer** (1.08: só o botão, sem texto; seção 6c). O Desfazer é para o toque errado e só vale nos instantes seguintes (o servidor aceita até 2 minutos); depois disso, tirar uma entrada é pelo **cancelamento com motivo** (seção 6e).
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

- **Tolerância entre um período e outro** (9 min de fábrica): até 39:00 continua valendo um período; até 1h09, dois. Para o responsável a equipe fala em 5 minutos; os 9 só aparecem em Ajustes.
- **Passou da tolerância**, o sistema conta sozinho mais um período. Na recreação avulsa ele entra na conta da saída; quem tem pacote usa as horas dele e, se elas acabaram, o período vira cobrança **além do pacote** (seção 6g).
- **A loja fecha às** (22h de fábrica): depois dessa hora o tempo para de contar, para uma saída esquecida não virar cobrança a noite toda.
- **Marcar "acabando"** (10 min): quem entrou com pacote fica em amarelo na lista quando as horas dele estão no fim; nada é enviado ao responsável. Na recreação avulsa não há prazo.
- **Cancelar a entrada sem cobrar** (seção 6e) e **Quando o pacote zerar** continuam como antes. Uma linha no fim do cartão mostra na prática quanto cada tempo debita. Os preços ficam em **Serviços e preços** (seção 6a).

## 6a. Cadastro, cliente e histórico

**Cadastrar** (botão de mais) pede primeiro o **responsável** e depois a **criança**, nesta ordem: documento do responsável (**CPF ou RNE**, para estrangeiro: o seletor fica ao lado do rótulo), nome do responsável, telefone (WhatsApp) e um **outro telefone** opcional; depois o nome da criança, a marca **Criança PCD** (seção 6h) e as **restrições e observações**. **A sapateira não é mais escolhida no cadastro (1.16):** ela é perguntada a cada entrada (veja "Sapateiras", logo abaixo), então quem só vai comprar uma festa, por exemplo, cadastra e segue direto. **O cadastro só fecha com quatro dados** (marcados com asterisco): o documento do responsável (CPF válido, ou RNE), o nome do responsável, **um telefone com DDD** (qualquer um dos dois campos) e o nome da criança. Enquanto falta algum, a linha acima do botão diz o que falta ("Falta o CPF (ou RNE), um telefone…") e, se a pessoa tentar cadastrar assim mesmo, os campos que faltam ficam marcados em vermelho com a explicação embaixo. O servidor confere a mesma coisa. Ao **editar**, nenhum desses dados pode ser apagado; um cadastro antigo, feito antes desta regra, continua podendo ser salvo como está (para anotar uma alergia, por exemplo), mas o que for mexido precisa ficar certo. O cadastro **não vende pacote** (as horas entram depois, pelo botão **Vender**) e **não pede foto nem rosto**: quando for usar o iDFace, a foto e o rosto são feitos na ficha da criança. Se só o segundo telefone for preenchido, ele vira o principal. Mensagem que chega no WhatsApp é reconhecida por qualquer um dos dois números.

- **Telefone internacional (1.16)**: no **Outro telefone** há o botão **Internacional**. Um toque põe o **+** no campo, e a equipe escreve o código do país e o número (ex.: +1 305 555 0123); tocar de novo tira o +. Número com + **não precisa de DDD** e vale com 8 números ou mais; sem o +, a regra continua a mesma (DDD e número). Se o cliente só tem o número de fora, basta preencher o Outro telefone: ele vira o telefone principal. O + escrito à mão vale do mesmo jeito, nos dois campos, e o servidor confere a mesma coisa. Com o número incompleto, a linha de baixo diz "Falta o telefone internacional completo".
- **Data de nascimento (1.11)**: ao lado do nome da criança fica o campo **Nascimento**, que é **opcional**. A equipe escreve só os números (03102021) e as barras entram sozinhas (03/10/2021); **a idade aparece na frente, na hora** ("5 anos"; para bebê, "8 meses"), sem ninguém fazer conta. Dali em diante a idade sai sempre da data: no alto do atendimento, na ficha, no histórico e na via da loja do cupom, e **muda sozinha no aniversário**. **A data vale para qualquer idade (1.17):** escrita uma data real, a idade aparece na hora, também a de um adulto ("36 anos"); até a 1.16 só valia até 17 anos, e uma data de adulto mostrava "confira a data". Só não passa a data que não vale, e o aviso ao lado do campo diz por quê: **"não existe"** (31/02), **"no futuro"** ou **"confira o ano"** (mais de 120 anos). Ao tentar cadastrar assim, a frase completa aparece ("Confira a data de nascimento: esse dia não existe"). Sem a data o cadastro fecha do mesmo jeito. No **Editar cadastro** (Gestor) a data pode ser posta, trocada ou apagada; o campo **Idade** (em anos) só aparece enquanto não há a data, para quem souber só a idade. No servidor, o banco passa para a versão 26 (coluna `nascimento` em `criancas`); os cadastros que já existem ficam sem a data, com a idade que tinham.
- **Restrições** (alergia, se pode ou não fazer pintura no rosto, se pode ou não mexer no cabelo) ficam no campo de observações e são opcionais. Os atalhos embaixo do campo só escrevem mais rápido: tocar de novo num atalho tira o que ele escreveu (inclusive a alergia), e **Sem restrição** limpa tudo. O texto é livre e pode ser mudado depois em **Editar**. Elas aparecem em destaque na ficha e no histórico.
- **Cliente já cadastrado**: ao completar um CPF (ou RNE) que já existe, o cadastro avisa na hora: mostra quem é o responsável e as crianças dele (última vinda, horas consumidas, horas disponíveis), preenche os dados do responsável e **abre sozinho o histórico do cliente ao lado**, sem precisar tocar em nada (no celular, aparece o aviso "Este CPF já tem cadastro" com o atalho **Ver o histórico**). Se o documento for trocado, o histórico fecha. Para cadastrar um irmão, é só escrever o nome da nova criança. A mesma criança não entra duas vezes no mesmo CPF.
- **Os três primeiros números do CPF já puxam o cliente**: no cadastro, ao escrever os **três primeiros números** do CPF, aparece a lista de todos os clientes cujo CPF começa assim: o nome do responsável, as crianças dele e o CPF só com o começo e o fim ("529.•••.•••-25", porque outra pessoa pode estar olhando a tela). **Um toque escolhe**: o CPF é preenchido inteiro e o cadastro segue como "Cliente já cadastrado" (os dados do responsável, as crianças e o histórico ao lado). A cada número a mais, a lista afina; aparecem até 8 de cada vez. Vale para CPF (o RNE continua sendo escrito inteiro).
- **Copiar o CPF**: o botão dos dois quadradinhos copia o documento com um toque ("CPF copiado"), para colar onde precisar sem anotar de novo. Ele fica **dentro do campo do CPF** no cadastro, ao lado do CPF no alto da **ficha** da criança e no alto do **histórico do cliente**.
- **Os dados ficam salvos, e o cliente que volta não escreve nada de novo**: no cartão "Cliente já cadastrado", cada criança dele é um botão, com quando veio, o saldo e o aviso "tem observação". **Um toque na criança abre o atendimento dela.** Se for outra criança do mesmo responsável, basta escrever o nome dela: os dados do responsável já vêm preenchidos. Nada disso se perde nas atualizações: cadastros, vendas, visitas e saldos ficam no banco de dados da loja.
- **Histórico do cliente**: abre numa aba à direita (no celular, na tela toda) e junta tudo do responsável: quanto pagou ao todo, horas compradas, consumidas e disponíveis; cada criança com o saldo, as restrições e os botões **Vender** e **Chamar para renovar**; **todas as compras** (pacote, o que veio junto, carrinho e outros produtos, valor, quem vendeu) e **todas as visitas**. Abre pelo botão **Histórico** no cadastro, na ficha da criança ou tocando na "Última contratação" do Banco de horas. O cliente é reconhecido pelo CPF ou pelo RNE; sem documento, pelo WhatsApp.
- **Sem horas, sem entrada**: com a regra "Quando o pacote zerar → Bloquear o acesso sozinho" (o padrão), a criança recém-cadastrada começa com o acesso **bloqueado** no iDFace e é liberada sozinha na primeira venda de horas. Vender só um produto (carrinho) não libera. Desfazer uma venda que deixa o saldo em zero bloqueia de novo.

**A ficha da criança tem só o atendimento (1.00):** **Saída** (ou **Entrada**), **Vender**, **Histórico** e **Cupom**, mais a sapateira e a observação. Saíram "Saída com outra hora", "Tirar a foto" (volta sozinha se o iDFace for ligado), "Editar" e "Remover".

**O responsável é a referência principal (1.16)**, como no sistema anterior da loja: em toda lista e em toda folha o **nome do responsável vem primeiro, em destaque, e a criança vem em segundo**. Vale para a lista da tela inicial (coluna **Responsável** antes da coluna **Criança**; no celular, o responsável em cima e a criança embaixo), a comanda (o título é o responsável; a criança, com a idade, vem na primeira linha), o atendimento, a festa, a busca, "Saíram há pouco", "Falta finalizar", Hoje na recepção, o Banco de horas, o mapa das sapateiras e Ajustes → Cadastros. As listas em ordem alfabética seguem o nome do responsável. A bolinha com as letras (ou a foto) continua sendo a da criança, e o **OBS** continua ao lado do nome dela. Nas frases ("HELENA entrou", "Saída de HELENA") o nome continua sendo o da criança, que é quem entra e sai.

**A ficha mais limpa (1.02):** de cima para baixo ficam só a criança (o responsável está no título) e o telefone, a linha do que falta receber, com **Receber** (só quando há; desde a 1.06 não existe mais o selo **Pago**), o relógio ("entrou 14:10 · até 14:40", o tempo que resta e a barra; na recreação avulsa, 1.15, o tempo correndo e "Recreação avulsa · até agora 1h · R$ 160 · acerta na saída") e a fileira de botões: **Saída** (ou **Entrada**, para quem está fora), **Vender**, **Histórico**, **Reimprimir cupom**, **Sapateira** e, no prazo, **Cancelar entrada**. Saíram as frases compridas ("Entrou às… e está há… Se sair agora, debita…", "é onde está o sapato dela", "As horas não expiram…"). Para quem tem pacote e ele acabou, a linha curta "Além do pacote · R$ 80 a receber". O **OBS** de quem não tem observação é um botão na fileira; com observação, continua a caixa vermelha no alto. A conta de horas (comprou, usou, saldo) só aparece para quem tem horas guardadas ou pacote, e a lista de visitas, só quando há visita.

**Editar ou remover um cadastro é só do Gestor**, em **Ajustes → Cadastros**: a busca acha por responsável, criança, telefone ou CPF; um toque abre o cadastro para mudar (nome, data de nascimento, responsável, telefones, documento, criança PCD, observações) e, no pé, **Remover este cadastro**. Atendente e Supervisor não mudam cadastro, nem pela tela, nem pelo servidor. A observação e a sapateira continuam com toda a equipe.

**Sapateiras** (1 a 44): a criança tira o sapato para brincar e ele fica numa sapateira numerada.

- **A sapateira é de cada visita (1.16).** Toda vez que a criança entra pela tela (o botão **Dar entrada** do atendimento, a **Entrada** da busca ou da comanda, ou a venda com "Dar entrada agora"), abre a pergunta **Sapateira**: de quem é (o responsável e a criança), a grade de números e o botão **Sem sapateira**. **Um toque no número** marca e segue para o cupom, que já sai com a sapateira na via da loja. **Sem sapateira** segue do mesmo jeito, sem número (bebê de colo, quem não vai tirar o sapato); fechar a folha é o mesmo que seguir sem. Como ela não é mais escolhida no cadastro, a venda de uma festa (ou de um produto) não passa por sapateira nenhuma.
- As que estão **em uso aparecem hachuradas** e não podem ser escolhidas (tocar numa delas mostra com quem está e oferece **Liberar**, para o caso de uma esquecida). **A grade mostra de 1 a 24** e a seta **Mostrar todas** abre o resto (1.00); se houver alguma sapateira acima da 24 em uso, a grade já abre inteira, para nada ficar escondido. No mapa de Ajustes aparecem sempre todas. Se dois aparelhos tocarem no mesmo número, o segundo recebe o aviso ("A sapateira 7 já está com…"), a grade se atualiza e a pergunta continua na tela.
- O **Desfazer** da entrada continua valendo com a pergunta na tela (ela fecha e o cupom não sai) e depois dela (a sapateira marcada fica livre de novo).
- Para marcar depois, ou trocar: na **comanda** de quem está no espaço, o botão **Sapateira** (com o número, quando já tem). Com o iDFace ligado a entrada não passa pela tela, então a sapateira é marcada por ali.
- O número aparece na comanda, na folha da saída (coluna Sapateira) e na via da loja do cupom. Na **saída**, a sapateira **fica livre sozinha** e a linha do dia diz onde estava o sapato ("sapato na sapateira 12"). **Na visita seguinte nada vem marcado: a pergunta abre de novo.**
- **Ao atualizar para a 1.16**, o banco passa para a versão 28 e solta as sapateiras que tinham ficado presas a quem está fora do espaço (escolhidas no cadastro e nunca usadas). Quem está no espaço na hora da atualização continua com a sua.
- **Ajustes → Sapateiras** mostra o mapa (quem está com qual), libera uma esquecida e muda a quantidade (44 por padrão).

**Atendimento e serviços**: ao terminar o cadastro, o sistema abre o **atendimento** da criança: no meio, os serviços com o preço e o **total a pagar**; ao lado (em tela larga), os dados e o histórico de compras do cliente. O mesmo atendimento abre pelo botão **Vender** (ficha, Banco de horas, histórico).

- **O que o atendimento mostra, de cima para baixo**: quem é o responsável e a criança (1.16: o responsável em destaque); o **OBS** da observação (vermelho e aceso quando há; o texto só aparece com um toque, seção 6q); as **horas do cliente** em três números (**comprou**, **usou** e **saldo**, em destaque); depois o **tempo de recreação** avulso (com o voucher), os **pacotes de horas**, os **outros serviços** (carrinhos), os **produtos** e o **outro item**.
- **Dar entrada com o saldo**: quando a criança está fora do espaço e tem pelo menos um período guardado, aparece o botão "Dar entrada com o saldo (4h30)": ela entra usando as horas que já tem, sem vender nada. Se a equipe escolher algo para vender, o botão some e a entrada vai junto com a venda (a caixa "Dar entrada agora").
- Ao marcar tempo ou pacote, o atendimento diz como o saldo fica: "Entra 5h de recreação no saldo: fica com 9h30".

- Os serviços de fábrica são **Recreação** (R$ 80 a cada meia hora), **Carrinho fusquinha** (R$ 15 a cada 30 min) e **Carrinho de bebê** (R$ 30, das 10h às 22h). Em cada linha, o **+** põe mais um período (ou mais uma unidade) e o **−** tira; o valor da linha e o total aparecem na hora. Com algo escolhido, o botão do pé vira **Pagamento · R$ …**: um toque leva para a tela de pagamento (seção 6p), que grava tudo de uma vez; o aviso tem **Desfazer**.
- O tempo de **Recreação** vendido entra no saldo de brincar da criança (meia hora conta) e libera o acesso dela; os carrinhos ficam só anotados na venda, com a duração.
- **Ajustes → Serviços e preços** muda o nome, o valor e a forma de cobrar (valor único ou a cada período de tantos minutos), cria serviços novos (uma meia, um lanche) e diz qual deles é tempo de brincar. O valor cobrado sai sempre dessa tabela.
- **Pacotes de horas**: no atendimento ficam numa linha só, **Lista de pacotes**, para a tela não ficar cheia. Um toque abre a lista (um pacote por linha: o tempo, o nome e o valor); outro toque escolhe, a lista fecha e o pacote escolhido fica à vista na própria linha. Para trocar, é abrir de novo. A tabela completa, com o valor da hora, fica no botão **Pacotes** da tela inicial (seção 6j).
- **Produtos** (Amigurumi, Maleta, Kit laço…): aparecem logo depois dos pacotes; um toque põe um na venda (seção 6l). **Outro item**, no pé do atendimento, registra algo fora da tabela.
- **Avisar quando restar** (Ajustes → Controle de horas) começa em 10 minutos, porque a recreação é vendida de 30 em 30.

## 6b. Banco de horas (aba)

A aba **Banco de horas** é a lista de todos os cadastros, em ordem alfabética do responsável (1.16), uma linha por criança:

- **o responsável e a criança** (um toque abre a ficha);
- o **saldo de horas** (**as horas não expiram**, seção 6r). Quem ainda não comprou aparece com **0h**;
- a **última compra**: o que foi, o valor e o dia (um toque abre o histórico do cliente);
- o relógio (histórico) e o botão **Vender**, que vira **Continuar** quando o atendimento ficou pela metade.

**Nada corre nesta tela** (0.99): o tempo de quem está no espaço fica na tela inicial; aqui a criança só ganha o selo **No espaço**. Saíram da linha os números correndo, a barrinha, o "tempo comprado até" e o telefone (que fica na ficha, a um toque). As colunas seguem a largura da própria lista: com o painel da direita ou o histórico abertos, ou no tablet, a última compra some da linha em vez de um texto passar por cima do outro; no celular ficam o nome, o saldo e o Vender.

A busca acha por responsável, criança, telefone ou CPF. O filtro **Pacote acabou** mostra quem comprou pacote de horas e ficou sem um período guardado (a linha ganha a borda vermelha e o selo **Pacote acabou**). *Com o módulo do WhatsApp ligado (seção 4):* **"Para renovar" vale só para quem comprou pacote de horas** e ficou sem um período guardado, e só depois que saiu: quem compra a recreação avulsa não entra na lista, e ninguém é chamado enquanto a criança está no espaço. No topo, **Para renovar** lista quem zerou ou está acabando, com o aviso pronto: "As horas do Miguel acabaram. Chame a Carla (mãe) no WhatsApp para ver se quer renovar." O botão **Chamar no WhatsApp** abre a conversa com a mensagem de renovação já escrita (com os pacotes e preços de Ajustes): é conferir e enviar. Depois de chamada, o cartão mostra "Já chamada hoje às 14:20 por Beatriz", para ninguém chamar duas vezes. Quando o iDFace registra uma saída que zera o pacote, a tela avisa na hora com o mesmo botão. O selo na aba mostra quantas crianças estão para renovar. **Com muitos cadastros (1.01)**, a lista desenha os primeiros 150 clientes e avisa no pé; os outros vêm pela busca.

## 6c. Avisos do sistema (o sininho no alto da tela inicial)

O sistema continua avisando o que vale olhar agora, mas os avisos **não ocupam a tela inicial**: ficam no botão do **sininho**, no alto, com a contagem (em vermelho quando há algo importante). Um toque abre a lista, uma frase e um botão por aviso:

- **Pacote que acabou** com a criança ainda no espaço: "As horas do pacote de ALICE acabaram: 0h30 a mais. R$ 80 a receber de JULIANA na saída." A recreação avulsa correndo não é aviso (1.15): é a conta normal da visita.
- **Entradas canceladas** para a supervisão conferir.
- **iDFace sem contato**: o que impede o sistema de trabalhar sozinho (só com o iDFace ligado).
- **Agenda**: o que começa nas próximas 2 horas.
- **Cadastro**: criança sem rosto no iDFace, ou com o acesso bloqueado mas com saldo (só com o iDFace ligado em Ajustes).

Cada aviso pode ser dispensado no X (vale para o dia, naquele aparelho). Sem avisos, o sininho some.

**A faixa do pé da tela (1.08).** A faixa escura que aparecia embaixo a cada atividade saiu:

- **O que deu certo não é mais anunciado**: entrada, saída, venda, pagamento recebido, cadastro salvo, regra salva, unidade trocada… A tela já mostra o resultado (a folha fecha, a lista muda).
- **O que acontece sozinho também não**: o pacote que acaba, a entrada ou a saída dada em outro aparelho, a entrada cancelada por um atendente. A lista mostra, e o sininho guarda o pacote que acabou e o cancelamento a conferir (ele fica vermelho na hora, em todas as telas).
- **Desfazer**: quando a ação tem como desfazer (entrada, saída, venda, recebimento, devolução, voucher, apagar um pacote ou um produto, a limpeza…), aparece no pé da tela **só o botão Desfazer**, sem texto, por 6 segundos. Parando o mouse nele, a dica diz o que será desfeito.
- **O que pede atenção continua aparecendo, com o texto**: um erro; o que falta para continuar ("Como recebeu R$ 80? Toque na forma de pagamento", "Escreva o motivo…"); uma recusa ("Já existe uma unidade com esse nome", "Só o gestor muda a regra"); o alerta do 2º voucher do dia da mesma criança; a entrada cancelada, para quem cancelou (com o que devolver ao cliente); o resumo da importação; e a observação pedida no OBS.
- **Copiar o CPF** mostra um ✓ no próprio botão, por um instante.

Com o módulo do WhatsApp desligado (seção 4), **não aparecem** os avisos que mandavam chamar o responsável: pacote de horas acabado, quem sumiu, família esperando resposta e WhatsApp desconectado. Eles voltam junto com o módulo.

## 6d. Bonequinhos (Ajustes → Aparência)

Os bonequinhos (o ursinho, o balão, o cavalinho, o despertador, o guindaste da tela de entrar) seguem o aparelho:

- Em **Automático**, eles se mexem normalmente. Se o aparelho estiver com as animações do sistema desligadas (no Windows: *Mostrar animações no Windows* desligado, ou o ajuste "melhor desempenho"; no iPhone: *Reduzir Movimento*), eles ficam **quietos**, cada um parado na sua pose. Nesse caso aparece a dica "Os bonequinhos estão quietos neste computador", com o botão **Animar os bonequinhos**: um toque e eles voltam a se mexer naquele aparelho. O X da dica dispensa de vez.
- **Animados**: mexem sempre, mesmo com as animações do sistema desligadas.
- **Quietos**: ficam parados sempre.

A escolha vale só para aquele aparelho (fica guardada no navegador).

**Sem o passarinho no botão de mais (1.06):** o botão de cadastrar (o **+** do trilho da esquerda, no computador, e o botão redondo do canto, no celular) é só o **+**. O passarinho que espiava por trás dele e voava saiu.

## 6e. Cancelar a entrada sem cobrar

Para quando a criança entra e desiste logo (chorou, não quis ficar, o responsável mudou de ideia) ou a entrada foi lançada errada.

- **Prazo**: até **9 minutos** depois da entrada (9 minutos cheios, contados do momento em que entrou), **o próprio atendente cancela**. O prazo corre à vista: na linha da criança, na lista do espaço, aparece o atalho com um **X e o tempo que falta** (8:42), e a ficha mostra até que horas dá. Quando o prazo acaba, o atalho some sozinho.
- **Motivo obrigatório**: a folha "Cancelar a entrada" só aceita com o motivo escrito (há atalhos: "A criança chorou", "O responsável desistiu"…). Sem motivo, não cancela.
- **O que deixa de ser cobrado**: a folha lista as vendas desta visita (as de hoje, feitas depois da última saída). Já vem marcada a **Recreação desta entrada** (na recreação avulsa, 1.15, é o período que o sistema lançou: ele aparece travado, porque sai sempre junto com a entrada cancelada); carrinho e o que foi comprado mais cedo ficam desmarcados (continuam cobrados), e dá para marcar se também não forem cobrados. O que for marcado sai das vendas do dia e do saldo da criança; se o cliente já pagou, o valor é devolvido. Nenhum tempo é descontado e a sapateira fica livre.
- **Fora do prazo, ninguém cancela** (1.00): quando o prazo acaba, a opção some da ficha para todos, inclusive Gestor e Supervisor, e o servidor recusa o cancelamento. O período é cobrado, qualquer que seja o motivo. **Na ficha (1.02)** o cancelamento é só o botão **Cancelar entrada · 6:57**, com o tempo que ainda falta; quando o prazo acaba o botão some, sem deixar aviso nenhum.
- **A supervisão vê tudo**: cada cancelamento vira uma dica no alto da tela de Gestor e Supervisor ("João cancelou a entrada de Helena, R$ 80 não cobrado. Motivo: …") e o sininho fica vermelho na hora em todas as telas da supervisão (1.08: sem o aviso no pé da tela). O botão **Conferir** abre **Entradas canceladas**: quem, quando entrou, quanto ficou, o que não foi cobrado, o motivo e quem cancelou; **Marcar como visto** registra quem conferiu. A mesma lista abre em "Hoje na recepção" e em Ajustes → Regra das horas. O cancelamento também aparece na linha do dia (com a venda marcada "cancelada") e no **histórico do cliente**.
- Se os avisos automáticos estão em "Entrada e saída", o responsável recebe no WhatsApp que a entrada foi cancelada e nada foi cobrado.
- **Ajustes → Regra das horas → Cancelar a entrada sem cobrar** muda o prazo (5, 9, 10 ou 15 min) ou desliga o cancelamento (**Não cancela**).
- Com o iDFace ligado, o rosto lido nos 10 minutos seguintes ao cancelamento não vira entrada de novo (é a criança saindo).

## 6f. Tela inicial: a lista

A primeira tela (aba **Espaço**) mostra **todo mundo numa lista, uma linha por cliente**, como uma planilha:

- a **situação**: bolinha verde = no tempo; amarela = acabando (faltam 10 min ou menos); vermelha = passou do tempo comprado. **Amarelo e vermelho só existem para quem entrou com pacote** (1.15): na recreação avulsa não há prazo, e a bolinha fica verde. Desde a 1.05 a linha não fica mais toda vermelha: só o relógio e o "passou 7 min" ficam em vermelho, o que já basta para ver quem passou. No que saiu da loja (carrinho, fusquinha), no lugar da bolinha fica o desenho do carrinho;
- o **responsável**, em destaque: desde a 1.16 ele é a referência principal e vem primeiro, como no sistema anterior da loja;
- a **criança**, com o **OBS** vermelho quando há observação (o texto só aparece com um toque, seção 6q). O valor a receber **não aparece na linha** (nem o da recreação avulsa, nem o do tempo além do pacote): fica na ficha e no pagamento da saída (seção 6r), para a equipe não confundir;
- o **serviço**: RECREAÇÃO, com o selo **AVULSA** ou **PACOTE**; ou o que saiu da loja (CARRINHO FUSQUINHA, CARRINHO DE BEBÊ);
- a **unidade** em que a criança está (1.06; no lugar da coluna Pagamento, que tinha os selos PAGO / NÃO PAGO): ANÁLIA FRANCO, MORUMBI… É a unidade do aparelho em que a entrada foi dada (seção 6v); no carrinho, a de onde ele saiu. Sem unidade marcada, um traço. No celular a unidade fica no alto da tela e só aparece na linha quando a lista mistura unidades;
- a hora em que **entrou**;
- o **tempo** correndo e, para quem tem pacote, **até que horas vai** ("até 13:46") ou **quanto passou** ("passou 7 min"); na recreação avulsa, só o tempo correndo;
- o botão **Saída** (ou **Devolveu**, no que saiu da loja).

**Quem tem pacote e está mais perto do fim das horas vem primeiro; depois, quem está na recreação avulsa, de quem entrou há mais tempo para quem acabou de entrar.** As linhas do mesmo responsável (irmãos, ou a criança e o carrinho dela) ficam **juntas**, com um elo ao lado do nome dele. O sistema junta quem tem o mesmo CPF (ou RNE) do responsável; em cadastros sem documento, quem tem o mesmo telefone.

**Em cima da lista:**

- os **filtros por serviço**, com a contagem de cada um: **TODAS**, **RECREAÇÃO** e um para cada coisa que sai da loja e volta (seção 6o). Um toque mostra só aquele serviço; **SAÍRAM HÁ POUCO** aparece no fim quando há alguém no prazo de reabrir a visita (seção 6m);
- a **busca** (responsável, criança, telefone ou CPF). Ela filtra a lista e também acha quem tem cadastro e não está no espaço (em ordem do nome do responsável), com os botões **Entrada** (um toque: quem tem horas usa as horas; quem não tem entra na recreação avulsa; em seguida abre a pergunta da sapateira, seção 6a) e **Atender** (vender e dar entrada).

**Tocar na linha abre a comanda** (a ficha), com tudo: responsável, telefones, documento, o OBS, o que está fora da loja (com o **Devolveu**), **sapateira**, hora da entrada, até quando vai o tempo, quanto debita se sair agora, os três números (**comprou**, **usou** e **saldo**, para quem tem horas), o **cancelar a entrada**, o que a visita soma até agora, o histórico e os botões.

**A lista se ajusta ao espaço**: no computador, todas as colunas; em tela menor (ou com o histórico do cliente aberto ao lado), a criança vai para baixo do responsável e a hora da entrada sai; no celular, a segunda linha fica "HELENA · AVULSA" (ou "MIGUEL · FUSQUINHA").

O resto da tela continua igual:

- a faixa **Falta finalizar** só aparece quando alguém ficou com o cadastro ou o atendimento pela metade (veja 6i);
- **no alto**: quantas crianças estão no espaço, quantas passaram do tempo e **quantos carrinhos há para devolver**; o **sininho** dos avisos (só quando há algum, seção 6c), e **Pacotes e produtos**. O botão "Entrada na tela" saiu na 0.98: a entrada é dada no atendimento, na ficha ou pela busca (seção 4b), e o iDFace continua em Ajustes;
- **Atividades da semana**: em tela larga (1400 px ou mais) ficam na coluna da direita, por dia, sempre à vista. Em tela menor e no celular viram **uma linha só** embaixo da lista; um toque abre os dias;
- **no pé**, dois atalhos: **Banco de horas** (quem não está no espaço) e **Hoje na recepção** (cada entrada, saída, venda, pagamento, devolução, cancelamento e mudança de estoque do dia). Os números do dia estão no alto de **Hoje na recepção**.

**O bloco do responsável (1.00):** as crianças do mesmo responsável, e os carrinhos que elas pegaram, ficam sempre juntos, dentro de um **contorno vermelho**: é um bloco só, para ver de longe que são da mesma família. Desde a 1.07, a **Saída** de qualquer criança do bloco abre a conta de todas juntas, com o **Tirar** em cada uma (seção 6r). O vínculo é pelo responsável (o CPF dele; sem documento, o telefone). O bloco entra na lista no lugar da linha mais urgente dele. No **Banco de horas** os irmãos também ficam juntos, no mesmo contorno. As três colunas por serviço, que eram opção de cada aparelho, saíram: elas separavam a criança do carrinho do mesmo responsável. O aparelho que estava em colunas volta sozinho para a lista. **A coluna Pagamento (1.02)** mostra em cada linha o selo PAGO ou NÃO PAGO (seção 6p).

## 6g. Recreação avulsa: acerto na saída (1.15), e o tempo além do pacote

**O cliente sem pacote não compra meia hora na entrada.** Ele deixa a criança, sabendo o valor (R$ 80 a cada meia hora, de fábrica), e **acerta na saída o tempo que ela ficou**. Na tela:

- **A entrada é um toque.** No atendimento de quem não tem horas, o primeiro botão é **Dar entrada · Recreação avulsa**, com o valor embaixo ("R$ 80 a cada meia hora · acerta na saída"). Não existe mais a linha de vender meia hora com − e +. O mesmo toque está no botão **Entrada** da ficha e da busca. Quem tem horas guardadas continua com **Dar entrada com o saldo**, e os pacotes continuam na lista logo abaixo.
- **O cupom de entrada** diz "Entrada 14:02 · acerto na saída" e "RECREAÇÃO avulsa · R$ 80,00 a cada 30 min" (não há mais "até 14:32").
- **Na lista**, a criança fica com o selo **AVULSA** e o tempo correndo. **Não tem "até", nem amarelo, nem "passou do tempo"**: quem está na recreação avulsa não tem prazo.
- **Na ficha**, embaixo do relógio: "Recreação avulsa · até agora 1h · R$ 160 · acerta na saída". O valor acompanha o relógio.
- **Na saída**, a conta é uma linha só: "RECREAÇÃO avulsa · 1h30 · ficou 1h12 · R$ 240". Escolhida a forma, **Recebi R$ 240 no Pix · dar saída**. O cupom da saída repete a linha, e Hoje na recepção anota "Ficou 1h12 · 1h30 debitados · recreação avulsa: R$ 240 · recebido: Pix".

**Como o valor é contado:** em períodos inteiros (meia hora), com a tolerância entre um e outro (9 min de fábrica; seção 6). Até 39 min, um período (R$ 80); até 1h09, dois (R$ 160); até 1h39, três (R$ 240). A criança PCD paga a metade; o voucher cobre meia hora (seção 6h). O único jeito de não cobrar é cancelar a entrada no prazo (seção 6e).

**Por baixo**, o sistema lança sozinho cada período que a visita passa a precisar (uma venda *RECREAÇÃO · 0h30 · avulsa*, a receber), a cada poucos segundos enquanto houver tela aberta, e de novo na saída: é o que aparece no histórico do cliente. Esses períodos **não viram linha em Hoje na recepção** nem aviso no sininho (são a conta normal da visita). Desfazer a entrada, ou cancelá-la, tira o que foi lançado.

**A saída confere o valor.** A tela manda para o servidor o total que mostrou; se a visita ganhou mais um período com a folha aberta, o servidor não dá a saída e avisa "O valor a receber mudou para R$ 160. Confira e confirme de novo": a folha já mostra a conta nova, e o segundo toque passa. Assim ninguém sai com período sem receber. O relógio da tela segue o do servidor: todos os aparelhos mostram o mesmo tempo, mesmo com o relógio de um deles errado.

**Passar do tempo só existe para quem tem pacote.** Quem entrou com horas de pacote tem prazo (as horas dele): a linha mostra "até 15:40", fica amarela quando falta pouco e, quando as horas acabam, vermelha, com "passou 4 min". Passada a tolerância, nasce a cobrança **além do pacote** (RECREAÇÃO +0h30, R$ 80), e aí todo mundo fica sabendo: o sininho avisa ("As horas do pacote de ALICE acabaram: 0h30 a mais. R$ 80 a receber de JULIANA na saída"), entra a linha "As horas do pacote acabaram" em Hoje na recepção, a ficha mostra "Além do pacote · R$ 80 a receber" e a saída traz a linha **Além do pacote · 0h30**. O cartão **além do pacote**, no alto de Hoje na recepção, soma só isso.

- **Saída esquecida** (a criança foi embora e ninguém marcou): dê a saída normalmente. Depois, **só o Gestor** acerta a hora: na ficha da criança, **Corrigir** ao lado da visita (últimos dois dias), com o motivo. O tempo é refeito e os períodos que sobraram são desfeitos; fica anotado em Hoje na recepção.
- **A loja que usa o iDFace** continua vendendo o período no atendimento (é a venda que libera o rosto na porta); o que passar dele entra como recreação avulsa na saída.
- **Quem já estava no espaço na atualização** continua valendo: quem tinha comprado meia hora usa essa meia hora, e o que passar dela é acertado na saída, já sem a palavra "atraso".
- O responsável não é avisado de nada disso pelo WhatsApp.
- No servidor: a ação `entrada` não exige mais um período comprado; as vendas que nascem sozinhas levam `avulsa` ou `além do pacote` no detalhe; a ação `saida` aceita `total` (o valor que a tela mostrou) e responde 409 quando a conta mudou. O banco continua na versão 27.

## 6h. Criança PCD e voucher

**Criança PCD: 50% de desconto, só na recreação avulsa.**

- No cadastro (e em **Editar**), marque **Criança PCD**. A partir daí a **recreação avulsa** dela sai pela metade: R$ 40 a cada meia hora, na conta da saída (e também no tempo além do pacote). **Pacote de horas, carrinho de bebê, fusquinha e outros produtos não têm desconto.**
- O atendimento mostra o preço já com o desconto e o selo **PCD 50%**; a lista do espaço e a ficha mostram o selo **PCD**.
- Cada venda com desconto fica anotada ("Recreação · 0h30 · PCD 50% · R$ 40") em Hoje na recepção e no histórico do cliente. **Marcar ou desmarcar PCD também fica anotado no dia**, com o nome de quem fez, porque muda o preço.

**Voucher do shopping: 30 min de recreação grátis, um por visita, sem código.**

- No atendimento, abaixo dos serviços, a linha **Voucher** é só de marcar: um toque marca, outro desmarca. Não tem quantidade nem código.
- **Os primeiros 30 min saem grátis; o que passar disso volta a ser cobrado normalmente.** Sem pacote: ficou até 39 min (30 + a tolerância), não paga nada; ficou 1 h, paga 1 período (R$ 80); ficou 2 h, paga 3 períodos (R$ 240). O que passar do voucher é recreação avulsa, acertada na saída (R$ 40 por período se a criança for PCD).
- **Quem tem pacote de horas**: o voucher cobre os primeiros 30 min e o resto sai do pacote, sem cobrança. Ex.: pacote de 5 h + voucher, ficou 2 h: sobram 3h30.
- Dá para juntar na mesma venda o voucher e períodos pagos (ex.: voucher + 30 min pagos = 1 h, R$ 80).
- **Um por visita**: quem já usou o voucher na visita (ou tem um de hoje guardado, ainda sem entrar) não ganha outro; a linha aparece como "já usado nesta visita". Se a criança sair e voltar no mesmo dia com outro voucher, o sistema aceita e anota "voucher (2º de hoje)", para a supervisão ver.
- **O voucher chegou depois** (o responsável trouxe na hora de buscar): na folha da **Saída**, quando há valor a receber, aparece **Tem voucher? Usar agora**. Um toque e ele cobre um período da visita (R$ 80 a menos); aparece o botão Desfazer. Se for o **2º voucher do dia** da mesma criança, o sistema avisa no pé da tela ("Atenção: é o 2º voucher de hoje para …"). O mesmo acontece ao marcar o Voucher no atendimento de uma criança que está no espaço com cobrança a receber.
- A lista do espaço mostra um presentinho ao lado do nome de quem está com voucher; a ficha e a folha da saída dizem "Voucher usado: meia hora desta visita ficou por conta do voucher".
- A venda fica anotada ("Recreação · 0h30 · voucher · R$ 0") em Hoje na recepção e no histórico do cliente. Quando o voucher cobre uma cobrança, a linha da cobrança fica marcada "coberta pelo voucher".
- Em **Hoje na recepção** (bloco "Horas vendidas hoje"), um resumo mostra quantos vouchers e quantas vendas com desconto PCD houve nas últimas 24 h e quanto isso representou: é o número para conferir com os vouchers de papel recolhidos no dia.

## 6i. Falta finalizar: cada atendimento continua de onde parou

O atendimento tem três etapas: **cadastro**, **escolher o serviço** e **registrar a venda** (com a entrada). Se a atendente é interrompida no meio, nada se perde: ao tocar naquele cliente de novo, a tela **abre na etapa em que parou**, com o que já estava preenchido.

- **Faixa "Falta finalizar"**, no alto da tela inicial: uma linha para cada atendimento em aberto, com o responsável, a criança, em que ponto parou e a hora. Tocar continua dali.
  - *cadastro pela metade · falta um telefone e o nome da criança*: o cadastro novo foi fechado antes de salvar. Abre o cadastro com tudo o que já estava escrito (documento, responsável, telefones, nome, PCD e observações).
  - *falta escolher o serviço*: o cadastro foi feito hoje e a venda ainda não. Abre direto o atendimento.
  - *R$ 80 escolhido · falta o pagamento*: o serviço já estava escolhido. Abre o atendimento com tudo de volta: quantidades, pacote, voucher marcado, outro item com o valor e a escolha de dar entrada.
- **Tocar no cliente em qualquer lugar** (a busca da tela inicial, o Banco de horas, o histórico) faz a mesma coisa: enquanto o atendimento está em aberto, o toque abre o atendimento, não a ficha. O botão **Atender**/**Vender** vira **Continuar**. Para ver a ficha mesmo assim, o atendimento tem o atalho **Ver a ficha**.
- **Criança que já está no espaço** e ficou com uma venda pela metade (ex.: mais 30 min): ela aparece na faixa, e o toque na linha dela continua abrindo a ficha, que mostra o aviso **Atendimento em aberto** com o botão **Continuar**.
- **Fechou sem terminar?** Um aviso diz que ficou guardado e traz o botão **Continuar**. Dentro do atendimento retomado, **Começar do zero** limpa o que estava escolhido.
- **Não vai fazer agora** (o cliente desistiu): o **✕** da linha tira da faixa (aparece o botão Desfazer). Daí em diante o toque naquele cliente volta a abrir a ficha.
- **Registrou a venda**, sai da faixa. Se a venda for desfeita no aviso, o que estava escolhido volta.
- **Tudo vale até o fim do dia.** No dia seguinte a faixa começa vazia; o cadastro que ficou sem venda continua no Banco de horas, como qualquer cliente.

O que é de todos os aparelhos e o que é de cada um:

- **Cadastro feito e ainda sem venda** vem do servidor: aparece em todos os aparelhos, e o ✕ tira de todos.
- **O que estava escolhido** (serviços, voucher, outro item) e **o cadastro pela metade** ficam guardados **no aparelho em que foram feitos**: continuam lá se a página for recarregada ou atualizada, e não aparecem nos outros aparelhos.
- Ao **sair da conta**, os cadastros pela metade são apagados do aparelho (têm documento e telefone do responsável).
- **Sem venda em dobro**: se a venda daquela criança for registrada em outro aparelho, o que estava escolhido aqui sai da faixa sozinho. E se o atendimento estiver aberto na tela nessa hora, ao tocar em Registrar o sistema avisa *"Já foi registrada uma venda para Mari às 14:05: Recreação · 0h30 · R$ 80"* e só registra outra se a atendente tocar em **Registrar mesmo assim**.

## 6j. Pacotes de horas (a tabela, em Ajustes)

**Desde a 1.12 o botão vermelho do alto da tela inicial é o da Festa (seção 6w).** A tabela de pacotes e produtos saiu de lá e abre por **Ajustes → Pacotes de horas → Abrir a tabela de pacotes e produtos**; os pacotes e os produtos continuam sendo vendidos, como sempre, no atendimento de cada cliente. O resto desta seção vale para essa tabela.

A folha **Pacotes e produtos** é a tabela de preços, para mostrar ao cliente **e para vender**, em duas abas: **Pacotes de horas** e **Produtos** (seção 6l). As horas do pacote não têm prazo para usar.

**Vender por aqui (0.98):** cada linha da tabela tem o botão **Vender** (um toque em qualquer ponto da linha faz o mesmo). A tela pergunta **para qual cliente**: escreva os **três primeiros números do CPF** do responsável (ou o nome dele, ou o da criança) e os clientes aparecem, cada um com as crianças e o saldo de horas de cada uma. O CPF aparece só com o começo e o fim, porque outra pessoa pode estar olhando a tela. **Um toque na criança abre o atendimento com o pacote já na venda**: é conferir (a entrada vem marcada, se a criança vai entrar agora) e tocar em **Pagamento**.

- **Cliente novo:** o botão **Cliente novo: cadastrar** abre o cadastro com o CPF que foi escrito e, ao cadastrar, o atendimento abre com o pacote na venda.
- **Trocar** volta para a tabela, para escolher outro pacote. Com uma criança só na lista, **Enter** já escolhe.
- Na aba **Produtos** é igual: um toque no produto, o cliente pelo CPF, e o atendimento abre com o produto na venda.
- O caminho de sempre continua valendo: abrir o atendimento do cliente e escolher o pacote lá. As horas do pacote entram no saldo da criança escolhida.

A tabela vem **por tempo e, dentro do mesmo tempo, por valor**, com as colunas **Tempo, Valor, Hora, Meia hora** e o **nome do pacote**. O tempo aparece em horas (2h, 5h, 10h): não há coluna de minutos. O valor da hora e da meia hora o sistema calcula sozinho. Se algum pacote parcela, aparece também a coluna **Parcela**.

Pacotes que já vêm instalados (os da tela "Venda de pacotes de tempo" do sistema que a loja usava, em novembro de 2026):

| Tempo | Valor | Hora | Meia hora | Nome no sistema antigo |
|---|---|---|---|---|
| 2h | R$ 280 | R$ 140 | R$ 70 | 02 HORAS 260 |
| 3h | R$ 225 | R$ 75 | R$ 37,50 | 03 HORAS 360 |
| 4h | R$ 380 | R$ 95 | R$ 47,50 | 04 HORAS 420 |
| 5h | R$ 390 | R$ 78 | R$ 39 | 05 HORAS 360 ESPECIAL |
| 5h | R$ 420 | R$ 84 | R$ 42 | PACOTE 05 HORAS 420 |
| 5h | R$ 480 | R$ 96 | R$ 48 | 05 HORAS 450 |
| 5h | R$ 500 | R$ 100 | R$ 50 | 05 HORAS 380 |
| 10h | R$ 820 | R$ 82 | R$ 41 | 10 HORAS 840 |

- **O valor é o da coluna "Valor" do sistema antigo.** Em sete dos oito pacotes o número que está no nome não bate com esse valor (ex.: "05 HORAS 380" custa R$ 500). O nome ficou como estava, só para a equipe reconhecer o pacote; a gestora troca o nome ou o valor quando quiser.
- **Mudar a tabela**: só a gestora. Na aba Pacotes, o **lápis** no fim da linha abre o pacote e **Novo pacote** cria outro; também dá para fazer em **Ajustes → Pacotes de horas**.
- No cadastro do pacote: as **horas**, o **valor**, o **nome** (opcional; serve para separar pacotes do mesmo tempo), o **tipo** (só as horas, ou com produto: o cliente leva o produto, que já está no valor), em **quantas vezes parcela** e, no pacote com produto, **qual é o produto**. Pode haver vários pacotes do mesmo tempo.
- Quando a loja tem pacotes **com produto** e pacotes **só de horas**, a aba e o atendimento mostram cada tipo no seu bloco.
- **Vender** continua no atendimento do cliente: os pacotes estão em **Lista de pacotes** (um toque abre, outro escolhe). A venda fica anotada com o nome do pacote ("Pacote 05 HORAS 380 · 5h · R$ 500"), em Hoje na recepção e no histórico.
- **Criança PCD não tem desconto em pacote** (seção 6h). O voucher junta com o pacote: a primeira meia hora sai do voucher e o resto das horas do pacote.
- Se a gestora mudar a tabela enquanto um atendimento está aberto em outro aparelho, a venda não troca de pacote sozinha: o sistema avisa "O valor do pacote mudou" e pede para abrir o atendimento de novo.
- Histórico das versões: a 0.81 instalou três valores "com produto" de uma tabela antiga, que a 0.82 tirou; a 0.83 instala a tabela acima. Quem já tinha o pacote de 5 h por R$ 420 não fica com ele em dobro.

## 6k. Como o tempo aparece

O tempo de recreação aparece **sempre em horas**, nunca em minutos soltos:

- **0h30, 1h, 1h30, 5h**: no atendimento, nas vendas, na saída ("ficou 0h52, debita 1h"), em Hoje na recepção e no histórico.
- **0:52:03** (horas, minutos e segundos): o cronômetro de quem está no espaço, na lista e na ficha. "passou 0:03:41" é quanto já passou do tempo comprado.
- Nos preços, o período aparece por extenso: "R$ 80 a cada meia hora".
- Ficam em minutos só os prazos curtos de configuração e de cancelamento (tolerância de 9 min, cancelar em até 9 min).

## 6l. Produtos da loja (aba Produtos, atendimento e estoque)

O botão vermelho do alto da tela inicial abre a folha **Pacotes e produtos**. A aba **Produtos** mostra o que a loja vende além do tempo: o **produto**, o **valor** e o **estoque**.

Produtos que já vêm instalados (os da tela "Produtos disponíveis" do sistema que a loja usava, em novembro de 2026):

| Produto | Valor | Estoque na foto | Nome no sistema antigo |
|---|---|---|---|
| Amigurumi | R$ 320 | 8 | AMIGURUMI 230 |
| Boneca articulada | R$ 150 | 19 | BONECA ARTICULADA 150 |
| Boneca articulada | R$ 230 | 40 | BONECA ARTICULADA 150 |
| Kit bijoux | R$ 230 | 10 | KIT BIJOUX 150 |
| Kit laço | R$ 260 | 8 | KIT LAÇO 150 |
| Maleta | R$ 350 | 20 | MALETA 300 |
| Maquiagem | R$ 160 | 4 | MAQUIAGEM (o número ficou atrás do cursor na foto) |

- **O valor é o da coluna "Valor" do sistema antigo.** Lá o nome trazia um número que quase nunca bate com esse valor ("AMIGURUMI 230" custa R$ 320); aqui ficou só o nome. Há **duas "Boneca articulada"**, uma de R$ 150 e outra de R$ 230: se forem modelos diferentes, vale a gestora pôr a diferença no nome.
- **O estoque é o que aparecia na foto.** A gestora confere a contagem e acerta pelo lápis.
- **Vender**: no atendimento do cliente, os produtos aparecem depois dos pacotes. **Um toque põe um na venda**; o botão fica marcado, com o número e um **−** para tirar. Outro toque põe mais um. O total a pagar soma na hora. A venda fica anotada com o nome do produto ("Maleta · R$ 350", "Maleta · 2 unidades · R$ 700") em Hoje na recepção e no histórico do cliente.
- **Pacote com produto**: toque no pacote de horas e no produto; o sistema soma os dois (ex.: 5h por R$ 420 + Kit laço por R$ 260 = R$ 680) e registra as duas linhas.
- **Estoque**: baixa sozinho a cada venda. Volta sozinho no **Desfazer** e quando a entrada é cancelada sem cobrar o produto (seção 6e). No botão do atendimento aparece quanto há ("8 em estoque") e, com o produto na venda, quanto fica ("ficam 7").
- **O estoque não barra a venda.** Se o sistema diz que não há mais, a tela avisa ("O estoque de Maleta no sistema está zerado. Confira antes de entregar.") e deixa vender; o estoque fica negativo e aparece **em vermelho**, para a gestora acertar a contagem.
- **Mudar a lista**: só a gestora. Na aba Produtos, o **lápis** abre o produto e **Novo produto** cria outro; também dá para fazer em **Ajustes → Produtos**. No cadastro: o **nome**, o **valor** e o **estoque agora** (com − e +). Estoque em branco = o sistema não controla o estoque desse produto.
- **Fica anotado**: toda mudança de estoque ou de valor feita pela gestora aparece em **Hoje na recepção**, com quem fez ("Maleta · Estoque: 20 → 25 · por Camila").
- Se a gestora abre o produto para somar a mercadoria que chegou e, nesse meio tempo, alguém vende uma unidade, o sistema aplica a diferença (chegaram 10: soma 10), sem apagar a venda.
- **Apagar** tira o produto da lista (aparece o botão Desfazer); as vendas antigas dele continuam no histórico.
- **Criança PCD não tem desconto em produto**, como no pacote (seção 6h).
- Se a gestora mudar o valor de um produto enquanto um atendimento está aberto em outro aparelho, a venda não sai no valor antigo: o sistema avisa "O valor de Maleta mudou" e pede para abrir o atendimento de novo.
- No **teste com 10 crianças** (seção 2b), o que for vendido de produto para uma criança de teste volta para o estoque quando o teste é tirado.

## 6m. Reabrir a visita (a criança saiu e voltou logo)

Às vezes a saída já foi marcada, a criança chora e o responsável deixa ficar mais. Em vez de fazer uma entrada nova, a equipe **reabre a visita**.

- **Prazo**: até **30 minutos depois da saída** (muda em **Ajustes → Regra das horas → Reabrir a visita depois da saída**: não reabre, até 15 min, até 30 min ou até 1 hora). Passou do prazo, é uma entrada nova.
- **Onde**: na tela inicial, quando alguém saiu dentro do prazo, aparece o filtro **Saíram há pouco** (com a contagem). Tocando nele, vem a lista de quem saiu, com o botão **Reabrir**. O mesmo botão aparece na busca (quem saiu há pouco) e na ficha da criança. O filtro some sozinho quando o prazo passa.
- **O que acontece**: a saída é desfeita e **o tempo continua contando desde a entrada**, como se a criança não tivesse saído. O que foi debitado volta para o saldo e a conta é refeita na próxima saída. A sapateira que ela usava volta, se ainda estiver livre; senão o aviso oferece escolher outra.
- **O tempo fora também conta.** Antes de confirmar, a folha mostra até quando ia o tempo comprado e, se o tempo fora fez a visita precisar de mais um período, quanto de recreação entra a mais na conta ao reabrir, e o valor.
- **Fica anotado** em Hoje na recepção: a linha da saída ganha "visita reaberta às 15:06" e entra uma linha nova com a hora da saída desfeita, quanto tempo ficou fora e **quem reabriu**.
- Qualquer pessoa da equipe reabre dentro do prazo (com o iDFace ligado, só gestor e supervisor, como na saída à mão). Se depois daquela saída houve uma entrada cancelada, não reabre: aí é entrada nova.
- O botão **Desfazer** que aparece depois da saída (os primeiros segundos) continua existindo, para o toque errado.

## 6n. Tudo o que é digitado fica em maiúsculas

**Tudo o que a equipe digita sai em MAIÚSCULAS**, sem a opção de sair em minúsculas: o nome da criança e o do responsável, a observação, o motivo de um cancelamento ou de uma correção de hora, o "outro item" da venda, os nomes dos serviços, dos pacotes e dos produtos, a agenda e os nomes da equipe.

- O campo já mostra em maiúsculas enquanto a pessoa escreve (no celular, o teclado abre em maiúsculas), e o sistema grava assim.
- Ficam de fora só o que não pode mudar de caixa: o **login** (sempre em minúsculas), a **senha**, e os campos técnicos do iDFace e do WhatsApp.
- Os atalhos da observação (Alergia, Pode pintura, Sem pintura…) também escrevem em maiúsculas.
- Na atualização para a 0.89, o que já estava digitado no banco foi convertido de uma vez (observações, motivos, serviços, pacotes, produtos, agenda e equipe). Os nomes das crianças e dos responsáveis já estavam assim desde a 0.85.
- O que o sistema escreve sozinho continua como texto normal ("Entrada registrada", "Ficou 0h45 · 1h debitados"), e as linhas antigas de Hoje na recepção ficam como foram escritas.
- A busca não liga para maiúsculas e minúsculas.

## 6o. O que sai da loja e volta: carrinho de bebê e fusquinha

O carrinho de bebê e o fusquinha saem da loja com o cliente e voltam. A partir da 0.89, **vendido, o carrinho entra na lista da tela inicial e fica lá até alguém tocar em Devolveu**. Assim a equipe vê, na mesma lista das crianças, o que está fora e há quanto tempo.

- **Quais serviços entram**: os que estão marcados como **Sai da loja e volta** em Ajustes → Serviços e preços (toque no serviço). O CARRINHO FUSQUINHA e o CARRINHO DE BEBÊ já vêm marcados. Um serviço novo (um patinete, por exemplo) entra na lista e ganha um filtro só de marcar a caixinha. O tempo de brincar (RECREAÇÃO) nunca entra.
- **A linha**: o ícone do carrinho, o responsável e a criança, o nome do serviço, o relógio contando desde a venda e o botão **Devolveu**. Com mais de uma unidade, aparece "2 × CARRINHO DE BEBÊ".
- **Com tempo comprado** (o fusquinha, vendido a cada meia hora): o relógio fica amarelo quando faltam 10 min e vermelho quando passa do tempo comprado, com o "passou 7 min" embaixo, também em vermelho (desde a 1.05 a linha não fica mais toda vermelha; na ficha, a linha do carrinho também não). Parando o mouse no relógio aparece até que horas vai, ou quanto passou. **Mais um período do mesmo fusquinha soma o tempo na mesma linha.**
- **Sem hora para voltar** (o carrinho de bebê, valor único): a linha só conta o tempo.
- **De outro dia**: se ninguém marcou a devolução, a linha continua na lista, no começo, com o dia em que saiu escrito em vermelho no lugar do relógio.
- **Devolveu**: um toque, na linha ou na comanda da criança. O carrinho sai da lista e fica anotado em **Hoje na recepção**: "CARRINHO FUSQUINHA · devolvido · ficou 0h44 · tempo comprado: 0h30 (passou 14 min) · por JOÃO". Depois do toque aparece o botão **Desfazer**, por alguns segundos.
- **Devolução com pagamento (1.00):** se o que foi alugado por tempo (o fusquinha) **passou do tempo comprado**, o toque em **Devolveu** abre o **Pagamento da devolução**: quanto ficou fora, quanto passou e o valor a receber (períodos inteiros, com a mesma tolerância da recreação). A devolução só fecha com a forma de pagamento (Pix, dinheiro, crédito ou débito, em até três formas). Dentro do tempo, ou no carrinho de valor único, devolve direto com um toque. O **Desfazer** do aviso põe o carrinho de volta na lista e desfaz o que foi cobrado. **1.02:** o carrinho registrado com "Paga na devolução" também abre o pagamento ao tocar em Devolveu, com o valor que ficou a pagar. **1.03:** a tela da devolução segue a mesma ordem das outras: Detalhes, a tabela com o carrinho (quando saiu, quanto ficou fora, quanto tempo foi comprado) e o **Tempo a mais**, o Total a receber e as formas de pagamento.
- Quem leva só o carrinho **não entra no espaço**: aparece na lista só a linha do carrinho. Se a criança também está na recreação, as duas linhas ficam juntas, na moldura da família.
- Se a venda for desfeita (ou deixar de ser cobrada num cancelamento de entrada), o carrinho sai da lista sozinho.
- Todos os aparelhos veem o carrinho entrar e sair da lista em poucos segundos.
- O que foi vendido antes da 0.89 não entra na lista: vale para as vendas feitas a partir da atualização.

## 6p. Pagamento: Pix, dinheiro, crédito e débito, em até três formas

Depois de escolher o serviço (horas avulsas, pacote, carrinho, produto), o atendimento **passa para a tela de pagamento**. É a mesma folha: embaixo dos itens fica **Adicionar ou trocar serviços**, que volta para a escolha sem perder nada do que estava marcado.

**As telas de pagamento na ordem do sistema anterior (1.03).** As quatro telas de pagamento (a do atendimento, a da saída, o Receber da ficha e a da devolução do carrinho) têm a mesma ordem da tela de fechamento que a equipe já conhecia:

1. **Detalhes**: o cliente (o responsável), o CPF ou RNE, os telefones e quem está atendendo, com a data. Se a criança está no espaço, ao lado do nome aparece **ENTRADA 14:10**.
2. **Itens selecionados**: uma tabela com o item, a criança, a entrada (na saída, também a sapateira) e o valor, e o **Total** embaixo. No celular ficam o item e o valor; o resto desce para uma linha pequena embaixo do item.
3. **Adicionar serviços**: no atendimento, volta para a escolha; na saída, soma o que o cliente leva no próprio pagamento da saída (seção 6r).
4. **Pagamento**: quando há horas na conta, três linhas, **Horas disponíveis**, **Horas necessárias** (ou "Horas desta compra") e **Saldo após confirmação**; depois, as formas de pagamento e o botão que confirma.

**Desconto, só para o gestor (1.04).** Como na tela anterior, o pagamento tem o campo **Desconto**; aqui, só o gestor vê. Atendente e supervisor não veem o campo, e o servidor recusa o desconto de quem não é gestor.

- Fica fechado, embaixo das formas de pagamento: **Desconto · só o gestor**. Um toque abre: **Por valor · R$** ou **Por percentual · %**, o **valor** e o **motivo** (obrigatório).
- O desconto vira uma linha verde na tabela dos itens ("Desconto (10%) · CLIENTE ANTIGO · − R$ 9,50"), e o total, o valor em cada forma de pagamento e o botão de baixo já mostram o valor com o desconto. Sem o motivo, ou com um valor maior que o total, a tela avisa e não registra.
- Vale no **pagamento do atendimento** (também com "Paga na saída": fica a receber o valor já descontado) e no **pagamento da saída** (sobre o que falta receber: o que ficou para pagar, a recreação avulsa e o tempo além do pacote). Com 100% de desconto não há o que receber: o botão vira **Registrar com desconto total**.
- O desconto é repartido entre os itens em proporção ao valor de cada um e fica guardado em cada venda, com o motivo e quem deu. No dia aparece a linha "Desconto · R$ 9,50 (10%) · Motivo: … · por CAMILA".
- **Onde aparece depois**: em **Hoje na recepção** ("2 descontos do gestor (R$ 39,50)"), no **Histórico do cliente** (em cada compra: "desconto de R$ 8") e no cupom de papel (linha "Desconto").
- **Desfazer**: o Desfazer da venda apaga a venda com o desconto; o Desfazer da saída devolve às vendas o desconto que foi dado nela.
- O desconto da criança PCD e o voucher continuam como eram, e não se misturam com este na conta do dia.

- **O que aparece**: os Detalhes, a tabela dos itens com o **Total a pagar** e, em Pagamento, quatro botões grandes: **Pix**, **Dinheiro**, **Crédito** e **Débito**. Quem já tem horas guardadas, ou leva um pacote, vê também as três linhas das horas (ex.: Horas disponíveis 4h30 · Horas desta compra + 1h · Saldo após confirmação 5h30).
- **Uma forma só** (o mais comum): um toque na forma e um toque em **Recebi R$ 420 no Pix · registrar**. A venda é gravada e, se a caixa "Dar entrada agora" estava marcada, a criança já entra.
- **Dividir em duas ou três formas**: toque nas formas que o cliente vai usar. Aparece uma linha para cada uma: escreva o valor das primeiras, e **a última forma tocada fica com o resto**, para a conta fechar sempre com o total. Exemplo: Pix, Dinheiro e Crédito num total de R$ 420; escreve 200 no Pix e 100 no dinheiro, e o crédito fica com R$ 120.
- Para tirar uma forma, toque nela de novo. A quarta forma não entra: são até três.
- Se os valores passam do total, a tela avisa em vermelho e o botão fica desligado até acertar. Aceita centavos ("100,50").
- **Nada a pagar** (só o voucher): não há tela de pagamento, o botão registra direto.
- **O que fica anotado**: uma linha em **Hoje na recepção** ("Pagamento · R$ 420 · Pix R$ 200 + Dinheiro R$ 100 + Crédito R$ 120"), a forma em cada compra no **Histórico do cliente**, e o total por forma no alto de **Hoje na recepção** (Pix, Dinheiro, Crédito e Débito das últimas 24 horas).
- **Desfazer** a venda (o botão que aparece logo depois dela) tira também o pagamento.
- **O servidor confere**: o pagamento tem de fechar com o total que ele mesmo calcula pela tabela. Se o valor de um serviço mudou no meio do atendimento, aparece "O pagamento não fecha com o total" e nada é vendido: é fechar e abrir o atendimento de novo.

**Pagar na entrada ou só na saída (1.02; sem os selos desde a 1.06).** Tem cliente que paga na entrada e tem cliente que só paga na saída. A escolha continua na tela de pagamento, e o que ficou para pagar é cobrado na saída ou na devolução. **Na 1.06 os selos PAGO e NÃO PAGO saíram da tela** (da lista, do celular, da ficha e da linha do carrinho): o dono vai decidir depois como isso aparece. O que ficou:

- **Na tela de pagamento do atendimento**, em Pagamento, a escolha fica sempre à vista (1.03): **Paga agora** (verde) ou **Paga na saída** (vermelho). Em "Paga agora" aparecem as formas de pagamento. Em "Paga na saída" a tela diz "R$ 80 a receber na saída" e o botão de baixo vira **Paga na saída · registrar e dar entrada**: a venda e a entrada são registradas sem pagamento, e a linha do dia diz "A receber na saída · R$ 80". A escolha só existe quando há uma hora certa para receber depois: a criança entra agora (ou já está no espaço), ou a venda tem um carrinho que sai da loja e volta (aí a opção diz **Paga na devolução**). Venda de produto ou de pacote para quem não vai entrar é paga na hora.
- **Na tela inicial** não há mais selo: a coluna Pagamento virou **Unidade** (seção 6v).
- **Na ficha**, quem ficou de pagar tem uma linha só: "A receber · R$ 80", com o botão **Receber** (com a recreação avulsa da visita junto, "A receber · R$ 30 + recreação R$ 160" e **Receber R$ 190**; para quem tem pacote e ele acabou, "Além do pacote · R$ 80 a receber"). Só a recreação avulsa correndo não gera essa linha: ela é acertada na saída. Quem está em dia não tem linha nem selo. O Receber abre as quatro formas de pagamento e recebe na hora, sem esperar a saída; o aviso tem Desfazer.
- **Na saída**, a tabela do Pagamento da saída lista cada item que ficou para pagar ("ficou para a saída") junto com a recreação avulsa (ou o tempo além do pacote), se houver, e o **Total a receber**. Sem a forma de pagamento a saída não é dada. O Desfazer da saída devolve também o que foi recebido nela.
- **Na devolução do carrinho** que ficou para pagar depois, o toque em Devolveu abre o Pagamento da devolução com o item "ficou para a devolução", R$ 15 (e o tempo a mais, se passou).
- **No cupom** da entrada sai "A pagar na saída" e "A RECEBER NA SAÍDA"; no cupom da saída, "O que ficou para pagar na saída", com a forma em que foi recebido.
- **No Histórico do cliente**, a compra aparece com **A RECEBER** enquanto não for recebida, e não entra em "Pagou ao todo".
- **Cancelar a entrada** de quem não pagou não tem nada a devolver.
- Quem entrou num dia e só saiu no outro (saída esquecida) continua com a conta aberta: o que ficou a pagar é cobrado na saída, mesmo depois da meia-noite.

**Na saída**, o toque em Saída já abre o **Pagamento da saída** (seção 6r): a visita é paga com as horas do cliente e, quando há valor a receber (a recreação avulsa, o tempo além do pacote ou o que ficou para a saída), com as mesmas quatro formas, em **Forma de pagamento**. Sem escolher a forma, a saída não é dada (o botão diz "Escolha a forma de pagamento" e a tela pergunta "Como recebeu R$ 80?"). Também dá para dividir. A linha do dia fica "… recreação avulsa: R$ 80 · recebido: Dinheiro".

**Depois de registrar**, a tela abre o **cupom** para imprimir e o responsável assinar (seção 6s).

**Cancelar a entrada de quem já pagou**: o aviso e a linha do dia dizem o que devolver e por qual forma ("devolver ao cliente: Crédito R$ 80").

**Reabrir a visita** (seção 6m): o que já foi recebido na primeira saída não volta a aparecer como "a receber"; na segunda saída a tela cobra só o que é novo.

O que ainda não tem forma anotada aparece numa frase em Hoje na recepção: é a recreação de quem ainda está no espaço (será recebida na saída) ou uma saída lida pelo iDFace. As vendas feitas antes da 0.90 não têm forma de pagamento.

## 6q. Observação: o OBS (só a equipe vê)

A observação do cliente (alergia, quem pode buscar, o que a equipe precisar lembrar) **não fica mais escrita na tela**, porque o responsável pode estar olhando. No lugar dela aparece um **OBS**:

- **OBS vermelho e aceso**: há observação. **Um toque mostra o texto; outro esconde.**
- **OBS apagado** (cinza): não há observação. Um toque abre o campo para escrever.

Onde o OBS aparece:

- **na tela inicial**, ao lado do nome da criança (só quando há observação): o toque mostra o texto no aviso do pé da tela, por alguns segundos;
- **no atendimento (a venda)** e **na comanda**, logo abaixo do nome: o toque abre o texto ali mesmo, com **Mudar**, **Esconder** e, se já houve mudança, **Ver a anterior**;
- **no histórico do cliente**, em cada criança.

**Dá para escrever ou mudar a observação em três lugares**: no cadastro (campo Restrições e observações), no atendimento e na comanda (toque no OBS → escrever → **Guardar**). Os atalhos (Alergia, Pode pintura, Não mexer no cabelo…) funcionam nos três. Qualquer pessoa da equipe pode anotar.

**A observação não some:**

- fica guardada no cadastro da criança e aparece (como OBS aceso) em todo atendimento dela, hoje e nas próximas visitas;
- quando alguém muda ou apaga, **a observação anterior continua guardada**: em **Ver a anterior** dá para ler e, com um toque em **Voltar para esta**, recuperar;
- o OBS aberto diz **quem anotou e quando**; a mudança vira uma linha em **Hoje na recepção** ("Observação alterada · por JOÃO"), sem repetir o texto.

O texto da observação não aparece em nenhuma tela sem que alguém toque no OBS, nem na dica que surge ao parar o mouse.

## 6r. Pagamento da saída, e as horas que não expiram

**O toque em Saída abre direto o Pagamento da saída**, para ninguém esquecer de receber. A tela diz sempre **como aquela visita é paga**:

- **Detalhes**: o cliente, com **ENTRADA 14:02** ao lado do nome, o CPF, os telefones e quem está atendendo;
- **Itens selecionados**: a linha da visita (RECREAÇÃO, com a criança, a sapateira, a hora da entrada, quanto ficou e quanto usa das horas do cliente), uma linha para cada coisa que ficou para pagar ("ficou para a saída") e, para quem tem pacote e ele acabou, a linha **Além do pacote · 0h30**. Na recreação avulsa (1.15, seção 6g) a visita é uma linha só, já com o valor: **RECREAÇÃO avulsa · 1h30**. Embaixo, o **Total a receber**;
- **Adicionar serviços** (1.03): o cliente quer levar um pacote, ou um produto, na saída. O atendimento abre com a lista de pacotes e o botão vira **Somar na saída · R$ 420**: o que foi escolhido entra na tabela da saída, que abre de novo sozinha, e **tudo é recebido num pagamento só**, com um cupom só. As horas do pacote já contam: a recreação que estava a receber é desfeita e a visita sai do saldo novo;
- **Pagamento**: as três linhas das horas, como na tela anterior (só para quem tem horas: na recreação avulsa elas não aparecem). Ex.: **Horas disponíveis 10h · Horas necessárias 0h30 · Saldo após confirmação 9h30**. Quando há valor a receber, a linha do meio mostra as duas coisas: "0h30 + R$ 80". Logo abaixo ficam o **Tem voucher?** (seção 6h) e a **Forma de pagamento**: Pix, Dinheiro, Crédito e Débito, em até três formas, igual à venda (seção 6p). Para o gestor, embaixo das formas fica o **Desconto** (seção 6p): o valor sai do que a saída recebe, e a linha do meio das horas já mostra o valor com o desconto.

O botão de baixo diz o que vai acontecer:

- **Usar 1h das horas · dar saída**: a visita é paga só com as horas do cliente. Um toque e a saída está dada.
- **Escolha a forma de pagamento**: há valor a receber e a forma ainda não foi escolhida. A saída não é dada sem ela.
- **Recebi R$ 80 no Pix · dar saída**: forma escolhida; o toque registra o pagamento e dá a saída. Dividindo em mais de uma forma, o botão pede o valor que falta ("Quanto no Pix?") e, com a conta fechada, vira **Recebi R$ 80 · dar saída**.

A tela acompanha o relógio: se a tolerância estourar com ela aberta, a conta e as formas de pagamento mudam na hora (e o servidor confere o total antes de dar a saída, seção 6g). **Pacote na saída (1.00, mudou na 1.03):** é o **Adicionar serviços** da lista acima; não há mais um pagamento à parte para o pacote. O mesmo vale para qualquer tempo comprado com a criança no espaço: o que o cliente comprou cobre a visita, e só fica a receber o que ainda faltar. A folha da saída não corrige mais a hora. **Quem ficou de pagar na saída (1.02):** a tabela lista o que ainda não foi pago, soma com a recreação (se houver) e só dá a saída com a forma de pagamento. O cupom da saída traz cada item pelo nome (1.03).

**Saída em conjunto (1.07): o responsável com mais de uma criança no espaço.** O toque em **Saída**, em qualquer uma delas, abre **uma conta só**, como o fechamento do sistema anterior:

- cada criança tem a linha dela (a visita, com a sapateira, a hora da entrada e quanto usa das horas) e, embaixo, o que ficou para pagar e o tempo além do pacote dela, com o nome ao lado (na recreação avulsa, a linha da criança já traz o valor). O **Total a receber** é um só, e as três linhas das horas somam as das crianças;
- em cada linha de criança há o **Tirar**. A criança que vai **pagar à parte** (é de outro pai e veio com este responsável, ou não entra na conta dele) ou que **vai sair depois** fica fora desta conta e **continua no espaço**. Ela aparece em "Fora desta conta", e um toque no nome põe de volta. Depois, a Saída dela abre a conta dela;
- o botão diz quem sai: **Usar 2h das horas · dar saída de SOFIA e HELENA**, ou **Recebi R$ 160 no Pix · dar saída de SOFIA e HELENA**;
- **um pagamento só** (em até três formas), **um desconto** do gestor para a conta toda e **um cupom só**, com as crianças que saíram, o pagamento e uma linha de assinatura. O botão **Desfazer** também é um só, e devolve todas;
- o voucher é de cada criança ("Tem voucher para SOFIA?"), e o **Adicionar serviços** soma na mesma conta;
- quem não tem irmão no espaço continua com a tela de sempre. Quem está em "Falta finalizar" não entra na conta.

No servidor cada criança continua com a saída dela: a tela reparte o pagamento por criança (na ordem das formas) e o desconto (em proporção ao que cada uma tem a receber). As horas continuam sendo de cada criança: cada uma usa as dela.

**Descontar do pacote ou pagar avulso (1.11).** O cliente tem horas guardadas, mas hoje quer pagar a visita e deixar o pacote como está. No Pagamento da saída, logo abaixo do título **Pagamento**, ficam as duas posições:

- **Descontar do pacote** (como a tela abre, sempre): a visita sai das horas do cliente, como antes;
- **Pagar avulso**: a visita **não desconta do pacote**. O tempo que sairia das horas guardadas vira a linha **RECREAÇÃO avulsa · 1h · não desconta do pacote**, pelo valor da tabela (o da recreação a cada período; a criança PCD com o desconto dela), entra no **Total a receber** e é recebido ali mesmo, em Pix, dinheiro, crédito ou débito (em até três formas). As três linhas das horas mostram a diferença: "Horas necessárias 0h + R$ 160" e o **Saldo após confirmação** igual ao que o cliente tinha.

Regras da conta:

- a escolha só aparece quando a visita usaria horas guardadas. Quem só tem o tempo comprado hoje (a recreação avulsa paga na entrada) não vê a escolha: esse tempo já foi pago e não é cobrado de novo. Se o cliente com pacote comprou meia hora na entrada e ficou uma hora, o avulso cobra só a meia hora que sairia do pacote;
- o **tempo além do pacote** continua valendo e soma na mesma conta; o **desconto do gestor** vale sobre o total;
- na **saída em conjunto**, a escolha vale para a conta: todas as crianças que usariam o pacote pagam avulso. Para uma só pagar avulso, use o **Tirar** e dê a saída dela à parte;
- o **cupom da saída** leva a linha "RECREAÇÃO avulsa · 1h", o total pago com a forma e o **número da venda**; o saldo de horas impresso é o do pacote, inteiro;
- a venda fica no histórico do cliente e no dia como "avulso na saída: não descontou do pacote", e entra no caixa na forma em que foi recebida;
- o botão **Desfazer** da saída tira a venda avulsa e o pagamento dela, e devolve a criança ao espaço. Ao abrir a saída de novo, a tela volta em "Descontar do pacote";
- ao tocar em **Adicionar serviços** e voltar, a escolha continua como estava.

No servidor: a ação `saida` aceita `avulso` e devolve a venda feita; a saída avulsa não desfaz a cobrança além do pacote (o tempo avulso não cobre o que já estava cobrado).

**As horas compradas não expiram, nunca.** Não existe prazo de validade no sistema: um pacote comprado hoje vale daqui a dez anos, inteiro. O saldo só diminui quando a criança usa (a visita) ou quando uma venda é desfeita ou cancelada.

- O Pagamento da saída, a comanda ("As horas não expiram: o saldo fica guardado até ser usado") e o alto do Banco de horas dizem isso, para a equipe responder ao cliente sem dúvida.
- **Remover um cadastro que tem horas guardadas** esconderia essas horas. Por isso: o **Supervisor não remove** cadastro com horas (a tela avisa "Só o gestor remove um cadastro com horas"); o **Gestor** vê o aviso com o saldo e precisa tocar em **Remover mesmo assim**. A remoção vira uma linha em Hoje na recepção ("Cadastro removido com 4h30 guardadas · por CAMILA").
- Mesmo removido, **nada é apagado do banco de dados**: o cadastro fica inativo, com as horas, as vendas, os pagamentos e as visitas. Ainda não há um botão para trazer um cadastro removido de volta: se precisar, peça ao suporte.
- Cadastro sem horas guardadas continua sendo removido como antes (Gestor ou Supervisor, um toque).
- Para as horas valerem por muitos anos, o que precisa durar é o banco de dados: faça de tempos em tempos a cópia da pasta `~/playspace-dados/` (seção 1).

## 6s. Cupom impresso (Bematech MP-4200 TH)

O cupom da loja é o papel da impressora térmica do balcão, que o responsável assina. Sai com a **logo do Play Space** e **sempre em duas vias**, uma depois da outra, cada uma cortada sozinha.

**Desde a 1.09 o cupom é curto.** A logo fica pequena, ao lado do nome da loja; a data vai na faixa da via; um tracejado fino separa as partes (no lugar das linhas em branco); e cada cupom leva só o que interessa a ele. Uma recreação simples, que gastava 185 mm de papel na via da loja e 145 mm na do cliente, gasta 102 mm e 76 mm. O cupom do carrinho, que gastava 180 mm e 141 mm, gasta 75 mm e 53 mm.

**VIA DA LOJA** (sai primeiro; é a que o responsável assina e fica com a loja):

- a logo, o **nome da loja**, a **unidade** e o telefone da loja; a faixa "VIA DA LOJA" com a **data e a hora**;
- o **responsável**, o **CPF** (ou RNE) e os **telefones** dele;
- **cada criança** dele que está no espaço (irmãos saem no mesmo cupom): o nome e a idade, o **número do cadastro** e a **sapateira**, a hora da **entrada** com o tempo comprado e até que horas ele vai (ou as horas do cliente, para quem tem pacote) e o **valor a cada 30 minutos**;
- **o pagamento, como um comprovante** (1.10): a tabela **Descrição / Qtd / Valor**, o subtotal e o desconto (só quando há desconto) e o **total já com a forma de pagamento** ("Total pago no Pix"; se dividiu, o total e uma linha para cada forma);
- uma linha pequena com o **número da venda** e **quem atendeu**;
- a **linha da assinatura**, com o nome e o CPF do responsável embaixo, quando há o que assinar (a entrada da criança, o carrinho que sai, a saída, a devolução);
- o **aviso** do pé ("Somente o responsável que assinar a entrada da criança poderá assinar sua saída"), seguido da **tolerância que o cliente lê: 5 minutos** (1.00). A conta do sistema continua com a tolerância da regra das horas (9 min): a diferença é a folga da loja para os dias de fila. Muda em **Ajustes → Regra das horas → No cupom, o cliente lê tolerância de** (ou "Não mostrar"). O aviso só sai quando há criança entrando.

**VIA DO CLIENTE** (a que o responsável leva): o mesmo cupom, mas **do cliente só vai o nome do responsável**. Não leva CPF, telefone, número do cadastro, idade, **sapateira** (1.09) nem assinatura: se o papel for perdido, ninguém fica com os dados do cliente. Nenhuma das duas vias leva a observação da criança (o OBS é só da equipe, seção 6q).

**O cupom do carrinho (1.09).** Quem leva só o carrinho (fusquinha, carrinho de bebê) recebe um cupom só do carrinho: o responsável, o carrinho na tabela com o valor e, embaixo dele, o tempo comprado, **a hora em que saiu e até quando devolver**, e o total pago. Não tem criança, número do cadastro, sapateira (mesmo que a criança do cadastro tenha uma marcada) nem nada da recreação. Com recreação e carrinho na mesma venda, o cupom traz a criança e os dois itens, com o total.

**O pacote e o comprovante de pagamento (1.10).** O pacote comprado sai na tabela com o **nome dele** e o valor; embaixo, as horas que ele dá ("2h de recreação"). Quem compra e **entra na hora** tem as horas do cliente no bloco da criança ("Entrada 16:05 · horas do cliente: 2h"). Quem compra **para usar depois** (ou leva só um produto) recebe só o **COMPROVANTE DE PAGAMENTO**, no jeito do comprovante do sistema anterior, mais enxuto: o título, a tabela, as horas guardadas embaixo do pacote ("Para EVA · horas guardadas: 2h"), o total pago com a forma, o número da venda e quem atendeu. Sem o bloco da criança, sem o número do cadastro e **sem a linha da assinatura** (não há o que assinar). Sai em duas vias; a do cliente, como sempre, sem CPF e sem telefone.

**O comprovante da festa (1.12 e 1.13).** As vendas da Festa (seção 6w) saem como **COMPROVANTE DE PAGAMENTO**, nas duas vias:

- a **festa nova**: a linha **FESTA 20 CRIANÇAS** e, embaixo, o tipo ("Festa de final de semana"), a data com o dia da semana e o horário ("Sábado, 21/11/2026 · das 10h30 às 13h30"), a duração ("3 horas · o espaço não fecha") e a cortesia dos adultos;
- o **upgrade** (1.13): a linha **UPGRADE DE FESTA** com o valor da diferença e, embaixo, "De 10 para 15 crianças", o tipo da festa e a data;
- o **adicional** (1.13): a linha **ADICIONAL DE FESTA** com a quantidade de crianças e, embaixo, "R$ 190,00 por criança";
- o total com a forma de pagamento, o número da venda e quem atendeu.

**A via da loja tem a linha da assinatura** (o responsável assina que ficou ciente das condições); a do cliente não leva CPF nem telefone. O comprovante não traz o bloco da criança, mesmo que ela esteja no espaço. No papel de 80 mm, uma festa com adicional gasta cerca de 100 mm na via da loja e 78 mm na do cliente; um upgrade, 84 mm e 62 mm; só o adicional, 80 mm e 58 mm.

**Na tela e no papel**: na tela, o cupom aparece com cara de cupom (papel amarelinho, a logo colorida, a ponta serrilhada), com as duas vias lado a lado. No papel ele sai **preto no branco**, com a logo em preto: a impressora térmica só imprime preto.

**Quando sai:**

- **cupom de entrada**: depois de cada atendimento registrado (a venda, com ou sem entrada) e depois de cada **Entrada** pelo botão (a criança que já tinha horas);
- **cupom da saída**: depois de **toda saída** (1.06: com ou sem pagamento; quem usa as horas do pacote também assina a saída). Traz "SAÍDA" e o nome da criança, a hora da entrada e da saída, quanto ficou, o tempo contado, o saldo de horas e, se houve, o que foi recebido e a forma. Em duas vias, com a linha da assinatura na via da loja;
- **cupom da devolução** (1.06): depois de **toda devolução** do que saiu da loja (carrinho de bebê, fusquinha), com ou sem valor a receber. Traz "DEVOLUÇÃO" e o item, a hora em que saiu e em que voltou, quanto ficou fora, o tempo comprado e, se houve, o tempo a mais e a forma de pagamento (1.09: sem a criança e sem o número do cadastro). Em duas vias, com a linha da assinatura na via da loja;
- **de novo, a qualquer hora** (1.14): na ficha de qualquer cliente, o botão **Reimprimir cupom** (ver abaixo).

**Reimprimir o cupom (1.14).** O cliente perdeu o cupom, ou a impressora travou: a equipe reimprime pela ficha do cliente.

- Um toque no cliente (na lista, na busca ou no Banco de horas) abre a ficha; nela, o botão **Reimprimir cupom**. Ele aparece para todo cliente, esteja a criança no espaço ou não.
- Abre a lista **dos últimos cupons do cliente**, do mais novo para o mais velho, com o dia, a hora, de quem é e o valor: "Cupom da saída · hoje, 18:24 · ALICE", "Cupom de entrada · hoje, 17:50 · ALICE · R$ 80 no Pix", "Comprovante da festa", "Comprovante de pagamento", "Cupom da devolução". Os cupons dos irmãos (mesmo responsável) vêm juntos.
- Um toque no cupom mostra as duas vias **iguais às que saíram** (a mesma hora, quem atendeu, os mesmos valores), com a linha **"Reimpressão · dia e hora"** no pé. **Imprimir as duas vias** manda de novo para a impressora; **Imprimir só a via do cliente** serve para quando o cliente só perdeu a via dele.
- **O que fica guardado:** todo cupom que o sistema gera (entrada, saída, devolução, comprovante), mesmo no aparelho que está em "Não imprimir". Fica guardado no servidor por **60 dias**, e qualquer aparelho da loja reimprime. Vale a partir da 1.14: o que foi impresso antes dela não está guardado.
- Para quem **já estava no espaço** quando a 1.14 chegou, a lista traz o "Cupom de entrada · como está agora", montado na hora (era o que o antigo botão Cupom fazia).
- No servidor: banco na versão 27 (tabela `papeis`; cada cupom ocupa menos de 1 KB). **Limpar os cadastros** apaga também os cupons guardados.

**O cupom sai sempre em pé (1.14).** Desde a 1.09 os cupons ficaram curtos, e a via que tinha menos de 80 mm de altura ia para a impressora como uma página "deitada" (mais larga do que alta): é isso que fazia o cupom **sair de lado**. Agora a página de impressão nunca tem menos de 82 mm de altura, para ficar sempre em pé. Na prática: as vias compridas não mudam; as curtas (a via do cliente, o cupom do carrinho) ganham alguns milímetros de papel em branco no fim.

**O que cada aparelho faz** fica guardado no próprio aparelho (não muda os outros):

- **Mostrar o cupom** (como vem): depois do atendimento a tela abre o cupom com as duas vias, e um toque em **Imprimir as duas vias** manda para a impressora;
- **Imprimir sozinho**: as duas vias vão direto para a impressora, sem abrir a tela. É o modo do computador do balcão;
- **Não imprimir**: para o celular e o tablet, que não têm a impressora. O cupom não aparece ali (nem o da saída, nem o da devolução): a saída que precisa da assinatura é dada no computador do balcão.

A escolha fica na própria folha do cupom, na linha "Neste aparelho: … · mudar", e em **Ajustes → Cupom impresso**.

**O que vai escrito** (só o Gestor muda): **Ajustes → Cupom impresso** → nome da loja, telefone e o aviso do pé. Desde a 1.06, embaixo do nome da loja sai a **unidade** (a da visita; seção 6v). No mesmo lugar, **Imprimir um cupom de teste** (saem as duas vias, com dados de exemplo).

**Imprimir direto, sem a janela de escolher a impressora (1.09).** Nenhum site consegue pular a janela de impressão do navegador: quem pula é o próprio navegador, quando é aberto com a impressão direta. O sistema entrega o atalho pronto. No computador do balcão, uma vez:

1. A Bematech MP-4200 TH precisa estar instalada no Windows como impressora (o programa dela, do fabricante), com o papel de 80 mm. Se o sistema antigo já imprimia nesse computador, ela já está.
2. Deixe a Bematech como **impressora padrão** do Windows (Configurações → Bluetooth e dispositivos → Impressoras e scanners) e desligue **"Deixar o Windows gerenciar a minha impressora padrão"**, para ela não trocar sozinha.
3. Entre no sistema como Gestor, vá em **Ajustes → Cupom impresso** e toque em **Baixar o atalho (Windows)**. Vem o arquivo **Play Space (imprime direto).bat**. Se o navegador perguntar, escolha manter o arquivo. Arraste o arquivo para a área de trabalho.
4. Abra o sistema **sempre por esse atalho** (dois cliques; na primeira vez o Windows pode pedir para confirmar: "Mais informações" → "Executar assim mesmo"). Ele abre numa **janela só do sistema**, com um perfil só dele: na primeira vez, entre com o usuário e escolha a unidade do aparelho.
5. Em Ajustes → Cupom impresso, toque em **Imprimir um cupom de teste**: as duas vias têm que sair sem perguntar nada. A mesma tela passa a dizer "Este computador abre o sistema pelo atalho da impressão direta".

Daí em diante, **Imprimir as duas vias** manda direto para a impressora. Para nem precisar desse toque, escolha **Imprimir sozinho** neste aparelho.

**O caminho fica à vista (1.11).** Enquanto o computador não abre o sistema pelo atalho, embaixo do botão **Imprimir as duas vias** aparece a linha **"A janela de impressão do Windows aparece? Veja como imprimir direto"**: um toque leva para Ajustes → Cupom impresso, onde está o botão **Baixar o atalho**. A linha aparece para o Gestor e o Supervisor, e some no computador que já abre pelo atalho. Lembrete: pelo Chrome comum (ou pela prévia) a janela do Windows **sempre** aparece, uma vez para cada via; só a janela aberta pelo atalho imprime sem perguntar.

O atalho abre o Chrome (ou o Edge, se não houver Chrome) com a opção `--kiosk-printing`, o endereço do sistema e um perfil separado (`%LOCALAPPDATA%\PlaySpace\Navegador`), para a impressão direta valer mesmo com outro Chrome aberto no computador. Sem o atalho o sistema funciona igual, mas a janela de impressão abre uma vez para cada via (é só confirmar as duas).

Se o cupom sair cortado no meio, com muito papel em branco no fim, ou se as duas vias saírem grudadas (sem corte entre elas), o acerto é nas preferências da impressora no Windows (tamanho do papel de 80 mm e corte no fim do documento). Mande uma foto do cupom de teste que eu ajusto a largura, o tamanho da letra e o da logo.

**Este cupom não é nota fiscal.** É o comprovante de entrada que o responsável assina (não tem CNPJ, nem chave, nem imposto). O acompanhamento de nota fiscal que existia na 0.93 (marcar "emitido" a cada pagamento) fica guardado no sistema, sem botão para ligar; se a loja passar a emitir nota fiscal pelo sistema, ele volta.

## 6t. Limpar os cadastros (começar do zero)

Para quando os testes acabam e a loja vai começar de verdade. Fica em **Ajustes → Limpar os cadastros**, e **só o Gestor** vê e faz.

**O que sai:** todos os cadastros (crianças e responsáveis, inclusive os que já tinham sido removidos, com as fotos), as horas compradas, as vendas e os pagamentos, as entradas e saídas, as entradas canceladas e o movimento do dia.

**O que fica:** a equipe e as senhas, a regra das horas, os serviços e preços, os pacotes, os produtos (o que tinha sido vendido volta para o estoque, porque a venda deixou de existir), a agenda, as sapateiras e os outros ajustes. Os números de cadastro recomeçam do 1.

**Como é:**

1. A folha mostra **quanto há para apagar** (cadastros, fotos, vendas e visitas) e avisa se há criança no espaço naquela hora.
2. O Gestor escreve **a própria senha** e toca em **Apagar todos os cadastros**. Com a senha errada, nada é apagado.
3. Antes de apagar, o servidor guarda uma **cópia de segurança do banco inteiro** (na pasta de dados, em `copias`, trancada como o banco; ficam as 3 últimas, mais a de que uma limpeza ou uma importação ainda depende para o Desfazer).
4. As outras telas abertas se atualizam sozinhas e ficam vazias. No dia fica a linha "Todos os cadastros foram apagados para começar do zero… por FULANO".

**Desfazer:** depois de apagar aparece, por alguns segundos, o botão **Desfazer**, e o cartão em Ajustes mostra a última limpeza com **Desfazer a limpeza**. Vale **enquanto não houver cadastro novo**: depois do primeiro cadastro (ou do teste do rodapé) não dá mais, para não misturar os dois. Se o sistema for atualizado para uma versão que mude o banco, a cópia antiga também deixa de servir para o Desfazer. A limpeza leva junto os cadastros que vieram de uma importação (seção 6u) e o registro dela; o Desfazer da limpeza traz os dois de volta.

**Na atualização para a 0.98:** a loja pediu para limpar os cadastros feitos nos testes. Por isso, na primeira vez que o Gestor abre o sistema depois de atualizar, a folha **Limpar os cadastros** aparece sozinha. **Nada é apagado sem ele confirmar com a senha.** "Agora não" fecha a folha e a oferta não aparece mais (a limpeza continua em Ajustes). Atendente e Supervisor não veem nada disso.

As cópias de segurança guardam os dados que foram apagados. Para sumir com elas de vez, apague os arquivos `copia-….sqlite` da pasta `playspace-dados/copias` (ao lado do `public_html`), pelo Gerenciador de arquivos do cPanel.

## 6u. Importar clientes e horas do sistema anterior

Para trazer de uma vez quem já era cliente e as horas que cada criança ainda tem. Fica em **Ajustes → Importar clientes e horas**, e **só o Gestor** vê e faz.

**A planilha:** uma linha por criança, com o nome dela. O resto entra se houver: responsável, CPF (ou RNE), telefone, outro telefone, observações e as horas. A primeira linha pode ser a dos títulos das colunas: o sistema reconhece "Criança", "Responsável", "CPF", "Telefone" ou "Celular", "Saldo", "Horas compradas", "Horas usadas", "Obs" e parecidos, e pula o cabeçalho de relatório que vier antes. Na folha há **Baixar um modelo de planilha**.

**Como entra:**

1. **Colar:** abra a planilha no Excel, selecione tudo (Ctrl+A), copie (Ctrl+C) e cole (Ctrl+V) no quadro da folha. Serve para qualquer planilha, inclusive o Excel antigo (.xls).
2. **Ou escolher o arquivo:** Excel (.xlsx), CSV (com ponto e vírgula ou vírgula) ou texto. PDF não serve: precisa ser a planilha.

**Conferir antes de importar:** a folha mostra quantos **cadastros novos** entram, o total de **horas**, quantos **já estão cadastrados** e quantas linhas ficam de fora (sem o nome da criança); os avisos (CPF que não confere, sem telefone, horas que não deu para ler, saldo negativo) e **como ficam os cinco primeiros**. A linha **Colunas** diz qual coluna virou o quê; **Conferir** abre para trocar e para dizer se os números das horas estão em **horas ou minutos**. Se faltar a coluna da criança, ou se os saldos parecerem estar em minutos, essa parte abre sozinha.

**As horas:** valem "5", "5,5", "5:30", "5h30" e "90 min". Se a planilha tem o **saldo**, é ele que vale; se tem **compradas e usadas**, o saldo é a diferença. Elas entram como um **pacote de valor zero** chamado PACOTE DO SISTEMA ANTERIOR: aparece no histórico do cliente e na última compra do Banco de horas, vale como pacote na entrada, **não expira** (seção 6r) e **não conta como venda do dia** nem gera cupom. Saldo negativo entra como zero. Mais de mil horas numa linha é tratado como coluna errada: a criança entra sem horas e a folha avisa.

**Quem já está cadastrado:** é a mesma criança (o mesmo nome) do mesmo responsável (o mesmo CPF; sem CPF, o mesmo telefone; sem os dois, o mesmo nome do responsável). O **cadastro não muda**. Se a planilha traz saldo para ela, **só as horas entram, e uma vez só**: quem já recebeu horas de uma importação não recebe de novo, então importar a mesma planilha duas vezes não dobra nada. Na folha dá para escolher **não mexer**.

**Repetidas na planilha** (a mesma criança em duas linhas): fica a primeira, ou a folha **soma as horas**, à escolha.

**O que acontece ao importar:**

- o servidor guarda uma **cópia de segurança** do banco antes da primeira linha (pasta `copias`, como na limpeza da seção 6t);
- as linhas vão em lotes de 400; uma planilha pode ter até 10 mil linhas. Se a internet cair no meio, o botão vira **Continuar a importação** e segue de onde parou, sem repetir ninguém;
- o CPF que o Excel deixou sem os zeros da frente volta inteiro; o telefone com DDD fica no formato da tela; os nomes ficam em maiúsculas (seção 6n);
- no dia fica **uma linha só**: "Importação do sistema anterior: N cadastros… por FULANO";
- o cadastro importado **não aparece em Falta finalizar**. Sem horas, ele entra como qualquer cadastro sem tempo comprado (a primeira venda libera). Sem CPF ou sem telefone ele entra assim mesmo, e o Gestor completa depois em **Ajustes → Cadastros**, onde quem veio pela planilha aparece marcado com "veio na importação".

**Desfazer a importação** (no cartão de Ajustes; vale para a **última** importação): saem os cadastros que vieram nela e **ainda não tiveram movimento** (venda, entrada, visita ou foto), com as horas que vieram junto; e as horas dadas a quem já estava cadastrado saem do saldo, se ainda estão inteiras e a criança está fora do espaço. O que já teve movimento fica. A folha mostra a conta (quantos saem, quantos ficam) antes de confirmar.

**Com muitos cadastros (1.01):** a lista do **Banco de horas** desenha os primeiros 150 clientes e pede a busca para os outros (a busca por nome, telefone ou CPF continua achando todos). O servidor passa a mandar a lista de cadastros **comprimida**: com 3 mil cadastros são uns 140 KB, em vez de 1,6 MB, a cada atualização da tela. A tela confere sozinha, ao entrar, se o servidor e o aparelho se entendem assim; se não se entenderem, tudo continua como era.

## 6v. Unidades (1.06)

A Play Space tem mais de uma unidade. O sistema já vem com **ANÁLIA FRANCO** e **MORUMBI**, e o Gestor cadastra as outras quando quiser.

- **Qual é a unidade deste aparelho:** na tela inicial, ao lado do título "No espaço agora", fica o botão com o nome da unidade (ex.: **ANÁLIA FRANCO**). Cada aparelho guarda a sua (computador do balcão, tablet, celular). Um toque abre **Unidade deste aparelho**, com as unidades para escolher. Aparelho que ainda não tem unidade mostra **Qual unidade?** em amarelo, até alguém escolher (1.08: sem o aviso ao entrar; o botão já pede).
- **Quem troca:** o Gestor e o Supervisor. O atendente só escolhe quando o aparelho ainda não tem unidade nenhuma (para a entrada não ficar sem unidade); depois disso, vê o nome e não troca.
- **O cadastro:** **Ajustes → Unidades** (só o Gestor). **Nova unidade** pede só o nome (fica em maiúsculas; não pode repetir). Um toque numa unidade muda o nome ou remove. Mudou o nome, muda em tudo o que já foi registrado nela. Unidade removida sai das escolhas, mas o que foi registrado nela continua com o nome; o aviso tem Desfazer. Não dá para remover uma unidade com criança no espaço.
- **O que fica marcado com a unidade:** a entrada da criança (e a visita, quando ela sai), cada venda, o que saiu da loja (carrinho, fusquinha) e cada linha de Hoje na recepção. O período que nasce sozinho (recreação avulsa, ou além do pacote) fica com a unidade da visita. A entrada pelo iDFace fica sem unidade (o aparelho da porta não diz em qual está).
- **Na lista** da tela inicial, a coluna **Unidade** mostra onde cada criança está (seção 6f). **No cupom**, a unidade sai embaixo do nome da loja.
- **No servidor:** o banco passa para a versão 25 (tabela `unidades`, e a coluna `unidade_id` em `criancas`, `visitas`, `vendas` e `registros`). O que já existia fica sem unidade (aparece com um traço).
- **O que ainda não é separado por unidade:** todos os aparelhos veem a mesma lista, com a coluna dizendo de qual unidade é cada linha. As sapateiras, a agenda, o estoque dos produtos e o resumo do dia ainda são um só para todas as unidades. Isso entra quando a segunda unidade for começar a usar o mesmo sistema.

## 6w. Festa (1.12 e 1.13)

No alto da tela inicial, o botão vermelho é **Festa**. Tudo da festa é vendido **só por ele**: não aparece no cadastro, nem no atendimento, nem na tabela de pacotes. São três coisas, na mesma folha:

- a **festa nova**, que é **festa de semana** ou **festa de final de semana** (a de final de semana vale também para férias, emenda e feriado);
- o **upgrade de festa** (1.13): o cliente já fechou uma festa e quer uma maior. Cobra só a diferença de uma para a outra;
- o **adicional de festa** (1.13): as crianças a mais do que o pacote. R$ 190 por criança.

**O caminho:**

1. **Festa** → a tela pergunta **de qual cliente é**: os três primeiros números do CPF do responsável (ou o nome dele, ou o da criança) já mostram os clientes, com as crianças de cada um. Um toque na criança escolhe. Para quem tem a data de nascimento no cadastro, a linha mostra o aniversário ("aniversário em 09/03"). **Cliente novo: cadastrar** abre o cadastro com o CPF escrito e, ao salvar, a Festa abre para ele.
2. A folha **Festa** abre com a escolha **Festa nova | Upgrade de festa** e, embaixo, a tabela **"Nossos valores"** em botões: uma linha para cada quantidade de crianças (10, 15, 20, 25 e 30) e duas colunas, **Festa de semana** e **Festa de final de semana**.
3. Embaixo da tabela, a linha **Adicional de festa**, com o **+** e o **−**.
4. **Data da festa** (opcional): só os números, as barras entram sozinhas, e o dia da semana aparece ao lado ("sábado"). Se a data cai num sábado ou num domingo, a festa de semana fica travada e a escolha passa para a de final de semana, com a mesma quantidade de crianças. Data que não existe ou que já passou não deixa seguir. Num dia de semana que é feriado, férias ou emenda, a equipe toca na coluna da festa de final de semana.
5. **Pagamento · R$ …** → a tela de pagamento de sempre (Pix, dinheiro, crédito e débito, em até três formas; o desconto é só do Gestor). **A festa é paga na hora**: não existe "paga na saída", mesmo que a criança esteja no espaço. Embaixo das formas a tela faz a conta das parcelas: "No crédito, em até 4x sem juros: 4x de R$ 1.079,75" (do valor que vai no crédito). A parcela é passada na maquininha; o sistema registra "crédito".
6. **Comprovante da festa**: abre com as duas vias, como os outros cupons (seção 6s).

**Festa nova.** Um toque no valor escolhe as duas coisas de uma vez: a quantidade de crianças e o tipo da festa. Outro toque no mesmo valor tira a escolha.

**Upgrade de festa (1.13).** É para quando o pai fechou a festa para 10 crianças e, perto do dia, quer para 15: paga só a diferença.

- Em **Upgrade de festa**, o primeiro toque é na **festa que o cliente já tem** (ela fica verde, com "já tem"). Daí em diante as festas maiores **do mesmo tipo** mostram só a diferença ("+ R$ 450", "+ R$ 900"…) e o resto fica apagado. O segundo toque escolhe a festa nova ("passa para"). A linha de baixo confirma: "Upgrade de 10 para 15 crianças, festa de semana: cobra só a diferença, R$ 450".
- **Se a festa do cliente foi vendida pelo sistema, ela já vem marcada** como a que ele tem (vale a festa vendida no nome de um irmão, e vale o último upgrade), com a data dela. Fica um toque só: o da festa nova. A folha avisa no alto: "No sistema, este cliente já tem a festa de semana para 10 crianças, em 21/11/2026".
- Quem fechou a festa **fora do sistema** (antes dele, ou no contrato de papel) não tem a festa marcada: a folha diz "O sistema não tem a festa deste cliente: confira no contrato a festa que ele já fechou", e a equipe toca nela.
- Para trocar a festa marcada como "já tem", um toque nela desmarca.
- O upgrade é sempre para uma festa maior, do mesmo tipo. Quem já tem a de 30 crianças usa o adicional de festa.
- **O que fica para o dono conferir:** a venda diz de quanto para quanto ("UPGRADE DE FESTA · de 10 para 15 crianças · festa de semana"). Quando a festa de origem não está no sistema, a venda leva "sem festa anterior no sistema"; quando o sistema tem outra festa para o cliente, leva "no sistema: festa de semana de 25 crianças". Essas anotações aparecem no histórico e em Hoje na recepção, não no comprovante do cliente.

**Adicional de festa (1.13).** É para quando o pacote foi de 10 crianças e vieram 13: as 3 a mais entram a R$ 190 cada. O **+** põe uma criança, o **−** tira. Pode ser vendido **sozinho** (no dia da festa), **junto com a festa nova** (o pai já fecha 10 crianças e mais 2) ou **junto com o upgrade**.

**Os valores** (os da tabela da loja, para as unidades Morumbi e Anália Franco):

| Crianças | Festa de semana | Festa de final de semana (e férias, emenda, feriado) |
|---|---|---|
| 10 | R$ 2.549 | R$ 2.849 |
| 15 | R$ 2.999 | R$ 3.299 |
| 20 | R$ 3.449 | R$ 3.749 |
| 25 | R$ 3.899 | R$ 4.199 |
| 30 | R$ 4.349 | R$ 4.649 |

Adicional de festa: R$ 190 por criança. Pela tabela de hoje, cada 5 crianças a mais num upgrade são R$ 450.

**O que a festa inclui** fica na própria folha, numa linha que abre, para a equipe dizer ao cliente: a duração (3 horas; o espaço não fecha para a festa), os horários (festa de semana: durante todo o dia, dentro do horário da loja; festa de final de semana, feriado, emenda e férias: das 10h30 às 13h30), os adultos (pai e mãe como cortesia, mais 4 adultos na hora do parabéns), as atividades, a alimentação, a papelaria, a lembrancinha (cupcake ou slime, para crianças de até 11 anos), o upgrade, o adicional e o pagamento (em até 4x sem juros no crédito; orçamento válido por 5 dias).

**O que fica registrado:** cada coisa vira uma venda, com o pagamento: "FESTA DE FINAL DE SEMANA · 20 crianças · festa em 21/11/2026", "UPGRADE DE FESTA · de 10 para 15 crianças · festa de semana" e "ADICIONAL DE FESTA · 3 crianças". Aparecem no histórico do cliente e em Hoje na recepção, e entram no caixa pela forma recebida. **Nada disso mexe nas horas do cliente, dá entrada ou muda a visita de quem está no espaço.** O botão **Desfazer** tira a venda e o pagamento. O atendimento comum que estava guardado em "Falta finalizar" para o mesmo cliente continua lá.

**Trocar o cliente**, no alto da folha, volta para a escolha do cliente e mantém o que já estava escolhido. Passar de "Festa nova" para "Upgrade de festa" (e voltar) guarda o que foi marcado em cada um, mas só entra na venda o que está na posição escolhida. Fechar a folha sem vender não guarda nada: a festa não vai para "Falta finalizar".

**No servidor:** a tabela é a de fábrica (está no programa, em `lib.php`; vai para a tela junto com a regra e não fica gravada no banco). O valor cobrado sai dessa tabela, não do que a tela mandar: se a tela mostrar outro valor, a venda não é feita ("O valor da festa para 20 crianças mudou para R$ 3.749. Feche e abra a Festa de novo."), e o mesmo vale para o upgrade e para o adicional. O servidor também recusa a data passada, o sábado ou domingo com a festa de semana, a quantidade fora da tabela, o upgrade para uma festa igual ou menor, a festa nova e o upgrade na mesma venda, mais de 60 crianças de adicional, a festa "para pagar depois" e a festa sem forma de pagamento. Para mudar os valores da festa, peça ao suporte (ainda não há tela para isso).

## 7. Atualizar

Botão **Atualizar** ao lado da versão. Ele baixa a versão nova do GitHub (ramo principal ou de trabalho, a mais nova), confere a versão dentro do arquivo e troca os arquivos de forma atômica. Não mexe em `~/playspace-dados/`. Se o GitHub demorar a refletir (cache de ~5 min), espere e toque de novo.

## 8. Se der errado

- **"Sem ligação com o servidor"** na tela de entrada: `api/api.php` não respondeu. Veja se a pasta `api/` subiu e se o PHP tem `pdo_sqlite`.
- **A importação parou no meio** (seção 6u): o que já entrou continua. Toque em **Continuar a importação**; se a folha foi fechada, importe a mesma planilha de novo: quem já entrou aparece como "já cadastrado" e não é repetido, nem recebe as horas duas vezes.
- **A planilha não é lida, ou as colunas saem trocadas:** copie as células no Excel e cole no quadro (é o caminho que sempre funciona), e confira a linha **Colunas** antes de importar. Arquivo .xls (Excel antigo) e PDF não abrem direto.
- **A Evolution recusou a chave**: a apikey está errada ou é de outra instância. Confira em Ajustes → WhatsApp.
- **iDFace "Sem contato"**: aparelho desligado, sem internet, ou o endereço push ficou errado no aparelho. Rode o conector de novo.
- **Mensagem do responsável não aparece**: o telefone no cadastro não bate com o número de onde veio (precisa dos últimos 8 dígitos iguais). Ajuste o telefone na ficha.
- **Os bonequinhos estão parados num computador**: as animações do sistema estão desligadas nele. Toque em **Animar os bonequinhos** na dica, ou escolha **Animados** em Ajustes → Aparência (seção 6d).
- Erros do servidor vão para o log de erros do PHP (cPanel → *Erros*) com o prefixo `[playspace]`.
