<?php
/* =====================================================================
   Play Space Finanças 2.4 (Play Space 1.63): o leitor do PDF da Rede ("Relatório de vendas", consolidado por modalidade)
   O PDF da Rede não tem texto: cada letra é desenhada como figura (o programa que gera o relatório transforma as letras em contornos).
   Então, aqui:
   1. abre o PDF (os objetos, as páginas, o conteúdo de cada página, descomprimido);
   2. pega cada figura pintada e separa as letras (os contornos que se sobrepõem na horizontal são da mesma letra: o "o" e o furo dele,
      o "é" e o acento);
   3. reconhece cada letra pela forma: primeiro pela "impressão digital" exata (as letras do relatório são sempre as mesmas), e a que não
      for conhecida, pela figura mais parecida (a letra desenhada numa grade pequena e comparada com as que já se conhece);
   4. junta as letras em linhas (pela altura na página) e as linhas em texto;
   5. lê do texto: o período, os estabelecimentos, e de cada dia as linhas crédito / débito / pix / voucher (total bruto, taxa, líquido e
      quantidade) e os cancelamentos. As contas são conferidas (bruto − taxa = líquido; a soma das modalidades = o total do dia).
   As formas conhecidas ficam em redeglifos.php (aprendidas de um relatório de verdade; só as formas, nenhum número de cliente).
   ===================================================================== */

/* ---------- 1. o PDF: objetos, páginas e o conteúdo de cada página ---------- */
function rpObjetos(string $b): array {
  $o = [];
  if (!preg_match_all('/(?<![0-9])(\d+)\s+(\d+)\s+obj\b/', $b, $m, PREG_OFFSET_CAPTURE)) return $o;
  foreach ($m[0] as $i => $x) {
    $num = (int)$m[1][$i][0]; $ini = $x[1] + strlen($x[0]);
    $fimObj = strpos($b, 'endobj', $ini); if ($fimObj === false) $fimObj = strlen($b);
    $corpo = substr($b, $ini, $fimObj - $ini); $s = strpos($corpo, 'stream');
    if ($s !== false && preg_match('/^\s*<</', $corpo)) {
      $dic = substr($corpo, 0, $s); $p = $ini + $s + 6;
      if (substr($b, $p, 2) === "\r\n") $p += 2; elseif ($b[$p] === "\n" || $b[$p] === "\r") $p++;
      $len = null; if (preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $dic, $l)) $len = (int)$l[1];
      if ($len === null || $len <= 0 || substr($b, $p + $len, 30) === '' || strpos(substr($b, $p + $len, 30), 'endstream') === false) {
        $e = strpos($b, 'endstream', $p); $len = ($e === false ? strlen($b) : $e) - $p;
        $dados = rtrim(substr($b, $p, $len), "\r\n");
      } else $dados = substr($b, $p, $len);
      $o[$num] = ['dic' => $dic, 'dados' => $dados];
    } else $o[$num] = ['dic' => $corpo, 'dados' => null];
  }
  return $o;
}
function rpConteudo(array $obj): string {
  $d = (string)$obj['dados'];
  if (strpos($obj['dic'], 'FlateDecode') !== false) {
    $z = @gzuncompress($d); if ($z === false) $z = @gzinflate(substr($d, 2)); if ($z === false) throw new Exception('conteúdo comprimido ilegível');
    return $z;
  }
  return $d;
}
function rpRefs(string $s): array { preg_match_all('/(\d+)\s+\d+\s+R/', $s, $m); return array_map('intval', $m[1]); }
/* as páginas na ordem (pela árvore /Pages), cada uma com o seu conteúdo */
function rpPaginas(array $o): array {
  $raiz = null;
  foreach ($o as $n => $x) if (preg_match('/\/Type\s*\/Pages\b/', $x['dic']) && !preg_match('/\/Parent\s/', $x['dic'])) { $raiz = $n; break; }
  $ordem = [];
  $anda = function (int $n, int $prof) use (&$anda, &$ordem, $o) {
    if ($prof > 20 || !isset($o[$n])) return; $d = $o[$n]['dic'];
    if (preg_match('/\/Type\s*\/Pages\b/', $d) && preg_match('/\/Kids\s*\[([^\]]*)\]/', $d, $k)) { foreach (rpRefs($k[1]) as $f) $anda($f, $prof + 1); }
    elseif (preg_match('/\/Type\s*\/Page\b/', $d)) $ordem[] = $n;
  };
  if ($raiz !== null) $anda($raiz, 0);
  if (!$ordem) foreach ($o as $n => $x) if (preg_match('/\/Type\s*\/Page\b/', $x['dic'])) $ordem[] = $n;
  $pags = [];
  foreach ($ordem as $n) {
    $d = $o[$n]['dic']; $refs = [];
    if (preg_match('/\/Contents\s*\[([^\]]*)\]/', $d, $c)) $refs = rpRefs($c[1]); elseif (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R/', $d, $c)) $refs = [(int)$c[1]];
    $txt = ''; foreach ($refs as $r) if (isset($o[$r]) && $o[$r]['dados'] !== null) $txt .= rpConteudo($o[$r]) . "\n";
    $pags[] = $txt;
  }
  return $pags;
}

/* ---------- 2. as figuras pintadas e as letras ---------- */
/* cada "f" (pintar) com os contornos dele, já na posição da página; as curvas viram pontos (o começo, os de controle e o fim) */
function rpPinturas(string $c): array {
  $out = []; $pilha = []; $ctm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]; $cam = []; $at = -1; $num = []; $cor = [0, 0, 0];
  $T = function (float $x, float $y) use (&$ctm): array { return [$ctm[0] * $x + $ctm[2] * $y + $ctm[4], $ctm[1] * $x + $ctm[3] * $y + $ctm[5]]; };
  if (!preg_match_all('/-?\d*\.?\d+(?:e-?\d+)?|[A-Za-z*\'"]+|\/[^\s\/\[\]()<>]+|\[[^\]]*\]|\([^)]*\)/', $c, $m)) return $out;
  foreach ($m[0] as $t) {
    $ch = $t[0];
    if (($ch >= '0' && $ch <= '9') || $ch === '-' || $ch === '.') { $num[] = (float)$t; if (count($num) > 8) array_shift($num); continue; }
    switch ($t) {
      case 'q': $pilha[] = [$ctm, $cor]; break;
      case 'Q': if ($pilha) [$ctm, $cor] = array_pop($pilha); break;
      case 'cm': if (count($num) >= 6) { [$a, $b, $cc, $d, $e, $f] = array_slice($num, -6); [$A, $B, $C, $D, $E, $F] = $ctm;
        $ctm = [$a * $A + $b * $C, $a * $B + $b * $D, $cc * $A + $d * $C, $cc * $B + $d * $D, $e * $A + $f * $C + $E, $e * $B + $f * $D + $F]; } break;
      case 'scn': case 'rg': case 'sc': if (count($num) >= 3) $cor = array_slice($num, -3); break;
      case 'm': if (count($num) >= 2) { $cam[] = [$T(...array_slice($num, -2))]; $at = count($cam) - 1; } break;
      case 'l': if ($at >= 0 && count($num) >= 2) $cam[$at][] = $T(...array_slice($num, -2)); break;
      case 'c': if ($at >= 0 && count($num) >= 6) { $v = array_slice($num, -6); $cam[$at][] = $T($v[0], $v[1]); $cam[$at][] = $T($v[2], $v[3]); $cam[$at][] = $T($v[4], $v[5]); } break;
      case 'v': case 'y': if ($at >= 0 && count($num) >= 4) { $v = array_slice($num, -4); $cam[$at][] = $T($v[0], $v[1]); $cam[$at][] = $T($v[2], $v[3]); } break;
      case 're': if (count($num) >= 4) { [$x, $y, $w, $h] = array_slice($num, -4); $cam[] = [$T($x, $y), $T($x + $w, $y), $T($x + $w, $y + $h), $T($x, $y + $h)]; $at = -1; } break;
      case 'f': case 'f*': case 'F': $out[] = ['cor' => $cor, 'cs' => $cam]; $cam = []; $at = -1; break;
      case 'n': case 'S': case 's': case 'B': case 'B*': case 'b': case 'b*': $cam = []; $at = -1; break;
    }
    $num = [];
  }
  return $out;
}
/* as letras de uma pintura: os contornos que se sobrepõem na horizontal (e estão na mesma altura) são uma letra só */
function rpLetras(array $cs): array {
  $z = [];
  foreach ($cs as $c) {
    if (count($c) < 2) continue; $xs = array_column($c, 0); $ys = array_column($c, 1);
    $z[] = ['x0' => min($xs), 'x1' => max($xs), 'y0' => min($ys), 'y1' => max($ys), 'p' => $c];
  }
  usort($z, fn($a, $b) => $a['x0'] <=> $b['x0']);
  $g = []; $k = -1;
  foreach ($z as $c) {
    if ($k >= 0 && $c['x0'] < $g[$k]['x1'] - 0.05 && abs(($c['y0'] + $c['y1']) / 2 - $g[$k]['yc']) < 12) {
      $g[$k]['cs'][] = $c; $g[$k]['x1'] = max($g[$k]['x1'], $c['x1']); $g[$k]['y0'] = min($g[$k]['y0'], $c['y0']); $g[$k]['y1'] = max($g[$k]['y1'], $c['y1']);
    } else { $g[] = ['x0' => $c['x0'], 'x1' => $c['x1'], 'y0' => $c['y0'], 'y1' => $c['y1'], 'yc' => ($c['y0'] + $c['y1']) / 2, 'cs' => [$c]]; $k++; }
  }
  return $g;
}
/* a "impressão digital" da letra: os contornos com os pontos relativos ao canto da letra (a mesma letra, no mesmo tamanho, dá sempre a mesma).
   Os pontos vêm em múltiplos de 1/192; o arredondamento (em décimos, deslocado de 0,037) nunca cai bem na divisa entre dois décimos,
   então o mesmo desenho, em outra posição da página, dá a mesma impressão digital */
function rpQ(float $v): int { return (int)floor($v * 10 + 0.37); }
function rpAssinatura(array $g): string {
  $cs = $g['cs']; usort($cs, fn($a, $b) => [rpQ($a['x0'] - $g['x0']), rpQ($a['y0'] - $g['y0'])] <=> [rpQ($b['x0'] - $g['x0']), rpQ($b['y0'] - $g['y0'])]);
  $s = [];
  foreach ($cs as $c) { $p = []; foreach ($c['p'] as $q) $p[] = rpQ($q[0] - $g['x0']) . ',' . rpQ($q[1] - $g['y0']); $s[] = implode(';', $p); }
  return substr(md5(implode('|', $s)), 0, 16);
}
/* a letra numa grade de 10 x 14 (a altura da letra enche a grade; a largura na mesma escala): para achar a forma mais parecida */
function rpGrade(array $g): string {
  $w = max(0.01, $g['x1'] - $g['x0']); $h = max(0.01, $g['y1'] - $g['y0']); $esc = 14 / $h; $dx = (10 - $w * $esc) / 2;
  $arestas = [];
  foreach ($g['cs'] as $c) { $p = $c['p']; $n = count($p); for ($i = 0; $i < $n; $i++) { $a = $p[$i]; $b = $p[($i + 1) % $n]; $arestas[] = [$a[0], $a[1], $b[0], $b[1]]; } }
  $s = '';
  for ($r = 0; $r < 14; $r++) for ($k = 0; $k < 10; $k++) {
    $px = $g['x0'] + ($k + 0.5 - $dx) / $esc; $py = $g['y0'] + ($r + 0.5) / $esc; $volta = 0;
    foreach ($arestas as [$x1, $y1, $x2, $y2]) {
      if ($y1 <= $py) { if ($y2 > $py && (($x2 - $x1) * ($py - $y1) - ($px - $x1) * ($y2 - $y1)) > 0) $volta++; }
      elseif ($y2 <= $py && (($x2 - $x1) * ($py - $y1) - ($px - $x1) * ($y2 - $y1)) < 0) $volta--;
    }
    $s .= $volta !== 0 ? '1' : '0';
  }
  return $s;
}

/* ---------- 3 e 4. reconhecer as letras e montar as linhas ---------- */
function rpReconhece(array $g, float $alturaLinha): string {
  static $G = null; if ($G === null) $G = require __DIR__ . '/redeglifos.php';
  $a = rpAssinatura($g); if (isset($G['exatas'][$a])) return $G['exatas'][$a];
  /* não conhecida: a forma mais parecida (a grade, a proporção e o tamanho em relação à linha) */
  $grade = rpGrade($g); $w = $g['x1'] - $g['x0']; $h = max(0.01, $g['y1'] - $g['y0']); $asp = $w / $h; $rel = $h / max(0.01, $alturaLinha);
  $melhor = '?'; $dm = 1e9;
  foreach ($G['formas'] as [$ch, $gr, $as, $re]) {
    $dif = 0; for ($i = 0; $i < 140; $i++) if ($grade[$i] !== $gr[$i]) $dif++;
    $d = $dif / 140 + abs($asp - $as) * 0.35 + abs($rel - $re) * 0.6;
    if ($d < $dm) { $dm = $d; $melhor = $ch; }
  }
  return $dm < 0.12 ? $melhor : '?';   /* só a forma muito parecida: na dúvida, "?" (o valor não é lido, e a tela avisa), nunca um número trocado */
}
/* as linhas de uma página, de cima para baixo, com as letras de cada uma (da esquerda para a direita) */
function rpLinhasGlifos(string $conteudo): array {
  $letras = [];
  foreach (rpPinturas($conteudo) as $p) foreach (rpLetras($p['cs']) as $g) { if ($g['y1'] - $g['y0'] < 0.3 && $g['x1'] - $g['x0'] < 0.3) continue; $letras[] = $g; }
  usort($letras, fn($a, $b) => (($b['y0'] + $b['y1']) / 2) <=> (($a['y0'] + $a['y1']) / 2));
  $L = [];
  foreach ($letras as $g) {
    $yc = ($g['y0'] + $g['y1']) / 2; $h = $g['y1'] - $g['y0']; $achou = false;
    foreach ($L as &$li) if (abs($li['yc'] - $yc) <= 3.2 && abs($li['h'] - $h) < 9) { $li['gs'][] = $g; $achou = true; break; }
    unset($li);
    if (!$achou) $L[] = ['yc' => $yc, 'h' => $h, 'gs' => [$g]];
  }
  $arruma = function (array &$li): void {
    usort($li['gs'], fn($a, $b) => $a['x0'] <=> $b['x0']);
    $hs = array_map(fn($g) => $g['y1'] - $g['y0'], $li['gs']); sort($hs);
    $li['hmax'] = end($hs); $li['hRef'] = $hs[(int)floor(count($hs) * 0.75)] ?? $li['hmax'];
    $li['ymin'] = min(array_column($li['gs'], 'y0')); $li['ymax'] = max(array_column($li['gs'], 'y1'));
  };
  foreach ($L as &$li) $arruma($li); unset($li);
  /* a vírgula e o ponto ficam mais baixos que os números e caem numa "linha" só deles: voltam para a linha dos números, no lugar certo */
  $ordem = array_keys($L); usort($ordem, fn($a, $b) => $L[$a]['hmax'] <=> $L[$b]['hmax']);
  foreach ($ordem as $ia) {
    if (!isset($L[$ia])) continue; $A = $L[$ia]; $fica = [];
    foreach ($A['gs'] as $g) {
      $yc = ($g['y0'] + $g['y1']) / 2; $h = $g['y1'] - $g['y0']; $dest = null; $dm = 1e9;
      foreach ($L as $ib => $B) {
        if ($ib === $ia || $h > 0.55 * $B['hRef'] || $yc < $B['ymin'] - 0.6 * $B['hRef'] || $yc > $B['ymax']) continue;
        $esq = null; $dir = null; foreach ($B['gs'] as $q) { if ($q['x1'] <= $g['x0'] + 0.6) $esq = $q; elseif ($dir === null && $q['x0'] >= $g['x1'] - 0.6) $dir = $q; }
        if ($esq === null || $g['x0'] - $esq['x1'] > 0.6 * $B['hRef']) continue;
        $d = abs($yc - ($B['ymin'] + $B['ymax']) / 2); if ($d < $dm) { $dm = $d; $dest = $ib; }
      }
      if ($dest === null) $fica[] = $g; else { $L[$dest]['gs'][] = $g; $arruma($L[$dest]); }
    }
    if (!$fica) unset($L[$ia]); else { $L[$ia]['gs'] = $fica; $arruma($L[$ia]); }
  }
  usort($L, fn($a, $b) => $b['yc'] <=> $a['yc']);
  return array_values($L);
}
/* as linhas de uma página como texto: [['y' =>, 'x' =>, 'texto' =>], ...]; um espaço grande (outra coluna) vira tabulação */
function rpLinhas(string $conteudo): array {
  $out = [];
  foreach (rpLinhasGlifos($conteudo) as $li) {
    $t = ''; $ant = null; $hmax = $li['hmax'];
    foreach ($li['gs'] as $g) {
      if ($ant !== null) { $gap = $g['x0'] - $ant['x1']; if ($gap > max(14, $hmax * 2.2)) $t .= "\t"; elseif ($gap > max(2.2, $hmax * 0.28)) $t .= ' '; }
      $t .= rpReconhece($g, $li['hRef']); $ant = $g;
    }
    $out[] = ['y' => round($li['yc'], 1), 'x' => round($li['gs'][0]['x0'], 1), 'texto' => $t];
  }
  return $out;
}

/* ---------- 5. o relatório ---------- */
function rpNum(string $s): float { return (float)str_replace(['.', ','], ['', '.'], $s); }
function rpData(string $s): string { return preg_match('/^(\d\d)\/(\d\d)\/(\d{4})$/', $s, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3]) ? "$m[3]-$m[2]-$m[1]" : ''; }
/* lê o relatório: período, estabelecimentos, os dias (com as modalidades) e os avisos do que não deu para conferir */
function rpLer(string $bin, string $nomeArquivo = ''): array {
  if (substr($bin, 0, 5) !== '%PDF-') throw new Exception('Este arquivo não é um PDF.');
  $o = rpObjetos($bin); $pags = rpPaginas($o);
  if (!$pags) throw new Exception('Não achei as páginas do PDF.');
  $linhas = [];
  foreach ($pags as $i => $c) foreach (rpLinhas($c) as $l) { $l['pag'] = $i + 1; $linhas[] = $l; }
  return rpInterpreta($linhas, $nomeArquivo);
}
/* o texto das linhas → o relatório (separado da leitura do PDF, para conferir com linhas de exemplo) */
function rpInterpreta(array $linhas, string $nomeArquivo = ''): array {
  $texto = implode("\n", array_map(fn($l) => $l['texto'], $linhas));
  $R = ['de' => '', 'ate' => '', 'ecs' => [], 'dias' => [], 'avisos' => [], 'linhas' => $linhas];
  if (preg_match('/Per[ií]odo de vendas:\s*(\S{10})\s*[àa]\s*(\S{10})/u', $texto, $m)) { $R['de'] = rpData($m[1]); $R['ate'] = rpData($m[2]); }
  /* o período também vem no nome do arquivo que a Rede dá (Rede_Rel_Vendas_Resumido_18_11_2026-18_11_2026-...) */
  if (preg_match('/(\d\d)_(\d\d)_(\d{4})-(\d\d)_(\d\d)_(\d{4})/', $nomeArquivo, $f)) {
    $fd = "$f[3]-$f[2]-$f[1]"; $fa = "$f[6]-$f[5]-$f[4]";
    if ($R['de'] === '' || $R['ate'] === '') { $R['de'] = $fd; $R['ate'] = $fa; }
    elseif ($R['de'] !== $fd || $R['ate'] !== $fa) $R['avisos'][] = 'O nome do arquivo e o período escrito no PDF não batem; vale o do PDF.';
  }
  if (preg_match('/Estabelecimentos selecionados:\s*(\d+)\s*\(([^)]*)\)/su', $texto, $m)) { preg_match_all('/\d{6,}/', $m[2], $e); $R['ecs'] = array_values(array_unique($e[0])); $R['nEcs'] = (int)$m[1]; }
  /* os dias: "Vendas do dia: dd/mm/aaaa" abre o dia; as linhas das modalidades; "Total do dia: dd/mm/aaaa (n vendas)" e os cancelamentos */
  $dia = null; $modal = ['crédito' => 'credito', 'credito' => 'credito', 'débito' => 'debito', 'debito' => 'debito', 'pix' => 'pix', 'voucher' => 'voucher'];
  $vale = '(\d{1,3}(?:\.\d{3})*,\d\d)';
  foreach ($linhas as $l) {
    $t = preg_replace('/\s+/u', ' ', str_replace("\t", ' ', $l['texto']));
    if (preg_match('/Vendas do dia:\s*(\S{10})/u', $t, $m)) { $dia = rpData($m[1]) ?: ($R['de'] === $R['ate'] ? $R['de'] : ''); if ($dia === '') $R['avisos'][] = 'Não consegui ler a data de um dos dias.'; if ($dia !== '' && !isset($R['dias'][$dia])) $R['dias'][$dia] = ['modalidades' => [], 'cancelado' => 0.0, 'vendas' => null]; continue; }
    if (preg_match('/^(cr[ée]dito|d[ée]bito|pix|voucher) R\$ ?' . $vale . ' - ?R\$ ?' . $vale . ' R\$ ?' . $vale . ' (\d+)$/u', $t, $m)) {
      if ($dia === null) { $dia = $R['de'] === $R['ate'] ? $R['de'] : ''; if ($dia !== '' && !isset($R['dias'][$dia])) $R['dias'][$dia] = ['modalidades' => [], 'cancelado' => 0.0, 'vendas' => null]; }
      if ($dia === '') continue;
      $R['dias'][$dia]['modalidades'][$modal[$m[1]] ?? $m[1]] = ['bruto' => rpNum($m[2]), 'taxa' => rpNum($m[3]), 'liquido' => rpNum($m[4]), 'qtde' => (int)$m[5]];
      continue;
    }
    if ($dia !== null && $dia !== '' && preg_match('/Total do dia:\s*\S{10}\s*\((\d+) vendas?\)/u', $t, $m)) $R['dias'][$dia]['vendas'] = (int)$m[1];
    if ($dia !== null && $dia !== '' && preg_match('/Cancelamentos e estornos:\s*-\s*R\$ ?' . $vale . '(?: R\$ ?' . $vale . ' - ?R\$ ?' . $vale . ' R\$ ?' . $vale . ')?/u', $t, $m)) {
      $R['dias'][$dia]['cancelado'] = rpNum($m[1]);
      if (!empty($m[2])) $R['dias'][$dia]['total'] = ['bruto' => rpNum($m[2]), 'taxa' => rpNum($m[3]), 'liquido' => rpNum($m[4])];
    }
  }
  /* as contas: o bruto menos a taxa é o líquido (o Pix pode vir com o líquido zerado: ele cai direto na conta) */
  foreach ($R['dias'] as $d => &$x) {
    foreach ($x['modalidades'] as $f => &$v) {
      if ($f === 'pix' && $v['liquido'] < 0.005 && $v['bruto'] > 0.004) { $v['liquido'] = round($v['bruto'] - $v['taxa'], 2); $v['pixDireto'] = true; }
      if (abs(round($v['bruto'] - $v['taxa'] - $v['liquido'], 2)) > 0.011) $R['avisos'][] = "No dia " . date('d/m', strtotime($d)) . ", em $f, o bruto menos a taxa não dá o líquido: confira os valores.";
    }
    unset($v);
    if (!empty($x['total'])) { $sb = array_sum(array_map(fn($v) => $v['bruto'], $x['modalidades'])); $st = array_sum(array_map(fn($v) => $v['taxa'], $x['modalidades']));
      if (abs(round($sb - $x['total']['bruto'], 2)) > 0.011 || abs(round($st - $x['total']['taxa'], 2)) > 0.011) $R['avisos'][] = 'No dia ' . date('d/m', strtotime($d)) . ' a soma das modalidades não bateu com o total do dia da Rede: confira os valores.'; }
    $qt = array_sum(array_map(fn($v) => $v['qtde'], $x['modalidades']));
    if ($x['vendas'] !== null && $qt !== $x['vendas']) $R['avisos'][] = 'No dia ' . date('d/m', strtotime($d)) . ' a quantidade de vendas não bateu com o total do dia: confira os valores.';
  }
  unset($x);
  ksort($R['dias']);
  return $R;
}
