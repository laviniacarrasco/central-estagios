<?php
/**
 * =============================================================================
 * ROTEADOR DO SERVIDOR PHP  —  protege os arquivos que não podem ser baixados
 * -----------------------------------------------------------------------------
 * O servidor embutido do PHP ("php -S") entrega QUALQUER arquivo da pasta.
 * Sem este roteador, quem soubesse o endereço baixaria data/usuarios.json,
 * as conversas da mentoria, o .env etc.
 *
 * Uso (o Dockerfile já faz isso no Render):
 *   php -S localhost:8000 router.php        no seu computador
 *   php -S 0.0.0.0:80 router.php            no servidor
 *
 * Bloqueia (responde 403):
 *   - as pastas data/, includes/, docs/, ferramentas/, vendor/, logs/
 *     (exceto as FOTOS do currículo em data/users/<id>/, que a tela mostra)
 *   - arquivos e pastas que começam com ponto (.env, .git, .gitignore...)
 *   - extensões de dados e configuração: .json .md .lock .sql .yml .yaml .log
 *     .ini .env .pem .bak .tmp .sh .dist .example
 *   - arquivos de configuração e de teste: Dockerfile, composer.*, render.yaml,
 *     router.php, teste-*.php, teste_*.php
 * Em hospedagem com Apache, o .htaccess faz o mesmo papel.
 * =============================================================================
 */

$caminho = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$caminho = '/' . ltrim(str_replace('\\', '/', $caminho), '/');

function roteadorNegar(): bool {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acesso negado.';
    return true;
}

// Caminhos estranhos
if (strpos($caminho, '..') !== false || strpos($caminho, "\0") !== false) return roteadorNegar();

$baixo = strtolower($caminho);

// Qualquer parte que começa com ponto (.env, .git/, .gitignore, .htaccess)
if (preg_match('#/\.#', $baixo)) return roteadorNegar();

// Fotos do currículo: data/users/<id>/<arquivo>.(jpg|jpeg|png|webp|gif) — liberadas
$fotoCurriculo = (bool) preg_match('#^/data/users/\d+/[a-z0-9_\-.]+\.(jpe?g|png|webp|gif)$#', $baixo);

// Pastas internas
if (!$fotoCurriculo && preg_match('#^/(data|includes|docs|ferramentas|vendor|logs)(/|$)#', $baixo)) return roteadorNegar();

// Extensões que nunca devem ser baixadas
if (preg_match('#\.(json|md|lock|sql|ya?ml|log|ini|env|pem|bak|tmp\d*|sh|dist|example)$#', $baixo)) return roteadorNegar();

// Arquivos de configuração e ferramentas de teste
if (preg_match('#^/(dockerfile|router\.php|composer\.(json|lock|phar)|teste[-_][^/]*\.php|gupyfonte\.php)$#', $baixo)) return roteadorNegar();

// O resto segue normal: páginas .php e arquivos públicos (uploads/, imagens...)
return false;
