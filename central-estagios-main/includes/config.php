<?php
// ================================================
// includes/config.php — ARQUIVO ÚNICO E UNIFICADO
// ================================================

// Evita session_start() duplo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================
// CONSTANTES DO SISTEMA
// ================================================
define('SITE_NAME', 'Central de Estágios - FSA');
define('SITE_URL', 'http://localhost:8000');
define('DATA_DIR', __DIR__ . '/../data/');

// ================================================
// AUTENTICAÇÃO
// Suporta ambas as chaves de sessão usadas no sistema
// ================================================
function checkAuth(): void {
    // Aceita 'user_id' (login antigo) ou 'user' (login novo)
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
}

// ================================================
// PERSISTÊNCIA — SALVAR DADOS
// ================================================
function saveData(string $filename, array $data): bool {
    $dir = DATA_DIR;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filePath = $dir . $filename . '.json';
    $result   = file_put_contents(
        $filePath,
        json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    return $result !== false;
}

// ================================================
// PERSISTÊNCIA — CARREGAR DADOS
// ================================================
function loadData(string $filename): array {
    $filePath = DATA_DIR . $filename . '.json';

    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    $data    = json_decode($content, true);

    return is_array($data) ? $data : [];
}

// ================================================
// VAGAS INICIAIS
// Usadas apenas na primeira execução (sem JSON)
// ================================================
function getInitialJobs(): array {
    return [
        [
            'id'              => 1,
            'title'           => 'Programa de Estágio 2026.2',
            'company'         => 'Ford Motor Company',
            'location'        => 'Santo André, SP',
            'hours'           => '6h/dia',
            'salary'          => 'R$ 1.500,00',
            'type'            => 'Presencial',
            'status'          => 'Ativa',
            'featured'        => true,
            'applicants'      => 12,
            'createdAt'       => '01/03/2026',
            'applicationLink' => 'https://ford.com/careers',
            'description'     => 'Atuar no suporte à engenharia de processos.',
            'requirements'    => 'Inglês avançado, cursando Engenharia.',
            'benefits'        => 'Plano de saúde, VR, VT.',
            'courses'         => 'Engenharia Mecânica, Engenharia de Produção'
        ],
        [
            'id'              => 2,
            'title'           => 'Estágio em Desenvolvimento Front-End',
            'company'         => 'Tech Makers Company',
            'location'        => 'São Paulo, SP',
            'hours'           => '6h/dia',
            'salary'          => 'R$ 1.200,00',
            'type'            => 'Híbrido',
            'status'          => 'Ativa',
            'featured'        => true,
            'applicants'      => 8,
            'createdAt'       => '05/03/2026',
            'applicationLink' => 'https://techmakers.com/vagas',
            'description'     => 'Desenvolvimento de interfaces com React.',
            'requirements'    => 'JavaScript, HTML5, CSS3.',
            'benefits'        => 'Auxílio Home Office, Mentoria.',
            'courses'         => 'Ciência da Computação, ADS'
        ],
    ];
}
