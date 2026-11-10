# Play Space Horas — instalação e configuração (v0.30)

Sistema de controle de horas da Play Space: banco de horas por criança, entrada e saída pelo rosto (iDFace da Control iD), avisos automáticos no WhatsApp (Evolution API), agenda e equipe com três papéis. Tudo em PHP + SQLite, sem banco MySQL, feito para rodar na hospedagem da Gama (HostGator) ou em qualquer cPanel.

## 1. O que precisa no servidor

- PHP 7.4 ou mais novo (8.x de preferência) com as extensões **pdo_sqlite**, **curl**, **zip**, **mbstring** e **gd** (todas vêm ligadas por padrão no HostGator; confira em cPanel → *Selecionar versão do PHP*).
- A pasta `playspace/` dentro de `public_html/` (ou de um subdomínio).
- O site em **HTTPS** (o navegador exige para a câmera, a PWA e o cookie de login).

Os dados ficam fora da pasta pública, em `~/playspace-dados/` (criada sozinha no primeiro acesso: o banco `playspace.sqlite`, as sessões e o log). Se o servidor não deixar criar ali, usa `playspace/api/dados/` como reserva. Para fazer backup, copie essa pasta.

## 2. Subir pela primeira vez

1. No cPanel → Gerenciador de Arquivos, envie a pasta `playspace/` inteira do repositório (index.html, manifest.json, versao.json, atualizar.php, `img/`, `api/`, `ponte/`).
2. Abra `https://seusite.com.br/playspace/`. Como ainda não há ninguém cadastrado, aparece **"Bem-vinda ao Play Space"**: crie a conta da gestora (nome, login curto, senha de 6+ caracteres). Pronto, está dentro.
3. Dali em diante, **Atualizar** no topo da tela baixa as versões novas direto do GitHub. Só a gestora ou a supervisora veem o botão.

## 2b. Dados de exemplo (Ajustes → Dados de exemplo)

Para a equipe conhecer o sistema antes de usar de verdade, a gestora pode **Carregar dados de exemplo**: 10 crianças fictícias (5 no espaço agora, com o tempo correndo), banco de horas em vários estados (em dia, acabando, acabou, sem foto), vendas e registros do dia, conversas no WhatsApp de hoje e de ontem (com perguntas das famílias ainda sem resposta) e compromissos na agenda. Nada vai para o iDFace nem para o WhatsApp.

Quando for começar de verdade, **Apagar os exemplos** no mesmo lugar tira tudo isso e deixa só os cadastros reais. O link também aparece no banco de horas enquanto não há nenhuma criança cadastrada.

## 3. Equipe e papéis (Ajustes → Equipe)

| Papel | O que faz |
|---|---|
| **Gestor** | Tudo: regra das horas, pacotes, equipe, WhatsApp, iDFace. Pode remover crianças. |
| **Supervisor** | Tudo da loja (horas, cadastro, WhatsApp, agenda) e *vê* os Ajustes, mas não os altera. |
| **Atendente** | Horas, cadastro de criança, WhatsApp e agenda. Não vê Ajustes. |

A gestora cadastra cada pessoa com nome, login e senha inicial. Cada um troca a própria senha no avatar (canto inferior esquerdo no computador, aba **Conta** no celular) → *Trocar a senha*. Para redefinir a senha de alguém, a gestora edita a pessoa e digita uma senha nova.

## 4. WhatsApp (Ajustes → WhatsApp da loja)

Usa a mesma Evolution API da Sophi CRM.

1. Toque em **WhatsApp da loja**. Preencha o **endereço do servidor** (já vem `http://143.95.164.89`), o **nome da instância** (`playspace`, uma instância só para a loja) e a **chave (apikey)** da Evolution. *Salvar conexão*.
2. Toque em **Conectar pelo QR Code**. No celular da loja: WhatsApp → *Dispositivos conectados* → *Conectar dispositivo* → aponte para o código. Ele renova sozinho a cada 20 s; quando conectar, a folha fecha e o chip fica verde.
3. O **webhook** (a Evolution avisar o sistema das mensagens que chegam) é configurado sozinho na hora em que conecta. O endereço aparece na mesma folha, caso precise conferir no painel da Evolution.

O que sai sozinho pelo número da loja, conforme *Avisos automáticos* em Ajustes:

- **Entrada**: "Oi, Mariana! Helena chegou na Play Space às 14:02 💛 Tem 3h10 no pacote…" com a **foto** da criança.
- **Saída**: "Helena saiu às 16:05. Hoje ficou 2h e ainda tem 1h10 no pacote…"

As respostas dos responsáveis entram na aba **WhatsApp**, organizada por dia (Hoje, Ontem, escolher a data), com quem da equipe respondeu. A conversa é ligada pela **telefone do responsável** no cadastro da criança: por isso o telefone precisa estar certo (com DDD).

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

Tudo configurável, porque a cliente ainda não decidiu: **arredondamento** (exato, 15 ou 30 min), **cortesia** (minutos que não contam), **mínimo** por visita, **aviso** de pacote acabando e os **pacotes** (horas + valor, incluindo hora avulsa). Uma linha abaixo mostra na prática quanto cada tempo cobraria.

## 6b. Banco de horas (aba)

A aba **Banco de horas** mostra todas as crianças com o saldo, a **última contratação** (quantas horas, valor e dia) e a situação. No topo, **Para renovar** lista quem zerou ou está acabando, com o aviso pronto: "As horas do Miguel acabaram. Chame a Carla (mãe) no WhatsApp para ver se quer renovar." O botão **Chamar no WhatsApp** abre a conversa com a mensagem de renovação já escrita (com os pacotes e preços de Ajustes): é conferir e enviar. Depois de chamada, o cartão mostra "Já chamada hoje às 14:20 por Beatriz", para ninguém chamar duas vezes. Quando o iDFace registra uma saída que zera o pacote, a tela avisa na hora com o mesmo botão. O selo na aba mostra quantas crianças estão para renovar.

## 6c. Dicas do sistema

No topo da aba Horas, o próprio sistema sugere o que fazer agora, uma frase e um botão por dica:

- **Horas acabando ou acabadas**: "Catarina tem só mais 1h disponível. Mande mensagem para a Mariana (mãe) para ver se gostaria de comprar mais horas." O botão **Mandar mensagem** abre uma folha com o texto pronto (dá para ajustar) e envia sem sair da tela.
- **Família esperando resposta** no WhatsApp há mais de 3 minutos, com o botão **Responder**.
- **iDFace sem contato** ou **WhatsApp desconectado**: o que impede o sistema de trabalhar sozinho.
- **Agenda**: o que começa nas próximas 2 horas.
- **Cadastro**: criança sem rosto no iDFace, ou com o acesso bloqueado mas com saldo.
- **Quem sumiu**: criança com horas guardadas que não vem há 14 dias ou mais.

Aparecem as 3 mais importantes, com "Ver as N dicas" para o resto. Cada dica pode ser dispensada no X (vale para o dia, naquele aparelho). Quem já foi chamado para renovar nos últimos 3 dias não volta a aparecer.

## 6d. Bonequinhos (Ajustes → Aparência)

Os bonequinhos (o ursinho, o balão, o cavalinho, o despertador, o guindaste da tela de entrar) seguem o aparelho:

- Em **Automático**, eles se mexem normalmente. Se o aparelho estiver com as animações do sistema desligadas (no Windows: *Mostrar animações no Windows* desligado, ou o ajuste "melhor desempenho"; no iPhone: *Reduzir Movimento*), eles ficam **quietos**, cada um parado na sua pose. Nesse caso aparece a dica "Os bonequinhos estão quietos neste computador", com o botão **Animar os bonequinhos**: um toque e eles voltam a se mexer naquele aparelho. O X da dica dispensa de vez.
- **Animados**: mexem sempre, mesmo com as animações do sistema desligadas.
- **Quietos**: ficam parados sempre.

A escolha vale só para aquele aparelho (fica guardada no navegador).

## 7. Atualizar

Botão **Atualizar** ao lado da versão. Ele baixa a versão nova do GitHub (ramo principal ou de trabalho, a mais nova), confere a versão dentro do arquivo e troca os arquivos de forma atômica. Não mexe em `~/playspace-dados/`. Se o GitHub demorar a refletir (cache de ~5 min), espere e toque de novo.

## 8. Se der errado

- **"Sem ligação com o servidor"** na tela de entrada: `api/api.php` não respondeu. Veja se a pasta `api/` subiu e se o PHP tem `pdo_sqlite`.
- **A Evolution recusou a chave**: a apikey está errada ou é de outra instância. Confira em Ajustes → WhatsApp.
- **iDFace "Sem contato"**: aparelho desligado, sem internet, ou o endereço push ficou errado no aparelho. Rode o conector de novo.
- **Mensagem do responsável não aparece**: o telefone no cadastro não bate com o número de onde veio (precisa dos últimos 8 dígitos iguais). Ajuste o telefone na ficha.
- **Os bonequinhos estão parados num computador**: as animações do sistema estão desligadas nele. Toque em **Animar os bonequinhos** na dica, ou escolha **Animados** em Ajustes → Aparência (seção 6d).
- Erros do servidor vão para o log de erros do PHP (cPanel → *Erros*) com o prefixo `[playspace]`.
