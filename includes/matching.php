<?php
/**
 * =============================================================================
 * COMPATIBILIDADE ALUNO x VAGA ("Vagas para voce")
 * -----------------------------------------------------------------------------
 * E uma pontuacao por REGRAS (comparacao de texto), nao e inteligencia artificial.
 * Compara o que o aluno escreveu no curriculo com o texto da vaga:
 *   +40  vaga indicada para o curso do aluno   (+20 se for aberta a todos os cursos)
 *   +12  por habilidade do curriculo citada na vaga (ate +36)
 *   +3   por palavra em comum entre experiencia/formacao e a vaga (ate +14)
 *   +6   idioma do curriculo citado na vaga
 *   +10  competencia de destaque do Perfil por Competencias citada na vaga
 * Maximo 100. Vaga de outro curso so aparece se citar 2+ habilidades do aluno.
 *
 * Le os arquivos do aluno DIRETO em data/users/<id>/, entao funciona tambem
 * quando roda sem sessao (resumo semanal, e-mails em lote).
 * =============================================================================
 */

function usuarioLerDados(int $id, string $nome): array {
    $nome = preg_replace('/[^A-Za-z0-9_\-]/', '', $nome);
    $path = DATA_PATH . "users/{$id}/{$nome}.json";
    if ($id <= 0 || !is_file($path)) return [];
    $d = json_decode((string) @file_get_contents($path), true);
    return is_array($d) ? $d : [];
}

function matchNormalizar($s): string {
    $s  = mb_strtolower((string) $s, 'UTF-8');
    $de = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
    $pa = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
    $s  = str_replace($de, $pa, $s);
    $s  = preg_replace('/[^a-z0-9+#]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

function matchStopwords(): array {
    return ['para','com','uma','dos','das','que','por','nos','nas','mais','como','sua','seu','ser','ter','sobre','entre',
            'area','vaga','estagio','curso','cursando','experiencia','conhecimento','conhecimentos','atuar','apoio',
            'empresa','equipe','trabalho','desejavel','diferencial','nivel','superior','graduacao'];
}

function matchTokens($texto): array {
    $stop = array_flip(matchStopwords());
    $out  = [];
    foreach (explode(' ', matchNormalizar($texto)) as $t) {
        if (strlen($t) >= 4 && !isset($stop[$t])) $out[$t] = $t;
    }
    return array_values($out);
}

/** Monta o "perfil" do aluno a partir do curriculo e do Perfil por Competencias */
function matchPerfilAluno(array $u): array {
    $id = (int) ($u['id'] ?? 0);
    $cv = usuarioLerDados($id, 'userCurriculum');

    $habs = [];
    foreach ((array) ($cv['habilidades'] ?? []) as $h) {
        if (!is_string($h)) continue;
        $n = matchNormalizar($h);
        if (strlen($n) >= 3) $habs[$n] = trim($h);
    }

    $idiomas = [];
    foreach ((array) ($cv['idiomas'] ?? []) as $i) {
        $n = matchNormalizar($i['idioma'] ?? '');
        if (strlen($n) >= 3) $idiomas[$n] = (string) $i['idioma'];
    }

    $partes = [(string) ($cv['resumo'] ?? ''), (string) ($u['curso'] ?? '')];
    $temExp = false;
    foreach ((array) ($cv['experiencias'] ?? []) as $e) {
        $partes[] = ($e['cargo'] ?? '') . ' ' . ($e['descricao'] ?? '');
        $temExp = true;
    }
    foreach ((array) ($cv['formacoes'] ?? []) as $f) {
        $partes[] = (string) ($f['curso'] ?? '');
    }
    $tokens = matchTokens(implode(' ', $partes));

    // Perfil por Competencias: pega as 3 competencias com maior percentual
    $comps = [];
    $perf  = usuarioLerDados($id, 'perfilResultado');
    $hist  = $perf['historico'] ?? [];
    if (is_array($hist) && !empty($hist)) {
        $ultimo = end($hist);
        $perc   = is_array($ultimo) ? ($ultimo['percentuais'] ?? []) : [];
        if (is_array($perc)) {
            $nums = [];
            foreach ($perc as $k => $v) {
                if (is_string($k) && is_numeric($v)) $nums[$k] = (float) $v;
            }
            arsort($nums);
            foreach (array_slice(array_keys($nums), 0, 3) as $k) {
                $rotulo = ucfirst(str_replace('_', ' ', $k));
                $comps[$rotulo] = matchTokens(str_replace('_', ' ', $k));
            }
        }
    }

    return [
        'id'           => $id,
        'curso'        => trim((string) ($u['curso'] ?? '')),
        'habilidades'  => $habs,
        'idiomas'      => $idiomas,
        'tokens'       => $tokens,
        'competencias' => $comps,
        'temCurriculo' => !empty($habs) || $temExp,
    ];
}

function matchPontuar(array $perfil, array $vaga): array {
    $texto = matchNormalizar(($vaga['title'] ?? '') . ' ' . ($vaga['description'] ?? '') . ' ' . ($vaga['requirements'] ?? ''));
    $cheio = ' ' . $texto . ' ';
    $score = 0;
    $motivos = [];

    $cursosVaga = array_values(array_filter(array_map('trim', explode(',', (string) ($vaga['courses'] ?? '')))));
    $cursoOk = true;
    if (empty($cursosVaga)) {
        $score += 20;
        $motivos[] = 'Aberta a todos os cursos';
    } else {
        $achou = false;
        $meu = matchNormalizar($perfil['curso']);
        foreach ($cursosVaga as $c) {
            if ($meu !== '' && matchNormalizar($c) === $meu) { $achou = true; break; }
        }
        if ($achou) {
            $score += 40;
            $motivos[] = 'Indicada para o seu curso';
        } else {
            $cursoOk = false;
        }
    }

    $achadas = [];
    foreach ($perfil['habilidades'] as $n => $orig) {
        if (strpos($cheio, ' ' . $n . ' ') !== false) $achadas[] = $orig;
    }
    if ($achadas) {
        $score += min(36, count($achadas) * 12);
        $motivos[] = 'Habilidades do seu currículo citadas: ' . implode(', ', array_slice($achadas, 0, 4));
    }

    $tokensVaga = matchTokens(($vaga['title'] ?? '') . ' ' . ($vaga['requirements'] ?? '') . ' ' . ($vaga['description'] ?? ''));
    $comum = array_values(array_intersect($perfil['tokens'], $tokensVaga));
    if ($comum) {
        $score += min(14, count($comum) * 3);
        $motivos[] = 'Afinidade com sua experiência e formação: ' . implode(', ', array_slice($comum, 0, 4));
    }

    foreach ($perfil['idiomas'] as $n => $orig) {
        if (strpos($cheio, ' ' . $n . ' ') !== false) {
            $score += 6;
            $motivos[] = 'Idioma citado na vaga: ' . $orig;
            break;
        }
    }

    foreach ($perfil['competencias'] as $rotulo => $toks) {
        if (!empty($toks) && array_intersect($toks, $tokensVaga)) {
            $score += 10;
            $motivos[] = 'Combina com sua competência de destaque: ' . $rotulo;
            break;
        }
    }

    return [
        'score'    => min(100, $score),
        'motivos'  => $motivos,
        'cursoOk'  => $cursoOk,
        'achadas'  => $achadas,
    ];
}

/**
 * $candidatas = lista de ['origem' => 'faculdade|egresso', 'vaga' => [...]]
 * Devolve as melhores, ja ordenadas, cada uma com 'score' e 'motivos'.
 */
function matchVagasPara(array $perfil, array $candidatas, int $minimo = 25, int $limite = 20): array {
    $res = [];
    foreach ($candidatas as $c) {
        $r = matchPontuar($perfil, $c['vaga']);
        if (!$r['cursoOk'] && count($r['achadas']) < 2) continue;
        if ($r['score'] < $minimo) continue;
        $res[] = $c + $r;
    }
    usort($res, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($res, 0, $limite);
}

function matchRotulo(int $score): array {
    if ($score >= 70) return ['Alta compatibilidade', 'bg-emerald-50 text-emerald-700 border-emerald-200'];
    if ($score >= 45) return ['Boa compatibilidade', 'bg-sky-50 text-sky-700 border-sky-200'];
    return ['Possível', 'bg-amber-50 text-amber-700 border-amber-200'];
}
