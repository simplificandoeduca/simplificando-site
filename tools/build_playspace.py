# Monta a pasta playspace/ (o que vai para gamacertificacao.com.br/playspace/) a partir de src/playspace.html.
# A fonte é a mesma página publicada como prévia no claude.ai (sem <html>/<head>); aqui ela ganha a casca completa,
# o manifesto de "instalar como app" e o versao.json que o atualizar.php usa para saber o que baixar do GitHub.
# Uso: python3 tools/build_playspace.py  (na raiz do repositório). A versão vem do VERSAO em src/playspace.html.
import os, re, json
R = os.path.dirname(os.path.dirname(os.path.abspath(__file__))) + '/'
src = open(R + 'src/playspace.html', encoding='utf-8').read()
versao = re.search(r"const VERSAO = '([0-9.]+)';", src).group(1)
i = src.index('<div class="app">'); head, body = src[:i], src[i:]
head = head.replace('<meta name="claude:viewport-fit-sides" content="cover">\n', '')
head = head.replace('<title>Play Space Horas</title>', '''<title>Play Space Horas</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#DBE8DA">
<link rel="manifest" href="manifest.json">
<link rel="icon" href="img/icone-192.png">
<link rel="apple-touch-icon" href="img/icone-180.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Play Space">
<meta name="apple-mobile-web-app-status-bar-style" content="default">''')
html = ('<!doctype html>\n<html lang="pt-BR">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">\n'
        '<style>:root{padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}[hidden]{display:none!important}</style>\n'
        + head + '</head>\n<body>\n' + body.rstrip('\n') + '\n</body>\n</html>\n')
open(R + 'playspace/index.html', 'w', encoding='utf-8').write(html)
json.dump({
    'name': 'Play Space Horas', 'short_name': 'Play Space', 'description': 'Controle de horas, WhatsApp, agenda e esteira da Play Space',
    'lang': 'pt-BR', 'start_url': './', 'scope': './', 'display': 'standalone', 'background_color': '#FAF3E9', 'theme_color': '#DBE8DA',
    'icons': [{'src': 'img/icone-192.png', 'sizes': '192x192', 'type': 'image/png', 'purpose': 'any'},
              {'src': 'img/icone-512.png', 'sizes': '512x512', 'type': 'image/png', 'purpose': 'any'},
              {'src': 'img/icone-maskable-512.png', 'sizes': '512x512', 'type': 'image/png', 'purpose': 'maskable'}],
}, open(R + 'playspace/manifest.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
# a lista do que o atualizador baixa: tudo que está em playspace/ (menos o próprio versao.json, que ele lê antes)
arquivos = sorted(os.path.relpath(os.path.join(d, f), R + 'playspace').replace(os.sep, '/')
                  for d, _, fs in os.walk(R + 'playspace') for f in fs if f != 'versao.json' and not f.endswith('.novo'))
json.dump({'versao': versao, 'arquivos': arquivos}, open(R + 'playspace/versao.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('playspace v' + versao, len(arquivos), 'arquivos')
