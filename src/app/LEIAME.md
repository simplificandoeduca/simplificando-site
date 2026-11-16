# App do aluno em partes

O app do aluno (`site/treino/index.html`) é montado a partir daqui. O arquivo `src/treino-grade.html` é a **casca**:
o começo da página, o corpo e uma linha `<!--#parte arquivo-->` para cada parte. O `tools/build_site.py`
troca cada linha dessas pelo conteúdo do arquivo e gera **um `index.html` só**, igual ao de antes.

As partes estão na **mesma ordem** em que o app sempre rodou (o número na frente do nome é essa ordem).
Algumas partes têm mais de um pedaço porque o código delas fica em mais de um lugar. Juntar mudaria a
ordem e poderia mudar o comportamento do app, então ficaram separadas.

| Parte | Arquivos | O que tem |
|---|---|---|
| estilos | `00-estilos.css` | Todo o visual (cores, cartões, telas, modo escuro) |
| base | `01-base.js` | Treino de exemplo, datas, estado do app, salvar no aparelho |
| | `07-base-regras-icones.js` | Regras do treino (carga sugerida, séries), ícones, pedaços de interface |
| | `18-base-render.js` | Desenhar as telas e as janelas; acesso do robô de testes |
| | `19-base-acoes.js` | O que cada toque faz; academia do dia; "Como foi"; começar e finalizar treino; água |
| | `21-base-liga.js` | Liga o app quando a página carrega |
| | `23-base-interface.js` | Lista com rolagem própria e janela que acompanha o teclado |
| login | `02-login.js` | Firebase, entrar com senha, abrir o app, tela de entrada |
| | `03-login-termos.js` | Termos de uso, privacidade e aceite (LGPD) |
| | `05-login-cadastro.js` | Criar conta sem personal, perguntas e treino montado pela IA |
| hoje | `08-hoje.js` | Tela Hoje (check-in, treino do dia, cardio obrigatório, semana) |
| treino | `09-treino.js` | Os 4 visuais do treino, séries, descanso, aquecimento, treino esquecido |
| | `16-treino-folhas.js` | Janelas do exercício e do resumo |
| | `17-treino-stories.js` | Stories do fim do treino e do cardio |
| | `22-treino-animacao.js` | Animação de como fazer cada exercício |
| editar treino | `06-editar-treino.js` | "Meus treinos": montar, séries, aquecimento, letras A/B |
| cardio | `10-cardio.js` | Os 3 visuais do cardio e o registro |
| | `12-cardio-boneco.js` | Boneco animado do cardio |
| água | `11-agua.js` | Garrafinha, copos, meta e lembretes |
| evolução | `15-evolucao.js` | Gráficos, recordes e semanas na meta |
| perfil | `14-perfil.js` | Perfil e bloco de Notificações |
| avisos | `04-avisos-janelas.js` | Janelas do 1º acesso: Baixe nosso app, Quer compartilhar, avaliação |
| | `13-avisos-lembrete.js` | Lembrete do treino do dia (horário aprendido) |
| | `20-avisos-celular.js` | Fim do descanso, treino esquecido, avisos no celular, versão nova, sem internet |

**Depois de mexer em qualquer parte:** `python3 tools/build_site.py` e o robô de testes (`cd testes && npm test`).
