<?php
/**
 * =============================================================================
 * ARQUITETURA DE PERFIS (quem e quem, o que ve e o que pode fazer)
 * -----------------------------------------------------------------------------
 * Este arquivo e a FONTE UNICA das regras de acesso da Central.
 * Antes, as regras estavam espalhadas em if/else no config.php e no header.php.
 * Agora cada perfil e descrito uma vez so, aqui, com:
 *
 *   grupo     -> alunos | professores | egressos | funcionarios
 *   rotulo    -> nome que aparece para as pessoas
 *   home      -> pagina aberta depois do login
 *   fallback  -> para onde vai quem tenta abrir uma pagina que nao pode
 *   paginas   -> LISTA FECHADA de paginas que o perfil pode abrir
 *   menu      -> icones do menu lateral (na ordem em que aparecem)
 *   recursos  -> "pode fazer X?" usado dentro das telas (perfilPode)
 *
 * Como o perfil e descoberto (perfilDoUsuario), pelo usuarios.json:
 *   is_admin + tipo "admin_egressos"  -> admin_egressos   (Funcionarios)
 *   is_admin                          -> admin            (Funcionarios: Coordenacao)
 *   tipo "egresso"                    -> egresso          (Egressos)
 *   tipo "professor"                  -> professor        (Professores)
 *   tipo "aluno_restrito"             -> aluno_restrito   (Alunos)
 *   (nada)                            -> aluno            (Alunos)
 *
 * REGRA DE OURO: pagina nova precisa entrar na lista "paginas" de quem pode
 * abrir. Se nao entrar, ninguem acessa (a pessoa volta para o fallback).
 *
 * As flags antigas da sessao (is_admin, is_egresso, is_admin_egressos,
 * is_restrito) continuam existindo e sao mantidas em sincronia, para nao
 * quebrar as telas que ainda usam essas flags.
 * =============================================================================
 */

/* ---------------------------------------------------------------------------
 * Grupos (as 4 divisoes do portal)
 * ------------------------------------------------------------------------- */
function perfisGrupos(): array {
    return [
        'alunos'       => ['rotulo' => 'Alunos',       'perfis' => ['aluno', 'aluno_restrito']],
        'professores'  => ['rotulo' => 'Professores',  'perfis' => ['professor']],
        'egressos'     => ['rotulo' => 'Egressos',     'perfis' => ['egresso']],
        'funcionarios' => ['rotulo' => 'Funcionários', 'perfis' => ['admin', 'admin_egressos']],
    ];
}

/** Item de menu. grupo "gestao" aparece abaixo do divisor, com o icone menor. */
function perfilItemMenu(string $href, string $titulo, string $icone, string $badge = '', string $grupo = 'principal', string $cond = ''): array {
    return ['href' => $href, 'titulo' => $titulo, 'icone' => $icone, 'badge' => $badge, 'grupo' => $grupo, 'cond' => $cond];
}

/* ---------------------------------------------------------------------------
 * Registro de perfis
 * ------------------------------------------------------------------------- */
function perfisRegistro(): array {
    static $reg = null;
    if ($reg !== null) return $reg;

    $comuns = ['index.php', 'forgot-password.php'];

    // ---------- Menus reutilizados ----------
    $mInicio   = perfilItemMenu('dashboard.php', 'Início', 'fa-home');
    $mPerfil   = perfilItemMenu('profile.php', 'Perfil', 'fa-user');
    $mCv       = perfilItemMenu('curriculum.php', 'Currículo', 'fa-file-alt');
    $mDocs     = perfilItemMenu('documents.php', 'Documentos', 'fa-folder');
    $mVagas    = perfilItemMenu('jobs.php', 'Vagas', 'fa-briefcase');
    $mMinhas   = perfilItemMenu('minhas-vagas.php', 'Minhas Vagas', 'fa-clipboard-check');
    $mParaVoce = perfilItemMenu('vagas-para-voce.php', 'Vagas para Você', 'fa-star');
    $mDicas    = perfilItemMenu('career-tips.php', 'Dicas de Carreira', 'fa-lightbulb');

    // ---------- Paginas da area do aluno ----------
    $paginasAlunoBase = [
        'dashboard.php', 'profile.php', 'curriculum.php', 'documents.php',
        'jobs.php', 'minhas-vagas.php', 'vagas-para-voce.php', 'career-tips.php',
        'candidatar.php', 'favoritar.php', 'candidaturas.php', 'favoritos.php',
    ];

    // ---------- Gestao (funcionarios) ----------
    $paginasCoordenacao = [
        'dashboard.php', 'jobs.php', 'egressos.php',
        'admin-dashboard.php',   // painel "Visão geral dos alunos" (aberto pela tela Painéis)
        'admin-jobs.php', 'admin-signature.php', 'posts.php',
        'admin-alertas.php', 'admin-funil.php', 'admin-sugeridas.php', 'admin-alunos-importar.php',
        'admin-totvs.php',   // cadastro de alunos pelo TOTVS / banco de dados
        'admin-banco.php',   // Banco de dados: conexão, tabelas de cada aba, migração
        'admin-paineis.php', 'bi-dados.php',   // Painéis BI
    ];
    $menuCoordenacao = [
        $mInicio,
        $mVagas,
        perfilItemMenu('admin-jobs.php', 'Admin Vagas', 'fa-cog', 'admin_jobs', 'gestao'),
        perfilItemMenu('admin-sugeridas.php', 'Vagas Sugeridas (externas)', 'fa-inbox', '', 'gestao'),
        perfilItemMenu('admin-signature.php', 'Admin Assinaturas', 'fa-file-signature', 'admin_assinaturas', 'gestao'),
        perfilItemMenu('posts.php', 'Admin Posts', 'fa-newspaper', '', 'gestao'),
        perfilItemMenu('admin-paineis.php', 'Painéis', 'fa-chart-pie', '', 'gestao'),   // alunos, funil, egressos e os criados
        perfilItemMenu('admin-totvs.php', 'Cadastro de Alunos (TOTVS)', 'fa-user-plus', '', 'gestao'),
        perfilItemMenu('admin-banco.php', 'Banco de dados', 'fa-database', '', 'gestao'),
        perfilItemMenu('admin-alertas.php', 'Alertas e E-mails', 'fa-envelope', '', 'gestao'),
    ];

    $reg = [

        /* ============================ ALUNOS ============================ */
        'aluno' => [
            'grupo'    => 'alunos',
            'rotulo'   => 'Aluno',
            'home'     => 'dashboard.php',
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, $paginasAlunoBase, [
                'signature.php', 'mentoria.php', 'request-job.php', 'egressos.php',
                'alertas-salvar.php', 'perfil-extras-salvar.php',
                'mentoria-chat.php',   // chat da mentoria (JSON)
            ]),
            'menu' => [
                $mInicio, $mPerfil, $mCv, $mDocs, $mVagas, $mMinhas, $mParaVoce,
                perfilItemMenu('signature.php', 'Assinaturas', 'fa-file-signature', 'aluno_assinaturas'),
                $mDicas,
                perfilItemMenu('mentoria.php', 'Mentoria', 'fa-hands-helping', 'mentoria'),   // bolinha = mensagens novas
                perfilItemMenu('request-job.php', 'Solicitar Vaga', 'fa-plus-circle', '', 'principal', 'nao_bloqueado'),
            ],
            'recursos' => ['area_aluno', 'candidatar', 'favoritar', 'alertas', 'ia_perfil', 'vagas_egresso'],
        ],

        'aluno_restrito' => [
            'grupo'    => 'alunos',
            'rotulo'   => 'Aluno (acesso restrito)',
            'home'     => 'dashboard.php',
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, $paginasAlunoBase),
            'menu'     => [$mInicio, $mPerfil, $mCv, $mDocs, $mVagas, $mMinhas, $mParaVoce, $mDicas],
            // sem: alertas, IA do perfil, vagas de egressos, assinaturas, mentoria, solicitar vaga
            'recursos' => ['area_aluno', 'candidatar', 'favoritar'],
        ],

        /* ========================== PROFESSORES ========================= */
        // Ponto de partida: so consulta. Amplie aqui quando a coordenacao definir
        // o papel do professor (ex.: orientador de estagio, ver alunos do curso).
        'professor' => [
            'grupo'    => 'professores',
            'rotulo'   => 'Professor',
            'home'     => 'dashboard.php',
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, ['dashboard.php', 'jobs.php', 'egressos.php', 'career-tips.php']),
            'menu'     => [$mInicio, $mVagas, $mDicas],   // vagas de egressos já aparecem em Vagas
            'recursos' => ['vagas_egresso'],
        ],

        /* =========================== EGRESSOS =========================== */
        'egresso' => [
            'grupo'    => 'egressos',
            'rotulo'   => 'Egresso',
            'home'     => 'jobs.php',
            'fallback' => 'jobs.php',
            'paginas'  => array_merge($comuns, ['jobs.php', 'egressos.php', 'egresso-perfil.php', 'egresso-publicar.php', 'mentoria.php', 'mentoria-chat.php']),
            'menu'     => [
                perfilItemMenu('jobs.php', 'Vagas', 'fa-briefcase'),
                perfilItemMenu('egresso-publicar.php', 'Divulgar Vaga', 'fa-plus-circle'),
                perfilItemMenu('egresso-perfil.php', 'Meu Perfil de Egresso', 'fa-user-graduate'),
                perfilItemMenu('mentoria.php', 'Mentoria', 'fa-hands-helping', 'mentoria'),
            ],
            'recursos' => ['vagas_egresso', 'divulgar_vaga', 'ser_mentor'],
        ],

        /* ========================= FUNCIONARIOS ========================= */
        'admin' => [
            'grupo'    => 'funcionarios',
            'rotulo'   => 'Coordenação de Estágios',
            'home'     => 'dashboard.php',
            'fallback' => 'admin-dashboard.php',
            'paginas'  => array_merge($comuns, $paginasCoordenacao),
            'menu'     => $menuCoordenacao,
            'recursos' => ['gestao', 'vagas_egresso'],
        ],

        'admin_egressos' => [
            'grupo'    => 'funcionarios',
            'rotulo'   => 'Coordenação de Egressos',
            'home'     => 'dashboard.php',
            'fallback' => 'admin-dashboard.php',
            'paginas'  => array_merge($comuns, $paginasCoordenacao, ['admin-egressos.php', 'admin-mentoria.php', 'admin-mentoria-conversa.php', 'admin-relatorios.php']),
            'menu'     => array_merge($menuCoordenacao, [
                perfilItemMenu('admin-egressos.php', 'Gestão de Egressos', 'fa-users', 'admin_egressos', 'gestao'),
                perfilItemMenu('admin-mentoria.php', 'Mentoria (Admin)', 'fa-hands-helping', 'mentoria', 'gestao'),
                // Relatórios de Egressos agora fica em Painéis > Relatórios (admin-relatorios.php continua liberado)
            ]),
            'recursos' => ['gestao', 'gestao_egressos', 'vagas_egresso'],
        ],
    ];

    return $reg;
}

/* ---------------------------------------------------------------------------
 * Descobrir o perfil
 * ------------------------------------------------------------------------- */
function perfilDoUsuario(array $u): string {
    $tipo  = (string) ($u['tipo'] ?? '');
    $admin = !empty($u['is_admin']) && $u['is_admin'] !== 'false' && $u['is_admin'] !== 'False';

    if ($admin && $tipo === 'admin_egressos') return 'admin_egressos';
    if ($admin)                               return 'admin';
    if ($tipo === 'egresso')                  return 'egresso';
    if ($tipo === 'professor')                return 'professor';
    if ($tipo === 'aluno_restrito')           return 'aluno_restrito';
    return 'aluno';
}

/** Mantem as flags antigas da sessao coerentes com o perfil. */
function perfilSincronizarSessao(): void {
    $p = $_SESSION['perfil'] ?? 'aluno';
    $_SESSION['is_admin']          = in_array($p, ['admin', 'admin_egressos'], true);
    $_SESSION['is_admin_egressos'] = ($p === 'admin_egressos');
    $_SESSION['is_egresso']        = ($p === 'egresso');
    $_SESSION['is_restrito']       = ($p === 'aluno_restrito');
    $_SESSION['is_professor']      = ($p === 'professor');
}

/** Perfil do usuario logado. Sessoes antigas (sem "perfil") sao corrigidas aqui. */
function perfilAtual(): string {
    if (empty($_SESSION['user_id'])) return 'visitante';

    $p = $_SESSION['perfil'] ?? '';
    if ($p === '' || !isset(perfisRegistro()[$p])) {
        $p = 'aluno';
        foreach (loadData('usuarios') as $u) {
            if (($u['id'] ?? null) == $_SESSION['user_id']) { $p = perfilDoUsuario($u); break; }
        }
        $_SESSION['perfil'] = $p;
        perfilSincronizarSessao();
    }
    return $p;
}

function perfilConfig(?string $perfil = null): array {
    $reg = perfisRegistro();
    return $reg[$perfil ?? perfilAtual()] ?? $reg['aluno'];
}

function perfilGrupo(?string $perfil = null): string   { return perfilConfig($perfil)['grupo']; }
function perfilRotulo(?string $perfil = null): string  { return perfilConfig($perfil)['rotulo']; }
function perfilHome(?string $perfil = null): string    { return perfilConfig($perfil)['home']; }
function perfilMenu(?string $perfil = null): array     { return perfilConfig($perfil)['menu']; }

function perfilPaginaPermitida(string $pagina, ?string $perfil = null): bool {
    return in_array($pagina, perfilConfig($perfil)['paginas'], true);
}

/** "Este usuario pode fazer X?" Ex.: perfilPode('candidatar'), perfilPode('ia_perfil') */
function perfilPode(string $recurso, ?string $perfil = null): bool {
    if (empty($_SESSION['user_id']) && $perfil === null) return false;
    return in_array($recurso, perfilConfig($perfil)['recursos'], true);
}

/** Barra a pagina se o perfil nao puder abri-la (chamado pelo checkAuth). */
function perfilProtegerPagina(): void {
    $pagina = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if ($pagina === '' || substr($pagina, -4) !== '.php') return;

    $perfil = perfilAtual();
    if (perfilPaginaPermitida($pagina, $perfil)) return;

    $destino = perfilConfig($perfil)['fallback'];
    if ($destino === $pagina) $destino = perfilHome($perfil); // evita voltar para a mesma pagina
    header('Location: ' . $destino);
    exit;
}
