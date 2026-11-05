<?php
// Erros do app do aluno: o app manda quando algo quebra no celular; o painel (só o dono) lê e limpa.
// Guarda fora do site, em ~/.simplificando-erros.json (últimos 300, os repetidos viram um só com contador).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function sai($a, $code = 200) { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
$arq = dirname(__DIR__) . '/.simplificando-erros.json';
$acao = $_GET['acao'] ?? 'registrar';
function le($arq) { $v = json_decode((string)@file_get_contents($arq), true); return is_array($v) ? $v : ['erros' => [], 'ip' => []]; }
function grava($arq, $v) { @file_put_contents($arq, json_encode($v, JSON_UNESCAPED_UNICODE), LOCK_EX); }
$corta = function ($s, $n) { $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$s); return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n); };

if ($acao === 'registrar') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false], 405);
  $raw = file_get_contents('php://input', false, null, 0, 6000);
  $e = json_decode((string)$raw, true); if (!is_array($e) || empty($e['msg'])) sai(['ok' => false], 400);
  $v = le($arq); $agora = time();
  // no máximo 30 avisos por hora de cada internet
  $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'simp'); $v['ip'] = array_filter($v['ip'] ?? [], fn($x) => $agora - $x[1] < 3600);
  $n = 0; foreach ($v['ip'] as $x) if ($x[0] === $ip) $n++;
  if ($n >= 30) sai(['ok' => true]);
  $v['ip'][] = [$ip, $agora];
  $item = ['msg' => $corta($e['msg'], 300), 'onde' => $corta(($e['src'] ?? '') . ':' . ($e['line'] ?? '') . ':' . ($e['col'] ?? ''), 200), 'pilha' => $corta($e['stack'] ?? '', 900),
    'tela' => $corta($e['tela'] ?? '', 40), 'ver' => $corta($e['ver'] ?? '', 30), 'quem' => $corta($e['quem'] ?? '', 60), 'aparelho' => $corta($e['ua'] ?? '', 160)];
  $chave = md5($item['msg'] . $item['onde']);
  $ach = false;
  foreach ($v['erros'] as &$x) { if (($x['chave'] ?? '') === $chave) { $x['vezes'] = ($x['vezes'] ?? 1) + 1; $x['ultimo'] = $agora; if ($item['quem'] && !in_array($item['quem'], $x['pessoas'] ?? [])) $x['pessoas'] = array_slice(array_merge($x['pessoas'] ?? [], [$item['quem']]), -10); $x['ver'] = $item['ver']; $ach = true; break; } }
  unset($x);
  if (!$ach) $v['erros'][] = $item + ['chave' => $chave, 'vezes' => 1, 'primeiro' => $agora, 'ultimo' => $agora, 'pessoas' => $item['quem'] ? [$item['quem']] : []];
  usort($v['erros'], fn($a, $b) => $b['ultimo'] - $a['ultimo']);
  $v['erros'] = array_slice($v['erros'], 0, 300);
  grava($arq, $v); sai(['ok' => true]);
}
require __DIR__ . '/dono.inc.php';
if (!dono_uid()) sai(['ok' => false, 'erro' => 'Só o dono do painel vê os erros.'], 403);
if ($acao === 'ver') { $v = le($arq); sai(['ok' => true, 'erros' => array_map(function ($x) { unset($x['chave']); return $x; }, $v['erros'])]); }
if ($acao === 'limpar' && $_SERVER['REQUEST_METHOD'] === 'POST') { $v = le($arq); $v['erros'] = []; grava($arq, $v); sai(['ok' => true]); }
sai(['ok' => false, 'erro' => 'Pedido inválido.'], 400);
