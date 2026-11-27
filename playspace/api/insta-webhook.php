<?php
/* =====================================================================
   Play Space 2.70 — o webhook do Instagram (cadastrado no app da Meta, com o "token de verificação" mostrado no Insta → Contas).
   GET: a Meta confere o endereço (devolve o hub.challenge). POST: as mensagens, assinadas com a chave secreta do app (X-Hub-Signature-256).
   Sempre responde rápido (a Meta tenta de novo se demorar).
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/conversas-lib.php';
header('Content-Type: text/plain; charset=utf-8');
set_exception_handler(function (Throwable $e) { registrarErro('[insta webhook] ' . $e->getMessage()); http_response_code(200); echo 'ok'; exit; });
$c = igCfg();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
  if ((string)($_GET['hub_mode'] ?? '') === 'subscribe' && hash_equals((string)$c['verificar'], (string)($_GET['hub_verify_token'] ?? ''))) { echo (string)($_GET['hub_challenge'] ?? ''); exit; }
  http_response_code(403); echo 'token de verificação errado'; exit;
}
$bruto = file_get_contents('php://input') ?: ''; if (strlen($bruto) > 2000000) { http_response_code(413); exit; }
$ass = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
if ($c['segredo'] === '' || !hash_equals('sha256=' . hash_hmac('sha256', $bruto, (string)$c['segredo']), $ass)) { http_response_code(403); echo 'assinatura'; exit; }
$j = json_decode($bruto, true);
if (is_array($j)) igWebhook($j);
echo 'ok';
