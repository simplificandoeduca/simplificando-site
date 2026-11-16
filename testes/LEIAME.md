# Robô de testes do Simplificando

Testa sozinho o app do aluno, o painel do personal, as regras do Firebase, o servidor de avisos e as fotos das telas.
Roda no GitHub a cada mudança (Actions › **Robô de testes**). Se tudo passar na `main`, a versão é **aprovada**
(ramo `aprovado`) e só ela pode ser instalada pelo "Atualizar agora" do painel.

**Nunca usa o Firebase de verdade**: o app e o painel conversam com um Firebase de mentira (emulador) e tudo que é de fora do computador de teste é bloqueado.

## Rodar no computador
```
cd testes
npm ci
npx playwright install chromium
npm test            # liga o Firebase de teste, roda tudo e desliga
npm run relatorio   # abre o relatório (com as fotos de cada falha)
```
Mudou uma tela de propósito? Atualize as fotos guardadas: `npm run fotos` (e confira as fotos novas em `fotos/`).

## O que cada arquivo testa
| Arquivo | O quê |
|---|---|
| `aluno-conta.spec.js` | Criar conta, IA monta o treino, aceites, sair/entrar, perfil antigo vira conta, ordem das janelas do 1º acesso |
| `aluno-treino.spec.js` | Começar, academia, cancelar, série (peso, arrastar, descanso), marcar todas, 4 visuais, séries seguem o plano, finalizar, meia-noite, treino esquecido (3 h), aquecimento, "Como foi?" |
| `aluno-dia.spec.js` | Cardio nos 3 visuais, cardio obrigatório, água e lembretes, check-in, evolução, editar treino (séries, aquecimento, letras A/B) |
| `aluno-avisos.spec.js` | Notificações no perfil, fim do descanso, treino esquecido, lembrete do dia (2ª semana, horário aprendido), convite, compartilhar/sugestão/avaliação no Firebase |
| `painel.spec.js` | De ponta a ponta: personal cria acesso, cadastra aluna, monta treino; aluna entra com a senha, treina; painel vê a evolução; Perfis e Ajustes |
| `painel-demo.spec.js` | Painel de demonstração: receita, abas do aluno, aquecimento, cardio obrigatório, PDF, Números |
| `regras.spec.js` | Regras do Firebase: cada um só lê e grava o que pode |
| `servidor.spec.js` | `push.php` e `erros.php` com o PHP de verdade (água, descanso, treino esquecido, lembrete, conta nova, limites) |
| `avisos-celular.spec.js` | O que aparece no celular (service workers do app e do painel) |
| `visual.spec.js` | Fotos das telas (Android e iPhone, claro e escuro, e o painel) comparadas com `fotos/` |

**Regra de ouro:** cada coisa nova no app ganha também um teste aqui.
