<?php
/* =====================================================================
   Play Space — o que a API e o idface.php compartilham sobre o aparelho
   ===================================================================== */
declare(strict_types=1);

function idfaceCfg(): array { $c = cfg('idface', null); $c = is_array($c) ? $c : []; return $c + ['ip' => '', 'usuario' => 'admin', 'nome' => 'Recepção', 'ligado' => true]; }
function chavePonte(): string { $k = cfg('ponte_chave'); if (!$k) { $k = bin2hex(random_bytes(20)); gravarCfg('ponte_chave', $k); } return $k; }
function urlIdface(): string { return urlBase() . '/api/idface.php'; }
function urlPush(): string { return urlIdface() . '/' . chavePonte(); }
// o que a tela de Ajustes mostra: a bolinha, a última vez que o aparelho falou, a fila do que falta mandar
function idfaceEstado(): array {
  $c = idfaceCfg(); $ps = cfg('push_estado', []); $ps = is_array($ps) ? $ps : []; $pe = cfg('ponte_estado', []); $pe = is_array($pe) ? $pe : [];
  $em = $ps['em'] ?? $pe['em'] ?? null;
  $seg = $em ? time() - strtotime((string)$em) : null;
  $fila = count(cfgComo('mandar_')) + count(cfgComo('foto_')) + count(cfgComo('apagar_')) + count(cfgComo('renomear_')) + count(cfgComo('pedirosto_')) + count(array_filter(array_keys(cfgComo('regra_')), fn($k) => preg_match('/^regra_\d+$/', $k)));
  return ['ip' => $c['ip'], 'usuario' => $c['usuario'], 'nome' => $c['nome'], 'ligado' => (bool)$c['ligado'], 'chave' => chavePonte(), 'push_url' => urlPush(), 'url_ponte' => urlIdface(),
    'ultimo_contato' => $em, 'segundos' => $seg, 'ativo' => $seg !== null && $seg < 120 && $c['ligado'], 'ultima_batida' => $ps['ultima_batida'] ?? $pe['ultima_batida'] ?? null, 'erro' => (string)($ps['erro'] ?? $pe['erro'] ?? ''), 'fila' => $fila, 'periodo' => $ps['periodo'] ?? null, 'modo' => !empty($ps['em']) ? 'direto' : (!empty($pe['em']) ? 'ponte' : '')];
}
