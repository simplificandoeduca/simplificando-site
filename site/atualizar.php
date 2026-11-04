<?php
// Atualiza o site com a versão mais nova do GitHub (simplificandoeduca/simplificando-site, pasta site/).
// É chamado pelo botão "Atualizar o site" em Ajustes, no painel. Só baixa deste repositório fixo
// e só grava dentro desta pasta; no máximo uma atualização por minuto.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
const REPO = 'simplificandoeduca/simplificando-site';
const BRANCH = 'main';
function sai($a, $code = 200) { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function baixa($url) {
  if (function_exists('curl_init')) {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 90, CURLOPT_USERAGENT => 'simplificando-atualizador']);
    $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return ($r !== false && $st == 200) ? $r : null;
  }
  $r = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 90, 'header' => "User-Agent: simplificando-atualizador\r\n"]]));
  return $r === false ? null : $r;
}
function versao($html) { return preg_match('/Versão do painel: ([^<\'"\n]+)/u', (string)$html, $m) ? trim($m[1]) : ''; }
$aqui = __DIR__;
$acao = isset($_GET['acao']) ? $_GET['acao'] : 'ver';
if ($acao === 'ver') {
  $rem = baixa('https://raw.githubusercontent.com/' . REPO . '/' . BRANCH . '/site/personal/index.html?t=' . time());
  sai(['ok' => true, 'atual' => versao(@file_get_contents($aqui . '/personal/index.html')), 'nova' => versao($rem)]);
}
if ($acao !== 'atualizar' || $_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
$trava = dirname($aqui) . '/.simplificando-atualizacao';
if (is_file($trava) && time() - filemtime($trava) < 60) sai(['ok' => false, 'erro' => 'Acabou de atualizar. Espere um minuto e tente de novo.'], 429);
@touch($trava);
if (!class_exists('ZipArchive')) sai(['ok' => false, 'erro' => 'O servidor não abre arquivos .zip. Fale com o suporte da HostGator.'], 500);
$dados = baixa('https://codeload.github.com/' . REPO . '/zip/refs/heads/' . BRANCH);
if (!$dados) sai(['ok' => false, 'erro' => 'Não deu para baixar a versão nova do GitHub. Tente de novo em instantes.'], 502);
$tmp = tempnam(sys_get_temp_dir(), 'simp');
file_put_contents($tmp, $dados);
$z = new ZipArchive();
if ($z->open($tmp) !== true) { @unlink($tmp); sai(['ok' => false, 'erro' => 'O arquivo baixado veio com defeito. Tente de novo.'], 502); }
$n = 0; $falhas = 0;
for ($i = 0; $i < $z->numFiles; $i++) {
  $nome = $z->getNameIndex($i);
  if (!preg_match('#^[^/]+/site/(.+)$#', $nome, $m)) continue;
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
sai(['ok' => true, 'arquivos' => $n, 'falhas' => $falhas, 'versao' => versao(@file_get_contents($aqui . '/personal/index.html'))]);
