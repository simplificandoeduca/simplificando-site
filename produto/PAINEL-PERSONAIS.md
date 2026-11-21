# Painel para outros personais (a venda do site)

Hoje o painel (`/personal`) tem uma conta só: a do dono, que é o administrador.
Quando o site começar a ser vendido, cada personal que comprar vai ganhar o próprio perfil no painel.
Esta lista guarda o que é só do dono e **não pode aparecer** no painel desses personais.

## Não aparece no painel dos outros personais

| Item | Onde fica hoje no painel do dono (`src/admin.html`) | Observação |
|---|---|---|
| Avaliações | Números › Avaliação do app (notas dos alunos a cada 15 dias) | São as notas do app, que interessam só ao dono. A avaliação física dos alunos (peso, gordura, medidas) continua para o personal. |
| Dados | Ajustes › Dados (contagem e "Apagar dados de exemplo") | |
| Avisos no celular | Ajustes › Avisos no celular (`nvCard`: aviso de conta nova no Simplificando) | |
| Erros do app | Ajustes › Erros do app dos alunos (`errCard`, lê o `erros.php`) | |
| Importar alunos | Ajustes › Importar alunos (arquivo .json de planilha) | |
| Você | Ajustes › Você (nome do personal e link do app) | O nome do personal entra sozinho, pelo cadastro da conta dele. Não precisa de campo para digitar. |

## Ainda a decidir quando o painel dos personais for feito

- Como o personal compra e cria a conta (cadastro, pagamento).
- Cada personal vê só os próprios alunos (separar os dados por personal no Firebase e nas regras).
