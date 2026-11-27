<?php
/* =====================================================================
   Play Space 2.70 — o webhook de cada número de WhatsApp do Insta: a Evolution API chama aqui a cada mensagem (do cliente ou da loja),
   ao conectar (o lote do histórico) e quando o número cai. Endereço: api/insta-zap.php?c=NUMERO&t=TOKEN (o token nasce com o número).
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/conversas-lib.php';
header('Content-Type: application/json; charset=utf-8');
set_exception_handler(function (Throwable $e) { registrarErro('[insta zap] ' . $e->getMessage()); echo json_encode(['ok' => false]); exit; });
$id = (string)($_GET['c'] ?? ''); $t = (string)($_GET['t'] ?? '');
$conta = null; foreach (zapCfg()['contas'] as $x) if ((string)$x['id'] === $id) $conta = $x;
if (!$conta || $t === '' || !hash_equals((string)$conta['token'], $t)) { http_response_code(403); echo json_encode(['ok' => false, 'erro' => 'token']); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok' => true, 'webhook' => 'Play Space Insta']); exit; }
$bruto = file_get_contents('php://input') ?: ''; if (strlen($bruto) > 20000000) { http_response_code(413); exit; }
$j = json_decode($bruto, true);
$n = is_array($j) ? zapWebhook($conta, $j) : 0;
echo json_encode(['ok' => true, 'guardadas' => $n]);
