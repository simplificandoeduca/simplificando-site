# =====================================================================
#  Play Space - ponte com o iDFace (Control iD)
#  Roda num computador do escritorio, na mesma rede do iDFace. A cada minuto:
#    1. entra no iDFace (login.fcgi) e le as batidas novas (load_objects access_logs)
#       e a lista de pessoas cadastradas no aparelho (load_objects users);
#    2. manda tudo para o Play Space (api.php?acao=ponte, com a chave da ponte);
#    3. se o Play Space pedir, cadastra no aparelho quem foi criado no site
#       (create_objects users); o rosto e cadastrado depois, no proprio aparelho.
#  Na primeira vez pergunta o endereco do site, a chave, o IP e o login do iDFace e guarda
#  em ponte-config.json (a senha do aparelho fica protegida pelo Windows, so este usuario le).
# =====================================================================
$ErrorActionPreference = 'Stop'
$VERSAO = '1.8.2'
$UMA_VEZ = $args -contains '-UmaVez'   # CONECTAR-IDFACE.bat: so liga o modo direto no aparelho, confirma com o site e fecha
$pasta = Split-Path -Parent $MyInvocation.MyCommand.Path
$arqConfig = Join-Path $pasta 'ponte-config.json'
$arqLog = Join-Path $pasta 'ponte-log.txt'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
trap { if ($TELA) { Mostrar-Janela 9 }; break } # erro inesperado com a tela aberta: a janela preta volta para mostrar o motivo
$TELA = $null   # no CONECTAR: a tela no navegador (usuario e senha do iDFace e o andamento), no lugar da janela preta

function Anotar($msg) {
  $linha = (Get-Date -Format 'dd/MM HH:mm:ss') + '  ' + $msg
  Write-Host $linha
  if ($TELA) { [void]$TELA.log.Add($linha); while ($TELA.log.Count -gt 60) { $TELA.log.RemoveAt(0) } }
  try {
    Add-Content -Path $arqLog -Value $linha -Encoding UTF8
    if ((Get-Item $arqLog).Length -gt 2MB) { Move-Item $arqLog ($arqLog + '.antigo') -Force }
  } catch { }
}


# ---------------------------------------------------------------------
#  A TELA DO CONECTAR: um servidorzinho so neste computador (127.0.0.1, porta e codigo sorteados) que abre uma pagina
#  no navegador. Ali a pessoa digita o IP, o usuario e a senha do iDFace e acompanha ate o "Conectado". Se nao der para
#  abrir (porta ocupada, sem navegador), segue tudo nesta janela, como antes.
# ---------------------------------------------------------------------
function Mostrar-Janela($modo) { # 6 minimiza esta janela preta; 9 traz de volta
  try {
    if (-not ('Gama.Janela' -as [type])) { Add-Type -Namespace Gama -Name Janela -MemberDefinition '[DllImport("user32.dll")] public static extern bool ShowWindow(System.IntPtr h, int c); [DllImport("kernel32.dll")] public static extern System.IntPtr GetConsoleWindow();' }
    [Gama.Janela]::ShowWindow([Gama.Janela]::GetConsoleWindow(), $modo) | Out-Null
  } catch { }
}
function Iniciar-Tela {
  try {
    $tcp = $null; $porta = 0
    foreach ($p in 47810..47830) { try { $t = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, $p); $t.Start(); $tcp = $t; $porta = $p; break } catch { } }
    if (-not $tcp) { return }
    $script:TELA = @{ tcp = $tcp; porta = $porta; tok = [Guid]::NewGuid().ToString('N'); visto = $false; fase = 'procurando'; ip = ''; usuario = 'admin'; log = (New-Object Collections.ArrayList); final = $null; login = $null }
    $url = 'http://127.0.0.1:' + $porta + '/' + $TELA.tok + '/'
    Start-Process $url
    Write-Host ''
    Write-Host 'Abri a tela de conexao no navegador: continue por la.' -ForegroundColor Green
    Write-Host ('Se fechar a pagina sem querer, abra de novo: ' + $url)
    Write-Host ''
  } catch {
    if ($script:TELA) { try { $script:TELA.tcp.Stop() } catch { } }
    $script:TELA = $null
  }
}
function Enviar-Tela($s, $cod, $tipo, $texto) {
  $b = [Text.Encoding]::UTF8.GetBytes([string]$texto)
  $st = if ($cod -eq 200) { '200 OK' } else { '404 Not Found' }
  $cab = [Text.Encoding]::ASCII.GetBytes('HTTP/1.1 ' + $st + "`r`nContent-Type: " + $tipo + "`r`nContent-Length: " + $b.Length + "`r`nCache-Control: no-store`r`nX-Content-Type-Options: nosniff`r`nConnection: close`r`n`r`n")
  $s.Write($cab, 0, $cab.Length); $s.Write($b, 0, $b.Length); $s.Flush()
}
function Estado-Tela { return (@{ fase = $TELA.fase; ip = $TELA.ip; usuario = $TELA.usuario; log = @($TELA.log); final = $TELA.final } | ConvertTo-Json -Depth 4 -Compress) }
function Responder-Tela($cli) {
  $cli.ReceiveTimeout = 1500; $cli.SendTimeout = 3000   # o navegador abre conexoes de reserva sem pedido: nao segura muito nelas
  $s = $cli.GetStream(); $buf = New-Object byte[] 32768; $tot = 0; $fimCab = -1; $txt = ''
  while ($fimCab -lt 0 -and $tot -lt $buf.Length) {
    $n = $s.Read($buf, $tot, $buf.Length - $tot); if ($n -le 0) { break }; $tot += $n
    $txt = [Text.Encoding]::ASCII.GetString($buf, 0, $tot); $fimCab = $txt.IndexOf("`r`n`r`n")
  }
  if ($fimCab -lt 0) { return }
  $linhas = $txt.Substring(0, $fimCab) -split "`r`n"
  $partes = $linhas[0] -split ' '; $metodo = $partes[0]; $caminho = ''; if ($partes.Count -gt 1) { $caminho = $partes[1] }
  $tam = 0; $hostPedido = ''
  foreach ($l in ($linhas | Select-Object -Skip 1)) { if ($l -match '^content-length:\s*(\d+)') { $tam = [int]$Matches[1] } elseif ($l -match '^host:\s*(\S+)') { $hostPedido = $Matches[1] } }
  $ini = $fimCab + 4
  while (($tot - $ini) -lt $tam -and $tot -lt $buf.Length) { $n = $s.Read($buf, $tot, $buf.Length - $tot); if ($n -le 0) { break }; $tot += $n }
  $corpo = ''; if ($tam -gt 0) { $corpo = [Text.Encoding]::UTF8.GetString($buf, $ini, [Math]::Min($tam, $tot - $ini)) }
  $raiz = '/' + $TELA.tok + '/'
  # so atende esta pagina: o endereco exato (127.0.0.1 e a porta) e o codigo sorteado no caminho
  if ($hostPedido -ne ('127.0.0.1:' + $TELA.porta) -or -not $caminho.StartsWith($raiz)) { Enviar-Tela $s 404 'text/plain' 'nao encontrado'; return }
  $rota = $caminho.Substring($raiz.Length)
  if (-not $TELA.visto) { $TELA.visto = $true; Mostrar-Janela 6 } # a pagina abriu: a janela preta sai da frente
  if ($metodo -eq 'GET' -and $rota -eq '') { Enviar-Tela $s 200 'text/html; charset=utf-8' $PAGINA; return }
  if ($metodo -eq 'GET' -and $rota -eq 'estado') { Enviar-Tela $s 200 'application/json; charset=utf-8' (Estado-Tela); return }
  if ($metodo -eq 'POST' -and $rota -eq 'login') { Enviar-Tela $s 200 'application/json; charset=utf-8' (Login-Tela $corpo); return }
  Enviar-Tela $s 404 'text/plain' 'nao encontrado'
}
function Atender($ms) { # atende a pagina por $ms milissegundos (no lugar de so esperar)
  if (-not $TELA) { Start-Sleep -Milliseconds $ms; return }
  $fim = (Get-Date).AddMilliseconds($ms)
  do {
    while ($TELA.tcp.Pending()) {
      $cli = $null
      try { $cli = $TELA.tcp.AcceptTcpClient(); Responder-Tela $cli } catch { } finally { if ($cli) { try { $cli.Close() } catch { } } }
    }
    Start-Sleep -Milliseconds 120
  } while ((Get-Date) -lt $fim)
}
function Preparar-Base($b) { # o endereco do iDFace; com https ele usa certificado proprio: aceita so para o endereco dele
  $script:base = $b
  if ($b -like 'https://*') {
    if (-not ('GamaCert' -as [type])) {
      Add-Type -TypeDefinition @"
using System.Net; using System.Net.Security; using System.Security.Cryptography.X509Certificates;
public static class GamaCert { public static string Host; public static bool Ok(object s, X509Certificate c, X509Chain ch, SslPolicyErrors e) { if (e == SslPolicyErrors.None) return true; var r = s as HttpWebRequest; return r != null && r.RequestUri.Host == Host; } }
"@
    }
    [GamaCert]::Host = ([Uri]$b).Host
    [Net.ServicePointManager]::ServerCertificateValidationCallback = [Net.Security.RemoteCertificateValidationCallback]{ param($s, $c, $ch, $e) [GamaCert]::Ok($s, $c, $ch, $e) }
  }
}
function Testar-Login($ip, $us, $se) { # entra no iDFace com o que foi digitado na pagina (8 s no maximo)
  $b = if ($ip -match '^https?://') { $ip.TrimEnd('/') } else { 'http://' + $ip.TrimEnd('/') }
  Preparar-Base $b
  $txt = ''; $cod = 0
  try {
    $req = [Net.HttpWebRequest]::Create($b + '/login.fcgi')
    $req.Method = 'POST'; $req.ContentType = 'application/json'; $req.Proxy = $null; $req.Timeout = 8000; $req.ReadWriteTimeout = 8000; $req.KeepAlive = $false
    $bytes = Json @{ login = $us; password = $se }; $req.ContentLength = $bytes.Length
    $st = $req.GetRequestStream(); $st.Write($bytes, 0, $bytes.Length); $st.Close()
    $resp = $null
    try { $resp = $req.GetResponse() } catch { $x = $_.Exception; while ($x -and -not ($x -is [Net.WebException])) { $x = $x.InnerException }; if ($x -and $x.Response) { $resp = $x.Response } else { throw } }
    $cod = [int]$resp.StatusCode
    $txt = (New-Object IO.StreamReader($resp.GetResponseStream())).ReadToEnd(); $resp.Close()
  } catch { return @{ ok = $false; cod = 'sem_resposta' } }
  $t = $txt.Trim()
  if ($t.StartsWith('{')) {
    $j = $null; try { $j = $t | ConvertFrom-Json } catch { }
    if ($j -and $j.session) {
      try { $lo = [Net.HttpWebRequest]::Create($b + '/logout.fcgi?session=' + $j.session); $lo.Method = 'POST'; $lo.ContentType = 'application/json'; $lo.Proxy = $null; $lo.Timeout = 5000; $lo.ReadWriteTimeout = 5000; $lo.KeepAlive = $false; $lo.ContentLength = 2; $w = $lo.GetRequestStream(); $w.Write([byte[]](123, 125), 0, 2); $w.Close(); $lo.GetResponse().Close() } catch { }
      return @{ ok = $true; cod = 'ok' }
    }
    if ($j -and ($j.error -or $cod -eq 401)) { return @{ ok = $false; cod = 'senha' } }
  }
  if ($cod -eq 401 -or $t -match 'Invalid login') { return @{ ok = $false; cod = 'senha' } }
  return @{ ok = $false; cod = 'nao_idface' }
}
function Login-Tela($corpo) {
  if ($TELA.fase -ne 'senha') { return '{"ok":false,"cod":"espera"}' }
  $j = $null; try { $j = $corpo | ConvertFrom-Json } catch { }
  if (-not $j) { return '{"ok":false,"cod":"espera"}' }
  $ip = ([string]$j.ip).Trim(); $us = ([string]$j.usuario).Trim(); $se = [string]$j.senha
  if (-not $us) { $us = 'admin' }
  if (-not ($ip -match '^(https?://)?[A-Za-z0-9.-]+(:\d+)?/?$')) { return '{"ok":false,"cod":"ip"}' }
  $r = Testar-Login $ip $us $se
  if ($r.ok) { $TELA.login = @{ ip = $ip; usuario = $us; senha = $se }; $TELA.ip = $ip; $TELA.usuario = $us; $TELA.fase = 'trabalhando'; Anotar ('Login ok com o iDFace em ' + $ip) }
  else { Anotar ('Tentativa de entrar no iDFace em ' + $ip + ': ' + $r.cod) }
  return ($r | ConvertTo-Json -Compress)
}
function Fim($ok, $cod, $msg) { # o fim do CONECTAR: avisa aqui e na pagina, e fecha
  Write-Host ''; Anotar $msg; Write-Host ''
  if ($TELA) {
    $TELA.final = @{ ok = [bool]$ok; cod = $cod; msg = $msg }
    Atender 6000   # a pagina pega o resultado
    try { $TELA.tcp.Stop() } catch { }
    if ($ok) { exit 2 }   # deu certo pela pagina: o .bat fecha esta janela sozinho
    Mostrar-Janela 9
  }
  if ($ok) { exit 0 } else { exit 1 }
}
$PAGINA = @'
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Play Space &#183; Conectar o iDFace</title>
<style>
:root{--bg:#F5F4FA;--card:#fff;--card2:#F2F1F8;--fg:#191823;--fg2:#55546A;--muted:#8C8AA0;--line:#E6E4EF;--roxo:#6D28D9;--roxo-soft:#EDE9FE;--verm:#F0524F;--ok:#059669;--ok-soft:#D1FAE5;--ok-txt:#065F46;--erro:#DC2626;--erro-soft:#FEE2E2;--erro-txt:#991B1B}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px 16px;background:radial-gradient(circle at 15% 0%,#FFE4E1 0,transparent 42%),radial-gradient(circle at 100% 100%,#EDE9FE 0,transparent 45%),var(--bg);font-family:Inter,"Segoe UI",system-ui,sans-serif;color:var(--fg)}
.card{width:min(440px,100%);background:var(--card);border-radius:28px;padding:28px;box-shadow:0 30px 80px rgba(25,24,35,.12)}
.topo{display:flex;align-items:center;gap:12px;margin-bottom:24px}
.logo{width:46px;height:46px;border-radius:15px;background:var(--verm);color:#fff;display:grid;place-items:center;font-weight:900;font-size:23px;box-shadow:0 8px 20px rgba(240,82,79,.3)}
.topo b{display:block;font-size:17px}.topo small{color:var(--muted);font-size:13px}
h1{font-size:22px;margin:0 0 6px;letter-spacing:-.01em}
p{margin:0 0 18px;color:var(--fg2);font-size:14.5px;line-height:1.45}
label{display:block;font-size:13px;font-weight:600;color:var(--fg2);margin:0 0 6px}
input{width:100%;height:48px;border:1.5px solid var(--line);border-radius:14px;padding:0 14px;font-size:16px;font-family:inherit;background:var(--card2);color:var(--fg);outline:none;margin-bottom:14px}
input:focus{border-color:var(--roxo);background:#fff}
.olho{position:relative}.olho input{padding-right:90px}
.olho span{position:absolute;right:12px;top:13px;font-size:13px;color:var(--roxo);font-weight:700;cursor:pointer;user-select:none}
button{width:100%;height:52px;border:0;border-radius:16px;background:var(--roxo);color:#fff;font-size:16px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 10px 24px rgba(109,40,217,.28)}
button:disabled{opacity:.6;cursor:default}
.msg{border-radius:14px;padding:12px 14px;font-size:14px;line-height:1.4;margin-bottom:14px;display:none}
.msg.erro{display:block;background:var(--erro-soft);color:var(--erro-txt)}
.msg.ok{display:block;background:var(--ok-soft);color:var(--ok-txt);font-weight:700}
.roda{width:46px;height:46px;border-radius:50%;border:4px solid var(--card2);border-top-color:var(--roxo);animation:g 1s linear infinite;margin:4px auto 18px}
@keyframes g{to{transform:rotate(360deg)}}
.grande{width:76px;height:76px;border-radius:26px;display:grid;place-items:center;margin:4px auto 16px;font-size:40px;font-weight:900}
.grande.ok{background:var(--ok-soft);color:var(--ok)}.grande.erro{background:var(--erro-soft);color:var(--erro)}
.centro{text-align:center}
.passos{list-style:none;padding:16px;margin:4px 0 0;display:grid;gap:12px;background:var(--card2);border-radius:18px}
.passos li{display:flex;gap:12px;align-items:center;font-size:14.5px;color:var(--muted)}
.passos i{width:26px;height:26px;border-radius:50%;flex:none;display:grid;place-items:center;font-style:normal;font-size:13px;font-weight:800;background:#fff;color:var(--muted)}
.passos li.feito{color:var(--fg)}.passos li.feito i{background:var(--ok-soft);color:var(--ok)}
.passos li.agora{color:var(--fg);font-weight:700}.passos li.agora i{background:var(--roxo-soft);color:var(--roxo)}
details{margin-top:18px;font-size:12.5px;color:var(--muted)}summary{cursor:pointer}
pre{white-space:pre-wrap;word-break:break-word;font-size:11.5px;background:var(--card2);border-radius:12px;padding:10px;max-height:180px;overflow:auto;margin:8px 0 0}
</style></head>
<body><main class="card">
<div class="topo"><span class="logo">G</span><div><b>Play Space</b><small>Conectar o rel&#243;gio de ponto (iDFace)</small></div></div>
<div id="c"><div class="centro"><div class="roda"></div><p>Abrindo&#8230;</p></div></div>
<details><summary>Detalhes t&#233;cnicos</summary><pre id="log"></pre></details>
</main>
<script>
var E = null, enviando = false, falhas = 0, desenhado = '';
function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
var MSG = {
  ip: 'Confira o IP do iDFace (ex.: 192.168.0.129). Ele aparece no aparelho: menu &#8594; Configura&#231;&#245;es &#8594; Rede.',
  sem_resposta: 'N&#227;o achei o iDFace nesse IP. Confira o n&#250;mero no aparelho (menu &#8594; Configura&#231;&#245;es &#8594; Rede) e se este computador est&#225; na mesma rede (mesmo roteador).',
  senha: 'O iDFace recusou o usu&#225;rio ou a senha. Use os mesmos de entrar no menu do aparelho.',
  nao_idface: 'Esse endere&#231;o respondeu, mas n&#227;o &#233; o iDFace. Confira o IP no aparelho (menu &#8594; Configura&#231;&#245;es &#8594; Rede).',
  espera: 'Aguarde um instante e tente de novo.'
};
var FIM = {
  ok: ['Conectado!', 'O iDFace j&#225; manda as batidas sozinho para o Play Space, pela internet. Pode fechar esta janela: este computador n&#227;o precisa de mais nada.'],
  desligado: ['A conex&#227;o est&#225; desligada no site', 'Em Play Space &#8594; Ajustes, ligue a chavinha &#8220;Conex&#227;o com o iDFace&#8221; e rode o arquivo de novo.'],
  recusou_direto: ['O iDFace n&#227;o aceitou o modo direto', 'Mande uma foto desta tela para o suporte.'],
  tempo: ['O site n&#227;o confirmou a tempo', 'Confira se o iDFace est&#225; no cabo, numa rede com internet, e rode o arquivo de novo. Se repetir, mande uma foto desta tela para o suporte.'],
  tempo_senha: ['Tempo esgotado', 'Ningu&#233;m entrou no iDFace em 15 minutos. Rode o arquivo &#8220;Conectar o iDFace&#8221; de novo.']
};
function passos(n) {
  var t = ['Achar o iDFace na rede', 'Entrar no iDFace', 'Ligar o modo direto', 'Confirmar com o Play Space'];
  return '<ul class="passos">' + t.map(function (x, i) { return '<li class="' + (i < n ? 'feito' : i === n ? 'agora' : '') + '"><i>' + (i < n ? '&#10003;' : i + 1) + '</i>' + x + '</li>'; }).join('') + '</ul>';
}
function tela() {
  var c = document.getElementById('c'), h = '';
  if (E.final) {
    if (desenhado === 'final') return;
    var f = FIM[E.final.cod] || [E.final.ok ? 'Conectado!' : 'N&#227;o deu', 'Mande uma foto desta tela para o suporte.'];
    h = '<div class="centro"><div class="grande ' + (E.final.ok ? 'ok' : 'erro') + '">' + (E.final.ok ? '&#10003;' : '!') + '</div><h1>' + f[0] + '</h1><p>' + f[1] + '</p></div>' + (E.final.ok ? passos(4) : '');
    desenhado = 'final';
  } else if (E.fase === 'procurando') {
    if (desenhado === 'procurando') return;
    h = '<div class="centro"><div class="roda"></div><h1>Procurando o iDFace&#8230;</h1><p>Olhando a rede deste computador. Leva alguns segundos.</p></div>' + passos(0);
    desenhado = 'procurando';
  } else if (E.fase === 'senha') {
    if (desenhado === 'senha') return;
    h = '<h1>Entre no iDFace</h1><p>' + (E.ip ? 'Achei o aparelho. Agora o usu&#225;rio e a senha do menu do iDFace.' : 'N&#227;o achei o aparelho sozinho: digite o IP dele (menu &#8594; Configura&#231;&#245;es &#8594; Rede).') + '</p>' +
      '<form id="f" autocomplete="off"><label for="ip">IP do iDFace</label><input id="ip" value="' + esc(E.ip) + '" placeholder="ex.: 192.168.0.129" inputmode="decimal">' +
      '<label for="us">Usu&#225;rio</label><input id="us" value="' + esc(E.usuario || 'admin') + '" autocapitalize="none" spellcheck="false">' +
      '<label for="se">Senha do iDFace</label><div class="olho"><input id="se" type="password"><span id="ver">mostrar</span></div>' +
      '<div class="msg" id="m" role="alert"></div><button id="b" type="submit">Entrar no iDFace</button></form>';
    desenhado = 'senha';
  } else {
    var n = /Modo direto ligado/.test((E.log || []).join('\n')) ? 3 : 2, k = 'trab' + n;
    if (desenhado === k) return;
    h = '<div class="msg ok">&#10003; Login ok com o iDFace' + (E.ip ? ' (' + esc(E.ip) + ')' : '') + '</div>' +
      '<div class="centro"><div class="roda"></div><h1>' + (n === 2 ? 'Ligando o modo direto&#8230;' : 'Confirmando com o Play Space&#8230;') + '</h1><p>O aparelho passa a mandar as batidas sozinho para o site. Leva at&#233; 2 minutos: deixe esta janela aberta.</p></div>' + passos(n);
    desenhado = k;
  }
  c.innerHTML = h;
  if (desenhado === 'senha') ligar();
}
function ligar() {
  var f = document.getElementById('f'), ip = document.getElementById('ip'), us = document.getElementById('us'), se = document.getElementById('se'), m = document.getElementById('m'), b = document.getElementById('b');
  document.getElementById('ver').onclick = function () { se.type = se.type === 'password' ? 'text' : 'password'; this.textContent = se.type === 'password' ? 'mostrar' : 'esconder'; };
  setTimeout(function () { (ip.value ? se : ip).focus(); }, 60);
  f.onsubmit = function (e) {
    e.preventDefault(); if (enviando) return;
    m.className = 'msg'; enviando = true; b.disabled = true; b.innerHTML = 'Entrando no iDFace&#8230;';
    fetch('login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ip: ip.value, usuario: us.value, senha: se.value }) })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        enviando = false;
        if (r.ok) { E.fase = 'trabalhando'; E.ip = ip.value; tela(); return; }
        m.className = 'msg erro'; m.innerHTML = MSG[r.cod] || 'N&#227;o deu: tente de novo.'; b.disabled = false; b.textContent = 'Entrar no iDFace'; se.select();
      })
      .catch(function () { enviando = false; m.className = 'msg erro'; m.innerHTML = 'O programa n&#227;o respondeu. A janela do programa foi fechada? Rode o arquivo de novo.'; b.disabled = false; b.textContent = 'Entrar no iDFace'; });
  };
}
function poll() {
  fetch('estado', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
    falhas = 0; E = j; document.getElementById('log').textContent = (j.log || []).join('\n'); tela();
    if (!j.final) setTimeout(poll, 1000);
  }).catch(function () {
    falhas++; if (E && E.final) return;
    if (falhas >= 8 && !enviando) { document.getElementById('c').innerHTML = '<div class="centro"><div class="grande erro">!</div><h1>O programa parou</h1><p>A janela do programa foi fechada antes de terminar. Rode o arquivo &#8220;Conectar o iDFace&#8221; de novo.</p></div>'; return; }
    setTimeout(poll, 1500);
  });
}
poll();
</script></body></html>

'@

# completa o ponte-config.json: o que veio pronto do site (endereco, chave, IP, usuario) nao e perguntado; a senha do aparelho
# e sempre digitada aqui e fica protegida pelo Windows (so este usuario do computador consegue ler)
# procura o iDFace na rede deste computador: quem responde na porta 80 e tem o login.fcgi da Control iD. Sem achar, devolve vazio
function Achar-IDFace {
  Write-Host 'Procurando o iDFace na rede deste computador (uns segundos)...' -ForegroundColor Cyan
  $redes = @()
  try { $redes = @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction Stop | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } | ForEach-Object { ($_.IPAddress -split '\.')[0..2] -join '.' } | Select-Object -Unique) } catch { }
  foreach ($rede in $redes) {
    $tentativas = @()
    foreach ($n in 1..254) { try { $cli = New-Object Net.Sockets.TcpClient; $tentativas += ,@(($rede + '.' + $n), $cli, $cli.ConnectAsync(($rede + '.' + $n), 80)) } catch { } }
    Start-Sleep -Milliseconds 1500
    $abertos = @($tentativas | Where-Object { $_[2].Status -eq 'RanToCompletion' -and $_[1].Connected } | ForEach-Object { $_[0] })
    foreach ($t in $tentativas) { try { $t[1].Close() } catch { } }
    foreach ($ip in $abertos) {
      $txt = ''
      try {
        $req = [Net.HttpWebRequest]::Create('http://' + $ip + '/login.fcgi')
        $req.Method = 'POST'; $req.ContentType = 'application/json'; $req.Proxy = $null; $req.Timeout = 3000; $req.ReadWriteTimeout = 3000
        $corpo = [Text.Encoding]::UTF8.GetBytes('{"login":"play-procura","password":"x"}'); $req.ContentLength = $corpo.Length
        $st = $req.GetRequestStream(); $st.Write($corpo, 0, $corpo.Length); $st.Close()
        $resp = $null
        try { $resp = $req.GetResponse() } catch { $x = $_.Exception; while ($x -and -not ($x -is [Net.WebException])) { $x = $x.InnerException }; if ($x) { $resp = $x.Response } }
        if ($resp) { $txt = (New-Object IO.StreamReader($resp.GetResponseStream())).ReadToEnd(); $resp.Close() }
      } catch { }
      $t2 = $txt.Trim() # so vale resposta de iDFace: JSON com session/error (a pagina de erro de um roteador tambem tem a palavra "error")
      if (($t2.StartsWith('{') -and $t2 -match '"session"|"error"') -or ($t2.Length -lt 200 -and $t2 -match 'Invalid login')) { Write-Host ('Achei o iDFace em ' + $ip) -ForegroundColor Green; return $ip }
    }
  }
  Write-Host 'Nao achei o iDFace sozinho. Vou perguntar o IP.' -ForegroundColor Yellow
  return $null
}

function Completar-Config {
  $c = [ordered]@{}
  if (Test-Path $arqConfig) { $j = Get-Content $arqConfig -Raw -Encoding UTF8 | ConvertFrom-Json; foreach ($p in $j.PSObject.Properties) { $c[$p.Name] = $p.Value } }
  $mudou = $false
  if (-not $TELA -and (-not $c.site -or -not $c.chave -or -not $c.ip -or -not $c.senha)) {
    Write-Host ''
    Write-Host 'Play Space - configurar a ponte com o iDFace' -ForegroundColor Magenta
    Write-Host 'Os dados do site estao em Play Space > Ajustes > iDFace e ponte.'
  }
  if (-not $c.site) { $c.site = (Read-Host 'Endereco da ponte (copie de Ajustes, termina em api.php)').Trim(); $mudou = $true }
  if (-not $c.chave) { $c.chave = (Read-Host 'Chave da ponte (copie de Ajustes)').Trim(); $mudou = $true }
  if ($TELA) { if (-not $c.usuario) { $c.usuario = 'admin' }; if (-not $c.nome) { $c.nome = 'recepcao' }; if (-not $c.Contains('ip')) { $c.ip = '' }; ($c | ConvertTo-Json) | Set-Content -Path $arqConfig -Encoding UTF8; return } # o resto vem da pagina
  if (-not $c.ip) { $achado = Achar-IDFace; if ($achado) { $c.ip = $achado; $mudou = $true } }
  while (-not $c.ip -or -not ($c.ip -match '^(https?://)?[A-Za-z0-9.-]+(:\d+)?/?$')) { # sem IP a ponte nao tem onde ler: pergunta ate vir um
    Write-Host 'O IP aparece no iDFace: menu > Configuracoes > Rede (ou na barra do navegador ao abrir o aparelho).' -ForegroundColor Yellow
    $c.ip = (Read-Host 'IP do iDFace na rede (ex.: 192.168.15.8)').Trim(); $mudou = $true
  }
  if (-not $c.usuario) { $u = (Read-Host 'Usuario do iDFace [admin]').Trim(); if (-not $u) { $u = 'admin' }; $c.usuario = $u; $mudou = $true }
  if (-not $c.senha) {
    Write-Host ("iDFace em " + $c.ip + ", usuario " + $c.usuario) -ForegroundColor Cyan
    $s = Read-Host 'Senha do iDFace (a do menu do aparelho)' -AsSecureString
    $c.senha = ConvertFrom-SecureString $s; $mudou = $true
  }
  if (-not $c.nome) { $c.nome = 'recepcao'; $mudou = $true }
  if ($mudou) { ($c | ConvertTo-Json) | Set-Content -Path $arqConfig -Encoding UTF8; Write-Host 'Guardado em ponte-config.json. Para mudar depois, apague esse arquivo (ou baixe o zip de novo) e abra a ponte.' -ForegroundColor Green }
}

if ($UMA_VEZ) { Iniciar-Tela }
Completar-Config
$cfg = Get-Content $arqConfig -Raw -Encoding UTF8 | ConvertFrom-Json
$senhaAparelho = ''
if ($cfg.senha) { $senhaAparelho = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR((ConvertTo-SecureString $cfg.senha))) }
$base = ''
Preparar-Base $(if ($cfg.ip -match '^https?://') { ([string]$cfg.ip).TrimEnd('/') } else { 'http://' + $cfg.ip })

# a virgula devolve os bytes inteiros (sem ela o PowerShell desmonta o byte[] num Object[] e o corpo sai errado)
function Json($obj) { return ,([Text.Encoding]::UTF8.GetBytes(($obj | ConvertTo-Json -Depth 8 -Compress))) }
# o iDFace fica na rede do escritorio: a chamada vai DIRETO (sem o proxy do Windows, que pode segurar enderecos locais ate estourar o tempo)
function Aparelho($caminho, $corpo) {
  $req = [Net.HttpWebRequest]::Create($base + '/' + $caminho)
  $req.Method = 'POST'; $req.ContentType = 'application/json'; $req.Proxy = $null; $req.Timeout = 45000; $req.ReadWriteTimeout = 45000; $req.KeepAlive = $false
  $bytes = Json $corpo; $req.ContentLength = $bytes.Length
  try { $s = $req.GetRequestStream(); $s.Write($bytes, 0, $bytes.Length); $s.Close(); $resp = $req.GetResponse() }
  catch {
    $er = $_.Exception; $web = $null; $x = $er
    while ($x) { if ($x -is [Net.WebException]) { $web = $x }; $m = $x.Message; $x = $x.InnerException } # o PowerShell embrulha o erro de rede; $m fica com o motivo de dentro
    if ($web -and $web.Response) { $txt = (New-Object IO.StreamReader($web.Response.GetResponseStream())).ReadToEnd(); throw ('o iDFace respondeu ' + [int]$web.Response.StatusCode + ': ' + $txt) }
    $st = if ($web) { [string]$web.Status } else { '' }
    if ($st -eq 'Timeout' -or $m -match 'time.?out|tempo limite') { throw ('o iDFace (' + $base + ') nao respondeu a tempo. Confira o IP no aparelho (menu > Configuracoes > Rede) e se este computador esta na mesma rede.') }
    if ($st -match 'ConnectFailure|NameResolution' -or $m -match 'refused|recusad|No such host|conhecido|unreachable|inacess') { throw ('nao consegui conectar no iDFace em ' + $base + '. O IP mudou? Abra esse endereco no navegador para conferir.') }
    throw ('iDFace em ' + $base + ': ' + $m)
  }
  $txt = (New-Object IO.StreamReader($resp.GetResponseStream())).ReadToEnd(); $resp.Close()
  if ($txt.Trim().StartsWith('{') -or $txt.Trim().StartsWith('[')) { return ($txt | ConvertFrom-Json) }
  return $txt
}
function Site($corpo) {
  return Invoke-RestMethod -Method Post -Uri $cfg.site -ContentType 'application/json; charset=utf-8' -Headers @{ 'X-Ponte-Chave' = $cfg.chave } -Body (Json $corpo) -TimeoutSec 30
}

# com a tela: acha o aparelho, espera o usuario e a senha na pagina (testados la) e guarda como a ponte guardaria
function Tela-Login {
  $procurar = -not $cfg.ip
  if ($cfg.ip) { # o IP salvo no site pode ser de outra rede (o aparelho mudou de lugar): se nao responder aqui, procura nesta rede
    $TELA.ip = [string]$cfg.ip
    $h = ([string]$cfg.ip) -replace '^https?://', '' -replace '[:/].*$', ''
    $alcanca = $false
    try { $tc = New-Object Net.Sockets.TcpClient; $ar = $tc.BeginConnect($h, 80, $null, $null); $alcanca = ($ar.AsyncWaitHandle.WaitOne(1500) -and $tc.Connected); $tc.Close() } catch { }
    if (-not $alcanca) { $procurar = $true; Anotar ('O IP salvo (' + $h + ') nao respondeu nesta rede: vou procurar o iDFace.') }
  }
  if ($procurar) { $TELA.fase = 'procurando'; Atender 400; $achado = Achar-IDFace; if ($achado) { $TELA.ip = $achado } }
  if ($cfg.usuario) { $TELA.usuario = [string]$cfg.usuario }
  $TELA.fase = 'senha'
  $inicio = Get-Date
  while (-not $TELA.login) {
    Atender 500
    if (((Get-Date) - $inicio).TotalMinutes -ge 15) { Fim $false 'tempo_senha' 'NAO DEU: ninguem entrou no iDFace pela pagina em 15 minutos. Rode este arquivo de novo.' }
  }
  $l = $TELA.login
  $cfg | Add-Member -NotePropertyName ip -NotePropertyValue $l.ip -Force
  $cfg | Add-Member -NotePropertyName usuario -NotePropertyValue $l.usuario -Force
  $sec = New-Object Security.SecureString; foreach ($ch in ([string]$l.senha).ToCharArray()) { $sec.AppendChar($ch) } # aceita senha vazia (ConvertTo-SecureString nao aceita)
  $cfg | Add-Member -NotePropertyName senha -NotePropertyValue (ConvertFrom-SecureString $sec) -Force
  $script:senhaAparelho = $l.senha
  $TELA.login = @{ ip = $l.ip; usuario = $l.usuario }   # a senha nao fica guardada em texto
  Preparar-Base $(if ($l.ip -match '^https?://') { $l.ip.TrimEnd('/') } else { 'http://' + $l.ip.TrimEnd('/') })
  ($cfg | ConvertTo-Json) | Set-Content -Path $arqConfig -Encoding UTF8
}
if ($TELA) { Tela-Login }

$ultimoLog = -1        # o maior numero de batida que o site ja tem (o site responde a cada volta)
$criados = @()         # pessoas que esta ponte cadastrou no aparelho (o site liga o numero na proxima volta)
$apagados = @()        # numeros que esta ponte apagou do aparelho (gente excluida no site)
Anotar "Ponte $VERSAO ligada: iDFace em $base, site $($cfg.site)"
# testa o login no aparelho; se ele recusar usuario/senha, pergunta de novo aqui mesmo (ate acertar) e guarda
while (-not $TELA) {
  $recusou = $false
  try {
    $t = Aparelho 'login.fcgi' @{ login = $cfg.usuario; password = $senhaAparelho }
    if ($t -is [string]) { # respondeu, mas nao como um iDFace (ex.: pagina de um roteador): o IP esta errado
      Anotar ('Teste do iDFace: ' + $base + ' nao respondeu como um iDFace (' + $t.Substring(0, [Math]::Min(80, $t.Length)).Trim() + ')')
      if ($UMA_VEZ) { Fim $false 'nao_idface' 'NAO DEU: esse endereco nao e o iDFace. Em Play Space > Ajustes, abra "Ele nao achou o aparelho sozinho? Informe o IP", salve o IP certo e rode este arquivo de novo.' }
      break
    }
    if ($t.session) { Anotar 'iDFace respondeu: login ok.'; try { Aparelho ("logout.fcgi?session=" + $t.session) @{} | Out-Null } catch { } } else { $recusou = $true }
  }
  catch { if ($_.Exception.Message -match '401|Invalid login') { $recusou = $true } else { Anotar ('Teste do iDFace: ' + $_.Exception.Message) } }
  if (-not $recusou) { break }
  Write-Host ''
  Write-Host 'O iDFace recusou o usuario ou a senha. Digite de novo (o mesmo login e senha que entram no aparelho pelo navegador).' -ForegroundColor Yellow
  $u = (Read-Host ('Usuario do iDFace [' + $cfg.usuario + ']')).Trim(); if ($u) { $cfg.usuario = $u }
  $sNova = Read-Host 'Senha do iDFace' -AsSecureString
  $cfg.senha = ConvertFrom-SecureString $sNova
  $senhaAparelho = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sNova))
  ($cfg | ConvertTo-Json) | Set-Content -Path $arqConfig -Encoding UTF8
}

# acerta o relogio do iDFace com a hora oficial do servidor (Brasilia), que o site manda em cada resposta
function Acertar-Relogio($agora, $q) {
  if (-not $agora -or -not ($agora -match '^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$')) { return }
  $c = @{ year = [int]$Matches[1]; month = [int]$Matches[2]; day = [int]$Matches[3]; hour = [int]$Matches[4]; minute = [int]$Matches[5]; second = [int]$Matches[6] }
  try { Aparelho ("set_system_time.fcgi$q") $c | Out-Null; Anotar ('Relogio do iDFace acertado: ' + $agora.Substring(11, 8)) }
  catch { Anotar ('Nao deu para acertar o relogio do iDFace: ' + $_.Exception.Message) }
}

function Desligado { # a chavinha "Conexao com o iDFace" esta desligada no site: o CONECTAR nao tem como concluir
  Fim $false 'desligado' 'NAO DEU: a Conexao com o iDFace esta DESLIGADA em Play Space > Ajustes. Ligue a chavinha no site e rode este arquivo de novo.'
}
$INTERVALO = 5     # segundos entre uma leitura e outra do iDFace (a batida chega no site em poucos segundos)
$AVISO = if ($UMA_VEZ) { 10 } else { 60 }   # mesmo sem batida nova, fala com o site a cada 60 s (no CONECTAR, a cada 10 s, para confirmar logo)
$inicio = Get-Date
if ($UMA_VEZ) { Anotar 'CONECTAR: vou ligar o modo direto no iDFace e conferir com o site. Leva ate 2 minutos.' }
$pushTentado = $false; $avisouDireto = $false; $avisouOk = $false; $sessao = $null; $ultimoSite = [datetime]::MinValue; $ultimoRelogio = [datetime]::MinValue; $ultimoErro = ''; $erroSite = [datetime]::MinValue; $primeira = $true
while ($true) {
  try {
    if (-not $sessao) { $sessao = (Aparelho 'login.fcgi' @{ login = $cfg.usuario; password = $senhaAparelho }).session; if (-not $sessao) { throw 'o iDFace nao devolveu a sessao (usuario ou senha do aparelho?)' } }
    $q = "?session=$sessao"
    # na primeira volta pergunta ao site de onde continuar e ja acerta o relogio
    if ($ultimoLog -lt 0) { $r0 = Site @{ acao = 'ponte'; aparelho = $cfg.nome; nome = $cfg.nome; versao = $VERSAO; batidas = @() }; if ($UMA_VEZ -and $r0.desligado) { Desligado }; $ultimoLog = [int]$r0.ultimo_log; Acertar-Relogio $r0.agora $q; $ultimoRelogio = Get-Date }
    $pedido = @{ object = 'access_logs'; where = @{ access_logs = @{ id = @{ '>' = $ultimoLog } } } }
    try { $logs = @((Aparelho ("load_objects.fcgi$q") $pedido).access_logs) }
    catch { if ($_.Exception.Message -match '401|sess') { throw } ; $logs = @((Aparelho ("load_objects.fcgi$q") @{ object = 'access_logs' }).access_logs | Where-Object { $_.id -gt $ultimoLog }) } # firmware sem filtro: le tudo e separa aqui
    $logs = @($logs | Where-Object { $_ })
    $falar = $logs.Count -gt 0 -or $criados.Count -gt 0 -or $apagados.Count -gt 0 -or ((Get-Date) - $ultimoSite).TotalSeconds -ge $AVISO
    if ($falar) {
      $envio = @{ acao = 'ponte'; aparelho = $cfg.nome; nome = $cfg.nome; versao = $VERSAO; criados = $criados; apagados = $apagados; ao_vivo = (-not $primeira)
        batidas = @($logs | Select-Object -First 2000 | ForEach-Object { @{ id = $_.id; time = $_.time; user_id = $_.user_id; event = $_.event } })
        usuarios = @((Aparelho ("load_objects.fcgi$q") @{ object = 'users' }).users | ForEach-Object { @{ id = $_.id; name = $_.name; registration = $_.registration } }) }
      $r = Site $envio
      if ($UMA_VEZ -and $r.desligado) { Desligado }
      $criados = @(); $apagados = @(); $ultimoSite = Get-Date; $primeira = $false
      $ultimoLog = [int]$r.ultimo_log
      if (-not $avisouOk) { $avisouOk = $true; if ($UMA_VEZ) { Anotar 'iDFace e site se falam por este computador. Agora ligo o modo direto no aparelho...' } else { Anotar 'TUDO CERTO: iDFace e site conectados. Pode minimizar esta janela (ou rodar o LIGAR-COM-O-WINDOWS.bat uma vez, para nem precisar abrir).' } }
      if ($r.novas -gt 0) { Anotar "$($r.novas) batida(s) nova(s) enviada(s)" }
      if ($ultimoErro) { Anotar 'Voltou a funcionar.'; $ultimoErro = '' }
      if (((Get-Date) - $ultimoRelogio).TotalMinutes -ge 60) { Acertar-Relogio $r.agora $q; $ultimoRelogio = Get-Date }
      # modo direto: o proprio iDFace passa a chamar o site pela internet (sem este computador). A ponte liga isso uma vez.
      if ($r.push_url -and -not $r.direto_ok -and -not $pushTentado) {
        $pushTentado = $true
        try { Aparelho ("set_configuration.fcgi$q") @{ push_server = @{ push_remote_address = [string]$r.push_url; push_request_period = '3'; push_request_timeout = '15000' } } | Out-Null; Anotar 'Modo direto ligado no iDFace: em ate 1 minuto ele passa a mandar as batidas sozinho para o site.' }
        catch {
          if ($UMA_VEZ) { Fim $false 'recusou_direto' ('NAO DEU: o iDFace recusou ligar o modo direto: ' + $_.Exception.Message + '. Mande uma foto desta janela para o suporte.') }
          Anotar ('Nao deu para ligar o modo direto no iDFace: ' + $_.Exception.Message + ' (a ponte continua funcionando normalmente)')
        }
      }
      if ($UMA_VEZ -and $r.direto_ok) { Fim $true 'ok' 'CONCLUIDO: o iDFace ja manda as batidas sozinho para o site. Pode fechar esta janela; este computador nao precisa de mais nada.' }
      if ($r.direto_ok -and -not $avisouDireto) { $avisouDireto = $true; Write-Host ''; Anotar 'PRONTO: o iDFace ja fala direto com o site. Esta janela pode ser fechada; o computador nao precisa mais ficar ligado.'; Write-Host '' }
      foreach ($ap in @($r.apagar)) { # excluido no site: apaga o cadastro (e o rosto) no aparelho; erro tambem tira da fila, para nao travar
        if (-not $ap) { continue }
        try { Aparelho ("destroy_objects.fcgi$q") @{ object = 'users'; where = @{ users = @{ id = [int]$ap } } } | Out-Null; Anotar "Apagado do iDFace: numero $ap" }
        catch { Anotar "Nao consegui apagar o numero $ap do iDFace: $($_.Exception.Message)" }
        $apagados += [int]$ap
      }
      foreach ($p in @($r.criar)) {
        if (-not $p) { continue }
        try {
          $res = Aparelho ("create_objects.fcgi$q") @{ object = 'users'; values = @(@{ name = $p.nome; registration = $p.matricula }) }
          $novoId = @($res.ids)[0]
          if ($novoId) { $criados += @{ pessoa_id = $p.pessoa_id; user_id = $novoId }; Anotar "Cadastrado no iDFace: $($p.nome) (numero $novoId). Falta o rosto: cadastre no aparelho." }
        } catch { Anotar "Nao consegui cadastrar $($p.nome) no iDFace: $($_.Exception.Message)" }
      }
    }
  } catch {
    $ex = $_.Exception; $erro = $ex.Message
    while ($ex.InnerException) { $ex = $ex.InnerException; $erro += ' / ' + $ex.Message } # o motivo de verdade costuma vir por dentro (ex.: conexao recusada)
    if ($sessao) { try { Aparelho "logout.fcgi?session=$sessao" @{} | Out-Null } catch { } }
    $sessao = $null # na proxima volta entra de novo no aparelho
    if ($erro -ne $ultimoErro) { Anotar "ERRO: $erro"; $ultimoErro = $erro } # o mesmo erro nao enche a janela a cada 5 s
    if (((Get-Date) - $erroSite).TotalSeconds -ge $AVISO) { try { $re = Site @{ acao = 'ponte'; aparelho = $cfg.nome; nome = $cfg.nome; versao = $VERSAO; batidas = @(); erro = $erro; criados = $criados }; $criados = @(); $erroSite = Get-Date
      # este computador nao alcanca o iDFace (ex.: Wi-Fi separado do cabo), mas o aparelho ja manda sozinho pela internet: esta tudo bem
      if ($UMA_VEZ -and $re.direto_ok) { Fim $true 'ok' 'CONCLUIDO: o iDFace ja manda as batidas sozinho para o site (este computador nao alcanca o aparelho, mas nem precisa). Pode fechar esta janela.' }
      if ($UMA_VEZ -and $re.desligado) { Desligado }
      if ($re.direto_ok -and -not $avisouDireto) { $avisouDireto = $true; Write-Host ''; Anotar 'MAS ESTA TUDO BEM: o iDFace esta mandando as batidas DIRETO para o site (modo direto). As batidas continuam chegando; este computador nem precisa da ponte.'; Write-Host '' }
    } catch { } } # o site mostra o erro em Ajustes
  }
  if ($UMA_VEZ -and ((Get-Date) - $inicio).TotalMinutes -ge 4) {
    if ($ultimoErro) { Anotar ('Ultimo erro: ' + $ultimoErro) }
    Fim $false 'tempo' 'NAO DEU: em 4 minutos o site nao confirmou o modo direto. Confira se o iDFace esta no cabo com internet e tente de novo; se repetir, mande uma foto desta janela para o suporte.'
  }
  Atender ($INTERVALO * 1000)
}
