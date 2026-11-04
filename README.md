# Simplificando

Site do Simplificando: app do aluno (`/treino`), painel do personal (`/personal`) e termos (`/termos`).

- `site/` — o que vai para o `public_html` da HostGator (é só isso que é publicado).
- `src/` — código do app (`treino-grade.html`) e do painel (`admin.html`).
- `tools/build_site.py` — monta `site/treino` e `site/personal` a partir de `src/`.
- `REGRAS-FIRESTORE.txt` — regras de segurança para colar no Firebase (Firestore → Regras).
- `.cpanel.yml` — diz ao cPanel para copiar `site/` para o `public_html`.

Atualizar o site: cPanel → Git Version Control → Gerenciar → Pull or Deploy → "Update from Remote" e depois "Deploy HEAD Commit".

Dados de alunos (arquivos .json de importação) não vão para este repositório.
