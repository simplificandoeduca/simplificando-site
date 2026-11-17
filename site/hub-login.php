<?php
// Entrada do hub (simplificandoeduca.com/hub) só com a senha do hub.
// A senha fica guardada embaralhada (password_hash) fora do site: ~/simplificando-hub-senha.txt. Ela nunca vai para o GitHub.
// Quem cria ou troca a senha é o dono do painel, já logado (acao=definir). Para entrar (acao=entrar), o servidor confere a senha
// e devolve um passe de entrada do Firebase da conta dona do painel, com a chave ~/simplificando-chave.json (a mesma do login.php).
// Limite: 6 tentativas erradas por hora de cada internet e 30 por hora no total.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function sai($a, $c = 200) { http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
$casa = dirname(__DIR__);
$arqS = $casa . '/simplificando-hub-senha.txt';
$acao = $_GET['acao'] ?? '';
$tem = function () use ($arqS) { $h = trim((string)@file_get_contents($arqS)); return strlen($h) > 20 ? $h : ''; };

if ($acao === 'ver') sai(['ok' => true, 'tem' => $tem() !== '']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false], 405);
$senha = (string)($_POST['senha'] ?? '');

// criar ou trocar a senha do hub: só o dono do painel, logado
if ($acao === 'definir') {
  require __DIR__ . '/dono.inc.php';
  if (!dono_uid()) sai(['ok' => false, 'erro' => 'Só o dono do painel pode criar a senha do hub. Saia e entre de novo e tente outra vez.'], 403);
  if (strlen($senha) < 6 || strlen($senha) > 64) sai(['ok' => false, 'erro' => 'A senha precisa ter de 6 a 64 caracteres.'], 400);
  if (@file_put_contents($arqS, password_hash($senha, PASSWORD_DEFAULT), LOCK_EX) === false) sai(['ok' => false, 'erro' => 'Não deu para guardar a senha no servidor.'], 500);
  @chmod($arqS, 0600);
  sai(['ok' => true]);
}
if ($acao !== 'entrar') sai(['ok' => false], 400);

// limite de tentativas erradas (por internet e no total)
$arqT = $casa . '/.simplificando-hub-tentativas.json'; $agora = time();
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'simp-hub');
$T = json_decode((string)@file_get_contents($arqT), true); if (!is_array($T)) $T = [];
foreach ($T as $k => $v) { $T[$k] = array_values(array_filter((array)$v, fn($x) => $agora - $x < 3600)); if (!$T[$k]) unset($T[$k]); }
$total = 0; foreach ($T as $v) $total += count($v);
if (count($T[$ip] ?? []) >= 6 || $total >= 30) sai(['ok' => false, 'erro' => 'Muitas tentativas. Espere um pouco e tente de novo.'], 429);
$hash = $tem();
if ($hash === '') sai(['ok' => false, 'semSenha' => true, 'erro' => 'A senha do hub ainda não foi criada. Entre uma vez com o e-mail do painel e crie a senha no hub.'], 409);
if (!password_verify($senha, $hash)) {
  $T[$ip][] = $agora; @file_put_contents($arqT, json_encode($T), LOCK_EX);
  sai(['ok' => false, 'erro' => 'Senha incorreta.'], 401);
}

// senha certa: passe de entrada da conta dona do painel (sistema/dono), válido por 1 hora
$k = is_file($casa . '/simplificando-chave.json') ? json_decode((string)file_get_contents($casa . '/simplificando-chave.json'), true) : null;
if (!$k || empty($k['private_key']) || empty($k['client_email']) || !function_exists('curl_init')) sai(['ok' => false, 'semServidor' => true, 'erro' => 'O servidor ainda não tem a chave do Firebase. Entre com o e-mail do painel.'], 503);
$pid = $k['project_id'] ?? 'simplificando-26c09';
$b = function ($x) { return rtrim(strtr(base64_encode($x), '+/', '-_'), '='); };
function pede($url, $body = null, $hdr = []) {
  $c = curl_init($url); $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $hdr];
  if ($body !== null) { $op[CURLOPT_POST] = true; $op[CURLOPT_POSTFIELDS] = $body; }
  curl_setopt_array($c, $op); $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c); return [$st, json_decode((string)$r, true)];
}
$j = $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/cloud-platform', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $agora, 'exp' => $agora + 600], JSON_UNESCAPED_SLASHES));
openssl_sign($j, $sg, $k['private_key'], 'sha256WithRSAEncryption');
[$st, $tk] = pede('https://oauth2.googleapis.com/token', http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $j . '.' . $b($sg)]));
if (empty($tk['access_token'])) sai(['ok' => false, 'semServidor' => true, 'erro' => 'O Firebase não respondeu. Tente de novo.'], 503);
[$st2, $d] = pede('https://firestore.googleapis.com/v1/projects/' . rawurlencode($pid) . '/databases/(default)/documents/sistema/dono', null, ['Authorization: Bearer ' . $tk['access_token']]);
$uid = ($st2 == 200 && isset($d['fields']['uid']['stringValue'])) ? $d['fields']['uid']['stringValue'] : '';
if (!$uid) sai(['ok' => false, 'erro' => 'O painel ainda não tem dono. Entre com o e-mail do painel.'], 409);
$ct = $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode(['iss' => $k['client_email'], 'sub' => $k['client_email'], 'aud' => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit', 'iat' => $agora, 'exp' => $agora + 3600, 'uid' => $uid], JSON_UNESCAPED_SLASHES));
openssl_sign($ct, $sg2, $k['private_key'], 'sha256WithRSAEncryption');
sai(['ok' => true, 'token' => $ct . '.' . $b($sg2)]);
