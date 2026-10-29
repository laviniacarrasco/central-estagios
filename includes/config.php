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

// Onde os dados ficam guardados (arquivos JSON ou banco de dados)
require_once __DIR__ . '/armazenamento.php';

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
 * Controle de acesso por perfil (detalhes e listas em includes/perfis.php).
 *  - ALUNO:           nao entra nas paginas de admin nem nas de egresso.
 *  - ADMIN:           so entra nas paginas administrativas + mural de egressos.
 *  - ADMIN-EGRESSOS:  tudo que o admin ve + admin-egressos.php.
 *  - EGRESSO:         so entra na area de egressos (nada de vagas, paineis ou
 *                     assinaturas da FSA).
 * Chamada automaticamente dentro de checkAuth(), entao nao precisa
 * adicionar nada de novo nas paginas - so manter o checkAuth()/checkAdmin() que ja existe.
 */
function verificarPermissaoPagina() {
    // As regras de cada perfil (paginas, menu e recursos) ficam em includes/perfis.php
    perfilProtegerPagina();
}

/**
 * Carrega dados (de um JSON ou do banco, conforme DADOS_DRIVER).
 * - Dados globais: loadData('platform_jobs')
 * - Dados do usuário logado: loadData('userCurriculum', true)
 * Quem decide ONDE os dados ficam é o includes/armazenamento.php.
 */
function loadData($name, $perUser = false) {
    return dadosLer(dadosChave($name, $perUser));
}

/**
 * Salva dados (global ou por usuário).
 */
function saveData($name, $data, $perUser = false) {
    dadosGravar(dadosChave($name, $perUser), $data);
}

/** 'platform_jobs' ou 'users/<id do logado>/userCurriculum' */
function dadosChave($name, $perUser = false): string {
    if ($perUser) {
        return 'users/' . (int) ($_SESSION['user_id'] ?? 0) . '/' . $name;
    }
    return (string) $name;
}

/** Caminho do arquivo JSON (mantido para o código antigo; no modo banco ele não é usado). */
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

/**
 * JUSTIFICATIVA OBRIGATÓRIA
 * Toda ação de negar, recusar, rejeitar, suspender, bloquear, encerrar ou remover
 * exige um texto explicando o motivo (no mínimo JUSTIFICATIVA_MIN caracteres).
 * Devolve o texto limpo, ou null se estiver vazio ou curto demais.
 * Na tela, use no formulário: data-justificativa="motivo" (veja includes/header.php).
 */
const JUSTIFICATIVA_MIN = 10;
function justificativaPost(string $campo = 'motivo', int $max = 500): ?string {
    $t = trim(preg_replace('/[ \t]+/u', ' ', (string) ($_POST[$campo] ?? '')));
    $t = mb_substr($t, 0, $max);
    return mb_strlen($t) >= JUSTIFICATIVA_MIN ? $t : null;
}

// Arquitetura de perfis: alunos, professores, egressos e funcionarios
require_once __DIR__ . '/perfis.php';
