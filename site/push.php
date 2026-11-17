<?php
// Lembrete de água com o app fechado (Web Push).
// O app do aluno se inscreve aqui; um agendamento (cron) do cPanel roda este arquivo a cada 10 minutos
// e manda o aviso para quem já passou do intervalo desde o último copo, dentro do horário e sem a meta batida.
// Fim do descanso: o app avisa a hora em que o descanso termina; este pedido responde na hora e espera até lá para avisar
// (se a pessoa continuou no app, o próprio app cancela antes).
// Treino esquecido aberto: avisa uma vez quem está com o treino aberto há 3 horas, a qualquer hora.
// Conta nova: o app avisa quando alguém termina o cadastro e o servidor manda uma notificação para os celulares do dono
// (ligados no painel do personal; só o dono consegue ligar). Limite por internet e por hora contra abuso.
// Lembrete do treino do dia: a partir da 2ª semana de uso, nos dias de treino, no horário em que a pessoa costuma treinar
// (o app estuda e manda um horário por dia da semana), uma vez no dia (não avisa se já treinou).
// Lembretes para voltar: 1 dia depois do cadastro sem o 1º treino (de novo com 4 e 10 dias) e 4 e 10 dias sem treinar;
// um aviso por etapa, a partir das 18h30 do horário da pessoa (o app manda a data do cadastro e a do último treino).
// O aviso vai sem conteúdo (o próprio app monta a mensagem), então não precisa de criptografia de conteúdo,
// só da assinatura VAPID. As chaves e as inscrições ficam fora do site, na pasta de cima.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$CLI = PHP_SAPI === 'cli';
function sai($a, $code = 200) { global $CLI; if (!$CLI) http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; }
$base = dirname(__DIR__);
$ARQ_CH = $base . '/.simplificando-vapid.json';
$ARQ_IN = $base . '/.simplificando-push.json';
$ARQ_DONO = $base . '/.simplificando-dono-push.json';   // celulares do dono que recebem o aviso de conta nova
$ARQ_NOVOS = $base . '/.simplificando-novos.json';     // últimas contas novas (o aviso busca daqui o nome)

function b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function chaves() {
  global $ARQ_CH;
  $v = json_decode((string)@file_get_contents($ARQ_CH), true);
  if (is_array($v) && !empty($v['pem']) && !empty($v['pub'])) return $v;
  $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
  if (!$k) sai(['ok' => false, 'erro' => 'O servidor não gera chaves (openssl).'], 500);
  openssl_pkey_export($k, $pem); $d = openssl_pkey_get_details($k);
  $pub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
  $v = ['pem' => $pem, 'pub' => b64u($pub)];
  @file_put_contents($ARQ_CH, json_encode($v), LOCK_EX); @chmod($ARQ_CH, 0600);
  return $v;
}
function le_in() { global $ARQ_IN; $v = json_decode((string)@file_get_contents($ARQ_IN), true); return is_array($v) ? $v : []; }
function grava_in($v) { global $ARQ_IN; @file_put_contents($ARQ_IN, json_encode($v), LOCK_EX); @chmod($ARQ_IN, 0600); }
// assinatura ES256 em DER -> 64 bytes (r||s)
function der2raw($der) {
  $p = 2; if (ord($der[1]) & 0x80) $p += ord($der[1]) & 0x7f;
  $lr = ord($der[$p + 1]); $r = substr($der, $p + 2, $lr); $p += 2 + $lr;
  $ls = ord($der[$p + 1]); $s = substr($der, $p + 2, $ls);
  $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT); $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
  return $r . $s;
}
function vapid($endpoint, $ch) {
  $u = parse_url($endpoint); $aud = $u['scheme'] . '://' . $u['host'];
  $h = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
  $c = b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'https://simplificandoeduca.com'], JSON_UNESCAPED_SLASHES));
  openssl_sign("$h.$c", $sig, $ch['pem'], OPENSSL_ALGO_SHA256);
  return "$h.$c." . b64u(der2raw($sig));
}
function le_arq($a, $padrao) { $v = json_decode((string)@file_get_contents($a), true); return is_array($v) ? $v : $padrao; }
function grava_arq($a, $v) { @file_put_contents($a, json_encode($v, JSON_UNESCAPED_UNICODE), LOCK_EX); @chmod($a, 0600); }
function envia($endpoint, $ch, $urg = 'normal') {
  // robô de testes: com SIMP_TESTE_PUSH (só no computador de teste), anota o envio num arquivo em vez de mandar
  if ($f = getenv('SIMP_TESTE_PUSH')) { @file_put_contents($f, json_encode(['e' => $endpoint, 'urg' => $urg, 't' => time()]) . "\n", FILE_APPEND | LOCK_EX); return 201; }
  $c = curl_init($endpoint);
  curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: vapid t=' . vapid($endpoint, $ch) . ', k=' . $ch['pub'], 'TTL: ' . ($urg === 'high' ? 120 : 3600), 'Urgency: ' . $urg, 'Content-Length: 0']]);
  curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
  return $st;
}
// só serviços de aviso conhecidos (Google/Chrome/Android, Apple, Firefox, Microsoft)
function endpoint_ok($e) {
  $h = parse_url((string)$e, PHP_URL_HOST); if (!$h || parse_url($e, PHP_URL_SCHEME) !== 'https') return false;
  return (bool)preg_match('/(^|\.)(fcm\.googleapis\.com|android\.googleapis\.com|push\.apple\.com|push\.services\.mozilla\.com|notify\.windows\.com)$/', $h);
}
// etapa do lembrete de volta: n1/n4/n10 (sem o 1º treino, contando do cadastro) ou t4/t10 (sem treinar, contando do último treino)
function volta_etapa($vt, $dia) {
  if (!is_array($vt) || empty($vt['on'])) return ['', ''];
  $ult = (string)($vt['ult'] ?? ''); $ref = $ult ?: (string)($vt['cad'] ?? ''); if (!$ref) return ['', ''];
  $d = (int)floor((strtotime($dia . ' 12:00:00 UTC') - strtotime($ref . ' 12:00:00 UTC')) / 86400);
  $e = $ult ? ($d >= 10 ? 't10' : ($d >= 4 ? 't4' : '')) : ($d >= 10 ? 'n10' : ($d >= 4 ? 'n4' : ($d >= 1 ? 'n1' : '')));
  return [$e, $e ? $e . '|' . $ref : ''];
}
$hm = function ($s, $padrao) { return preg_match('/^(\d{1,2}):(\d{2})$/', (string)$s, $m) ? min(1439, $m[1] * 60 + $m[2]) : $padrao; };

$acao = $CLI ? ($argv[1] ?? 'cron') : ($_GET['acao'] ?? '');

if ($acao === 'chave') { $ch = chaves(); sai(['ok' => true, 'pub' => $ch['pub']]); }

// ---------- conta nova: avisa o dono ----------
if ($acao === 'novo') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false], 405);
  $j = json_decode((string)file_get_contents('php://input', false, null, 0, 2000), true); if (!is_array($j)) sai(['ok' => false], 400);
  $corta = function ($x, $n) { $x = trim(preg_replace('/[\x00-\x1F\x7F<>]+/u', ' ', (string)$x)); return function_exists('mb_substr') ? mb_substr($x, 0, $n) : substr($x, 0, $n); };
  $nome = $corta($j['nome'] ?? '', 40); if ($nome === '') sai(['ok' => false], 400);
  $N = le_arq($ARQ_NOVOS, ['novos' => [], 'ip' => []]); $agora = time();
  // no máximo 3 cadastros a cada 10 min por internet e 40 por hora no total
  $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'simp-novo');
  $N['ip'] = array_values(array_filter($N['ip'] ?? [], function ($x) use ($agora) { return $agora - $x[1] < 3600; }));
  $doIp = count(array_filter($N['ip'], function ($x) use ($ip, $agora) { return $x[0] === $ip && $agora - $x[1] < 600; }));
  if ($doIp >= 3 || count($N['ip']) >= 40) sai(['ok' => true]);
  $N['ip'][] = [$ip, $agora];
  $N['novos'][] = ['nome' => $nome, 'ed' => $corta($j['ed'] ?? '', 20), 'plano' => $corta($j['plano'] ?? '', 10), 'em' => $agora];
  $N['novos'] = array_slice($N['novos'], -30); grava_arq($ARQ_NOVOS, $N);
  $D = le_arq($ARQ_DONO, []); if (!$D) sai(['ok' => true]);
  $ch = chaves(); $mudou = false;
  foreach ($D as $id => $d) { $st = envia($d['e'], $ch, 'high'); if ($st == 404 || $st == 410) { unset($D[$id]); $mudou = true; } }
  if ($mudou) grava_arq($ARQ_DONO, $D);
  sai(['ok' => true]);
}
// o aviso no celular do dono busca o nome de quem entrou (só com a chave que o painel recebeu ao ligar)
if ($acao === 'novos') {
  $k = (string)($_GET['k'] ?? ''); $D = le_arq($ARQ_DONO, []); $ok = false;
  foreach ($D as $d) if (strlen($k) >= 20 && hash_equals((string)($d['k'] ?? ''), $k)) $ok = true;
  if (!$ok) sai(['ok' => false], 403);
  $desde = (int)($_GET['desde'] ?? 0); $N = le_arq($ARQ_NOVOS, ['novos' => []]);
  sai(['ok' => true, 'novos' => array_values(array_filter($N['novos'], function ($x) use ($desde) { return $x['em'] > $desde && time() - $x['em'] < 86400; }))]);
}
if (in_array($acao, ['dono-assinar', 'dono-sair'], true)) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false], 405);
  require __DIR__ . '/dono.inc.php';
  if (!dono_uid()) sai(['ok' => false, 'erro' => 'Só o dono do painel liga este aviso.'], 403);
  $j = json_decode((string)file_get_contents('php://input', false, null, 0, 8000), true);
  $id = is_array($j) && isset($j['id']) && preg_match('/^[a-z0-9]{12,40}$/', $j['id']) ? $j['id'] : '';
  if (!$id) sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
  $D = le_arq($ARQ_DONO, []);
  if ($acao === 'dono-sair') { unset($D[$id]); grava_arq($ARQ_DONO, $D); sai(['ok' => true]); }
  $e = $j['sub']['endpoint'] ?? ''; if (!endpoint_ok($e)) sai(['ok' => false, 'erro' => 'Serviço de aviso desconhecido.'], 400);
  if (!isset($D[$id]) && count($D) >= 10) sai(['ok' => false, 'erro' => 'Limite de aparelhos.'], 429);
  $k = $D[$id]['k'] ?? bin2hex(random_bytes(16));
  $D[$id] = ['e' => $e, 'k' => $k, 'em' => time()]; grava_arq($ARQ_DONO, $D);
  sai(['ok' => true, 'k' => $k]);
}

if (in_array($acao, ['assinar', 'copo', 'sair', 'descanso', 'treino', 'lembrete'], true)) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false], 405);
  $j = json_decode((string)file_get_contents('php://input', false, null, 0, 8000), true);
  $id = is_array($j) && isset($j['id']) && preg_match('/^[a-z0-9]{12,40}$/', $j['id']) ? $j['id'] : '';
  if (!$id) sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
  $v = le_in();
  if ($acao === 'sair') { unset($v[$id]); grava_in($v); sai(['ok' => true]); }
  if ($acao === 'descanso') {
    if (!isset($v[$id])) sai(['ok' => false]);
    $fim = (int)floor(((float)($j['fim'] ?? 0)) / 1000); $k = preg_match('/^[a-z0-9]{4,16}$/', (string)($j['k'] ?? '')) ? (string)$j['k'] : '';
    $marca = ($fim > 0 && $k) ? $fim . ':' . $k : '';
    $v[$id]['ds'] = $marca; grava_in($v);
    $espera = $fim - time();
    if (!$marca || $espera > 600 || $espera < -5) sai(['ok' => true]);
    // responde já e segura o envio até o fim do descanso
    ignore_user_abort(true); @set_time_limit($espera + 60);
    $out = json_encode(['ok' => true]); header('Content-Length: ' . strlen($out)); header('Connection: close'); echo $out;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); elseif (function_exists('litespeed_finish_request')) litespeed_finish_request(); else { while (ob_get_level()) @ob_end_flush(); flush(); }
    if ($espera > 0) sleep($espera);
    $v = le_in(); if (!isset($v[$id]) || ($v[$id]['ds'] ?? '') !== $marca) exit; // cancelado, mudou (+15 s) ou já avisado
    $st = envia($v[$id]['e'], chaves(), 'high');
    $v = le_in(); if (isset($v[$id])) { if ($st == 404 || $st == 410) unset($v[$id]); elseif (($v[$id]['ds'] ?? '') === $marca) $v[$id]['ds'] = ''; grava_in($v); }
    exit;
  }
  if ($acao === 'treino') { // início do treino aberto (ms) ou 0 quando finaliza/cancela
    if (isset($v[$id])) { $ini = (int)floor(((float)($j['ini'] ?? 0)) / 1000); $ini = $ini > 0 ? min(time(), $ini) : 0;
      if ((int)($v[$id]['tr'] ?? 0) !== $ini) { $v[$id]['tr'] = $ini; $v[$id]['trav'] = 0; grava_in($v); } }
    sai(['ok' => true]);
  }
  if ($acao === 'lembrete') { // {on, h: minutos do dia, hs: {dia da semana: minutos}, dias: [0=domingo..6], feito, pausa, desde: a partir de que dia avisar}
    if (isset($v[$id])) {
      $dt = function ($x) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$x) ? (string)$x : ''; };
      $dias = array_values(array_unique(array_filter(array_map('intval', is_array($j['dias'] ?? null) ? $j['dias'] : []), function ($d) { return $d >= 0 && $d <= 6; })));
      $hs = []; foreach ((is_array($j['hs'] ?? null) ? $j['hs'] : []) as $w => $m) { $w = (int)$w; if ($w >= 0 && $w <= 6) $hs[(string)$w] = max(0, min(1439, (int)$m)); }
      $v[$id]['lb'] = ['on' => empty($j['on']) ? 0 : 1, 'h' => max(0, min(1439, (int)($j['h'] ?? 1080))), 'hs' => $hs, 'dias' => $dias, 'feito' => $dt($j['feito'] ?? ''), 'pausa' => $dt($j['pausa'] ?? ''), 'desde' => $dt($j['desde'] ?? ''),
        'vt' => ['on' => empty($j['vt']['on']) ? 0 : 1, 'cad' => $dt($j['vt']['cad'] ?? ''), 'ult' => $dt($j['vt']['ult'] ?? '')]];
      grava_in($v);
    }
    sai(['ok' => true]);
  }
  if ($acao === 'copo') { if (isset($v[$id])) { $v[$id]['copo'] = time(); if (!empty($j['meta'])) $v[$id]['meta'] = (string)$j['meta']; grava_in($v); } sai(['ok' => true]); }
  $e = $j['sub']['endpoint'] ?? '';
  if (!endpoint_ok($e)) sai(['ok' => false, 'erro' => 'Serviço de aviso desconhecido.'], 400);
  if (!isset($v[$id]) && count($v) >= 2000) sai(['ok' => false, 'erro' => 'Limite de inscrições.'], 429);
  $antes = $v[$id] ?? [];
  $v[$id] = ['e' => $e, 'ini' => $hm($j['ini'] ?? '', 480), 'fim' => $hm($j['fim'] ?? '', 1320), 'cada' => max(30, min(240, (int)($j['cada'] ?? 90))),
    'tz' => max(-840, min(840, (int)($j['tz'] ?? 180))), 'meta' => (string)($j['meta'] ?? ($antes['meta'] ?? '')), 'copo' => (int)($antes['copo'] ?? 0), 'ultimo' => (int)($antes['ultimo'] ?? 0), 'ag' => isset($j['ag']) ? (int)!empty($j['ag']) : 1, 'ds' => (string)($antes['ds'] ?? ''), 'tr' => (int)($antes['tr'] ?? 0), 'trav' => (int)($antes['trav'] ?? 0), 'lb' => $antes['lb'] ?? null, 'lbav' => (string)($antes['lbav'] ?? ''), 'em' => time()];
  grava_in($v); sai(['ok' => true]);
}

// cron: php push.php cron  (cPanel → Cron Jobs, a cada 10 minutos)
if ($acao === 'cron') {
  if (!$CLI) {
    $ka = $base . '/simplificando-atualizar-chave.txt'; $k = is_file($ka) ? trim((string)@file_get_contents($ka)) : '';
    if (strlen($k) < 12 || !hash_equals($k, (string)($_GET['chave'] ?? ''))) sai(['ok' => false, 'erro' => 'Só pelo agendamento do servidor.'], 403);
  }
  $ch = chaves(); $v = le_in(); $agora = ($CLI && getenv('SIMP_AGORA')) ? (int)getenv('SIMP_AGORA') : time(); $env = 0; $tirou = 0; // SIMP_AGORA: só o robô de testes
  foreach ($v as $id => $s) {
    // treino aberto há 3h (até 14h): um aviso só, a qualquer hora (mesmo com o lembrete de água desligado)
    $tr = (int)($s['tr'] ?? 0);
    if ($tr && empty($s['trav']) && $agora - $tr >= 3 * 3600 && $agora - $tr < 14 * 3600) {
      $st = envia($s['e'], $ch);
      if ($st == 404 || $st == 410) { unset($v[$id]); $tirou++; continue; }
      if ($st >= 200 && $st < 300) { $v[$id]['trav'] = $agora; $v[$id]['ultimo'] = $agora; $env++; }
      continue;
    }
    $local = $agora - $s['tz'] * 60; $min = (int)gmdate('G', $local) * 60 + (int)gmdate('i', $local); $dia = gmdate('Y-m-d', $local);
    // lembrete do treino do dia: do horário daquele dia da semana até 2h depois, uma vez no dia, só depois da 1ª semana
    $lb = $s['lb'] ?? null; $wd = (int)gmdate('w', $local); $lh = is_array($lb) ? (int)($lb['hs'][(string)$wd] ?? $lb['h'] ?? 1080) : 0;
    if (is_array($lb) && !empty($lb['on']) && (empty($lb['desde']) || $dia >= $lb['desde']) && in_array($wd, $lb['dias'] ?? [], true) && $min >= $lh && $min - $lh < 120
        && ($s['lbav'] ?? '') !== $dia && ($lb['feito'] ?? '') !== $dia && !(($lb['pausa'] ?? '') && $lb['pausa'] >= $dia)) {
      $st = envia($s['e'], $ch);
      if ($st == 404 || $st == 410) { unset($v[$id]); $tirou++; continue; }
      if ($st >= 200 && $st < 300) { $v[$id]['lbav'] = $dia; $v[$id]['ultimo'] = $agora; $env++; }
      continue;
    }
    // lembrete para voltar (1 dia sem o 1º treino; 4 e 10 dias sem treinar): uma vez por etapa, das 18h30 às 21h
    [$et, $vk] = volta_etapa($lb['vt'] ?? null, $dia);
    if ($et && $min >= 18 * 60 + 30 && $min < 21 * 60 && ($s['vtav'] ?? '') !== $vk) {
      $st = envia($s['e'], $ch);
      if ($st == 404 || $st == 410) { unset($v[$id]); $tirou++; continue; }
      if ($st >= 200 && $st < 300) { $v[$id]['vtav'] = $vk; $v[$id]['ultimo'] = $agora; $env++; }
      continue;
    }
    if (isset($s['ag']) && !$s['ag']) continue; // só os avisos do descanso (lembrete de água desligado)
    if ($min < $s['ini'] || $min > $s['fim']) continue;
    if ($s['meta'] === $dia) continue;
    // primeiro aviso do dia: no horário de início; depois, a cada intervalo desde o último copo ou aviso
    $base0 = max($s['copo'], $s['ultimo']); if (gmdate('Y-m-d', $base0 - $s['tz'] * 60) !== $dia) $base0 = 0;
    if ($base0 && $agora - $base0 < $s['cada'] * 60) continue;
    $st = envia($s['e'], $ch);
    if ($st == 404 || $st == 410) { unset($v[$id]); $tirou++; continue; }
    if ($st >= 200 && $st < 300) { $v[$id]['ultimo'] = $agora; $env++; }
  }
  grava_in($v);
  // lembretes por e-mail (lembretes.php): uma vez por dia, a partir das 18h30 de Brasília
  $sp = $agora - 3 * 3600; $msp = (int)gmdate('G', $sp) * 60 + (int)gmdate('i', $sp); $lbD = $base . '/.simplificando-lembretes-dia'; $lemb = null;
  if ($msp >= 18 * 60 + 30 && !getenv('SIMP_TESTE_PUSH') && trim((string)@file_get_contents($lbD)) !== gmdate('Y-m-d', $sp)) {
    @file_put_contents($lbD, gmdate('Y-m-d', $sp)); define('LEMB_INCLUIDO', 1); require_once __DIR__ . '/lembretes.php'; $lemb = lb_rodar();
  }
  sai(['ok' => true, 'enviados' => $env, 'removidos' => $tirou, 'inscritos' => count($v), 'emails' => $lemb]);
}
sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
