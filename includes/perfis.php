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
 *   is_admin + tipo "admin_denuncias" -> admin_denuncias  (Funcionarios: denúncias e chats da mentoria)
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
        'funcionarios' => ['rotulo' => 'Funcionários', 'perfis' => ['admin', 'admin_egressos', 'admin_denuncias']],
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
    $mDen      = perfilItemMenu('denuncias.php', 'Canal de Denúncias', 'fa-shield-alt', 'denuncias');   // bolinha = resposta nova
    $pagDen    = ['denuncias.php', 'denuncia-anexo.php'];

    // ---------- Paginas da area do aluno ----------
    $paginasAlunoBase = [
        'dashboard.php', 'profile.php', 'curriculum.php', 'documents.php',
        'jobs.php', 'minhas-vagas.php', 'vagas-para-voce.php', 'career-tips.php',
        'candidatar.php', 'favoritar.php', 'candidaturas.php', 'favoritos.php',
        'configuracoes.php',   // caixa de Configurações (engrenagem do topo)
        'curriculo-pdf.php',   // currículo em PDF com texto (legível por ATS)
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
                'ia-curriculo.php',    // IA do currículo (Gemini)
            ], $pagDen),
            'menu' => [
                $mInicio, $mPerfil, $mCv, $mDocs, $mVagas, $mMinhas, $mParaVoce,
                perfilItemMenu('signature.php', 'Assinaturas', 'fa-file-signature', 'aluno_assinaturas'),
                $mDicas,
                perfilItemMenu('mentoria.php', 'Mentoria', 'fa-hands-helping', 'mentoria'),   // bolinha = mensagens novas
                perfilItemMenu('request-job.php', 'Solicitar Vaga', 'fa-plus-circle', '', 'principal', 'nao_bloqueado'),
                $mDen,
            ],
            'recursos' => ['area_aluno', 'candidatar', 'favoritar', 'alertas', 'ia_perfil', 'ia_curriculo', 'ia_cv', 'vagas_egresso'],
        ],

        'aluno_restrito' => [
            'grupo'    => 'alunos',
            'rotulo'   => 'Aluno (acesso restrito)',
            'home'     => 'dashboard.php',
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, $paginasAlunoBase, $pagDen, [
                'ia-curriculo.php',    // v33: só a IA do currículo (acao=avaliar)
            ]),
            'menu'     => [$mInicio, $mPerfil, $mCv, $mDocs, $mVagas, $mMinhas, $mParaVoce, $mDicas, $mDen],
            // sem: alertas, IA do perfil, IA de vagas, vagas de egressos, assinaturas, mentoria, solicitar vaga
            // v33: 'ia_cv' = só a avaliação do currículo com IA
            'recursos' => ['area_aluno', 'candidatar', 'favoritar', 'ia_cv'],
        ],

        /* ========================== PROFESSORES ========================= */
        // Publica vagas (aprovação da Coordenação de Estágios) e é MENTOR dos alunos.
        'professor' => [
            'grupo'    => 'professores',
            'rotulo'   => 'Professor',
            'home'     => 'dashboard.php',      // Início próprio (includes/inicio-gestao.php)
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, $pagDen, [
                'dashboard.php', 'jobs.php', 'egressos.php', 'career-tips.php',
                'request-job.php', 'mentoria.php', 'mentoria-chat.php',
            ]),
            'menu'     => [
                $mInicio, $mVagas,
                perfilItemMenu('request-job.php', 'Publicar Vaga', 'fa-plus-circle', '', 'principal', 'nao_bloqueado'),
                perfilItemMenu('mentoria.php', 'Mentoria', 'fa-hands-helping', 'mentoria'),
                $mDicas, $mDen,
            ],
            'recursos' => ['vagas_egresso', 'publicar_vaga', 'ser_mentor'],
        ],

        /* =========================== EGRESSOS =========================== */
        // Só divulga vagas e vê AS SUAS vagas (em Divulgar Vaga). Não vê a aba Vagas.
        'egresso' => [
            'grupo'    => 'egressos',
            'rotulo'   => 'Egresso',
            'home'     => 'dashboard.php',
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, $pagDen, ['dashboard.php', 'egresso-publicar.php', 'egresso-perfil.php']),
            'menu'     => [
                $mInicio,
                perfilItemMenu('egresso-publicar.php', 'Divulgar e minhas vagas', 'fa-plus-circle'),
                perfilItemMenu('egresso-perfil.php', 'Meu Perfil de Egresso', 'fa-user-graduate'),
                $mDen,
            ],
            'recursos' => ['divulgar_vaga'],
        ],

        /* ========================= FUNCIONARIOS ========================= */
        // As coordenações não se misturam: cada uma vê só o que é dela.
        'admin' => [
            'grupo'    => 'funcionarios',
            'rotulo'   => 'Coordenação de Estágios',
            'home'     => 'dashboard.php',
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, $paginasCoordenacao, $pagDen, ['admin-mentoria.php']),
            'menu'     => array_merge($menuCoordenacao, [
                perfilItemMenu('admin-mentoria.php', 'Mentoria (Admin)', 'fa-hands-helping', 'mentoria', 'gestao'),   // aprovar mentores
                $mDen,
            ]),
            'recursos' => ['gestao', 'gestao_mentoria', 'vagas_egresso'],
        ],

        'admin_egressos' => [
            'grupo'    => 'funcionarios',
            'rotulo'   => 'Coordenação de Egressos',
            'home'     => 'dashboard.php',
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, $pagDen, [
                'dashboard.php', 'jobs.php', 'egressos.php',
                'admin-egressos.php', 'admin-relatorios.php',
                'admin-paineis.php', 'bi-dados.php', 'admin-dashboard.php',   // Painéis e relatórios
            ]),
            'menu'     => [
                $mInicio, $mVagas,
                perfilItemMenu('admin-egressos.php', 'Gestão de Egressos', 'fa-users', 'admin_egressos', 'gestao'),
                perfilItemMenu('admin-paineis.php', 'Painéis', 'fa-chart-pie', '', 'gestao'),
                perfilItemMenu('admin-relatorios.php', 'Relatórios de Egressos', 'fa-chart-bar', '', 'gestao'),
                $mDen,
            ],
            'recursos' => ['gestao_egressos', 'vagas_egresso', 'paineis'],
        ],

        // Recebe o Canal de Denúncias e controla os chats e as denúncias da mentoria
        'admin_denuncias' => [
            'grupo'    => 'funcionarios',
            'rotulo'   => 'Admin de Denúncias',
            'home'     => 'dashboard.php',
            'fallback' => 'dashboard.php',
            'paginas'  => array_merge($comuns, [
                'dashboard.php', 'admin-denuncias.php', 'denuncia-anexo.php',
                'admin-mentoria.php', 'admin-mentoria-conversa.php',
            ]),
            'menu'     => [
                $mInicio,
                perfilItemMenu('admin-denuncias.php', 'Denúncias', 'fa-shield-alt', 'denuncias'),
                perfilItemMenu('admin-mentoria.php?tab=denuncias', 'Chats da Mentoria', 'fa-comments', 'mentoria'),
            ],
            'recursos' => ['gestao_denuncias'],
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
    if ($admin && $tipo === 'admin_denuncias') return 'admin_denuncias';
    if ($admin)                               return 'admin';
    if ($tipo === 'egresso')                  return 'egresso';
    if ($tipo === 'professor')                return 'professor';
    if ($tipo === 'aluno_restrito')           return 'aluno_restrito';
    return 'aluno';
}

/** Mantem as flags antigas da sessao coerentes com o perfil. */
function perfilSincronizarSessao(): void {
    $p = $_SESSION['perfil'] ?? 'aluno';
    $_SESSION['is_admin']          = in_array($p, ['admin', 'admin_egressos', 'admin_denuncias'], true);
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
