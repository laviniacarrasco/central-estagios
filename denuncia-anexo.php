<?php
/** Entrega um anexo do Canal de Denúncias (só para quem abriu e para o Admin de Denúncias). */
require_once 'includes/config.php';
require_once 'includes/denuncias.php';
checkAuth();

$d = denunciaPorId((int) ($_GET['id'] ?? 0));
$arquivo = basename((string) ($_GET['a'] ?? ''));
if (!$d || !denunciaPodeVer($d) || $arquivo === '') { http_response_code(404); exit('Arquivo não encontrado.'); }
$info = null;
foreach ((array) ($d['mensagens'] ?? []) as $m) foreach ((array) ($m['anexos'] ?? []) as $a) if (($a['arquivo'] ?? '') === $arquivo) $info = $a;
$caminho = denunciaPastaAnexos((int) $d['id']) . $arquivo;
if (!$info || !is_file($caminho)) { http_response_code(404); exit('Arquivo não encontrado.'); }

header('Content-Type: ' . $info['mime']);
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', (string) $info['nome']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($caminho);
