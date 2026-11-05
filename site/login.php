<?php
// Entrada do aluno com personal só com a senha de 6 números (sem e-mail).
// O servidor procura de quem é esse código (no painel ou na senha nova que o aluno escolheu)
// e devolve um passe de entrada do Firebase só daquele aluno. Usa a chave ~/simplificando-chave.json
// (a mesma do e-mail de "esqueci a senha"). Até 12 tentativas por hora de cada internet.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function sai($a, $c = 200) { http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false], 405);
$casa = dirname(__DIR__);
$code = preg_replace('/\D/', '', (string)($_POST['codigo'] ?? ''));
if (strlen($code) !== 6) sai(['ok' => false, 'erro' => 'A senha tem 6 números.'], 400);

// limite de tentativas por internet
$arqT = $casa . '/.simplificando-login.json'; $agora = time();
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'simp-login');
$T = json_decode((string)@file_get_contents($arqT), true); if (!is_array($T)) $T = [];
foreach ($T as $k => $v) { $T[$k] = array_values(array_filter($v, fn($x) => $agora - $x < 3600)); if (!$T[$k]) unset($T[$k]); }
if (count($T[$ip] ?? []) >= 12) sai(['ok' => false, 'erro' => 'Muitas tentativas. Espere alguns minutos e tente de novo.'], 429);

$k = is_file($casa . '/simplificando-chave.json') ? json_decode((string)file_get_contents($casa . '/simplificando-chave.json'), true) : null;
if (!$k || empty($k['private_key']) || empty($k['client_email']) || !function_exists('curl_init')) sai(['ok' => false, 'semServidor' => true], 503);
$pid = $k['project_id'] ?? 'simplificando-26c09';
$b = function ($x) { return rtrim(strtr(base64_encode($x), '+/', '-_'), '='); };
function post($url, $body, $hdr = []) {
  $c = curl_init($url); curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $hdr]);
  $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c); return [$st, json_decode((string)$r, true)];
}
// token do Google com a chave da conta de serviço
$j = $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/cloud-platform', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $agora, 'exp' => $agora + 600], JSON_UNESCAPED_SLASHES));
openssl_sign($j, $sg, $k['private_key'], 'sha256WithRSAEncryption');
[$st, $tk] = post('https://oauth2.googleapis.com/token', http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $j . '.' . $b($sg)]));
if (empty($tk['access_token'])) sai(['ok' => false, 'semServidor' => true], 503);
$H = ['Authorization: Bearer ' . $tk['access_token'], 'Content-Type: application/json'];
$busca = function ($col, $campo) use ($pid, $code, $H) {
  [$st, $r] = post('https://firestore.googleapis.com/v1/projects/' . rawurlencode($pid) . '/databases/(default)/documents:runQuery',
    json_encode(['structuredQuery' => ['from' => [['collectionId' => $col]], 'where' => ['fieldFilter' => ['field' => ['fieldPath' => $campo], 'op' => 'EQUAL', 'value' => ['stringValue' => $code]]], 'limit' => 3]]), $H);
  $out = []; if (is_array($r)) foreach ($r as $x) if (!empty($x['document'])) $out[] = $x['document'];
  return $out;
};
$uids = [];
// 1) a senha que o aluno escolheu pelo "esqueci a senha" (codigos/{uid})
foreach ($busca('codigos', 'codigo') as $d) { $u = basename($d['name']); if ($u) $uids[$u] = 1; }
// 2) o código do painel (alunos/{id}.acessoCod e .uid), só com o acesso ativo
foreach ($busca('alunos', 'acessoCod') as $d) {
  $f = $d['fields'] ?? []; $u = $f['uid']['stringValue'] ?? '';
  $ativo = $f['acesso']['mapValue']['fields']['ativo']['booleanValue'] ?? true;
  if ($u && $ativo !== false) $uids[$u] = 1;
}
$uids = array_keys($uids);
if (count($uids) !== 1) { $T[$ip][] = $agora; @file_put_contents($arqT, json_encode($T), LOCK_EX); sai(['ok' => false, 'erro' => count($uids) ? 'Essa senha está repetida. Fale com seu personal para gerar outra.' : 'Senha não encontrada. Confira com seu personal.'], count($uids) ? 409 : 404); }
// passe de entrada (custom token) só deste aluno, válido por 1 hora
$ct = $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode(['iss' => $k['client_email'], 'sub' => $k['client_email'], 'aud' => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit', 'iat' => $agora, 'exp' => $agora + 3600, 'uid' => $uids[0]], JSON_UNESCAPED_SLASHES));
openssl_sign($ct, $sg2, $k['private_key'], 'sha256WithRSAEncryption');
sai(['ok' => true, 'token' => $ct . '.' . $b($sg2)]);
