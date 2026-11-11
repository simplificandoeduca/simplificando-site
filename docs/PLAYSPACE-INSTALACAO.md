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

Para ver como a tela fica com o espaço cheio, a gestora toca em **Teste: 30 crianças**, no rodapé de qualquer tela (ou em Ajustes → Dados de exemplo). Entram 34 crianças fictícias:

- **30 no espaço agora**, com nomes, responsáveis e horários de entrada diferentes: umas acabaram de entrar (com o atalho de cancelar), outras estão no tempo, acabando, **na tolerância** e três **já com cobrança por atraso**; duas são **PCD** e duas entraram com **voucher**; algumas têm restrição anotada e duas estão sem sapateira. Há **duas famílias com duas crianças** (aparecem juntas na lista) e **três crianças chamadas PEDRO**, de responsáveis diferentes, para ver como o nome do responsável separa uma da outra.
- **4 fora do espaço** (uma com a entrada cancelada, para a supervisão conferir; duas que já saíram hoje, uma delas com cobrança por atraso; e a **Marina**, com o cadastro feito agora há pouco e o atendimento por fazer, que aparece em **Falta finalizar**), para testar a busca.
- **5 crianças com pacote de horas** (ELISA, FELIPE, JOAQUIM, JOÃO e RAFAELA) e as outras na recreação avulsa, para ver o filtro **Pacotes / Horas avulsas**.
- **4 carrinhos fora da loja**: três fusquinhas (um dentro do tempo, um que já passou e um de quem não está no espaço) e um carrinho de bebê, para ver as linhas com o botão **Devolveu** (seção 6o).
- Vendas e registros do dia e compromissos na agenda.

Elas se comportam como crianças de verdade: o tempo corre, a cobrança por atraso nasce sozinha, dá para dar saída, cancelar e vender. Nada vai para o iDFace. Nas crianças de teste o relógio não para na hora de fechar a loja, para o teste funcionar a qualquer hora.

Para tirar, o mesmo botão vira **Tirar o teste**: saem as 34 crianças e tudo o que nasceu delas (vendas, cobranças, carrinhos fora, linhas do dia, agenda). Os cadastros de verdade ficam. Só a gestora coloca e tira o teste; a equipe vê as crianças de teste na tela enquanto elas estiverem lá.

Na prévia (sem servidor) o mesmo botão existe e faz a mesma coisa na própria tela.

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

- No **atendimento**, ao registrar uma venda com Recreação, a opção **Dar entrada agora** já vem marcada: a venda é gravada e o tempo começa a contar. Para quem está comprando para outro dia, é só desmarcar.
- Quem já tem **um período inteiro guardado** ganha o botão **Entrada** (na busca da tela inicial e na ficha). Sem um período comprado, a entrada não é aceita: aparece **Vender** (ou **Atender**, na busca).
- Quem está no espaço tem o botão **Saída** na linha da lista e na ficha. O toque abre o **Pagamento da saída** (seção 6r): de quem é, quanto ficou e como a visita é paga, com as horas do cliente e, se passou do tempo, com o valor a receber e a forma de pagamento. O segundo toque confirma. São dois toques de propósito: com 40 crianças na lista, um toque errado não tira a criança errada. A saída libera a sapateira e avisa onde está o sapato.
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
- **Os dados ficam salvos, e o cliente que volta não escreve nada de novo**: no cartão "Cliente já cadastrado", cada criança dele é um botão, com quando veio, o saldo e o aviso "tem observação". **Um toque na criança abre o atendimento dela.** Se for outra criança do mesmo responsável, basta escrever o nome dela: os dados do responsável já vêm preenchidos. Nada disso se perde nas atualizações: cadastros, vendas, visitas e saldos ficam no banco de dados da loja.
- **Histórico do cliente**: abre numa aba à direita (no celular, na tela toda) e junta tudo do responsável: quanto pagou ao todo, horas compradas, consumidas e disponíveis; cada criança com o saldo, as restrições e os botões **Vender** e **Chamar para renovar**; **todas as compras** (pacote, o que veio junto, carrinho e outros produtos, valor, quem vendeu) e **todas as visitas**. Abre pelo botão **Histórico** no cadastro, na ficha da criança ou tocando na "Última contratação" do Banco de horas. O cliente é reconhecido pelo CPF ou pelo RNE; sem documento, pelo WhatsApp.
- **Sem horas, sem entrada**: com a regra "Quando o pacote zerar → Bloquear o acesso sozinho" (o padrão), a criança recém-cadastrada começa com o acesso **bloqueado** no iDFace e é liberada sozinha na primeira venda de horas. Vender só um produto (carrinho) não libera. Desfazer uma venda que deixa o saldo em zero bloqueia de novo.

**Sapateiras** (1 a 44): a criança tira o sapato para brincar e ele fica numa sapateira numerada.

- No **cadastro**, a grade de números aparece logo depois do nome da criança: um toque escolhe. As que estão **em uso aparecem hachuradas** e não podem ser escolhidas (tocar numa delas mostra com quem está e oferece **Liberar**, para o caso de uma esquecida).
- Para quem já é cadastrado: na **ficha** (linha "Sapateira", botão Escolher ou Trocar), no **número da sapateira** no começo da linha de quem está no espaço (na tela inicial), ou no botão **Sapateira** do aviso que aparece quando o rosto é lido na entrada.
- O número fica à vista no começo da linha da criança, na lista do espaço. Na **saída**, a sapateira **fica livre sozinha** e o aviso diz onde está o sapato ("Helena saiu · sapato na sapateira 12"); o mesmo fica anotado em "Hoje na recepção".
- **Ajustes → Sapateiras** mostra o mapa (quem está com qual), libera uma esquecida e muda a quantidade (44 por padrão).

**Atendimento e serviços**: ao terminar o cadastro, o sistema abre o **atendimento** da criança: no meio, os serviços com o preço e o **total a pagar**; ao lado (em tela larga), os dados e o histórico de compras do cliente. O mesmo atendimento abre pelo botão **Vender** (ficha, Banco de horas, histórico).

- **O que o atendimento mostra, de cima para baixo**: quem é a criança e o responsável; o **OBS** da observação (vermelho e aceso quando há; o texto só aparece com um toque, seção 6q); as **horas do cliente** em três números (**comprou**, **usou** e **saldo**, em destaque); depois o **tempo de recreação** avulso (com o voucher), os **pacotes de horas**, os **outros serviços** (carrinhos), os **produtos** e o **outro item**.
- **Dar entrada com o saldo**: quando a criança está fora do espaço e tem pelo menos um período guardado, aparece o botão "Dar entrada com o saldo (4h30)": ela entra usando as horas que já tem, sem vender nada. Se a equipe escolher algo para vender, o botão some e a entrada vai junto com a venda (a caixa "Dar entrada agora").
- Ao marcar tempo ou pacote, o atendimento diz como o saldo fica: "Entra 5h de recreação no saldo: fica com 9h30".

- Os serviços de fábrica são **Recreação** (R$ 80 a cada meia hora), **Carrinho fusquinha** (R$ 15 a cada 30 min) e **Carrinho de bebê** (R$ 30, das 10h às 22h). Em cada linha, o **+** põe mais um período (ou mais uma unidade) e o **−** tira; o valor da linha e o total aparecem na hora. Com algo escolhido, o botão do pé vira **Pagamento · R$ …**: um toque leva para a tela de pagamento (seção 6p), que grava tudo de uma vez; o aviso tem **Desfazer**.
- O tempo de **Recreação** vendido entra no saldo de brincar da criança (meia hora conta) e libera o acesso dela; os carrinhos ficam só anotados na venda, com a duração.
- **Ajustes → Serviços e preços** muda o nome, o valor e a forma de cobrar (valor único ou a cada período de tantos minutos), cria serviços novos (uma meia, um lanche) e diz qual deles é tempo de brincar. O valor cobrado sai sempre dessa tabela.
- **Pacotes de horas**: no atendimento ficam numa linha só, **Lista de pacotes**, para a tela não ficar cheia. Um toque abre a lista (um pacote por linha: o tempo, o nome e o valor); outro toque escolhe, a lista fecha e o pacote escolhido fica à vista na própria linha. Para trocar, é abrir de novo. A tabela completa, com o valor da hora, fica no botão **Pacotes** da tela inicial (seção 6j).
- **Produtos** (Amigurumi, Maleta, Kit laço…): aparecem logo depois dos pacotes; um toque põe um na venda (seção 6l). **Outro item**, no pé do atendimento, registra algo fora da tabela.
- **Avisar quando restar** (Ajustes → Controle de horas) começa em 10 minutos, porque a recreação é vendida de 30 em 30.

## 6b. Banco de horas (aba)

A aba **Banco de horas** mostra todas as crianças com o saldo (**as horas não expiram**, seção 6r), a **última contratação** (quantas horas, valor e dia; tocar nela abre o histórico do cliente) e a situação. A busca acha por criança, responsável, telefone ou CPF. Quem foi cadastrado e ainda não comprou aparece como **Sem horas**. O filtro **Pacote acabou** mostra quem comprou pacote de horas e ficou sem um período guardado. *Com o módulo do WhatsApp ligado (seção 4):* **"Para renovar" vale só para quem comprou pacote de horas** e ficou sem um período guardado, e só depois que saiu: quem compra a recreação avulsa não entra na lista, e ninguém é chamado enquanto a criança está no espaço. No topo, **Para renovar** lista quem zerou ou está acabando, com o aviso pronto: "As horas do Miguel acabaram. Chame a Carla (mãe) no WhatsApp para ver se quer renovar." O botão **Chamar no WhatsApp** abre a conversa com a mensagem de renovação já escrita (com os pacotes e preços de Ajustes): é conferir e enviar. Depois de chamada, o cartão mostra "Já chamada hoje às 14:20 por Beatriz", para ninguém chamar duas vezes. Quando o iDFace registra uma saída que zera o pacote, a tela avisa na hora com o mesmo botão. O selo na aba mostra quantas crianças estão para renovar.

## 6c. Avisos do sistema (o sininho no alto da tela inicial)

O sistema continua avisando o que vale olhar agora, mas os avisos **não ocupam a tela inicial**: ficam no botão do **sininho**, no alto, com a contagem (em vermelho quando há algo importante). Um toque abre a lista, uma frase e um botão por aviso:

- **Cobrança por atraso** de quem ainda está no espaço: "ALICE passou do tempo comprado: nova cobrança por atraso. R$ 80 a receber de JULIANA na saída."
- **Entradas canceladas** para a supervisão conferir.
- **iDFace sem contato**: o que impede o sistema de trabalhar sozinho (só com o iDFace ligado).
- **Agenda**: o que começa nas próximas 2 horas.
- **Cadastro**: criança sem rosto no iDFace, ou com o acesso bloqueado mas com saldo (só com o iDFace ligado em Ajustes).

Cada aviso pode ser dispensado no X (vale para o dia, naquele aparelho). Sem avisos, o sininho some. A nova cobrança por atraso e a entrada cancelada continuam aparecendo também no aviso rápido do pé da tela, em qualquer aba.

Com o módulo do WhatsApp desligado (seção 4), **não aparecem** os avisos que mandavam chamar o responsável: pacote de horas acabado, quem sumiu, família esperando resposta e WhatsApp desconectado. Eles voltam junto com o módulo.

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

## 6f. Tela inicial: a lista

A primeira tela (aba **Espaço**) mostra **todo mundo numa lista, uma linha por cliente**, como uma planilha:

- a **situação**: bolinha verde = no tempo; amarela = acabando (faltam 10 min ou menos); vermelha, com a linha rosada = passou do tempo comprado. No que saiu da loja (carrinho, fusquinha), no lugar da bolinha fica o desenho do carrinho;
- a **criança**, com o **OBS** vermelho quando há observação (o texto só aparece com um toque, seção 6q) e o selo **+ R$ 80** quando há cobrança por atraso a receber;
- o **responsável** (com três PEDRO no espaço, é ele que diz qual é qual);
- o **serviço**: RECREAÇÃO, com o selo **AVULSA** ou **PACOTE**; ou o que saiu da loja (CARRINHO FUSQUINHA, CARRINHO DE BEBÊ);
- a hora em que **entrou**;
- o **tempo** correndo e, ao lado, **até que horas vai** ("até 13:46") ou **quanto passou** ("passou 7 min");
- o botão **Saída** (ou **Devolveu**, no que saiu da loja).

**Quem está mais perto de estourar o tempo vem primeiro.** As linhas do mesmo responsável (irmãos, ou a criança e o carrinho dela) ficam **juntas**, com um elo ao lado do nome dele. O sistema junta quem tem o mesmo CPF (ou RNE) do responsável; em cadastros sem documento, quem tem o mesmo telefone.

**Em cima da lista:**

- os **filtros por serviço**, com a contagem de cada um: **TODAS**, **RECREAÇÃO** e um para cada coisa que sai da loja e volta (seção 6o). Um toque mostra só aquele serviço; **SAÍRAM HÁ POUCO** aparece no fim quando há alguém no prazo de reabrir a visita (seção 6m);
- a **busca** (criança, responsável, telefone ou CPF). Ela filtra a lista e também acha quem tem cadastro e não está no espaço, com os botões **Entrada** (se tem um período guardado) e **Atender** (vender e dar entrada).

**Tocar na linha abre a comanda** (a ficha), com tudo: responsável, telefones, documento, o OBS, o que está fora da loja (com o **Devolveu**), **sapateira**, hora da entrada, até quando vai o tempo, quanto debita se sair agora, os três números (**comprou**, **usou** e **saldo**), o **cancelar a entrada**, as cobranças por atraso, o histórico e os botões.

**A lista se ajusta ao espaço**: no computador, todas as colunas; em tela menor (ou com o histórico do cliente aberto ao lado), o responsável vai para baixo do nome e a hora da entrada sai; no celular, a segunda linha fica "MARIANA SOUZA · AVULSA" (ou "CARLA · FUSQUINHA").

O resto da tela continua igual:

- a faixa **Falta finalizar** só aparece quando alguém ficou com o cadastro ou o atendimento pela metade (veja 6i);
- **no alto**: quantas crianças estão no espaço, quantas passaram do tempo e **quantos carrinhos há para devolver**; o **sininho** dos avisos (só quando há algum, seção 6c), **Pacotes e produtos** e **Entrada na tela**;
- **Atividades da semana**: em tela larga (1400 px ou mais) ficam na coluna da direita, por dia, sempre à vista. Em tela menor e no celular viram **uma linha só** embaixo da lista; um toque abre os dias;
- **no pé**, dois atalhos: **Banco de horas** (quem não está no espaço) e **Hoje na recepção** (cada entrada, saída, venda, pagamento, devolução, cancelamento e mudança de estoque do dia). Os números do dia estão no alto de **Hoje na recepção**.

**As três colunas por serviço** (Horas avulsas, Pacotes e Carrinhos), que eram a tela inicial da 0.91 à 0.94, continuam no sistema como opção de cada aparelho: **Ajustes → Aparência → Tela inicial neste aparelho → Por serviço**. Como a aba Ajustes só aparece para Gestor e Supervisor, o aparelho dos atendentes fica na lista.

## 6g. Cobrança por atraso

Quando a criança continua no espaço depois do **tempo comprado mais a tolerância** (30 min + 9 = 39:00), o sistema registra sozinho **mais um período** (Recreação +0h30, R$ 80). Se ela continuar, depois de mais 30 min e da tolerância (1h09) nasce outro, e assim por diante.

- **Todo mundo fica sabendo**: aviso em todas as telas abertas ("Nova cobrança por atraso: Alice passou do tempo comprado · R$ 80 a receber na saída"), uma dica, o selo **+ R$ 80** na linha da criança e a linha "Nova cobrança por atraso: ficou além do tempo comprado" em Hoje na recepção. A venda entra no histórico do cliente marcada como *cobrança por atraso*.
- **Na saída**, a tela de pagamento mostra o valor a receber e pede a forma de pagamento; com a forma escolhida, o botão vira **Recebi R$ 80 no Pix · dar saída** (seção 6r).
- Quem gera a cobrança é o servidor, a cada poucos segundos enquanto alguma tela estiver aberta, e de novo na saída: mesmo com tudo fechado, a conta fecha certa.
- **Saída esquecida** (a criança foi embora e ninguém marcou): Gestor ou Supervisor usa **Saída com outra hora** (na ficha ou na folha da saída), escreve a hora em que ela saiu de verdade e o motivo. O tempo é refeito e as cobranças por atraso que sobraram são desfeitas. Se a saída já foi marcada, a ficha tem **Corrigir** ao lado da visita (últimos dois dias). Tudo fica anotado em Hoje na recepção.
- **Na atualização para a 0.75**, quem já estava marcado como "no espaço" não ganha cobrança para trás: a regra vale para as entradas feitas depois de atualizar.
- O responsável não é avisado de nada disso pelo WhatsApp.

## 6h. Criança PCD e voucher

**Criança PCD: 50% de desconto, só na recreação avulsa.**

- No cadastro (e em **Editar**), marque **Criança PCD**. A partir daí a **recreação avulsa** dela sai pela metade: a Recreação no atendimento (R$ 40 a cada meia hora) e a **cobrança por atraso**, que é um período avulso a mais. **Pacote de horas, carrinho de bebê, fusquinha e outros produtos não têm desconto.**
- O atendimento mostra o preço já com o desconto e o selo **PCD 50%**; a lista do espaço e a ficha mostram o selo **PCD**.
- Cada venda com desconto fica anotada ("Recreação · 0h30 · PCD 50% · R$ 40") em Hoje na recepção e no histórico do cliente. **Marcar ou desmarcar PCD também fica anotado no dia**, com o nome de quem fez, porque muda o preço.

**Voucher do shopping: 30 min de recreação grátis, um por visita, sem código.**

- No atendimento, abaixo dos serviços, a linha **Voucher** é só de marcar: um toque marca, outro desmarca. Não tem quantidade nem código.
- **Os primeiros 30 min saem grátis; o que passar disso volta a ser cobrado normalmente.** Sem pacote: ficou até 39 min (30 + a tolerância), não paga nada; ficou 1 h, paga 1 período (R$ 80); ficou 2 h, paga 3 períodos (R$ 240). A cobrança nasce sozinha, como toda cobrança por atraso (R$ 40 por período se a criança for PCD).
- **Quem tem pacote de horas**: o voucher cobre os primeiros 30 min e o resto sai do pacote, sem cobrança. Ex.: pacote de 5 h + voucher, ficou 2 h: sobram 3h30.
- Dá para juntar na mesma venda o voucher e períodos pagos (ex.: voucher + 30 min pagos = 1 h, R$ 80).
- **Um por visita**: quem já usou o voucher na visita (ou tem um de hoje guardado, ainda sem entrar) não ganha outro; a linha aparece como "já usado nesta visita". Se a criança sair e voltar no mesmo dia com outro voucher, o sistema aceita e anota "voucher (2º de hoje)", para a supervisão ver.
- **O voucher chegou depois** (o responsável trouxe na hora de buscar): na folha da **Saída**, quando há valor a receber, aparece **Tem voucher? Usar agora**. Um toque e ele cobre um período da cobrança por atraso (R$ 80 a menos); o aviso tem Desfazer. O mesmo acontece ao marcar o Voucher no atendimento de uma criança que está no espaço com cobrança a receber.
- A lista do espaço mostra um presentinho ao lado do nome de quem está com voucher; a ficha e a folha da saída dizem "Voucher usado: meia hora desta visita ficou por conta do voucher".
- A venda fica anotada ("Recreação · 0h30 · voucher · R$ 0") em Hoje na recepção e no histórico do cliente. Quando o voucher cobre uma cobrança, a linha da cobrança fica marcada "coberta pelo voucher".
- Em **Hoje na recepção** (bloco "Horas vendidas hoje"), um resumo mostra quantos vouchers e quantas vendas com desconto PCD houve nas últimas 24 h e quanto isso representou: é o número para conferir com os vouchers de papel recolhidos no dia.

## 6i. Falta finalizar: cada atendimento continua de onde parou

O atendimento tem três etapas: **cadastro**, **escolher o serviço** e **registrar a venda** (com a entrada). Se a atendente é interrompida no meio, nada se perde: ao tocar naquele cliente de novo, a tela **abre na etapa em que parou**, com o que já estava preenchido.

- **Faixa "Falta finalizar"**, no alto da tela inicial: uma linha para cada atendimento em aberto, com o nome da criança, o responsável, em que ponto parou e a hora. Tocar continua dali.
  - *cadastro pela metade · falta um telefone e o nome da criança*: o cadastro novo foi fechado antes de salvar. Abre o cadastro com tudo o que já estava escrito (documento, responsável, telefones, nome, PCD, sapateira e observações).
  - *falta escolher o serviço*: o cadastro foi feito hoje e a venda ainda não. Abre direto o atendimento.
  - *R$ 80 escolhido · falta o pagamento*: o serviço já estava escolhido. Abre o atendimento com tudo de volta: quantidades, pacote, voucher marcado, outro item com o valor e a escolha de dar entrada.
- **Tocar no cliente em qualquer lugar** (a busca da tela inicial, o Banco de horas, o histórico) faz a mesma coisa: enquanto o atendimento está em aberto, o toque abre o atendimento, não a ficha. O botão **Atender**/**Vender** vira **Continuar**. Para ver a ficha mesmo assim, o atendimento tem o atalho **Ver a ficha**.
- **Criança que já está no espaço** e ficou com uma venda pela metade (ex.: mais 30 min): ela aparece na faixa, e o toque na linha dela continua abrindo a ficha, que mostra o aviso **Atendimento em aberto** com o botão **Continuar**.
- **Fechou sem terminar?** Um aviso diz que ficou guardado e traz o botão **Continuar**. Dentro do atendimento retomado, **Começar do zero** limpa o que estava escolhido.
- **Não vai fazer agora** (o cliente desistiu): o **✕** da linha tira da faixa, com Desfazer. Daí em diante o toque naquele cliente volta a abrir a ficha.
- **Registrou a venda**, sai da faixa. Se a venda for desfeita no aviso, o que estava escolhido volta.
- **Tudo vale até o fim do dia.** No dia seguinte a faixa começa vazia; o cadastro que ficou sem venda continua no Banco de horas, como qualquer cliente.

O que é de todos os aparelhos e o que é de cada um:

- **Cadastro feito e ainda sem venda** vem do servidor: aparece em todos os aparelhos, e o ✕ tira de todos.
- **O que estava escolhido** (serviços, voucher, outro item) e **o cadastro pela metade** ficam guardados **no aparelho em que foram feitos**: continuam lá se a página for recarregada ou atualizada, e não aparecem nos outros aparelhos.
- Ao **sair da conta**, os cadastros pela metade são apagados do aparelho (têm documento e telefone do responsável).
- **Sem venda em dobro**: se a venda daquela criança for registrada em outro aparelho, o que estava escolhido aqui sai da faixa sozinho. E se o atendimento estiver aberto na tela nessa hora, ao tocar em Registrar o sistema avisa *"Já foi registrada uma venda para Mari às 14:05: Recreação · 0h30 · R$ 80"* e só registra outra se a atendente tocar em **Registrar mesmo assim**.

## 6j. Pacotes de horas (botão Pacotes na tela inicial)

No alto da tela inicial, ao lado de **Entrada na tela**, o botão vermelho **Pacotes e produtos** (no celular, só **Pacotes**) abre a tabela de preços para mostrar ao cliente, em duas abas: **Pacotes de horas** e **Produtos** (seção 6l). As horas do pacote não têm prazo para usar.

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
- **Apagar** tira o produto da lista (o aviso tem Desfazer); as vendas antigas dele continuam no histórico.
- **Criança PCD não tem desconto em produto**, como no pacote (seção 6h).
- Se a gestora mudar o valor de um produto enquanto um atendimento está aberto em outro aparelho, a venda não sai no valor antigo: o sistema avisa "O valor de Maleta mudou" e pede para abrir o atendimento de novo.
- No **teste com 30 crianças** (seção 2b), o que for vendido de produto para uma criança de teste volta para o estoque quando o teste é tirado.

## 6m. Reabrir a visita (a criança saiu e voltou logo)

Às vezes a saída já foi marcada, a criança chora e o responsável deixa ficar mais. Em vez de fazer uma entrada nova, a equipe **reabre a visita**.

- **Prazo**: até **30 minutos depois da saída** (muda em **Ajustes → Regra das horas → Reabrir a visita depois da saída**: não reabre, até 15 min, até 30 min ou até 1 hora). Passou do prazo, é uma entrada nova.
- **Onde**: na tela inicial, quando alguém saiu dentro do prazo, aparece o filtro **Saíram há pouco** (com a contagem). Tocando nele, vem a lista de quem saiu, com o botão **Reabrir**. O mesmo botão aparece na busca (quem saiu há pouco) e na ficha da criança. O filtro some sozinho quando o prazo passa.
- **O que acontece**: a saída é desfeita e **o tempo continua contando desde a entrada**, como se a criança não tivesse saído. O que foi debitado volta para o saldo e a conta é refeita na próxima saída. A sapateira que ela usava volta, se ainda estiver livre; senão o aviso oferece escolher outra.
- **O tempo fora também conta.** Antes de confirmar, a folha mostra até quando ia o tempo comprado e, se o tempo fora fez passar da tolerância, quantas **cobranças por atraso** nascem ao reabrir e o valor.
- **Fica anotado** em Hoje na recepção: a linha da saída ganha "visita reaberta às 15:06" e entra uma linha nova com a hora da saída desfeita, quanto tempo ficou fora e **quem reabriu**.
- Qualquer pessoa da equipe reabre dentro do prazo (com o iDFace ligado, só gestor e supervisor, como na saída à mão). Se depois daquela saída houve uma entrada cancelada, não reabre: aí é entrada nova.
- O **Desfazer** do aviso da saída (os primeiros segundos) continua existindo, para o toque errado.

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
- **A linha**: o ícone do carrinho, a criança e o responsável em cima, o nome do serviço embaixo, o relógio contando desde a venda e o botão **Devolveu**. Com mais de uma unidade, aparece "2 × CARRINHO DE BEBÊ".
- **Com tempo comprado** (o fusquinha, vendido a cada meia hora): a linha fica amarela quando faltam 10 min e vermelha quando passa do tempo comprado. Parando o mouse no relógio aparece até que horas vai, ou quanto passou. **Mais um período do mesmo fusquinha soma o tempo na mesma linha.**
- **Sem hora para voltar** (o carrinho de bebê, valor único): a linha só conta o tempo.
- **De outro dia**: se ninguém marcou a devolução, a linha continua na lista, em vermelho, com o dia em que saiu, no começo da lista.
- **Devolveu**: um toque, na linha ou na comanda da criança. O carrinho sai da lista e fica anotado em **Hoje na recepção**: "CARRINHO FUSQUINHA · devolvido · ficou 0h44 · tempo comprado: 0h30 (passou 14 min) · por JOÃO". O aviso tem **Desfazer** (vale por 10 minutos).
- **Nada é cobrado sozinho na devolução.** Se passou do tempo comprado, a linha fica vermelha, o aviso diz quanto passou e a equipe vende o período a mais pelo atendimento da criança (botão Vender da comanda).
- Quem leva só o carrinho **não entra no espaço**: aparece na lista só a linha do carrinho. Se a criança também está na recreação, as duas linhas ficam juntas, na moldura da família.
- Se a venda for desfeita (ou deixar de ser cobrada num cancelamento de entrada), o carrinho sai da lista sozinho.
- Todos os aparelhos veem o carrinho entrar e sair da lista em poucos segundos.
- O que foi vendido antes da 0.89 não entra na lista: vale para as vendas feitas a partir da atualização.

## 6p. Pagamento: Pix, dinheiro, crédito e débito, em até três formas

Depois de escolher o serviço (horas avulsas, pacote, carrinho, produto), o atendimento **passa para a tela de pagamento**. É a mesma folha: em cima fica **Voltar para a escolha**, que não perde nada do que estava marcado.

- **O que aparece**: de quem é, o que foi escolhido com o valor de cada coisa, o **Total a pagar** e quatro botões grandes: **Pix**, **Dinheiro**, **Crédito** e **Débito**.
- **Uma forma só** (o mais comum): um toque na forma e um toque em **Recebi R$ 420 no Pix · registrar**. A venda é gravada e, se a caixa "Dar entrada agora" estava marcada, a criança já entra.
- **Dividir em duas ou três formas**: toque nas formas que o cliente vai usar. Aparece uma linha para cada uma: escreva o valor das primeiras, e **a última forma tocada fica com o resto**, para a conta fechar sempre com o total. Exemplo: Pix, Dinheiro e Crédito num total de R$ 420; escreve 200 no Pix e 100 no dinheiro, e o crédito fica com R$ 120.
- Para tirar uma forma, toque nela de novo. A quarta forma não entra: são até três.
- Se os valores passam do total, a tela avisa em vermelho e o botão fica desligado até acertar. Aceita centavos ("100,50").
- **Nada a pagar** (só o voucher): não há tela de pagamento, o botão registra direto.
- **O que fica anotado**: uma linha em **Hoje na recepção** ("Pagamento · R$ 420 · Pix R$ 200 + Dinheiro R$ 100 + Crédito R$ 120"), a forma em cada compra no **Histórico do cliente**, e o total por forma no alto de **Hoje na recepção** (Pix, Dinheiro, Crédito e Débito das últimas 24 horas).
- **Desfazer** a venda (no aviso) tira também o pagamento.
- **O servidor confere**: o pagamento tem de fechar com o total que ele mesmo calcula pela tabela. Se o valor de um serviço mudou no meio do atendimento, aparece "O pagamento não fecha com o total" e nada é vendido: é fechar e abrir o atendimento de novo.

**Na saída**, o toque em Saída já abre o **Pagamento da saída** (seção 6r): a visita é paga com as horas do cliente e, se passou do tempo, com as mesmas quatro formas, em **Forma de pagamento**. Sem escolher a forma, a saída não é dada (o botão diz "Escolha a forma de pagamento" e a tela pergunta "Como recebeu R$ 80?"). Também dá para dividir. A linha do dia fica "… cobrança por atraso: R$ 80 · recebido: Dinheiro".

**Depois de registrar**, a tela abre o **cupom** para imprimir e o responsável assinar (seção 6s).

**Cancelar a entrada de quem já pagou**: o aviso e a linha do dia dizem o que devolver e por qual forma ("devolver ao cliente: Crédito R$ 80").

**Reabrir a visita** (seção 6m): a cobrança por atraso que já foi recebida na primeira saída não volta a aparecer como "a receber"; na segunda saída a tela cobra só o que é novo.

O que ainda não tem forma anotada aparece numa frase em Hoje na recepção: é a cobrança por atraso de quem ainda está no espaço (será recebida na saída) ou uma saída lida pelo iDFace. As vendas feitas antes da 0.90 não têm forma de pagamento.

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

- em cima, a criança, o responsável e a sapateira; depois "Entrou às 14:02 e ficou 0h52. A visita conta 1h" (sempre períodos inteiros);
- **Horas do cliente** (quadro verde, com o ✓): quanto a visita usa das horas que o cliente já tem e como fica o saldo. Ex.: "usa 1h. Saldo: 5h → 4h. As horas não expiram.";
- **Passou do tempo comprado** (quadro rosa), só quando a criança ficou além das horas que tinha: quanto passou, quantas cobranças por atraso e **A receber agora: R$ 80**. Logo abaixo ficam o **Tem voucher?** (seção 6h) e a **Forma de pagamento**: Pix, Dinheiro, Crédito e Débito, em até três formas, igual à venda (seção 6p).

O botão de baixo diz o que vai acontecer:

- **Usar 1h das horas · dar saída**: a visita é paga só com as horas do cliente. Um toque e a saída está dada.
- **Escolha a forma de pagamento**: há valor a receber e a forma ainda não foi escolhida. A saída não é dada sem ela.
- **Recebi R$ 80 no Pix · dar saída**: forma escolhida; o toque registra o pagamento e dá a saída. Dividindo em mais de uma forma, o botão pede o valor que falta ("Quanto no Pix?") e, com a conta fechada, vira **Recebi R$ 80 · dar saída**.

A tela acompanha o relógio: se a tolerância estourar com ela aberta, o quadro rosa e as formas de pagamento aparecem na hora. Gestor e Supervisor continuam com **Corrigir a hora** (a criança saiu antes e a saída não foi marcada), na mesma tela.

**As horas compradas não expiram, nunca.** Não existe prazo de validade no sistema: um pacote comprado hoje vale daqui a dez anos, inteiro. O saldo só diminui quando a criança usa (a visita) ou quando uma venda é desfeita ou cancelada.

- O Pagamento da saída, a comanda ("As horas não expiram: o saldo fica guardado até ser usado") e o alto do Banco de horas dizem isso, para a equipe responder ao cliente sem dúvida.
- **Remover um cadastro que tem horas guardadas** esconderia essas horas. Por isso: o **Supervisor não remove** cadastro com horas (a tela avisa "Só o gestor remove um cadastro com horas"); o **Gestor** vê o aviso com o saldo e precisa tocar em **Remover mesmo assim**. A remoção vira uma linha em Hoje na recepção ("Cadastro removido com 4h30 guardadas · por CAMILA").
- Mesmo removido, **nada é apagado do banco de dados**: o cadastro fica inativo, com as horas, as vendas, os pagamentos e as visitas. Ainda não há um botão para trazer um cadastro removido de volta: se precisar, peça ao suporte.
- Cadastro sem horas guardadas continua sendo removido como antes (Gestor ou Supervisor, um toque).
- Para as horas valerem por muitos anos, o que precisa durar é o banco de dados: faça de tempos em tempos a cópia da pasta `~/playspace-dados/` (seção 1).

## 6s. Cupom impresso (Bematech MP-4200 TH)

O cupom da loja é o papel da impressora térmica do balcão, que o responsável assina. O sistema imprime **no mesmo formato do cupom que a loja já usava**:

- o **nome e o telefone da loja**;
- o **responsável** e o telefone dele;
- **cada criança** dele que está no espaço (irmãos saem no mesmo cupom): o número do cadastro, a **sapateira**, a hora da **entrada**, o serviço, o tempo comprado (ou as horas do cliente) e o **valor a cada 30 minutos**;
- o **pagamento** que acabou de ser feito: os itens, o total e a forma;
- a **data e a hora**, a **linha da assinatura**, o **nome e o CPF** do responsável;
- o **aviso** do pé ("Somente o responsável que assinar a entrada da criança poderá assinar sua saída"), seguido da tolerância da regra das horas.

**Quando sai:**

- **cupom de entrada**: depois de cada atendimento registrado (a venda, com ou sem entrada) e depois de cada **Entrada** pelo botão (a criança que já tinha horas);
- **cupom da saída**: depois de uma saída **com pagamento** (a hora da entrada e da saída, quanto ficou, o que usou das horas, o que foi recebido e a forma), também com a linha da assinatura;
- **de novo, a qualquer hora**: na comanda de quem está no espaço, o botão **Cupom**.

**O que cada aparelho faz** fica guardado no próprio aparelho (não muda os outros):

- **Mostrar o cupom** (como vem): depois do atendimento a tela abre o cupom, e um toque em **Imprimir o cupom** manda para a impressora;
- **Imprimir sozinho**: o cupom vai direto para a impressora, sem abrir a tela. É o modo do computador do balcão;
- **Não imprimir**: para o celular e o tablet, que não têm a impressora. O cupom não aparece ali.

A escolha fica na própria folha do cupom, na linha "Neste aparelho: … · mudar", e em **Ajustes → Cupom impresso**.

**O que vai escrito** (só o Gestor muda): **Ajustes → Cupom impresso** → nome da loja, telefone e o aviso do pé. No mesmo lugar, **Imprimir um cupom de teste**.

**Preparar o computador do balcão (uma vez):**

1. A Bematech MP-4200 TH precisa estar instalada no Windows como impressora (o programa dela, do fabricante), com o papel de 80 mm. Se o sistema antigo já imprimia nesse computador, ela já está.
2. Abra o sistema no **Chrome** desse computador, entre em Ajustes → Cupom impresso e toque em **Imprimir um cupom de teste**. Na janela de impressão: escolha a Bematech, em "Mais definições" coloque **Margens: Nenhuma** e desmarque **Cabeçalhos e rodapés**. O Chrome guarda essas escolhas.
3. Se o cupom sair cortado no meio, pequeno demais ou com muito papel em branco no fim, o acerto é nas preferências da impressora no Windows (tamanho do papel de 80 mm e corte no fim do documento). Mande uma foto do cupom de teste que eu ajusto a largura e o tamanho da letra.
4. **Para sair sem abrir a janela de impressão**: deixe a Bematech como **impressora padrão** do Windows; no sistema, escolha **Imprimir sozinho**; e abra o Chrome por um atalho com a impressão direta. Para criar o atalho: botão direito na área de trabalho → Novo → Atalho, e no destino escreva (com o endereço do sistema no fim):
   `"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing https://gamacertificacao.com.br/playspace/`
   Feche todas as janelas do Chrome antes de abrir por esse atalho (se o Chrome já estiver aberto, ele ignora a opção).

**Este cupom não é nota fiscal.** É o comprovante de entrada que o responsável assina (não tem CNPJ, nem chave, nem imposto). O acompanhamento de nota fiscal que existia na 0.93 (marcar "emitido" a cada pagamento) fica guardado no sistema, sem botão para ligar; se a loja passar a emitir nota fiscal pelo sistema, ele volta.

## 7. Atualizar

Botão **Atualizar** ao lado da versão. Ele baixa a versão nova do GitHub (ramo principal ou de trabalho, a mais nova), confere a versão dentro do arquivo e troca os arquivos de forma atômica. Não mexe em `~/playspace-dados/`. Se o GitHub demorar a refletir (cache de ~5 min), espere e toque de novo.

## 8. Se der errado

- **"Sem ligação com o servidor"** na tela de entrada: `api/api.php` não respondeu. Veja se a pasta `api/` subiu e se o PHP tem `pdo_sqlite`.
- **A Evolution recusou a chave**: a apikey está errada ou é de outra instância. Confira em Ajustes → WhatsApp.
- **iDFace "Sem contato"**: aparelho desligado, sem internet, ou o endereço push ficou errado no aparelho. Rode o conector de novo.
- **Mensagem do responsável não aparece**: o telefone no cadastro não bate com o número de onde veio (precisa dos últimos 8 dígitos iguais). Ajuste o telefone na ficha.
- **Os bonequinhos estão parados num computador**: as animações do sistema estão desligadas nele. Toque em **Animar os bonequinhos** na dica, ou escolha **Animados** em Ajustes → Aparência (seção 6d).
- Erros do servidor vão para o log de erros do PHP (cPanel → *Erros*) com o prefixo `[playspace]`.
