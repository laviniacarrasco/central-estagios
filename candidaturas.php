<?php
/**
 * Pagina antiga. Mantida so para nao quebrar links/favoritos do navegador.
 * O conteudo agora esta em minhas-vagas.php (aba Candidaturas).
 */
$qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
header('Location: minhas-vagas.php?aba=candidaturas' . ($qs !== '' ? '&' . $qs : ''));
exit;
