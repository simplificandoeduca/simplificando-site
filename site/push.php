<?php
// Lembrete de água com o app fechado (Web Push).
// O app do aluno se inscreve aqui; um agendamento (cron) do cPanel roda este arquivo a cada 10 minutos
// e manda o aviso para quem já passou do intervalo desde o último copo, dentro do horário e sem a meta batida.
// Também avisa uma vez quem está com o treino aberto há 3 horas (esqueceu de finalizar), a qualquer hora.
// O aviso vai sem conteúdo (o próprio app monta a mensagem), então não precisa de criptografia de conteúdo,
// só da assinatura VAPID. As chaves e as inscrições ficam fora do site, na pasta de cima.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$CLI = PHP_SAPI === 'cli';
function sai($a, $code = 200) { global $CLI; if (!$CLI) http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; exit; }
$base = dirname(__DIR__);
$ARQ_CH = $base . '/.simplificando-vapid.json';
$ARQ_IN = $base . '/.simplificando-push.json';

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
function envia($endpoint, $ch) {
  $c = curl_init($endpoint);
  curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: vapid t=' . vapid($endpoint, $ch) . ', k=' . $ch['pub'], 'TTL: 3600', 'Urgency: normal', 'Content-Length: 0']]);
  curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
  return $st;
}
// só serviços de aviso conhecidos (Google/Chrome/Android, Apple, Firefox, Microsoft)
function endpoint_ok($e) {
  $h = parse_url((string)$e, PHP_URL_HOST); if (!$h || parse_url($e, PHP_URL_SCHEME) !== 'https') return false;
  return (bool)preg_match('/(^|\.)(fcm\.googleapis\.com|android\.googleapis\.com|push\.apple\.com|push\.services\.mozilla\.com|notify\.windows\.com)$/', $h);
}
$hm = function ($s, $padrao) { return preg_match('/^(\d{1,2}):(\d{2})$/', (string)$s, $m) ? min(1439, $m[1] * 60 + $m[2]) : $padrao; };

$acao = $CLI ? ($argv[1] ?? 'cron') : ($_GET['acao'] ?? '');

if ($acao === 'chave') { $ch = chaves(); sai(['ok' => true, 'pub' => $ch['pub']]); }

if (in_array($acao, ['assinar', 'copo', 'sair', 'treino'], true)) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false], 405);
  $j = json_decode((string)file_get_contents('php://input', false, null, 0, 8000), true);
  $id = is_array($j) && isset($j['id']) && preg_match('/^[a-z0-9]{12,40}$/', $j['id']) ? $j['id'] : '';
  if (!$id) sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
  $v = le_in();
  if ($acao === 'sair') { unset($v[$id]); grava_in($v); sai(['ok' => true]); }
  if ($acao === 'treino') { // início do treino aberto (ms) ou 0 quando finaliza/cancela
    if (isset($v[$id])) { $ini = (int)floor(((float)($j['ini'] ?? 0)) / 1000); $ini = $ini > 0 ? min(time(), $ini) : 0;
      if ((int)($v[$id]['tr'] ?? 0) !== $ini) { $v[$id]['tr'] = $ini; $v[$id]['trav'] = 0; grava_in($v); } }
    sai(['ok' => true]);
  }
  if ($acao === 'copo') { if (isset($v[$id])) { $v[$id]['copo'] = time(); if (!empty($j['meta'])) $v[$id]['meta'] = (string)$j['meta']; grava_in($v); } sai(['ok' => true]); }
  $e = $j['sub']['endpoint'] ?? '';
  if (!endpoint_ok($e)) sai(['ok' => false, 'erro' => 'Serviço de aviso desconhecido.'], 400);
  if (!isset($v[$id]) && count($v) >= 2000) sai(['ok' => false, 'erro' => 'Limite de inscrições.'], 429);
  $antes = $v[$id] ?? [];
  $v[$id] = ['e' => $e, 'ini' => $hm($j['ini'] ?? '', 480), 'fim' => $hm($j['fim'] ?? '', 1320), 'cada' => max(30, min(240, (int)($j['cada'] ?? 90))),
    'tz' => max(-840, min(840, (int)($j['tz'] ?? 180))), 'meta' => (string)($j['meta'] ?? ($antes['meta'] ?? '')), 'copo' => (int)($antes['copo'] ?? 0), 'ultimo' => (int)($antes['ultimo'] ?? 0), 'tr' => (int)($antes['tr'] ?? 0), 'trav' => (int)($antes['trav'] ?? 0), 'em' => time()];
  grava_in($v); sai(['ok' => true]);
}

// cron: php push.php cron  (cPanel → Cron Jobs, a cada 10 minutos)
if ($acao === 'cron') {
  if (!$CLI) {
    $ka = $base . '/simplificando-atualizar-chave.txt'; $k = is_file($ka) ? trim((string)@file_get_contents($ka)) : '';
    if (strlen($k) < 12 || !hash_equals($k, (string)($_GET['chave'] ?? ''))) sai(['ok' => false, 'erro' => 'Só pelo agendamento do servidor.'], 403);
  }
  $ch = chaves(); $v = le_in(); $agora = time(); $env = 0; $tirou = 0;
  foreach ($v as $id => $s) {
    // treino aberto há 3h (até 14h): um aviso só, a qualquer hora
    $tr = (int)($s['tr'] ?? 0);
    if ($tr && empty($s['trav']) && $agora - $tr >= 3 * 3600 && $agora - $tr < 14 * 3600) {
      $st = envia($s['e'], $ch);
      if ($st == 404 || $st == 410) { unset($v[$id]); $tirou++; continue; }
      if ($st >= 200 && $st < 300) { $v[$id]['trav'] = $agora; $v[$id]['ultimo'] = $agora; $env++; }
      continue;
    }
    $local = $agora - $s['tz'] * 60; $min = (int)gmdate('G', $local) * 60 + (int)gmdate('i', $local); $dia = gmdate('Y-m-d', $local);
    if ($min < $s['ini'] || $min > $s['fim']) continue;
    if ($s['meta'] === $dia) continue;
    // primeiro aviso do dia: no horário de início; depois, a cada intervalo desde o último copo ou aviso
    $base0 = max($s['copo'], $s['ultimo']); if (gmdate('Y-m-d', $base0 - $s['tz'] * 60) !== $dia) $base0 = 0;
    if ($base0 && $agora - $base0 < $s['cada'] * 60) continue;
    $st = envia($s['e'], $ch);
    if ($st == 404 || $st == 410) { unset($v[$id]); $tirou++; continue; }
    if ($st >= 200 && $st < 300) { $v[$id]['ultimo'] = $agora; $env++; }
  }
  grava_in($v); sai(['ok' => true, 'enviados' => $env, 'removidos' => $tirou, 'inscritos' => count($v)]);
}
sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
