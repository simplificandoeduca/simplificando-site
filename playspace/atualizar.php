<?php
// Play Space: atualiza esta pasta com a versão mais nova do GitHub (simplificandoeduca/simplificando-site, pasta playspace/).
// É chamado pelo selo de versão no topo da tela: "ver" confere se há versão nova; "atualizar" baixa o pacote do ramo inteiro
// (codeload, que nunca entrega versão velha) e troca cada arquivo de playspace/ (grava .novo e renomeia, para nunca deixar
// arquivo pela metade). A versão "no ar" é lida do próprio index.html, e a versão nova é conferida dentro do pacote baixado:
// se o pacote vier com a tela antiga, nada é gravado. Só baixa deste repositório fixo, só grava dentro desta pasta,
// só aceita pedido vindo da própria página e no máximo um por minuto.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
const REPO = 'simplificandoeduca/simplificando-site';
const RAMOS = ['main', 'claude/determined-tesla-vn2e2k']; // vale a versão mais alta entre eles (o 2º é o ramo de trabalho, até juntar no main)
function sai($a, $code = 200) { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function baixa($url, $tempo = 60) {
  if (function_exists('curl_init')) {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => $tempo, CURLOPT_USERAGENT => 'playspace-atualizador']);
    $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return ($r !== false && $st == 200) ? $r : null;
  }
  $r = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => $tempo, 'header' => "User-Agent: playspace-atualizador\r\n"]]));
  return $r === false ? null : $r;
}
function versaoDoHtml($html) { return preg_match("/const VERSAO = '([0-9.]+)'/", (string)$html, $m) ? $m[1] : ''; }
$aqui = __DIR__;
$atual = versaoDoHtml(@file_get_contents($aqui . '/index.html')) ?: '0';
// a versão mais nova entre os ramos: lê o versao.json de cada um (o GitHub pode demorar até 5 min para mostrar a última)
$melhor = null;
foreach (RAMOS as $ramo) {
  $j = json_decode((string)baixa('https://raw.githubusercontent.com/' . REPO . '/' . $ramo . '/playspace/versao.json?t=' . time(), 20), true);
  if (!is_array($j) || empty($j['versao'])) continue;
  if ($melhor === null || version_compare((string)$j['versao'], (string)$melhor['versao'], '>')) $melhor = ['versao' => (string)$j['versao'], 'ramo' => $ramo];
}
$acao = isset($_GET['acao']) ? $_GET['acao'] : 'ver';
if ($acao === 'ver') sai(['ok' => true, 'atual' => $atual, 'nova' => $melhor ? $melhor['versao'] : '', 'ramo' => $melhor ? $melhor['ramo'] : '', 'erro' => $melhor ? '' : 'Não consegui ler a versão no GitHub.']);
if ($acao !== 'atualizar' || $_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
// só a própria página pede a atualização (não um link de fora)
$site = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
$origem = isset($_SERVER['HTTP_ORIGIN']) ? parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) : (isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '');
$meuHost = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : '';
if (($site !== '' && $site !== 'same-origin') || ($origem !== '' && $origem !== $meuHost) || !isset($_SERVER['HTTP_X_PEDIDO'])) sai(['ok' => false, 'erro' => 'A atualização só pode ser pedida de dentro do próprio Play Space.'], 403);
if (!$melhor) sai(['ok' => false, 'erro' => 'Não consegui ler a versão nova no GitHub. Tente de novo em instantes.'], 502);
if (!version_compare($melhor['versao'], $atual, '>')) sai(['ok' => false, 'erro' => 'Você já está na versão mais nova (v' . $atual . ').'], 409);
$trava = $aqui . '/.atualizando';
if (is_file($trava) && time() - filemtime($trava) < 60) sai(['ok' => false, 'erro' => 'Acabou de atualizar. Espere um minuto e tente de novo.'], 429);
@touch($trava);
if (!class_exists('ZipArchive')) sai(['ok' => false, 'erro' => 'O servidor não abre arquivos .zip (cPanel → Selecionar versão do PHP → zip).'], 500);
// o pacote do ramo inteiro, direto do GitHub (sem cache): só a pasta playspace/ interessa
$dados = baixa('https://codeload.github.com/' . REPO . '/zip/refs/heads/' . $melhor['ramo'], 120);
if (!$dados) sai(['ok' => false, 'erro' => 'Não deu para baixar o pacote do GitHub. Tente de novo em instantes.'], 502);
$tmp = tempnam(sys_get_temp_dir(), 'plsp'); file_put_contents($tmp, $dados);
$z = new ZipArchive();
if ($z->open($tmp) !== true) { @unlink($tmp); sai(['ok' => false, 'erro' => 'O pacote baixado veio com defeito. Tente de novo.'], 502); }
$arquivos = [];
for ($i = 0; $i < $z->numFiles; $i++) {
  $nome = $z->getNameIndex($i);
  if (!preg_match('#^[^/]+/playspace/(.+)$#', $nome, $m)) continue;
  $rel = $m[1];
  if (substr($rel, -1) === '/' || strpos($rel, '..') !== false || $rel[0] === '/' || strpos($rel, "\0") !== false || !preg_match('#^[A-Za-z0-9_][A-Za-z0-9_./-]*$#', $rel)) continue;
  $conteudo = $z->getFromIndex($i);
  if ($conteudo !== false) $arquivos[$rel] = $conteudo;
}
$z->close(); @unlink($tmp);
if (!isset($arquivos['index.html'])) sai(['ok' => false, 'erro' => 'O pacote veio sem a pasta playspace/.'], 502);
// a versão de verdade é a que está dentro da tela baixada: se o GitHub entregou a tela velha, não grava nada
$versaoNova = versaoDoHtml($arquivos['index.html']);
if ($versaoNova === '' || !version_compare($versaoNova, $atual, '>')) sai(['ok' => false, 'erro' => 'O GitHub ainda está entregando a versão v' . ($versaoNova ?: '?') . '. Espere uns minutos e tente de novo.'], 503);
$n = 0; $falhas = [];
foreach ($arquivos as $rel => $conteudo) {
  if ($rel === 'versao.json') continue; // por último
  $dest = $aqui . '/' . $rel; $dir = dirname($dest);
  if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $falhas[] = $rel . ' (pasta)'; continue; }
  $t = $dest . '.novo';
  if (@file_put_contents($t, $conteudo) === false || !@rename($t, $dest)) { @unlink($t); $falhas[] = $rel . ' (gravar)'; continue; }
  @chmod($dest, 0644); $n++;
}
if (!$n) sai(['ok' => false, 'erro' => 'Nenhum arquivo foi copiado. ' . implode(', ', $falhas)], 500);
if (isset($arquivos['versao.json'])) { @file_put_contents($aqui . '/versao.json.novo', $arquivos['versao.json']); @rename($aqui . '/versao.json.novo', $aqui . '/versao.json'); }
@file_put_contents($aqui . '/atualizacoes.log', date('Y-m-d H:i') . " · v$atual → v$versaoNova ({$melhor['ramo']}) · $n arquivos" . ($falhas ? ' · falhou: ' . implode(', ', $falhas) : '') . "\n", FILE_APPEND);
sai(['ok' => true, 'versao' => $versaoNova, 'arquivos' => $n, 'falhas' => $falhas]);
