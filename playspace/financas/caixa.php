<?php
/* =====================================================================
   Play Space Finanças 2.2 (Play Space 1.61): o Controle de caixa
   Pedido do dono: "uma tela de controle de caixa diário: escolher a data e ver o que passou no caixa naquele dia, no crédito, no débito e
   no Pix; um campo em branco onde eu lanço os valores da Rede; ele compara e dá um positivo ou um negativo (não quero ninguém olhando se
   bateu 1.225); e um filtro para buscar o caixa de hoje, o de ontem ou o dia que eu escolher. É a primeira coisa do financeiro."
   - O que passou no caixa: o mesmo "Recebido por forma" do resumo (os pagamentos do dia menos o devolvido no dia, e a venda cancelada em
     outro dia continua no dia em que foi paga), separado por unidade (cada loja tem a sua maquininha da Rede).
   - O valor da Rede fica guardado por dia, unidade e forma (fin_caixa), com quem lançou e quando; cada mudança fica no histórico
     (fin_caixa_hist): nada se perde.
   - A conta é ao centavo: bateu (positivo) ou não bateu (negativo, com a diferença).
   ===================================================================== */
const CX_FORMAS = ['credito' => 'Crédito', 'debito' => 'Débito', 'pix' => 'Pix'];   /* as que passam pela Rede; o dinheiro aparece à parte */
const CX_DIAS_HIST = 14;

function cxPreparar(): void {
  static $ok = false; if ($ok) return; $ok = true;
  $db = db();
  $db->exec("CREATE TABLE IF NOT EXISTS fin_caixa (dia TEXT NOT NULL, unidade_id INTEGER NOT NULL, forma TEXT NOT NULL, valor REAL NOT NULL,
    por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '', PRIMARY KEY (dia, unidade_id, forma))");
  $db->exec("CREATE TABLE IF NOT EXISTS fin_caixa_hist (id INTEGER PRIMARY KEY, dia TEXT NOT NULL, unidade_id INTEGER NOT NULL, forma TEXT NOT NULL,
    antes REAL, depois REAL, por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '')");
}
function cxHoje(): string { return date('Y-m-d', agoraTs()); }
function cxDiaOk($d): string {
  $d = (string)$d;
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) falha('Escolha uma data que existe.');
  if ($d > cxHoje()) falha('Esse dia ainda não chegou.');
  return $d;
}
function cxMenosDias(string $d, int $n): string { return date('Y-m-d', strtotime($d . ' 12:00:00 -' . $n . ' day')); }

/* o que passou no caixa, por dia, unidade e forma: [dia][unidade][forma] = valor (o mesmo Recebido do resumo) */
function cxSistema(string $de, string $ate): array {
  $db = db(); $ini = $de . ' 00:00:00'; $fim = date('Y-m-d', strtotime($ate . ' 12:00:00 +1 day')) . ' 00:00:00'; $S = [];
  $soma = function (string $d, int $u, string $f, float $v) use (&$S) { $S[$d][$u][$f] = round(($S[$d][$u][$f] ?? 0) + $v, 2); };
  $q = $db->prepare('SELECT substr(p.quando, 1, 10) d, COALESCE(p.unidade_id, v.unidade_id, 0) u, p.forma f, COALESCE(SUM(p.valor), 0) t FROM pagamentos p JOIN vendas v ON v.id = p.venda_id
    WHERE p.quando >= ? AND p.quando < ? GROUP BY d, u, f');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) $soma((string)$l['d'], (int)$l['u'], (string)$l['f'], (float)$l['t']);
  /* o devolvido no dia sai do dia; a venda cancelada em outro dia (o pagamento dela é apagado) volta ao dia em que foi paga */
  $q = $db->prepare('SELECT substr(quando, 1, 10) d, COALESCE(unidade_id, 0) u, forma f, COALESCE(SUM(valor), 0) t FROM estornos WHERE quando >= ? AND quando < ? GROUP BY d, u, f');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) $soma((string)$l['d'], (int)$l['u'], (string)$l['f'], -(float)$l['t']);
  $q = $db->prepare('SELECT substr(pago_em, 1, 10) d, COALESCE(unidade_id, 0) u, forma f, COALESCE(SUM(valor), 0) t FROM estornos WHERE pago_em >= ? AND pago_em < ? GROUP BY d, u, f');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) $soma((string)$l['d'], (int)$l['u'], (string)$l['f'], (float)$l['t']);
  return $S;
}
/* o que foi lançado da Rede: [dia][unidade][forma] = ['v', 'por', 'em'] */
function cxRede(string $de, string $ate): array {
  cxPreparar(); $R = [];
  $q = db()->prepare('SELECT * FROM fin_caixa WHERE dia >= ? AND dia <= ?'); $q->execute([$de, $ate]);
  foreach ($q->fetchAll() as $l) $R[(string)$l['dia']][(int)$l['unidade_id']][(string)$l['forma']] = ['v' => round((float)$l['valor'], 2), 'por' => (string)$l['por'], 'em' => (string)$l['em']];
  return $R;
}
/* bateu? ok (bateu) · dif (não bateu) · falta (passou no caixa e a Rede não foi lançada) · '' (nada no caixa e nada lançado) */
function cxSituacao(float $sis, ?float $rede): string {
  if ($rede === null) return abs($sis) > 0.004 ? 'falta' : '';
  return abs(round($sis - $rede, 2)) < 0.005 ? 'ok' : 'dif';
}
/* o dia de uma unidade: as formas, os totais e a situação geral (a pior das formas) */
function cxUnidade(array $S, array $R, string $d, int $u): array {
  $formas = []; $ts = 0.0; $tr = 0.0; $temR = false; $pior = '';
  $ordem = ['' => 0, 'ok' => 1, 'falta' => 2, 'dif' => 3];
  foreach (CX_FORMAS as $f => $nome) {
    $sis = round((float)($S[$d][$u][$f] ?? 0), 2); $r = $R[$d][$u][$f] ?? null; $rv = $r ? (float)$r['v'] : null;
    $sit = cxSituacao($sis, $rv); if ($ordem[$sit] > $ordem[$pior]) $pior = $sit;
    $ts = round($ts + $sis, 2); if ($rv !== null) { $tr = round($tr + $rv, 2); $temR = true; }
    $formas[] = ['f' => $f, 'nome' => $nome, 'sis' => $sis, 'rede' => $rv, 'dif' => $rv === null ? null : round($rv - $sis, 2), 'sit' => $sit, 'por' => $r['por'] ?? '', 'em' => $r['em'] ?? ''];
  }
  $din = round((float)($S[$d][$u]['dinheiro'] ?? 0), 2);
  $outras = 0.0; foreach ((array)($S[$d][$u] ?? []) as $f => $v) if (!isset(CX_FORMAS[$f]) && $f !== 'dinheiro') $outras = round($outras + $v, 2);
  return ['u' => $u, 'formas' => $formas, 'sis' => $ts, 'rede' => $temR ? $tr : null, 'dinheiro' => $din, 'outras' => $outras,
    'total' => round($ts + $din + $outras, 2), 'sit' => $pior];
}
/* as unidades que aparecem: as ativas e as outras (sem unidade, inativas) só quando têm movimento ou valor lançado no dia */
function cxUnidadesDoDia(array $S, array $R, string $d): array {
  $us = []; foreach (db()->query('SELECT id FROM unidades WHERE ativo = 1 ORDER BY id')->fetchAll() as $x) $us[] = (int)$x['id'];
  foreach (array_merge(array_keys($S[$d] ?? []), array_keys($R[$d] ?? [])) as $u) {
    $u = (int)$u; if (in_array($u, $us, true)) continue;
    $tem = false; foreach ((array)($S[$d][$u] ?? []) as $v) if (abs((float)$v) > 0.004) $tem = true;
    if ($tem || !empty($R[$d][$u])) $us[] = $u;
  }
  return $us;
}

/* a tela: o dia escolhido (cada unidade) e os últimos dias (a situação de cada um) */
function cxDia(array $in): array {
  $d = cxDiaOk($in['dia'] ?? cxHoje());
  /* os últimos dias vão até hoje (para ver o que ainda falta conferir); um dia mais antigo mostra os dias até ele */
  $fimH = $d >= cxMenosDias(cxHoje(), CX_DIAS_HIST - 1) ? cxHoje() : $d;
  $de = cxMenosDias($fimH, CX_DIAS_HIST - 1);
  $S = cxSistema($de, $fimH); $R = cxRede($de, $fimH);
  $unis = array_map(fn($u) => cxUnidade($S, $R, $d, $u), cxUnidadesDoDia($S, $R, $d));
  $hist = [];
  for ($i = 0; $i < CX_DIAS_HIST; $i++) {
    $x = cxMenosDias($fimH, $i); $ordem = ['' => 0, 'ok' => 1, 'falta' => 2, 'dif' => 3]; $pior = ''; $n = 0;
    foreach (cxUnidadesDoDia($S, $R, $x) as $u) { $c = cxUnidade($S, $R, $x, $u); if ($ordem[$c['sit']] > $ordem[$pior]) $pior = $c['sit']; if ($c['sit'] !== '') $n++; }
    $hist[] = ['dia' => $x, 'sit' => $pior];
  }
  return ['dia' => $d, 'hoje' => cxHoje(), 'unidades' => $unis, 'nomes' => unidades(), 'hist' => $hist];
}
/* "1.225,50", "1225.5", "R$ 1.225" → 1225.5 (vazio → null: tira o valor lançado) */
function cxValor($v): ?float {
  if ($v === null) return null;
  if (is_int($v) || is_float($v)) $n = (float)$v;
  else {
    $t = trim(str_replace(['R$', ' ', "\u{00a0}"], '', (string)$v)); if ($t === '') return null;
    if (strpos($t, ',') !== false) $t = str_replace(['.', ','], ['', '.'], $t);
    elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) $t = str_replace('.', '', $t);
    if (!is_numeric($t)) falha('Digite o valor em reais (ex.: 1.225,50).');
    $n = (float)$t;
  }
  if ($n < 0 || $n > 10000000) falha('Digite um valor entre R$ 0,00 e R$ 10.000.000,00.');
  return round($n, 2);
}
/* lança (ou tira) o valor da Rede de uma forma, num dia e numa unidade */
function cxSalvar(array $in, array $eu): array {
  cxPreparar(); $db = db();
  $d = cxDiaOk($in['dia'] ?? ''); $u = (int)($in['u'] ?? -1); $f = (string)($in['forma'] ?? '');
  if (!isset(CX_FORMAS[$f])) falha('Escolha crédito, débito ou Pix.');
  if ($u < 0) falha('Escolha a unidade.');
  if ($u > 0) { $q = $db->prepare('SELECT 1 FROM unidades WHERE id = ?'); $q->execute([$u]); if (!$q->fetchColumn()) falha('Unidade não encontrada.', 404); }
  $v = cxValor($in['valor'] ?? null);
  $q = $db->prepare('SELECT valor FROM fin_caixa WHERE dia = ? AND unidade_id = ? AND forma = ?'); $q->execute([$d, $u, $f]); $antes = $q->fetchColumn();
  $antes = $antes === false ? null : round((float)$antes, 2);
  if ($antes !== $v) {
    $quem = (string)$eu['nome']; $em = agora();
    if ($v === null) $db->prepare('DELETE FROM fin_caixa WHERE dia = ? AND unidade_id = ? AND forma = ?')->execute([$d, $u, $f]);
    else $db->prepare('INSERT OR REPLACE INTO fin_caixa (dia, unidade_id, forma, valor, por, em) VALUES (?, ?, ?, ?, ?, ?)')->execute([$d, $u, $f, $v, $quem, $em]);
    $db->prepare('INSERT INTO fin_caixa_hist (dia, unidade_id, forma, antes, depois, por, em) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$d, $u, $f, $antes, $v, $quem, $em]);
  }
  return cxDia(['dia' => $d]);
}
