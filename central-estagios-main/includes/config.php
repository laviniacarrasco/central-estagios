<?php
session_start();

// Configurações
define('SITE_NAME', 'Central de Estágios - FSA');
define('SITE_URL', 'http://localhost:8000');

// Função para verificar login
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

// Função para carregar dados
function loadData($key) {
    $file = __DIR__ . "/../data/{$key}.json";
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

function saveData($key, $data) {
    $dir = __DIR__ . "/../data";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents("{$dir}/{$key}.json", json_encode($data, JSON_PRETTY_PRINT));
}

// Dados iniciais para vagas
function getInitialJobs() {
    return [
        [
            'id' => 1,
            'title' => 'Programa de Estágio 2026.2',
            'company' => 'Ford Motor Company',
            'location' => 'Santo André, SP',
            'hours' => '6h/dia',
            'salary' => 'R$ 1.500,00',
            'type' => 'Presencial',
            'status' => 'Ativa',
            'featured' => true,
            'applicants' => 12,
            'createdAt' => '01/03/2026',
            'applicationLink' => 'https://ford.com/careers',
            'description' => 'Atuar no suporte à engenharia de processos.',
            'requirements' => 'Inglês avançado, cursando Engenharia.',
            'benefits' => 'Plano de saúde, VR, VT.',
            'courses' => 'Engenharia Mecânica, Engenharia de Produção'
        ],
        [
            'id' => 2,
            'title' => 'Estágio em Desenvolvimento Front-End',
            'company' => 'Tech Makers Company',
            'location' => 'São Paulo, SP',
            'hours' => '6h/dia',
            'salary' => 'R$ 1.200,00',
            'type' => 'Híbrido',
            'status' => 'Ativa',
            'featured' => true,
            'applicants' => 8,
            'createdAt' => '05/03/2026',
            'applicationLink' => 'https://techmakers.com/vagas',
            'description' => 'Desenvolvimento de interfaces com React.',
            'requirements' => 'JavaScript, HTML5, CSS3.',
            'benefits' => 'Auxílio Home Office, Mentoria.',
            'courses' => 'Ciência da Computação, ADS'
        ]
    ];
}
?>