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
- **3 carrinhos fora da loja**: dois fusquinhas (um no primeiro período e um que já está no segundo: desde a 1.20 o fusquinha só sai e a conta fecha na devolução) e um carrinho de bebê, para ver as linhas com o botão **Devolveu** (seção 6o).
- Vendas e registros do dia e compromissos na agenda.

Elas se comportam como crianças de verdade: o tempo corre, os períodos da recreação avulsa nascem sozinhos, dá para dar saída, cancelar e vender. Nada vai para o iDFace. Nas crianças de teste o relógio não para na hora de fechar a loja, para o teste funcionar a qualquer hora.

Para tirar, o mesmo botão vira **Tirar o teste**: saem as 10 crianças e tudo o que nasceu delas (vendas, cobranças, carrinhos fora, linhas do dia, agenda). Os cadastros de verdade ficam. Só a gestora coloca e tira o teste; a equipe vê as crianças de teste na tela enquanto elas estiverem lá.

Na prévia (sem servidor) o mesmo botão existe e faz a mesma coisa na própria tela. Até a 0.97 o teste colocava 30 crianças no espaço (e 4 fora); a loja pediu só 10.

**Quem ficou de pagar depois, no teste (1.02):** das dez crianças, a ANA CLARA e o PEDRO ficaram de pagar na saída. As outras pagaram na entrada. Os dois fusquinhas do teste (da LARA e do GAEL) acertam na devolução, como todo fusquinha desde a 1.20. Desde a 1.06 a lista não mostra mais os selos PAGO / NÃO PAGO: o que ficou para pagar aparece na ficha ("A receber") e é cobrado na saída ou na devolução. As dez nascem na **unidade** do aparelho que pediu o teste (seção 6v).

## 3. Equipe e papéis (Ajustes → Equipe)

| Papel | O que faz |
|---|---|
| **Gestor** | Tudo: regra das horas, serviços, pacotes, produtos, equipe, iDFace. Pode remover crianças; **cadastro com horas guardadas, só o Gestor remove, e confirmando** (seção 6r). **É quem desfaz e cancela o que já tem valor** (1.21, seção 6z) e, desde a 1.26, **o único com o botão Desfazer** (seção 6ze). |
| **Supervisor** | Tudo da loja (horas, cadastro, agenda) e *vê* os Ajustes, mas não os altera. **Confere as entradas canceladas**, cancela uma entrada mesmo fora do prazo e **corrige a hora de uma saída esquecida**. Desde a 1.26 não tem o botão Desfazer (seção 6ze). |
| **Operador** (até a 1.20 chamava-se Atendente) | O dia a dia da recepção: cadastro, entrada, venda, saída, devolução, receber e agenda. Não vê Ajustes, não muda preço nem cadastro, não dá desconto por conta própria (pede ao gestor: seção 6zb). Cancela uma entrada sem cobrar só dentro do prazo (9 min), escrevendo o motivo. **Não desfaz o que tem valor** (1.21, seção 6z): a venda registrada só sai pelo cancelamento do Gestor, com o motivo. Desde a 1.26 não tem o botão **Desfazer** em nada (seção 6ze). |

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
- Qualquer pessoa da equipe (inclusive o Operador) marca entrada e saída. **Para o Gestor**, logo depois de cada uma aparece, por alguns segundos, o botão **Desfazer** (1.08: só o botão, sem texto; seção 6c). O Desfazer é para o toque errado e só vale nos instantes seguintes (o servidor aceita até 2 minutos); depois disso, tirar uma entrada é pelo **cancelamento com motivo** (seção 6e). **Desde a 1.26 o Desfazer é só do Gestor** (seção 6ze): o Operador e o Supervisor não têm o botão; a entrada errada eles cancelam pela ficha, no prazo, com o motivo e o nome da atendente.
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

**Editar ou remover um cadastro é só do Gestor**, em **Ajustes → Cadastros** (o nome e o telefone a equipe corrige pela ficha desde a 1.19: seção 6x): a busca acha por responsável, criança, telefone ou CPF; um toque abre o cadastro para mudar (nome, data de nascimento, responsável, telefones, documento, criança PCD, observações) e, no pé, **Remover este cadastro**. Atendente e Supervisor não mudam cadastro, nem pela tela, nem pelo servidor. A observação e a sapateira continuam com toda a equipe.

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

- Os serviços de fábrica são **Recreação** (R$ 80 a cada meia hora), **Carrinho fusquinha** (R$ 15 a cada 30 min) e **Carrinho de bebê** (R$ 30, das 10h às 22h). Em cada linha, o **+** põe mais um período (ou mais uma unidade) e o **−** tira; o valor da linha e o total aparecem na hora. Com algo escolhido, o botão do pé vira **Pagamento · R$ …**: um toque leva para a tela de pagamento (seção 6p), que grava tudo de uma vez; o aviso tem **Desfazer**. **O fusquinha é diferente (1.20):** a linha dele é **um toque só**, sem escolher o tempo e sem valor na hora ("R$ 15 a cada meia hora · acerta na devolução"); sozinho, o botão do pé vira **Registrar a saída · acerta na devolução** (seção 6o).
- O tempo de **Recreação** vendido entra no saldo de brincar da criança (meia hora conta) e libera o acesso dela; os carrinhos ficam só anotados na venda, com a duração.
- **Ajustes → Serviços e preços** muda o nome, o valor e a forma de cobrar (valor único ou a cada período de tantos minutos), cria serviços novos (uma meia, um lanche) e diz qual deles é tempo de brincar. O valor cobrado sai sempre dessa tabela.
- **Pacotes de horas**: no atendimento ficam numa linha só, **Lista de pacotes**, para a tela não ficar cheia. Um toque abre a lista (um pacote por linha: o tempo, o nome e o valor); outro toque escolhe, a lista fecha e o pacote escolhido fica à vista na própria linha. Para trocar, é abrir de novo. A tabela completa, com o valor da hora, fica no botão **Pacotes** da tela inicial (seção 6j).
- **Produtos** (Amigurumi, Maleta, Kit laço…): aparecem logo depois dos pacotes; um toque põe um na venda (seção 6l). **Outro item**, no pé do atendimento, registra algo fora da tabela.
- **Avisar quando restar** (Ajustes → Controle de horas) começa em 10 minutos, porque a recreação é vendida de 30 em 30.

## 6b. Banco de horas (aba)

A aba **Banco de horas** é a lista de todos os cadastros, em ordem alfabética do responsável (1.16), uma linha por criança:

- **o responsável e a criança** (um toque abre a ficha);
- o **saldo de horas** (**as horas não expiram**, seção 6r). Quem ainda não comprou aparece com **0h**;
- a **última compra**: o que foi, o valor e o dia (um toque abre o histórico do cliente). Para quem veio na recreação avulsa aparece "RECREAÇÃO avulsa" e o dia (1.18; o valor de cada visita está no histórico);
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
- **O que acontece sozinho também não**: o pacote que acaba, a entrada ou a saída dada em outro aparelho, a entrada cancelada por um operador. A lista mostra, e o sininho guarda o pacote que acabou e o cancelamento a conferir (ele fica vermelho na hora, em todas as telas).
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

- **Prazo**: até **9 minutos** depois da entrada (9 minutos cheios, contados do momento em que entrou), **o próprio operador cancela**. O prazo corre à vista: na linha da criança, na lista do espaço, aparece o atalho com um **X e o tempo que falta** (8:42), e a ficha mostra até que horas dá. Quando o prazo acaba, o atalho some sozinho.
- **Motivo obrigatório**: a folha "Cancelar a entrada" só aceita com o motivo escrito (há atalhos: "A criança chorou", "O responsável desistiu"…). Sem motivo, não cancela.
- **O que deixa de ser cobrado**: a folha lista as vendas desta visita (as de hoje, feitas depois da última saída). Já vem marcada a **Recreação desta entrada** (na recreação avulsa, 1.15, é o período que o sistema lançou: ele aparece travado, porque sai sempre junto com a entrada cancelada); carrinho e o que foi comprado mais cedo ficam desmarcados (continuam cobrados), e dá para marcar se também não forem cobrados. O que for marcado sai das vendas do dia e do saldo da criança; se o cliente já pagou, o valor é devolvido. Nenhum tempo é descontado e a sapateira fica livre.
- **Fora do prazo, ninguém cancela** (1.00): quando o prazo acaba, a opção some da ficha para todos, inclusive Gestor e Supervisor, e o servidor recusa o cancelamento. O período é cobrado, qualquer que seja o motivo. **Na ficha (1.02)** o cancelamento é só o botão **Cancelar entrada · 6:57**, com o tempo que ainda falta; quando o prazo acaba o botão some, sem deixar aviso nenhum.
- **A supervisão vê tudo**: cada cancelamento vira uma dica no alto da tela de Gestor e Supervisor ("João cancelou a entrada de Helena, R$ 80 não cobrado. Motivo: …") e o sininho fica vermelho na hora em todas as telas da supervisão (1.08: sem o aviso no pé da tela). O botão **Conferir** abre **Entradas canceladas**: quem, quando entrou, quanto ficou, o que não foi cobrado, o motivo e quem cancelou; **Marcar como visto** registra quem conferiu. A mesma lista abre em "Hoje na recepção" e em Ajustes → Regra das horas. O cancelamento também aparece na linha do dia (com a venda marcada "cancelada") e no **histórico do cliente**.
- Se os avisos automáticos estão em "Entrada e saída", o responsável recebe no WhatsApp que a entrada foi cancelada e nada foi cobrado.
- **Ajustes → Regra das horas → Cancelar a entrada sem cobrar** muda o prazo (5, 9, 10 ou 15 min) ou desliga o cancelamento (**Não cancela**).
- Com o iDFace ligado, o rosto lido nos 10 minutos seguintes ao cancelamento não vira entrada de novo (é a criança saindo).
- **Desde a 1.21 (a blindagem do operador, seção 6z):** quem não é o Gestor cancela a entrada, mas **não tira a cobrança do que já foi pago** (devolver dinheiro é do Gestor) **nem do que é coisa** (um produto, uma meia, um carrinho: saiu com o cliente): essas linhas vêm travadas na folha ("Continua cobrada: só o gestor tira esta cobrança"). O **tempo que ainda não foi pago** (a recreação avulsa da visita, o pacote ou a recreação "paga na saída") sai com a entrada, como sempre saiu: nada foi recebido, as horas saem do saldo e o cancelamento fica na lista, com o que deixou de ser cobrado. **O que continua cobrado e ainda não foi pago não some mais com a visita** (até a 1.20, a meia "paga na saída" saía da conta junto com a entrada cancelada, e nada mais a pedia): a folha avisa "Continua a receber: R$ 30 (MEIA)", a linha do dia diz "ficou a receber" e, cancelada a entrada, a tela **abre o recebimento**. Vale para todos os papéis. A lista que a supervisão confere passou a se chamar **Cancelamentos**: além da entrada cancelada, mostra a entrada desfeita na hora pelo operador, a saída do carrinho cancelada e a venda cancelada pelo gestor.

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
- **Na saída**, a conta é uma linha só: "RECREAÇÃO avulsa · 1h30 · ficou 1h12 · R$ 240". Escolhida a forma, **Recebi R$ 240 no Pix · dar saída**. O cupom da saída repete a linha, e Hoje na recepção anota "Ficou 1h12 · recreação avulsa 1h30: R$ 240 · recebido: Pix" (1.18: sem "debitados", porque na recreação avulsa o cliente não comprou período nenhum; "debitados · restam" é só de quem tem pacote).

**Como o valor é contado:** em períodos inteiros (meia hora), com a tolerância entre um e outro (9 min de fábrica; seção 6). Até 39 min, um período (R$ 80); até 1h09, dois (R$ 160); até 1h39, três (R$ 240). A criança PCD paga a metade; o voucher cobre meia hora (seção 6h). O único jeito de não cobrar é cancelar a entrada no prazo (seção 6e).

**Por baixo**, o sistema lança sozinho cada período que a visita passa a precisar (a receber), a cada poucos segundos enquanto houver tela aberta, e de novo na saída. Esses períodos **não viram linha em Hoje na recepção** nem aviso no sininho (são a conta normal da visita). Desfazer a entrada, ou cancelá-la, tira o que foi lançado.

**Os registros falam da visita, não de períodos (1.18).** Para a equipe, a recreação avulsa é uma coisa só: a criança entrou, o tempo correu e a visita foi acertada na saída. Por isso:

- **No histórico do cliente**, cada visita é **uma linha só**: durante a visita, "RECREAÇÃO avulsa · até agora 1h30 · ACERTA NA SAÍDA · R$ 240"; depois, "RECREAÇÃO avulsa · 1h30 · Pix · R$ 240" (com duas formas, "Pix R$ 100 + Dinheiro R$ 140"). Não aparece mais um "RECREAÇÃO · 0h30" para cada meia hora. O tempo **além do pacote** também vira uma linha por visita ("Além do pacote · 1h").
- **Quem não tem pacote não tem conta de horas**: no histórico, no lugar de "Horas compradas / consumidas / Saldo" fica só **Pagou ao todo** e **Visitas**; a criança não mostra "comprou / consumiu / saldo". Quem tem (ou já teve) pacote continua vendo tudo.
- **A comanda de quem está fora e não tem pacote** diz "Sem horas · recreação avulsa: acerta na saída" (antes: "0h guardado · comprou 1h30 ao todo"), e o selo é **Sem horas**. "Sem tempo" ficou só para quem tinha pacote e ele acabou.
- **No Banco de horas**, a última compra de quem veio na recreação avulsa aparece como "RECREAÇÃO avulsa" e o dia (antes: "RECREAÇÃO · 0h30 · R$ 80", que era só o último período).
- **O aviso da saída** (o que o leitor de tela diz, e o texto do Desfazer) acompanha: "BENTO saiu · recreação avulsa 1h30 · R$ 240 recebido (Pix)". Com o voucher: "0h30 do tempo que já tinha · recreação avulsa 0h30". Irmãos na recreação avulsa: "ANA e BETO saíram · recreação avulsa 1h30".
- **A folha de reabrir a visita** diz "tempo contado 1h30" no lugar de "1h30 debitados".

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
- **Não vai fazer agora** (o cliente desistiu): o **✕** da linha tira da faixa (para o Gestor, aparece o botão Desfazer; seção 6ze). Daí em diante o toque naquele cliente volta a abrir a ficha.
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
- O botão **Desfazer** que aparece depois da saída (os primeiros segundos) continua existindo, para o toque errado (desde a 1.26, só para o Gestor: seção 6ze).

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

**O fusquinha na mesma pegada da recreação avulsa (1.20).** A equipe não escolhe mais quanto tempo o cliente quer (ninguém sabe quanto ele vai ficar): **só dá a saída do fusquinha, e a conta fecha na devolução**, pelo tempo que ficou fora.

- **No atendimento**, a linha CARRINHO FUSQUINHA é **um toque só** (sem − e +): "R$ 15 a cada meia hora · acerta na devolução". Marcada, o botão do pé vira **Registrar a saída · acerta na devolução**. Não há pagamento nessa hora. Com outra coisa a pagar na mesma venda (um carrinho de bebê, um produto), o pagamento cobra só o resto, e o fusquinha aparece como "na devolução".
- **O cupom da saída** (o responsável assina a via da loja): "CARRINHO FUSQUINHA · Saída 14:05 · R$ 15,00 a cada 30 min, na devolução". Sem "R$ 0,00" e sem total.
- **Na lista**, o relógio corre desde a saída e embaixo aparece **até agora R$ 15**, **até agora R$ 30**… Não existe mais "até que horas" nem "passou do tempo" para o fusquinha. A comanda da criança diz o mesmo.
- **A conta** é em períodos inteiros do serviço (30 min, R$ 15 cada), com **a mesma tolerância da recreação** (9 min, em Ajustes → Controle de horas): o primeiro período conta sempre; até 39:00 é um período (R$ 15); passou disso, dois (R$ 30); depois de 1h09, três (R$ 45). O relógio da conta **para na hora em que a loja fecha** (e nunca passa de 12 h), como o da visita: um fusquinha esquecido na lista não vira cobrança a noite toda.
- **Devolveu** abre o **Pagamento da devolução** com a conta pronta: "CARRINHO FUSQUINHA · 1h · ficou 0h44 fora · 2 períodos de 30 min · R$ 15 cada · R$ 30". Só fecha com a forma de pagamento (Pix, dinheiro, crédito ou débito, em até três formas). Se a conta mudar com a folha aberta (nasceu mais um período), a tela avisa e abre de novo com o valor novo.
- **No histórico e no relatório do cliente fica uma linha só**: a saída ganha o valor e o tempo cobrado ("CARRINHO FUSQUINHA · 1h · R$ 30 · Pix"). Enquanto está fora, aparece como "acerta na devolução · fora da loja". Em **Hoje na recepção**: "CARRINHO FUSQUINHA · saiu da loja · R$ 15 a cada 30 min, acerta na devolução" na saída e "CARRINHO FUSQUINHA · devolvido · ficou 0h44 · 1h: R$ 30 · por JOÃO" na devolução, mais a linha do pagamento.
- **O Desfazer** da devolução (o botão que aparece por alguns segundos) põe o fusquinha de volta na lista, sem valor e sem o pagamento.
- **Cancelar sem cobrar:** nos primeiros minutos depois da saída (o mesmo prazo de cancelar a entrada: 9 min, em Ajustes), a folha da devolução mostra **Não usou? Cancelar sem cobrar**: saiu no nome da criança errada, o cliente desistiu. Precisa do **motivo**, e o cancelamento vai para a lista **Entradas canceladas** da supervisão e para o dia ("Saída das 14:05 de CARRINHO FUSQUINHA cancelada aos 3 min · nada foi cobrado · Motivo: … · por JOÃO"). **Passou do prazo, ninguém cancela** (nem o Gestor): o período é cobrado.
- **Um fusquinha por criança de cada vez:** com um fora, a linha do atendimento diz "já está fora da loja desde as 14:05" e não deixa registrar outro (nem vender tempo por cima dele, se o serviço for trocado para "Na venda").
- **O carrinho de bebê não muda:** valor único (R$ 30, das 10h às 22h), pago na venda (ou "Paga na devolução"), sem conta de tempo.
- **Quando a conta fecha é uma escolha do serviço.** Em Ajustes → Serviços e preços, o serviço marcado como **Sai da loja e volta** e cobrado **A cada período** tem a escolha **Quando a conta fecha**: **Na devolução** (de fábrica: é o que vale para o fusquinha desde a 1.20, sem ninguém precisar mexer) ou **Na venda** (como era até a 1.19: escolhe-se o tempo com + e −, paga na hora e, se passar, o tempo a mais é cobrado na devolução). O valor e o tamanho do período saem dessa tabela, na hora da devolução. A lista de serviços diz "a conta fecha na devolução" ao lado de quem está assim. **Trocar a escolha com um fusquinha fora não muda a conta dele:** quem saiu em aberto continua fechando na devolução, e quem comprou tempo continua com o tempo comprado.
- **O que já estava fora na hora da atualização** (um fusquinha vendido por tempo na 1.19) continua na regra antiga até ser devolvido: tempo comprado e, se passou, o tempo a mais.

O que vem abaixo vale para os carrinhos em geral; onde fala em **tempo comprado**, é a regra antiga do fusquinha (até a 1.19).

- **Quais serviços entram**: os que estão marcados como **Sai da loja e volta** em Ajustes → Serviços e preços (toque no serviço). O CARRINHO FUSQUINHA e o CARRINHO DE BEBÊ já vêm marcados. Um serviço novo (um patinete, por exemplo) entra na lista e ganha um filtro só de marcar a caixinha. O tempo de brincar (RECREAÇÃO) nunca entra.
- **A linha**: o ícone do carrinho, o responsável e a criança, o nome do serviço, o relógio contando desde a venda e o botão **Devolveu**. Com mais de uma unidade, aparece "2 × CARRINHO DE BEBÊ".
- **Com tempo comprado** (o fusquinha, vendido a cada meia hora): o relógio fica amarelo quando faltam 10 min e vermelho quando passa do tempo comprado, com o "passou 7 min" embaixo, também em vermelho (desde a 1.05 a linha não fica mais toda vermelha; na ficha, a linha do carrinho também não). Parando o mouse no relógio aparece até que horas vai, ou quanto passou. **Mais um período do mesmo fusquinha soma o tempo na mesma linha.**
- **Sem hora para voltar** (o carrinho de bebê, valor único): a linha só conta o tempo.
- **De outro dia**: se ninguém marcou a devolução, a linha continua na lista, no começo, com o dia em que saiu escrito em vermelho no lugar do relógio.
- **Devolveu**: um toque, na linha ou na comanda da criança. O carrinho sai da lista e fica anotado em **Hoje na recepção**: "CARRINHO FUSQUINHA · devolvido · ficou 0h44 · tempo comprado: 0h30 (passou 14 min) · por JOÃO". Depois do toque aparece o botão **Desfazer**, por alguns segundos (desde a 1.26, só para o Gestor: seção 6ze).
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

**Desconto, só para o gestor (1.04).** Como na tela anterior, o pagamento tem o campo **Desconto**; aqui, só o gestor vê. Operador e supervisor não dão desconto, e o servidor recusa o de quem não é gestor (desde a 1.23 eles veem o campo para **pedir** o desconto ao gestor: seção 6zb).

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

**Desde a 1.27 o cupom tem mais respiro** (seção 6zf): a logo voltou para o meio, grande, e as partes ganharam espaço entre elas. O que vai escrito em cada via continua o mesmo.

**VIA DA LOJA** (sai primeiro; é a que o responsável assina e fica com a loja):

- a logo, o **nome da loja**, a **unidade** e o telefone da loja; a faixa "VIA DA LOJA" com a **data e a hora**;
- o **responsável**, o **CPF** (ou RNE) e os **telefones** dele;
- **cada criança** dele que está no espaço (irmãos saem no mesmo cupom): o nome e a idade, o **número do cadastro** e a **sapateira**, a hora da **entrada** com o tempo comprado e até que horas ele vai (ou as horas do cliente, para quem tem pacote) e o **valor a cada 30 minutos**;
- **o pagamento, como um comprovante** (1.10): a tabela **Descrição / Qtd / Valor**, o subtotal e o desconto (só quando há desconto) e o **total já com a forma de pagamento** ("Total pago no Pix"; se dividiu, o total e uma linha para cada forma);
- uma linha pequena com o **número da venda** e **quem atendeu**;
- a **linha da assinatura**, com o nome e o CPF do responsável embaixo, quando há o que assinar (a entrada da criança, o carrinho que sai, a saída, a devolução);
- o **aviso** do pé ("Somente o responsável que assinar a entrada da criança poderá assinar sua saída"), seguido da **tolerância que o cliente lê: 5 minutos** (1.00). A conta do sistema continua com a tolerância da regra das horas (9 min): a diferença é a folga da loja para os dias de fila. Muda em **Ajustes → Regra das horas → No cupom, o cliente lê tolerância de** (ou "Não mostrar"). O aviso só sai quando há criança entrando.

**VIA DO CLIENTE** (a que o responsável leva): o mesmo cupom, mas **do cliente só vai o nome do responsável**. Não leva CPF, telefone, número do cadastro, idade, **sapateira** (1.09) nem assinatura: se o papel for perdido, ninguém fica com os dados do cliente. Nenhuma das duas vias leva a observação da criança (o OBS é só da equipe, seção 6q).

**O cupom do carrinho (1.09).** Quem leva só o carrinho (fusquinha, carrinho de bebê) recebe um cupom só do carrinho: o responsável, o carrinho na tabela com o valor e, embaixo dele, **a hora em que saiu**, e o total pago. **1.20:** o fusquinha não tem valor na saída: o cupom dele traz a hora em que saiu e "R$ 15,00 a cada 30 min, na devolução", sem tabela e sem total; na tela e na lista de **Reimprimir cupom** ele chama **Cupom do carrinho**. Não tem criança, número do cadastro, sapateira (mesmo que a criança do cadastro tenha uma marcada) nem nada da recreação. Com recreação e carrinho na mesma venda, o cupom traz a criança e os dois itens, com o total.

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
- **cupom da devolução** (1.06): depois de **toda devolução** do que saiu da loja (carrinho de bebê, fusquinha), com ou sem valor a receber. Traz "DEVOLUÇÃO" e o item, a hora em que saiu e em que voltou, quanto ficou fora e, no fusquinha (1.20), a linha do tempo cobrado ("CARRINHO FUSQUINHA · 1h · R$ 30,00", com "2 × 30 min · R$ 15,00 cada" embaixo) e a forma de pagamento (1.09: sem a criança e sem o número do cadastro). Em duas vias, com a linha da assinatura na via da loja;
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

**Imprimir direto, sem a janela de escolher a impressora (1.09; com instalador na 1.18).** Nenhum site consegue pular a janela de impressão do navegador: quem pula é o próprio navegador, quando é aberto com a impressão direta. O sistema entrega um **instalador** que deixa isso pronto: ele cria o ícone **Play Space** (com a logo) na área de trabalho, e é por esse ícone que a equipe abre o sistema. Aberto por ele, o sistema fica com cara de programa (uma janela só dele, sem abas nem barra de endereço) e o toque em **Imprimir** já manda o cupom para a impressora. No computador do balcão, uma vez:

1. A Bematech MP-4200 TH precisa estar instalada no Windows como impressora (o programa dela, do fabricante), com o papel de 80 mm. Se o sistema antigo já imprimia nesse computador, ela já está.
2. Deixe a Bematech como **impressora padrão** do Windows (Configurações → Bluetooth e dispositivos → Impressoras e scanners) e desligue **"Deixar o Windows gerenciar a minha impressora padrão"**, para ela não trocar sozinha.
3. Entre no sistema como Gestor, vá em **Ajustes → Cupom impresso** e toque em **Baixar o instalador (Windows)**. Vem o arquivo **Instalar Play Space (imprime direto).bat**. Se o navegador perguntar, escolha manter o arquivo.
4. Dê **dois cliques no arquivo baixado** (na primeira vez o Windows pode pedir para confirmar: "Mais informações" → "Executar assim mesmo"). Uma janela preta aparece por um instante. Ele cria o ícone **Play Space** na área de trabalho e abre o sistema numa **janela só do sistema**, com um perfil só dele: na primeira vez, entre com o usuário e escolha a unidade do aparelho.
5. Em Ajustes → Cupom impresso, toque em **Imprimir um cupom de teste**: as duas vias têm que sair sem perguntar nada. A mesma tela passa a dizer "Este computador abre o sistema pelo ícone da impressão direta".
6. Dali em diante, abra o sistema **sempre pelo ícone Play Space**. O arquivo baixado pode ser apagado (rodar de novo só recria o ícone).

Daí em diante, **Imprimir as duas vias** manda direto para a impressora. Para nem precisar desse toque, escolha **Imprimir sozinho** neste aparelho: o cupom sai sozinho no fim de cada atendimento.

**Se o ícone não aparecer** na área de trabalho (o Windows ou o antivírus não deixou criar), nada se perde: o sistema abre do mesmo jeito, e o próprio arquivo baixado serve de atalho, como era até a 1.17 (guarde-o na área de trabalho e abra o sistema por ele). Se o ícone aparecer com o desenho do Chrome no lugar da logo, a logo não pôde ser baixada naquela hora: rode o instalador de novo. O instalador foi conferido linha a linha, mas só o computador do balcão diz se o Windows dali deixa: se o ícone não aparecer ou o cupom não sair direto, mande uma foto da tela que eu ajusto.

**O caminho fica à vista (1.11).** Enquanto o computador não abre o sistema pelo atalho, embaixo do botão **Imprimir as duas vias** aparece a linha **"A janela de impressão aparece? Veja como imprimir direto"**: um toque leva para Ajustes → Cupom impresso, onde está o botão **Baixar o instalador**. A linha aparece para o Gestor e o Supervisor, e some no computador que já abre pelo ícone. Lembrete: pelo Chrome comum (ou pela prévia) a janela de impressão **sempre** aparece, uma vez para cada via; só a janela aberta pelo ícone Play Space imprime sem perguntar.

**O que o instalador faz** (e só isto): guarda a logo em `%LOCALAPPDATA%\PlaySpace\playspace.ico` (baixada do próprio site da loja, pelo `curl` do Windows), cria o atalho `Play Space.lnk` na área de trabalho (pelo PowerShell do Windows) e abre o sistema. O ícone abre o Chrome (ou o Edge, se não houver Chrome) com a opção `--kiosk-printing`, o endereço do sistema e um perfil separado (`%LOCALAPPDATA%\PlaySpace\Navegador`), para a impressão direta valer mesmo com outro Chrome aberto no computador. Não instala programa nenhum e não mexe no Chrome do dia a dia. Para desfazer, apague o ícone e a pasta `%LOCALAPPDATA%\PlaySpace`. Sem o ícone o sistema funciona igual, mas a janela de impressão abre uma vez para cada via (é só confirmar as duas).

**No Mac (1.18).** Não há instalador. Em Ajustes → Cupom impresso, o bloco **Este computador é um Mac** (só aparece no Mac) tem o botão **Copiar o comando do Mac**: abra o Terminal (Command + espaço, escreva Terminal), cole e aperte Enter. O sistema abre no Chrome, numa janela só dele, com a impressão direta na impressora padrão do Mac, e o comando deixa na Mesa o atalho **Play Space.command**: dali em diante, dois cliques nele abrem o sistema do mesmo jeito. Na primeira vez o Mac pode perguntar se o Terminal pode usar a pasta Mesa: aceite.

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
- **Quem troca:** o Gestor e o Supervisor. O operador só escolhe quando o aparelho ainda não tem unidade nenhuma (para a entrada não ficar sem unidade); depois disso, vê o nome e não troca.
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

## 6x. Corrigir o nome e o telefone pela ficha (1.19)

A equipe digitou um nome errado no cadastro e o cupom saiu com o nome errado: dá para acertar na hora, sem chamar o Gestor.

- Na ficha do cliente, **o nome do responsável** (o título), **o nome da criança** e **o telefone** têm um lápis pequeno ao lado: **um toque em cima abre "Corrigir o cadastro"**, já com o campo certo selecionado.
- A folha tem só quatro campos: **nome do responsável, nome da criança, telefone e outro telefone**. O CPF (ou RNE) e a data de nascimento continuam com o Gestor, em Ajustes → Cadastros.
- **Qualquer pessoa da equipe corrige** (operador, supervisor ou gestor). **Nada é apagado, e não existe botão de excluir**: remover um cadastro continua sendo só do Gestor, e mesmo lá o cadastro fica guardado no banco.
- **Vale para os irmãos, onde o dado estava igual:** o nome do responsável e os telefones são do cliente, então o que for corrigido muda também nas outras crianças dele (a folha avisa quais são). O nome da criança muda só nela. Duas proteções, para a correção nunca apagar o que a tela não mostrou:
  - **só o que mudou vai para os irmãos, e só no irmão em que estava igual.** Se o cadastro de um irmão tem outro telefone (o Gestor mudou só nele), esse telefone continua; a folha avisa: "O que está diferente no cadastro de PEDRO continua como está." Corrigir só o nome da criança não mexe em irmão nenhum;
  - **cadastro antigo sem CPF:** o que junta os irmãos é o telefone. Nesse caso a correção só acompanha quem tem também **o mesmo nome de responsável**: um telefone repetido em cadastros de pessoas diferentes não leva o nome de uma para a outra.
- **Fica anotado quem mudou:** uma linha em Hoje na recepção, por exemplo `Cadastro corrigido: responsável de "ANA SILVA" para "ANA SOUZA" · telefone de (11) 9 1111-1111 para (11) 9 2222-2222 · também em LUCAS · por CAMILA` (o "também em" diz em quais irmãos a correção entrou). A mesma linha aparece no relatório do cliente (seção 6y).
- **Telefone:** escrito só com os números, ele é arrumado no formato da tela, (11) 9 0000-0000. Número de fora do Brasil começa com `+`. Sem telefone completo a correção não salva. O telefone em que ninguém mexeu fica escrito como estava.
- **O cupom com o nome certo:** se a criança (ou um irmão) está no espaço e um nome mudou, a tela oferece **Imprimir o cupom**: é o cupom de entrada de novo, já com o nome corrigido. No aparelho que está em "Não imprimir" ela só confirma a correção. Os cupons que já tinham sido impressos continuam guardados como saíram; por isso, depois de uma correção, o **Reimprimir cupom** da ficha mostra no alto da lista o **Cupom de entrada · como está agora** (com o nome certo) de quem está no espaço, além do guardado.
- **Acompanhamento de nota fiscal** (o módulo da 0.93, que hoje fica guardado sem botão para ligar, seção 6s): se ele voltar a ser usado, o cupom que ainda está **a emitir** passa a mostrar o nome corrigido (o da criança e o do responsável); o que já foi marcado como emitido fica como saiu.

No servidor: ação `cadastro_corrigir` (nome, resp, tel, tel2), para qualquer usuário que entrou; não aceita mudar documento, nascimento, observação nem PCD. A resposta diz quantas coisas mudaram (`mudou`) e em quantos irmãos a correção entrou (`irmaos`).

## 6y. Relatório do cliente (1.19)

O extrato do cliente, no jeito do relatório do sistema anterior: o que ele comprou, cada visita e o saldo que ficou depois de cada movimento. Serve para conferir uma reclamação ("tiraram uma hora a mais"), para mostrar ao cliente e para guardar.

**Onde fica:** o botão **Relatório** na ficha do cliente, ao lado de Histórico, e no painel do histórico (à direita). O relatório é do **cliente**: o responsável e todas as crianças dele juntas.

**O que mostra:**

- **O período:** 30 dias, 90 dias, 12 meses (o que abre) ou Tudo; ou as datas **De** e **Até**, escolhidas à mão.
- **O resumo do período:** saldo no início, horas compradas, horas usadas e saldo no fim, e quanto o cliente pagou no período.
- **Os movimentos, do mais antigo para o mais novo.** Em cada linha: a data e a hora, o que foi, o detalhe (a criança quando há irmãos, o valor e a forma de pagamento, quem vendeu, a unidade) e, à direita, **as horas que entraram (+) ou saíram (−) e o saldo que ficou**.
  - **Compra de pacote** (e as horas que vieram do sistema anterior): soma as horas.
  - **Visita:** "entrou 14:05 · saiu 18:00 · ficou 3h55" e as horas que saíram do pacote. A visita na **recreação avulsa** não mexe no saldo: aparece com o tempo contado e o valor pago.
  - **Outras compras** (carrinho, produto, festa): aparecem com o valor, sem horas.
  - **Ocorrências:** saída corrigida, visita reaberta, entrada cancelada, desconto e cadastro corrigido entram como linha de aviso, com o texto que ficou em Hoje na recepção. É o que explica um acerto.
  - **No espaço agora:** quem está brincando aparece no fim, com a hora da entrada (e o que a recreação avulsa já soma, a acertar na saída).

**O saldo fecha sempre com o do sistema.** O saldo no fim de "hoje" é o saldo que a ficha mostra. Se os movimentos guardados não explicam esse saldo (um cadastro antigo, horas que vieram de antes), a diferença para mais aparece como **saldo no início**, e a diferença para menos vira a linha **Acerto de saldo** no fim do relatório, dizendo quanto falta explicar. Num cadastro em ordem essa linha não existe.

**Imprimir no cupom:** sai uma via na impressora do cupom (80 mm), com o alto da loja, o cliente, o período, o resumo e uma linha por movimento. Com o computador aberto pelo ícone da impressão direta (seção 6s), sai sem a janela de impressão. Cada movimento ocupa perto de 1 cm de papel: **com mais de 40 movimentos a tela avisa antes** ("São 120 movimentos: o cupom sai comprido…") e só imprime com o toque em **Imprimir assim mesmo**; um período menor ou a planilha gastam menos papel.

**Abrir em planilha:** baixa um arquivo `relatorio-nome-do-responsavel-AAAA-MM-DD.csv`, que o Excel abre com dois cliques: o resumo em cima e uma linha por movimento (data, hora, movimento, detalhe, criança, unidade, valor, pagamento, horas e saldo, estes dois em número).

No servidor: o relatório usa a ação `historico` com `tudo: 1`, que manda todas as vendas e visitas do cliente (até 5 mil de cada), a hora completa da entrada e a unidade de cada visita, a unidade de cada venda e as ocorrências (`ocorr`).

## 6z. Blindagem do operador (1.21)

Pedido do dono: um usuário do tipo operador, com acesso só ao que ele precisa, e que **não consiga desfazer a venda que ele mesmo gerou**. A preocupação é o desvio: vender, receber e apagar a venda.

**O papel.** O "Atendente" passou a se chamar **Operador** (em Ajustes → Equipe; quem já era atendente virou operador sozinho, sem mexer em nada). Ele faz o dia a dia da recepção: cadastro, entrada, venda, saída, devolução, receber, agenda. Não entra em Ajustes, não muda preço, pacote, produto, equipe nem cadastro, não dá desconto, não corrige a hora de uma saída. No cupom, "Atendente FULANO" continua sendo quem atendeu.

**Quem estorna.** "Estornar" é desfazer ou cancelar o que já tem valor: a venda registrada, o pagamento recebido, a saída e a devolução que receberam. De fábrica, **só o Gestor**. Em **Ajustes → Regra das horas → Quem desfaz ou cancela o que já tem valor** dá para escolher **Só o gestor**, **Gestor e supervisor** ou **Toda a equipe** (como era até a 1.20). Só o Gestor muda.

**O que muda para quem não estorna (o operador e, de fábrica, o supervisor):**

**Desde a 1.26 o botão Desfazer é só do Gestor (seção 6ze):** onde a tabela diz "aparece", vale para o Gestor; o operador e o supervisor não têm o botão em nenhuma das linhas.

| O que ele fez | O botão Desfazer | Se precisar mesmo voltar atrás |
|---|---|---|
| Venda com valor (paga ou a receber) | **não aparece** | o Gestor cancela a venda no histórico do cliente, com o motivo |
| Voucher (sem valor) | aparece, como antes | — |
| Entrada | aparece, mas **fica registrada**: a entrada sai, a linha "Entrada registrada" continua no dia e nasce a linha "Entrada das 14:30 desfeita na hora … por JOÃO"; vai para a lista de conferência | depois de 2 min, "Cancelar entrada" com o motivo (no prazo de 9 min) |
| Saída do fusquinha (acerta na devolução) | aparece, e também **fica registrada** | no prazo, "Cancelar a saída" na folha da devolução, com o motivo |
| Saída que recebeu pagamento | **não aparece** | se a criança voltou, **Reabrir visita** (o que foi pago continua pago); senão, o Gestor |
| Saída sem nada a receber (usou as horas) | aparece, como antes | — |
| Devolução que recebeu (o fusquinha, o tempo a mais) | **não aparece** | o Gestor |
| Devolução sem nada a receber (carrinho de bebê já pago) | aparece, como antes | — |
| Recebimento ("Receber") | **não aparece** | o Gestor |

Não é só a tela: **o servidor recusa** cada um desses pedidos de quem não estorna (resposta 403, "Só o gestor desfaz…"), mesmo que venham de uma tela antiga ou de fora da tela. E mais três fechaduras do lado do servidor:

- **Venda a receber só com hora certa de receber.** Quem não estorna só deixa uma venda sem pagamento quando a criança está no espaço (ou entra agora: a saída recebe) ou quando tudo o que tem valor sai da loja (a devolução recebe). Senão: "Escolha a forma de pagamento. Sem a criança no espaço, só o gestor deixa uma venda a receber." Na tela, **"Paga na devolução" só aparece quando tudo o que tem valor sai da loja**, para todos os papéis: um pacote vendido junto com o carrinho não era recebido na devolução.
- **A saída dele sempre fecha a conta.** Sem o total que a tela mostrou, vale como "nada a receber" e é conferido igual: se há o que receber, a saída não é dada.
- **A ação antiga `vender`** (das primeiras versões, com minutos e valor livres; a tela não usa mais) ficou só para o Gestor. E o Desfazer de uma venda só apaga as linhas do dia daquela venda (da mesma criança, de agora há pouco): não serve para apagar outra linha.
- **Cancelar a entrada** (no prazo, com o motivo) continua com o operador, mas ele não tira a cobrança do que já foi pago nem do que é coisa (produto, meia, carrinho); o tempo ainda não pago sai com a entrada. O que continua cobrado e não foi pago fica a receber, e a tela abre o recebimento (seção 6e).

**Cancelar uma venda (quem estorna).** No **histórico do cliente** (o painel da direita), cada compra feita pela equipe nos últimos **7 dias** ganha o link **Cancelar esta venda**. A folha mostra a venda, **o que devolver ao cliente** (por forma: "Pix R$ 30"), se saem horas do saldo e se o produto volta para o estoque, e pede o **motivo** (há atalhos). Confirmado: a venda sai da conta com o pagamento dela, as horas saem do saldo, o produto volta para o estoque, a linha do dia fica marcada "cancelada" e nasce a linha "Venda das 14:05 cancelada: MEIA (R$ 30) · devolver ao cliente: Pix R$ 30 · Motivo: … · por CAMILA". O Gestor faz isso de qualquer aparelho, inclusive do celular, sem precisar estar na loja. Não cancela: a recreação que a visita somou sozinha (é acertada na saída), as horas que vieram do sistema anterior, venda com mais de 7 dias e pacote cujas horas já foram usadas ("sobram 1h de 2h"). Pacote cancelado com a criança no espaço: a visita passa a dever a recreação avulsa. Para o operador, na venda da última meia hora, o histórico diz "Lançou errado? Só o gestor cancela esta venda."

**A lista de conferência (Cancelamentos).** Tudo o que foi cancelado ou desfeito sem cobrar fica numa lista só, com o motivo e o nome de quem fez, para Gestor e Supervisor conferirem e marcarem como visto (o sininho avisa na hora): a **entrada cancelada** no prazo, a **entrada desfeita na hora** por quem não estorna, a **saída do carrinho** cancelada ou desfeita e a **venda cancelada**. O operador não vê a lista.

**O que a 1.21 consertou da 1.20.** Cancelar a saída do fusquinha (1.20) fechava a "visita" da criança como se a entrada tivesse sido cancelada: o que ela tinha ficado de pagar na saída (uma meia "paga na saída") saía da conta, e a saída não cobrava mais. Agora cada cancelamento tem o seu tipo, e **só o cancelamento da entrada fecha a visita**; a conta do que há para receber só fecha na saída.

**O que o sistema não alcança.** Entrada que não é registrada, dinheiro recebido e lançado como Pix, voucher ou marca de PCD dados a quem pagou cheio: isso se pega com conferência (o fechamento por forma de pagamento contra o extrato do banco e da maquininha, a contagem dos vouchers de papel, a lista de Cancelamentos), não com trava de tela.

No servidor: `regra.estorno` (`gestor`, `supervisor` ou `todos`); `podeEstornar()` decide em `desfazer_venda`, `desfazer_entrada`, `desfazer_saida`, `devolver` (voltar), `receber` (voltar), `cancelar_entrada` e `atender`; a ação nova `venda_cancelar` (`venda`, `motivo`); o banco passa para a versão **29**: `cancelamentos.tipo` ('' = a entrada, `desfeita`, `aluguel`, `venda`) e `visitas.pag_de` (a saída que recebeu pagamento). A atualização faz isso sozinha, e os cancelamentos de fusquinha da 1.20 ficam com o tipo certo.

## 6za. Mais de uma criança, carrinhos com quantidade e o número do cadastro (1.22)

**O responsável veio com mais de uma criança.** No cadastro, embaixo dos dados da criança, há o botão **Salvar e cadastrar outra criança**: ele guarda esta e abre o cadastro de novo com o documento, o nome e o telefone do responsável já escritos, pedindo só a outra criança (o cartão "Cliente já cadastrado" mostra as que já foram salvas). Dá para repetir quantas vezes precisar. No fim, **Cadastrar** abre o atendimento da primeira; registrado o dela (a entrada ou a venda, depois da sapateira e do cupom), **o atendimento da próxima abre em seguida**, na ordem em que foram cadastradas. Quem ficar para depois continua em **Falta finalizar**, como qualquer cadastro do dia sem atendimento. Tocou em "cadastrar outra" e não há outra? No cadastro que abriu, **Não há outra criança: ir para o atendimento** leva ao atendimento de quem já foi salvo. A fila é só dessas crianças cadastradas juntas: um cadastro novo (de outro cliente) abre o atendimento dele, e o atendimento de outro cliente não puxa a fila; depois de 20 min parada, ela deixa de valer. Para quem já é cliente, o **histórico** ganhou o atalho **Outra criança** (na seção das crianças): abre o cadastro com os dados do responsável.

**Carrinhos com quantidade, de 1 a 4.** O carrinho de bebê e o fusquinha têm o **número com − e +**; cada toque (no nome ou no +) põe mais um, e o + para no 4.
- **Fusquinha (acerta na devolução):** com 2 ou mais, a linha diz "2 carrinhos · na devolução", o botão vira **Registrar a saída de 2 carrinhos**, a linha do dia e o cupom dizem "2 × CARRINHO FUSQUINHA … R$ 15 a cada 30 min, cada um", e a lista mostra **2 × CARRINHO FUSQUINHA** com a conta dos dois até agora. No **Devolveu**, a folha pergunta **Quantos voltaram agora** (− e +): a conta é períodos × valor × os que voltaram, e os outros continuam fora, com o relógio correndo ("1 de 2 × CARRINHO FUSQUINHA" na linha do dia e no cupom). Cada carrinho aparece no histórico do cliente com o tempo e o valor dele. Cancelar a saída no prazo cancela a saída inteira, dizendo quantos eram. Com fusquinha ainda fora, outra saída para a mesma criança continua barrada (devolva antes).
- **Carrinho de bebê (valor único):** a linha mostra o total e "2 × R$ 30"; continua uma venda só ("2 unidades"), e o Devolveu marca todos de uma vez.
- O serviço vendido por tempo comprado (o jeito antigo, pela opção do serviço em Ajustes) não muda: lá o número continua sendo o tempo.

**O número do cadastro.** Cada cadastro tem o seu número, único (é o mesmo que já saía no cupom, "Cadastro 123"). Agora ele aparece na **ficha** e no **histórico do cliente** ("Cadastro nº 123"), e **a busca acha por ele**: "123", "#123", "nº 123" ou "cadastro 123", na tela inicial e em Ajustes → Cadastros.

No servidor: em `atender`, o fusquinha em aberto cria uma venda por carrinho (todas com a mesma linha do dia); `alugueis()` devolve `q` = quantos ainda estão fora; `devolver` aceita `quantos` (os que voltam agora) e responde `ficam`; `aluguelExtra` devolve `q`, `de` e o valor já multiplicado. O que sai da loja com valor único para em 4 por venda (`ALUG_MAX`). O banco não muda de versão.

## 6zb. Pedidos de autorização e nota fiscal (1.23)

O que só o gestor faz (dar desconto, autorizar a nota fiscal) e o que só quem estorna faz (cancelar uma venda), o operador **pede**. Há dois jeitos de autorizar, e os dois ficam guardados:

- **Um toque na tela de quem autoriza.** O pedido aparece sozinho, em poucos segundos, num **cartão** por cima de qualquer tela (no computador, embaixo à direita; no celular, no alto), com um bipe e o número na aba do navegador: quem pede, o que pede, o cliente e o motivo. **Liberar** (ou **Negar**) é um toque. O cartão chega mesmo com uma folha aberta; dá para recolher. A tela precisa estar aberta e com o gestor logado (no celular dele, por exemplo): não há aviso com o sistema fechado.
- **A senha de quem autoriza, no aparelho de quem pediu.** O gestor está ao lado: digita a senha dele ali e vale na hora. O campo esconde o que é digitado e não é tratado como senha pelo navegador (ele não oferece para guardar). Oito senhas erradas seguidas param os pedidos com senha daquele usuário por 15 minutos.

**Desconto.** Na tela de pagamento (do atendimento e da saída), o operador vê **Desconto · o gestor autoriza**: escreve o valor (em R$ ou %) e o motivo e toca em **Pedir ao gestor**, ou o gestor digita a senha em **Liberar com a senha**. Enquanto espera, o total continua cheio. Liberado, a tela que estava aberta muda sozinha: "Desconto de R$ 10 liberado por CAMILA", e o total cai. O desconto liberado vale **até o valor liberado, para aquele cliente (ou os irmãos), por 30 minutos e uma vez** (a saída em conjunto reparte entre os irmãos). A linha do dia diz quem deu e quem autorizou, e o desconto fica no nome de quem autorizou. Para o gestor nada muda: ele dá o desconto direto.

**Cancelar uma venda.** No histórico do cliente, o operador tem **Pedir o cancelamento** (com o motivo). Quem estorna recebe o pedido e, ao **Liberar o cancelamento**, a venda é cancelada na hora, no nome dele, com o motivo de quem pediu; os dois são avisados do que devolver ao cliente. Com a senha de quem estorna, a venda é cancelada ali mesmo. Vale o mesmo que a seção 6z (vendas da equipe dos últimos 7 dias, horas não usadas).

**Nota fiscal (NF).** No histórico do cliente, cada compra com valor tem o **NF**. A folha traz em nome de quem a nota sai (o responsável e o CPF do cadastro; dá para trocar por outro nome, um CNPJ, e pôr um e-mail). **Só o gestor autoriza**: com a senha ali, ou pela tela dele (**Pedir ao gestor**); logado, ele autoriza com um toque. Autorizada, a folha mostra os **dados para emitir** (com **Copiar os dados**) e o campo **Número da nota emitida**: quem emitir marca o número, e a compra passa a mostrar "NF nº 123". O gestor corrige o número ou desfaz a marcação. **O sistema guarda o pedido, a autorização e o número; ele não emite a nota** (a emissão continua sendo feita no emissor da loja ou da prefeitura). Se uma venda com nota emitida for cancelada, a linha do dia avisa: "TEM NOTA FISCAL EMITIDA (Nº 123): CANCELAR A NOTA TAMBÉM". A recreação avulsa que a visita soma sozinha ainda não tem o NF.

**Conferência.** Em **Hoje na recepção → Autorizações**: os pedidos que esperam, as **notas fiscais a emitir** e os últimos pedidos, cada um com quem pediu, quem liberou e **como** (toque ou senha). Gestor e supervisor veem todos; o operador, os dele.

No servidor: tabela `pedidos` (banco **30**), com o tipo (`desconto`, `cancelar_venda`, `nf`), o estado (`pendente`, `liberado`, `usado`, `negado`, `cancelado`), quem pediu, quem decidiu, quando e como. Ações `pedido_criar` (com `senha`, vale na hora), `pedido_decidir`, `pedido_cancelar`, `nf_emitida` e `pedidos_lista`. O desconto de quem não é gestor vai com `autorizacao` (o pedido liberado) e o servidor confere tudo de novo. O `pulso` leva os pedidos de cada tela (`ped`), e a tela continua perguntando por eles mesmo com uma folha aberta. Uma venda apagada (cancelada, desfeita) solta os pedidos dela por um gatilho: o número de uma venda apagada pode voltar numa venda nova, e ela não herda nada.

## 6zc. A lista da tela inicial (1.24)

- **Sem o "saiu às".** O carrinho (de bebê) não mostra mais "saiu às 16:47" ao lado do relógio: a hora em que saiu já está na coluna **Entrou**. O carrinho que saiu em outro dia diz **de outro dia** (o relógio mostra a data). O fusquinha continua com "até agora R$ 30", e a recreação com "até 18:35" ou "passou 3 min".
- **Colunas redistribuídas.** A linha pequena (até quando, passou, até agora) fica **embaixo do relógio**, na coluna Tempo; o espaço que ela ocupava foi para **Serviço** e **Unidade**, que não cortam mais o RECREAÇÃO com o AVULSA/PACOTE nem o nome da unidade. Sem nada embaixo, o relógio fica no meio da linha.
- **Tela mais estreita** (ou com o histórico aberto ao lado): as colunas ficam alinhadas de uma linha para a outra; com menos espaço ainda, a coluna **Unidade** sai (a unidade deste aparelho está no alto da tela). Quando a lista mistura unidades, a criança que está na **outra** unidade mostra o nome dela na própria linha ("HELENA · MORUMBI"), inteiro; as da mesma unidade não repetem. No celular, nessa linha a unidade fica no lugar do serviço.

## 6zd. A conta do responsável, o nome de quem cancela, o painel da gestora e os finalizados do dia (1.25)

**A conta do responsável.** Quando o mesmo responsável tem mais de um serviço aberto (as crianças no espaço e o que saiu da loja: o carrinho de bebê, o fusquinha), ele paga tudo **numa conta só**. Tocar em **Saída** de qualquer criança dele, ou em **Devolveu** de qualquer carrinho dele, abre a soma de tudo ("Pagamento da saída e da devolução"), cada item numa linha, com o total e uma forma de pagamento. Cada linha tem o **Tirar**: o item tirado fica em "Fora desta conta" (com o **+** para pôr de volta) e é cobrado à parte, depois; a conta nunca fica vazia. O botão diz o que vai acontecer: "Recebi R$ 110 no Pix · saída de SOFIA · devolver CARRINHO FUSQUINHA". Um cupom só (com a saída e a devolução) e um **Desfazer** só (para quem estorna).

- O carrinho entra com o que ele tem a pagar se voltar agora: o tempo do fusquinha (períodos × valor × quantos), o tempo a mais do que foi vendido por tempo, e o que ficou para pagar na devolução. O carrinho que ficou para pagar **na saída** da criança dona dele já está na conta dela: ele aparece como "cobrado na saída de …" e **não é cobrado duas vezes**. Se a criança for tirada da conta, o carrinho passa a cobrar o que é dele.
- O fusquinha que saiu com mais de um carrinho e voltou **só em parte**, ou que **não foi usado** (cancelar no prazo), tem o link "**abrir só este**", que abre a folha da devolução dele, como antes.
- O desconto do gestor vale sobre as saídas (a devolução continua sem desconto). O carrinho que sai em **Adicionar serviços**, na própria saída, fica fora da conta (o cliente leva).
- No servidor, primeiro as saídas (cada criança com a parte dela do pagamento), depois as devoluções (cada carrinho com a dele). Se a conta mudou com a folha aberta e algo não foi feito, a tela diz o que foi feito e o que faltou.

**O nome de quem cancela.** O aparelho da recepção fica no usuário do operador, e quem está no balcão muda ao longo do dia: por isso **todo cancelamento pede o nome da atendente** (obrigatório): cancelar a entrada, cancelar a saída do carrinho, cancelar uma venda (ou pedir o cancelamento), o **"não vai fazer agora"** de "Falta finalizar" e o **Desfazer** que fica registrado (o da entrada e o da saída do fusquinha, de quem não estorna). Os nomes já usados no aparelho aparecem embaixo do campo, para escolher com um toque. O gestor não escreve: é ele mesmo. O nome vai no cancelamento e na linha do dia ("por MARIA (login João)"). O cancelamento de uma venda pedido pela atendente e liberado pela gestora fica "CAMILA, a pedido de MARIA (login João)". Um aparelho que ainda estiver com a tela antiga aberta (de antes da 1.25) cancela no nome do login; recarregando a página, passa a pedir o nome.

- **"Não vai fazer agora"** (o cliente desistiu do atendimento aberto, ou do cadastro pela metade) agora fica registrado como **atendimento cancelado**, com o que estava escolhido e o motivo (se quiser: "O cliente desistiu", "Volta outro dia"…). O Desfazer tira o registro.

**O aviso da gestora.** Cada cancelamento feito pela equipe **chega na tela da gestora, na hora** (em poucos segundos, mesmo com uma folha aberta), com o nome da atendente, o que foi cancelado, o cliente, o motivo e a unidade. Fora do painel, ele aparece no **cartão** dos pedidos (com o bipe); no painel, na parte "Para aprovar". **Visto** tira o aviso (e marca o cancelamento como conferido na lista de Cancelamentos). Os que já estavam lá quando a tela abriu ficam no painel e no sininho, sem pular no cartão. A tela precisa estar aberta com a gestora logada.

**O painel da gestora.** Quem é **gestor** abre o sistema no **Painel** (a primeira aba), numa tela só:
- **Para aprovar:** os pedidos de autorização (desconto, cancelamento de venda, nota fiscal), com **Liberar** e **Negar**, e os cancelamentos que ela ainda não viu, com **Visto**; e os atalhos para Autorizações e Cancelamentos. O número na aba do Painel diz quantos esperam.
- **Hoje em cada unidade** (desde a meia-noite): quantas **crianças estão no espaço agora** (quantas passaram do tempo, quantos carrinhos estão fora), o **faturado hoje** (o que foi vendido, já com o desconto, e quantas vendas), o **recebido hoje** por forma (Pix, dinheiro, crédito, débito), os descontos e os cancelamentos. Com mais de uma unidade, um total das duas no alto. A unidade é a da venda (o aparelho que vendeu; a recreação avulsa fica com a unidade da entrada); o que não tem unidade (o iDFace, o que é de antes da 1.06) aparece à parte.
- **Lista do espaço** e **Hoje na recepção**, para seguir.

A lista continua na aba **Espaço**. Num aparelho em que a gestora prefere abrir na lista, em **Ajustes → Aparência → Ao abrir, neste aparelho: Lista do espaço**. Ao recarregar a página numa aba (o endereço termina em #horas, por exemplo), a tela continua nela. O operador e o supervisor não têm o painel.

**Finalizados do dia.** No filtro da lista (ao lado de RECREAÇÃO, CARRINHO FUSQUINHA, CARRINHO DE BEBÊ), o botão **Finalizados do dia** mostra todo mundo que saiu hoje, do mais novo para o mais velho: a hora em que entrou e saiu, quanto ficou, o que a visita vendeu e o valor (com o que ainda falta receber, se houver); e o que **voltou para a loja hoje** (o carrinho, o fusquinha), com a hora em que saiu e voltou e o valor. Tocar no nome abre a ficha do cliente (o histórico, os cupons). Quem ainda está no prazo de reabrir tem o **Reabrir** ali também. O "Saíram há pouco" continua.

No servidor: banco **31**, com o nome da atendente e a unidade em `cancelamentos` (colunas `atendente` e `unidade_id`) e o tipo novo `atendimento` (o "não vai fazer agora"), e os índices das contas do dia. As ações de cancelar recebem `atendente` (obrigatório para quem não é gestor): `cancelar_entrada`, `desfazer_entrada` e `desfazer_venda` (quando ficam registrados), `devolver` com `cancelar`, `venda_cancelar`, `pedido_criar` (cancelar venda: o nome vai no pedido) e `pendente_tirar` (que agora registra o atendimento cancelado; `voltar` com `cancelamento` tira). `dados` leva `avisos` (só para o gestor), `painel` (só para o gestor) e `finalizados`; o `pulso` leva `av` (os avisos), como leva os pedidos. Os itens a receber de cada criança (`pendi`) levam o número da venda (`id`).

## 6ze. O Desfazer só na conta do gestor (1.26)

O botão **Desfazer** (o que aparece embaixo por alguns segundos depois de cada ação) agora é **só do gestor**. O operador e o supervisor não veem o Desfazer em nada: a entrada, a saída, a devolução do carrinho, a venda, o recebimento, o voucher, a troca de sapateira, o "não vai fazer agora" e o "Desfazer esta marcação" do cupom fiscal. Para eles, a ação é feita e a tela mostra o resultado, sem o botão embaixo (os avisos de erro e os botões como **Ver o histórico** continuam).

Quem não é gestor e errou: a entrada errada ainda se cancela nos primeiros minutos, pela ficha, com o motivo e o nome da atendente (a gestora recebe o aviso), enquanto **Cancelar a entrada sem cobrar** não estiver em "Não cancela". A saída dada por engano volta pelo **Reabrir**, no prazo (seção 6m: sem o iDFace, qualquer pessoa da equipe reabre). A venda com valor sai pelo pedido de cancelamento (a gestora libera com um toque). O pagamento recebido por engano, a gestora cancela no histórico do cliente, com o motivo.

O Desfazer do gestor vale para o que ele mesmo fez, no aparelho dele e nos segundos seguintes: não alcança o que foi feito no aparelho do operador. A devolução marcada no carrinho errado não tem como voltar para a lista (nem pela gestora): confira o carrinho antes de tocar em **Devolveu**.

No servidor, as ações de desfazer (`desfazer_entrada`, `desfazer_saida`, `desfazer_venda`, `devolver` e `receber` com `voltar`, `pendente_tirar` com `voltar` e `cupom_voltar`) recusam quem não é gestor ("Só o gestor desfaz"), inclusive quando o pedido vem de uma tela antiga que ficou aberta num aparelho. A regra aceita `desfazer: "todos"` (o jeito da 1.25), que não aparece em Ajustes e nenhuma ação grava: existe para os testes dos roteiros antigos (tabela `config`, chave `regra`).

## 6zf. O comprovante mais bonito (1.27)

Pedido do dono: o comprovante não precisa só economizar papel; tem de ser bonito, com o castelo no meio e espaço entre as coisas. O que mudou foi **só o desenho**: o texto de cada via, a ordem das linhas e o que vai na via da loja e na do cliente continuam como na seção 6s.

- **O alto centralizado:** o castelo no meio, grande (22 mm), com o **nome da loja** embaixo, em letras espaçadas, a **unidade** e o telefone.
- **Respiro entre as partes:** a faixa da via (VIA DA LOJA / VIA DO CLIENTE, com a data), o responsável (o nome em destaque), cada criança, a tabela do pagamento, o total, quem atendeu, a assinatura e o aviso ficam separados por espaço e por um tracejado fino.
- **O total em destaque** (letra maior, com um traço cheio em cima), como num comprovante de pagamento.
- **A assinatura com mais lugar para a caneta** (10 mm em branco, linha cheia), com o nome e o CPF do responsável centralizados embaixo.
- **O pé centralizado** (quem atendeu, o número da venda, o aviso), e a via do cliente termina com um traço curto no meio.

**Quanto papel:** uma via comum passa de uns 8,5 cm para uns 12,5 cm (a entrada: de 84 para 121 mm na via da loja e de 58 para 96 mm na do cliente; a saída: de 86 para 128 mm e de 64 para 107 mm; o comprovante do pacote: de 66 para 104 mm). O relatório do cliente no cupom ganhou só o alto novo e um pouco de espaço (uns 5 cm a mais num relatório de 9 movimentos).

Vale para todos os comprovantes (entrada, saída, conta do responsável, venda e pacote, devolução do carrinho, festa, relatório do cliente e o cupom de teste) e também para os **cupons guardados antes** da 1.27: na reimpressão eles saem com o desenho novo. Na tela, a folha do cupom (o papel amarelinho) mostra o mesmo desenho. Nada muda em Ajustes → Cupom impresso nem na impressão direta.

## 6zg. As telas da gestora: Aprovações, o painel e a unidade (1.27)

**Aprovações.** O gestor ganhou uma aba só para responder: **Aprovar** (no computador, na barra da esquerda; no celular, na barra de baixo), com o número do que está esperando. Nela aparece só o que espera a resposta dele, em cartões grandes: os **pedidos** (desconto, cancelamento de venda, nota fiscal), com **Liberar** e **Negar**, e os **cancelamentos da equipe** que ele ainda não viu, com **Visto**. Um toque resolve; quando não há nada, a tela diz "Tudo em dia". As Aprovações são sempre de **todas as unidades** (cada cartão diz de qual é): nenhum pedido fica escondido. O cartão que pula no canto da tela continua nas outras abas, e as regras de antes não mudaram (quem pede, quem decide, a senha do gestor no aparelho de quem pediu).

**No celular.** Pelo telefone, o gestor entra e, **havendo pedido esperando, já cai nas Aprovações**; sem nada esperando, abre no Painel. Em **Ajustes → Aparência → Ao abrir, neste aparelho** dá para fixar: **Painel**, **Aprovações** (abre sempre nelas) ou **Lista do espaço**. No celular, o "Para aprovar" do painel vira um botão só (**Abrir Aprovações**), para o painel não ficar comprido.

**O painel.** A aba **Painel** virou o resumo do dia da operação (desde a meia-noite):

- os números: **no espaço agora** (e quantos passaram do tempo), **entradas de hoje** (e quantos já saíram), **carrinhos fora da loja**, **faturado hoje** (e quantas vendas), **caixa do dia** (o recebido) e **descontos** (com os cancelamentos do dia);
- **Entradas por hora**: um gráfico de colunas, com a hora de agora marcada e o pico do dia; passando o mouse (ou tocando) numa coluna aparece o número; "Ver em tabela" mostra os mesmos números;
- **Caixa do dia**: o recebido por forma de pagamento (Pix, dinheiro, crédito, débito), em barras;
- **Faturado por serviço**: o que foi vendido hoje, em barras: a recreação e cada carrinho pelo nome; os **pacotes de horas**, os **produtos** da loja e as **festas**, cada um num grupo só (o pacote é a venda de horas sem nome de serviço ou com o nome começando por PACOTE; cada venda fica com o nome que o serviço tinha na hora dela);
- **Últimas entradas e saídas** (o toque abre o histórico do cliente) e **Últimos cancelamentos** (quem cancelou, o quê e o motivo; o toque abre a lista de conferência);
- no alto, **Para aprovar**, com os pedidos ali mesmo (no computador) e o atalho **Abrir Aprovações**.

**A unidade.** Com mais de uma unidade, o painel tem no alto um seletor grande: **ANÁLIA FRANCO | MORUMBI | TODAS** (cada botão já diz quantas crianças estão lá). Tocar em uma troca **a tela inteira**: todos os números, os gráficos e as listas passam a ser daquela unidade. Em **TODAS** os números são a soma, e aparece a tabela **Cada unidade hoje**, para comparar (tocar no nome leva para a unidade). O seletor é só o que o painel **mostra** e fica guardado no aparelho (começa na unidade dele); ele **não muda a unidade em que o aparelho registra** as entradas e as vendas, que continua no botão ao lado do título da lista. O que não tem unidade (o iDFace, o que é de antes da 1.06) entra em TODAS e, quando existe no dia, ganha o botão **SEM UNIDADE**.

No servidor: `dados.painel.unidades[]` ganhou `servicos` (`[{ n, q, v }]`: o nome, quantas vendas com valor e o total de hoje, do maior para o menor). O resto do painel sai do que a tela já recebia (quem está no espaço, os finalizados do dia, os cancelamentos). Sem mudança no banco. Neste aparelho: `ps-painel-uni` (a unidade do painel; 0 = todas) e `ps-inicio-gestor` (`painel`, `aprovar` ou `horas`).

## 6zh. O menu lateral aberto (1.28)

Pedido do dono: um menu parecido com o do sistema antigo, que a equipe conhece. Das três maquetes ele escolheu o **formato 1: o menu lateral aberto**, com tudo escrito, em grupos, e os filtros da lista (que eram a faixa horizontal) como itens de um grupo.

**Onde aparece.** No computador com a janela larga, de **1280 px para cima** (o computador do balcão). No lugar do trilho de ícones fica o menu aberto, com:

- no alto, a **marca** (tocar leva para a lista de quem está no espaço) e o **cartão de quem está usando**: Usuário, Unidade (a do aparelho) e Perfil. Tocar no cartão abre a conta (trocar de usuário, sair), como o selo de antes;
- **Clientes**: *Consultar cliente* (abre o Banco de horas com todos os clientes e o cursor já na busca: nome, telefone, CPF ou nº do cadastro), ***+ Cadastrar cliente*** (o botão de mais de sempre, agora escrito) e *Banco de horas*;
- **Comandas abertas**: os **filtros da lista**, com o número de cada um: *Todas*, a recreação, cada carrinho, *Saíram há pouco* e *Finalizados do dia* (os dois últimos aparecem quando há alguém, como antes). Tocar em um leva para a lista já filtrada, de qualquer tela. São os mesmos botões de antes (as mesmas ações e os mesmos números): só mudaram de lugar, e por isso a faixa de filtros **não aparece mais no meio** quando o menu está aberto;
- **Vender**: *Vender horas* (os pacotes: escolhe o pacote e depois o cliente) e *Festa*;
- **Agenda**.

O que cada um vê a mais continua como nas versões anteriores: o **gestor** tem o grupo **Gestão** (*Painel* e *Aprovações*, com o número do que espera a resposta) e **Ajustes**; a **supervisora** tem **Ajustes**; o **operador** não tem o Painel nem as Aprovações. Com o WhatsApp ligado, ele aparece em Clientes.

**Os grupos abrem e fecham** tocando no nome (a setinha). O que a pessoa fechou fica guardado no aparelho (`ps-menu-fechados`), e só isso. Com o grupo Gestão fechado, o número das aprovações aparece no nome do grupo.

**Onde nada muda.** Abaixo de 1280 px fica tudo como estava: o **trilho de ícones** no tablet e nas janelas menores, e a **barra de baixo** com a faixa de filtros horizontal no celular (a gestora no celular continua com as Aprovações e o Painel da 1.27). A unidade continua sendo trocada no botão ao lado do título da lista (e em Ajustes).

**Acertos da revisão.** Os números de Comandas abertas acompanham os outros aparelhos mesmo com o cursor na busca do Banco de horas, e fora da lista não seguem a busca que ficou escrita nela (tocar num item abre a lista sem a busca). Com o grupo fechado e a lista filtrada, o item escolhido continua à vista debaixo do nome do grupo. De 1280 a 1365 px, quando ninguém no espaço é de outra unidade, a coluna Unidade sai (ela já está no cartão do menu), para os nomes caberem; havendo alguém de outra unidade no espaço, a coluna continua, mesmo que a busca ou o filtro deixe à vista só uma parte da lista. Com o cursor na busca do Banco de horas, a novidade que vem de outro aparelho troca só o menu e as linhas: o campo de busca não é refeito (o que está selecionado, o acento que está sendo digitado e a rolagem da página ficam como estão), e o resto da tela se acerta quando o cursor sai da busca.

**Detalhes.** Com o **histórico do cliente** aberto ao lado, o menu só fica aberto de 1560 px para cima; antes disso ele encolhe para o trilho enquanto o histórico está aberto, para a lista não ficar espremida. A coluna da direita (as atividades da semana) aparece, com o menu aberto, a partir de 1620 px; até lá a semana fica na linha do pé da lista. Em tela baixa (notebook de 768 px de altura) o menu fica mais junto, e rola se ainda assim não couber.

## 6zi. A festa marcada: o calendário das unidades, o tema, a entrada na festa e o relatório (1.29)

Pedido da dona: marcar a data da festa num calendário que mostre as festas das duas unidades; pôr o tema da festa (para não ter, sem saber, Harry Potter no Morumbi e no Anália no mesmo dia, com material para comprar em dobro); na entrada, identificar quem é o aniversariante (não paga) e quem é convidado; avisar quando passar do combinado, para cobrar o adicional (o upgrade só antes do dia da festa); e um relatório com as crianças que vieram e o contato, para mandar ao pai quando ele pedir.

**A venda da festa.** O botão Festa continua o mesmo, agora com três escolhas no alto: **Festa nova**, **Upgrade** e **Adicional**.
- Na **festa nova**, a **data é obrigatória** e vem antes da tabela de valores. O botão **Calendário** mostra as festas já marcadas das unidades, cada uma com a cor da unidade e o tema. No computador (e no tablet deitado) o calendário **abre sozinho ao lado da folha**; no celular abre dentro da folha. Um toque no dia marca a festa; dia que já passou não se escolhe; sábado e domingo viram festa de final de semana.
- Depois da tabela: a **unidade** da festa (vem a do aparelho), o **aniversariante** (quando o cliente tem mais de uma criança), o **tema** (com os temas já usados para escolher) e o **horário** da festa de semana (a de final de semana é das 10h30 às 13h30). O horário aceita 15:30, 9:30 ou só os números (1530, 930).
- **Tema repetido:** com o dia e o tema escritos, se já houver outra festa com o mesmo tema nesse dia (em qualquer unidade), a folha avisa na hora, com a unidade e a festa. No calendário, o dia com tema repetido fica com um **!** vermelho.
- O comprovante da festa sai com o tema, o aniversariante e a unidade.
- O **Upgrade** parte da festa marcada do cliente e só existe **antes do dia da festa**. No dia (ou depois), ele fica travado e manda para o Adicional.
- O **Adicional** vai para a festa marcada do cliente e soma no combinado (crianças da festa + adicional). Depois do dia da festa (até 30 dias), ele continua indo para ela.
- Quando a família tem **mais de uma festa** marcada, o Upgrade e o Adicional mostram as festas para escolher (vem marcada a da criança escolhida).
- **Trocar o cliente** leva a festa escolhida (o valor, a data, o tema), mas não o aniversariante: ele passa a ser a criança do novo cliente.

**A tela Festas.** No menu (o item **Festas**, e o botão **Festas** na Agenda): o calendário do mês com as festas das unidades (a sigla da unidade e o tema em cada dia, o **!** do tema repetido), as festas do dia escolhido e as próximas festas. Cada festa abre a folha dela.

**O dia da festa.**
- Na lista do espaço aparece a faixa **Festa hoje** (a da unidade do aparelho): o tema, o aniversariante, quantos convidados vieram e o combinado.
- A criança entra pela festa no **atendimento** (depois do cadastro, ou pela busca): em cima aparece **Entrar na festa · convidado**; para a criança da festa, **Entrar na festa · aniversariante**. A faixa Festa hoje tem também **Dar entrada na festa**, que abre a busca do cliente. Com o iDFace ligado, toda a equipe dá a entrada na festa pela tela (o aparelho não sabe que a criança veio para a festa).
- **Fora do horário da festa** (de 1h30 antes de começar até 1h depois de acabar), a festa aparece no atendimento sem o destaque, com **Entrar mesmo assim**.
- **Quem já entrou hoje como recreação** (pelo rosto no iDFace, ou por engano) aparece com **Passar para a festa**: a visita passa a ser da festa e as cobranças ainda não pagas dessa visita saem. Cancelar a entrada na festa (ou desfazer) tira a criança da festa; reabrir a visita da festa volta para a festa.
- Quem entra pela festa **não paga e não gasta horas**: na lista aparece **FESTA** com **CONVIDADO** ou **ANIVERSARIANTE**, e a saída sai **sem cobrança** (o que a criança comprar na visita continua sendo cobrado). Quem sai e volta no mesmo dia não conta de novo. O aniversariante não conta no combinado.
- **Passou do combinado:** quando o convidado que entra passa do combinado, a recepção vê na hora "Passou do combinado: 21ª criança numa festa de 20. Cobre o adicional…", com o botão **Cobrar o adicional**. A gestora vê a festa em **Aprovações** (e no Painel), em "Festas com criança a mais", com **Cobrar o adicional** (abre a venda já no Adicional, com quantas crianças passaram), **Ver a festa** e **Não cobrar**. Depois do adicional pago, o aviso some.

**A folha da festa e o relatório.** Tocando na festa (na faixa, na tela Festas ou nas Aprovações): o combinado, quantos convidados vieram, quem está no espaço, o adicional a cobrar e **Quem veio**, com cada criança, a idade, o responsável e o telefone, na ordem em que entraram (o aniversariante primeiro). Quem passou do combinado é marcado pela conta de agora: com o adicional pago, ninguém fica marcado. Para mandar ao responsável do aniversariante: **Copiar a lista** (texto pronto para colar no WhatsApp), **Planilha** (abre no Excel) ou **Imprimir** (no papel do cupom). A lista tem o telefone de outras famílias: é só para o responsável do aniversariante. Na mesma folha dá para **mudar o tema, o horário, o dia ou a unidade** (o dia só antes de a festa começar, e só para o mesmo tipo de festa); fica anotado em Hoje na recepção, com quem mudou.

**As festas de antes.** Ao atualizar, as festas já vendidas com a data escrita (de hoje em diante) entram sozinhas no calendário, sem tema: é só abrir a festa e pôr o tema. O adicional vendido junto com a festa, e o upgrade com a data dela, ficam ligados à festa (contam no combinado).

## 6zj. O caixa do dia e o cupom só com a via da loja (1.30)

Pedido da dona: um caixa para as meninas do atendimento, no **Finalizados do dia**, com o ícone de caixa: o que foi pago no dia, as formas de pagamento, tudo destrinchado, e os carrinhos à parte (não entram no faturamento da recreação). E no cupom: identificar a via de entrada e a via de saída, o cliente assina as duas, a via da saída sem o saldo de horas do cliente (só o tempo descontado de cada criança), e sem a via do cliente.

**O caixa do dia.** O item **Finalizados do dia** (no menu, em Comandas abertas, e na faixa de filtros da lista) agora tem o ícone de caixa e fica sempre à vista. Tocando nele, a lista abre com o **Caixa do dia** em cima e, embaixo, quem saiu hoje (como antes). O caixa é o que foi **recebido hoje**, desde a meia-noite, na unidade deste aparelho:
- o total recebido e quanto entrou em **Pix, dinheiro, crédito e débito**;
- o que foi pago, separado: **Recreação avulsa** (o tempo de quem não usa pacote), **Pacotes de horas**, **Festas** (festa, upgrade e adicional), e, quando houver, **Produtos da loja** e **Outros** — cada um com o total, as formas e quantas vendas;
- **Total sem os carrinhos**;
- **Carrinhos**, à parte (o fusquinha e o carrinho de bebê, cada um com o seu valor);
- **Total do dia**.
O botão **Imprimir** (no celular, só o ícone) tira o caixa no papel do cupom (para o fechamento). A venda antiga de carrinho (de antes da 1.20) também conta nos carrinhos. A gestora escolhe a unidade (ou todas) no próprio caixa.

**O cupom.** Sai **só a via da loja** (a via do cliente saiu, e a opção de imprimi-la também). Cada cupom diz qual é: **VIA DE ENTRADA**, **VIA DE SAÍDA**, **VIA DE DEVOLUÇÃO** (o carrinho que voltou), **VIA DE SAÍDA DO CARRINHO**, **VIA DA FESTA** ou **VIA DE PAGAMENTO** (venda sem entrada). A via de entrada e a via de saída têm sempre a linha para o responsável **assinar**. Na via de saída, cada criança mostra só o tempo: **Tempo descontado** (quando o tempo saiu todo das horas do cliente) ou **Tempo contado** (quando a saída cobrou tempo: recreação avulsa, além do pacote, ou pago avulso); o saldo de horas do cliente não sai mais. Na festa, "Festa · sem desconto de horas". Os cupons guardados antes, ao reimprimir, saem só com a via da loja.

## 6zk. O caixa conferido (1.31)

Pedido da dona: conferir todas as contas, somas e porcentagens do caixa ("é dinheiro: tenha certeza que está tudo certo").

**Como foi conferido.** Um livro-caixa feito à parte, operação por operação (pacote, recreação na entrada e acertada na saída, desconto em %, criança PCD, festa, adicional, upgrade, produto, carrinho de bebê, fusquinha na devolução, venda cancelada, Desfazer, as duas unidades), comparado com o caixa e com o painel da gestora depois de cada passo; e mais três sorteios de 40 operações cada, com pagamentos divididos em até três formas e centavos quebrados. Em todos: o total é a soma das formas e a soma dos tipos; "sem os carrinhos" é o total menos os carrinhos; o painel mostra o mesmo número do caixa.

**O que mudou.**
- **A venda paga em outro dia e cancelada hoje** (o dinheiro volta ao cliente hoje): sai do caixa de hoje, na linha **Devolvido hoje**, pela forma em que tinha entrado. Antes ela sumia do caixa do dia em que foi paga e o caixa de hoje ficava com o dinheiro a mais. A venda paga e cancelada no mesmo dia continua simplesmente saindo do caixa.
- **O dinheiro conta na unidade em que entrou** (o aparelho que recebeu). Antes contava na unidade da venda: a recreação avulsa de quem entrou pelo rosto (o iDFace não diz a unidade) ficaria fora do caixa da unidade.
- **As parcelas da festa no crédito** com a conta exata: quando não divide em centavos iguais, a diferença vai na 1ª parcela ("4x: 1x de R$ 877,29 + 3x de R$ 877,27"). Antes, "4x de R$ 877,28" somava R$ 3.509,12 para R$ 3.509,10.
- **As porcentagens do painel** ("x% do recebido", "x% do faturado") somam sempre 100%.
- **A saída pelo rosto (iDFace)** não passa pelo pagamento: o que a visita cobrou e ficou sem pagar continua **a receber** na ficha (Receber). Antes não havia onde receber.
- **O Desfazer de um recebimento** tira só os pagamentos daquela operação (antes podia levar junto um pagamento que outro aparelho tinha acabado de receber da mesma criança).
- **O pacote com "FESTA" no nome** (ex.: "FESTA DO PIJAMA") conta nos pacotes, no caixa e no painel.
- **O preço da criança PCD** é calculado em centavos inteiros, igual na tela e no servidor (com preço de centavos quebrados, a venda podia ser recusada por 1 centavo).
- **"1.500" digitado num valor** é mil e quinhentos (antes virava R$ 1,50).

Uma segunda leitura do código, independente, conferiu também: a divisão do desconto entre as vendas (fecha sempre os centavos e nunca passa do que cada venda deve), o desconto em % (vale o valor em reais mostrado na tela), o PCD de 50%, os valores da festa (upgrade = a diferença da tabela, adicional = crianças × valor), os totais dos cupons e o recebido por forma.

## 6zl. Os pedidos de autorização como notificação no celular (1.32)

Pedido da dona: os pedidos que esperam a aprovação do gestor subirem como notificação no celular, com uma caixinha para ele só ativar.

**Ligar (uma vez em cada aparelho).** Em **Aprovações**, a caixinha **Receber os pedidos como notificação** tem o botão **Ativar avisos neste celular**. Um toque: o navegador pergunta se o Play Space pode mandar notificações; toque em **Permitir**. Pronto: a caixinha vira "Avisos ligados neste aparelho", com **Testar** (manda um aviso de teste na hora) e **Desligar**.

**Como funciona.** A cada pedido novo (desconto, cancelar uma venda, nota fiscal), o servidor manda o aviso para o celular de quem decide aquele pedido (o gestor; o cancelamento de venda também para quem estorna), menos para quem pediu. O aviso diz o pedido, quem pediu e a unidade ("Pedido de desconto · JOÃO · ANÁLIA FRANCO: …"). Chega mesmo com o Play Space fechado; tocar abre as Aprovações. A tela de quem pediu não espera o aviso sair.

**Cada celular.**
- **Android (Chrome)** e **computador (Chrome, Edge, Firefox)**: liga direto, pelo navegador ou pelo app instalado.
- **iPhone**: só com o Play Space na **Tela de Início** (Safari → Compartilhar → "Adicionar à Tela de Início"), aberto pelo ícone, com iOS 16.4 ou mais novo. Fora da Tela de Início, a caixinha explica esse passo.
- Se a permissão foi negada, a caixinha mostra como liberar (o cadeado ao lado do endereço, ou Ajustes do celular → Notificações) e o botão **Já liberei**.

**Por dentro.** É o aviso padrão dos navegadores (Web Push): a chave da loja é criada sozinha no servidor na primeira vez e fica guardada nele; cada aviso vai cifrado só para aquele aparelho. O servidor só manda avisos para os serviços dos navegadores (Google, Apple, Mozilla, Microsoft). O aparelho que o serviço diz que não existe mais sai da lista sozinho. O arquivo `sw.js` (na pasta do Play Space) é o que recebe o aviso no aparelho; ele não guarda nada e não mexe no carregamento da tela.

## 6zm. Alterar valor, com a aprovação da supervisora (1.33)

Pedido da dona: além do desconto, um campo para **alterar o valor** de um pacote ou de um produto (por exemplo, vender um brinquedo junto com o pacote, sem o brinquedo estar cadastrado), que a supervisora aprova.

**Na tela de pagamento do atendimento**, embaixo do Desconto, o bloco **Alterar valor** (toca e abre):
1. escolha o pacote ou o produto (com um item só, ele já vem escolhido);
2. o campo do valor já vem com o valor de agora: escreva o valor novo, e o motivo (o que muda: "COM O BRINQUEDO …");
3. toque em **Pedir aprovação**. O pedido aparece para a supervisora e para o gestor (nas Aprovações, no cartão por cima da tela e como notificação no celular de quem ligou os avisos). A supervisora também pode digitar a senha ali mesmo (**Aprovar com a senha**).

Enquanto espera, a conta continua com o valor da tabela. Aprovado, o item passa a valer o valor novo na tabela do pagamento, no total, nas formas de pagamento e no cupom; a linha do item diz "Valor alterado · na tabela, R$ …". A aprovação vale por 30 minutos. **Tirar** volta ao valor da tabela; **Cancelar o pedido** desiste. A supervisora e o gestor alteram direto (o botão é **Alterar o valor**).

Fica registrado: a linha do dia "Valor alterado · PACOTE …: R$ 225 → R$ 260 · Motivo: … · por … · aprovado por …", o pedido nas Autorizações (quem pediu, quem aprovou e como) e a venda com o valor novo (o caixa do dia soma o valor novo). O servidor confere de novo: o pedido aprovado é de quem está vendendo, do mesmo cliente, do mesmo item e do mesmo valor de tabela.

**A supervisora tem a aba Aprovações** (com o aviso no celular, seção 6zl) para responder aos pedidos de alterar valor. O desconto continua sendo só do gestor.

**No cupom**, discreta, embaixo do total, a resposta do que foi pedido naquela conta (só quando alguém pediu): "Desconto: aprovado por …", "Desconto: não aprovado" ou "Desconto: pendente de aprovação" (a conta foi fechada antes da resposta, com o valor sem desconto). O mesmo para a alteração de valor.

## 6zn. O menu simples de volta (1.34)

Pedido do dono: o menu aberto da 1.28 (grupos com tudo escrito, os filtros da lista dentro do menu, o cartão do usuário) ficou poluído, com muita informação. Volta o menu simples de antes: no computador, o trilho de ícones à esquerda (Painel, Aprovações, Espaço, Banco, Agenda, Ajustes), os filtros da lista em botões em cima dela (Todas, Recreação, Carrinho fusquinha, Carrinho de bebê, Saíram há pouco, Finalizados do dia) e as Atividades da semana na coluna da direita; no celular, a barra de baixo, como sempre. Nada mais muda: as telas, os filtros e os botões são os mesmos.

## 6zo. O voucher só na entrada (1.35)

Pedido do dono: o voucher é apresentado na entrada, obrigatoriamente; na saída ele nunca é dado.

- **Na saída** não aparece mais o "Tem voucher? Usar agora".
- **No atendimento**, o **Voucher** só aparece para quem está entrando (a criança fora do espaço). Marcado o voucher, a entrada vai junto: a caixa **Dar entrada agora** fica marcada e travada ("O voucher é dado na entrada").
- Para a criança que já está no espaço, o voucher não aparece (se ela já usou um nesta visita, continua a linha "Voucher · já usado nesta visita").
- O servidor confere de novo: voucher para quem já está no espaço é recusado ("O voucher só vale na entrada"), e voucher sem a entrada junto também ("marque Dar entrada agora").

## 6zp. Alterar valor: o pacote que não existe na tabela (1.36)

Pedido do dono: às vezes a equipe quer lançar um pacote que não existe (sem cadastrar um pacote novo). No bloco **Alterar valor** (embaixo do Desconto, na tela de pagamento), o pacote ganhou o campo **Horas do pacote**, ao lado do **Valor novo (R$)**: escolha o pacote mais perto, mude as horas e o valor, escreva o motivo e toque em **Pedir aprovação**. O gestor (ou a supervisora) autoriza, como no desconto (um toque nas Aprovações, ou a senha ali mesmo).

Aprovado, a venda sai como "PACOTE 4H" (as horas novas), com o valor novo; o saldo do cliente recebe as horas novas; a tabela do pagamento diz "Alterado · na tabela, 3h por R$ 225"; as horas desta compra e o cupom acompanham; a linha do dia fica "Valor alterado · PACOTE 03 HORAS 360 (3h): R$ 225 → 4h por R$ 300 · Motivo: … · por … · aprovado por …". Para o produto continua só o valor.

## 6zq. A cobrança sempre na saída; "Alterar pacote" (1.37)

Pedido do dono: tirar o "Paga agora" da entrada, porque fica confuso saber o que foi pago e o que não foi. Quando a criança entra (ou já está no espaço), a tela de pagamento do atendimento não oferece mais a forma de pagamento: tudo o que foi escolhido (pacote, produto, recreação) fica **a receber na saída**, e o botão é **Cobrar na saída · registrar e dar entrada**. O carrinho que sai da loja (sem a criança entrar) fica a receber na devolução. Na saída, a folha cobra tudo de uma vez, com o Desconto ali também.

Sem uma saída para receber (a compra de um pacote para guardar, sem entrar; só um produto; a festa), continua cobrando na hora.

O bloco embaixo do **Desconto** se chama **Alterar pacote** quando há um pacote na conta (as horas e o valor; o gestor libera, como no desconto; seção 6zp). Com só produto, continua **Alterar valor**.

## 6zr. A festa vendida em partes (1.38)

Pedido do dono: a festa vendida no dia 10 entra no caixa do dia 10; o upgrade dela, vendido no dia 15, entra no caixa do dia 15. Mas tudo tem de aparecer como uma venda só, com as datas, para saber por que a festa foi faturada em dias diferentes.

- **Cada pagamento entra no caixa do dia em que foi feito** (como já era): a festa no dia em que foi paga; o upgrade e o adicional, no dia em que foram pagos.
- **A folha da festa** (toque na festa, em Festas ou na faixa da festa de hoje) ganhou **A venda da festa**: a festa, o upgrade e o adicional, cada um com o dia e a hora em que foi vendido, quem vendeu, o que foi pago (em que dia e em que forma) e o que falta receber, e o **Total da festa**. Vendida em partes, em dias diferentes, a folha avisa: "cada parte entra no caixa do dia em que foi paga".
- **No caixa do dia**, embaixo de **Festas**, o que entrou hoje e de qual festa: "UPGRADE DE FESTA · festa de ALICE (20/11) · a festa foi vendida em 10/11". No caixa impresso também.

## 6zs. "Alterar pacote" também na saída (1.39)

Pedido do dono: como a cobrança agora é sempre na saída (1.37), o **Alterar pacote** tem de estar na tela de pagamento da saída, embaixo do **Desconto**. Antes ele só aparecia na tela de pagamento da entrada.

- Na **tela de pagamento da saída** (e na conta em conjunto do responsável), embaixo do Desconto: **Alterar pacote**. Ela escolhe o pacote ou o produto desta visita que ficou para pagar na saída, escreve **o que muda**, as **horas do pacote** e o **valor novo**, e toca em **Pedir aprovação**.
- A supervisora ou o gestor aprova com um toque (em Aprovações, com o aviso no celular), ou digita a senha ali mesmo (**Aprovar com a senha**). A supervisora e o gestor alteram direto (**Alterar o valor**).
- Aprovado, **a venda muda na hora**: o valor e, no pacote, as horas (o saldo do cliente acompanha). A conta da saída já mostra o valor novo, e o dia ganha a linha "Valor alterado na saída", com quem pediu e quem aprovou. No comprovante, discreto: "Valor alterado: aprovado por ...".
- Sem pacote ou produto na conta, o bloco explica e leva direto para **Adicionar serviços**: o pacote entra para pagar nesta saída e, depois, é só alterar ali.
- Não vale para a recreação, o carrinho, a festa, o voucher, nem para o que já foi pago (ou já teve desconto).

## 6zt. Sem internet, o Play Space continua (1.40)

Pedido do dono: se a internet cair, a recepção não pode parar. Com internet, tudo funciona como sempre (e a versão nova chega para todos); sem internet, o Play Space continua no computador e manda tudo sozinho quando a internet volta.

- **O ícone na tela do computador**: em **Ajustes → Cupom impresso**, no bloco **Imprimir direto**, toque em **Baixar o instalador (Windows)** e dê dois cliques no arquivo baixado: ele cria o ícone **Play Space** na área de trabalho, com a logo (o mesmo da impressão direta). Abra o Play Space por ele uma vez com internet: daí em diante ele abre mesmo sem internet.
- **Sem internet**, aparece em cima a faixa **Sem internet desde 15:20 · o Play Space continua funcionando**. A lista continua (quem está no espaço, o relógio, a conta de cada um), e continuam funcionando: **cadastro**, **entrada** (recreação, pacote, produto), **sapateira** e **saída com o pagamento** (inclusive a conta do responsável). Cada coisa feita fica guardada no computador (a faixa diz quantas: "3 guardados para enviar"). O cupom sai normalmente.
- **A internet voltou**: o que ficou guardado vai sozinho para o servidor, na ordem, com a hora em que foi feito (a entrada das 15:20 fica às 15:20, e a saída é cobrada pela hora em que a criança saiu). No dia, cada linha diz "feito sem internet". O que já tinha chegado não é feito duas vezes.
- **Esperam a internet** (avisa na hora): carrinho e fusquinha (saída e devolução), voucher, festa, pedidos de aprovação (desconto da atendente, alterar pacote), cancelar e desfazer. O desconto do gestor continua.
- Se o servidor não aceitar algo (outro aparelho já tinha feito, ou a conta mudou), a faixa mostra **Envios para conferir · Ver**: o que era, quando foi feito e o motivo. Confira com o cliente e toque em **Conferido**.
- O que fica guardado no computador é a lista da última vez e o que espera para ser enviado. Ao tocar em **Sair**, a lista guardada é apagada (com algo ainda esperando para ser enviado, o Play Space pede para esperar a internet voltar antes de sair).

## 6zu. Alterar pacote: horas, valor do pacote e valor do produto (1.41)

Pedido do dono: o **Alterar pacote** é para quando a loja quer um pacote de horas com o valor que ela define, igual ao desconto: o gestor aprova.

- Embaixo do **Desconto** (no pagamento da entrada e no da saída): **Alterar pacote**, com três campos: **Valor do pacote**, **Horas** e **Valor do produto** (o produto é opcional: sem produto, deixe vazio). Embaixo, o **Total**.
- Toque em **Pedir aprovação**: o gestor (ou a supervisora) aprova com um toque no celular, ou digita a senha ali mesmo (**Aprovar com a senha**). O gestor e a supervisora alteram direto (**Alterar o pacote**).
- É sempre um pacote: sem pacote na conta, o bloco pede para escolher um da lista (qualquer um); as horas e os valores são definidos ali.
- O gestor vê a conta: "PACOTE 03 HORAS 360 (3h, R$ 225) para 4h: pacote R$ 300 + produto R$ 60 = R$ 360". A linha do dia também.
- **No cupom sai um valor só**: o pacote com as horas novas e o total (R$ 360), sem separar o pacote e o produto.

## 6zv. Cortesia na saída (1.42)

Pedido do dono: um botão de **Cortesia** na tela de pagamento da saída, com o tempo, que deixa a saída sem cobrar nada, e que só vale dentro da regra dos 9 minutos.

- Na tela de pagamento da saída (de uma criança), embaixo da conta: **Cortesia · sai sem cobrar nada**, com quanto a criança ficou, até que horas vale e o tempo que falta (a mesma regra de **cancelar a entrada sem cobrar**, em Ajustes: 9 min de fábrica).
- Um toque: a criança sai e nada é cobrado (a recreação da visita não é cobrada e o tempo não é descontado do pacote); a sapateira fica livre.
- Fica registrado como a entrada cancelada no prazo, com o motivo **CORTESIA** e o nome de quem fez: a gestora vê, como todo cancelamento.
- Passou do prazo, o botão fica apagado ("só até 9 min da entrada"). Com compra para pagar na saída (pacote, produto), o botão não aparece.

## 6zw. Cupom de entrada em duas vias (1.43)

Pedido do dono: na entrada, duas cópias do comprovante, uma para a loja e uma para o cliente (para ele ter a hora em que deixou a criança). Na saída, uma via só, que o cliente assina.

- Na **entrada** saem duas vias, cada uma cortada: a **VIA DE ENTRADA** (da loja, completa, com a linha da assinatura) e a **VIA DO CLIENTE** (a hora da entrada, o que foi pago e a tolerância; sem os documentos e sem a assinatura).
- A tela do cupom avisa: "Saem duas vias: a da loja (o responsável assina) e a do cliente". O botão diz **Imprimir o cupom · 2 vias**.
- **Saída**, devolução, festa, carrinho e comprovante de pagamento: uma via só, como já era. Se o cliente pedir, **Reimprimir cupom** na ficha (sai uma via).

## 6zx. O Play Space da loja num domínio próprio, separado do teste (1.44)

O Play Space de verdade fica no domínio da loja (**playspace2.com.br**); a instalação na hospedagem da Gama era só de teste.

- O pacote do domínio da loja (o .zip para extrair na pasta do domínio) já vem com a pasta `api/dados`. Desde a 1.44, a instalação que tem essa pasta guarda **os dados só ali** (o banco, as sessões, as cópias de segurança), e nunca na `~/playspace-dados`, que a instalação de teste na mesma hospedagem pode estar usando. Assim o Play Space de verdade nasce **zerado** ("Bem-vinda ao Play Space") e nunca se mistura com o teste.
- A pasta `api/dados` é fechada para a internet (`.htaccess` que nega tudo) e o banco tem nome sorteado. Para o backup, copie essa pasta.
- As instalações de antes (sem a pasta `api/dados`) continuam usando a `~/playspace-dados`, como sempre.
- O **Atualizar** nunca apaga a pasta `api/dados` (ele só troca os arquivos do sistema).

## 6zy. Auditoria das contas (1.45)

Pedido do dono: auditar todas as somas e valores (caixa, liberações, porcentagens, horas de pacote e avulso); as contas têm de bater, e trocar de opção ou de tela não pode dar valor diferente.

Três auditorias, cada uma num servidor de teste isolado, com testes que se podem repetir:

- **Tempo e horas:** a conta dos períodos, da tolerância, do PCD, do voucher, do pacote ("descontar do pacote" ou "pagar avulso"), da saída em conjunto e do horário de fechar bateu **até o segundo** entre a tela da saída, o servidor, o cupom e o saldo (ficha, Banco de horas, histórico, relatório), inclusive com a folha aberta virando o período e com o computador em outro fuso.
- **Dinheiro e caixa:** em **1.250 operações sorteadas** (vendas, pagamentos em até três formas, descontos em R$ e em %, PCD, produtos, carrinhos, festas, cancelamentos, devoluções, desfazer) nas duas unidades, o dinheiro gravado bateu **centavo a centavo** com um livro-caixa feito à parte; o Caixa do dia (por unidade e TODAS), o painel e o extrato do cliente também.
- **Liberações e integridade:** quem libera o quê, a senha, o pedido usado uma vez só, e o servidor recusando valores adulterados (total menor, desconto maior, outro item, outra criança).

O que foi corrigido:

- **Dois aparelhos ao mesmo tempo** (a mesma saída em dois computadores, o mesmo "Liberar" no celular da gestora e no da supervisora, o mesmo desconto, a cobrança do período que nasce no pulso de várias telas): o servidor faz o que muda os dados **um pedido de cada vez** — nunca cobra, soma horas ou usa um desconto duas vezes.
- O **cadastro** não vende mais horas (um caminho antigo, mandado à mão, vendia horas com qualquer valor).
- O **desconto liberado em %** vale a % da conta de agora (se a conta mudou depois do pedido, nunca passa daquela %); o pedido de desconto da saída que ninguém respondeu fecha quando a criança sai; pedido de mais de 12 h não é mais liberado; a % do gestor que não bate com o valor não vai para a linha do dia.
- O **serviço com o preço mudado** no meio do atendimento avisa (como o pacote e o produto).
- A **saída feita sem internet** com a hora de antes da entrada de agora não entra (e não deixa cobrança para trás).
- A **cortesia**, o **cancelar a entrada** e o **passar para a festa** tiram o voucher da visita (antes ficava como 30 min guardados).
- **Corrigir a hora da saída** de uma visita paga: a linha do dia diz "devolver ao cliente: …" e o que foi pago em outro dia entra em "Devolvido hoje".
- O **"ficou"** perto do limite do período (de 39:01 a 39:59) mostra o minuto que começou (0h40), nunca 0h39 com dois períodos cobrados; o relatório do cliente mostra o mesmo "ficou" da saída.
- **Caixa do dia:** a linha "Devolvido hoje" não leva mais o carrinho devolvido (ele sai da linha Carrinhos; as linhas somam o total, na tela e no papel); o dinheiro recebido num aparelho sem unidade aparece no botão **SEM UNIDADE**; o valor negativo se lê "− R$ 60,00".
- **"Hoje na recepção":** os números são do dia (desde a meia-noite), e o recebido é o mesmo do Caixa do dia.
- **Ficha e relatório do cliente:** a cobrança da saída pelo rosto ainda não recebida não conta como paga.
- **Painel:** "x% do recebido" nunca passa de 100%.

Fica como está (regra da loja, para o dono confirmar): o valor do produto no "Alterar pacote" entra em Pacotes de horas; o faturado conta na unidade da venda e o recebido na unidade do aparelho que recebeu; um resto de saldo menor que um período (ex.: 10 min de uma importação) não é usado sozinho; duas vendas iguais feitas sem internet em dois computadores valem as duas (o dinheiro foi recebido nos dois).

## 6zz. A coluna Tempo sem o "até" (1.46)

Pedido do dono: na tela inicial, a coluna **Tempo** não mostra mais o "até 13:46" (a hora em que acaba o tempo comprado). O cliente se perde no tempo, e quanto cobrar aparece na saída.

- Fica o relógio de quanto tempo a criança está dentro e, embaixo, o **"passou X min"** de quem passou do tempo comprado (é o que vai ser cobrado).
- Vale também para o carrinho com tempo comprado.
- A ficha do cliente e a tela da saída continuam mostrando o tempo do pacote, para quem precisar conferir.

## 6zza. O carrinho sempre cobrado na devolução (1.47)

Pedido do dono: tirar o "já pago" do carrinho e deixar sempre para cobrar na saída (ou na devolução). Não existe a opção de marcar o carrinho como pago na hora.

- O carrinho sozinho, ou com a criança entrando ou já no espaço, já era **Cobrar na devolução** / **Cobrar na saída** (desde a 1.37).
- Faltava um caso: o carrinho vendido **junto com o que é pago na hora** (um pacote, um produto), sem a criança no espaço. Antes, tudo era pago na hora e, na devolução, o carrinho aparecia "já pago". Agora o pagamento de agora é **só do resto**; na tabela o carrinho diz "na devolução", o aviso embaixo diz quanto fica a receber, e o cupom também ("R$ 30,00 a receber na devolução"). O desconto do gestor, nesse caso, vale só para o que é pago agora.
- Na devolução, o carrinho é cobrado normalmente. A conta da devolução não diz mais "já pago".
- Se o cliente já tinha pago, quem decide é o gestor (ex.: desconto ou cortesia na devolução).

Junto: o serviço do aparelho (o que guarda o Play Space para abrir sem internet) só cuida do que é do Play Space. O **/notas** (o emissor de notas, no mesmo site) ficava debaixo dele: a versão nova do Notas demorava a aparecer e, sem internet, o Play Space podia abrir a tela do Notas. O guardado de antes é apagado sozinho na atualização.

## 6zzb. O ícone na área de trabalho, no Mac e no Windows (1.47)

Pedido do dono: um botão nos Ajustes para criar o ícone do Play Space na área de trabalho, no Mac e no Windows, para o **Imprimir** já mandar o cupom para a impressora (no site, o navegador sempre mostra a janela de impressão).

Em **Ajustes → Cupom impresso → Ícone na área de trabalho · Mac e Windows**:

- **Windows: Baixar o instalador (Windows).** Dois cliques no arquivo baixado: ele cria o ícone **Play Space** (com a logo) na área de trabalho e abre o sistema.
- **Mac: Copiar o comando (Mac).** Abra o **Terminal** (Command + espaço, escreva Terminal), cole (Command + V) e aperte Enter: aparece na Mesa o app **Play Space**, com a logo, e o sistema abre. Precisa do Google Chrome.
  - Por que um comando: nenhum site consegue pôr um arquivo na Mesa do Mac, e um programa baixado da internet a Apple barra ("desenvolvedor não identificado"). Feito pelo Terminal, no próprio Mac, o app abre sem aviso.
  - O app é uma pasta "Play Space.app" com o script que abre o Chrome numa janela só dele, com a impressão direta (`--kiosk-printing`) e um perfil só dele, a ficha do app e a logo (`img/playspace.icns`, baixada do próprio site). O comando de antes (que deixava um "Play Space.command" na Mesa, abrindo o Terminal junto) é trocado pelo app.
- Nos dois: deixe a impressora do cupom como **impressora padrão** do computador, e abra o sistema **sempre pelo ícone**. O cupom de teste tem que sair sem perguntar nada.

## 6zzc. Play Space Finanças: playspace2.com.br/financas (1.48)

Pedido do dono: um **/financas** com login próprio (como no Notas) e a mesma cara do Notas, com o que é crítico e delicado do negócio.

**Chega pelo Atualizar** (a pasta `playspace/financas` vai junto do Play Space). Não precisa de banco novo: os números saem do próprio banco do Play Space, **só lendo** (nada nas Finanças muda uma venda, um pagamento ou um cadastro).

**O acesso:**

- É só das Finanças: a equipe do Play Space **não entra** com o login de lá.
- **Primeira vez:** "Criar o acesso". Para provar que é o dono, entra-se uma vez com um usuário de **gestor do Play Space**; depois cria-se o login das Finanças (nome, login, senha). Ex.: José → login **jose**, senha 123456. Só dá para criar o primeiro acesso uma vez.
- **Entrar:** login ("José" e "jose" são o mesmo) e senha. 8 tentativas erradas em 15 min bloqueiam um pouco.
- **Esqueci a senha:** com o usuário de gestor do Play Space, escolhe-se a senha nova.
- **Conta → Quem vê as Finanças** (só a conta principal): criar acesso, nova senha, tirar acesso (quem estiver com a tela aberta sai na hora).

**O que mostra** (escolhe-se o período: hoje, ontem, 7 dias, 30 dias, este mês, mês passado, este ano ou as datas; e a unidade: todas, Anália Franco, Morumbi):

- **Visão geral:** faturado (já com os descontos; o ticket médio), recebido (os pagamentos, já sem o devolvido), em aberto (vendido e ainda não pago), crianças (visitas, crianças diferentes, no espaço agora), cancelamentos, descontos, pacotes vendidos e festas; **cada unidade** lado a lado; o faturado por dia (ou por mês, em períodos longos) com as unidades; o que foi vendido por tipo; as formas de pagamento.
- **Vendas e pacotes:** cada pacote vendido (quantos em cada unidade) e as vendas por atendente.
- **Cancelamentos:** quantos, quanto deixou de ser cobrado, os fora do prazo; **por atendente** (o nome que a atendente escreveu; sem ele, quem estava no aparelho); cada cancelamento com a hora, a unidade, a criança, o que foi cancelado e o motivo. Baixa em planilha.
- **Descontos:** os descontos do gestor (cada um, mesmo repartido em mais de uma venda), o valor e a % da conta, quem liberou e a atendente; o desconto PCD e os vouchers à parte. Baixa em planilha.

Os números de "hoje" batem com o painel da gestora no Play Space (o faturado, o recebido e os cancelados de cada unidade). A unidade é a do aparelho em que a coisa aconteceu. A cobrança da recreação de quem está no espaço nasce no Play Space (no pulso das telas abertas): as Finanças mostram o que já nasceu.

## 6zzd. A versão final: auditoria de segurança e das Finanças (1.49)

Pedido do dono: "audite tudo, veja se não tem nenhum erro; o banco de informação está seguro?"; o operador com o básico; só o gestor agenda.

**Segurança do Play Space** (verificado numa cópia, corrigido):

- Um link de outro site (aberto pela gestora logada) fazia ações com a sessão dela, até desfazer a importação do banco de horas: agora **todo pedido** precisa do cabeçalho do app.
- A hora "sem internet" deixava dar saída com a hora que se quisesse: se o aparelho estava falando com o servidor naquela hora, vale a hora de agora (o gestor, que já corrige horas, fica de fora). Entrar de novo não escapa: para a hora de antes de entrar, a conferência é pelo usuário (o servidor guarda, de 30 em 30 s e por 2 dias, quem estava falando com ele). Toda saída feita sem internet fica marcada no histórico ("feito sem internet, chegou às ..."), e a hora recusada também ("mandado como feito sem internet às ..., mas o aparelho estava com internet").
- Site novo e vazio: o primeiro gestor só nasce nas 2 primeiras horas (depois, com o arquivo `liberar-primeiro-acesso` na pasta de dados).
- O **Atualizar** só funciona com alguém logado; o registro das atualizações fica na pasta de dados (trancada); `api/.htaccess` tranca os registros de erro do PHP.
- A chave da ponte do iDFace só vai para o gestor. A trava do login é por login + aparelho (ninguém de fora trava a gestora).
- **A agenda:** marcar, mudar ou apagar é só do gestor (todos veem e marcam "feito"); a festa continua sendo marcada no atendimento.

**O banco de dados:** fica na pasta de dados (`api/dados`, ou fora do site), trancada para o navegador (`.htaccess` + `index.html`), com nome sorteado e permissão só do servidor; as sessões ficam lá dentro; nenhuma ação baixa o banco.

**Regras da loja que ficam como estão (para o dono confirmar):** o operador marca PCD no cadastro novo (50% na recreação) e dá o voucher do shopping na entrada sem pedir liberação; as Finanças mostram os dois à parte (Descontos → PCD e vouchers).

**Finanças** (corrigido): a venda paga num dia e cancelada em outro não sai duas vezes do Recebido; cada desconto dado conta uma vez (mesmo repartido); a entrada desfeita na hora não conta como cancelamento (como no painel); os resumos por atendente e por quem liberou são feitos com todos os registros; datas impossíveis recusadas; a planilha não leva fórmulas; o "Esqueci a senha" das Finanças só vale com o mesmo usuário de gestor que criou o acesso (a gestora não troca a senha do dono).

## 6zze. Nota fiscal pelo Play Space Notas (1.50)

Pedido do dono: o botão NF com a mesma pegada da Gama, abrindo o Notas já preenchido, e o Notas pré-configurado como a nota que a Play Space já emite no site do governo (DANFSe nº 29).

- Na compra, **NF** → pedir ou autorizar (como antes). Com a nota autorizada aparece **Emitir no Play Space Notas**: o `/notas` abre em outra aba com o cliente, o CPF/CNPJ, o e-mail, o serviço, a descrição ("Recreação infantil: pacote de 3 horas", por exemplo) e o valor. Os dados vão pelo próprio navegador (o Play Space e o Notas ficam no mesmo site); nada vai no endereço.
- Emitida em **produção**, o número volta sozinho para o campo **Número da nota emitida** da compra; é só tocar em **Marcar como emitida**. Em Teste ou Simulação o número não é anotado.
- O Notas (arquivo separado, não fica neste repositório) já vem com: CNPJ 02.687.087/0001-42, inscrição municipal 2.717.568-0, São Paulo/SP, Simples Nacional (ME/EPP, tributos pelo Simples), serviço **12.17.01 / 001** (recreação e animação), ISS não retido, tributos aproximados da Lei 12.741 como na nota (13,45% federais, 0% estaduais, 4,29% municipais). O que já estiver preenchido em Conta não muda.
- A série da DPS do app é a 1 (a 70000 é a do emissor do site do governo); o número da NFS-e continua na sequência (quem numera é o governo).
- A nota nº 29 tem **R$ 4,29 de contribuição previdenciária retida** de uma pessoa física (o valor líquido ficou R$ 685,61): parece engano (é o mesmo número do % municipal). O app não retém nada; confirmar com o contador.
- Emitir de verdade só depois de anexar o certificado A1 no Notas (Emissor Nacional → Teste → Produção).

## 6zzf. Cópia de segurança da semana (1.51)

Pedido do dono: a cópia automática, a cada 7 dias.

- A cada 7 dias o sistema faz sozinho uma cópia inteira do banco (clientes, horas, vendas, tudo). Quem dispara é a primeira tela aberta depois do prazo; leva 1 ou 2 segundos e ninguém percebe. Ficam as **8 mais novas** (uns 2 meses) na pasta de cópias, dentro da pasta de dados, trancada para o navegador. As cópias que a importação e a limpeza usam para desfazer continuam à parte.
- **Ajustes → Cópia de segurança** (só o gestor): a lista, **Baixar** cada uma e **Fazer uma cópia agora**. Fica guardado quem baixou e quando.
- Enquanto a cópia nova não for baixada, o gestor vê a dica **"A cópia de segurança da semana está pronta"** com o botão **Baixar a cópia**.
- **Guarde o arquivo baixado fora do site** (Google Drive, o computador). A cópia no servidor protege de um erro no sistema; só a de fora protege se o site ou a conta da hospedagem sumir. O arquivo tem os dados dos clientes: guarde em lugar com senha.
- **Para voltar uma cópia** (só em último caso): no cPanel, Gerenciador de Arquivos, pasta de dados (`api/dados` ou `playspace-dados`). Renomeie o banco atual (`playspace-….sqlite`) para guardar, apague os arquivos `-wal` e `-shm` dele, e suba a cópia com **o mesmo nome** do banco. Abra o Play Space e confira.

## 6zzg. A lista sem o "até agora R$" (1.52)

Pedido do dono (com a foto da lista): o fusquinha que está fora mostra, na coluna **Tempo**, só o relógio de quanto tempo está fora, sem o "até agora R$ 360" embaixo. Quanto cobrar aparece na **devolução**, e a comanda do cliente (a ficha) continua dizendo o que soma até agora, para quem precisar conferir. O "passou X min" do carrinho com tempo comprado continua.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. "Pacote | Avulso" ao lado do nome, com prioridade no pacote (2.14)

Pedido do dono (26/11): "tem que ter uma opção também de eu, tipo, cobrar no avulso. Às vezes uma opção do lado do nome da criança ... prioridade máxima no pacote".

- Na saída em conjunto, na lista dos itens, ao lado do nome de cada criança que pode usar as horas: "Pacote | Avulso". O verde (Pacote) desconta das horas do responsável; o vermelho (Avulso) cobra a visita dela. É o mesmo que o check de baixo (os dois andam juntos). No celular, o par fica embaixo do item.
- Tudo começa no Pacote (com as horas do CPF, em Ajustes → Regra das horas). Avulso só quando a atendente escolhe.
- Prioridade no pacote: a criança que paga avulso não segura as horas dela; elas ficam para os irmãos da mesma conta. Ex.: MANO com 3h paga avulso → as 3h dele cobrem as duas irmãs (1h30 cada). Para isso, na hora de confirmar, quem paga avulso sai primeiro.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. O check de cada criança na saída em conjunto (2.13)

Pedido do dono (25/11): "eu preciso de uma opção pra mim, tipo, descontar as horas do pacote desse responsável. Se eu quiser selecionar todas as crianças pra descontar a hora, eu seleciono".

- Na conta do responsável com mais de uma criança (a saída em conjunto), embaixo de "Descontar do pacote / Pagar avulso", aparece "Descontar das horas do responsável", com um check verde por criança (como o da entrada).
- Marcada (verde): o tempo dela sai das horas do responsável (as dela e, faltando, as dos irmãos), e o check diz quanto ("desconta 1h30"). Sem marcar: ela paga avulso.
- "Marcar todas" marca todas de uma vez. "Pagar avulso" desmarca todas; "Descontar do pacote" marca de novo.
- Se as horas acabarem, a criança que sobra aparece apagada ("sem horas no pacote") e paga o tempo dela. Desmarcando outra, as horas passam para ela.
- A escolha continua igual depois de "Adicionar serviços" (por exemplo, vender um pacote na saída e voltar para a conta).

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. As anotações do cliente, com a visualização rápida (2.12)

Pedido do dono (25/11): "uma opção de visualização rápida, de passar o mouse em cima e visualizar, às vezes, alguma informação que as atendentes coloquem. Cliente, entrada em contato tal dia, algum tipo de observação assim que as meninas consigam visualizar no cadastro do cliente sem precisar abrir outra tela".

- No Histórico do cliente (o painel da direita), "Anotações": escreva (ex.: "cliente entrou em contato dia 25/11 pedindo…") e toque em "Salvar a anotação". Cada uma fica com a data e o nome de quem escreveu; no computador da loja, toque antes em quem está escrevendo. Quem escreveu (no mesmo login) ou o gestor apaga.
- A anotação é da família (o mesmo responsável). Quem tem anotação ganha um balãozinho com o número ao lado do nome do responsável: na lista de quem está no espaço, na busca e em Cadastros. Passando o mouse em cima, aparecem as últimas anotações, sem abrir outra tela; no celular, toque no balãozinho para ver e toque de novo para fechar.
- As observações de cada criança (alergias, pintura no rosto…) continuam no OBS, como antes.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. As horas do CPF e a senha das unidades (2.11)

Pedidos do dono (25/11).

As horas do CPF: "as horas tem que estar vinculado ao CPF. Quantas crianças ele colocar, tem que dar a opção de abater do pacote ... Esse tá sendo meu maior problema".

- As horas do pacote são do CPF (Ajustes → Regra das horas → "As horas do pacote são: Do CPF (todas as crianças)", de fábrica). Qualquer criança do responsável entra com o saldo: o atendimento mostra "Dar entrada com o saldo (Xh do CPF)" e a conta "comprou / usou / saldo do CPF" somando as crianças.
- Na entrada, as horas guardadas dos irmãos que estão fora vão com a criança que entra (ela fica no pacote, com o tempo correndo como sempre). Com irmão no espaço, a criança que entra sem horas fica com uma parte igual das horas que sobram a ele (em períodos inteiros): duas crianças com 3h30 no CPF entram com 2h e 1h30.
- Na saída, o que faltar sai das horas do CPF sem precisar tocar ("Descontar do pacote" já vem marcado); "Pagar avulso" continua para quem quiser pagar. Vale para a saída sozinha e para a conta em conjunto, com quantas crianças forem.
- Os registros das crianças dizem "Horas do CPF: … passaram para a entrada de …". A conta da família (o relatório do cliente) não muda: as horas só passam de uma criança para outra do mesmo CPF.
- "De cada criança" volta ao jeito antigo (cada criança com as horas dela; na saída, as horas da família só com o toque).

A senha das unidades e o login do gestor: "pede pra colocar uma senha ... essa senha pode colocar de 1 a 6 todos e depois a opção de eu trocar lá dentro ... o login do gestor precisa ter uma opção ... essas outras opções de login são todos de operador ... Tem que ter o Casatom Morumbi".

- No computador da loja, a equipe toca na unidade e escreve a senha dela: de fábrica, 123456 em todas. O gestor troca a senha de cada unidade em Ajustes → Computadores da loja → "Senha de cada unidade".
- As unidades entram como operador. Embaixo delas, o botão "Login do gestor" abre o login com usuário e senha.
- Se faltar a Casa Tom de algum lugar (ex.: Casa Tom Morumbi), a folha dos Computadores da loja avisa e cria com um toque ("Criar agora"); daí ela aparece na tela de entrar.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. O computador da loja, as horas da família, várias crianças de uma vez e o produto da Casa Tom (2.10)

Pedidos do dono (25/11).

O computador da loja: "a tela de login eu só vou querer 4: Anália, Casa Tom Anália, Casa Tom Morumbi e Morumbi ... não precisa pedir senha ... mas deixa a parte do usuário pra gente ir cadastrando as meninas que vai aparecer lá no centro de comissão".

- O gestor libera cada computador do balcão uma vez: entre com o login do gestor nesse computador → Ajustes → Computadores da loja → escreva um nome (ex.: BALCÃO ANÁLIA) → "Liberar este computador". Ao sair, a tela de entrar desse computador mostra só as unidades; a equipe toca na unidade e entra, sem senha, travada nela.
- Quem entra é a "loja" da unidade (aparece como ANÁLIA, MORUMBI, CASATOM ANÁLIA…): não aparece na Equipe nem entre os vendedores. Quem vende, cadastra e atende continua tocando no nome (as comissões continuam por pessoa); no computador da loja, o cadastro novo só fecha com "Quem está cadastrando?" tocado.
- Os usuários da Equipe continuam (para as comissões e para o gestor). "Entrar com usuário e senha" fica embaixo dos botões. O celular e o computador de casa (sem a liberação) continuam com usuário e senha, para ninguém de fora entrar pelo site.
- Em Ajustes → Computadores da loja aparecem os computadores liberados, com a última entrada; "Tirar acesso" faz o computador voltar a pedir senha (a sessão aberta nele cai na hora).

As horas da família: "quando eu entro alguém no avulso, eu tenho que, mesmo assim, ter a opção de descontar do pacote no final ... Se a pessoa tiver horas no pacote".

- As horas são da família (o mesmo responsável). A criança sem horas no nome dela (as do sistema antigo ficaram num irmão) entra na recreação avulsa, e a saída dela mostra "Descontar do pacote" ao lado de "Pagar avulso" (vem marcado "Pagar avulso", porque ela entrou na avulsa; a folha avisa "dá para descontar Xh das horas de …"). Com um toque em "Descontar do pacote", o tempo sai das horas guardadas do irmão ("Pacote da família: desconta 1h30 das horas de …"), e a cobrança avulsa sai da conta. Passa só o que tira cobrança (o que não fecha um período continua avulso). Vale na saída sozinha e na conta em conjunto. O registro das duas crianças diz "Horas da família"; o Desfazer da saída devolve as horas.
- O atendimento da criança sem horas já avisa: "a família tem Xh no pacote: na saída, dá para descontar".

Várias crianças de uma vez: "um checkzinho nas crianças ... quando eu quiser entrar uma criança só ou 2 crianças ... Aí ela fica toda marcada, verdinha".

- No atendimento do responsável com mais de uma criança fora do espaço: "Quem vai entrar?", com cada criança num botão; marcada, fica verde com o ✓ (a do atendimento já vem marcada). "Dar entrada · 2 crianças" entra todas as marcadas de uma vez (cada uma com o saldo dela ou na recreação avulsa): uma mensagem, um Desfazer, a sapateira de cada uma em seguida e um cupom de entrada só, com as duas.

O produto da Casa Tom: "no Casatom eu quero um botão vermelho escrito adicionar produto, para que os próprios operadores adicionem o valor e o produto".

- Casa Tom → Venda de produtos → o botão vermelho "Adicionar produto" (para toda a equipe): o nome (já vem o que foi buscado), o valor e, se quiser, a categoria; o produto entra no cadastro da Casa Tom e já vai para a venda. O registro diz quem cadastrou. Nome repetido não entra: com o mesmo valor, usa o que já existe; com outro valor, pede para procurar na lista (o preço de um produto só muda pelo gestor, em Produtos).

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. O tamanho do cupom e o ajuste da impressão do Chrome (2.09)

Pedido do dono (25/11, com a foto de uma notinha de entrada): "a notinha tá saindo muito grande. Eu consigo diminuir ela ou colocar uma opção dentro do sistema para mim fazer um ajuste da notinha?"

- Ajustes → Cupom impresso → "Tamanho do cupom": Normal (como sempre), Compacto (a logo pequena ao lado do nome da loja, menos espaço entre as partes, a letra um pouco menor) e Econômico (sem a logo, a letra menor, o mínimo de espaço). Só o gestor muda; vale na hora para todos os aparelhos e para todos os cupons (entrada, saída, devolução, pacote, festa, reimpressão e relatório do cliente). A folha mostra o cupom de teste do jeito que ele sai e quanto papel ele gasta (o de teste, uma entrada: uns 15,6 cm no Normal, 10,4 cm no Compacto e 9,4 cm no Econômico).
- O que não é do cupom: a data e "Play Space Horas" em cima, o endereço do site e o "1/1" embaixo e o papel em branco (as margens) vêm da configuração de impressão do Chrome, que a impressão direta repete. Na mesma folha, "A data, o endereço do site e o papel em branco no cupom" explica e entrega o "Ajuste da impressão" (Windows: um arquivo para abrir; Mac: um comando para colar no Terminal). Ele abre o Play Space no mesmo perfil do ícone, mas sem a impressão direta, já nesta folha. Aí: "Imprimir um cupom de teste" → Mais configurações → Margens: Nenhuma, Escala: Padrão, desmarcar "Cabeçalhos e rodapés" → Imprimir. Feche e abra de novo pelo ícone Play Space: o Chrome guarda essa escolha, e a impressão direta passa a sair sem a data, sem o site e sem o papel em branco.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A Venda futura e os cadastros nas comissões (2.08, Finanças 5.2)

Pedidos do dono (25/11).

Venda futura: "no lado de festas eu quero um botãozinho verde escrito venda futura ... Elas selecionam a data que o cliente falou que ia fechar o pacote e seleciona a data de pagamento e tem que aparecer um lembrete para as meninas no calendário referente a esse pagamento desse cliente ... Obrigatoriamente tem que pedir autorização do gestor."

- Festas → o botão verde "Venda futura": escolha o cliente (a mesma busca da Festa; cliente novo: cadastre e ela abre), escreva o que ele vai fechar e o valor combinado, a data em que ele disse que fecha, a data do pagamento, toque em quem está vendendo (e, se quiser, uma observação) e "Pedir a aprovação do gestor".
- O pedido vai para o gestor como as outras liberações: aparece na tela dele (com o aviso no celular), com "Aprovar a venda futura" e "Negar"; a senha do gestor no aparelho também vale. O pedido não some da tela do gestor enquanto ele não responder. Lançada pelo gestor, vale na hora.
- Aprovada, entram no calendário (e em "Atividades da semana") dois lembretes, às 10h: FECHAR (no dia em que o cliente disse que fecha) e COBRAR (no dia do pagamento), com o cliente, o que, o valor, a vendedora e quem aprovou.
- Nas Festas, o quadro "Vendas futuras" mostra cada uma: esperando o gestor, no calendário, cobrar hoje, cobrar · atrasada, paga ou negada. "Pago" marca o COBRAR como feito; a equipe pode marcar esse lembrete (os outros da agenda continuam só do gestor). A venda em si (a festa, o pacote) continua sendo feita como sempre, quando o cliente fechar.

Cadastros nas comissões: "o nome dos cadastros tem que contar lá nas comissões ... quando eu cadastrar alguém, eles têm que puxar pra comissão".

- O cadastro novo pergunta "Quem está cadastrando?" (os vendedores da unidade); já vem marcado quem está logado, e é só tocar em outro nome se for outra pessoa. Sem nome tocado (a tela antiga), fica quem está logado.
- Finanças → Comissões: cada cadastro novo conta para quem cadastrou (uma família por cadastro: os irmãos cadastrados juntos, no mesmo dia, contam um). Na lista aparece "Cadastro novo" com o nome do responsável e das crianças. O bônus vem da regra "Cadastro novo" (Regras de comissão → + Nova regra → "Cadastro novo" → o valor fixo por cadastro); sem essa regra, o cadastro conta, mas não paga bônus.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. Vendedores por unidade, as vias dos cupons e o sistema sempre aberto (2.07, Finanças 5.1)

Pedidos do dono (25/11).

- "Tira a questão dos 5 minutos do sistema, não precisa, ele tem que ficar logado, aberto na tela do atendente": a saída sozinha depois de 5 minutos parado (2.02) está desligada. O sistema fica aberto até alguém tocar em Sair.
- "Na opção de reimpressão eu tenho que reimprimir as 2 vias quando tiver a opção de 2 vias": a reimpressão volta com as duas vias (a da loja e a do cliente), as duas marcadas REIMPRESSÃO, quando o cupom tem duas (a entrada e a saída do carrinho de bebê).
- "No carrinho de bebê, ele tem que sair 2 vias também": o cupom da saída do carrinho de bebê sai em duas vias: a da loja (o responsável assina) e a VIA DO CLIENTE. O fusquinha continua com uma.
- "Tira de todos os comprovantes a quantidade de horas do pacote que o cliente tem": o cupom não diz mais as horas guardadas do cliente. Na entrada com pacote sai "Entrada 14:05 · pacote de horas"; o pacote comprado sem entrar sai com "Para (nome da criança)". O tamanho do pacote comprado ("5h de recreação") continua.
- "Os vendedores do Anália são vendedores do Anália, os da Casa Tom são da Casa Tom ... todo mundo que tiver usuário, você já pode colocar como vendedor": os vendedores de cada unidade são as pessoas com usuário daquela unidade (Ajustes → Equipe → a unidade da pessoa); quem não tem unidade escolhida aparece em todas. Na venda ("Quem está vendendo?", "Quem está atendendo?" e a venda da Casa Tom), cada unidade mostra só os seus, em ordem alfabética, e o servidor recusa o nome de outra unidade. Os nomes da lista antiga das Finanças continuam aceitos, para a tela ainda não atualizada não perder a venda.
- Finanças → Comissões: o quadro "Vendedores" mostra quem vende em cada unidade (não há mais a lista de nomes para editar ali: para pôr ou tirar alguém, Ajustes → Equipe no Play Space).

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A Casa Tom sem nada cortado no notebook (2.06)

Pedido do dono, com a foto da tela Vender da Casa Tom num MacBook: "O casa tom está cortando".

- O botão do quadro da venda ("Escolha os produtos", "Receber R$ ...") fica inteiro dentro do quadro (antes passava da borda direita e era cortado).
- As categorias quebram em linhas: todas ficam à vista, nenhuma cortada no fim da linha.
- A coluna "Atividades da semana" (da Play Space; o menu da Casa Tom não tem Agenda) não aparece nas telas da Casa Tom, e a tela da Casa Tom usa até 1400 px de largura: num MacBook os produtos ficam em 5 colunas (eram 3). Ao voltar para a Play Space, a coluna volta.
- Em notebook de tela baixa, a logo T O M fica inteira no alto do menu vermelho (antes ficava por baixo do primeiro botão).

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. O compilado novo do banco de horas: o aviso de perfil duplicado em quem já está cadastrado (2.05)

Pedido do dono: "faça um compilado mais atualizado possível de todas as horas, cancele todos os CPFs duplicados, igual a gente fez, e coloque uma observação nesses que são CPF duplicados no cadastro deles, para que a gente consiga visualizar."

- O compilado (feito fora do sistema, a partir do sistema antigo, só leitura) junta os cadastros repetidos do mesmo CPF num só, com as crianças juntas e as horas somadas, e põe nas observações de cada criança desse CPF o aviso "PERFIL DUPLICADO NO SISTEMA ANTIGO (N CADASTROS JUNTADOS)". O arquivo vai direto para o dono (tem dados de clientes: nunca fica no repositório nem em página publicada).
- Ajustes → Importar clientes e horas: até a 2.04 o aviso só entrava nos cadastros novos. Agora ele entra também em quem já está cadastrado (feito à mão ou de uma importação anterior): vai na frente da observação que já havia, e a anterior fica guardada, como em toda mudança de observação. Uma vez só: quem já tem o aviso não recebe de novo. A folha conta antes ("N que já estão cadastrados recebem nas observações o aviso PERFIL DUPLICADO") e o aviso final diz em quantos entrou.
- As horas do sistema anterior entram uma vez só por família (o CPF do responsável). O compilado mais novo pode pôr as horas da família em outra criança (a que mais usou o pacote no último ano); se uma criança desse CPF já recebeu horas numa importação anterior, a outra entra sem horas, e a folha avisa ("N de família que já recebeu as horas ... entra sem horas"). Na mesma importação nada muda: irmãos com horas próprias na planilha recebem cada um as suas.
- A importação que só põe avisos (nenhum cadastro, nenhuma hora) aparece em "Hoje na recepção" e não toma o lugar da anterior no Desfazer. O Desfazer tira os cadastros e as horas da importação; o aviso que entrou em quem já estava cadastrado fica.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A nota pelo CNPJ da unidade: Morumbi ou Anália Franco (2.04, com o Notas 187)

Pedido do dono: "o que a gente colocou é do Morumbi. Agora eu preciso colocar o certificado do Anália ... quero um botão para trocar entre Morumbi e Anália para mim saber se eu estou emitindo nota fiscal no Morumbi ou na Anália Franco".

- O Play Space Notas 187 (o pacote playspace-notas-187.zip, subido em /notas pelo cPanel, como os outros) tem duas empresas no mesmo site: cada uma com o CNPJ, o certificado, a emissão (simulação, teste, produção), a numeração, os clientes, os serviços e as notas dela.
- A conta principal acrescenta a outra em Conta → Empresas (CNPJ) → "Acrescentar outra empresa": o nome no botão (Anália Franco), o nome da atual no botão (Morumbi), a razão social, o CNPJ e a inscrição municipal. Ela nasce com os serviços (e os códigos) da atual, em simulação. Depois, com ela em uso: Conta → Certificado digital (o da Anália) e Emissor Nacional → Produção.
- O botão de troca fica no alto do Início ("Emitindo por [Morumbi] [Anália Franco]", com o CNPJ), no menu do computador e na tela de emitir ("Emitindo por ANÁLIA FRANCO · CNPJ ..."). Cada pessoa volta na empresa que usou por último. Os usuários valem para as duas.
- No Play Space (2.04), a nota em um clique sai pelo CNPJ da unidade da compra (Anália ou Casa Tom Anália → Anália; Morumbi ou Casa Tom Morumbi → Morumbi), pelo nome no botão do Notas. A folha da nota mostra "A nota sai pelo CNPJ de [Morumbi] [Anália Franco]" já escolhido, com o CNPJ, e dá para trocar ali. O Notas recebe a empresa em cada pedido, sem mudar a que está aberta na tela dele. Com uma empresa só, nada muda.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A importação traz as contas a pagar e mais fornecedores (2.03, Finanças 5.0)

Pedido do dono, com a planilha do financeiro: "essa é a planilha com fornecedor, contas a pagar, esse campo que colocamos tem que puxar tudo isso".

- A planilha só tem o que já foi pago (a última despesa é de novembro). As compras parceladas ("parc 2/10") dizem o que ainda falta: cada série (o mesmo total de parcelas, o mesmo valor e a mesma filial) continua mês a mês a partir da última parcela lançada.
- As parcelas que vencem depois do último dia da planilha entram em **Contas a pagar** (a pagar, com o vencimento), com o fornecedor, o grupo e o centro de custo da última. A que venceria antes do último dia não entra (foi paga e lançada de outro jeito).
- A conferência da importação mostra as contas a pagar achadas: quantas, quanto, de quando a quando, e a lista. O aviso do fim diz quantas foram para Contas a pagar.
- Mais fornecedores reconhecidos nas despesas (os nomes de empresa da planilha que faltavam): Muvile Confecções, Witec, Dorel, Officina do Acrílico, Vitrine Cartoon, Festíssima, Benvollf Machado, Universo Sublimático, GS1, Exata (nos honorários), Young, os laudos, Marcas e Patentes, Uber, o eletricista. Folha, extras, vales, impostos e aluguel continuam sem fornecedor.
- Conferido com a planilha do financeiro: 45 contas a pagar (R$ 33.311,23, de 25/11/2026 a 17/07/2027) e 57 fornecedores ligados a 827 despesas (eram 44 e 704). Importar de novo não repete nada; a conta paga no sistema continua paga.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A relação de produtos, a unidade de cada pessoa, uma janela só, o login com espaço, a nota em um clique, a saída sozinha e a importação na tela inicial das Finanças (2.02, Finanças 4.9)

Pedidos do dono no fim do dia da virada.

**Importar relação** ("faz um botão importar relação no produto que eu não consegui")

- O botão "Importar relação" está em Ajustes → Produtos e na aba de pacotes e produtos (só o gestor), além da tela Produtos da Casa Tom.
- A folha pergunta onde os produtos vão ser vendidos: Casa Tom, Play Space ou as duas. O que já existe com o mesmo nome fica com o preço de lá e passa a valer também onde foi escolhido.
- A Play Space mostra só os produtos dela. Os que são só da Casa Tom ficam na Casa Tom; em Ajustes, uma linha diz quantos são.

**A unidade de cada pessoa** ("quem eu selecionar a unidade não pode trocar a unidade, a não ser o gestor, que pode ver tudo de todas as unidades")

- Ajustes → Equipe → a pessoa → Unidade: "Qualquer uma (escolhe no aparelho)" ou uma unidade. Com a unidade escolhida, a pessoa só usa essa unidade: o aparelho fica nela e não troca, e o servidor guarda tudo o que ela faz nessa unidade, venha o pedido de que aparelho vier.
- Quem tem a unidade escolhida vê só o movimento dela: quem está no espaço, os carrinhos fora, as festas e os pedidos ao gestor dessa unidade (o caixa do dia já era o da unidade do aparelho). Os clientes (o cadastro) continuam à vista de todos.
- O gestor troca sempre e vê todas as unidades. Quem não tem unidade escolhida fica como antes.

**Uma janela só por computador** ("eu não quero que o sistema abra mais de uma tela no mesmo computador ... se tiver uma aberta ele dá informativo: já tem um sistema aberto")

- A segunda janela (ou aba) do Play Space no mesmo computador não liga o sistema: mostra "Já tem um Play Space aberto neste computador", com "Usar esta janela" (a outra para, com o aviso, e esta abre) e "Fechar esta". Fechada a janela, a vez fica livre.
- Vale para as janelas do mesmo navegador (o ícone Play Space abre sempre o mesmo). Um navegador diferente no mesmo computador não enxerga o outro.
- Para teste ou suporte, a opção do aparelho ps-varias-janelas (no armazenamento do navegador) deixa abrir várias.

**O login com espaço** ("tentei criar um usuário tipo Julia Alves, ele não deixou ... quero com o espaço")

- Na Equipe (e no primeiro gestor) e nas Finanças, o login pode ter espaço: "Julia Alves" fica "julia alves". Fica em minúsculas, com um espaço só entre as palavras, e o acento vira a letra sem acento.
- Antes, a Play Space tirava o espaço ("juliaalves") e as Finanças trocavam por ponto ("julia.alves").
- Para entrar, vale digitar com maiúscula, acento ou espaço a mais. Nas Finanças, quem já tinha o login com ponto entra também com o espaço. Login repetido continua recusado.

**A nota fiscal em um clique** ("com um clique, igual da Gama, já fazer todo aquele processo de emissão, só aparecer a confirmação dos dados e emitir. E pronto")

- O botão NF da compra abre a confirmação: em nome de quem, CPF ou CNPJ, e-mail, a compra e o valor. "Emitir a nota fiscal" emite na hora pelo Play Space Notas, sem abrir outra aba. Emitida, o número marca a compra sozinho.
- Por trás: o Play Space fala com o Notas do mesmo site. O cliente é achado no Notas pelo CPF/CNPJ (ou cadastrado ali, com o nome conferido), o serviço é o da compra (festa, pacote de horas ou recreação), a alíquota é a da empresa no Notas.
- O computador precisa estar com o Notas entrado (uma vez; o login do Notas dura 30 dias). Se não estiver, a própria folha pede o login e a senha do Notas.
- Cada compra tem um código de nota fixo: tocar de novo não emite outra; se a nota já tinha saído, o número é só marcado. A recusa do governo aparece na folha e a compra não é marcada.
- Com o Notas em teste ou simulação, a folha avisa antes, a nota sai no Notas e a compra não é marcada (como na 1.55). "Abrir no Play Space Notas", "Copiar os dados" e o número escrito à mão continuam para corrigir algo lá.

**Sai sozinho depois de 5 minutos** ("o computador tem que deslogar a cada 5 minutos do sistema, só por questão de segurança")

- No computador, 5 minutos sem ninguém mexer (mouse, teclado, toque) e o sistema sai sozinho, de volta ao login, com o aviso "O sistema saiu sozinho depois de 5 minutos sem uso". Nos últimos 30 segundos aparece "Por segurança, o sistema sai sozinho em X s", com "Continuar aqui".
- Não sai sem internet (não daria para entrar de novo), com registro feito sem internet esperando, nem no meio da emissão de uma nota. No celular e no tablet não sai (o gestor recebe os pedidos lá). As Finanças ficam como estavam.
- Para teste ou suporte, a opção do aparelho ps-sem-sair-sozinho (no armazenamento do navegador) desliga.

**Finanças 4.9: importar dados do sistema anterior na tela inicial** ("coloque importar dados do sistema anterior ... a opção para anexar o Excel ... na tela inicial ... ele lê todos os dados e já destrincha e já preenche tudo")

- A tela inicial das Finanças (Controle de caixa) tem o cartão "Importar dados do sistema anterior", com "Anexar o Excel". O botão abre o importador da 2.01 e já pede o arquivo.
- O importador lê as quatro abas com dados (Faturamento, Despesas com os fornecedores e os custos, Recebimentos líquidos e Rede), todos os meses. A conferência mostra, em cada aba, as linhas, a soma e os meses achados, e diz quais abas são só gráficos, comparativos e resumos (o sistema refaz com o Painel e o Relatório financeiro).
- Depois de importar, o cartão fica numa linha: quando foi, quem importou e quantas linhas, com "Importar de novo" (a planilha atualizada não repete nada). Em Lançamentos o botão virou "Importar do sistema anterior".
- Conferido com a planilha do financeiro (23 abas): 10.499 linhas, as receitas com o total da planilha e 44 fornecedores ligados às despesas.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A planilha do financeiro inteira, a equipe e o ícone do computador (2.01, Finanças 4.8)

Pedidos do dono no dia da virada.

**A planilha do financeiro** ("todas as informações que constem aí vão constar dentro do nosso sistema; fornecedor ... tudo que tiver de cadastro pode puxar")

- Finanças → Lançamentos → Importar a planilha lê também o que a planilha de verdade tem de diferente: a data escrita como texto; o ano que destoa da linha de cima no mesmo mês (2024 ou 1016 no meio de 2026: vira o ano da linha de cima); a despesa com o valor e a obs trocados de coluna; a despesa do cartão sem data (entra no dia escrito na obs ou no dia da linha de cima). Os quadros e totais do fim ficam fora.
- Tudo o que o sistema acertou na leitura aparece na prévia ("acertos na leitura"), com a aba e a linha, e fica escrito na obs do lançamento.
- O fornecedor sai da descrição da despesa (Vivo, Enel, Contabilidade Exata, Costureira Silvana...) e entra no cadastro de Fornecedores, ligado às despesas.
- Importar de novo a mesma planilha (atualizada) não duplica: o que já está fica como está, o que foi corrigido no sistema continua corrigido, o que foi tirado continua tirado. Vale também para o que foi importado antes desta versão (as linhas iguais são reconhecidas).

**Os produtos da Casa Tom**: na planilha de produtos, o que já existe com o mesmo nome fica com o preço de lá e passa a valer também na Casa Tom (o cadastro serve as duas lojas).

**A equipe**: quem sai da equipe continua guardado (com o que fez), mas solta o login. Dá para criar alguém novo com o mesmo login, inclusive de quem saiu antes desta versão.

**O ícone do computador** ("está abrindo só metade"): o instalador (Ajustes → Ícone na área de trabalho) abre a janela maximizada. Aberto pelo ícone (ou instalado como app), o sistema ocupa a tela inteira na primeira vez de cada abertura. Quem já tem o ícone baixa o instalador de novo e roda uma vez: o ícone é refeito.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A Casa Tom (2.00)

O dono: "agora a gente está fazendo um sistema para a Casa Tom, lojas adjacentes ... quando eu mudar para a Casa Tom, ele tem que mudar o sistema, por dentro do sistema mesmo". Escolheu o visual mais limpo, com o menu vermelho.

**O sistema da Casa Tom**

- Com o aparelho numa unidade Casa Tom (CASA TOM ANÁLIA, CASA TOM MORUMBI), o sistema troca de cara: o menu vermelho com a logo T O M e as telas dela. Vender (busca, categorias, a lista da venda, quem está vendendo e o Receber), Vendas (o caixa do dia da Casa Tom) e Produtos (o cadastro). Aprovar e Ajustes continuam lá para o gestor.
- "Ir para a Play Space" (no alto) volta para a unidade da Play Space do mesmo lugar, no sistema de sempre.
- O Receber abre o mesmo pagamento de sempre: as formas, as parcelas, o desconto do gestor. A venda vai para o cupom ("Cupom da venda", com a logo e o nome da Casa Tom), para as comissões (quem está vendendo) e para as Finanças, pela unidade do aparelho.
- A venda vai em nome do "cliente da loja": um cadastro só, que nasce quando um aparelho entra na Casa Tom pela primeira vez. Ele não aparece nas listas e não entra no espaço.

**Os produtos por loja**

- Cada produto diz em que loja é vendido: Play Space, Casa Tom ou as duas ("Vende em", no cadastro do produto), e pode ter uma categoria.
- A Casa Tom mostra só os produtos dela; "Ver todo o cadastro" mostra todos. O atendimento da Play Space não mostra os que são só da Casa Tom.
- Produtos → Importar planilha (só o gestor): colunas PRODUTO, PREÇO e, se tiver, CATEGORIA. Os novos entram na Casa Tom; o que já existe com o mesmo nome fica como está. Linha sem preço não entra.
- A planilha dos produtos da Casa Tom (do Bling, sem repetidos, com o último preço cheio e as categorias) foi entregue ao dono para importar. Ela não fica no repositório.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. O telefone com o tracinho e os serviços por unidade (1.99)

Pedidos do dono no dia da implantação.

**O telefone com o tracinho** ("separa o DDD, o 9 e o número")

- Na tela, no cupom e ao digitar no cadastro: o celular fica (11) 9-1234-5678 e o fixo (11) 1234-5678. O internacional (com o +) fica como veio.
- O que já estava guardado não muda: a tela mostra sempre com o tracinho.

**Os serviços por unidade** ("tira o carrinho de bebê da Anália e o fusquinha, não tem aqui")

- Ajustes → o serviço → "Tem nas unidades": desmarcada, a unidade não vende o serviço; ele some do atendimento, dos filtros da lista e do menu de lá, e o servidor também recusa.
- Na primeira vez desta versão, o que sai e volta (carrinho de bebê, fusquinha) já vem desmarcado na Anália (e na Casa Tom Anália). O gestor pode marcar de novo.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A planilha inteira de uma vez na importação (1.98)

Pedido do dono, na implantação: "coloque tudo em um só arquivo então para colocar só uma vez".

- Ajustes → Importar clientes e horas aceita a planilha inteira de uma vez: até 60 mil linhas (antes, 10 mil). Acima disso, o aviso "Importe em partes de até 60.000".
- A tela manda ao servidor em lotes de 400 linhas; o botão mostra "Importando… X de Y · não feche esta tela". Se a internet cair no meio, o botão vira "Continuar a importação" e segue de onde parou.
- É uma importação só: o Desfazer tira tudo de uma vez.
- Importar o mesmo arquivo de novo não repete nada: quem já está cadastrado não muda e as horas do sistema anterior entram uma vez só (a tela mostra "Nada para importar").

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. Começar as Finanças do zero e a data de nascimento na importação (1.97 · Finanças 4.7)

Pedido do dono, na véspera da implantação: "zere todo o financeiro" e "importe também o perfil do cliente".

**Começar as Finanças do zero** (Finanças → Conta, só a conta principal)

- O cartão mostra o que há hoje nas Finanças (lançamentos, conferências do caixa, festas lançadas, dias de freelancer, planilhas, adiantamento; quantos fornecedores e freelancers cadastrados).
- "Zerar as Finanças…" abre a folha: o que vai ser apagado, duas marcações (também os fornecedores; também os freelancers cadastrados) e a senha de quem está entrando, para confirmar. Senha errada não apaga nada.
- Ficam os acessos, as regras das comissões, as metas, os valores e os e-mails. O Notas não é mexido. Antes de apagar, o sistema guarda uma cópia do banco (como no Começar do zero do Play Space).
- Depois, o cartão diz quando e quem zerou por último.

**A data de nascimento na importação** (Ajustes → Importar clientes e horas)

- A planilha pode ter a coluna NASCIMENTO (dd/mm/aaaa, d/m/aa ou aaaa-mm-dd): a data vai para o cadastro e a idade sai dela. Data que não existe ou no futuro: o cadastro entra sem a data.
- Os arquivos preparados do sistema antigo já trazem essa coluna (e, nas observações, o aviso "PERFIL DUPLICADO NO SISTEMA ANTIGO" de quem tinha mais de um cadastro com o mesmo CPF).

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz. O PDF das Festas nos blocos coloridos (1.96 · Finanças 4.6)

Pedido do dono (com a foto do PDF das Festas): "me dá 3 opções mais modernas esteticamente". Foram feitas três (branco e limpo; faixa escura; blocos coloridos) e o dono escolheu a 3.

- A página fica creme. No alto: a logo, "Festas", o mês e a unidade, e o ursinho com o recado no balão.
- O **lucro real do mês** num bloco coral grande, com a barra da margem, o lucro por festa e o ticket médio. Se os custos passarem o valor das festas, o bloco fica vermelho.
- Ao lado, o **valor bruto** (amarelo) e os **custos** (verde, com quantas festas ainda estão sem os custos). Embaixo, festas, crianças e a receber em blocos brancos.
- **Para onde vai o dinheiro das festas**: a frase ("Dos R$ X das festas, R$ Y ficam de lucro real."), uma barra colorida e as etiquetas de buffet, salgados, papelaria, gasto do adiantamento e lucro real.
- **Cada festa** num cartão: a data num círculo colorido, o aniversariante com o tema, a unidade, as crianças e o pagamento; bruto, custos ("falta lançar" quando ainda não foram lançados) e lucro. No fim, a linha do total.
- Na segunda página, o lucro de cada festa e como foram pagas, em cartões brancos. O texto ao lado das barras do crédito (como "liberado pelo gestor") não fica mais em cima da barra.
- Os outros PDFs das Finanças (Freelancers, Caixa, Comissões, Vendas por cliente) continuam como estavam.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzzz. A Agenda abre uma vez só (1.95)

Pedido do dono: "na aba Agenda, quando abre, está dando um pequeno bug e abrindo 2 vezes".

- A parte do dia da Agenda ("Hoje, 23 de novembro" e "Também nesta semana") tinha a sua animação de subir e, desde a 1.74, também a entrada das telas. Quando a entrada terminava, a animação antiga começava de novo: a parte do dia subia duas vezes.
- Agora ela sobe uma vez só ao abrir a Agenda (no computador e no celular, para todos). Ao tocar em outro dia do calendário, a parte do dia continua subindo uma vez, como antes.
- As outras telas do Play Space e das Finanças foram conferidas: nenhuma tinha o mesmo problema.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzzz. O ✓ da senha do gestor e o menu do lado de vidro (1.94 · Finanças 4.5)

Pedido do dono, com dois vídeos de modelo ("Glassy OTP" e "Glassy Sidebar"): "coloque essas 2 animações". O primeiro vai na senha do gestor; o segundo, no menu do lado do Play Space e das Finanças, no computador.

**O ✓ da senha do gestor**

- Quando a senha do gestor libera um pedido na hora (desconto, crédito em mais vezes, alterar valor, alterar pacote, nota fiscal) e quando o gestor toca em **Liberar** (no pedido em destaque ou na lista de Aprovações), aparece no meio da tela um cartão de vidro: quatro quadradinhos ligados por linhas se juntam no meio e viram um quadrado verde com o ✓ e "Liberado por" o nome de quem liberou.
- Leva uns 2 segundos, some sozinho e não segura nada: o atendimento continua por baixo, os toques passam.
- Senha errada, só pedir (sem a senha) e **Negar** não mostram o ✓.
- Com "reduzir movimento" (no aparelho ou em Ajustes), o ✓ aparece parado, sem os quadradinhos.

**O menu do lado de vidro (computador)**

- Play Space: tocar na **logo** do alto do trilho abre o trilho num painel de vidro, por cima da tela (a tela não anda), com o nome de cada item entrando um depois do outro. Uma luz clara desliza atrás do item que está sob o mouse.
- Finanças: tocar na **logo** (o ícone amarelo do alto) abre a barra escura do mesmo jeito, com os nomes, os grupos e o nome de quem entrou; o círculo do item aberto vira uma pílula.
- Escolher uma tela, tocar fora, apertar **Esc** ou tocar na logo de novo fecha. Numa janela estreita (celular, tablet em pé) nada muda.
- O trilho de ícones continua como era: o menu aberto fixo segue desligado, como o dono pediu na 1.34.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzzz. A barra de baixo das Finanças no celular com o + no meio (1.93 · Finanças 4.4)

Pedido do dono (com a foto de um modelo "Center FAB"): "no Finanças, no telefone, eu quero assim". Antes, a barra de baixo tinha as vinte telas numa fila que rolava para o lado.

- A barra agora tem cinco lugares: **Caixa**, **Visão**, o **+** redondo no meio, **Painel** e **Menu**. O + fica maior, acima da barra, encaixado num recorte dela. A aba aberta fica escura, com um tracinho coral em cima.
- O **+** abre "O que você quer lançar?": **Despesa** e **Receita** abrem os lançamentos já com o formulário novo aberto; **Adiantamento** e **Freelancer** abrem as telas deles. Tocar de novo no + (que vira um ×) ou fora fecha.
- O **Menu** abre todas as telas, nos mesmos grupos do menu do computador (Caixa e equipe; Loja · caixa do sistema; Financeiro; Conta). Quando a tela aberta não está na barra, o Menu fica aceso.
- No computador nada muda: continua o menu escuro do lado.

## 6zzzzzzzzzzzzzzzzzzzzzzzzzz. O PCD volta para a atendente (1.92)

Pedido do dono, sobre a 1.91: "pode deixar para a atendente, para o operador mudar para PCD, não tem problema". A senha do gestor que a 1.91 passou a pedir para marcar "Criança PCD" no cadastro novo saiu: a atendente marca direto, como antes. A linha do dia continua dizendo quem marcou ("Cadastrada como criança PCD · por ...").

As horas de pacote, como sempre: o pacote vendido entra no faturamento no dia da venda (com o pagamento); quando a criança usa as horas, o sistema desconta do saldo dela, sem cobrar de novo. Nada disso mudou na 1.91.

## 6zzzzzzzzzzzzzzzzzzzzzzzzz. A abertura animada, o "Começar do zero" com as Finanças e a auditoria (1.91)

**A abertura animada.** Pedido do dono (com um vídeo de exemplo): "a abertura com uma animação assim, mas sem demorar para abrir a tela". Ao tocar em Entrar (o servidor confirma a senha), o cartão do login escorrega e some desfocando; o sistema se monta em sequência: o trilho e os ícones da esquerda, o cabeçalho, os blocos do meio e a coluna da direita (no celular, a barra de baixo sobe). A tela principal aparece em menos de 1 s depois do toque; tudo fica pronto em menos de 1,5 s. Acontece uma vez por abertura (trocar de tela não repete). Com "reduzir movimento" no aparelho, ou no modo leve, entra direto, sem animação.

**"Começar do zero" (Ajustes, só o gestor, com a senha).** Pedido do dono: "tirando o sistema de nota, pode zerar todo o sistema"; escolha dele: as Finanças também (o movimento), ficando as regras e os cadastros.

- **Sai:** os cadastros (crianças e responsáveis, com as fotos), as horas compradas, as vendas e os pagamentos, as entradas e saídas, o movimento do dia, os estornos e as trocas de forma de pagamento, e o **movimento das Finanças**: os lançamentos, o controle de caixa (e os relatórios da Rede), os custos das festas, os dias dos freelancers e o fluxo de caixa deles, as planilhas importadas, os comprovantes e o Adiantamento. As marcas das metas batidas também saem (para comemorar de novo).
- **Fica:** a equipe e as senhas, a regra das horas, os serviços e preços, os pacotes, os produtos, as unidades, a agenda e os ajustes; nas Finanças, os logins, os fornecedores, os freelancers (o cadastro), as regras das comissões e as metas. O **Notas** não é tocado (é outro banco).
- A prévia mostra quantos lançamentos das Finanças vão sair. O **Desfazer** traz tudo de volta (também as Finanças) enquanto não houver cadastro novo nem lançamento novo nas Finanças.
- Como fazer no sistema da loja: entrar como gestor → Ajustes → **Começar do zero** → conferir o que sai e o que fica → senha do gestor.

**A auditoria (1.91).** Pedido do dono: "faça uma auditoria completa de todos os processos de pedidos, procura dos bugs e falhas que o operador possa explorar; auditoria em todas as contas feitas pelo sistema, descontos etc". Três revisões separadas (o que a atendente consegue fazer, as contas da venda e as Finanças); cada falha foi reproduzida numa cópia antes de corrigir. Corrigido:

- **O "a receber" não some à meia-noite.** A venda "paga na saída" de uma visita cuja entrada foi cancelada (um produto, por exemplo) ficava a receber só até a meia-noite: no dia seguinte sumia da ficha e da saída. Agora continua a receber desde a última saída da criança (olhando os últimos 60 dias), até ser recebida.
- **PCD no cadastro novo:** a atendente marcava "Criança PCD" (50% na recreação) sozinha. Agora aparece o campo "Senha do gestor"; sem a senha (ou sem internet) não cadastra como PCD. O gestor e o supervisor marcam direto. A linha do dia diz quem liberou.
- **A saída "feita sem internet" com a hora inventada:** a hora mandada só vale se nenhum aparelho da mesma unidade falou com o servidor naquela hora (antes valia só o próprio login; com outro login, ou o relógio do computador mudado, passava).
- **A unidade da venda:** a atendente fica na unidade em que entrou (e na Casa Tom do mesmo lugar), também no servidor. O gestor e o supervisor trocam.
- **A cópia de segurança:** quem baixa a cópia da semana e não é o dono das Finanças recebe a cópia sem os dados das Finanças.
- **O estorno de outra unidade:** a venda de outro dia cancelada no aparelho de outra unidade tirava o dinheiro do dia da unidade errada. O estorno guarda a unidade em que o dinheiro entrou.
- **Casa Tom:** a unidade guarda o dia em que virou Casa Tom; apagar ou renomear não muda mais o passado.
- **Comissão:** a venda de valor zero (desconto de 100%) não paga mais o bônus fixo.
- **Contas:** a divisão do desconto da conta do responsável entre as crianças errava 1 centavo em contas exatas (18,42 + 18,39 + 18,39 em vez de 18,40 para cada); os valores digitados (o "outro" da venda, os preços dos pacotes e dos serviços) ficam com 2 casas; na meta do mês, os dias fixados não passam da meta; as datas do Adiantamento e dos freelancers vão de 2000 até um ano para frente.

Conferido e **mantido como está** (era a escolha feita antes): a supervisora libera o "Alterar valor" que ela mesma pediu (1.33); todos veem o mês de cada unidade nas metas (1.87); a diária do freelancer paga a menos conta como paga, com a diferença anotada (1.65); mudar uma regra de comissão vale na hora, para corrigir (para mudar a partir de um dia, a regra tem o "desde" e o "até"). Ficaram de fora, com o motivo: as falhas do leitor de rosto (iDFace), que não está em uso; o convidado de festa que entra fora do horário da festa (anotado para uma próxima versão); o dia só com estorno nas Finanças contra a Rede (raro, conferido à mão).

## 6zzzzzzzzzzzzzzzzzzzzzzzz. A baixa automática e o Adiantamento por unidade (1.90 · Finanças 4.3)

**A baixa automática ("o relógio interno").** Pedido do dono: uma criança ficou marcada no espaço de um dia para o outro (ninguém deu a saída) e a tela mostrava 48 h. A conta da visita já parava na hora em que a loja fecha (Ajustes → "A loja fecha às", no dia da entrada; nunca mais de 12 h), mas o relógio da lista, o "passou" e o saldo de horas da tela continuavam contando.

- O relógio, o "passou" e o saldo da tela param na mesma hora da conta. A linha diz "sem saída · contou até 22:00 de 20/11"; a folha da saída explica que nada além disso é cobrado.
- Virado o dia (e passada a hora em que a loja fecha), a visita **sai sozinha**, contada só até lá, quando não há nada a receber (o pacote cobre o tempo e nada vendido ficou para a saída). Fica na linha do dia: "saída automática: ninguém deu a saída; o tempo contou até 22:00 de 20/11".
- Com algo a receber (recreação avulsa, tempo além do pacote, produto para pagar na saída), a criança **continua na lista** com o relógio parado, para a equipe dar a saída e receber. A conta é a mesma: só até a loja fechar. O gestor pode corrigir a hora da saída, como antes.
- Dois aparelhos ao mesmo tempo não dão a baixa duas vezes.

**Finanças → Adiantamento** (logo abaixo de Freelancers). Pedido do dono: "uma aba adiantamento por unidade. No Anália eu coloco 700 e no Morumbi 1500; vou discriminando do que foram esses valores e ele me dá o quanto sobrou".

- Um cartão por unidade: **Sobrou** (grande; em vermelho, "Gastou a mais"), o que veio do mês anterior, o colocado e o gasto no mês.
- **Colocar valor** (o dinheiro deixado na unidade) e **Lançar gasto** (no que foi usado: a descrição é obrigatória). O que sobra continua para o mês seguinte.
- Tocar num lançamento muda (fica "mudado", com quem e o que era); "Tirar" é para o lançado por engano (fica guardado quem tirou).
- Fica à parte: não entra no Painel, nos Lançamentos, no Relatório financeiro nem no Controle de caixa.

## 6zzzzzzzzzzzzzzzzzzzzzzz. O crédito em quantas vezes em todo pagamento (1.89)

Pedido do dono: "quando clica no crédito, tem que aparecer a opção de 1 a 4 vezes; as outras, até 12, só com o gestor liberando; de 1 a 4, liberado por operador". Até a 1.88 isso era só na venda da festa.

- **Onde**: em toda tela de pagamento: a venda (pacote, produto, recreação), a saída (e a saída em conjunto do responsável), a devolução do carrinho e o "Receber" da ficha. Ao tocar em **Crédito**, aparece "Em quantas vezes no crédito?" com **1x a 4x** (qualquer um escolhe) e o "**Mais vezes no crédito (5x a 12x)**".
- **5x a 12x**: o gestor escolhe direto. A atendente toca em "Pedir ao gestor" (aparece na tela dele, como os outros pedidos) ou "Liberar com a senha" do gestor ali mesmo; o botão de confirmar fica travado ("Peça ao gestor o crédito em 8x") até liberar. A liberação vale uma vez, para aquele cliente (na saída em conjunto, para os irmãos da mesma conta). O pedido diz de onde é ("· venda", "· saída", "· devolução", "· a receber", "· festa").
- Escolher as vezes não é obrigatório (sem escolher, fica como antes). O limite livre é o mesmo do "em até 4x" da festa.
- O servidor confere antes de registrar (na saída, antes de dar a saída) e guarda as vezes nas vendas pagas no crédito; o PDF do caixa das Finanças ("em quantas vezes no crédito") passa a contar todas as vendas, não só as festas.

## 6zzzzzzzzzzzzzzzzzzzzzz. As Festas das Finanças arrumadas (1.89 · Finanças 4.2)

Pedido do dono (com a foto da tela): "achando meio jogado as informações; alinhar mais, deixar mais intuitivo, aproveitar mais o espaço".

- A tabela das **Finanças → Festas** passou de 12 para 9 colunas e cabe na tela (antes a última ficava cortada): **Festa** (o nome, o dia e a hora, o selo "falta lançar", a unidade e o tema), **Venda** (quando e quem vendeu, as crianças, a forma de pagamento, sem repetir o valor), **Valor bruto**, os 5 campos de **Você lança** (Buffet, Salgados, Papelaria, Adiantamento, Retornado; todos do mesmo tamanho, num bloco com fundo próprio) e **Lucro real** (com a margem e o gasto do adiantamento embaixo).
- O "por criança" do buffet fica embaixo do campo sem empurrar a linha. Tudo continua igual no uso: Enter desce para a festa de baixo, o valor é salvo ao sair do campo. No celular, a tabela rola dentro do cartão.

## 6zzzzzzzzzzzzzzzzzzzzz. A Casa Tom de cada lugar como unidade (1.88 · Finanças 4.1)

Pedido do dono: "quem está no Morumbi pode trocar entre a unidade Morumbi e a Casa Tom Morumbi; quem está na Anália, entre a Anália e a Casa Tom Anália". Escolhas dele: vale a unidade do aparelho (o que é vendido com o aparelho na Casa Tom é da Casa Tom; no Play, do Play, até produto); o gestor e o supervisor trocam para qualquer unidade.

- **Ajustes → Unidades → "Criar CASA TOM ANÁLIA e CASA TOM MORUMBI"** (só o gestor; o botão aparece enquanto faltar a Casa Tom de algum lugar). O lugar de uma unidade é o nome sem o "Casa Tom" (ANÁLIA FRANCO e CASA TOM ANÁLIA são da Anália).
- **Trocar a unidade do aparelho** (o botão com o nome da unidade, no alto da tela inicial): a atendente troca só entre o Play e a Casa Tom do lugar do aparelho (MORUMBI ↔ CASA TOM MORUMBI); as outras ficam apagadas. O gestor e o supervisor trocam para qualquer uma.
- **O que é da Casa Tom**: o que for vendido com o aparelho na unidade Casa Tom. Com o aparelho no Play, tudo é do Play, até produto. Isso vale **a partir do dia seguinte** ao que a Casa Tom do lugar foi criada; antes (e nos lugares sem a unidade Casa Tom), a Casa Tom continua sendo os produtos que o Play vende, como era, para o que já passou não mudar.
- O **Caixa do dia** mostra o centro de custo por lugar ("Play Morumbi · vendido no Play", "Casa Tom Morumbi · vendido na Casa Tom"); as **Finanças → Visão geral → Play e Casa Tom**, uma linha por lugar.
- **Metas**: cada Casa Tom tem a sua meta do mês em Ajustes → Metas (como as outras unidades). A equipe de um lugar comemora as metas das duas unidades dele (o Play e a Casa Tom).

## 6zzzzzzzzzzzzzzzzzzzz. As metas do mês, por unidade (1.87 · Finanças 4.0)

Pedido do dono: "pego o faturamento do mês do ano passado, jogo 15, 20, 30%, e dá a meta do mês (por exemplo, Anália Franco 410 mil). Divido pelos dias: no fim de semana o movimento é maior, então a meta é mais alta; durante a semana eu equilibro. Se num dia não bateram, o que fizeram a mais no outro dia cobre: o que importa é entregar o mês cheio." Escolhas dele: cada unidade com a sua meta; o sistema sugere a meta de cada dia e ele ajusta; a tela mostra a meta do dia e o saldo do mês, com fogos maiores ao bater a do mês.

- **Ajustes → Metas** (só o gestor): escolha a unidade e o mês (as setas ‹ ›). Escreva o **mesmo mês do ano passado** e a **% a mais** (a meta do mês sai sozinha) ou a **meta do mês** direto. **Sábado, domingo e feriado valem** 2× um dia de semana (dá para mudar, de 1 a 5). O calendário mostra a meta de cada dia, em valores redondos, somando a do mês. Para **fixar** um dia (um feriado forte, um dia fraco), mude o valor dele: os outros dias se ajustam sozinhos; apague o valor e o dia volta a ser automático. Os feriados nacionais e os de São Paulo já vêm marcados (Carnaval, Sexta-feira Santa e Corpus Christi também). Os dois **alertas** ("quando faltar R$ X") valem para a meta do dia de cada unidade. **Salvar**.
- **No alto de toda tela**, ao lado do relógio: a meta de hoje da unidade daquele aparelho (quanto já foi, em %), e o **saldo do mês** até ontem: "na frente R$ X" ou "atrás R$ X". Um toque abre o mês: hoje, o mês, quanto falta em quantos dias, o que precisa hoje para ficar em dia, e o calendário (verde: bateu a meta do dia; vermelho: não bateu). O aparelho sem unidade escolhida mostra a meta se só uma unidade tiver.
- **Comemoração**: quando a unidade bate a meta do dia, comemoram as telas dela, as do gestor e as Finanças ("Meta do dia batida!", com o nome da unidade); o aviso vai para o celular dos gestores ("ANÁLIA FRANCO: meta de R$ 16.000 batida às 17:42…"). Quando bate a **meta do mês**, a festa é maior ("Meta do mês batida!") e vai outro aviso para o celular ("Meta do mês batida! 🏆"). Cada tela comemora uma vez.
- As vendas contam para a unidade em que foram feitas (a do aparelho). O painel do gestor mostra no "Faturado hoje" quanto da meta já foi (a unidade escolhida, ou todas as que têm meta).
- A meta do dia de antes (um valor só, somando as unidades) saiu: no lugar dela, a meta do mês de cada unidade.

## 6zzzzzzzzzzzzzzzzzzz. Os ícones novos do celular (1.86 · Finanças 3.9)

O dono escolheu, entre 3 sugestões para cada app: o **Play Space** com o castelo creme sobre o **coral** e as **Finanças** com o cartão do gráfico (barras e a seta subindo) sobre o **amarelo**.

- Play Space: o desenho novo nos mesmos arquivos de sempre (`img/icone-180.png`, `icone-192.png`, `icone-512.png`, `icone-maskable-512.png`, o `playspace.ico` do Windows e o `playspace.icns` do Mac). Vale para o ícone do celular, o do computador e o dos avisos.
- Finanças: antes usava o ícone do Play Space; agora tem o seu (`img/financas-*.png`) e o manifesto próprio (`financas/manifest.json`). No celular, "Adicionar à tela de início" nas Finanças cria um app à parte, chamado **Finanças**.
- Quem já tem o ícone na tela do celular: no **iPhone**, apague o ícone e adicione de novo (o iPhone não troca o ícone sozinho). No **Android**, ele se troca sozinho em até um dia (ou apague e adicione de novo). No computador, o atalho da área de trabalho muda ao tocar de novo em **Ajustes → Ícone na área de trabalho**.

## 6zzzzzzzzzzzzzzzzzz. As Finanças ao vivo: a tela aberta se acerta sozinha (1.85 · Finanças 3.8)

Pedido do dono: "o sistema também está comunicando com o sistema de finanças rapidamente, né? Tudo atualizando automaticamente, rapidamente".

- As Finanças sempre leram o mesmo banco do sistema (os dados são os mesmos na hora). Agora a **tela aberta** também se acerta sozinha: a cada **3 segundos** ela pergunta ao servidor se mudou alguma coisa (uma venda, um pagamento, um cancelamento, uma nota, um lançamento, o caixa, uma festa, um freelancer…). Mudou, a aba aberta busca os números de novo e se redesenha no lugar: sem recarregar a página, sem voltar para o alto e sem repetir a animação de entrada; os números que mudaram correm até o valor novo. No teste, a venda feita no sistema apareceu nas Finanças em ~3 segundos.
- **Não mexe** enquanto alguém está escrevendo num campo da tela, com uma janela aberta (um lançamento, uma ficha), com regras de comissão ainda não salvas, nas páginas a mais da lista de lançamentos, nem nas abas **Emitir nota**, **Planilhas** e **Conta**. Fechou a janela, se acerta.
- Página escondida (outra aba na frente) não fica perguntando. De minuto em minuto, a tela busca de novo de qualquer jeito.

## 6zzzzzzzzzzzzzzzzz. Os fornecedores das notas fiscais, cadastrados de uma vez (1.84 · Finanças 3.7)

Pedido do dono: "consegue ler as notas que já tem da Play Space e já deixar cadastrados todos os fornecedores que tem lá?".

- **Onde:** Finanças → **Fornecedores** → **Trazer das notas fiscais**.
- O botão pergunta ao **sistema de notas** (no mesmo navegador, com o login do Notas: o do menu **Emitir nota**) quais fornecedores mandaram nota para a Play Space. São as **notas de fornecedores** que o Notas traz do governo com **Conferir no governo → Trazer o histórico**.
- Aparece a lista: cada fornecedor com o CNPJ, quantas notas, o total e a última nota; quem já está no cadastro aparece como "já no cadastro". Todos vêm marcados; **Cadastrar os marcados** cadastra de uma vez.
- Cada fornecedor novo entra com o **nome, o CNPJ, o telefone e o e-mail** (quando a nota tem) e, nas observações, o nome fantasia, as notas (quantas, de quando a quando, o total) e a cidade. Quem já estava no cadastro (pelo CNPJ ou pelo nome) **não duplica**: só ganha o que faltava; nada do que já estava escrito muda.
- Precisa do pacote do Notas **playspace-notas-184.zip** (ou mais novo) no site.
- Vêm só as **notas de serviço** (NFS-e) do Emissor Nacional. Notas de produto (NF-e, por exemplo de mercadoria) não passam por esse sistema e não aparecem.

## 6zzzzzzzzzzzzzzzz. As telas se acertam a cada 1 segundo (1.83)

Pedido do dono: "quero que o site e o aplicativo atualizem a cada um segundo, o mínimo possível: tudo que aparecer num computador tem que aparecer imediatamente em todos os outros".

- Cada tela aberta pergunta ao servidor **uma vez por segundo** se algo mudou (eram 2 segundos). Quando muda, puxa tudo de novo: o que um computador faz aparece nos outros em **1 a 2 segundos** (no teste, em média 0,4 s).
- A pergunta é leve (uns 6 milésimos de segundo no servidor), então vários computadores ao mesmo tempo não pesam para a hospedagem.
- Quem está **no meio de uma venda, de um pagamento ou digitando** só vê a novidade quando fecha a janela, para a tela não mudar debaixo da mão. Os **pedidos de autorização** chegam na hora mesmo assim.
- Tela **escondida** (outra aba na frente, celular bloqueado) não fica perguntando; ao voltar, se acerta no mesmo segundo. A tela do gestor continua olhando os pedidos de autorização.
- As **Finanças** continuam se atualizando ao trocar de tela.

## 6zzzzzzzzzzzzzzz. O Notas sempre na versão nova, nas Finanças e no botão do sistema (1.82 · Finanças 3.6)

O dono atualizou o Notas no site, mas dentro das Finanças (Emitir nota) continuava o antigo: o navegador do computador tinha guardado a página do Notas e abria a cópia velha (o Ctrl+F5, ou Cmd+Shift+R no Mac, nem sempre limpa a página de dentro da moldura).

- A moldura **Emitir nota** das Finanças, o **Abrir em outra aba** e o botão do sistema que abre o Notas (**Emitir no Play Space Notas**) agora pedem a página do Notas com um endereço novo a cada vez (`/notas/?a=…`). Não existe cópia guardada para esse endereço, então o navegador sempre traz a versão que está no site.
- Para o computador que ainda mostra o Notas antigo: atualizar o sistema (versão no rodapé → Atualizar agora) já resolve. Se a página das Finanças ainda estiver aberta de antes, recarregue-a uma vez.
- O pacote do Notas (playspace-notas-183.zip) traz também um `.htaccess` na pasta **notas** que pede ao navegador para sempre conferir a página com o servidor (para quem abre o /notas direto pelo endereço).

## 6zzzzzzzzzzzzzz. Os dois alertas da meta ("Falta pouco para a meta!") e a meta batida no celular dos gestores (1.81 · Finanças 3.5)

Pedidos do dono: "coloque também a opção lá na configuração de eu colocar um alerta, tipo, falta tanto para minha meta. 2 alertas" e "também mandar uma notificação no telefone dos gestores, meta batida".

- **Onde:** Ajustes → **Meta do dia**, ao lado do valor da meta: **1º alerta: quando faltar** e **2º alerta: quando faltar** (por exemplo R$ 500 e R$ 200). Deixe vazio o que não quiser. **Salvar** guarda a meta e os alertas juntos.
- O alerta tem que ser **menor que a meta** (é quanto falta para chegar nela); se não for, o sistema não salva e diz por quê.
- **Quando o dia chega a um alerta**, todas as telas ligadas (a recepção, o gestor e as Finanças) mostram no alto **"Falta pouco para a meta!"** e quanto falta naquela hora (por exemplo "Faltam R$ 450 para a meta do dia"), com uns confetes, por uns 6 segundos. **Uma vez por dia em cada tela**, cada alerta. Espera o cupom fechar e não trava nada (um toque ou o Esc fecha).
- Se uma venda grande passa dos dois alertas de uma vez, aparece um aviso só; se já bateu a meta, aparece só a comemoração.
- O cartão mostra cada alerta com a hora em que chegou (**✓ 15:10**). **Ver o alerta** mostra como fica, só naquela tela. **Desligar a meta** tira a meta e os alertas.
- **No celular dos gestores:** quando a meta é batida, chega a notificação **"Meta do dia batida! 🎉"** ("Meta de R$ 3.000 batida às 15:42 · faturado hoje R$ 3.120. Parabéns, equipe!"); tocar nela abre o Painel. Chega em cada celular de gestor que ligou os **avisos no celular** (os mesmos dos pedidos), **uma vez por meta**, mesmo com o app fechado. Os alertas de "falta pouco" não vão para o celular, só para as telas.

## 6zzzzzzzzzzzzz. A meta do dia: "Meta do dia batida!" com fogos (1.80 · Finanças 3.4)

Pedido do dono: "na conta do gestor, numa configuração, ele coloca a meta do dia; quando bater a meta, todos os computadores que estiverem com o sistema ligado, inclusive o financeiro: meta do dia batida e vários foguinhos. Um campo para eu colocar o valor".

- **Onde:** Ajustes → **Meta do dia** (só o gestor vê). Escreva o valor (por exemplo 3.000,00) e toque em **Salvar** (ou Enter). O cartão mostra quanto já foi feito hoje e quanto falta, com uma barrinha. **Desligar a meta** tira; **Ver a comemoração** mostra como fica, só naquela tela.
- **Quando conta como batida:** quando o **faturado do dia** (o mesmo "Faturado hoje" do painel: o vendido desde a meia-noite, já com o desconto, as unidades juntas) chega ao valor da meta.
- **O que acontece:** cada tela ligada (a recepção, o computador e o celular do gestor, e as **Finanças** abertas) mostra **"Meta do dia batida!"**, com a meta e a hora, e os fogos por uns 7 segundos. **Uma vez por dia em cada tela.** Se o cupom (ou outra janela) estiver aberto, espera fechar.
- **Não trava nada:** dá para continuar trabalhando por cima; um toque em qualquer lugar (ou o Esc) fecha. Com "Animações: leves" (ou "reduzir movimento" no aparelho), aparece só o recado, sem os fogos.
- **No painel do gestor** (todas as unidades), o "Faturado hoje" diz quanto da meta já foi (por exemplo "72% da meta") ou "meta batida ✓".
- A equipe vê a comemoração, mas **não vê o valor** da meta nem o faturado, e não muda a meta (o servidor recusa).
- Mudar o valor da meta no meio do dia vale como meta nova: se o dia ainda não chegou nela, volta a contar.

## 6zzzzzzzzzzzz. O pedido de autorização em foco: o X vermelho e o ✓ verde (1.79)

Pedido do dono: "quando o gestor tocar na notificação do pedido e abrir o aplicativo, o pedido tem que ser a primeira coisa: aprovar ou negar, dois botões, um vermelho e um verde, só um certo e um X; ele não tem que procurar".

- Tocou no aviso do celular (com o app fechado ou aberto), o pedido **aparece sozinho, por cima de tudo**: quem pede, o que é (desconto, alterar valor, nota, cancelamento, parcelas), o cliente e o motivo.
- Dois botões grandes: o **X vermelho** (negar) e o **✓ verde** (autorizar). Respondeu, vem o próximo ("1 de 3"); sem mais nenhum, fecha.
- Também aparece ao **abrir o app** (ou voltar para ele) com pedido esperando, e, no celular, quando chega um pedido novo com o app aberto.
- **Ver depois** fecha: os pedidos de agora não voltam sozinhos (voltam no próximo aviso tocado ou quando chega outro pedido).
- Quem pede (a atendente) não vê esse cartão: continua vendo o pedido no alto da tela dela, vermelho esperando e verde quando é aprovado.

## 6zzzzzzzzzzz. O sistema de notas dentro das Finanças (1.78 · Finanças 3.3)

Pedido do dono: "no sistema de finanças, integre o sistema de notas: que ele vá para dentro das Finanças, para eu não ter que abrir outra aba; só clicar e ele já abre".

- No menu das Finanças, logo abaixo de "Notas fiscais", o item **Emitir nota** (no celular, **Emitir NF** na barra de baixo). Ele abre o **Play Space Notas aqui dentro**, numa moldura que ocupa a tela: emitir, consultar, conferir no governo, tudo sem abrir outra aba.
- É o mesmo sistema de **/notas** (as mesmas notas e o mesmo certificado). O login é o do Notas: entra uma vez e ele fica guardado no navegador.
- **Abrir em outra aba** continua, no alto, para quem preferir.
- Na aba **Notas fiscais** (o relatório notas x caixa), o botão **Emitir nota** leva direto para o sistema de notas aqui dentro.

## 6zzzzzzzzzz. Os foguetes do pacote grande e o lembrete de pedir a avaliação (1.77)

Pedido do dono: "quando lançar um pacote com mais de 10 horas, lançar uns foguetinhos" e "no final de tudo do cliente, uma mensagem na tela para lembrar de pedir a avaliação".

- **Foguetes:** a venda com pacote de **10 horas ou mais** (somando os pacotes da venda) solta foguetes que sobem e estouram em fogos coloridos, com o recado "Pacote de 20 horas vendido!" e o nome do responsável. Não segura nada: o cupom e o resto seguem por baixo, e tudo some sozinho em uns 4 segundos. Com "Animações: leves", aparece só o recado.
- **Lembrete da avaliação:** no fim de tudo do cliente (quando sai a última criança da família e não tem carrinho fora, ou quando volta o último carrinho), depois que a folha do cupom fecha, aparece o cartão **"Peça a avaliação!"**: "Antes de MARIANA ir embora, pergunte como foi o dia e peça para avaliar a Play Space no Google". Não trava a tela: some no **Pedi**, num toque fora ou em 25 segundos.
- Não aparece na saída da festa, na saída corrigida pela supervisão nem na cortesia.

## 6zzzzzzzzz. Só o gestor mexe no calendário (1.76)

Pedido do dono: "o operador não pode mexer no calendário, tipo marcar festa ou alguma coisa assim; ele só pode agendar; quem pode colocar compromissos é só o gestor".

- **O operador agenda a festa do cliente pela venda** (Vender festa → escolhe o dia no calendário → recebe), como antes. Também continua com o upgrade, o adicional e a entrada na festa.
- **Mudar a festa marcada** (o tema, o horário, o dia ou a unidade) agora é **só do gestor**. Para o operador, a folha da festa diz "fale com o gestor".
- **Compromissos da Agenda** (oficina, reunião, postagem, festa marcada à mão): criar, mudar, apagar e **marcar como feito** são só do gestor. O operador vê a agenda e o que está feito, mas não mexe.
- O servidor também recusa (não adianta tentar por fora da tela).

## 6zzzzzzzz. A barra de baixo do celular como a do Instagram (1.75)

Pedido do dono: "quando o sistema da Play Space for aberto no telefone, os ícones iguais aos do Instagram, lá de baixo".

- No celular, a barra de baixo virou uma **cápsula de vidro que flutua** um pouco acima da borda da tela, com os cantos bem redondos.
- **Só os ícones**, maiores e sem os nomes embaixo (o nome continua no botão para o leitor de tela).
- O item aberto fica numa **pílula clara**, com o ícone mais forte; ao tocar em outro item, a pílula desliza até ele.
- O número dos pedidos para aprovar aparece numa **bolinha vermelha** em cima do ícone.
- O botão **+** (cadastrar criança) fica logo acima da barra, sem encostar.
- No tema escuro a cápsula fica escura. Com "Animações: leves" ou "reduzir movimento", a pílula pula direto para o item, sem deslizar.
- No computador nada muda (o trilho de ícones do lado).

## 6zzzzzzz. As animações das Finanças também no sistema da Play Space (1.74)

Pedido do dono: "gostei muito das animações que você colocou no financeiro: coloque no sistema da Play Space".

- **Ao trocar de tela** (ou o filtro da lista do espaço), os blocos da tela entram um depois do outro, desfocados e ficando nítidos.
- **Os números correm até o valor**: os quadros do painel da gestora (no espaço, entradas, carrinhos, faturado, caixa, descontos) e os valores do Caixa do dia (Finalizados do dia). Ao abrir a tela, do zero; depois, só quando o número muda.
- **O gráfico "Entradas por hora"** cresce de baixo para cima (ao abrir o painel e quando chega entrada nova); as barrinhas das linhas enchem.
- **No menu de ícones** (no computador), o quadrado escuro do item aberto desliza até o item novo.
- **Ao passar o mouse**, o quadro do número sobe um pouco.
- A atualização automática (de cada minuto e a sincronia) **não mexe em nada**: a tela não pisca e os números não voltam do zero.
- Com **Ajustes → Animações: leves**, ou "reduzir movimento" no aparelho, nada disso acontece: os números já aparecem prontos e o menu fica como antes.
- No celular, a barra de baixo continua igual (só a tela e os números se mexem).

## 6zzzzzz. O menu da barra escura nas Finanças e o Casa Tom no Caixa do dia (1.73 · Finanças 3.2)

Pedido do dono: "esse menu" (o menu do vídeo de painel que o dono mandou) e "o Casa Tom Anália, o Casa Tom Morumbi: no Casa Tom só se vende produto; o centro de custo Casa Tom lá no sistema de atendimento também, só naquela aba de financeiro".

**O menu das Finanças (no computador):**
- Uma barra escura e arredondada do lado esquerdo, só com os ícones: o logo em cima, as iniciais de quem entrou embaixo. Os grupos (a loja e o financeiro) ficam separados por um traço.
- Ao abrir as Finanças, a barra cresce e os ícones aparecem um depois do outro.
- O item aberto fica num círculo claro, que desliza até o item novo quando a aba muda. A tela nova entra desfocada e fica nítida.
- Ao passar o mouse (ou com o Tab do teclado), o nome aparece numa etiqueta escura ao lado do ícone. O número dos cancelamentos fica numa bolinha no ícone.
- Em telas mais baixas (o notebook), os ícones ficam menores para o menu inteiro caber; se ainda assim não couber, a ponta da barra esmaece e dá para rolar.
- No celular continua a barra de baixo. Com "reduzir movimento" no aparelho, a barra já aparece pronta e nada desliza.

**O Casa Tom no sistema de atendimento (o Caixa do dia, em Finalizados do dia):**
- A linha dos produtos agora se chama **Casa Tom (produtos)**.
- No fim do Caixa do dia, o **Centro de custo**: o **Play** de cada unidade (horas, recreação, festas, carrinhos) e o **Casa Tom** de cada unidade (só os produtos): Play Anália, Casa Tom Anália, Play Morumbi, Casa Tom Morumbi. Escolhendo uma unidade, só as duas linhas dela. O devolvido hoje sai do centro de custo dele.
- O caixa impresso também traz o centro de custo.
- Nas Finanças o Casa Tom já estava: os produtos são o "Casa Tom (produtos)", a Visão geral tem o quadro "Play e Casa Tom" de cada unidade e os lançamentos têm as filiais Casa Tom Anália e Casa Tom Morumbi.

**Um acerto:** no painel da gestora, o gráfico "Entradas por hora" se refaz sempre que a largura do quadro muda (o histórico abre ou fecha). Com "reduzir movimento" no aparelho, ele ficava na largura antiga.

## 6zzzzz. As Finanças com animações (1.72 · Finanças 3.1)

Pedido do dono: "quero o financeiro com essas animações" (um vídeo de painel moderno: os cartões que aparecem um a um, os números que correm, os gráficos que crescem).

- **Ao abrir uma aba**, os cartões (os números de cima e os quadros) sobem e aparecem um depois do outro.
- **Os números dos cartões correm** do zero até o valor (e, quando o período ou a unidade muda, do valor de antes até o novo). Quando a tela só se atualiza sozinha com o mesmo número, nada se mexe.
- **Os gráficos:** as barras crescem de baixo para cima; no Relatório financeiro, a linha do resultado se desenha e as bolinhas aparecem. As barrinhas das listas (por forma de pagamento, por produto) enchem da esquerda para a direita.
- **Ao passar o mouse** em cima de um cartão de número, ele sobe um pouco.
- As cores, os textos e os números continuam os mesmos (a cara da Play Space). Quem ligou "reduzir movimento" no computador ou no celular vê tudo parado, já pronto.

## 6zzzz. A atendente troca (e divide) a forma de pagamento a qualquer hora (1.71)

Pedido do dono: "as meninas podem trocar a forma de pagamento; não precisa deixar os 2 minutos. Depois, se elas precisarem, elas conseguem editar a forma de pagamento para bater o caixa"; "e às vezes até escolher duas formas de pagamento: desmembrar, quando o cliente desmembrou".

- **Na folha do cupom:** "Trocar a forma de pagamento" fica sempre, sem o relógio de 2 minutos.
- **Depois, para bater o caixa:** no histórico do cliente, cada compra paga tem **"Trocar pagamento"**, para a atendente também, a qualquer hora. Toque na forma certa: o pagamento inteiro muda de forma, e o caixa do dia, as Finanças e o cupom fiscal acompanham.
- **Dividir em mais de uma forma** (quando o cliente pagou parte numa forma e parte em outra): embaixo de cada forma, "Dividir em mais de uma forma" abre um campo para cada forma (Pix, Dinheiro, Crédito, Débito). Escreva quanto foi em cada uma; a tela mostra "Falta R$ …" até a soma bater com o valor pago. "Salvar a divisão" reparte o pagamento (o caixa, as Finanças e o cupom fiscal acompanham). O valor mostrado é o do pagamento inteiro: as compras pagas juntas (o pacote e o produto, por exemplo) entram juntas.
- **Continua igual:** cada troca fica registrada (quem trocou, de qual forma para qual, o valor) e o gestor recebe o aviso da troca feita pela atendente. A reimpressão do cupom pela atendente continua só nos 2 minutos (depois, só o gestor).

## 6zzz. No comprovante do cliente, só o pacote (1.70)

Pedido do dono: "por mais que eu discrimine, montando um pacote, o valor do produto e o valor do pacote, no comprovante do cliente só pode sair o valor do pacote. As meninas dão o brinde: é como um presente, incluso dentro do pacote. O cliente só pode saber que tem o pacote".

A 1.66 já fazia isso no cupom da venda paga na hora. Agora vale em todo papel que o cliente recebe:

- **O cupom da saída:** o pacote (e o produto) vendido durante a visita e pago só na saída sai numa linha só: o pacote, com o valor do produto somado. O nome e o valor do produto não aparecem. O total é o mesmo.
- **O nome do pacote no papel:** sem o produto que vem com ele ("PACOTE 05 HORAS 400 · 5h", e não "… com BONECA"). O pacote sem nome sai como "Pacote de 5h".
- **A reimpressão:** os cupons guardados a partir desta versão já saem assim.
- **O cupom fiscal** (quando o módulo está ligado em Ajustes): a lista do que emitir na máquina traz o pacote com o valor do produto dentro, numa linha só.
- **O Relatório do cliente** (na tela, no papel e na planilha): o produto vendido no mesmo atendimento do pacote entra na linha do pacote.
- **O que continua discriminado:** o que é só da loja. O Histórico do cliente na tela, as Finanças (o relatório por cliente, o produto a produto, as comissões) e o estoque continuam separando o pacote do produto. O produto vendido sozinho (sem pacote) continua saindo como produto.

## 6zzy. O pedido ao gestor no alto, a tela sem barra de rolagem, o relatório por cliente, a diária 90/120 e o comprovante das despesas (1.69 · Finanças 3.0)

Pedido do dono: "quando um pedido ficar aguardando a aprovação do gestor, elas podem fechar, porém ele fica destacado lá em cima: o nome do cliente, vermelhinho, aguardando a aprovação do gestor; quando o gestor aprovar, fica verdinho, e só sai de lá quando elas clicarem e finalizarem"; "o sistema se adapta à tela, sem aquela barra de rolagem (com uma segunda tela aberta, eram duas barras)"; "no financeiro, um relatório com o nome do cliente, o pacote e o produto, tudo discriminado"; "o freelancer é 120 no fim de semana e 90 durante a semana, com a opção de trocar na tela de finanças"; "anexar o comprovante no controle de despesas: o financeiro adiciona, e o valor do comprovante tem que bater com o que está escrito".

- **O pedido ao gestor no alto (tela da atendente):** cada pedido que ela mandou (desconto, alterar valor ou pacote, parcelas, cancelamento, NF…) fica numa faixa no alto da tela, com o nome do cliente:
  - **vermelho, "Aguardando a aprovação do gestor"** (com uma bolinha piscando), mesmo com o atendimento fechado;
  - **verde, "Aprovado por … · toque para finalizar"** quando o gestor libera. Tocar abre o atendimento (ou a saída, ou as parcelas) para terminar. Fechar sem terminar: o pedido continua no alto. **Sai quando a venda é finalizada** (o desconto, o valor ou as parcelas usados);
  - **"Não aprovado por … · toque para ver"** quando o gestor nega; o cancelamento feito e a nota autorizada também aparecem. Esses saem com um toque;
  - fica só na tela de quem pediu, na unidade dela, e some sozinho depois de 12 horas.
- **A tela sem a barra de rolagem:** a página não mostra mais a barra do lado (a roda do mouse e o dedo continuam rolando). As caixinhas (atendimento, cadastro, aprovações) rolam por dentro, com uma barra fina, sem arrastar a tela de trás: com a caixinha aberta, fica só uma rolagem. Vale também nas Finanças.
- **Relatório por cliente (Finanças → Vendas e pacotes, no alto):** cada venda de pacote ou de produto no período e na unidade escolhidos, as mais recentes primeiro: quando, o cliente (responsável, criança e telefone), cada pacote (com as horas), cada produto (com a quantidade), o total (e o desconto), como foi pago (e o que falta receber) e quem vendeu. Em cima, os totais (vendido, pacotes, produtos, descontos). Tem a busca (cliente, pacote ou produto), o **PDF** e o **Excel** (uma linha por item, para filtrar).
- **A diária do freelancer (Finanças → Freelancers):** já vem **R$ 90 de segunda a sexta e R$ 120 no sábado e no domingo**. A barra "Diária do freelancer: semana … · fim de semana …" tem o **Mudar** (fica guardado quem mudou). No **Lançar dias** vêm os dois valores (semana e fim de semana), que dá para trocar só naquele lançamento; o freelancer com **diária fixa** no cadastro usa a dele.
- **O comprovante da despesa (Finanças → Lançamentos → Despesas, e Contas a pagar):** na despesa, **📎 Anexar o comprovante** (foto ou PDF, até 8 MB; na despesa nova vai junto quando lançar).
  - Escreva o **valor que está no comprovante** e toque **Conferir**: ✓ **confere** (o mesmo valor da despesa) ou ✗ **não bate** (mostra a diferença). O PDF que tem o texto (o comprovante do Pix do banco) é lido sozinho e confere na hora.
  - Na lista, cada despesa mostra: sem comprovante, falta conferir, ✓ confere ou ✗ não bate. Se o valor da despesa mudar, a conferência é refeita.
  - **Baixar** e **Trocar** (o comprovante antigo fica guardado). Os arquivos ficam na pasta de dados, fechada para a internet.
- **A janela de impressão que pisca:** o Chrome (e o aplicativo instalado) não tem um jeito de esconder de vez essa janelinha. Só um programa de impressão instalado no computador do balcão (que recebe o cupom e manda direto para a impressora) tira ela. Fica para uma próxima versão, se o dono quiser.

## 6zzx. A comissão de quem abre o pedido, o Fluxo de caixa dos freelancers e o freelancer à parte (1.68 · Finanças 2.9)

Pedido do dono: "a comissão sempre é de quem dá início no pedido do cliente, não de quem dá saída: ela oferta o pacote no início; às vezes o cliente fecha no final, mas a comissão é de quem abre o pedido"; "na aba Freelancers, um fluxo de caixa: o valor que ela passa para o gerente; conforme os freelancers são colocados como pagos, o valor vai sendo debitado, e fica o que ela precisa receber de Pix de volta"; "o freelancer fica à parte: não entra no resto".

- **Quem abre o pedido:** dar a entrada é abrir o pedido do cliente.
  - O **"Dar entrada"** (no atendimento, com o saldo ou na recreação avulsa) e a entrada rápida da lista perguntam antes **"Quem está atendendo?"**, com os nomes grandes: um toque no nome e a criança entra.
  - A venda com **"Dar entrada agora"** marcado também pede quem está atendendo (o mesmo quadro da 1.67).
- **A venda durante a visita e na saída:** com a criança no espaço, o pacote ou o produto vendido (também o comprado na saída) é de **quem abriu o pedido**. O quadro mostra **"Comissão de …"** (quem abriu e a hora da entrada) e não pede o nome de novo. O servidor confere: mesmo que a tela mande outro nome, vale quem abriu.
  - Sem quem abriu (a entrada pelo iDFace, a da festa ou as de antes da 1.68), a venda pede o nome como na 1.67.
  - A visita guarda quem abriu o pedido (também depois da saída).
- **Fluxo de caixa (Finanças → Freelancers → Fluxo de caixa):**
  - **+ Fluxo de caixa:** o valor entregue ao gerente, para quem, os dias (do dia / até o dia; vem com o próximo fim de semana) e a unidade (ou todas).
  - Na aba **Freelancers**, cada diária ou VT marcado como **pago** nesses dias (na unidade do fluxo) **sai do valor entregue**. O cartão do fluxo mostra o entregue, o pago aos freelancers (com a lista de quem foi pago), a barra do quanto já foi usado e **o que falta voltar por Pix**. Se o gerente pagou mais do que recebeu, mostra quanto ele pagou a mais.
  - **Recebi o Pix:** quanto voltou e quando (vem com o valor que falta). O fluxo fica acertado; dá para desfazer.
  - **Editar** e **Tirar** (o tirado fica guardado, com quem tirou). Dois fluxos da mesma unidade (ou um de todas) não podem ter dias em comum: cada pagamento sai de um só.
  - Na aba Freelancers aparece o aviso do fluxo aberto ("falta voltar …"); o PDF do mês dos freelancers traz o fluxo de caixa.
- **O freelancer à parte:** os freelancers e o fluxo de caixa ficam só na aba Freelancers. Não entram no faturamento e no resumo do dia, no Painel financeiro, nos Lançamentos, no Relatório financeiro nem no Controle de caixa (o teste confere que esses números não mudam).

## 6zzw. Quem está vendendo e as Comissões (1.67 · Finanças 2.8)

Pedido do dono: "toda vez que forem vender um pacote, selecionar o nome de quem está vendendo (Aline, Edjane, Karol e Maria Vitória), de forma destacada; a cada pacote, limpa, para marcar de novo; nas comissões vai constar o nome; as regras pré-configuradas e uma opção para o financeiro editar, caso mude e passe a ser 5%, 1%".

- **Quem está vendendo? (no atendimento do Play Space):** quando a venda tem um **pacote** ou um **produto**, aparece em destaque, logo acima do total, o quadro **"Quem está vendendo?"** com os nomes grandes. Enquanto ninguém toca no nome, o botão fica travado ("Escolha quem está vendendo"). Tocou no nome, ele fica marcado e a venda segue; no pagamento aparece "Vendido por …". **Cada venda começa sem ninguém marcado:** a próxima venda pede o nome de novo. A festa não pede (ela tem a tela dela).
- **Comissões (Finanças → Comissões, logo abaixo dos Freelancers):** o **trimestre** (ou o mês), com o bônus de cada vendedora (cartões com o total e o bônus de cada mês), a lista das vendas (dia, vendedora, o que foi, cliente, valor de tabela, pago, a regra que valeu e o bônus) e o **PDF das comissões**. Tocar no cartão de uma vendedora mostra só as vendas dela. A venda cancelada não aparece. A venda de pacote ou de produto sem o nome (as de antes da 1.67) aparece à parte ("Sem vendedora") e não entra no bônus.
- **As regras (pré-configuradas, a partir de 15/05/2026):** produto de R$ 190,00 → 7%; produto de R$ 350,00 → R$ 30,00 fixo; pacote de 5 h de R$ 400,00 → 2,5%; pacote de 10 h de R$ 800,00 → 2,5%; pacote sem produto de R$ 450,00 a R$ 580,00 → 3%.
  - Cada linha da venda (o pacote, o produto) passa pelas regras na ordem: **a primeira que serve é a que conta** (a seta ↑ muda a ordem).
  - O valor que a regra olha é o **de tabela** (antes do desconto). O bônus em **%** é sobre o que foi **pago** (já com o desconto); o bônus **fixo** é por unidade do produto.
  - **Pacote sem produto:** o pacote vendido sem nenhum produto no mesmo atendimento.
  - **Editar:** mude o número (por exemplo, 7 para 5), o valor, as horas, o % ou R$ fixo e toque em **Salvar as regras**; vale na hora para a conta. **+ Nova regra** cria outra.
  - **Regra não se apaga:** para parar de valer, escreva a data **"até"** (ou toque em **Encerrar**, que põe a data de hoje). Para mudar o % a partir de um dia, encerre a regra antiga no dia anterior e crie a nova "Vale de" a partir do dia.
  - Cada mudança fica guardada em **Mudanças** (quem mudou e quando).
- **Os nomes das vendedoras:** em Comissões → **Vendedoras** (Pôr na lista, × para tirar, **Salvar os nomes**). Os nomes aparecem no atendimento do Play Space na hora. Tirar um nome não mexe nas vendas que ela já fez.

## 6zzv. Cancelados com os clientes, nome e motivo sempre, o pacote na saída, o Casa Tom e o cupom só com o pacote (1.66 · Finanças 2.7)

Pedido do dono: "no Cancelados, clicar no 1 e ver quem foi o cliente cancelado; obrigatoriamente o nome da atendente e o motivo do cancelamento; na saída, quando seleciono o pacote, a forma de pagamento é o pacote; o Casa Tom (Anália e Morumbi), que só vende produto; e de forma alguma no comprovante pode sair valor de produto: para o cliente é só pacote de horas".

- **Cancelados (Finanças → Cancelamentos):** em "Por atendente", **toque no número** (ou na linha) da atendente: abre a lista dos cancelamentos dela, com o **cliente** (criança e responsável), quando, o que foi cancelado, o **motivo** e o valor.
- **Nome da atendente e motivo, sempre:** todo cancelamento pede o nome da atendente, **também no login do gestor** (às vezes é a atendente usando o login dele). No "Não vai fazer agora", o **motivo** passou a ser obrigatório (antes era "se quiser"). Cancelar a entrada, a venda e a saída do carrinho já pediam o motivo.
- **A saída com o pacote:** com "Descontar do pacote", as horas saem do pacote: a forma de pagamento delas é o pacote. Quando a conta tem mais alguma coisa (o carrinho, um produto ou horas além do pacote), a tela diz **"Forma de pagamento do que o pacote não cobre · R$ X"** e explica que Pix, crédito ou débito são só para esse resto. Sem nada além das horas, não pede forma nenhuma.
- **Casa Tom:** nas Finanças, os produtos que cada unidade vende são do **Casa Tom** daquela unidade: "Casa Tom (produtos)" e, na Visão geral, o cartão **Play e Casa Tom** (em cada unidade, o Play e o Casa Tom). Nos lançamentos, as filiais **Casa Tom Anália** e **Casa Tom Morumbi**. Isso é só no financeiro: o sistema da Play Space continua igual.
- **O cupom impresso:** para o cliente é só **pacote de horas**. O produto vendido junto com um pacote não sai em linha própria: o valor dele entra na linha do pacote (o total do cupom é o mesmo), e o nome do produto que vem com o pacote não aparece. Produto vendido sozinho (sem pacote) continua saindo como produto.

## 6zzu. Freelancers, relatórios bonitos em PDF, crédito em mais vezes com o gestor e Upgrade/Adicional na festa (1.65 · Finanças 2.6)

Pedido do dono: "um campo para os freelancers: a data que trabalhou, se eu paguei ou não, o valor que paguei, o valor que combinei, se paguei o vale-transporte, o nome e o pagamento total (às vezes trabalha o mês inteiro). As meninas conseguirem fazer o upgrade e o adicional de festa. O crédito é até 4 vezes; pode ir até 12, só com a liberação do gestor. E um PDF bonito da festa, do freelancer e do caixa, com dashboards, informações do crédito, na estética da Play Space, com o urso e a logo".

**Freelancers** (Finanças, logo abaixo de Festas):
- **+ Freelancer**: o nome, o telefone, a chave Pix, a função, a **diária combinada** e o **vale-transporte por dia** (vêm preenchidos ao lançar os dias).
- **Lançar dias**: escolha o freelancer e **toque nos dias** no calendário do mês (atalhos: seg a sex, sáb e dom, todos). A unidade, o combinado e o VT valem para os dias tocados. O dia que já está lançado não entra de novo.
- Cada freelancer tem um cartão com os dias do mês. Em cada dia: o combinado e o VT (dá para mudar), **Diária paga** (toque quando pagar) e o **valor pago** (se pagou diferente do combinado, escreva: aparece "R$ 30,00 a menos"), **VT pago**, o **total pago** e o que **falta**.
- **Pagar o que falta**: paga o mês de uma vez (as diárias pelo combinado e, se quiser, o VT), com a data do pagamento.
- Tirar um dia lançado por engano (o ×) não apaga: fica guardado quem tirou. Toda mudança fica no histórico.
- Em cima: o total do mês, o pago, o que falta pagar e quantos freelancers trabalharam. **Exportar Excel** (resumo e os dias) e **PDF do mês**.

**Os relatórios bonitos em PDF** (com a logo, o ursinho da Play Space e gráficos):
- **Controle de caixa**: **PDF do dia** e **PDF do mês**. O total do caixa, cada forma (crédito, débito, Pix, dinheiro) num gráfico de rosca, o dia a dia em barras, cada unidade, **o crédito em detalhe** (a taxa da Rede, a taxa média, o líquido, as vendas, o ticket médio e em quantas vezes as festas foram parceladas), o sistema x a Rede forma a forma, a conferência de cada dia (bateu, não bateu, falta a Rede), os consertos e o que cai na conta nos próximos dias.
- **Festas**: **PDF do mês**. O valor bruto, os custos e o lucro real, para onde vai o dinheiro (buffet, salgados, papelaria, adiantamento, lucro), o lucro de cada festa, como foram pagas (e em quantas vezes no crédito) e cada festa numa linha.
- **Freelancers**: **PDF do mês**. O total, o pago e o que falta, quanto custou cada dia, cada freelancer e os dias dele.
- O ursinho dá o recado mais importante no balãozinho (ex.: "Falta pagar R$ 460,00 a 2 freelancers").

**O crédito da festa em mais vezes** (Play Space, no pagamento da festa):
- Até **4x** (a regra da festa, a mesma do "em até 4x sem juros"): como sempre, qualquer um escolhe.
- **Mais vezes no crédito (5x a 12x)**: o **gestor** escolhe direto. A atendente escolhe as vezes e pede a **liberação do gestor**: **Pedir ao gestor** (aparece nas Aprovações e no celular dele) ou **Liberar com a senha** do gestor, ali mesmo. Enquanto espera, o botão de registrar fica travado. Negado, ou mudou de ideia: escolher até 4x desiste do pedido.
- O servidor confere: sem a liberação, a venda em 5x a 12x não passa. A liberação vale 30 minutos, para aquele cliente, para até as vezes liberadas, e uma vez só.

**Upgrade e Adicional na festa**: na folha da festa marcada (Festas → a festa), toda a equipe tem os botões **Upgrade de festa** (antes do dia da festa) e **Adicional de festa**. Eles abrem a venda já com a festa escolhida, sem chamar o gestor.

## 6zzt. Finanças 2.5: a página das Finanças se renova sozinha (1.64)

Pedido do dono: "ele não subiu a alteração para o gestão". O servidor da loja já estava na 1.63 (o Atualizar trocou os arquivos), mas a página das Finanças que estava aberta no aparelho (a aba aberta, o app do celular ou o cache do navegador) continuava a antiga.

- Agora a página das Finanças sabe de que versão ela é e pergunta ao servidor qual está instalada. Se o servidor já tem uma mais nova:
  - **ao abrir** as Finanças, ela recarrega sozinha antes de desenhar a tela;
  - **ao voltar para a aba ou para o app** (com a página já aberta), ela recarrega sozinha;
  - se houver um **lançamento no meio** (uma caixinha aberta ou alguém digitando), ela não recarrega: aparece embaixo "Chegou a versão … do sistema" com o botão **Recarregar**, para ninguém perder o que digitou.
- Recarrega uma vez só para cada versão (nunca fica num laço).
- Para quem ainda está com a página antiga aberta (de antes da 1.64): recarregar as Finanças uma vez à mão (no computador, F5; no celular, puxar a tela para baixo ou fechar e abrir de novo). Daí em diante é sozinho.
- Técnico: o `estado` das Finanças devolve `ps` (a versão do `versao.json`); a página tem `PS_PAGINA`, que o `tools/build_playspace.py` carimba com a versão do Play Space a cada build.

## 6zzs. Finanças 2.4: o PDF da Rede, Consertar caixa e o Caixa líquido (1.63)

Pedido do dono: "ela não vai mais lançar a Rede à mão: anexa o PDF e o sistema destrincha e preenche (cada bandeira tem uma taxa, e só a Rede sabe que cartão foi passado); uma opção Consertar caixa, com valor positivo ou negativo e uma observação do porquê; e o Pix cai no mesmo dia, o débito em um dia útil e o crédito em dois: isso fica no caixa líquido".

- **Anexar PDF da Rede** (em cada unidade, no Controle de caixa): escolha o PDF do **Relatório de vendas** da Rede, **consolidado por modalidade** (o que a Rede gera em "Vendas"). O sistema lê e preenche, em cada dia do relatório, o crédito, o débito e o Pix: o **bruto** (vai para "Na Rede"), a **taxa** da Rede, o **líquido** e a **quantidade de vendas**. Aparece um resumo do que foi lido.
  - O PDF da Rede não tem texto (as letras são desenhadas). O sistema reconhece cada letra pela forma; quando não tem certeza de uma, não chuta: não preenche aquele valor e avisa.
  - As contas do relatório são conferidas: bruto − taxa = líquido; a soma das modalidades = o total do dia; a quantidade de vendas. Se algo não fechar, aparece o aviso.
  - **Estabelecimentos:** se o PDF juntar mais de um estabelecimento da Rede, o sistema avisa. Para a conferência bater, tire na Rede o relatório **só do estabelecimento daquela loja**. Ele também avisa quando os estabelecimentos são outros que os do PDF anterior da mesma unidade.
  - O PDF fica guardado (na pasta de dados, fora do site): **baixar o PDF** no cartão da unidade.
  - Ainda dá para digitar o valor da Rede à mão (por exemplo, se o PDF não estiver à mão); a taxa que veio do PDF continua.
- **Consertar caixa** (em cada unidade): a forma (crédito, débito ou Pix), **+ Somar ao sistema** ou **− Tirar do sistema**, o valor e a **observação obrigatória** (o porquê). A forma que não bate já vem escolhida, e **Usar a diferença** põe o valor que falta. O conserto entra na conta do sistema ("sistema R$ 70,00 − conserto R$ 10,00") e a conferência muda na hora. Embaixo do cartão, **Consertos do dia** (com o porquê, quem e quando); **tirar** desfaz (fica guardado quem tirou).
- **Caixa líquido · o que cai na conta** (a outra aba do Controle de caixa): o que cai na conta no dia escolhido, por unidade:
  - **Pix no mesmo dia**, **débito em 1 dia útil**, **crédito em 2 dias úteis** (sábado, domingo e feriados nacionais não contam: Ano-Novo, Carnaval, Sexta-feira Santa, Tiradentes, Dia do Trabalho, Corpus Christi, Independência, Nossa Senhora Aparecida, Finados, Proclamação da República, Consciência Negra e Natal).
  - Cada linha: a forma, o dia da venda, o bruto, a **taxa da Rede** e o **líquido**. Com o PDF da Rede do dia da venda, o líquido é o da Rede; sem ele, aparece **sem a taxa** (o valor do sistema).
  - **Os próximos dias:** o que ainda vai cair na conta, dia a dia, pelas vendas que já aconteceram (o fim de semana e o feriado aparecem como "não é útil"). Tocar num dia mostra de onde vem.

## 6zzr. Finanças 2.3: as Festas, com o lucro real (1.62)

Pedido do dono: "embaixo do caixa, um Festas: do sistema, a data, quem fez a venda, o valor bruto da festa, a data da festa, a forma de pagamento e em quantas vezes foi. E os campos que eu lanço à mão (as meninas não lançam): buffet (varia com a quantidade de crianças), salgados e papelaria; o adiantamento da festa (R$ 500, para balão e outras despesas) e o valor retornado (nem sempre gastam tudo). No fim, o lucro real. Tudo para digitar, tipo um Excel, mas com a cara do sistema".

- **Festas** fica logo abaixo do **Controle de caixa** no menu (no celular, **Festas** na barra de baixo).
- **O mês** (‹ ›, pela data da festa), a **unidade** e a **busca** pelo aniversariante, pelo tema ou por quem vendeu.
- **Cada festa é uma linha**, como numa planilha:
  - **Do sistema:** a festa (aniversariante, dia, horário, unidade e tema), a **venda** (o dia e quem vendeu), as **crianças** (as do pacote, os adicionais e quantas vieram), o **valor bruto** (a festa, o upgrade e o adicional) e o **pagamento** (cada forma com o valor; no crédito, em quantas vezes).
  - **Você lança:** **Buffet** (embaixo aparece quanto deu por criança), **Salgados**, **Papelaria**, **Adiantamento** e **Retornado**. Enter desce para a festa de baixo, na mesma coluna; Tab vai para o lado. O valor fica salvo ao sair do campo, e cada mudança fica no histórico.
  - **Resultado:** o **gasto do adiantamento** (adiantamento − retornado) e o **lucro real** = valor bruto − buffet − salgados − papelaria − gasto do adiantamento. Lucro negativo fica em vermelho.
- **O retornado nunca passa do adiantamento** (o sistema avisa e não deixa).
- **Em cima:** quantas festas tem no mês (e quantas ainda sem os custos, com o selo **falta lançar**), o valor bruto, os custos e o **lucro real** do mês, com a margem. **Embaixo:** a linha do total.
- **Exportar Excel:** a planilha das festas do mês, com tudo e o total.
- **Em quantas vezes no crédito:** na venda da festa (no Play Space da loja), quando o crédito está no pagamento, aparece **"Em quantas vezes no crédito?"** (1x a 4x). A atendente toca na que o cliente escolheu na maquininha, e isso aparece nas Festas. Nas vendas de antes (ou se ninguém tocou), você escolhe as vezes na própria linha.
- A festa cancelada (sem venda) não aparece.

## 6zzq. Finanças 2.2: o Controle de caixa (1.61)

Pedido do dono: "uma tela de controle de caixa diário: escolher a data e ver o que passou no caixa naquele dia, no crédito, no débito e no Pix; um campo em branco onde eu lanço os valores da Rede; ele compara e dá um positivo ou um negativo (não quero ninguém olhando se bateu 1.225); um filtro para o caixa de hoje, o de ontem ou a data que eu escolher; e que seja o ícone inicial do financeiro".

- **É a primeira tela das Finanças:** o primeiro ícone do menu (no celular, **Caixa**). As Finanças abrem nela, no dia de hoje.
- **O dia:** **Hoje**, **Ontem**, **Escolher a data** (o calendário) e as setas ‹ › para ir um dia para trás ou para a frente.
- **Cada unidade num cartão** (cada loja tem a sua maquininha da Rede). Em cada um: **Crédito**, **Débito** e **Pix**, com o que passou no caixa do sistema e, ao lado, o campo **Na Rede** para digitar o valor da Rede (aceita 1225, 1.225,50, 1225,5…).
- **A conferência sai na hora, enquanto se digita:** ✓ verde **Bateu** (ao centavo) ou ✗ vermelho **Não bateu**, com a diferença ("Rede R$ 10,00 a menos" ou "a mais"). Sem valor da Rede: **Falta lançar**; sem nada no dia: **Sem movimento**. A linha **Total** soma tudo, e o selo do cartão diz o resultado da unidade (**Tudo bateu**, **1 forma não bateu**, **Falta lançar a Rede**).
- **O valor fica salvo** ao sair do campo (ou com Enter, que já vai para o próximo campo), com quem lançou e quando. Apagar o campo tira o valor. Toda mudança fica guardada no histórico.
- **O dinheiro** aparece embaixo, à parte (não passa pela Rede).
- **Os números do sistema** são os mesmos do "Recebido" da Visão geral: os pagamentos do dia, menos o que foi devolvido no dia; a venda cancelada em outro dia continua no dia em que foi paga (como na Rede).
- **Últimos 14 dias:** um quadradinho por dia com ✓ (bateu), ✗ (não bateu), ! (falta lançar) ou – (sem movimento). Tocar num dia abre o caixa dele.

## 6zzp. Finanças 2.1: mais clareza, fornecedores, contas a pagar e planilhas (1.60)

Pedido do dono: "dar mais usabilidade ao financeiro, as informações mais claras, como num sistema de gestão financeira; registrar fornecedor; e uma aba de planilhas: importo uma planilha modelo, o sistema lê, copia o que foi escrito e as próximas linhas são lançadas direto no sistema, no mesmo visual".

- **O menu em dois grupos:** **Loja · caixa do sistema** (Visão geral, Vendas e pacotes, Cancelamentos, Descontos, Notas fiscais: os números que saem do caixa do Play Space) e **Financeiro** (Painel financeiro, Lançamentos, Contas a pagar, Fornecedores, Planilhas, Relatório financeiro: o que o financeiro lança e controla). O número vermelho em **Contas a pagar** é o que venceu ou vence hoje.
- **Painel financeiro:** o mês num olhar: receitas, despesas (o investimento à parte), resultado e margem, comparados com o mês passado; o total a pagar (e quanto já venceu); as contas que vencem nos próximos 7 dias, com o botão **Pagar**; entradas e saídas dia a dia (gráfico); os maiores fornecedores; para onde foi o dinheiro (centros de custo); os últimos lançamentos e quem fez.
- **A despesa** agora tem: o **fornecedor** (um nome novo é cadastrado sozinho; o centro de custo e o grupo de costume do fornecedor vêm preenchidos), a situação **Paga** ou **A pagar**, o **vencimento**, a **forma de pagamento** (Pix, boleto, transferência, dinheiro, cartão, débito automático) e **repetir todo mês** (aluguel, salários: as próximas contas a pagar no mesmo dia de cada mês). A despesa a pagar entra no mês do vencimento.
- **Recorrente** (o botão na despesa nova): repete todo mês, quantas vezes você escolher (12, por exemplo). A primeira pode já estar paga; as próximas entram como contas a pagar, no mesmo dia de cada mês. Cada uma mostra o selo **"3/12 · Recorrente mensal"**. Ao corrigir uma delas, **"Mudar também as próximas"** muda as que ainda estão a pagar (o aluguel subiu); ao tirar, tira também as próximas.
- **Contas a pagar:** os cartões **Em atraso** (vermelho), **A pagar hoje**, **Próximos 7 dias** e **Total devido**; a lista separada em Em atraso, Vencem hoje, Próximos 7 dias e Depois, com a coluna **Devido** e o total devido no fim. **Pagar** (ou a chave **Pago?** da linha) marca o dia, a forma e o valor pago (mude se teve juros ou desconto). Em **Pagas**, as contas pagas no mês; tocar duas vezes na chave **Pago?** desfaz um pagamento marcado sem querer (a conta volta a ser devida).
- **Nas despesas (Lançamentos):** os cartões do mês (despesas, pagas, **ainda devido**, **em atraso**) e, em cada linha, o **Devido** (o que falta pagar, ou R$ 0,00) e a chave **Pago?**.
- **Fornecedores:** o cadastro (nome, CNPJ ou CPF, contato, telefone, e-mail, chave Pix, banco, centro de custo e grupo de costume, observações), a lista (quantas despesas, o pago no ano, o a pagar, a última) e a ficha de cada um (as despesas e o total por ano). Tirar um fornecedor tira da lista; as despesas continuam com o nome.
- **Lançamentos:** as linhas separadas por dia (com a soma do dia); nas despesas, os cartões do mês (total, pagas, a pagar) e a situação de cada uma (paga, a pagar, vencida). A busca acha também pelo nome do fornecedor. O Excel das despesas tem o fornecedor, a situação, o vencimento, o pagamento e a forma.
- **Planilhas:** **Importar planilha** (.xlsx ou .csv). O sistema acha, em cada aba, a linha do cabeçalho (mesmo com título em cima), as colunas e o tipo de cada uma (data, valor em R$, número, lista de opções, texto), copia as linhas (as vazias e as de total ficam fora) e mostra antes como vai ficar (dá para mudar o nome e o tipo). Cada aba vira uma planilha dentro do sistema ("Planilha 1, 2, 3…"), no visual do sistema:
  - busca, filtro por mês (quando tem data), ordenar pela coluna, o total das colunas de valor;
  - **+ Nova linha** abre os campos certos de cada coluna (data, valor, número, lista, texto); tocar numa linha corrige ou tira (fica quem lançou e quem corrigiu);
  - **Colunas:** renomear, mudar o tipo, esconder, mudar a ordem, pôr uma coluna nova; **Importar mais linhas** (as colunas casadas pelo nome); **Exportar Excel**.
  - O arquivo importado não fica guardado: o que fica é a planilha dentro do sistema. A planilha do financeiro (Faturamento, Despesas, Recebimentos, Rede) continua entrando por **Lançamentos → Importar a planilha**, para virar lançamentos com o relatório.

## 6zzo. Finanças 2.0: o financeiro dentro do sistema, no lugar da planilha (1.59)

No **/financas**, duas abas novas:

- **Lançamentos:** o que o financeiro preenchia na planilha, com os mesmos campos:
  - **Receitas** (o faturamento): data, sub item, filial, segmento, quantidade de vendas, de crianças, valor bruto e observações;
  - **Despesas:** data, descrição, filial, segmento, grupo (Custo Fixo, Custo variável, Investimento), centro de custo, valor e obs;
  - **Recebimentos** (o líquido): data, forma (Pix, dinheiro, crédito, débito, link), local (filial e segmento), valor, obs e a data após o fechamento;
  - **Rede** (a maquininha): o que a Rede pagou no dia, por unidade. Fica à parte (não soma no líquido).
  - Escolha o mês nas setas e filtre por filial, segmento ou busca. Toque numa linha para corrigir ou tirar (fica quem lançou, quem corrigiu e quem tirou). "Lançar e mais um" guarda e já abre o próximo, com a mesma data, filial e segmento.
  - As listas (filiais, segmentos, sub itens, centros de custo) vêm da planilha; um nome novo entra na lista sozinho.
- **Importar a planilha:** escolha o arquivo .xlsx do financeiro. O sistema lê as abas Faturamento (Receita), Despesas, Recebimentos LÍQUIDOS e Rede, mostra quantas linhas e a soma de cada uma e importa. Importar de novo troca o que veio da planilha antes; o que foi lançado no sistema continua.
- **Relatório financeiro:** o ano mês a mês (ou um mês): receita bruta, receita líquida, despesas, investimento, resultado e margem; o gráfico de receitas x despesas; cada local (Play e Casa Tom de cada unidade, eventos, bolos); o que mais vendeu; os centros de custo; custo fixo e variável de cada local; a semana a semana (dias 1–7, 8–15, 16–fim); os recebimentos por forma.
  - As contas, como na planilha: **Despesas** = custo fixo + variável; o **Investimento** fica à parte (grupo, filial ou segmento "Investimento"). **Resultado** = receita bruta − despesas. **Líquido** = a soma dos recebimentos.
- **Exportar:** **Excel** (.xlsx: o resumo mês a mês, cada local e as abas de lançamentos) e **PDF** (com a logo, os números grandes, os gráficos e as tabelas, para o dono).

## 6zzn. Caixinha que não fecha sozinha, abrir rápido e reabrir (1.58)

- **Caixinhas (folhas):** arrastar o mouse para selecionar um texto e soltar fora da caixinha não fecha mais. Ela fecha no X, no Esc ou num clique simples no fundo escuro. O mesmo nas Finanças.
- **Abrir rápido:** o aplicativo abre com a tela guardada no aparelho e mostra na hora a lista da última vez; a lista de agora chega em seguida (uma faixa fina no alto mostra que está buscando; até ela chegar, os botões esperam). Quando sai uma versão nova, a tela recarrega sozinha com ela, logo na abertura.
- **Reabrir:** o tempo nunca zera nem para: continua contando da entrada (o tempo que a criança ficou fora entra na conta). Vale para o reabrir no prazo e para o do gestor, fora do prazo.
- **Impressão direta:** a janelinha que pisca ao imprimir é do próprio Chrome (é o jeito dele de imprimir direto); o site não consegue esconder.

## 6zzm. Festas na Agenda, trocar a forma de pagamento e reabrir fora do prazo (1.57)

- **Agenda:** as festas vendidas aparecem no calendário (o pontinho no dia), no dia escolhido e na semana, com o horário e o tema. Tocar abre a festa.
- **Trocar a forma de pagamento** ("o cliente disse crédito e pagou no Pix"):
  - **na folha do cupom**, logo depois de pagar: "Trocar a forma de pagamento", com o relógio de 2 minutos. Toque na forma certa e o cupom sai com ela (com o cupom fiscal ligado, fica na folha do cupom fiscal, enquanto ele está a emitir);
  - **no histórico do cliente**, em cada compra: "Trocar pagamento". A equipe só nos 2 minutos depois de pagar; o **gestor, sempre**.
  - A troca vale para o pagamento inteiro; o caixa, as Finanças e o cupom fiscal acompanham. Fica o registro (e no extrato). A troca feita pela equipe **avisa o gestor** na tela (com o "Visto") e no celular.
- **Reimprimir o cupom:** a equipe só nos 2 minutos depois do cupom; depois, só o gestor.
- **Reabrir a visita:** dentro do prazo (Ajustes → Reabrir a visita: agora também "Até 10 min"), qualquer um reabre. Depois do prazo, **só o gestor**, no mesmo dia: o botão diz "Reabrir · gestor" e a folha avisa em vermelho que ele está reabrindo fora do prazo; fica anotado.

## 6zzl. Telefone na notinha, reimpressão, cancelar fora da tolerância, cortesia, alterar as horas pelo número e o e-mail do dia (1.56)

- **Notinha (cupom):** o telefone do cliente sai nas **duas vias** (da loja e do cliente), para ligar para ele pela notinha. Na reimpressão, sai **REIMPRESSÃO** com a data e a hora em cima (e embaixo).
- **Cancelar a entrada fora da tolerância:** passou dos 9 min, **só o gestor** cancela. A ficha mostra "Cancelar entrada · fora da tolerância" e a folha avisa; fica anotado como cancelamento fora do prazo.
- **Cortesia:** no pagamento da saída, o botão **Cortesia** com o relógio correndo (o tempo que a criança está no espaço, até 9:00). Também na saída em conjunto (irmãos), um botão para cada criança que entrou há pouco. Passou dos 9 min, o botão apaga para a equipe; o gestor ainda dá, com o aviso e um segundo toque para confirmar.
- **Alterar as horas pelo número (gestor):** no **Banco de horas**, toque no saldo do cliente; no **histórico**, toque em "Horas compradas". Abre a correção das horas (com uma compra só, direto; com mais, escolha qual). A alteração aparece no **Relatório do cliente (extrato)** como "Horas alteradas pelo gestor", com o motivo e quem mudou.
- **Ajustes → Relatório do dia por e-mail (gestor):** até 4 e-mails (do gestor, do financeiro e mais dois) e "Mandar sozinho todo dia" (sai meia hora depois de fechar, com o PDF). "Mandar agora o de hoje" para testar. É o mesmo de Finanças → Conta.

## 6zzk. Notas fiscais do dia, a nota sem esperar o gestor e a correção das horas (1.55)

Pedido do dono: o relatório das notas emitidas no dia na tela do gestor e no financeiro (faturamento em notas e em caixa); todos emitem a nota, só o gestor cancela; o gestor corrige as horas do pacote quando a equipe lança errado.

- **Nota fiscal** (botão **NF** na compra, no histórico do cliente): qualquer pessoa da equipe preenche em nome de quem a nota sai e toca em **Emitir no Play Space Notas**. Não precisa mais da autorização do gestor. Emitida (produção), o número volta sozinho e a nota fica marcada na compra.
- **Só o gestor** desfaz a marcação, corrige o número, tira uma nota aberta que não vai sair e **cancela a nota** (com o motivo). A nota cancelada sai do faturamento em notas e fica no relatório como cancelada. No **Play Space Notas**, só quem é gestor cancela: a conta principal e quem ela marcar em Conta → Usuários.
- **Painel do gestor → Notas fiscais hoje**: em notas, no caixa, sem nota, a porcentagem do caixa com nota e cada nota (número, em nome de, compra, valor, quem emitiu). A nota de uma venda que foi cancelada aparece com o aviso para cancelar a nota também. Em **Cada unidade hoje**, a coluna **Em notas**.
- **Finanças → Notas fiscais**: o mesmo para qualquer período e unidade, dia a dia, cada unidade, cada nota e a planilha. Também na Visão geral, no PDF do dia e no e-mail.
- **Alterar as horas** (gestor, no histórico do cliente, em cada compra de horas e nas horas que vieram do sistema anterior): escreva as horas certas (ex.: 5 ou 11h30) e o motivo. Muda só as horas daquela compra (o valor fica), o saldo acompanha a diferença e não dá para tirar horas que já foram usadas. Fica no registro do cliente, com o motivo e o nome do gestor.

## 6zzj. O cupom só sai com a autorização; o motivo no alterar pacote; a festa pelo gestor (1.54)

Pedido do dono: sem a permissão do gestor, a atendente não segue; o motivo no desconto e no alterar pacote; o gestor cancela a festa e muda a data.

- **Pediu desconto** (ou escreveu o desconto e ainda não pediu), ou **pediu para alterar o pacote**: o botão de registrar (o que imprime o cupom) fica **riscado e não clica**, com o porquê escrito nele, até quem autoriza responder. Vale na venda e na saída (sozinha ou em conjunto).
- **Negado**: o botão continua travado. A atendente pede de novo, ou toca em **Cobrar sem o desconto** (ou **Cobrar sem alterar**) e segue com o valor de antes.
- **Alterar pacote** tem o campo **Motivo** (obrigatório), como o desconto. Quem aprova vê o motivo em destaque nas Aprovações e no aviso do celular.
- **Festa** (abrir a festa na agenda ou em Festas), só para o gestor:
  - **Cancelar a festa** (com o motivo): a venda da festa, o upgrade e o adicional são cancelados (mesmo vendidos há mais de 7 dias), a festa sai da agenda e aparece o que devolver ao cliente. Festa que já começou (criança já entrou) não cancela.
  - **Mudar o dia** para qualquer dia: se passar de semana para final de semana (ou o contrário), o tipo da festa muda junto; o valor da venda não muda (acerte a diferença à parte, se tiver).
  - **Editar o cadastro do cliente**, direto da festa.

## 6zzi. Resumo do dia em PDF e por e-mail (1.54)

Pedido do dono: um resumo do dia em PDF para os gestores, com o "exportar PDF do dia" lá em cima, e o e-mail cadastrado para receber o faturamento do dia.

- **Finanças → Exportar PDF do dia** (no alto, ao lado do "atualizar"): o PDF do dia escolhido (ou do período), da unidade escolhida e de todos os produtos: faturado, recebido, em aberto, crianças, pacotes, descontos, cancelamentos, festas, cada unidade, as formas de pagamento, o que foi vendido por tipo, o produto a produto, cada desconto (com o motivo e quem liberou) e cada cancelamento.
- **Finanças → Conta → Resumo do dia por e-mail**: os e-mails (até 10) e o **Mandar sozinho todo dia, quando a loja fecha** (sai meia hora depois da hora de fechar dos Ajustes, das duas lojas, com o PDF anexado). **Mandar agora o de hoje** e **Mandar o de ontem** mandam na hora. Se nenhuma tela estiver aberta na hora de fechar, o e-mail sai na manhã seguinte, com o resumo do dia anterior.
- O e-mail sai do próprio servidor da loja (remetente nao-responda@ o endereço da loja). Se a hospedagem não deixar o PHP mandar e-mail, a tela avisa.
- **Play Space → Caixa do dia → PDF do dia**: o gestor baixa o mesmo PDF pelo Play Space.

## 6zzh. Finanças: filtro por produto e o produto a produto (1.53)

Pedido do dono: no financeiro, filtro por data, por unidade e por produto (todos, festa, só pacote, só recreação avulsa, só produto), tudo destrinchado e já na tela.

- Em cima, três linhas de filtro: o **período** (hoje, ontem, 7 e 30 dias, este mês, mês passado, este ano, ou escolher as datas), a **unidade** e, agora, o **produto**: Todos os produtos · Pacotes · Recreação avulsa · Festas · Produtos da loja · Carrinhos, ou **um produto só** (a lista traz todos: os da loja, os pacotes, os serviços e as festas, mesmo os que não venderam no período).
- Com o filtro de produto, faturado, recebido (já sem o devolvido), em aberto, vendas, pacotes, o gráfico, as formas de pagamento e as vendas por atendente são só daquilo. Crianças, cancelamentos, descontos e festas marcadas continuam de tudo (um aviso diz isso).
- Na Visão geral, sempre aberta: **Produto a produto**, com cada coisa vendida (quantidade, valor e % do total), por tipo, e os que não venderam em zero. Tocar num produto ou no tipo filtra por ele. **Baixar planilha** leva a tabela para o Excel.

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
- **Avisos no celular: "O servidor da loja ainda não consegue mandar avisos"** (seção 6zl): a hospedagem precisa do PHP 7.3 ou mais novo, com openssl e curl (cPanel → *Selecionar versão do PHP*), e o site em `https://`. Se o aviso de teste não chega, toque em **Desligar** e em **Ativar avisos neste celular** de novo.
- Erros do servidor vão para o log de erros do PHP (cPanel → *Erros*) com o prefixo `[playspace]`.
