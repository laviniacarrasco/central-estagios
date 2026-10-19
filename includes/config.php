<?php
session_start();
date_default_timezone_set('America/Sao_Paulo'); // validade das vagas e datas dos e-mails em horario de Brasilia

define('DATA_PATH', __DIR__ . '/../data/');

/* =========================================================================
 * CARREGA VARIÁVEIS DE AMBIENTE DO ARQUIVO .env (SOMENTE PARA TESTE LOCAL)
 * -------------------------------------------------------------------------
 * Em produção (Render), as variáveis já vêm configuradas direto no painel
 * de "Environment", então esse bloco nem é necessário lá — mas não tem
 * problema deixar, ele só ativa se o arquivo .env existir.
 * ========================================================================= */
require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

/**
 * Bloqueia acesso de quem não está logado
 * e, em seguida, aplica a regra de permissão por perfil (admin x aluno).
 */
function checkAuth() {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    verificarPermissaoPagina();
}

/**
 * Bloqueia acesso de quem não é admin (usar nas páginas exclusivas de admin)
 */
function checkAdmin() {
    checkAuth();
    if (empty($_SESSION['is_admin'])) {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Controle de acesso por perfil.
 *  - ALUNO:           nao entra nas paginas de admin nem nas de egresso.
 *  - ADMIN:           so entra nas paginas administrativas + mural de egressos.
 *  - ADMIN-EGRESSOS:  tudo que o admin ve + admin-egressos.php.
 *  - EGRESSO:         so entra na area de egressos (nada de vagas, paineis ou
 *                     assinaturas da FSA).
 * Chamada automaticamente dentro de checkAuth(), entao nao precisa
 * adicionar nada de novo nas paginas - so manter o checkAuth()/checkAdmin() que ja existe.
 */
function verificarPermissaoPagina() {
    $paginaAtual     = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $isAdmin         = !empty($_SESSION['is_admin']);
    $isEgresso       = !empty($_SESSION['is_egresso']);
    $isAdminEgressos = $isAdmin && !empty($_SESSION['is_admin_egressos']);

    // ---- EGRESSO: lista fechada de paginas ----
    if ($isEgresso) {
        $paginasEgresso = [
            'egressos.php',          // mural de vagas de egressos
            'egresso-perfil.php',    // perfil do egresso
            'egresso-publicar.php',  // divulgar vagas
            'mentoria.php',          // mentoria (egresso e mentor)
            'forgot-password.php',
            'index.php',
        ];
        if (!in_array($paginaAtual, $paginasEgresso, true)) {
            header('Location: egressos.php');
            exit;
        }
        return;
    }

    // Paginas que o ADMIN pode acessar
    $paginasPermitidasAdmin = [
        'admin-dashboard.php', // ajuste aqui se o nome real for admin_dashboard.php
        'admin-jobs.php',
        'admin-signature.php',
        'dashboard.php',
        'forgot-password.php',
        'index.php',
        'jobs.php',
        'posts.php',
        'egressos.php',        // mural de egressos: todo admin ve
        'admin-alertas.php',   // fila de e-mails e resumo semanal
        'admin-empresas.php',  // empresas parceiras
    ];
    if ($isAdminEgressos) {
        $paginasPermitidasAdmin[] = 'admin-egressos.php';
        $paginasPermitidasAdmin[] = 'admin-relatorios.php';
        $paginasPermitidasAdmin[] = 'admin-mentoria.php';
    }

    // Paginas exclusivas de admin (aluno nao pode entrar)
    $paginasExclusivasAdmin = [
        'admin-dashboard.php', // ajuste aqui se o nome real for admin_dashboard.php
        'admin-jobs.php',
        'admin-signature.php',
        'posts.php',
        'admin-egressos.php',
        'admin-alertas.php',
        'admin-relatorios.php',
        'admin-mentoria.php',
        'admin-empresas.php',
    ];

    // Paginas so de egresso (aluno nao entra; admin ja e barrado pela lista acima)
    $paginasSoEgresso = ['egresso-perfil.php', 'egresso-publicar.php'];

    if ($isAdmin) {
        if (!in_array($paginaAtual, $paginasPermitidasAdmin, true)) {
            header('Location: admin-dashboard.php'); // ajuste aqui se necessario
            exit;
        }
    } else {
        if (in_array($paginaAtual, $paginasExclusivasAdmin, true) || in_array($paginaAtual, $paginasSoEgresso, true)) {
            header('Location: dashboard.php');
            exit;
        }
    }
}

/**
 * Carrega dados de um JSON.
 * - Dados globais: loadData('platform_jobs')
 * - Dados do usuário logado: loadData('userCurriculum', true)
 */
function loadData($name, $perUser = false) {
    $path = getDataPath($name, $perUser);

    if (!file_exists($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Salva dados em um JSON (global ou por usuário)
 */
function saveData($name, $data, $perUser = false) {
    $path = getDataPath($name, $perUser);
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getDataPath($name, $perUser = false) {
    if ($perUser) {
        $userId = $_SESSION['user_id'] ?? 0;
        return DATA_PATH . "users/{$userId}/{$name}.json";
    }
    return DATA_PATH . "{$name}.json";
}

/**
 * Lista oficial de cursos da FSA — fonte única.
 * Usada em jobs.php (filtro do aluno) e admin-jobs.php (cadastro de vaga).
 * Mudou um curso? Só precisa editar aqui.
 */
function getFsaCourses() {
    return [
        "Ciência de Dados e Inteligência Artificial",
        "Análise e Desenvolvimento de Sistemas",
        "Engenharia Mecânica",
        "Engenharia Elétrica",
        "Engenharia de Produção",
        "Engenharia Civil",
        "Administração",
        "Psicologia",
        "Arquitetura e Urbanismo",
        "Ciências Biológicas",
        "Direito",
        "Pedagogia",
        "Ciência da Computação",
        "Sistemas de Informação"
    ];
}
