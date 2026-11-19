<?php
/* =====================================================================
   Play Space Finanças 2.0 (Play Space 1.59): a gestão financeira dentro do sistema
   Pedido do dono: "o financeiro faz tudo manualmente numa planilha; não quero mais nada por Excel. O que elas tiverem que alimentar, o
   sistema fornece os campos; e uma opção de exportar tudo em Excel ou PDF (o PDF com gráficos e imagens, bonito, para o dono olhar)".
   - Os lançamentos (tabela fin_lanc), um por linha, como na planilha:
       receita      (Faturamento): data, qtd de crianças, qtd de vendas, sub item (descrição), filial, segmento, valor bruto, observações;
       despesa      (Despesas): data, descrição, filial, grupo (Custo Fixo, Custo variável, Investimento), segmento, valor, obs, centro de custo;
       recebimento  (Recebimentos líquidos): data, forma (Pix, dinheiro, crédito, débito, link), filial, segmento (o "local"), valor, obs,
                    data após o fechamento;
       rede         (Rede, a maquininha): data, filial, valor (o que a Rede pagou no dia). Fica à parte: não soma no líquido.
   - Nada se perde: tirar um lançamento só marca quem tirou e quando. Importar a planilha de novo troca o que veio da planilha antes (o que
     foi lançado aqui continua).
   - As contas, como a planilha: Despesas = os grupos Custo Fixo e Custo variável (o Investimento fica à parte: o grupo, a filial ou o
     segmento Investimento, como a planilha marca); Resultado = receita bruta
     menos despesas; o líquido = o que foi recebido (os recebimentos).
   ===================================================================== */
const FL_TIPOS = ['receita', 'despesa', 'recebimento', 'rede'];
const FL_FORMAS = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'credito' => 'Crédito', 'debito' => 'Débito', 'link' => 'Link de pagamento', 'rede' => 'Rede (maquininha)'];
const FL_INVEST = 'Investimento';
// as listas de fábrica (as da planilha); o que for lançado ou importado com um nome novo entra na lista sozinho
const FL_LISTAS = [
  'filial' => ['Morumbi', 'Anália Franco', 'Casa Tom Anália', 'Casa Tom Morumbi', 'Escritório', 'Eventos Proprietário', 'Evento Externo', 'Bolo', 'Play Lab', 'Investimento'],
  'segmento' => ['Play', 'Casa Tom', 'Festas', 'Eventos', 'Eventos Proprietário', 'Evento Externo', 'Bolo', 'Escritório', 'Play Lab', 'Investimento'],
  'grupo' => ['Custo Fixo', 'Custo variável', 'Investimento'],
  'centro' => ['RH', 'Aluguel', 'Água', 'Luz', 'Telefone', 'Internet', 'Impostos', 'Contabilidade', 'Advogado', 'Marketing', 'Limpeza', 'Manutenção', 'Sistema', 'TI', 'Licença', 'Seguro', 'Frete', 'Festa', 'Recreação', 'Galpão', 'Despesas caixa', 'Extra'],
  'subitem' => ['Play' => ['Recreação', 'Pacote', 'Prod', 'Festa', 'Avulso Festa', 'Eventos', 'Balões', 'Carrinho de bb', 'Fusquinha'],
    'Casa Tom' => ['Avulso Bibbidi', 'Bibbidi comemoração', 'Boneca', 'Boneca Duda', 'Acessórios', 'Vestido', 'Vestido Linha Nova', 'Roupas de Boneca', 'Brinquedo', 'Fantasia', 'Pelúcia', 'Saia', 'Frete'],
    'Eventos Proprietário' => ['Convite', 'Vestido + Convite'], 'Bolo' => ['Bolo']],
];
const FL_UNIDADES = ['Morumbi', 'Anália Franco'];
const FL_COM_LOCAL = ['Play', 'Casa Tom'];

function flPreparar(): void {
  static $ok = false; if ($ok) return; $ok = true;
  $db = db();
  $db->exec("CREATE TABLE IF NOT EXISTS fin_lanc (id INTEGER PRIMARY KEY, tipo TEXT NOT NULL, data TEXT NOT NULL, descricao TEXT NOT NULL DEFAULT '', filial TEXT NOT NULL DEFAULT '',
    segmento TEXT NOT NULL DEFAULT '', grupo TEXT NOT NULL DEFAULT '', centro TEXT NOT NULL DEFAULT '', forma TEXT NOT NULL DEFAULT '', qtd_criancas INTEGER NOT NULL DEFAULT 0,
    qtd_vendas INTEGER NOT NULL DEFAULT 0, valor REAL NOT NULL DEFAULT 0, obs TEXT NOT NULL DEFAULT '', data_fech TEXT NOT NULL DEFAULT '', origem TEXT NOT NULL DEFAULT 'manual',
    lote TEXT NOT NULL DEFAULT '', criado_por TEXT NOT NULL DEFAULT '', criado_em TEXT NOT NULL DEFAULT '', alterado_por TEXT NOT NULL DEFAULT '', alterado_em TEXT NOT NULL DEFAULT '',
    removido_por TEXT NOT NULL DEFAULT '', removido_em TEXT)");
  $db->exec('CREATE INDEX IF NOT EXISTS fin_lanc_td ON fin_lanc (tipo, data)');
  $db->exec('CREATE INDEX IF NOT EXISTS fin_lanc_lote ON fin_lanc (origem, lote)');
  /* 2.1: a despesa ganha o fornecedor, a situação (pago ou a pagar), o vencimento e o dia em que foi paga */
  $tem = array_column($db->query('PRAGMA table_info(fin_lanc)')->fetchAll(), 'name');
  /* e a recorrência (o "1/12 · recorrente mensal"): a série, qual é esta e quantas são */
  foreach (['fornecedor_id' => 'INTEGER', 'situacao' => "TEXT NOT NULL DEFAULT 'pago'", 'vencimento' => "TEXT NOT NULL DEFAULT ''", 'pago_em' => "TEXT NOT NULL DEFAULT ''",
    'serie' => "TEXT NOT NULL DEFAULT ''", 'serie_n' => 'INTEGER NOT NULL DEFAULT 0', 'serie_tot' => 'INTEGER NOT NULL DEFAULT 0'] as $c => $tp)
    if (!in_array($c, $tem, true)) $db->exec("ALTER TABLE fin_lanc ADD COLUMN $c $tp");
  $db->exec('CREATE INDEX IF NOT EXISTS fin_lanc_ab ON fin_lanc (tipo, situacao, vencimento)');
  fl21Preparar($db);
}
// o texto limpo (sem espaço sobrando) e a comparação sem acento nem maiúscula ("casa tom anália Franco" = "Casa Tom Anália Franco")
function flTxt($v, int $max = 120): string { $s = trim((string)preg_replace('/\s+/u', ' ', (string)$v)); return mb_substr($s, 0, $max); }
function flChave(string $s): string { $s = mb_strtolower(flTxt($s)); return strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']); }
function flListas(): array {
  $q = db()->query("SELECT valor FROM config WHERE chave = 'fin_listas'"); $v = $q->fetchColumn(); $q->closeCursor(); $g = $v ? json_decode((string)$v, true) : null;
  $L = FL_LISTAS; if (is_array($g)) foreach (['filial', 'segmento', 'grupo', 'centro'] as $k) if (isset($g[$k]) && is_array($g[$k])) $L[$k] = array_values(array_filter(array_map('flTxt', $g[$k]), 'strlen'));
  $pos = min(2, count($L['filial'])); foreach (['Casa Tom Anália', 'Casa Tom Morumbi'] as $ct) { $i = array_search($ct, $L['filial'], true); if ($i === false) { array_splice($L['filial'], $pos, 0, [$ct]); $i = $pos; } $pos = $i + 1; }   /* 2.7: o Casa Tom de cada unidade (só no financeiro), logo depois das unidades */
  if (is_array($g) && isset($g['subitem']) && is_array($g['subitem'])) { $S = []; foreach ($g['subitem'] as $seg => $xs) if (is_array($xs)) $S[flTxt($seg)] = array_values(array_filter(array_map('flTxt', $xs), 'strlen')); $L['subitem'] = $S; }
  return $L;
}
function flListasGuarda(array $L): void { db()->prepare("INSERT OR REPLACE INTO config (chave, valor) VALUES ('fin_listas', ?)")->execute([json_encode($L, JSON_UNESCAPED_UNICODE)]); }
// o nome como está na lista (sem acento/maiúscula diferente), ou o nome novo como foi digitado
function flCanon(string $v, array $lista): string { $v = flTxt($v); if ($v === '') return ''; $k = flChave($v); foreach ($lista as $x) if (flChave((string)$x) === $k) return (string)$x; return $v; }
// os nomes novos entram nas listas sozinhos (assim aparecem para escolher na próxima vez)
function flAprende(array $linhas): void {
  $L = flListas(); $mudou = false;
  $poe = function (array &$lista, string $v) use (&$mudou) { if ($v === '') return; $k = flChave($v); foreach ($lista as $x) if (flChave((string)$x) === $k) return; $lista[] = $v; $mudou = true; };
  foreach ($linhas as $l) {
    $poe($L['filial'], (string)($l['filial'] ?? '')); $poe($L['segmento'], (string)($l['segmento'] ?? ''));
    if (($l['tipo'] ?? '') === 'despesa') { $poe($L['grupo'], (string)($l['grupo'] ?? '')); $poe($L['centro'], (string)($l['centro'] ?? '')); }
    if (($l['tipo'] ?? '') === 'receita' && ($l['segmento'] ?? '') !== '') { $s = (string)$l['segmento']; if (!isset($L['subitem'][$s])) $L['subitem'][$s] = []; $poe($L['subitem'][$s], (string)($l['descricao'] ?? '')); }
  }
  if ($mudou) flListasGuarda($L);
}
function flListasSalvar(array $in): array {
  $L = flListas(); $k = (string)($in['lista'] ?? ''); $vals = array_values(array_unique(array_filter(array_map(fn($x) => flTxt($x, 80), (array)($in['valores'] ?? [])), 'strlen')));
  if (count($vals) > 300) falha('Lista grande demais.');
  if (in_array($k, ['filial', 'segmento', 'grupo', 'centro'], true)) $L[$k] = $vals;
  elseif ($k === 'subitem') { $seg = flTxt($in['segmento'] ?? '', 80); if ($seg === '') falha('Escolha o segmento.'); $L['subitem'][$seg] = $vals; }
  else falha('Lista desconhecida.');
  flListasGuarda($L); return flListas();
}
// "1.234,56", "1234.56", 1234.56 → 1234.56
function flNum($v): ?float {
  if (is_int($v) || is_float($v)) return is_finite((float)$v) ? (float)$v : null;
  $s = trim(str_replace(['R$', ' ', "\u{00A0}"], '', (string)$v)); if ($s === '' || !preg_match('/^-?[\d.,]+$/', $s)) return null;
  if (strpos($s, ',') !== false) $s = str_replace(['.', ','], ['', '.'], $s); elseif (substr_count($s, '.') > 1) $s = str_replace('.', '', $s);
  return is_numeric($s) ? (float)$s : null;
}
function flData($v): string { $d = (string)$v; return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) && (int)$m[1] >= 2000 && (int)$m[1] <= 2100 ? $d : ''; }
// um lançamento conferido (o que a tela mandou ou a linha da planilha); devolve o erro em vez de parar, para a importação contar
function flLinha(array $x, string $tipo, array $L): array {
  if (!in_array($tipo, FL_TIPOS, true)) return ['erro' => 'Tipo desconhecido.'];
  $d = flData($x['data'] ?? ''); if ($d === '') return ['erro' => 'Data inválida.'];
  $v = flNum($x['valor'] ?? null); if ($v === null || abs($v) < 0.005) return ['erro' => 'Valor em branco.']; if (abs($v) > 99999999) return ['erro' => 'Valor alto demais.'];
  $r = ['tipo' => $tipo, 'data' => $d, 'valor' => round($v, 2), 'filial' => flCanon((string)($x['filial'] ?? ''), $L['filial']), 'segmento' => flCanon((string)($x['segmento'] ?? ''), $L['segmento']),
    'descricao' => '', 'grupo' => '', 'centro' => '', 'forma' => '', 'qtd_criancas' => 0, 'qtd_vendas' => 0, 'obs' => flTxt($x['obs'] ?? '', 300), 'data_fech' => '',
    'fornecedor_id' => null, 'situacao' => 'pago', 'vencimento' => '', 'pago_em' => $d];
  if ($tipo === 'receita') {
    $r['descricao'] = flCanon((string)($x['descricao'] ?? ''), $L['subitem'][$r['segmento']] ?? []); $r['descricao'] = $r['descricao'] === '' ? '' : mb_substr($r['descricao'], 0, 120);
    $r['qtd_criancas'] = max(0, min(100000, (int)round(flNum($x['qtd_criancas'] ?? 0) ?? 0))); $r['qtd_vendas'] = max(0, min(100000, (int)round(flNum($x['qtd_vendas'] ?? 0) ?? 0)));
    if ($r['filial'] === '' || $r['segmento'] === '') return ['erro' => 'Escolha a filial e o segmento.'];
  } elseif ($tipo === 'despesa') {
    $r['descricao'] = flTxt($x['descricao'] ?? '', 160); $r['grupo'] = flCanon((string)($x['grupo'] ?? ''), $L['grupo']); $r['centro'] = flCanon((string)($x['centro'] ?? ''), $L['centro']);
    if ($r['descricao'] === '') return ['erro' => 'Escreva a descrição.']; if ($r['grupo'] === '') return ['erro' => 'Escolha o grupo da despesa.'];
    /* 2.1: a conta a pagar (a data é o vencimento) ou a despesa paga (a data é o dia do pagamento) */
    $fo = (string)($x['forma'] ?? ''); $r['forma'] = isset(FL_FORMAS_PAG[$fo]) ? $fo : '';
    $fid = (int)($x['fornecedor_id'] ?? 0); $r['fornecedor_id'] = $fid > 0 ? $fid : null;
    /* a data de referência (a do mês no relatório): o vencimento, se houver; senão, o dia do pagamento */
    if (($x['situacao'] ?? 'pago') === 'aberto') { $vc = flData($x['vencimento'] ?? '') ?: $d; $r['situacao'] = 'aberto'; $r['vencimento'] = $vc; $r['pago_em'] = ''; $r['data'] = $vc; }
    else { $pe = flData($x['pago_em'] ?? '') ?: $d; $vc = flData($x['vencimento'] ?? ''); $r['vencimento'] = $vc; $r['pago_em'] = $pe; $r['data'] = $vc ?: $pe; }
  } elseif ($tipo === 'recebimento') {
    $f = (string)($x['forma'] ?? ''); if (!isset(FL_FORMAS[$f]) || $f === 'rede') return ['erro' => 'Escolha a forma de pagamento.']; $r['forma'] = $f;
    $df = (string)($x['data_fech'] ?? ''); $r['data_fech'] = $df === '' ? '' : flData($df);
    if ($r['filial'] === '' && $r['segmento'] === '') return ['erro' => 'Escolha o local (filial e segmento).'];
  } else { $r['forma'] = 'rede'; if ($r['filial'] === '') return ['erro' => 'Escolha a filial.']; }
  return $r;
}
function flPublico(array $l): array {
  return ['id' => (int)$l['id'], 'tipo' => (string)$l['tipo'], 'data' => (string)$l['data'], 'descricao' => (string)$l['descricao'], 'filial' => (string)$l['filial'], 'segmento' => (string)$l['segmento'],
    'grupo' => (string)$l['grupo'], 'centro' => (string)$l['centro'], 'forma' => (string)$l['forma'], 'qtd_criancas' => (int)$l['qtd_criancas'], 'qtd_vendas' => (int)$l['qtd_vendas'],
    'valor' => round((float)$l['valor'], 2), 'obs' => (string)$l['obs'], 'data_fech' => (string)$l['data_fech'], 'origem' => (string)$l['origem'],
    'criado_por' => (string)$l['criado_por'], 'criado_em' => (string)$l['criado_em'], 'alterado_por' => (string)$l['alterado_por'], 'alterado_em' => (string)$l['alterado_em'],
    'fornecedor_id' => (int)($l['fornecedor_id'] ?? 0), 'fornecedor' => (string)($l['fornecedor'] ?? ''), 'situacao' => (string)($l['situacao'] ?? 'pago') ?: 'pago', 'vencimento' => (string)($l['vencimento'] ?? ''),
    'pago_em' => (string)($l['pago_em'] ?? '') ?: ((string)($l['situacao'] ?? 'pago') === 'aberto' ? '' : (string)$l['data']),
    'serie' => (string)($l['serie'] ?? ''), 'serie_n' => (int)($l['serie_n'] ?? 0), 'serie_tot' => (int)($l['serie_tot'] ?? 0)];
}
const FL_CAMPOS = ['tipo', 'data', 'descricao', 'filial', 'segmento', 'grupo', 'centro', 'forma', 'qtd_criancas', 'qtd_vendas', 'valor', 'obs', 'data_fech', 'fornecedor_id', 'situacao', 'vencimento', 'pago_em'];
/* 2.1: como a despesa foi (ou vai ser) paga */
const FL_FORMAS_PAG = ['pix' => 'Pix', 'boleto' => 'Boleto', 'transferencia' => 'Transferência', 'dinheiro' => 'Dinheiro', 'cartao' => 'Cartão de crédito', 'debito_auto' => 'Débito automático'];
const FL_SEL = "SELECT l.*, (SELECT nome FROM fin_fornecedores WHERE id = l.fornecedor_id) fornecedor FROM fin_lanc l";
function flSalvar(array $in, array $eu): array {
  flPreparar(); $db = db(); $id = (int)($in['id'] ?? 0); $tipo = (string)($in['tipo'] ?? '');
  if ($id) { $q = $db->prepare('SELECT * FROM fin_lanc WHERE id = ? AND removido_em IS NULL'); $q->execute([$id]); $a = $q->fetch(); if (!$a) falha('Lançamento não encontrado.', 404); $tipo = (string)$a['tipo']; }
  $x = (array)($in['lanc'] ?? []); if ($tipo === 'despesa') $x['fornecedor_id'] = fnResolve($x, $eu);
  $r = flLinha($x, $tipo, flListas()); if (isset($r['erro'])) falha($r['erro']);
  $rep = $id || $tipo !== 'despesa' ? 1 : max(1, min(60, (int)($in['repetir'] ?? 1)));
  if ($id) {
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_slice(FL_CAMPOS, 1)));
    $db->prepare("UPDATE fin_lanc SET $sets, alterado_por = ?, alterado_em = ? WHERE id = ?")->execute(array_merge(array_map(fn($c) => $r[$c], array_slice(FL_CAMPOS, 1)), [(string)$eu['nome'], agora(), $id]));
    /* 2.1: "mudar também as próximas" da recorrência (o aluguel subiu): as que ainda estão a pagar, com o mesmo dia de cada uma */
    if (!empty($in['proximas']) && (string)$a['serie'] !== '') { $C = ['descricao', 'filial', 'segmento', 'grupo', 'centro', 'forma', 'valor', 'obs', 'fornecedor_id'];
      $q = $db->prepare('UPDATE fin_lanc SET ' . implode(', ', array_map(fn($c) => "$c = ?", $C)) . ", alterado_por = ?, alterado_em = ? WHERE serie = ? AND serie_n > ? AND situacao = 'aberto' AND removido_em IS NULL");
      $q->execute(array_merge(array_map(fn($c) => $r[$c], $C), [(string)$eu['nome'], agora(), (string)$a['serie'], (int)$a['serie_n']])); $prox = $q->rowCount(); }
  } else {
    $serie = $rep > 1 ? 'r' . bin2hex(random_bytes(6)) : '';
    $ins = $db->prepare('INSERT INTO fin_lanc (' . implode(', ', FL_CAMPOS) . ', serie, serie_n, serie_tot, origem, criado_por, criado_em) VALUES (' . implode(', ', array_fill(0, count(FL_CAMPOS) + 3, '?')) . ", 'manual', ?, ?)");
    $ins->execute(array_merge(array_map(fn($c) => $r[$c], FL_CAMPOS), [$serie, $rep > 1 ? 1 : 0, $rep > 1 ? $rep : 0, (string)$eu['nome'], agora()])); $id = (int)$db->lastInsertId();
    /* 2.1: recorrente todo mês (o aluguel, o salário): as próximas entram como contas a pagar, no mesmo dia de cada mês (ou no último, se o mês for curto) */
    $base = $r['vencimento'] !== '' ? $r['vencimento'] : $r['data'];
    for ($k = 1; $k < $rep; $k++) { $r2 = $r; $r2['data'] = $r2['vencimento'] = flMaisMeses($base, $k); $r2['situacao'] = 'aberto'; $r2['pago_em'] = '';
      $ins->execute(array_merge(array_map(fn($c) => $r2[$c], FL_CAMPOS), [$serie, $k + 1, $rep, (string)$eu['nome'], agora()])); }
  }
  flAprende([$r]); $q = db()->prepare(FL_SEL . ' WHERE l.id = ?'); $q->execute([$id]); return ['lanc' => flPublico($q->fetch()), 'listas' => flListas(), 'repetidas' => $rep, 'proximas' => $prox ?? 0];
}
function flRemover(array $in, array $eu): array {
  flPreparar(); $id = (int)($in['id'] ?? 0); $s = db()->prepare('SELECT serie, serie_n FROM fin_lanc WHERE id = ?'); $s->execute([$id]); $a = $s->fetch();
  $q = db()->prepare('UPDATE fin_lanc SET removido_por = ?, removido_em = ? WHERE id = ? AND removido_em IS NULL'); $q->execute([(string)$eu['nome'], agora(), $id]);
  if (!$q->rowCount()) falha('Lançamento não encontrado.', 404); $prox = 0;
  if (!empty($in['proximas']) && $a && (string)$a['serie'] !== '') { $q = db()->prepare("UPDATE fin_lanc SET removido_por = ?, removido_em = ? WHERE serie = ? AND serie_n > ? AND situacao = 'aberto' AND removido_em IS NULL");
    $q->execute([(string)$eu['nome'], agora(), (string)$a['serie'], (int)$a['serie_n']]); $prox = $q->rowCount(); }
  return ['proximas' => $prox];
}
// o filtro da lista: o tipo, as datas, a filial, o segmento e a busca (descrição, sub item, centro de custo, obs)
function flOnde(array $in, array &$p): string {
  $tipo = (string)($in['tipo'] ?? ''); if (!in_array($tipo, FL_TIPOS, true)) falha('Escolha o que listar.');
  $de = flData($in['de'] ?? '') ?: date('Y-m-01'); $ate = flData($in['ate'] ?? '') ?: date('Y-m-t'); if ($ate < $de) [$de, $ate] = [$ate, $de];
  $w = 'removido_em IS NULL AND tipo = ? AND data >= ? AND data <= ?'; $p = [$tipo, $de, $ate];
  foreach (['filial', 'segmento', 'grupo', 'forma', 'centro', 'situacao'] as $k) { $v = flTxt($in[$k] ?? '', 80); if ($v !== '') { $w .= " AND $k = ?"; $p[] = $v; } }
  $fid = (int)($in['fornecedor_id'] ?? 0); if ($fid) { $w .= ' AND fornecedor_id = ?'; $p[] = $fid; }
  $b = flTxt($in['busca'] ?? '', 60); if ($b !== '') { $w .= " AND (descricao LIKE ? OR obs LIKE ? OR centro LIKE ? OR filial LIKE ? OR segmento LIKE ? OR fornecedor_id IN (SELECT id FROM fin_fornecedores WHERE nome LIKE ?))"; $t = '%' . str_replace(['%', '_'], ['', ''], $b) . '%'; array_push($p, $t, $t, $t, $t, $t, $t); }
  return $w;
}
function flListar(array $in): array {
  flPreparar(); $db = db(); $p = []; $w = flOnde($in, $p); $pag = max(0, (int)($in['pagina'] ?? 0)); $lim = 200;
  $q = $db->prepare(FL_SEL . " WHERE $w ORDER BY data DESC, id DESC LIMIT " . ($lim + 1) . ' OFFSET ' . ($pag * $lim)); $q->execute($p); $L = $q->fetchAll();
  $t = $db->prepare("SELECT COUNT(*) n, COALESCE(SUM(valor), 0) v, COALESCE(SUM(qtd_vendas), 0) qv, COALESCE(SUM(qtd_criancas), 0) qc, COALESCE(SUM(CASE WHEN situacao = 'aberto' THEN valor END), 0) ab, SUM(situacao = 'aberto') nab,
      COALESCE(SUM(CASE WHEN situacao = 'aberto' AND vencimento < ? THEN valor END), 0) atr, SUM(situacao = 'aberto' AND vencimento < ?) natr FROM fin_lanc WHERE $w"); $h = date('Y-m-d', agoraTs()); $t->execute(array_merge([$h, $h], $p)); $T = $t->fetch();
  return ['linhas' => array_map('flPublico', array_slice($L, 0, $lim)), 'mais' => count($L) > $lim, 'n' => (int)$T['n'], 'soma' => round((float)$T['v'], 2), 'qv' => (int)$T['qv'], 'qc' => (int)$T['qc'], 'pagina' => $pag,
    'aberto' => round((float)$T['ab'], 2), 'nAberto' => (int)$T['nab'], 'atraso' => round((float)$T['atr'], 2), 'nAtraso' => (int)$T['natr']];
}
// tudo de um período (para o Excel)
function flExportar(array $in): array {
  flPreparar(); $de = flData($in['de'] ?? '') ?: date('Y-01-01'); $ate = flData($in['ate'] ?? '') ?: date('Y-12-31'); if ($ate < $de) [$de, $ate] = [$ate, $de];
  $q = db()->prepare(FL_SEL . ' WHERE l.removido_em IS NULL AND l.data >= ? AND l.data <= ? ORDER BY l.tipo, l.data, l.id'); $q->execute([$de, $ate]);
  $O = ['receita' => [], 'despesa' => [], 'recebimento' => [], 'rede' => []]; foreach ($q->fetchAll() as $l) $O[(string)$l['tipo']][] = flPublico($l);
  return ['de' => $de, 'ate' => $ate, 'lanc' => $O];
}
// a importação da planilha: a tela lê o arquivo (.xlsx) e manda as linhas aos pedaços, todas com o mesmo lote; no fim, o lote novo
// substitui o que veio da planilha antes
function flImportar(array $in, array $eu): array {
  flPreparar(); $db = db(); $lote = (string)($in['lote'] ?? ''); if (!preg_match('/^[a-z0-9-]{8,40}$/', $lote)) falha('Lote inválido.');
  $tipo = (string)($in['tipo'] ?? ''); if (!in_array($tipo, FL_TIPOS, true)) falha('Tipo desconhecido.');
  $linhas = (array)($in['linhas'] ?? []); if (count($linhas) > 1500) falha('Mande no máximo 1500 linhas por vez.');
  $L = flListas(); $ok = 0; $erros = []; $boas = [];
  $ins = $db->prepare('INSERT INTO fin_lanc (' . implode(', ', FL_CAMPOS) . ', origem, lote, criado_por, criado_em) VALUES (' . implode(', ', array_fill(0, count(FL_CAMPOS), '?')) . ", 'planilha', ?, ?, ?)");
  $db->beginTransaction();
  try {
    foreach ($linhas as $i => $x) { $r = flLinha(is_array($x) ? $x : [], $tipo, $L); if (isset($r['erro'])) { if (count($erros) < 20) $erros[] = ['linha' => (int)(($x['_l'] ?? 0) ?: $i + 1), 'erro' => $r['erro']]; continue; }
      $ins->execute(array_merge(array_map(fn($c) => $r[$c], FL_CAMPOS), [$lote, (string)$eu['nome'], agora()])); $ok++; $boas[] = $r; }
    $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  flAprende($boas);
  return ['entrou' => $ok, 'erros' => count($linhas) - $ok, 'exemplos' => $erros];
}
function flImportarFim(array $in): array {
  flPreparar(); $db = db(); $lote = (string)($in['lote'] ?? ''); if (!preg_match('/^[a-z0-9-]{8,40}$/', $lote)) falha('Lote inválido.');
  $q = $db->prepare("SELECT COUNT(*) FROM fin_lanc WHERE origem = 'planilha' AND lote = ?"); $q->execute([$lote]); if (!(int)$q->fetchColumn()) falha('Nada chegou desta importação.', 409);
  $d = $db->prepare("DELETE FROM fin_lanc WHERE origem = 'planilha' AND lote != ?"); $d->execute([$lote]);
  $q = $db->prepare("SELECT tipo, COUNT(*) n, ROUND(SUM(valor), 2) v FROM fin_lanc WHERE origem = 'planilha' AND lote = ? GROUP BY tipo"); $q->execute([$lote]);
  $T = []; foreach ($q->fetchAll() as $l) $T[(string)$l['tipo']] = ['n' => (int)$l['n'], 'v' => round((float)$l['v'], 2)];
  return ['trocados' => $d->rowCount(), 'tipos' => $T];
}
function flImportacoes(): array {
  flPreparar(); $q = db()->query("SELECT lote, MIN(criado_em) em, MAX(criado_por) por, COUNT(*) n FROM fin_lanc WHERE origem = 'planilha' GROUP BY lote ORDER BY em DESC LIMIT 5");
  return array_map(fn($l) => ['lote' => (string)$l['lote'], 'em' => (string)$l['em'], 'por' => (string)$l['por'], 'n' => (int)$l['n']], $q->fetchAll());
}

/* ---------- o relatório: as somas do ano, mês a mês (a tela e o PDF fazem as contas com elas) ---------- */
// o "local" (como na planilha): Play e Casa Tom de cada unidade ("Play Morumbi", "Casa Tom Anália Franco"); o resto pelo segmento
function flLocal(string $filial, string $segmento): string {
  $s = flTxt($segmento); $f = flTxt($filial);
  if (in_array($s, FL_COM_LOCAL, true) && in_array($f, FL_UNIDADES, true)) return $s . ' ' . $f;
  return $s !== '' ? $s : ($f !== '' ? $f : 'Sem local');
}
function flRel(array $in): array {
  flPreparar(); $db = db(); $ano = (int)($in['ano'] ?? date('Y')); if ($ano < 2000 || $ano > 2100) falha('Escolha o ano.');
  $de = "$ano-01-01"; $ate = "$ano-12-31";
  $q = $db->prepare("SELECT tipo, CAST(substr(data, 6, 2) AS INTEGER) m, filial, segmento, CASE WHEN tipo = 'receita' THEN descricao ELSE '' END d, grupo, centro, forma,
      ROUND(SUM(valor), 2) v, COUNT(*) n, SUM(qtd_vendas) qv, SUM(qtd_criancas) qc FROM fin_lanc WHERE removido_em IS NULL AND data >= ? AND data <= ?
      GROUP BY tipo, m, filial, segmento, d, grupo, centro, forma");
  $q->execute([$de, $ate]); $G = [];
  foreach ($q->fetchAll() as $l) $G[] = [(string)$l['tipo'], (int)$l['m'], (string)$l['filial'], (string)$l['segmento'], (string)$l['d'], (string)$l['grupo'], (string)$l['centro'], (string)$l['forma'], round((float)$l['v'], 2), (int)$l['n'], (int)$l['qv'], (int)$l['qc']];
  // a semana do mês, como na planilha: do dia 1 ao 7, do 8 ao 15, do 16 ao fim
  $q = $db->prepare("SELECT tipo, CAST(substr(data, 6, 2) AS INTEGER) m, CASE WHEN CAST(substr(data, 9, 2) AS INTEGER) <= 7 THEN 1 WHEN CAST(substr(data, 9, 2) AS INTEGER) <= 15 THEN 2 ELSE 3 END s,
      (tipo = 'despesa' AND (grupo = ? OR segmento = ? OR filial = ?)) inv, ROUND(SUM(valor), 2) v FROM fin_lanc WHERE removido_em IS NULL AND data >= ? AND data <= ? GROUP BY tipo, m, s, inv");
  $q->execute([FL_INVEST, FL_INVEST, FL_INVEST, $de, $ate]); $W = []; foreach ($q->fetchAll() as $l) $W[] = [(string)$l['tipo'], (int)$l['m'], (int)$l['s'], (int)$l['inv'], round((float)$l['v'], 2)];
  $anos = array_map('intval', array_column($db->query("SELECT DISTINCT CAST(substr(data, 1, 4) AS INTEGER) a FROM fin_lanc WHERE removido_em IS NULL ORDER BY a DESC")->fetchAll(), 'a'));
  return ['ano' => $ano, 'g' => $G, 'sem' => $W, 'anos' => $anos ?: [(int)date('Y')], 'gerado' => agora()];
}
// as contas do relatório (a mesma da tela): o ano inteiro mês a mês e o recorte (o ano, ou um mês)
function flContas(array $R, int $mes = 0): array {
  $M = []; for ($m = 1; $m <= 12; $m++) $M[$m] = ['bruto' => 0.0, 'liquido' => 0.0, 'desp' => 0.0, 'inv' => 0.0, 'rede' => 0.0, 'qv' => 0, 'qc' => 0];
  $loc = []; $sub = []; $dl = []; $cen = []; $formas = []; $rede = [];
  $no = fn($m) => !$mes || $m === $mes;
  foreach ($R['g'] as [$t, $m, $f, $s, $d, $g, $c, $fo, $v, $n, $qv, $qc]) {
    if ($m < 1 || $m > 12) continue; $lk = flLocal($f, $s);
    if ($t === 'receita') { $M[$m]['bruto'] += $v; $M[$m]['qv'] += $qv; $M[$m]['qc'] += $qc;
      if ($no($m)) { $loc[$lk]['bruto'] = ($loc[$lk]['bruto'] ?? 0) + $v; $k = $s . '|' . ($d !== '' ? $d : 'Sem sub item'); if (!isset($sub[$k])) $sub[$k] = ['seg' => $s, 'item' => $d !== '' ? $d : 'Sem sub item', 'v' => 0.0, 'qv' => 0, 'qc' => 0, 'n' => 0]; $sub[$k]['v'] += $v; $sub[$k]['qv'] += $qv; $sub[$k]['qc'] += $qc; $sub[$k]['n'] += $n; } }
    elseif ($t === 'despesa') { $inv = $g === FL_INVEST || $s === FL_INVEST || $f === FL_INVEST; if ($inv) $M[$m]['inv'] += $v; else $M[$m]['desp'] += $v;
      if ($no($m)) { if (!$inv) { $loc[$lk]['desp'] = ($loc[$lk]['desp'] ?? 0) + $v; $dl[$lk][$g !== '' ? $g : 'Sem grupo'] = ($dl[$lk][$g !== '' ? $g : 'Sem grupo'] ?? 0) + $v; } $ck = $c !== '' ? $c : 'Sem centro de custo'; $cen[$ck] = ($cen[$ck] ?? 0) + $v; } }
    elseif ($t === 'recebimento') { $M[$m]['liquido'] += $v; if ($no($m)) { $loc[$lk]['liquido'] = ($loc[$lk]['liquido'] ?? 0) + $v; $formas[$fo] = ($formas[$fo] ?? 0) + $v; } }
    elseif ($t === 'rede') { $M[$m]['rede'] += $v; if ($no($m)) $rede[$f !== '' ? $f : 'Sem filial'] = ($rede[$f !== '' ? $f : 'Sem filial'] ?? 0) + $v; }
  }
  $T = ['bruto' => 0.0, 'liquido' => 0.0, 'desp' => 0.0, 'inv' => 0.0, 'rede' => 0.0, 'qv' => 0, 'qc' => 0];
  foreach ($M as $m => &$x) { foreach ($x as $k => &$y) $y = is_float($y) ? round($y, 2) : $y; unset($y); $x['res'] = round($x['bruto'] - $x['desp'], 2); $x['resl'] = round($x['liquido'] - $x['desp'], 2);
    if ($no($m)) foreach ($T as $k => $_) $T[$k] += $x[$k]; } unset($x);
  foreach ($T as $k => $v) $T[$k] = is_float($v) ? round($v, 2) : $v; $T['res'] = round($T['bruto'] - $T['desp'], 2); $T['resl'] = round($T['liquido'] - $T['desp'], 2); $T['margem'] = $T['bruto'] > 0.004 ? round($T['res'] / $T['bruto'] * 100, 1) : null;
  foreach ($loc as $k => $x) { $loc[$k] = ['local' => $k, 'bruto' => round($x['bruto'] ?? 0, 2), 'liquido' => round($x['liquido'] ?? 0, 2), 'desp' => round($x['desp'] ?? 0, 2)]; $loc[$k]['res'] = round($loc[$k]['bruto'] - $loc[$k]['desp'], 2); }
  uasort($loc, fn($a, $b) => $b['bruto'] <=> $a['bruto'] ?: $b['desp'] <=> $a['desp']);
  uasort($sub, fn($a, $b) => $b['v'] <=> $a['v']); arsort($cen); arsort($formas); arsort($rede);
  $S = [1 => ['bruto' => 0.0, 'liquido' => 0.0, 'inv' => 0.0, 'desp' => 0.0], 2 => ['bruto' => 0.0, 'liquido' => 0.0, 'inv' => 0.0, 'desp' => 0.0], 3 => ['bruto' => 0.0, 'liquido' => 0.0, 'inv' => 0.0, 'desp' => 0.0]];
  if ($mes) foreach ($R['sem'] as [$t, $m, $s, $inv, $v]) { if ($m !== $mes || !isset($S[$s])) continue; if ($t === 'receita') $S[$s]['bruto'] += $v; elseif ($t === 'recebimento') $S[$s]['liquido'] += $v; elseif ($t === 'despesa') { if ($inv) $S[$s]['inv'] += $v; else $S[$s]['desp'] += $v; } }
  return ['M' => $M, 'T' => $T, 'loc' => array_values($loc), 'sub' => array_values($sub), 'dl' => $dl, 'cen' => $cen, 'formas' => $formas, 'rede' => $rede, 'sem' => $S];
}

/* ---------- o PDF do relatório: a logo, os números grandes, os gráficos e as tabelas ---------- */
const FL_MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
function flPdf(array $in): string {
  $R = flRel($in); $mes = (int)($in['mes'] ?? 0); if ($mes < 0 || $mes > 12) $mes = 0; $C = flContas($R, $mes); $T = $C['T'];
  $p = new PdfSimples(); $M = 40; $W = PdfSimples::W - 2 * $M; $y = 0;
  $CORAL = 'E25B3F'; $TINTA = '3B3B3B'; $MUDO = '7A736B'; $GRADE = 'ECE2D5'; $FUNDO = 'FFF8EE';
  $AZUL = '2A78D6'; $LARANJA = 'EB6834'; $VERDE = '1BAF7A'; $CINZA = 'A39B91';
  $logo = @file_get_contents(__DIR__ . '/../img/logo-pdf.jpg') ?: '';
  $titulo = 'Relatório financeiro'; $sub = $mes ? ucfirst(FL_MESES[$mes - 1]) . ' de ' . $R['ano'] : 'Ano de ' . $R['ano'] . ' · mês a mês';
  $cab = function () use ($p, $M, $W, $titulo, $sub, $CORAL, $TINTA, $MUDO, $R, $logo, &$y) {
    $p->ret(0, 0, PdfSimples::W, 6, $CORAL);
    if ($logo !== '') $p->imagem($logo, $M, 18, 62, 50);
    $x = $logo !== '' ? $M + 74 : $M;
    $p->texto($x, 32, 'PLAY SPACE · FINANÇAS', 8.5, true, $CORAL); $p->texto($M + $W, 32, 'Gerado em ' . finDataBR(substr((string)$R['gerado'], 0, 10)) . ' às ' . substr((string)$R['gerado'], 11, 5), 8.5, false, $MUDO, 'D');
    $p->texto($x, 56, $titulo, 21, true, $TINTA); $p->texto($x, 72, $sub, 10.5, false, $MUDO); $y = 88;
  };
  $novaPag = function () use ($p, $cab) { $p->pagina(); $cab(); };
  $cabe = function ($h) use ($novaPag, &$y) { if ($y + $h > PdfSimples::H - 46) $novaPag(); };
  $secao = function ($nome, $exp = '', $junto = 70) use ($p, $M, $TINTA, $MUDO, $cabe, &$y) { $cabe($junto);   /* junto: o título não fica sozinho no pé da página */ $y += 12; $p->texto($M, $y + 12, $nome, 13, true, $TINTA); $y += 18; if ($exp !== '') { $p->texto($M, $y + 9, $exp, 8.5, false, $MUDO); $y += 14; } $y += 4; };
  $pc = fn($a, $b) => $b > 0.004 ? number_format($a / $b * 100, 1, ',', '') . '%' : '–';
  $cab();
  // os números grandes
  $caixas = [['Receita bruta', finBrl($T['bruto']), finInt($T['qv']) . ' vendas', $AZUL], ['Receita líquida', finBrl($T['liquido']), 'o que entrou (recebimentos)', $VERDE], ['Despesas', finBrl($T['desp']), 'custo fixo + variável', $LARANJA],
    ['Resultado', finBrl($T['res']), 'margem ' . ($T['margem'] === null ? '–' : number_format($T['margem'], 1, ',', '') . '%'), $T['res'] < 0 ? 'B8402A' : $VERDE]];
  $cw = ($W - 3 * 8) / 4;
  foreach ($caixas as $i => $c) { $x = $M + $i * ($cw + 8); $p->ret($x, $y, $cw, 60, $FUNDO, 6, $GRADE); $p->ret($x, $y + 10, 3, 40, $c[3]); $p->texto($x + 12, $y + 17, mb_strtoupper($c[0]), 7.5, true, $MUDO); $tam = 14.5; while ($tam > 9 && $p->larg($c[1], $tam, true) > $cw - 20) $tam -= 0.5; $p->texto($x + 12, $y + 37, $c[1], $tam, true, $TINTA); $p->texto($x + 12, $y + 51, $p->corta($c[2], $cw - 20, 7.8), 7.8, false, $MUDO); }
  $y += 68;
  $caixas = [['Investimento', finBrl($T['inv']), 'fora das despesas'], ['Resultado líquido', finBrl($T['resl']), 'líquido menos despesas'], ['Crianças', finInt($T['qc']), 'na recreação (lançadas)'], ['Rede (maquininha)', finBrl($T['rede']), 'informativo, não soma']];
  foreach ($caixas as $i => $c) { $x = $M + $i * ($cw + 8); $p->ret($x, $y, $cw, 50, $FUNDO, 6, $GRADE); $p->texto($x + 12, $y + 16, mb_strtoupper($c[0]), 7.5, true, $MUDO); $tam = 12.5; while ($tam > 9 && $p->larg($c[1], $tam, true) > $cw - 20) $tam -= 0.5; $p->texto($x + 12, $y + 33, $c[1], $tam, true, $TINTA); $p->texto($x + 12, $y + 44, $p->corta($c[2], $cw - 20, 7.5), 7.5, false, $MUDO); }
  $y += 58;
  // o gráfico do ano: receita bruta x despesas (barras) e o resultado (linha), mês a mês
  $secao('Receitas x despesas, mês a mês', 'Barras: receita bruta e despesas. Linha: o resultado (receita bruta menos despesas).' . ($mes ? ' Em destaque, ' . FL_MESES[$mes - 1] . '.' : ''));
  $gh = 190; $cabe($gh + 40); $gx = $M + 58; $gw = $W - 58; $gy = $y; $ph = $gh - 24;
  $vals = []; foreach ($C['M'] as $m => $x) { $vals[] = $x['bruto']; $vals[] = $x['desp']; $vals[] = $x['res']; }
  $max = max(1.0, max($vals)); $min = min(0.0, min($vals));
  $passo = (function ($bruto) { $p10 = pow(10, floor(log10(max($bruto, 1)))); foreach ([1, 2, 2.5, 5, 10] as $k) if ($k * $p10 >= $bruto) return $k * $p10; return $bruto; })(($max - $min) / 4);
  $topo = ceil($max / $passo) * $passo; $fundo = floor($min / $passo) * $passo; $Y = fn($v) => $gy + $ph * ($topo - $v) / max(1, $topo - $fundo);
  for ($v = $fundo; $v <= $topo + 0.001; $v += $passo) { $p->linha($gx, $Y($v), $gx + $gw, $Y($v), abs($v) < 0.001 ? 'B8AFA4' : 'EFE7DC', abs($v) < 0.001 ? 0.9 : 0.6); $p->texto($gx - 6, $Y($v) + 3, finCurto($v), 7, false, $MUDO, 'D'); }
  $cw2 = $gw / 12; $bw = min(14, $cw2 * 0.3); $pts = [];
  foreach ($C['M'] as $m => $x) { $cx = $gx + $cw2 * ($m - 1) + $cw2 / 2;
    if ($mes === $m) $p->ret($gx + $cw2 * ($m - 1) + 2, $gy - 2, $cw2 - 4, $ph + 4, 'FBEFE3', 4);
    foreach ([[$x['bruto'], $AZUL, -1], [$x['desp'], $LARANJA, 1]] as [$v, $cor, $lado]) { if ($v <= 0.004) continue; $top = $Y($v); $base = $Y(0); $p->ret($lado < 0 ? $cx - $bw - 1 : $cx + 1, $top, $bw, max(0.8, $base - $top), $cor, 2.5); }
    $p->texto($cx, $gy + $ph + 13, mb_substr(FL_MESES[$m - 1], 0, 3), 7.5, $mes === $m, $mes === $m ? $TINTA : $MUDO, 'C');
    if ($x['bruto'] > 0.004 || $x['desp'] > 0.004) $pts[] = [$cx, $Y($x['res'])]; }
  for ($i = 1; $i < count($pts); $i++) $p->linha($pts[$i - 1][0], $pts[$i - 1][1], $pts[$i][0], $pts[$i][1], $VERDE, 1.6);
  foreach ($pts as [$px, $py]) { $p->ret($px - 3.2, $py - 3.2, 6.4, 6.4, 'FFFFFF', 3.2); $p->ret($px - 2.2, $py - 2.2, 4.4, 4.4, $VERDE, 2.2); }
  $y = $gy + $gh; $lx = $M;
  foreach ([['Receita bruta', $AZUL], ['Despesas', $LARANJA], ['Resultado', $VERDE]] as [$n, $cor]) { $p->ret($lx, $y - 2, 8, 8, $cor, 2); $p->texto($lx + 12, $y + 5, $n, 8, false, $TINTA); $lx += 18 + $p->larg($n, 8) + 14; }
  $y += 16;
  // a tabela: um tabela (cabeçalho, linhas, total), como a do resumo do dia
  $tabela = function (array $cols, array $linhas, $total = null) use ($p, $M, $TINTA, $MUDO, $GRADE, $cabe, &$y) {
    $lh = 16; $cab = function () use ($p, $cols, $M, $MUDO, $GRADE, &$y) { $x = $M; foreach ($cols as $c) { $p->texto($c[2] === 'D' ? $x + $c[1] - 4 : $x + 4, $y + 11, mb_strtoupper($c[0]), 7, true, $MUDO, $c[2]); $x += $c[1]; } $y += 15; $p->linha($M, $y, $M + array_sum(array_column($cols, 1)), $y, $GRADE, 0.8); };
    $cabe(15 + $lh * min(4, count($linhas) + 1)); $cab();
    foreach ($linhas as $l) { if ($y + $lh > PdfSimples::H - 46) { $cabe(999); $cab(); }
      $x = $M; $neg = !empty($l['_b']); if (!empty($l['_d'])) $p->ret($M, $y, array_sum(array_column($cols, 1)), $lh, 'FBEFE3');
      foreach ($cols as $j => $c) { $v = (string)($l[$j] ?? ''); $cor = !empty($l['_neg'][$j]) ? 'B8402A' : ($neg ? $TINTA : '4A4540'); $p->texto($c[2] === 'D' ? $x + $c[1] - 4 : $x + 4, $y + 11.5, $p->corta($v, $c[1] - 8, 8.4, $neg), 8.4, $neg, $cor, $c[2]); $x += $c[1]; }
      $y += $lh; $p->linha($M, $y, $M + array_sum(array_column($cols, 1)), $y, 'F3ECE2', 0.5); }
    if ($total) { $x = $M; foreach ($cols as $j => $c) { $v = (string)($total[$j] ?? ''); $p->texto($c[2] === 'D' ? $x + $c[1] - 4 : $x + 4, $y + 12.5, $p->corta($v, $c[1] - 8, 8.8, true), 8.8, true, $TINTA, $c[2]); $x += $c[1]; } $y += $lh + 2; }
    $y += 4;
  };
  $L = []; foreach ($C['M'] as $m => $x) { if ($x['bruto'] < 0.005 && $x['desp'] < 0.005 && $x['liquido'] < 0.005 && $x['inv'] < 0.005) continue;
    $L[] = [ucfirst(FL_MESES[$m - 1]), finBrl($x['bruto']), finBrl($x['liquido']), finBrl($x['desp']), finBrl($x['inv']), finBrl($x['res']), $pc($x['res'], $x['bruto']), '_d' => $mes === $m, '_neg' => [5 => $x['res'] < 0]]; }
  if (!$L) $L[] = ['Nada lançado neste ano', '', '', '', '', '', ''];
  $A = ['bruto' => 0, 'liquido' => 0, 'desp' => 0, 'inv' => 0]; foreach ($C['M'] as $x) foreach ($A as $k => $_) $A[$k] += $x[$k];
  $tabela([['Mês', 78, 'E'], ['Receita bruta', 80, 'D'], ['Líquida', 74, 'D'], ['Despesas', 74, 'D'], ['Investimento', 70, 'D'], ['Resultado', 80, 'D'], ['Margem', $W - 456, 'D']], $L,
    ['Ano', finBrl($A['bruto']), finBrl($A['liquido']), finBrl($A['desp']), finBrl($A['inv']), finBrl($A['bruto'] - $A['desp']), $pc($A['bruto'] - $A['desp'], $A['bruto'])]);
  // cada local: barras de bruto e líquido, e a tabela com as despesas e o resultado
  $LOC = array_values(array_filter($C['loc'], fn($x) => $x['bruto'] > 0.004 || $x['liquido'] > 0.004 || $x['desp'] > 0.004));
  if ($LOC) {
    $BAR = array_slice(array_values(array_filter($LOC, fn($x) => $x['bruto'] > 0.004 || $x['liquido'] > 0.004)), 0, 10);   // (só os locais que venderam; as barras juntas, com o título, na mesma página)
    $secao('Cada local', 'Receita bruta (azul) e líquida (verde) de cada local' . ($mes ? ' no mês' : ' no ano') . '. O local é a unidade com o segmento (Play, Casa Tom) ou o próprio segmento.', 60 + 26 * count($BAR));
    $mx = max(1.0, max(array_map(fn($x) => max($x['bruto'], $x['liquido']), $LOC))); $rw = 150; $bwid = $W - $rw - 92;
    foreach ($BAR as $x) { $cabe(30); $p->texto($M, $y + 10, $p->corta($x['local'], $rw - 8, 8.6, true), 8.6, true, $TINTA);
      $p->ret($M + $rw, $y + 2, max(0.8, $bwid * $x['bruto'] / $mx), 8, $AZUL, 2); $p->texto($M + $rw + max(0.8, $bwid * $x['bruto'] / $mx) + 5, $y + 9, finCurto($x['bruto']), 7.4, false, $MUDO);
      $p->ret($M + $rw, $y + 12, max(0.8, $bwid * $x['liquido'] / $mx), 8, $VERDE, 2); $p->texto($M + $rw + max(0.8, $bwid * $x['liquido'] / $mx) + 5, $y + 19, finCurto($x['liquido']), 7.4, false, $MUDO); $y += 26; }
    $y += 4; $L = []; $tt = ['bruto' => 0, 'liquido' => 0, 'desp' => 0];
    foreach ($LOC as $x) { $L[] = [$x['local'], finBrl($x['bruto']), finBrl($x['liquido']), finBrl($x['desp']), finBrl($x['res']), $pc($x['res'], $x['bruto']), '_neg' => [4 => $x['res'] < 0]]; foreach ($tt as $k => $_) $tt[$k] += $x[$k]; }
    $tabela([['Local', 150, 'E'], ['Receita bruta', 82, 'D'], ['Líquida', 78, 'D'], ['Despesas', 78, 'D'], ['Resultado', 82, 'D'], ['Margem', $W - 470, 'D']], $L, ['Total', finBrl($tt['bruto']), finBrl($tt['liquido']), finBrl($tt['desp']), finBrl($tt['bruto'] - $tt['desp']), $pc($tt['bruto'] - $tt['desp'], $tt['bruto'])]);
  }
  // o que mais vendeu: os sub itens
  if ($C['sub']) {
    $secao('O que mais vendeu', 'Os sub itens da receita' . ($mes ? ' do mês' : ' do ano') . ', do maior para o menor (as 12 primeiras linhas no gráfico).');
    $tot = array_sum(array_column($C['sub'], 'v')); $mx = max(1.0, max(array_column($C['sub'], 'v'))); $rw = 170; $bwid = $W - $rw - 90;
    foreach (array_slice($C['sub'], 0, 12) as $x) { $cabe(16); $p->texto($M, $y + 9, $p->corta($x['item'] . ' · ' . $x['seg'], $rw - 8, 8.2), 8.2, false, $TINTA); $w = max(0.8, $bwid * $x['v'] / $mx);
      $p->ret($M + $rw, $y + 2, $w, 9, $CORAL, 2); $p->texto($M + $rw + $w + 5, $y + 9.5, finCurto($x['v']) . ' · ' . $pc($x['v'], $tot), 7.4, false, $MUDO); $y += 15; }
    $y += 6; $L = []; foreach ($C['sub'] as $x) $L[] = [$x['seg'], $x['item'], finInt($x['qv']), finInt($x['qc']), finBrl($x['v']), $pc($x['v'], $tot)];
    $tabela([['Segmento', 104, 'E'], ['Sub item', 150, 'E'], ['Vendas', 60, 'D'], ['Crianças', 60, 'D'], ['Valor', 86, 'D'], ['%', $W - 460, 'D']], $L, ['Total', '', finInt(array_sum(array_column($C['sub'], 'qv'))), finInt(array_sum(array_column($C['sub'], 'qc'))), finBrl($tot), '']);
  }
  // as despesas: fixo x variável por local e os centros de custo
  if ($C['dl'] || $C['cen']) {
    $secao('Despesas', 'Custo fixo e custo variável de cada local, e os centros de custo (com o investimento) do maior para o menor.');
    $grs = []; foreach ($C['dl'] as $lk => $gs) foreach ($gs as $g => $v) $grs[$g] = true; $grs = array_keys($grs); usort($grs, fn($a, $b) => array_search($a, FL_LISTAS['grupo']) <=> array_search($b, FL_LISTAS['grupo']));
    if ($C['dl']) { $L = []; $tg = array_fill_keys($grs, 0.0); $ord = $C['dl']; uksort($ord, fn($a, $b) => array_sum($C['dl'][$b]) <=> array_sum($C['dl'][$a]));
      foreach ($ord as $lk => $gs) { $l = [$lk]; foreach ($grs as $g) { $l[] = finBrl($gs[$g] ?? 0); $tg[$g] += $gs[$g] ?? 0; } $l[] = finBrl(array_sum($gs)); $L[] = $l; }
      $cols = [['Local', 150, 'E']]; $cwg = ($W - 150) / (count($grs) + 1); foreach ($grs as $g) $cols[] = [$g, $cwg, 'D']; $cols[] = ['Total', $cwg, 'D'];
      $tot = ['Total']; foreach ($grs as $g) $tot[] = finBrl($tg[$g]); $tot[] = finBrl(array_sum($tg)); $tabela($cols, $L, $tot); }
    if ($C['cen']) { $mx = max(1.0, max($C['cen'])); $rw = 150; $bwid = $W - $rw - 90; $tot = array_sum($C['cen']);
      foreach (array_slice($C['cen'], 0, 10, true) as $c => $v) { $cabe(16); $p->texto($M, $y + 9, $p->corta($c, $rw - 8, 8.2), 8.2, false, $TINTA); $w = max(0.8, $bwid * $v / $mx);
        $p->ret($M + $rw, $y + 2, $w, 9, $LARANJA, 2); $p->texto($M + $rw + $w + 5, $y + 9.5, finCurto($v) . ' · ' . $pc($v, $tot), 7.4, false, $MUDO); $y += 15; }
      $y += 6; }
  }
  // o mês, semana a semana (como a aba da planilha)
  if ($mes) {
    $secao('Semana a semana', 'Do dia 1 ao 7, do 8 ao 15 e do 16 ao fim do mês.'); $L = []; $rot = [1 => 'Dia 1 ao 7', 2 => 'Dia 8 ao 15', 3 => 'Dia 16 ao fim'];
    foreach ($C['sem'] as $s => $x) $L[] = [$rot[$s], finBrl($x['bruto']), finBrl($x['liquido']), finBrl($x['inv']), finBrl($x['desp']), finBrl($x['bruto'] - $x['desp']), finBrl($x['liquido'] - $x['desp'])];
    $tabela([['Semana', 78, 'E'], ['Receita bruta', 76, 'D'], ['Líquida', 72, 'D'], ['Investimento', 70, 'D'], ['Despesas', 72, 'D'], ['Resultado', 74, 'D'], ['Res. líquido', $W - 442, 'D']], $L);
  }
  // as formas de recebimento e a Rede
  if ($C['formas'] || $C['rede']) {
    $secao('Recebimentos', 'O que entrou, por forma de pagamento; e o que a Rede (maquininha) pagou, à parte.'); $L = []; $tf = array_sum($C['formas']);
    foreach ($C['formas'] as $f => $v) $L[] = [FL_FORMAS[$f] ?? $f, finBrl($v), $pc($v, $tf)];
    if ($L) $tabela([['Forma', $W - 210, 'E'], ['Valor', 120, 'D'], ['%', 90, 'D']], $L, ['Total recebido', finBrl($tf), '']);
    if ($C['rede']) { $L = []; foreach ($C['rede'] as $f => $v) $L[] = [$f, finBrl($v), '']; $tabela([['Rede (maquininha), à parte: não soma no recebido', $W - 210, 'E'], ['Valor', 120, 'D'], ['', 90, 'D']], $L, ['Total da Rede', finBrl(array_sum($C['rede'])), '']); }
  }
  $p->rodape(function ($p, $i, $n) use ($M, $W, $MUDO, $GRADE) { $p->linha($M, PdfSimples::H - 30, $M + $W, PdfSimples::H - 30, $GRADE, 0.6); $p->texto($M, PdfSimples::H - 18, 'Play Space · Finanças · relatório dos lançamentos do financeiro', 7.5, false, $MUDO); $p->texto($M + $W, PdfSimples::H - 18, "Página $i de $n", 7.5, false, $MUDO, 'D'); });
  return $p->pdf($titulo . ' · ' . $sub);
}
function finCurto($v) { $v = (float)$v; $a = abs($v); $s = $v < 0 ? '-' : ''; if ($a >= 1e6) return $s . 'R$ ' . number_format($a / 1e6, 1, ',', '.') . ' mi'; if ($a >= 1e3) return $s . 'R$ ' . number_format($a / 1e3, 0, ',', '.') . ' mil'; return $s . 'R$ ' . number_format($a, 0, ',', '.'); }
function flPdfSaida(array $in) {
  $pdf = flPdf($in); $mes = (int)($in['mes'] ?? 0); $ano = (int)($in['ano'] ?? date('Y'));
  $nome = 'play-space-relatorio-financeiro-' . $ano . ($mes ? '-' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) : '') . '.pdf';
  header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $nome . '"'); header('Cache-Control: no-store'); header('Content-Length: ' . strlen($pdf));
  echo $pdf; exit;
}
