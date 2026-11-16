# Junta o código do app (src/) com a "casca" de cada pasta do site (site/*/index.html).
# Uso: python3 tools/build_site.py  (rodar na raiz do repositório)
import os
R = os.path.dirname(os.path.dirname(os.path.abspath(__file__))) + '/'
import re
# versão única do app do aluno e do painel do personal: muda só no VERSAO.txt
V = open(R+'VERSAO.txt').read().strip()
# o app do aluno fica dividido em partes (src/app/); a versão mora na parte que tiver "const APPV"
ARQS = {'APPV': ['src/treino-grade.html'] + ['src/app/'+n for n in sorted(os.listdir(R+'src/app'))] if os.path.isdir(R+'src/app') else ['src/treino-grade.html'], 'PAINELV': ['src/admin.html']}
for const, lista in ARQS.items():
    for f in lista:
        t = open(R+f).read()
        if 'const '+const+" = '" not in t: continue
        t2 = re.sub(r"const "+const+r" = '[^']*'", "const "+const+" = '"+V+"'", t, count=1)
        t2 = re.sub(r"// Versão do painel: .*", "// Versão do painel: "+V, t2, count=1)
        if t2 != t: open(R+f, 'w').write(t2)
        break
# junta as partes: cada linha <!--#parte arquivo--> vira o conteúdo de src/app/arquivo (na mesma ordem, nada muda no site)
def junta(f):
    return re.sub(r'<!--#parte ([\w.-]+)-->\n', lambda m: open(R+'src/app/'+m.group(1)).read(), open(R+'src/'+f).read())
sw = R+'site/treino/sw.js'; t = open(sw).read(); t2 = re.sub(r"const VERSAO = '[^']*'", "const VERSAO = '"+V+"'", t, count=1)
if t2 != t: open(sw, 'w').write(t2)
print('versão', V)
for folder, src, title in [('treino','treino-grade.html','Simplificando · Seu treino'), ('personal','admin.html','Simplificando · Painel do personal')]:
    idx = R+'site/'+folder+'/index.html'
    cur = open(idx).read(); tag = '<title>'+title+'</title>'
    pre = cur[:cur.index(tag)]
    app = junta(src); app = app[app.index(tag):]
    open(idx,'w').write(pre + app.rstrip('\n') + '\n</body>\n</html>\n')
    print(folder, 'ok')
