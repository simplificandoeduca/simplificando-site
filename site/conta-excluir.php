<?php
// Excluir a conta de quem treina sem personal (painel › Perfis › ficha da pessoa › Excluir conta). Só o dono do painel, logado.
// Apaga tudo da pessoa de uma vez: o resumo (perfis), o treino (autonomos), o progresso e o login (Firebase Authentication).
// Usa a chave do servidor (~/simplificando-chave.json, a mesma do login.php): as regras do Firebase não deixam o painel apagar
// o treino nem o login de outra pessoa direto do navegador.
// Robô de testes: SIMP_TESTE_DONO (token aceito como dono) e SIMP_TESTE_PUSH (só anota a exclusão, não apaga nada).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function sai($a, $c = 200) { http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false], 405);
$uid = (string)($_POST['uid'] ?? '');
if (!preg_match('/^[A-Za-z0-9_\-]{6,128}$/', $uid)) sai(['ok' => false, 'erro' => 'Conta inválida.'], 400);
require __DIR__ . '/dono.inc.php';
$teste = getenv('SIMP_TESTE_DONO');
if ($teste !== false && $teste !== '') { if (!hash_equals($teste, dono_token())) sai(['ok' => false, 'erro' => 'Só o dono do painel.'], 403); $dono = 'donoTeste01'; }
else { $dono = dono_uid(); if (!$dono) sai(['ok' => false, 'erro' => 'Só o dono do painel pode excluir contas. Saia e entre de novo.'], 403); }
if (hash_equals($dono, $uid)) sai(['ok' => false, 'erro' => 'Essa é a conta dona do painel. Ela não pode ser excluída por aqui.'], 400);
if ($f = getenv('SIMP_TESTE_PUSH')) { @file_put_contents($f, json_encode(['excluir' => $uid]) . "\n", FILE_APPEND | LOCK_EX); sai(['ok' => true, 'teste' => true]); }

$casa = dirname(__DIR__);
$k = is_file($casa . '/simplificando-chave.json') ? json_decode((string)file_get_contents($casa . '/simplificando-chave.json'), true) : null;
if (!$k || empty($k['private_key']) || empty($k['client_email']) || !function_exists('curl_init')) sai(['ok' => false, 'erro' => 'O servidor ainda não tem a chave do Firebase (simplificando-chave.json).'], 503);
$pid = $k['project_id'] ?? 'simplificando-26c09'; $agora = time();
$b = function ($x) { return rtrim(strtr(base64_encode($x), '+/', '-_'), '='); };
function pede($metodo, $url, $tok, $corpo = null) {
  $c = curl_init($url); $h = ['Authorization: Bearer ' . $tok];
  $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CUSTOMREQUEST => $metodo];
  if ($corpo !== null) { $op[CURLOPT_POSTFIELDS] = json_encode($corpo); $h[] = 'Content-Type: application/json'; }
  $op[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $op); $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
  return [$st, (string)$r];
}
$j = $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/identitytoolkit https://www.googleapis.com/auth/cloud-platform', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $agora, 'exp' => $agora + 600], JSON_UNESCAPED_SLASHES));
openssl_sign($j, $sg, $k['private_key'], 'sha256WithRSAEncryption');
$c = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $j . '.' . $b($sg)])]);
$t = json_decode((string)curl_exec($c), true); curl_close($c);
$tok = $t['access_token'] ?? ''; if (!$tok) sai(['ok' => false, 'erro' => 'O Google não respondeu. Tente de novo.'], 503);

$falhou = [];
foreach (['perfis', 'autonomos', 'progresso'] as $col) {
  [$st] = pede('DELETE', 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($pid) . '/databases/(default)/documents/' . $col . '/' . rawurlencode($uid), $tok);
  if ($st != 200 && $st != 404) $falhou[] = $col;
}
[$st, $r] = pede('POST', 'https://identitytoolkit.googleapis.com/v1/projects/' . rawurlencode($pid) . '/accounts:delete', $tok, ['localId' => $uid]);
if ($st != 200 && strpos($r, 'USER_NOT_FOUND') === false) $falhou[] = 'login';
if ($falhou) sai(['ok' => false, 'erro' => 'Não deu para apagar tudo (' . implode(', ', $falhou) . '). Tente de novo.', 'falhou' => $falhou], 502);
sai(['ok' => true]);
