<?php
/* =====================================================================
   Play Space 2.70 — a volta do Instagram depois de liberar a conta (o "endereço de retorno" cadastrado no app da Meta).
   Confere que é o mesmo gestor que pediu (o código da sessão), troca o código pelo acesso de 60 dias, liga o webhook e traz as conversas
   mais recentes. Depois volta para o Insta, com o resultado.
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/conversas-lib.php';
function volta(string $msg, bool $ok): void { header('Location: ../insta/#contas=' . ($ok ? 'ok' : 'erro') . '&m=' . rawurlencode($msg)); exit; }
set_exception_handler(function (Throwable $e) { registrarErro('[insta retorno] ' . $e->getMessage()); volta($e->getMessage(), false); });
iniciarSessao();
$u = eu(); if (!$u || $u['papel'] !== 'gestor') volta('Entre no Play Space com o login de gestor e conecte de novo.', false);
$estado = (string)($_GET['state'] ?? ''); $certo = (string)($_SESSION['ig_estado'] ?? ''); $em = (int)($_SESSION['ig_estado_em'] ?? 0);
unset($_SESSION['ig_estado'], $_SESSION['ig_estado_em']); session_write_close();
if ($certo === '' || !hash_equals($certo, $estado) || time() - $em > 1800) volta('A liberação demorou ou veio de outro lugar. Toque em "Conectar uma conta do Instagram" de novo.', false);
if (!empty($_GET['error'])) volta('A conta não foi liberada no Instagram (' . mb_substr((string)($_GET['error_description'] ?? $_GET['error_reason'] ?? $_GET['error'] ?? ''), 0, 120) . ').', false);
$codigo = (string)($_GET['code'] ?? ''); if ($codigo === '') volta('O Instagram não mandou o código. Tente conectar de novo.', false);
cvTabelas();
$conta = igConectar($codigo);
$r = ['conversas' => 0, 'novas' => 0]; try { $r = igSincronizar((string)$conta['id'], false, 40); } catch (Throwable $e) { }
volta('@' . $conta['usuario'] . ' conectada · ' . $r['conversas'] . ' conversas trazidas', true);
