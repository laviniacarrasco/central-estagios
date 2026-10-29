<?php
/**
 * Página antiga. O "Funil e Indicadores" agora é um painel editável:
 * Painéis > pasta Vagas e Funil > Funil de candidaturas (admin-paineis.php).
 * Este arquivo continua existindo só para não quebrar links antigos.
 */
require_once 'includes/config.php';
checkAuth();
header('Location: admin-paineis.php?painel=funil');
exit;
