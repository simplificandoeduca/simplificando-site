<?php
/* =====================================================================
   Play Space 2.70 — Insta: as conversas do Instagram e do WhatsApp da loja num lugar só, para o gestor ver (daqui não se responde: a
   equipe continua respondendo no celular, como sempre). Pedido do dono: "um módulo tipo notas ... fazer um traqueamento com o Instagram,
   de maneira com que eu conecte a minha conta e eu consiga visualizar as mensagens e receba notificação ... e também já a parte de conectar
   o WhatsApp para que a gente visualize também o WhatsApp".
   - Instagram: a API oficial da Meta (Instagram API com o login do Instagram). O gestor cria o app na Meta (grátis), põe aqui o ID e a chave
     secreta do app e conecta cada conta profissional pelo botão (o Instagram pergunta se libera). As mensagens chegam pelo webhook, na hora,
     e por uma conferência de tempos em tempos (a lista de conversas da API, com as mensagens mais novas de cada uma).
   - WhatsApp: pela Evolution API (como o WhatsApp Web: lê o QR Code no celular da loja), num servidor à parte. Cada número da loja é uma
     instância; o webhook traz cada mensagem (do cliente e da loja) e, ao conectar, as conversas antigas.
   - Aviso no celular do gestor (os mesmos avisos dos pedidos de autorização): quando um cliente começa uma conversa (a primeira mensagem,
     ou a primeira depois de um dia sem falar). Não apita a cada mensagem.
   Os segredos (a chave do app, os acessos das contas, a chave da Evolution) ficam no banco do Play Space, fora do site; a tela só vê o fim.
   ===================================================================== */
declare(strict_types=1);
require_once __DIR__ . '/whats-lib.php';

const IG_GRAPH = 'https://graph.instagram.com/v23.0';
const IG_TOKEN = 'https://api.instagram.com/oauth/access_token';
const IG_AUTORIZA = 'https://www.instagram.com/oauth/authorize';
const IG_ESCOPO = 'instagram_business_basic,instagram_business_manage_messages';
const CV_UM_DIA = 86400;
const CV_PADRAO = ['avisos' => true];

/* ---------- as tabelas ---------- */
function cvTabelas(): void {
  static $ok = false; if ($ok) return; $ok = true; $db = db();
  $db->exec("CREATE TABLE IF NOT EXISTS cv_conversas (id INTEGER PRIMARY KEY, canal TEXT NOT NULL, conta TEXT NOT NULL, contato TEXT NOT NULL, nome TEXT NOT NULL DEFAULT '',
    usuario TEXT NOT NULL DEFAULT '', foto TEXT NOT NULL DEFAULT '', tel TEXT NOT NULL DEFAULT '', ps_id INTEGER, ultima TEXT NOT NULL DEFAULT '', ultima_de TEXT NOT NULL DEFAULT '',
    ultima_em TEXT, nao_lidas INTEGER NOT NULL DEFAULT 0, cliente_em TEXT, loja_em TEXT, criado_em TEXT NOT NULL, ext TEXT NOT NULL DEFAULT '', UNIQUE (canal, conta, contato))");
  $db->exec('CREATE INDEX IF NOT EXISTS cv_conversas_ultima ON cv_conversas (ultima_em)');
  $db->exec("CREATE TABLE IF NOT EXISTS cv_mensagens (id INTEGER PRIMARY KEY, conversa_id INTEGER NOT NULL, ext_id TEXT NOT NULL, de TEXT NOT NULL, texto TEXT NOT NULL DEFAULT '',
    tipo TEXT NOT NULL DEFAULT 'texto', midia TEXT NOT NULL DEFAULT '', quando TEXT NOT NULL, UNIQUE (conversa_id, ext_id))");
  $db->exec('CREATE INDEX IF NOT EXISTS cv_mensagens_conversa ON cv_mensagens (conversa_id, quando)');
}
function cvCfg(): array { $c = cfg('conversas', null); return (is_array($c) ? $c : []) + CV_PADRAO; }
function cvTxt(string $s, int $max): string { $s = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s)); return mb_substr($s, 0, $max); }
function cvQuando(int $ts): string { return date('Y-m-d H:i:s', $ts); }
function cvTsDe($v): int {   /* a hora vinda de fora: segundos, milissegundos ou texto ISO */
  if (is_numeric($v)) { $n = (int)$v; return $n > 20000000000 ? intdiv($n, 1000) : $n; }
  $t = strtotime((string)$v); return $t ?: agoraTs();
}

/* ---------- guardar uma mensagem (de onde vier) ----------
   $m: ext (id da mensagem lá fora), de ('cliente' | 'loja'), texto, tipo (texto, foto, video, audio, arquivo, figurinha, outro), midia (endereço),
   ts (a hora, em segundos). $info: nome, usuario, foto, tel do contato. $aovivo: chegou agora (conta como não lida e pode avisar); a
   importação do histórico não avisa nem marca. Devolve o id da conversa (ou 0 se a mensagem já estava guardada). */
$GLOBALS['CV_AVISOS'] = [];
function cvGuardar(string $canal, string $conta, string $contato, array $m, array $info = [], bool $aovivo = true): int {
  cvTabelas(); $db = db(); $contato = cvTxt($contato, 120); if ($contato === '') return 0;
  $q = $db->prepare('SELECT * FROM cv_conversas WHERE canal = ? AND conta = ? AND contato = ?'); $q->execute([$canal, $conta, $contato]); $cv = $q->fetch();
  $nome = cvTxt((string)($info['nome'] ?? ''), 120); $usu = cvTxt((string)($info['usuario'] ?? ''), 80); $foto = cvTxt((string)($info['foto'] ?? ''), 900); $tel = preg_replace('/\D/', '', (string)($info['tel'] ?? ''));
  if (!$cv) {
    /* o WhatsApp de um cliente do Play Space (pelo telefone do cadastro): o nome do cadastro no lugar do nome do perfil */
    $ps = null; if ($canal === 'wa' && $tel !== '') { try { $c = criancaPorTelefone($tel); if ($c) { $ps = (int)$c['id']; $nome = (string)($c['resp'] ?: $c['nome']); } } catch (Throwable $e) { } }
    $db->prepare('INSERT OR IGNORE INTO cv_conversas (canal, conta, contato, nome, usuario, foto, tel, ps_id, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$canal, $conta, $contato, $nome, $usu, $foto, $tel, $ps, agora()]);
    $q->execute([$canal, $conta, $contato]); $cv = $q->fetch(); if (!$cv) return 0;
  } else {
    $trocaNome = $nome !== '' && $nome !== $cv['nome'] && ($cv['nome'] === '' || (($m['de'] ?? '') === 'cliente' && $cv['ps_id'] === null));   /* (o nome do cadastro do Play Space fica) */
    if ($trocaNome || ($usu !== '' && $usu !== $cv['usuario']) || ($foto !== '' && $foto !== $cv['foto']))
      $db->prepare('UPDATE cv_conversas SET nome = ?, usuario = ?, foto = ? WHERE id = ?')->execute([$trocaNome ? $nome : $cv['nome'], $usu !== '' ? $usu : $cv['usuario'], $foto !== '' ? $foto : $cv['foto'], (int)$cv['id']]);
  }
  $de = ($m['de'] ?? '') === 'loja' ? 'loja' : 'cliente'; $ts = (int)($m['ts'] ?? agoraTs()); $quando = cvQuando($ts);
  $texto = cvTxt((string)($m['texto'] ?? ''), 4000); $tipo = preg_match('/^(texto|foto|video|audio|arquivo|figurinha|outro)$/', (string)($m['tipo'] ?? '')) ? (string)$m['tipo'] : 'texto';
  if ($texto === '' && $tipo === 'texto') $tipo = 'outro';
  $ext = cvTxt((string)($m['ext'] ?? ''), 200); if ($ext === '') $ext = 'h' . substr(sha1($de . '|' . $texto . '|' . $ts), 0, 24);
  $ins = $db->prepare('INSERT OR IGNORE INTO cv_mensagens (conversa_id, ext_id, de, texto, tipo, midia, quando) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $ins->execute([(int)$cv['id'], $ext, $de, $texto, $tipo, cvTxt((string)($m['midia'] ?? ''), 900), $quando]);
  if ($ins->rowCount() < 1) return 0;
  $resumo = $texto !== '' ? $texto : ['foto' => '📷 Foto', 'video' => '🎬 Vídeo', 'audio' => '🎤 Áudio', 'arquivo' => '📎 Arquivo', 'figurinha' => 'Figurinha', 'outro' => 'Mensagem'][$tipo];
  $sets = []; $vals = [];
  if ($cv['ultima_em'] === null || $quando >= (string)$cv['ultima_em']) { $sets[] = 'ultima = ?'; $vals[] = mb_substr($resumo, 0, 300); $sets[] = 'ultima_de = ?'; $vals[] = $de; $sets[] = 'ultima_em = ?'; $vals[] = $quando; }
  if ($de === 'cliente') {
    if ($cv['cliente_em'] === null || $quando > (string)$cv['cliente_em']) { $sets[] = 'cliente_em = ?'; $vals[] = $quando; }
    if ($aovivo) { $sets[] = 'nao_lidas = nao_lidas + 1';
      /* o aviso: conversa nova (nunca falou) ou a primeira mensagem depois de um dia sem falar; só da mensagem que acabou de chegar (até 2 h) */
      $antes = $cv['cliente_em'] === null ? 0 : (int)strtotime((string)$cv['cliente_em']);
      if ((!$antes || $ts - $antes >= CV_UM_DIA) && agoraTs() - $ts < 7200) $GLOBALS['CV_AVISOS'][] = ['id' => (int)$cv['id'], 'nova' => !$antes, 'resumo' => $resumo];
    }
  } elseif ($cv['loja_em'] === null || $quando > (string)$cv['loja_em']) { $sets[] = 'loja_em = ?'; $vals[] = $quando; }
  if ($sets) { $vals[] = (int)$cv['id']; $db->prepare('UPDATE cv_conversas SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals); }
  return (int)$cv['id'];
}
/* depois da resposta (quem pediu não espera): solta a trava de escrita do Play Space antes (a conferência pode levar uns segundos) */
function cvDepois(callable $f): void {
  require_once __DIR__ . '/push-lib.php';
  pushDepois(function () use ($f) { if (isset($GLOBALS['_trava'])) { @flock($GLOBALS['_trava'], LOCK_UN); @fclose($GLOBALS['_trava']); unset($GLOBALS['_trava']); } $f(); });
}
/* os avisos juntados neste pedido saem depois da resposta */
function cvAvisarDepois(): void {
  if (!$GLOBALS['CV_AVISOS'] || !empty($GLOBALS['CV_AVISOS_MARCADO'])) return; $GLOBALS['CV_AVISOS_MARCADO'] = true;
  cvDepois(function () { $L = $GLOBALS['CV_AVISOS']; $GLOBALS['CV_AVISOS'] = []; foreach ($L as $a) cvAvisar($a); });
}
function cvAvisar(array $a): array {
  if (empty(cvCfg()['avisos'])) return ['ok' => 0];
  require_once __DIR__ . '/push-lib.php';
  $q = db()->prepare('SELECT * FROM cv_conversas WHERE id = ?'); $q->execute([$a['id']]); $cv = $q->fetch(); if (!$cv) return ['ok' => 0];
  $L = array_values(array_filter(db()->query('SELECT i.*, u.papel FROM push_inscricoes i JOIN usuarios u ON u.id = i.usuario_id AND u.ativo = 1 ORDER BY i.id')->fetchAll(), fn($i) => (string)$i['papel'] === 'gestor'));
  if (!$L) return ['ok' => 0];
  $quem = cvNome($cv); $onde = cvContaNome((string)$cv['canal'], (string)$cv['conta']);
  $t = ($cv['canal'] === 'ig' ? 'Instagram' : 'WhatsApp') . ($onde !== '' ? ' · ' . $onde : '') . ($a['nova'] ? ' · conversa nova' : ' · voltou a falar');
  return pushMandar($L, fn($i) => ['t' => $t, 'b' => mb_substr($quem . ': ' . $a['resumo'], 0, 180), 'tag' => 'cv-' . (int)$cv['id'], 'url' => './insta/#c=' . (int)$cv['id']]);
}
function cvNome(array $cv): string {
  if ($cv['nome'] !== '') return (string)$cv['nome'];
  if ($cv['usuario'] !== '') return '@' . $cv['usuario'];
  $t = (string)$cv['tel']; if (strlen($t) >= 12 && substr($t, 0, 2) === '55') { $d = substr($t, 2); return '(' . substr($d, 0, 2) . ') ' . (strlen($d) === 11 ? substr($d, 2, 1) . ' ' . substr($d, 3, 4) . '-' . substr($d, 7) : substr($d, 2, 4) . '-' . substr($d, 6)); }
  return $t !== '' ? '+' . $t : 'Cliente';
}
function cvContaNome(string $canal, string $conta): string {
  if ($canal === 'ig') { foreach (igCfg()['contas'] as $c) if ((string)$c['id'] === $conta) return '@' . $c['usuario']; return ''; }
  foreach (zapCfg()['contas'] as $c) if ((string)$c['id'] === $conta) return (string)$c['nome']; return '';
}
function cvPublica(array $c): array {
  return ['id' => (int)$c['id'], 'canal' => (string)$c['canal'], 'conta' => (string)$c['conta'], 'nome' => cvNome($c), 'usuario' => (string)$c['usuario'], 'foto' => (string)$c['foto'],
    'tel' => (string)$c['tel'], 'cliente' => $c['ps_id'] !== null, 'ultima' => (string)$c['ultima'], 'ultimaDe' => (string)$c['ultima_de'], 'em' => (string)($c['ultima_em'] ?? $c['criado_em']),
    'naoLidas' => (int)$c['nao_lidas'], 'semResposta' => $c['cliente_em'] !== null && ($c['loja_em'] === null || (string)$c['loja_em'] < (string)$c['cliente_em'])];
}

/* ==========================================================
   INSTAGRAM (a API oficial)
   ========================================================== */
function igCfg(): array {
  $c = cfg('insta', null); $c = (is_array($c) ? $c : []) + ['app_id' => '', 'segredo' => '', 'verificar' => '', 'contas' => [], 'graph' => '', 'token_url' => '', 'autoriza' => ''];
  if ($c['verificar'] === '') { $c['verificar'] = bin2hex(random_bytes(10)); gravarCfg('insta', $c); }
  if (!is_array($c['contas'])) $c['contas'] = [];
  return $c;
}
function igGravar(array $c): void { gravarCfg('insta', $c); }
function igGraph(array $c): string { return rtrim($c['graph'] !== '' ? $c['graph'] : IG_GRAPH, '/'); }   /* (o endereço muda só nos testes) */
function igRetornoUrl(): string { return urlBase() . '/api/insta-retorno.php'; }
function igWebhookUrl(): string { return urlBase() . '/api/insta-webhook.php'; }
function igPublico(array $c): array {
  return ['appId' => $c['app_id'], 'temSegredo' => $c['segredo'] !== '', 'segredo' => mascara((string)$c['segredo']), 'retorno' => igRetornoUrl(), 'webhook' => igWebhookUrl(), 'verificar' => $c['verificar'],
    'contas' => array_map(fn($x) => ['id' => (string)$x['id'], 'usuario' => (string)$x['usuario'], 'nome' => (string)($x['nome'] ?? ''), 'foto' => (string)($x['foto'] ?? ''),
      'conectadoEm' => (string)($x['conectado_em'] ?? ''), 'sincronizadoEm' => (string)($x['sync_em'] ?? ''), 'webhookEm' => (string)($x['webhook_em'] ?? ''),
      'expira' => (string)($x['expira'] ?? ''), 'erro' => (string)($x['erro'] ?? '')], array_values($c['contas']))];
}
/* uma chamada à Meta: GET com os parâmetros na URL, ou POST de formulário */
function igHttp(string $metodo, string $url, array $campos = [], int $tempo = 20): array {
  $ch = curl_init($metodo === 'GET' && $campos ? $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($campos) : $url);
  $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $tempo, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_HTTPHEADER => ['Accept: application/json']];
  if ($metodo !== 'GET') { $op[CURLOPT_POST] = true; $op[CURLOPT_POSTFIELDS] = http_build_query($campos); }
  curl_setopt_array($ch, $op); $r = curl_exec($ch);
  if ($r === false) { $e = curl_error($ch); curl_close($ch); throw new RuntimeException('Não consegui falar com o Instagram (' . $e . ').'); }
  $st = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch); $j = json_decode((string)$r, true);
  return [$st, is_array($j) ? $j : [], (string)$r];
}
function igErro(array $j, int $st): string {
  $m = (string)($j['error']['message'] ?? $j['error_message'] ?? $j['error']['error_user_msg'] ?? '');
  return 'O Instagram respondeu com erro ' . $st . ($m !== '' ? ': ' . mb_substr($m, 0, 240) : '.');
}
function igGet(array $c, string $caminho, array $campos, string $token): array {
  [$st, $j] = igHttp('GET', igGraph($c) . '/' . ltrim($caminho, '/'), $campos + ['access_token' => $token]);
  if ($st >= 400) throw new RuntimeException(igErro($j, $st));
  return $j;
}
function igAutorizarUrl(array $c, string $estado): string {
  return ($c['autoriza'] !== '' ? $c['autoriza'] : IG_AUTORIZA) . '?' . http_build_query(['client_id' => $c['app_id'], 'redirect_uri' => igRetornoUrl(), 'response_type' => 'code', 'scope' => IG_ESCOPO, 'state' => $estado, 'enable_fb_login' => 0, 'force_reauth' => 1]);
}
/* o código da volta do Instagram vira o acesso da conta (o curto, trocado pelo de 60 dias) e a conta entra na lista */
function igConectar(string $codigo): array {
  $c = igCfg(); if ($c['app_id'] === '' || $c['segredo'] === '') throw new RuntimeException('Falta o ID e a chave secreta do app da Meta (Insta → Contas).');
  [$st, $j] = igHttp('POST', $c['token_url'] !== '' ? $c['token_url'] : IG_TOKEN, ['client_id' => $c['app_id'], 'client_secret' => $c['segredo'], 'grant_type' => 'authorization_code', 'redirect_uri' => igRetornoUrl(), 'code' => preg_replace('/#_$/', '', $codigo)]);
  $d = isset($j['data'][0]) ? $j['data'][0] : $j; $curto = (string)($d['access_token'] ?? '');
  if ($st >= 400 || $curto === '') throw new RuntimeException(igErro($j, $st));
  [$st, $l] = igHttp('GET', preg_replace('~/v[0-9.]+$~', '', igGraph($c)) . '/access_token', ['grant_type' => 'ig_exchange_token', 'client_secret' => $c['segredo'], 'access_token' => $curto]);
  $token = (string)($l['access_token'] ?? ''); if ($st >= 400 || $token === '') throw new RuntimeException(igErro($l, $st));
  $p = igGet($c, 'me', ['fields' => 'id,user_id,username,name,profile_picture_url'], $token);
  $id = (string)($p['user_id'] ?? $p['id'] ?? ''); if ($id === '') throw new RuntimeException('O Instagram não mandou o número da conta.');
  $conta = ['id' => $id, 'app_id' => (string)($p['id'] ?? ''), 'usuario' => (string)($p['username'] ?? ''), 'nome' => (string)($p['name'] ?? ''), 'foto' => (string)($p['profile_picture_url'] ?? ''),
    'token' => $token, 'expira' => date('Y-m-d H:i:s', agoraTs() + (int)($l['expires_in'] ?? 5184000)), 'conectado_em' => agora(), 'sync_em' => '', 'sync_ts' => 0, 'erro' => ''];
  /* a conta passa a mandar as mensagens para o nosso webhook */
  try { [$st, $r] = igHttp('POST', igGraph($c) . '/me/subscribed_apps', ['subscribed_fields' => 'messages,messaging_seen', 'access_token' => $token]); if ($st >= 400) $conta['erro'] = 'Webhook: ' . igErro($r, $st); } catch (Throwable $e) { $conta['erro'] = $e->getMessage(); }
  $c = igCfg(); $c['contas'] = array_values(array_filter($c['contas'], fn($x) => (string)$x['id'] !== $id)); $c['contas'][] = $conta; igGravar($c);
  return $conta;
}
/* o acesso dura 60 dias: renova quando faltam menos de 15 (a renovação vale mais 60) */
function igRenovar(array &$conta, array $c): void {
  if (strtotime((string)($conta['expira'] ?? '')) > agoraTs() + 15 * CV_UM_DIA) return;
  [$st, $j] = igHttp('GET', preg_replace('~/v[0-9.]+$~', '', igGraph($c)) . '/refresh_access_token', ['grant_type' => 'ig_refresh_token', 'access_token' => $conta['token']]);
  if ($st < 400 && !empty($j['access_token'])) { $conta['token'] = (string)$j['access_token']; $conta['expira'] = date('Y-m-d H:i:s', agoraTs() + (int)($j['expires_in'] ?? 5184000)); }
}
/* uma mensagem da API (da conversa) no formato de guardar */
function igMsg(array $x, array $conta): ?array {
  $de = (string)($x['from']['id'] ?? ''); if ($de === '') return null;
  $daLoja = in_array($de, [(string)$conta['id'], (string)($conta['app_id'] ?? '')], true) || ((string)($x['from']['username'] ?? '') !== '' && (string)$x['from']['username'] === (string)$conta['usuario']);
  $outro = $daLoja ? (array)($x['to']['data'][0] ?? []) : (array)$x['from'];
  $tipo = 'texto'; $midia = '';
  foreach ((array)($x['attachments']['data'] ?? []) as $a) { $tipo = isset($a['video_data']) ? 'video' : (isset($a['image_data']) ? 'foto' : (isset($a['file_url']) ? 'arquivo' : 'outro')); $midia = (string)($a['image_data']['url'] ?? $a['video_data']['url'] ?? $a['file_url'] ?? ''); break; }
  if (!empty($x['story'])) { $tipo = $tipo === 'texto' && (string)($x['message'] ?? '') !== '' ? 'texto' : 'outro'; }
  return ['contato' => (string)($outro['id'] ?? ''), 'usuario' => (string)($outro['username'] ?? ''),
    'm' => ['ext' => (string)($x['id'] ?? ''), 'de' => $daLoja ? 'loja' : 'cliente', 'texto' => (string)($x['message'] ?? '') . (!empty($x['story']) && (string)($x['message'] ?? '') === '' ? 'Respondeu ao story' : ''), 'tipo' => $tipo, 'midia' => $midia, 'ts' => cvTsDe($x['created_time'] ?? '')]];
}
/* a conferência: as conversas que mudaram desde a última vez, com as mensagens mais novas de cada uma (a API dá as 20 últimas) */
function igSincronizar(string $id, bool $aovivo = true, int $max = 25): array {
  $c = igCfg(); $k = null; foreach ($c['contas'] as $i => $x) if ((string)$x['id'] === $id) $k = $i; if ($k === null) throw new RuntimeException('Conta do Instagram não encontrada.');
  $conta = $c['contas'][$k]; $desde = (int)($conta['sync_ts'] ?? 0); $novas = 0; $vistas = 0; $maior = $desde; $erro = '';
  try {
    igRenovar($conta, $c);
    $L = igGet($c, 'me/conversations', ['platform' => 'instagram', 'fields' => 'id,updated_time', 'limit' => $max], $conta['token']);
    foreach ((array)($L['data'] ?? []) as $cvs) {
      $up = cvTsDe($cvs['updated_time'] ?? 0); if ($desde && $up <= $desde) continue; $maior = max($maior, $up); $vistas++;
      $d = igGet($c, (string)$cvs['id'], ['fields' => 'participants,messages.limit(20){id,created_time,from,to,message,attachments,story}'], $conta['token']);
      $msgs = (array)($d['messages']['data'] ?? []);
      if ($msgs && !isset($msgs[0]['from'])) { $todas = []; foreach (array_slice($msgs, 0, 20) as $mm) $todas[] = igGet($c, (string)$mm['id'], ['fields' => 'id,created_time,from,to,message,attachments,story'], $conta['token']); $msgs = $todas; }   /* (a API sem os campos juntos: uma a uma) */
      $part = []; foreach ((array)($d['participants']['data'] ?? []) as $p) if ((string)($p['id'] ?? '') !== (string)$conta['id'] && (string)($p['username'] ?? '') !== (string)$conta['usuario']) $part = $p;
      foreach (array_reverse($msgs) as $mm) {
        $g = igMsg((array)$mm, $conta); if (!$g || $g['contato'] === '') continue;
        $info = ['usuario' => $g['usuario'] !== '' ? $g['usuario'] : (string)($part['username'] ?? '')];
        if (cvGuardar('ig', $id, $g['contato'], $g['m'], $info, $aovivo && $desde > 0)) $novas++;
      }
    }
  } catch (Throwable $e) { $erro = $e->getMessage(); }
  $c = igCfg(); foreach ($c['contas'] as $i => $x) if ((string)$x['id'] === $id) { $c['contas'][$i]['token'] = $conta['token']; $c['contas'][$i]['expira'] = $conta['expira'];
    if ($erro === '') { $c['contas'][$i]['sync_ts'] = $maior; $c['contas'][$i]['sync_em'] = agora(); } $c['contas'][$i]['erro'] = $erro; }
  igGravar($c); cvAvisarDepois();
  if ($erro !== '') throw new RuntimeException($erro);
  return ['conversas' => $vistas, 'novas' => $novas];
}
/* o nome do cliente do Instagram (quando o webhook só traz o número dele) */
function igPerfilContato(array $c, array $conta, string $igsid): array {
  try { $p = igGet($c, $igsid, ['fields' => 'name,username,profile_pic'], $conta['token']); return ['nome' => (string)($p['name'] ?? ''), 'usuario' => (string)($p['username'] ?? ''), 'foto' => (string)($p['profile_pic'] ?? '')]; }
  catch (Throwable $e) { return []; }
}
/* o webhook: cada mensagem que chega ou sai pela conta (is_echo = mandada pela loja) */
function igWebhook(array $j): int {
  $c = igCfg(); $n = 0;
  foreach ((array)($j['entry'] ?? []) as $e) {
    $dono = (string)($e['id'] ?? ''); $conta = null; foreach ($c['contas'] as $x) if ((string)$x['id'] === $dono || (string)($x['app_id'] ?? '') === $dono) $conta = $x;
    if (!$conta) continue;
    foreach ((array)($e['messaging'] ?? []) as $ev) {
      $msg = $ev['message'] ?? null; if (!is_array($msg) || !empty($msg['is_deleted'])) continue;
      $eco = !empty($msg['is_echo']); $outro = (string)($eco ? ($ev['recipient']['id'] ?? '') : ($ev['sender']['id'] ?? '')); if ($outro === '' || $outro === (string)$conta['id']) continue;
      $tipo = 'texto'; $midia = ''; foreach ((array)($msg['attachments'] ?? []) as $a) { $t = (string)($a['type'] ?? ''); $tipo = ['image' => 'foto', 'video' => 'video', 'audio' => 'audio', 'file' => 'arquivo', 'sticker' => 'figurinha'][$t] ?? 'outro'; $midia = (string)($a['payload']['url'] ?? ''); break; }
      $info = []; $q = db()->prepare("SELECT nome, usuario FROM cv_conversas WHERE canal = 'ig' AND conta = ? AND contato = ?"); cvTabelas(); $q->execute([(string)$conta['id'], $outro]); $ja = $q->fetch();
      if (!$ja || ($ja['nome'] === '' && $ja['usuario'] === '')) $info = igPerfilContato($c, $conta, $outro);
      $texto = (string)($msg['text'] ?? ''); if ($texto === '' && isset($msg['reply_to']['story'])) $texto = 'Respondeu ao story';
      if (cvGuardar('ig', (string)$conta['id'], $outro, ['ext' => (string)($msg['mid'] ?? ''), 'de' => $eco ? 'loja' : 'cliente', 'texto' => $texto, 'tipo' => $tipo, 'midia' => $midia, 'ts' => cvTsDe($ev['timestamp'] ?? agoraTs())], $info, true)) $n++;
    }
    $c2 = igCfg(); foreach ($c2['contas'] as $i => $x) if ((string)$x['id'] === (string)$conta['id']) $c2['contas'][$i]['webhook_em'] = agora(); igGravar($c2);
  }
  cvAvisarDepois();
  return $n;
}

/* ==========================================================
   WHATSAPP (pela Evolution API, lendo o QR Code)
   ========================================================== */
function zapCfg(): array { $c = cfg('zap', null); $c = (is_array($c) ? $c : []) + ['url' => '', 'chave' => '', 'contas' => []]; if (!is_array($c['contas'])) $c['contas'] = []; return $c; }
function zapGravar(array $c): void { gravarCfg('zap', $c); }
function zapConta(array $z, string $id): array { foreach ($z['contas'] as $x) if ((string)$x['id'] === $id) return $x; throw new RuntimeException('Número de WhatsApp não encontrado.'); }
function zapEvo(array $z, array $conta): array { return ['url' => $z['url'], 'chave' => $z['chave'], 'instancia' => (string)$conta['instancia']]; }
function zapAtualiza(string $id, array $campos): void { $z = zapCfg(); foreach ($z['contas'] as $i => $x) if ((string)$x['id'] === $id) $z['contas'][$i] = $campos + $x; zapGravar($z); }
function zapPublico(array $z): array {
  return ['url' => $z['url'], 'temChave' => $z['chave'] !== '', 'chave' => mascara((string)$z['chave']),
    'contas' => array_map(fn($x) => ['id' => (string)$x['id'], 'nome' => (string)$x['nome'], 'numero' => (string)($x['numero'] ?? ''), 'conectadoEm' => (string)($x['conectado_em'] ?? ''),
      'ultima' => (string)($x['ultima'] ?? ''), 'historicoEm' => (string)($x['hist_em'] ?? ''), 'erro' => (string)($x['erro'] ?? '')], array_values($z['contas']))];
}
function zapWebhookUrl(array $conta): string { return urlBase() . '/api/insta-zap.php?c=' . rawurlencode((string)$conta['id']) . '&t=' . $conta['token']; }
/* um número novo: a instância na Evolution (com o histórico), o webhook e o QR Code */
function zapNovo(string $nome): array {
  $z = zapCfg(); if ($z['url'] === '' || $z['chave'] === '') throw new RuntimeException('Primeiro o endereço e a chave do servidor do WhatsApp (Evolution API).');
  $nome = cvTxt($nome, 40); if (mb_strlen($nome) < 2) throw new RuntimeException('Dê um nome para este número (ex.: Morumbi).');
  $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: 'numero')), '-') ?: 'numero';
  $id = $slug; $n = 2; while (array_filter($z['contas'], fn($x) => (string)$x['id'] === $id)) $id = $slug . '-' . $n++;
  $conta = ['id' => $id, 'nome' => $nome, 'instancia' => 'playspace-insta-' . $id, 'token' => bin2hex(random_bytes(12)), 'numero' => '', 'conectado_em' => '', 'ultima' => '', 'hist_em' => '', 'erro' => ''];
  $e = zapEvo($z, $conta);
  [$st, $j, $raw] = http('POST', evoBase($e) . '/instance/create', evoCab($e), ['instanceName' => $conta['instancia'], 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS', 'groupsIgnore' => true,
    'syncFullHistory' => true, 'readMessages' => false, 'readStatus' => false, 'alwaysOnline' => false, 'rejectCall' => false]);
  if ($st === 401 || $st === 403) throw new RuntimeException('O servidor do WhatsApp recusou a chave (apikey). Confira a chave.');
  if ($st >= 400 && !preg_match('/already|já existe|in use/i', (string)$raw)) throw new RuntimeException('O servidor do WhatsApp não criou o número: ' . msgErro($j, $raw, $st));
  $z['contas'][] = $conta; zapGravar($z);
  zapWebhookLigar($z, $conta);
  return $conta;
}
function zapWebhookLigar(array $z, array $conta): void {
  $e = zapEvo($z, $conta); $ev = ['MESSAGES_UPSERT', 'MESSAGES_SET', 'CONNECTION_UPDATE', 'CONTACTS_UPSERT'];
  [$st, $j, $raw] = http('POST', evoBase($e) . '/webhook/set/' . rawurlencode($e['instancia']), evoCab($e), ['webhook' => ['enabled' => true, 'url' => zapWebhookUrl($conta), 'webhookByEvents' => false, 'webhookBase64' => false, 'events' => $ev]]);
  if ($st >= 400) [$st, $j, $raw] = http('POST', evoBase($e) . '/webhook/set/' . rawurlencode($e['instancia']), evoCab($e), ['url' => zapWebhookUrl($conta), 'enabled' => true, 'webhook_by_events' => false, 'events' => $ev]);
  if ($st >= 400) throw new RuntimeException('Não consegui ligar o aviso das mensagens no servidor do WhatsApp: ' . msgErro($j, $raw, $st));
}
/* o QR Code (ou "conectado"); conectado pela primeira vez: guarda o número e traz o histórico */
function zapQr(string $id): array {
  $z = zapCfg(); $conta = zapConta($z, $id); $e = zapEvo($z, $conta);
  $r = evoQr($e);
  if ($r['estado'] === 'conectado') {
    $i = evoInfo($e); $primeira = (string)($conta['conectado_em'] ?? '') === '';
    zapAtualiza($id, ['conectado_em' => $conta['conectado_em'] ?: agora(), 'numero' => (string)($i['numero'] ?? $conta['numero'] ?? ''), 'erro' => '']);
    if ($primeira || (string)($conta['hist_em'] ?? '') === '') { cvDepois(function () use ($id) { try { zapHistorico($id); } catch (Throwable $e) { zapAtualiza($id, ['erro' => $e->getMessage()]); } }); }
  }
  return $r;
}
function zapEstado(string $id): string {
  $z = zapCfg(); $conta = zapConta($z, $id); $r = evoEstado(zapEvo($z, $conta));
  if ($r['estado'] !== 'conectado' && (string)($conta['conectado_em'] ?? '') !== '') zapAtualiza($id, ['conectado_em' => '']);
  return $r['estado'];
}
function zapSair(string $id): void {
  $z = zapCfg(); $conta = zapConta($z, $id); try { evoSair(zapEvo($z, $conta)); } catch (Throwable $e) { }
  $z['contas'] = array_values(array_filter($z['contas'], fn($x) => (string)$x['id'] !== $id)); zapGravar($z);
}
/* uma mensagem do WhatsApp (o formato da Evolution/Baileys) no formato de guardar; grupos e status ficam de fora */
function zapMsg(array $m): ?array {
  $k = (array)($m['key'] ?? []); $jid = (string)($k['remoteJid'] ?? $m['remoteJid'] ?? '');
  if ($jid === '' || strpos($jid, '@g.us') !== false || strpos($jid, 'status@') !== false || strpos($jid, '@newsletter') !== false || strpos($jid, '@broadcast') !== false) return null;
  $alt = (string)($k['remoteJidAlt'] ?? $k['senderPn'] ?? '');
  $tel = strpos($jid, '@lid') !== false ? (strpos($alt, '@s.whatsapp.net') !== false ? preg_replace('/\D/', '', explode('@', $alt)[0]) : '') : preg_replace('/\D/', '', explode('@', $jid)[0]);
  $contato = $tel !== '' ? $tel : $jid; if ($tel !== '' && strlen($tel) < 10) return null;
  $msg = (array)($m['message'] ?? []); foreach (['ephemeralMessage', 'viewOnceMessage', 'viewOnceMessageV2', 'documentWithCaptionMessage'] as $emb) if (isset($msg[$emb]['message'])) $msg = (array)$msg[$emb]['message'];
  $texto = (string)($msg['conversation'] ?? $msg['extendedTextMessage']['text'] ?? $msg['imageMessage']['caption'] ?? $msg['videoMessage']['caption'] ?? $msg['documentMessage']['caption'] ?? $msg['buttonsResponseMessage']['selectedDisplayText'] ?? $msg['listResponseMessage']['title'] ?? $msg['templateButtonReplyMessage']['selectedDisplayText'] ?? '');
  $tipo = isset($msg['imageMessage']) ? 'foto' : (isset($msg['videoMessage']) ? 'video' : (isset($msg['audioMessage']) ? 'audio' : (isset($msg['documentMessage']) ? 'arquivo' : (isset($msg['stickerMessage']) ? 'figurinha' : ($texto !== '' ? 'texto' : 'outro')))));
  if (isset($msg['protocolMessage']) || isset($msg['reactionMessage']) || isset($msg['senderKeyDistributionMessage']) && $texto === '' && $tipo === 'outro') return null;
  if ($texto === '' && $tipo === 'outro' && !$msg) return null;
  $de = !empty($k['fromMe']) ? 'loja' : 'cliente';
  return ['contato' => $contato, 'tel' => $tel, 'nome' => $de === 'cliente' ? (string)($m['pushName'] ?? '') : '',
    'm' => ['ext' => (string)($k['id'] ?? ''), 'de' => $de, 'texto' => $texto, 'tipo' => $tipo, 'midia' => '', 'ts' => cvTsDe($m['messageTimestamp'] ?? agoraTs())]];
}
/* as mensagens de um pedaço de JSON da Evolution (uma, uma lista, ou dentro de "messages"/"records") */
function zapColher($d, array &$o, int $prof = 0): void {
  if (!is_array($d) || $prof > 4 || count($o) > 3000) return;
  if (isset($d['key']) && is_array($d['key']) && (isset($d['message']) || isset($d['messageTimestamp']))) { $o[] = $d; return; }
  foreach ($d as $v) if (is_array($v)) zapColher($v, $o, $prof + 1);
}
/* o webhook de um número: as mensagens (ao vivo ou o lote do histórico) e a conexão */
function zapWebhook(array $conta, array $j): int {
  $ev = strtolower(str_replace('_', '.', (string)($j['event'] ?? ''))); $n = 0;
  if (strpos($ev, 'connection') !== false) {
    $s = (string)($j['data']['state'] ?? $j['data']['status'] ?? '');
    if ($s === 'open') { $primeira = (string)($conta['conectado_em'] ?? '') === ''; zapAtualiza((string)$conta['id'], ['conectado_em' => $conta['conectado_em'] ?: agora(), 'erro' => '']);
      if ($primeira && (string)($conta['hist_em'] ?? '') === '') { $id = (string)$conta['id']; cvDepois(function () use ($id) { try { zapHistorico($id); } catch (Throwable $e) { zapAtualiza($id, ['erro' => $e->getMessage()]); } }); } }
    elseif ($s === 'close') zapAtualiza((string)$conta['id'], ['conectado_em' => '']);
    return 0;
  }
  if (strpos($ev, 'contacts') !== false) {   /* os nomes dos contatos (o da agenda do celular da loja) */
    cvTabelas(); $up = db()->prepare("UPDATE cv_conversas SET nome = ? WHERE canal = 'wa' AND conta = ? AND contato = ? AND nome = ''");
    foreach ((array)(isset($j['data'][0]) ? $j['data'] : [$j['data'] ?? []]) as $ct) { $tel = preg_replace('/\D/', '', explode('@', (string)($ct['id'] ?? $ct['remoteJid'] ?? ''))[0]); $nm = cvTxt((string)($ct['pushName'] ?? $ct['name'] ?? ''), 120); if ($tel !== '' && $nm !== '') $up->execute([$nm, (string)$conta['id'], $tel]); }
    return 0;
  }
  if (strpos($ev, 'messages') === false && !isset($j['data']['key'])) return 0;
  $aovivo = strpos($ev, 'messages.set') === false; $L = []; zapColher($j['data'] ?? [], $L);
  foreach ($L as $m) { $g = zapMsg($m); if (!$g) continue; if (cvGuardar('wa', (string)$conta['id'], $g['contato'], $g['m'], ['nome' => $g['nome'], 'tel' => $g['tel']], $aovivo && agoraTs() - $g['m']['ts'] < 7200)) $n++; }
  if ($n) zapAtualiza((string)$conta['id'], ['ultima' => agora()]);
  cvAvisarDepois();
  return $n;
}
/* o histórico: as conversas mais recentes do número, com as últimas mensagens de cada uma (não avisa nem marca como não lida) */
function zapHistorico(string $id, int $chats = 60, int $porChat = 40): array {
  $z = zapCfg(); $conta = zapConta($z, $id); $e = zapEvo($z, $conta); $nC = 0; $nM = 0;
  [$st, $j, $raw] = http('POST', evoBase($e) . '/chat/findChats/' . rawurlencode($e['instancia']), evoCab($e), new stdClass(), 60);
  if ($st >= 400) throw new RuntimeException('O servidor do WhatsApp não deu as conversas: ' . msgErro($j, $raw, $st));
  $C = isset($j[0]) ? $j : (array)($j['chats'] ?? $j['records'] ?? []);
  usort($C, fn($a, $b) => cvTsDe($b['updatedAt'] ?? $b['lastMessage']['messageTimestamp'] ?? 0) <=> cvTsDe($a['updatedAt'] ?? $a['lastMessage']['messageTimestamp'] ?? 0));
  foreach (array_slice($C, 0, $chats) as $ch) {
    $jid = (string)($ch['remoteJid'] ?? $ch['id'] ?? ''); if ($jid === '' || strpos($jid, '@g.us') !== false || strpos($jid, '@broadcast') !== false || strpos($jid, 'status@') !== false) continue;
    [$st, $r] = http('POST', evoBase($e) . '/chat/findMessages/' . rawurlencode($e['instancia']), evoCab($e), ['where' => ['key' => ['remoteJid' => $jid]], 'limit' => $porChat, 'page' => 1, 'offset' => $porChat], 60);
    if ($st >= 400) continue; $L = []; zapColher($r, $L); $nC++;
    $nm = cvTxt((string)($ch['pushName'] ?? $ch['name'] ?? ''), 120);
    foreach (array_slice($L, 0, $porChat) as $m) { $g = zapMsg($m); if (!$g) continue; if (cvGuardar('wa', $id, $g['contato'], $g['m'], ['nome' => $g['nome'] !== '' ? $g['nome'] : $nm, 'tel' => $g['tel'], 'foto' => (string)($ch['profilePicUrl'] ?? '')], false)) $nM++; }
  }
  zapAtualiza($id, ['hist_em' => agora(), 'erro' => '']);
  return ['conversas' => $nC, 'mensagens' => $nM];
}

/* ==========================================================
   A conferência de tempos em tempos (o Instagram, para o que o webhook não trouxe): roda depois da resposta de quem estiver usando
   o Play Space ou o Insta, no máximo a cada 3 minutos, e não atrasa ninguém
   ========================================================== */
function cvSincronizarDepois(): void {
  try { $c = cfg('insta', null); if (!is_array($c) || empty($c['contas'])) return; } catch (Throwable $e) { return; }
  $GLOBALS['CV_AVISOS_MARCADO'] = true;   /* (os avisos saem aqui mesmo, no fim) */
  cvDepois(function () { foreach (igCfg()['contas'] as $x) { try { igSincronizar((string)$x['id']); } catch (Throwable $e) { } } foreach ($GLOBALS['CV_AVISOS'] as $a) cvAvisar($a); $GLOBALS['CV_AVISOS'] = []; });
}
