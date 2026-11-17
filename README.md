# Simplificando

Site do Simplificando: app do aluno (`/treino`), painel do personal (`/personal`) e termos (`/termos`).

Hub do dono (`/hub`): a entrada com os números do dia, um bloco para cada espaço (app de teste, painel, Números) e Finanças, Painel de teste, Site e **Atualizar** (instala a versão aprovada pelo robô). É a mesma página do painel (mesmo login), gerada pelo `tools/build_site.py` em `site/hub/`, com modo claro/escuro próprio. A entrada do hub pede só a senha do hub: `site/hub-login.php` confere a senha (guardada embaralhada fora do site, em `~/simplificando-hub-senha.txt`; o dono cria ou troca no próprio hub) e dá a entrada da conta dona do painel com a chave `~/simplificando-chave.json`. A página Números (`/personal/#estatisticas`) abre pelo hub, fora das abas do painel.

App de teste (`/app-teste`): o mesmo app do aluno, gerado pelo `tools/build_site.py`, que entra direto com o aluno de exemplo (Lucas), sem senha e sem o Firebase. Os dados ficam só no aparelho, separados do app de verdade.

Na mesma hospedagem, mas independente: `/financas` (controle pessoal de finanças, com login, dados e atualização próprios; veja `LEIA-ME-FINANCAS.txt`).

- `site/` — o que vai para o `public_html` da HostGator (é só isso que é publicado).
- `src/` — código do app (`treino-grade.html` é a casca; as partes ficam em `src/app/`, veja `src/app/LEIAME.md`) e do painel (`admin.html`).
- `tools/build_site.py` — monta `site/treino` e `site/personal` a partir de `src/`.
- `REGRAS-FIRESTORE.txt` — regras de segurança para colar no Firebase (Firestore → Regras).
- `.cpanel.yml` — diz ao cPanel para copiar `site/` para o `public_html`.

Atualizar o site: cPanel → Git Version Control → Gerenciar → Pull or Deploy → "Update from Remote" e depois "Deploy HEAD Commit".

Dados de alunos (arquivos .json de importação) não vão para este repositório.
