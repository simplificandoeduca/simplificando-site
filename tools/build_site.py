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

# ---------- hub do dono (/hub): a mesma página do painel, que abre direto no hub (endereço simplificandoeduca.com/hub) ----------
# Usa o login e os arquivos do painel (pasta personal); só o nome, o ícone "H." e o manifesto são do hub.
h = open(R+'site/personal/index.html').read()
def troca(a, b):
    global h
    assert h.count(a) == 1, ('hub', h.count(a), a[:80])
    h = h.replace(a, b)
for s in ['fb/firebase-app-compat.js', 'fb/firebase-auth-compat.js', 'fb/firebase-firestore-compat.js', 'firebase-config.js']:
    troca('<script src="'+s+'"></script>', '<script src="../personal/'+s+'"></script>')
troca('<link rel="icon" type="image/png" sizes="192x192" href="icon-p-tab-192.png">', '<link rel="icon" type="image/png" sizes="192x192" href="icon-hub-192.png">')
troca('<link rel="apple-touch-icon" href="icon-p-180.png">', '<link rel="apple-touch-icon" href="icon-hub-180.png">')
troca('<meta name="apple-mobile-web-app-title" content="Personal">', '<meta name="apple-mobile-web-app-title" content="Hub">\n<meta name="robots" content="noindex">')
troca('<title>Simplificando · Painel do personal</title>', '<title>Hub · Simplificando</title>')
troca('<meta property="og:url" content="https://simplificandoeduca.com/personal/">', '<meta property="og:url" content="https://simplificandoeduca.com/hub/">')
os.makedirs(R+'site/hub', exist_ok=True)
open(R+'site/hub/index.html', 'w').write(h)
print('hub ok')

# ---------- app de teste (/app-teste): o mesmo app do aluno, sem servidor, entrando direto com o aluno de exemplo (Lucas) ----------
# Recebe toda atualização junto com o app de verdade. Os dados ficam só no aparelho, separados dos do app de verdade.
import shutil
t = open(R+'site/treino/index.html').read()
def rep(a, b, n=1):
    global t
    assert t.count(a) == n, ('app-teste', t.count(a), a[:80])
    t = t.replace(a, b)
for s in ['<script src="fb/firebase-app-compat.js"></script>\n', '<script src="fb/firebase-auth-compat.js"></script>\n', '<script src="fb/firebase-firestore-compat.js"></script>\n',
          '<script src="firebase-config.js"></script>\n', '<link rel="manifest" href="manifest.webmanifest">\n']:
    rep(s, '')
rep('<meta name="apple-mobile-web-app-title" content="Simplificando">', '<meta name="apple-mobile-web-app-title" content="App de teste">\n<meta name="robots" content="noindex">\n<script>window.SIMPLIFICANDO_FIREBASE = null;</script>')
rep('<title>Simplificando · Seu treino</title>', '<title>Simplificando · App de teste</title>')
rep("const TESTE_LOCAL = location.protocol==='file:' || /^(localhost|127\\.0\\.0\\.1)$/.test(location.hostname);", "const TESTE_LOCAL = true;")
t = re.sub(r"'simplificando-(conta|contas|pro-foto|pro-grade|pro-ui|pro-v2|reset|senhas|sessao)'", r"'simpteste-aluno-\1'", t)
rep("'simp-app-v'", "'simpteste-aluno-v'", 2)
rep("'simp-estado'", "'simpteste-aluno-estado'", 3)
rep("function boot(){\n", "function boot(){\n  if(!readSess()) writeSess({mode:'personal', code:'482913'}); // app de teste: entra direto com o aluno de exemplo, sem senha\n")
rep("  if(needAceite()){ LG.view = 'aceite';", "  if(needAceite() && ACCT.mode==='personal'){ S.aceite = aceiteNovo(false); save(); } // app de teste: termos já aceitos\n  if(needAceite()){ LG.view = 'aceite';")
rep("const EM_TESTE = false;", "const EM_TESTE = true;")
rep("const B3_ON = false, B3_TROCA = false;", "const B3_ON = true, B3_TROCA = true;") # boneco 3D do cardio: por enquanto só no app de teste
os.makedirs(R+'site/app-teste/b3', exist_ok=True)
for f in sorted(os.listdir(R+'src/b3')): shutil.copyfile(R+'src/b3/'+f, R+'site/app-teste/b3/'+f)
open(R+'site/app-teste/index.html', 'w').write(t)
for f in ['icon-180.png', 'icon-192.png', 'icon-512.png']: shutil.copyfile(R+'site/treino/'+f, R+'site/app-teste/'+f)
print('app-teste ok')
