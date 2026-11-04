# Junta o código do app (src/) com a "casca" de cada pasta do site (site/*/index.html).
# Uso: python3 tools/build_site.py  (rodar na raiz do repositório)
import os
R = os.path.dirname(os.path.dirname(os.path.abspath(__file__))) + '/'
for folder, src, title in [('treino','treino-grade.html','Simplificando Treino Grade'), ('personal','admin.html','Painel do Personal')]:
    idx = R+'site/'+folder+'/index.html'
    cur = open(idx).read(); tag = '<title>'+title+'</title>'
    pre = cur[:cur.index(tag)]
    app = open(R+'src/'+src).read(); app = app[app.index(tag):]
    open(idx,'w').write(pre + app.rstrip('\n') + '\n</body>\n</html>\n')
    print(folder, 'ok')
