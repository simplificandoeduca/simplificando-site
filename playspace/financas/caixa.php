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
  /* 2.4: o valor da Rede pode vir do PDF da Rede, com a taxa, o líquido e a quantidade de vendas */
  $tem = array_column($db->query('PRAGMA table_info(fin_caixa)')->fetchAll(), 'name');
  foreach (['taxa' => 'REAL', 'liquido' => 'REAL', 'qtde' => 'INTEGER', 'origem' => "TEXT NOT NULL DEFAULT 'manual'", 'relatorio_id' => 'INTEGER'] as $c => $t)
    if (!in_array($c, $tem, true)) $db->exec("ALTER TABLE fin_caixa ADD COLUMN $c $t");
  /* os PDFs da Rede anexados (o arquivo fica na pasta de dados; aqui, o que foi lido dele) */
  $db->exec("CREATE TABLE IF NOT EXISTS fin_caixa_rel (id INTEGER PRIMARY KEY, unidade_id INTEGER NOT NULL, de TEXT NOT NULL, ate TEXT NOT NULL, ecs TEXT NOT NULL DEFAULT '',
    arquivo TEXT NOT NULL DEFAULT '', nome TEXT NOT NULL DEFAULT '', dados TEXT NOT NULL DEFAULT '', por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '')");
  /* os consertos do caixa: um valor a mais ou a menos numa forma, com o porquê (nada se apaga: tirar marca quem tirou) */
  $db->exec("CREATE TABLE IF NOT EXISTS fin_caixa_ajustes (id INTEGER PRIMARY KEY, dia TEXT NOT NULL, unidade_id INTEGER NOT NULL, forma TEXT NOT NULL, valor REAL NOT NULL,
    obs TEXT NOT NULL DEFAULT '', por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '', removido_por TEXT NOT NULL DEFAULT '', removido_em TEXT)");
  $db->exec('CREATE INDEX IF NOT EXISTS fin_caixa_aj_dia ON fin_caixa_ajustes (dia, unidade_id)');
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
  foreach ($q->fetchAll() as $l) $R[(string)$l['dia']][(int)$l['unidade_id']][(string)$l['forma']] = ['v' => round((float)$l['valor'], 2), 'por' => (string)$l['por'], 'em' => (string)$l['em'],
    'taxa' => $l['taxa'] === null ? null : round((float)$l['taxa'], 2), 'liquido' => $l['liquido'] === null ? null : round((float)$l['liquido'], 2), 'qtde' => $l['qtde'] === null ? null : (int)$l['qtde'],
    'origem' => (string)$l['origem'], 'rel' => $l['relatorio_id'] === null ? null : (int)$l['relatorio_id']];
  return $R;
}
/* os consertos do caixa: [dia][unidade] = ['soma' => [forma => total], 'lista' => [...]] (só os que não foram tirados) */
function cxAjustes(string $de, string $ate): array {
  cxPreparar(); $A = [];
  $q = db()->prepare('SELECT * FROM fin_caixa_ajustes WHERE dia >= ? AND dia <= ? AND removido_em IS NULL ORDER BY id'); $q->execute([$de, $ate]);
  foreach ($q->fetchAll() as $l) { $d = (string)$l['dia']; $u = (int)$l['unidade_id']; $f = (string)$l['forma']; $v = round((float)$l['valor'], 2);
    $A[$d][$u]['soma'][$f] = round(($A[$d][$u]['soma'][$f] ?? 0) + $v, 2);
    $A[$d][$u]['lista'][] = ['id' => (int)$l['id'], 'f' => $f, 'nome' => CX_FORMAS[$f] ?? $f, 'v' => $v, 'obs' => (string)$l['obs'], 'por' => (string)$l['por'], 'em' => (string)$l['em']]; }
  return $A;
}
/* bateu? ok (bateu) · dif (não bateu) · falta (passou no caixa e a Rede não foi lançada) · '' (nada no caixa e nada lançado) */
function cxSituacao(float $sis, ?float $rede): string {
  if ($rede === null) return abs($sis) > 0.004 ? 'falta' : '';
  return abs(round($sis - $rede, 2)) < 0.005 ? 'ok' : 'dif';
}
/* o dia de uma unidade: as formas, os totais e a situação geral (a pior das formas) */
function cxUnidade(array $S, array $R, string $d, int $u, array $A = []): array {
  $formas = []; $ts = 0.0; $tr = 0.0; $temR = false; $pior = '';
  $ordem = ['' => 0, 'ok' => 1, 'falta' => 2, 'dif' => 3];
  foreach (CX_FORMAS as $f => $nome) {
    /* 2.4: o conserto entra na conta do sistema (o que o sistema diz + o conserto) e é isso que é comparado com a Rede */
    $sis0 = round((float)($S[$d][$u][$f] ?? 0), 2); $aj = round((float)($A[$d][$u]['soma'][$f] ?? 0), 2); $sis = round($sis0 + $aj, 2);
    $r = $R[$d][$u][$f] ?? null; $rv = $r ? (float)$r['v'] : null;
    $sit = cxSituacao($sis, $rv); if ($ordem[$sit] > $ordem[$pior]) $pior = $sit;
    $ts = round($ts + $sis, 2); if ($rv !== null) { $tr = round($tr + $rv, 2); $temR = true; }
    $formas[] = ['f' => $f, 'nome' => $nome, 'sis' => $sis, 'sis0' => $sis0, 'aj' => $aj, 'rede' => $rv, 'dif' => $rv === null ? null : round($rv - $sis, 2), 'sit' => $sit, 'por' => $r['por'] ?? '', 'em' => $r['em'] ?? '',
      'taxa' => $r['taxa'] ?? null, 'liquido' => $r['liquido'] ?? null, 'qtde' => $r['qtde'] ?? null, 'origem' => $r['origem'] ?? '', 'rel' => $r['rel'] ?? null];
  }
  $din = round((float)($S[$d][$u]['dinheiro'] ?? 0), 2);
  $outras = 0.0; foreach ((array)($S[$d][$u] ?? []) as $f => $v) if (!isset(CX_FORMAS[$f]) && $f !== 'dinheiro') $outras = round($outras + $v, 2);
  return ['u' => $u, 'formas' => $formas, 'sis' => $ts, 'rede' => $temR ? $tr : null, 'dinheiro' => $din, 'outras' => $outras,
    'total' => round($ts + $din + $outras, 2), 'sit' => $pior, 'ajustes' => $A[$d][$u]['lista'] ?? []];
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
  $S = cxSistema($de, $fimH); $R = cxRede($de, $fimH); $A = cxAjustes($de, $fimH);
  $unis = array_map(fn($u) => cxUnidade($S, $R, $d, $u, $A), cxUnidadesDoDia($S, $R, $d));
  /* 2.4: o PDF da Rede de cada unidade nesse dia (o último anexado) */
  foreach ($unis as &$U) $U['relatorio'] = cxRelDoDia($d, $U['u']);
  unset($U);
  $hist = [];
  for ($i = 0; $i < CX_DIAS_HIST; $i++) {
    $x = cxMenosDias($fimH, $i); $ordem = ['' => 0, 'ok' => 1, 'falta' => 2, 'dif' => 3]; $pior = ''; $n = 0;
    foreach (cxUnidadesDoDia($S, $R, $x) as $u) { $c = cxUnidade($S, $R, $x, $u, $A); if ($ordem[$c['sit']] > $ordem[$pior]) $pior = $c['sit']; if ($c['sit'] !== '') $n++; }
    $hist[] = ['dia' => $x, 'sit' => $pior];
  }
  return ['dia' => $d, 'hoje' => cxHoje(), 'unidades' => $unis, 'nomes' => unidades(), 'hist' => $hist, 'liquido' => cxLiquido($d, array_map(fn($U) => $U['u'], $unis))];
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
    else {
      $q2 = $db->prepare('SELECT taxa FROM fin_caixa WHERE dia = ? AND unidade_id = ? AND forma = ?'); $q2->execute([$d, $u, $f]); $tx = $q2->fetchColumn(); $tx = $tx === false || $tx === null ? null : (float)$tx;
      $db->prepare("INSERT OR REPLACE INTO fin_caixa (dia, unidade_id, forma, valor, por, em, taxa, liquido, origem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'manual')")
        ->execute([$d, $u, $f, $v, $quem, $em, $tx, $tx === null ? null : round($v - $tx, 2)]);
    }
    $db->prepare('INSERT INTO fin_caixa_hist (dia, unidade_id, forma, antes, depois, por, em) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$d, $u, $f, $antes, $v, $quem, $em]);
  }
  return cxDia(['dia' => $d]);
}

/* =====================================================================
   2.4 (Play Space 1.63): o PDF da Rede, o conserto do caixa e o caixa líquido
   Pedido do dono: "ela não vai mais lançar a Rede à mão: anexa o PDF e o sistema destrincha e preenche (cada bandeira tem uma taxa, e só
   a Rede sabe que cartão foi passado)"; "consertar caixa: lançar um valor a mais ou a menos, com a observação do porquê, para as contas
   baterem"; e "o Pix cai no mesmo dia, o débito em um dia útil e o crédito em dois: isso fica no caixa líquido".
   ===================================================================== */
const CX_PDF_MAX = 8 * 1024 * 1024;

/* o último PDF da Rede anexado que cobre o dia, na unidade */
function cxRelDoDia(string $d, int $u): ?array {
  cxPreparar();
  $q = db()->prepare('SELECT id, de, ate, ecs, nome, por, em FROM fin_caixa_rel WHERE unidade_id = ? AND de <= ? AND ate >= ? ORDER BY id DESC LIMIT 1'); $q->execute([$u, $d, $d]);
  $r = $q->fetch(); if (!$r) return null;
  $ecs = array_values(array_filter(explode(',', (string)$r['ecs'])));
  return ['id' => (int)$r['id'], 'de' => (string)$r['de'], 'ate' => (string)$r['ate'], 'ecs' => $ecs, 'nome' => (string)$r['nome'], 'por' => (string)$r['por'], 'em' => (string)$r['em']];
}

/* ---------- os dias úteis (sem sábado, domingo e os feriados nacionais) ---------- */
function cxPascoa(int $a): string {   /* o domingo de Páscoa (o cálculo de Meeus) */
  $b = intdiv($a, 100); $c = $a % 100; $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
  $h = (19 * ($a % 19) + $b - $d - $g + 15) % 30; $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv(($a % 19) + 11 * $h + 22 * $l, 451);
  $mes = intdiv($h + $l - 7 * $m + 114, 31); $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
  return sprintf('%04d-%02d-%02d', $a, $mes, $dia);
}
function cxFeriados(int $a): array {
  static $C = []; if (isset($C[$a])) return $C[$a];
  $f = array_map(fn($md) => "$a-$md", ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25']);
  $p = strtotime(cxPascoa($a) . ' 12:00:00');
  foreach ([-48, -47, -2, 60] as $n) $f[] = date('Y-m-d', $p + $n * 86400);   /* carnaval (segunda e terça), Sexta-feira Santa, Corpus Christi */
  return $C[$a] = array_flip($f);
}
function cxUtil(string $d): bool { $t = strtotime($d . ' 12:00:00'); return (int)date('N', $t) < 6 && !isset(cxFeriados((int)date('Y', $t))[$d]); }
function cxMaisUteis(string $d, int $n): string { $x = $d; while ($n > 0) { $x = date('Y-m-d', strtotime($x . ' 12:00:00 +1 day')); if (cxUtil($x)) $n--; } return $x; }
/* em que dia o dinheiro da venda cai na conta: o Pix no mesmo dia, o débito em 1 dia útil, o crédito em 2 dias úteis */
const CX_CAI = ['pix' => 0, 'debito' => 1, 'credito' => 2];
function cxCai(string $venda, string $f): string { $n = CX_CAI[$f] ?? 0; return $n ? cxMaisUteis($venda, $n) : $venda; }

/* ---------- o caixa líquido: o que cai na conta num dia ---------- */
/* cada parte: a forma, o dia da venda, o bruto, a taxa e o líquido. Com o PDF da Rede do dia da venda, os números são os da Rede;
   sem ele, o que o sistema registrou (sem a taxa, que só a Rede sabe) */
function cxLiquidoDia(string $A, array $unis, array $S, array $R, array $AJ): array {
  $out = [];
  foreach ($unis as $u) {
    $itens = []; $tot = ['bruto' => 0.0, 'taxa' => 0.0, 'liquido' => 0.0]; $estimado = false;
    for ($k = 0; $k <= 10; $k++) {
      $v = cxMenosDias($A, $k);
      foreach (CX_CAI as $f => $_) {
        if (cxCai($v, $f) !== $A) continue;
        $r = $R[$v][$u][$f] ?? null;
        if ($r && $r['liquido'] !== null) { $b = $r['v']; $tx = (float)$r['taxa']; $lq = (float)$r['liquido']; $fonte = 'rede'; }
        elseif ($r) { $b = $r['v']; $tx = null; $lq = $b; $fonte = 'rede_sem_taxa'; }
        else { $b = round((float)($S[$v][$u][$f] ?? 0) + (float)($AJ[$v][$u]['soma'][$f] ?? 0), 2); $tx = null; $lq = $b; $fonte = 'sistema'; }
        if (abs($b) < 0.005) continue;
        if ($fonte !== 'rede') $estimado = true;
        $itens[] = ['f' => $f, 'nome' => CX_FORMAS[$f], 'venda' => $v, 'bruto' => round($b, 2), 'taxa' => $tx === null ? null : round($tx, 2), 'liquido' => round($lq, 2), 'fonte' => $fonte];
        $tot['bruto'] = round($tot['bruto'] + $b, 2); $tot['taxa'] = round($tot['taxa'] + (float)$tx, 2); $tot['liquido'] = round($tot['liquido'] + $lq, 2);
      }
    }
    usort($itens, fn($a, $b) => [array_search($a['f'], array_keys(CX_CAI), true), $a['venda']] <=> [array_search($b['f'], array_keys(CX_CAI), true), $b['venda']]);
    $out[] = ['u' => $u, 'itens' => $itens, 'total' => $tot, 'estimado' => $estimado];
  }
  return $out;
}
/* o dia escolhido e os próximos dias (a previsão do que vai cair) */
function cxLiquido(string $d, array $unis): array {
  $ate = cxMaisUteis($d, 6); $de = cxMenosDias($d, 12);
  $S = cxSistema($de, $ate); $R = cxRede($de, $ate); $AJ = cxAjustes($de, $ate);
  $prox = [];
  for ($x = date('Y-m-d', strtotime($d . ' 12:00:00 +1 day')), $n = 0; $n < 7; $x = date('Y-m-d', strtotime($x . ' 12:00:00 +1 day')), $n++) {
    $p = cxLiquidoDia($x, $unis, $S, $R, $AJ); $t = 0.0; $est = false; foreach ($p as $q) { $t = round($t + $q['total']['liquido'], 2); $est = $est || $q['estimado']; }
    $prox[] = ['dia' => $x, 'util' => cxUtil($x), 'liquido' => $t, 'estimado' => $est, 'unidades' => $p];
  }
  return ['dia' => $d, 'util' => cxUtil($d), 'unidades' => cxLiquidoDia($d, $unis, $S, $R, $AJ), 'proximos' => $prox, 'regra' => CX_CAI];
}

/* ---------- anexar o PDF da Rede ---------- */
function cxPdf(array $in, array $eu): array {
  cxPreparar(); $db = db();
  require_once __DIR__ . '/redepdf.php';
  $u = (int)($in['u'] ?? -1); if ($u < 0) falha('Escolha a unidade.');
  if ($u > 0) { $q = $db->prepare('SELECT 1 FROM unidades WHERE id = ?'); $q->execute([$u]); if (!$q->fetchColumn()) falha('Unidade não encontrada.', 404); }
  $nome = mb_substr(basename(str_replace('\\', '/', (string)($in['nome'] ?? 'rede.pdf'))), 0, 160);
  $bin = base64_decode((string)($in['arquivo'] ?? ''), true);
  if ($bin === false || $bin === '') falha('Não consegui ler o arquivo. Escolha o PDF de novo.');
  if (strlen($bin) > CX_PDF_MAX) falha('PDF grande demais (até 8 MB). Tire o relatório de menos dias.');
  try { $R = rpLer($bin, $nome); }
  catch (Throwable $e) { falha('Não consegui ler este PDF: ' . $e->getMessage() . ' Ele precisa ser o "Relatório de vendas" da Rede, consolidado por modalidade.'); }
  $dias = array_filter($R['dias'], fn($x) => !empty($x['modalidades']));
  if (!$dias) falha('Não achei as vendas por modalidade (crédito, débito, Pix) neste PDF. Ele precisa ser o "Relatório de vendas" da Rede, consolidado por modalidade.');
  foreach (array_keys($dias) as $d) if ($d > cxHoje()) falha('O PDF tem vendas de um dia que ainda não chegou (' . date('d/m/Y', strtotime($d)) . '). Confira o arquivo.');
  /* guarda o arquivo (na pasta de dados, fora do site) */
  $pasta = pastaDados() . '/rede'; if (!is_dir($pasta)) @mkdir($pasta, 0700, true);
  $arq = 'rede-' . $u . '-' . min(array_keys($dias)) . '-' . bin2hex(random_bytes(5)) . '.pdf';
  if (!is_dir($pasta) || @file_put_contents($pasta . '/' . $arq, $bin, LOCK_EX) === false) $arq = '';
  $quem = (string)$eu['nome']; $em = agora();
  $guardar = $R; unset($guardar['linhas']);
  $db->prepare('INSERT INTO fin_caixa_rel (unidade_id, de, ate, ecs, arquivo, nome, dados, por, em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$u, $R['de'] ?: min(array_keys($dias)), $R['ate'] ?: max(array_keys($dias)), implode(',', $R['ecs']), $arq, $nome, json_encode($guardar, JSON_UNESCAPED_UNICODE), $quem, $em]);
  $rid = (int)$db->lastInsertId();
  /* os estabelecimentos: avisa quando o relatório junta mais de um, ou quando são outros que os do PDF anterior desta unidade */
  $avisos = $R['avisos'];
  if (count($R['ecs']) > 1) $avisos[] = 'Este PDF junta ' . count($R['ecs']) . ' estabelecimentos da Rede (' . implode(', ', $R['ecs']) . '). Se eles não forem todos desta loja, tire na Rede o relatório só do estabelecimento desta loja: senão a conferência não vai bater.';
  $q = $db->prepare('SELECT ecs FROM fin_caixa_rel WHERE unidade_id = ? AND id < ? ORDER BY id DESC LIMIT 1'); $q->execute([$u, $rid]); $antes = $q->fetchColumn();
  if ($antes !== false && $antes !== '' && $R['ecs'] && $antes !== implode(',', $R['ecs'])) $avisos[] = 'Os estabelecimentos deste PDF (' . implode(', ', $R['ecs']) . ') não são os mesmos do PDF anterior desta unidade (' . str_replace(',', ', ', (string)$antes) . '). Confira se é a loja certa.';
  /* preenche cada dia, cada forma: o bruto vai para "Na Rede", com a taxa, o líquido e a quantidade */
  $preenchidos = [];
  foreach ($dias as $d => $x) {
    foreach (CX_FORMAS as $f => $nm) {
      $m = $x['modalidades'][$f] ?? ['bruto' => 0.0, 'taxa' => 0.0, 'liquido' => 0.0, 'qtde' => 0];
      $q = $db->prepare('SELECT valor FROM fin_caixa WHERE dia = ? AND unidade_id = ? AND forma = ?'); $q->execute([$d, $u, $f]); $a = $q->fetchColumn();
      $db->prepare("INSERT OR REPLACE INTO fin_caixa (dia, unidade_id, forma, valor, por, em, taxa, liquido, qtde, origem, relatorio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pdf', ?)")
        ->execute([$d, $u, $f, round($m['bruto'], 2), $quem, $em, round($m['taxa'], 2), round($m['liquido'], 2), (int)$m['qtde'], $rid]);
      if ($a === false || abs((float)$a - $m['bruto']) > 0.004)
        $db->prepare('INSERT INTO fin_caixa_hist (dia, unidade_id, forma, antes, depois, por, em) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$d, $u, $f, $a === false ? null : (float)$a, round($m['bruto'], 2), $quem . ' (PDF da Rede)', $em]);
    }
    if (!empty($x['modalidades']['voucher']) && $x['modalidades']['voucher']['bruto'] > 0.004) $avisos[] = 'No dia ' . date('d/m', strtotime($d)) . ' a Rede tem ' . finBrl($x['modalidades']['voucher']['bruto']) . ' em voucher, que não entra na conferência.';
    $preenchidos[] = ['dia' => $d, 'modalidades' => $x['modalidades'], 'cancelado' => $x['cancelado'], 'vendas' => $x['vendas']];
  }
  $diaTela = isset($in['dia']) && isset($dias[(string)$in['dia']]) ? (string)$in['dia'] : max(array_keys($dias));
  return cxDia(['dia' => $diaTela]) + ['pdf' => ['id' => $rid, 'dias' => $preenchidos, 'ecs' => $R['ecs'], 'avisos' => array_values(array_unique($avisos)), 'irPara' => $diaTela]];
}
/* baixar o PDF anexado */
function cxPdfVer(array $in): void {
  cxPreparar();
  $q = db()->prepare('SELECT arquivo, nome FROM fin_caixa_rel WHERE id = ?'); $q->execute([(int)($in['id'] ?? 0)]); $r = $q->fetch();
  if (!$r || $r['arquivo'] === '') falha('O PDF não está mais guardado.', 404);
  $f = pastaDados() . '/rede/' . basename((string)$r['arquivo']); if (!is_file($f)) falha('O PDF não está mais guardado.', 404);
  header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$r['nome'] ?: 'rede.pdf') . '"');
  header('Content-Length: ' . filesize($f)); readfile($f); exit;
}

/* ---------- consertar o caixa ---------- */
/* um valor a mais (positivo) ou a menos (negativo) numa forma, com o porquê; entra na conta do sistema na conferência */
function cxAjuste(array $in, array $eu): array {
  cxPreparar(); $db = db();
  $d = cxDiaOk($in['dia'] ?? ''); $u = (int)($in['u'] ?? -1); $f = (string)($in['forma'] ?? '');
  if (!isset(CX_FORMAS[$f])) falha('Escolha crédito, débito ou Pix.');
  if ($u < 0) falha('Escolha a unidade.');
  if ($u > 0) { $q = $db->prepare('SELECT 1 FROM unidades WHERE id = ?'); $q->execute([$u]); if (!$q->fetchColumn()) falha('Unidade não encontrada.', 404); }
  $t = trim(str_replace(['R$', ' ', "\u{00a0}", '−'], ['', '', '', '-'], (string)($in['valor'] ?? '')));
  $neg = substr($t, 0, 1) === '-'; $t = ltrim($t, '+-');
  $v = cxValor($t); if ($v === null || $v < 0.005) falha('Digite o valor do conserto (maior que zero).');
  if ($neg) $v = -$v;
  $obs = mb_substr(trim((string)preg_replace('/\s+/u', ' ', (string)($in['obs'] ?? ''))), 0, 300);
  if (mb_strlen($obs) < 5) falha('Escreva na observação o porquê do conserto (o que aconteceu).');
  $db->prepare('INSERT INTO fin_caixa_ajustes (dia, unidade_id, forma, valor, obs, por, em) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$d, $u, $f, round($v, 2), $obs, (string)$eu['nome'], agora()]);
  return cxDia(['dia' => $d]);
}
function cxAjusteTirar(array $in, array $eu): array {
  cxPreparar(); $db = db();
  $q = $db->prepare('SELECT dia FROM fin_caixa_ajustes WHERE id = ? AND removido_em IS NULL'); $q->execute([(int)($in['id'] ?? 0)]); $d = $q->fetchColumn();
  if ($d === false) falha('Esse conserto não existe mais.', 404);
  $db->prepare('UPDATE fin_caixa_ajustes SET removido_por = ?, removido_em = ? WHERE id = ?')->execute([(string)$eu['nome'], agora(), (int)$in['id']]);
  return cxDia(['dia' => (string)$d]);
}
