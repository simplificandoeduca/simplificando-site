<?php
// Play Space: atualiza esta pasta com a versão mais nova do GitHub (simplificandoeduca/simplificando-site, pasta playspace/).
// É chamado pelo selo de versão no topo da tela: "ver" confere se há versão nova; "atualizar" baixa os arquivos listados no
// versao.json do GitHub e troca cada um (grava .novo e renomeia, para nunca deixar arquivo pela metade). Só baixa deste
// repositório fixo, só grava dentro desta pasta, só aceita pedido vindo da própria página e no máximo um por minuto.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
const REPO = 'simplificandoeduca/simplificando-site';
const RAMOS = ['main', 'claude/determined-tesla-vn2e2k']; // vale a versão mais alta entre eles (o 2º é o ramo de trabalho, até juntar no main)
function sai($a, $code = 200) { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function baixa($url) {
  if (function_exists('curl_init')) {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'playspace-atualizador']);
    $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return ($r !== false && $st == 200) ? $r : null;
  }
  $r = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 60, 'header' => "User-Agent: playspace-atualizador\r\n"]]));
  return $r === false ? null : $r;
}
$aqui = __DIR__;
$local = json_decode((string)@file_get_contents($aqui . '/versao.json'), true);
$atual = is_array($local) && !empty($local['versao']) ? (string)$local['versao'] : (preg_match("/const VERSAO = '([0-9.]+)'/", (string)@file_get_contents($aqui . '/index.html'), $m) ? $m[1] : '0');
// a versão mais nova entre os ramos: lê o versao.json de cada um
$melhor = null;
foreach (RAMOS as $ramo) {
  $j = json_decode((string)baixa('https://raw.githubusercontent.com/' . REPO . '/' . $ramo . '/playspace/versao.json?t=' . time()), true);
  if (!is_array($j) || empty($j['versao']) || empty($j['arquivos']) || !is_array($j['arquivos'])) continue;
  if ($melhor === null || version_compare((string)$j['versao'], (string)$melhor['versao'], '>')) $melhor = $j + ['ramo' => $ramo];
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
if (!version_compare((string)$melhor['versao'], $atual, '>')) sai(['ok' => false, 'erro' => 'Você já está na versão mais nova (v' . $atual . ').'], 409);
$trava = $aqui . '/.atualizando';
if (is_file($trava) && time() - filemtime($trava) < 60) sai(['ok' => false, 'erro' => 'Acabou de atualizar. Espere um minuto e tente de novo.'], 429);
@touch($trava);
$base = 'https://raw.githubusercontent.com/' . REPO . '/' . $melhor['ramo'] . '/playspace/';
$n = 0; $falhas = [];
foreach ($melhor['arquivos'] as $rel) {
  $rel = (string)$rel;
  if ($rel === '' || !preg_match('#^[A-Za-z0-9_][A-Za-z0-9_./-]*$#', $rel) || strpos($rel, '..') !== false || $rel === 'versao.json') { $falhas[] = $rel . ' (nome)'; continue; }
  $conteudo = baixa($base . $rel . '?t=' . time());
  if ($conteudo === null) { $falhas[] = $rel . ' (baixar)'; continue; }
  $dest = $aqui . '/' . $rel; $dir = dirname($dest);
  if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $falhas[] = $rel . ' (pasta)'; continue; }
  $t = $dest . '.novo';
  if (@file_put_contents($t, $conteudo) === false || !@rename($t, $dest)) { @unlink($t); $falhas[] = $rel . ' (gravar)'; continue; }
  @chmod($dest, 0644); $n++;
}
if (!$n) sai(['ok' => false, 'erro' => 'Nenhum arquivo foi copiado. ' . implode(', ', $falhas)], 500);
// o versao.json por último: só depois que os arquivos da versão nova estão no lugar
$novoJson = ['versao' => $melhor['versao'], 'arquivos' => array_values($melhor['arquivos'])];
@file_put_contents($aqui . '/versao.json.novo', json_encode($novoJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); @rename($aqui . '/versao.json.novo', $aqui . '/versao.json');
@file_put_contents($aqui . '/atualizacoes.log', date('Y-m-d H:i') . " · v$atual → v{$melhor['versao']} ({$melhor['ramo']}) · $n arquivos" . ($falhas ? ' · falhou: ' . implode(', ', $falhas) : '') . "\n", FILE_APPEND);
sai(['ok' => true, 'versao' => $melhor['versao'], 'arquivos' => $n, 'falhas' => $falhas]);
