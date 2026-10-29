<?php
/**
 * =============================================================================
 * MELHORIAS (fase 5): funcoes compartilhadas
 * -----------------------------------------------------------------------------
 * Filtros de vagas (cidade/ABC, modalidade, bolsa), selos e compartilhar,
 * pontuacao do curriculo, passos/acoes do inicio e log de acoes do admin.
 * Nada aqui usa IA: sao regras simples sobre os dados que ja existem.
 * =============================================================================
 */

require_once __DIR__ . '/vagas.php';
require_once __DIR__ . '/egressos.php';

function mhSemAcento(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
    ]);
}

function mhBaseUrl(): string {
    if (function_exists('appBaseUrl')) return appBaseUrl();
    $env = $_ENV['APP_URL'] ?? getenv('APP_URL');
    if ($env) return rtrim((string) $env, '/');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/* ------------------------------------------------------------------ */
/* Filtros de vagas                                                    */
/* ------------------------------------------------------------------ */

function mhCidadesFiltro(): array {
    return [
        ''            => 'Todas as cidades',
        'sao-paulo'   => 'São Paulo (capital)',
        'abc'         => 'Todo o ABC',
        'santo-andre' => 'Santo André',
        'sao-bernardo'=> 'São Bernardo do Campo',
        'sao-caetano' => 'São Caetano do Sul',
        'abc-outras'  => 'Outras do ABC (Diadema, Mauá...)',
        'remoto'      => 'Remoto',
    ];
}

function mhModalidadesFiltro(): array {
    return ['' => 'Todas as modalidades', 'presencial' => 'Presencial', 'hibrido' => 'Híbrido', 'remoto' => 'Remoto'];
}

function mhBolsaFiltro(): array {
    return [0 => 'Qualquer valor', 1000 => 'A partir de R$ 1.000', 1500 => 'A partir de R$ 1.500', 2000 => 'A partir de R$ 2.000', 2500 => 'A partir de R$ 2.500'];
}

function mhOrdemFiltro(): array {
    return ['recentes' => 'Mais recentes', 'bolsa' => 'Maior bolsa', 'vencer' => 'Perto de vencer'];
}

/** santo-andre | sao-bernardo | sao-caetano | abc-outras | sao-paulo | outras */
function mhGrupoCidade(array $v): string {
    $l = mhSemAcento((string) ($v['location'] ?? ''));
    if (strpos($l, 'santo andre') !== false)  return 'santo-andre';
    if (strpos($l, 'sao bernardo') !== false) return 'sao-bernardo';
    if (strpos($l, 'sao caetano') !== false)  return 'sao-caetano';
    foreach (['diadema', 'maua', 'ribeirao pires', 'rio grande da serra'] as $c) {
        if (strpos($l, $c) !== false) return 'abc-outras';
    }
    if (preg_match('/\babc\b/', $l)) return 'abc-outras';
    if (strpos($l, 'sao paulo') !== false
        || preg_match('/(^|[\s,\-\/])sp$/', trim($l))
        || preg_match('/vila olimpia|itaim|pinheiros|paulista|faria lima|berrini|morumbi|brooklin|tatuape/', $l)) {
        return 'sao-paulo';
    }
    return 'outras';
}

function mhModalidade(array $v): string {
    $t = mhSemAcento((string) ($v['type'] ?? '') . ' ' . (string) ($v['location'] ?? ''));
    if (strpos($t, 'remot') !== false || strpos($t, 'home office') !== false) return 'remoto';
    if (strpos($t, 'hibrid') !== false) return 'hibrido';
    return 'presencial';
}

function mhCidadeCasa(array $v, string $filtro): bool {
    if ($filtro === '') return true;
    if ($filtro === 'remoto') return mhModalidade($v) === 'remoto';
    $g = mhGrupoCidade($v);
    if ($filtro === 'abc') return in_array($g, ['santo-andre', 'sao-bernardo', 'sao-caetano', 'abc-outras'], true);
    return $g === $filtro;
}

/** "R$ 1.200,00" -> 1200.0 ; "1300" -> 1300.0 ; "A combinar" -> 0.0 */
function mhBolsaValor($salary): float {
    $s = preg_replace('/[^0-9,\.]/', '', (string) $salary);
    if ($s === '' || $s === null) return 0.0;
    if (strpos($s, ',') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);
    }
    return (float) $s;
}

/** Data de publicacao da vaga (aceita d/m/Y, Y-m-d e publishedTs dos egressos) */
function mhVagaTs(array $v): int {
    if (!empty($v['publishedTs'])) return (int) $v['publishedTs'];
    $c = trim((string) ($v['createdAt'] ?? ''));
    if ($c === '') return 0;
    foreach (['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $f) {
        $d = DateTime::createFromFormat($f, $c);
        if ($d) return $d->getTimestamp();
    }
    $t = strtotime($c);
    return $t ? (int) $t : 0;
}

function mhJaCandidatou(string $origem, int $id): bool {
    static $set = null;
    if ($set === null) {
        $set = [];
        if (empty($_SESSION['is_admin']) && empty($_SESSION['is_egresso'])) {
            $lista = loadData('userCandidaturas', true);
            foreach ((is_array($lista) ? $lista : []) as $c) {
                $set[($c['origem'] ?? '') . ':' . (int) ($c['jobId'] ?? 0)] = true;
            }
        }
    }
    return isset($set[$origem . ':' . $id]);
}

/** Selos pequenos embaixo do nome da empresa: Nova, Termina em X dias, Ja se candidatou, Vaga externa */
function vagaSelosHtml(array $v): string {
    $selos = [];
    $ts = mhVagaTs($v);
    if ($ts > 0 && $ts >= strtotime('-7 days')) {
        $selos[] = ['Nova', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-bolt'];
    }
    $dias = vagaDiasRestantes($v);
    if ($dias !== null && $dias >= 0 && $dias <= 3) {
        $txt = $dias === 0 ? 'Termina hoje' : 'Termina em ' . $dias . ' dia' . ($dias > 1 ? 's' : '');
        $selos[] = [$txt, 'bg-amber-50 text-amber-700 border-amber-200', 'fa-hourglass-half'];
    }
    if (!empty($v['externa'])) {
        $selos[] = ['Vaga externa', 'bg-gray-100 text-gray-600 border-gray-200', 'fa-external-link-alt'];
    }
    if (mhJaCandidatou((string) ($v['origem'] ?? 'faculdade'), (int) ($v['id'] ?? 0))) {
        $selos[] = ['Você já se candidatou', 'bg-[#4A9FCA]/10 text-[#2B7FA6] border-[#4A9FCA]/30', 'fa-check'];
    }
    if (!$selos) return '';
    $h = '<div class="flex flex-wrap gap-1.5 mt-1.5">';
    foreach ($selos as $s) {
        $h .= '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold border ' . $s[1] . '">'
            . '<i class="fas ' . $s[2] . ' text-[10px]"></i> ' . htmlspecialchars($s[0], ENT_QUOTES, 'UTF-8') . '</span>';
    }
    return $h . '</div>';
}

/** Botoes de compartilhar (WhatsApp e LinkedIn) */
function vagaCompartilharHtml(array $v): string {
    $titulo  = (string) ($v['title'] ?? '');
    $empresa = (string) ($v['company'] ?? '');
    $texto   = 'Vaga de estágio: ' . $titulo . ' - ' . $empresa . '. Veja na Central de Estágios da FSA: '
             . mhBaseUrl() . '/jobs.php?search=' . rawurlencode($titulo);
    $classe  = 'px-3 py-2 border border-gray-200 text-gray-500 rounded-xl hover:bg-gray-50 transition-all text-sm inline-flex items-center';
    $h = '<a href="https://wa.me/?text=' . rawurlencode($texto) . '" target="_blank" rel="noopener" title="Compartilhar no WhatsApp" class="' . $classe . '">'
       . '<i class="fab fa-whatsapp text-[#27AE60]"></i></a>';
    $link = trim((string) ($v['applicationLink'] ?? ''));
    if (preg_match('#^https?://#i', $link)) {
        $h .= '<a href="https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($link) . '" target="_blank" rel="noopener" title="Compartilhar no LinkedIn" class="' . $classe . '">'
            . '<i class="fab fa-linkedin text-[#0A66C2]"></i></a>';
    }
    return $h;
}

/* ------------------------------------------------------------------ */
/* Curriculo e perfil                                                  */
/* ------------------------------------------------------------------ */

/** Pontuacao de 0 a 100 + o que falta (cada item com dica) */
function mhCurriculoPontuacao(array $cv): array {
    $txt = function ($k) use ($cv) { return trim((string) ($cv[$k] ?? '')); };
    $n   = function ($k) use ($cv) { return is_array($cv[$k] ?? null) ? count($cv[$k]) : 0; };

    $descOk = false;
    $exps = is_array($cv['experiencias'] ?? null) ? $cv['experiencias'] : [];
    if ($exps) {
        $descOk = true;
        foreach ($exps as $e) {
            if (mb_strlen(trim((string) ($e['descricao'] ?? ''))) < 20) { $descOk = false; break; }
        }
    }
    $habs = $n('habilidades');

    $itens = [
        ['Nome completo',        $txt('nome') !== '',     8,  'Informe seu nome completo.'],
        ['E-mail',               $txt('email') !== '',    8,  'Coloque um e-mail que você consulta sempre.'],
        ['Telefone',             $txt('telefone') !== '', 8,  'Inclua um telefone ou WhatsApp para contato.'],
        ['Cidade',               $txt('cidade') !== '',   6,  'Informe a cidade, como Santo André, SP.'],
        ['Resumo profissional',  mb_strlen($txt('resumo')) >= 60, 16, 'Escreva 2 a 4 linhas sobre você e o tipo de estágio que busca.'],
        ['Formação acadêmica',   $n('formacoes') > 0,     14, 'Cadastre seu curso e a instituição.'],
        ['Experiência, projeto ou voluntariado', $n('experiencias') > 0, 12, 'Sem experiência formal? Inclua projetos da faculdade, voluntariado ou atividades.'],
        ['Descrição nas experiências', $descOk,           6,  'Explique o que você fazia em cada experiência (mínimo de 20 caracteres).'],
        ['Pelo menos 5 habilidades', $habs >= 5,          14, 'Liste ferramentas e competências que você realmente domina.'],
        ['Idiomas',              $n('idiomas') > 0,       8,  'Informe os idiomas e o seu nível.'],
    ];
    $pts = 0; $falta = [];
    foreach ($itens as $it) {
        if ($it[1]) $pts += $it[2];
        else $falta[] = ['label' => $it[0], 'dica' => $it[3], 'pts' => $it[2]];
    }
    if ($habs > 0 && $habs < 5) $pts += (int) round(14 * $habs / 5);
    return ['pct' => min(100, $pts), 'faltando' => $falta];
}

/** Todo o texto do curriculo numa string (usado no "adaptar para a vaga") */
function mhCurriculoTexto(array $cv): string {
    $pular = ['layout', 'corTema', 'idiomaCV', 'fotoCV', 'exibirFoto', 'inicio', 'fim', 'atual', 'email', 'telefone'];
    $saida = [];
    $andar = function ($v, $k = '') use (&$andar, &$saida, $pular) {
        if (is_array($v)) {
            foreach ($v as $kk => $vv) $andar($vv, is_string($kk) ? $kk : $k);
            return;
        }
        if (is_string($v) && !in_array($k, $pular, true)) $saida[] = $v;
    };
    $andar($cv);
    return implode(' ', $saida);
}

/** Links e disponibilidade do aluno (data/users/<id>/userProfile.json -> preferencias) */
function mhPreferencias(): array {
    $p  = loadData('userProfile', true);
    $pr = (is_array($p) && is_array($p['preferencias'] ?? null)) ? $p['preferencias'] : [];
    return $pr + ['linkedin' => '', 'github' => '', 'portfolio' => '', 'turno' => '', 'inicio' => '', 'modalidade' => '', 'areas' => ''];
}

/* ------------------------------------------------------------------ */
/* Inicio do aluno: passos, proximas acoes e vagas recomendadas        */
/* ------------------------------------------------------------------ */

function mhDashboardAcoes(bool $restrito): array {
    $userId  = (int) ($_SESSION['user_id'] ?? 0);
    $cv      = loadData('userCurriculum', true);
    if (!is_array($cv)) $cv = [];
    $pont    = mhCurriculoPontuacao($cv);
    $prefs   = mhPreferencias();
    $prof    = loadData('userProfile', true);
    $temFoto = is_array($prof) && !empty($prof['foto']);
    $usuario = egressoCarregarUsuario($userId) ?: [];
    $curso   = trim((string) ($usuario['curso'] ?? ''));

    $passos = [];
    $passos[] = ['Complete seu perfil (foto e disponibilidade)', ($temFoto && ($prefs['turno'] !== '' || $prefs['modalidade'] !== '')), 'profile.php', 'fa-user'];
    $passos[] = ['Monte seu currículo (70% ou mais)', $pont['pct'] >= 70, 'curriculum.php', 'fa-file-alt'];

    if (!$restrito) {
        $cursosAlerta = $usuario['alertas']['cursos'] ?? [];
        $passos[] = ['Escolha cursos de interesse e ative os alertas', !empty($cursosAlerta), 'profile.php?config=alertas', 'fa-bell'];
    }
    $c = loadData('userCandidaturas', true);
    $cands = is_array($c) ? $c : [];
    $passos[] = ['Candidate-se à sua primeira vaga', count($cands) > 0, 'jobs.php', 'fa-paper-plane'];

    $acoes  = [];
    $agora  = time();

    // entrevistas nos proximos 7 dias
    foreach ($cands as $c) {
        $ent = (string) ($c['entrevista'] ?? '');
        if ($ent === '') continue;
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $ent);
        if (!$d) continue;
        $ts = $d->getTimestamp();
        if ($ts >= $agora - 7200 && $ts <= $agora + 7 * 86400) {
            $acoes[] = [
                'icone' => 'fa-calendar-check', 'cor' => 'amber',
                'titulo' => 'Entrevista em ' . $d->format('d/m') . ' às ' . $d->format('H:i'),
                'texto'  => trim((string) ($c['title'] ?? '') . ' · ' . (string) ($c['company'] ?? ''), ' ·'),
                'link'   => 'minhas-vagas.php?aba=candidaturas',
            ];
        }
    }

    // candidaturas sem retorno ha 7 dias ou mais
    $semRetorno = 0;
    foreach ($cands as $c) {
        if (($c['status'] ?? 'Enviada') === 'Enviada' && $agora - (int) ($c['statusTs'] ?? ($c['ts'] ?? $agora)) >= 7 * 86400) $semRetorno++;
    }
    if ($semRetorno > 0) {
        $acoes[] = [
            'icone' => 'fa-bell', 'cor' => 'red',
            'titulo' => $semRetorno . ' candidatura' . ($semRetorno > 1 ? 's' : '') . ' sem retorno há 7 dias ou mais',
            'texto'  => 'Já teve resposta? Atualize o andamento.',
            'link'   => 'minhas-vagas.php?aba=candidaturas&status=Enviada',
        ];
    }

    // vagas salvas que encerram em ate 3 dias
    if (!$restrito) {
        $porId = [];
        foreach (loadData('platform_jobs') as $j) $porId[(int) ($j['id'] ?? 0)] = $j;
        $vencendo = 0;
        foreach (favoritosLista() as $f) {
            if (($f['origem'] ?? '') !== 'faculdade') continue;
            $j = $porId[(int) ($f['jobId'] ?? 0)] ?? null;
            if (!$j || !vagaVigente($j)) continue;
            $dias = vagaDiasRestantes($j);
            if ($dias !== null && $dias <= 3) $vencendo++;
        }
        if ($vencendo > 0) {
            $acoes[] = [
                'icone' => 'fa-hourglass-half', 'cor' => 'amber',
                'titulo' => $vencendo . ' vaga' . ($vencendo > 1 ? 's salvas encerram' : ' salva encerra') . ' em até 3 dias',
                'texto'  => 'Candidate-se antes que saiam do ar.',
                'link'   => 'minhas-vagas.php?aba=salvas',
            ];
        }
    }

    // vagas novas (7 dias) para o curso do aluno
    $novas = 0;
    foreach (loadData('platform_jobs') as $j) {
        if (!vagaVigente($j)) continue;
        if (mhVagaTs($j) < strtotime('-7 days')) continue;
        $cj = trim((string) ($j['courses'] ?? ''));
        if ($cj !== '' && $curso !== '' && !in_array($curso, array_map('trim', explode(',', $cj)), true)) continue;
        $novas++;
    }
    if ($novas > 0) {
        $acoes[] = [
            'icone' => 'fa-briefcase', 'cor' => 'green',
            'titulo' => $novas . ' vaga' . ($novas > 1 ? 's novas' : ' nova') . ' para o seu curso',
            'texto'  => 'Publicadas nos últimos 7 dias.',
            'link'   => 'jobs.php',
        ];
    }

    // curriculo incompleto
    if ($pont['pct'] < 100 && !empty($pont['faltando'])) {
        $acoes[] = [
            'icone' => 'fa-file-alt', 'cor' => 'blue',
            'titulo' => 'Seu currículo está ' . $pont['pct'] . '% completo',
            'texto'  => 'Falta: ' . $pont['faltando'][0]['label'] . '.',
            'link'   => 'curriculum.php',
        ];
    }

    // vagas recomendadas (regras de compatibilidade, sem IA)
    $rec = [];
    try {
        require_once __DIR__ . '/matching.php';
        $perfil = matchPerfilAluno($usuario);
        if ($restrito) $perfil['competencias'] = []; // sem dados do Perfil por Competencias (IA)
        $cand = [];
        foreach (array_values(loadData('platform_jobs')) as $j) {
            if (vagaVigente($j)) $cand[] = ['origem' => 'faculdade', 'vaga' => $j];
        }
        if (!$restrito) {
            foreach (egressoVagas() as $j) {
                if (egressoVagaStatusEfetivo($j) === 'aprovada') $cand[] = ['origem' => 'egresso', 'vaga' => $j];
            }
        }
        $rec = matchVagasPara($perfil, $cand, 25, 3);
    } catch (Throwable $e) {
        error_log('mhDashboardAcoes: ' . $e->getMessage());
        $rec = [];
    }

    return ['passos' => $passos, 'acoes' => $acoes, 'rec' => $rec, 'pontuacao' => $pont];
}

/* ------------------------------------------------------------------ */
/* Admin                                                               */
/* ------------------------------------------------------------------ */

/** Registra quem fez o que (data/admin_log.json, guarda as ultimas 300) */
function mhLogAdmin(string $acao, string $detalhe = ''): void {
    try {
        $l = loadData('admin_log');
        if (!is_array($l)) $l = [];
        array_unshift($l, [
            'ts'      => time(),
            'admin'   => (string) ($_SESSION['user_name'] ?? 'Sistema'),
            'acao'    => $acao,
            'detalhe' => mb_substr($detalhe, 0, 200),
        ]);
        saveData('admin_log', array_slice($l, 0, 300));
    } catch (Throwable $e) {
        error_log('mhLogAdmin: ' . $e->getMessage());
    }
}

function mhNovoIdVaga(array $jobs): int {
    $m = 0;
    foreach ($jobs as $j) $m = max($m, (int) ($j['id'] ?? 0));
    return $m + 1;
}

/** Chave para detectar vaga repetida (titulo + empresa + cidade) */
function mhChaveVaga(string $titulo, string $empresa, string $cidade = ''): string {
    return preg_replace('/[^a-z0-9]+/', '', mhSemAcento($titulo . '|' . $empresa . '|' . $cidade));
}
