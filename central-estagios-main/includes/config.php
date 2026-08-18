<?php
session_start();

define('DATA_PATH', __DIR__ . '/../data/');

/**
 * Bloqueia acesso de quem nÃ£o estÃ¡ logado
 */
function checkAuth() {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Bloqueia acesso de quem nÃ£o Ã© admin (usar nas pÃ¡ginas posts.php e admin-jobs.php)
 */
function checkAdmin() {
    checkAuth();
    if (empty($_SESSION['is_admin'])) {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Carrega dados de um JSON.
 * - Dados globais: loadData('platform_jobs')
 * - Dados do usuÃ¡rio logado: loadData('userCurriculum', true)
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
 * Salva dados em um JSON (global ou por usuÃ¡rio)
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
 * Lista oficial de cursos da FSA â€” fonte Ãºnica.
 * Usada em jobs.php (filtro do aluno) e admin-jobs.php (cadastro de vaga).
 * Mudou um curso? SÃ³ precisa editar aqui.
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