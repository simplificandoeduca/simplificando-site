<?php
// Atualiza SÓ esta pasta (/financas) com a versão mais nova do GitHub (simplificandoeduca/simplificando-site, pasta site/financas).
// É chamado pelo botão "Atualizar esta página" em Ajustes, no Finanças, só com o login da conta dona da página
// (financas/dono no Firebase). Independente do painel do personal: não mexe em nada fora desta pasta.
// Só baixa deste repositório fixo e só grava dentro desta pasta; no máximo uma atualização por minuto.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
const REPO = 'simplificandoeduca/simplificando-site';
const BRANCH = 'main';
function sai($a, $code = 200) { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function http($url, $post = null) {
  if (function_exists('curl_init')) {
    $c = curl_init($url);
    $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 90, CURLOPT_USERAGENT => 'financas-atualizador'];
    if ($post !== null) { $op[CURLOPT_POST] = true; $op[CURLOPT_POSTFIELDS] = json_encode($post); $op[CURLOPT_HTTPHEADER] = ['Content-Type: application/json']; }
    curl_setopt_array($c, $op);
    $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return [$st, $r === false ? null : $r];
  }
  $ctx = stream_context_create(['http' => ['timeout' => 90, 'header' => "User-Agent: financas-atualizador\r\n" . ($post !== null ? "Content-Type: application/json\r\n" : ''), 'method' => $post !== null ? 'POST' : 'GET', 'content' => $post !== null ? json_encode($post) : null, 'ignore_errors' => true]]);
  $r = @file_get_contents($url, false, $ctx);
  $st = 0; if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $st = (int)$m[1];
  return [$st, $r === false ? null : $r];
}
function baixa($url) { [$st, $r] = http($url); return ($st == 200 && $r !== null) ? $r : null; }
function versao($html) { return preg_match('/Versão do Finanças: ([^<\'"\n]+)/u', (string)$html, $m) ? trim($m[1]) : ''; }
$aqui = __DIR__;
$acao = isset($_GET['acao']) ? $_GET['acao'] : 'ver';
if ($acao === 'ver') {
  $rem = baixa('https://raw.githubusercontent.com/' . REPO . '/' . BRANCH . '/site/financas/index.html?t=' . time());
  sai(['ok' => true, 'atual' => versao(@file_get_contents($aqui . '/index.html')), 'nova' => versao($rem)]);
}
if ($acao !== 'atualizar' || $_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);

// só a conta dona desta página (financas/dono) pode atualizar: confere o token de login do Firebase
function cfg_fb() {
  $js = @file_get_contents(__DIR__ . '/firebase-config.js');
  $k = preg_match('/apiKey:\s*"([^"]+)"/', (string)$js, $a) ? $a[1] : '';
  $p = preg_match('/projectId:\s*"([^"]+)"/', (string)$js, $b) ? $b[1] : '';
  return [$k, $p];
}
function token_fb() {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
  if (!$h && function_exists('getallheaders')) { foreach (getallheaders() as $n => $v) if (strtolower($n) === 'authorization') $h = $v; }
  if (preg_match('/Bearer\s+(\S+)/', $h, $m)) return $m[1];
  return isset($_POST['token']) ? (string)$_POST['token'] : '';
}
function dono_ok() {
  [$key, $pid] = cfg_fb(); $tok = token_fb();
  if (!$key || !$pid || strlen($tok) < 100) return false;
  [$st, $r] = http('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($key), ['idToken' => $tok]);
  $j = json_decode((string)$r, true);
  $uid = ($st == 200 && isset($j['users'][0]['localId'])) ? $j['users'][0]['localId'] : '';
  if (!$uid) return false;
  [$st2, $d] = http('https://firestore.googleapis.com/v1/projects/' . rawurlencode($pid) . '/databases/(default)/documents/financas/dono?key=' . urlencode($key));
  $dd = json_decode((string)$d, true);
  $dono = ($st2 == 200 && isset($dd['fields']['uid']['stringValue'])) ? $dd['fields']['uid']['stringValue'] : '';
  return $dono !== '' && hash_equals($dono, $uid);
}
if (!dono_ok()) sai(['ok' => false, 'erro' => 'Só a conta dona desta página pode atualizar. Saia, entre de novo e tente outra vez.'], 403);
$trava = $aqui . '/.atualizando';
if (is_file($trava) && time() - filemtime($trava) < 60) sai(['ok' => false, 'erro' => 'Acabou de atualizar. Espere um minuto e tente de novo.'], 429);
@touch($trava);
if (!class_exists('ZipArchive')) sai(['ok' => false, 'erro' => 'O servidor não abre arquivos .zip. Fale com o suporte da HostGator.'], 500);
$dados = baixa('https://codeload.github.com/' . REPO . '/zip/refs/heads/' . BRANCH);
if (!$dados) sai(['ok' => false, 'erro' => 'Não deu para baixar a versão nova do GitHub. Tente de novo em instantes.'], 502);
$tmp = tempnam(sys_get_temp_dir(), 'fin');
file_put_contents($tmp, $dados);
$z = new ZipArchive();
if ($z->open($tmp) !== true) { @unlink($tmp); sai(['ok' => false, 'erro' => 'O arquivo baixado veio com defeito. Tente de novo.'], 502); }
$n = 0; $falhas = 0;
for ($i = 0; $i < $z->numFiles; $i++) {
  $nome = $z->getNameIndex($i);
  if (!preg_match('#^[^/]+/site/financas/(.+)$#', $nome, $m)) continue; // só a pasta do Finanças
  $rel = $m[1];
  if (substr($rel, -1) === '/' || strpos($rel, '..') !== false || $rel[0] === '/' || strpos($rel, "\0") !== false) continue;
  $dest = $aqui . '/' . $rel; $dir = dirname($dest);
  if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $falhas++; continue; }
  $conteudo = $z->getFromIndex($i);
  if ($conteudo === false) { $falhas++; continue; }
  $t = $dest . '.novo';
  if (@file_put_contents($t, $conteudo) === false || !@rename($t, $dest)) { @unlink($t); $falhas++; continue; }
  $n++;
}
$z->close(); @unlink($tmp);
if (!$n) sai(['ok' => false, 'erro' => 'Nenhum arquivo foi copiado. Fale com o suporte.'], 500);
sai(['ok' => true, 'arquivos' => $n, 'falhas' => $falhas, 'versao' => versao(@file_get_contents($aqui . '/index.html'))]);
