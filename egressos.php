<?php
/**
 * O mural de vagas de egressos agora faz parte da aba Vagas (jobs.php).
 * Esta página continua existindo só para os links antigos (e-mails, favoritos):
 * ela abre a aba Vagas já filtrada nas vagas de egressos.
 */
require_once 'includes/config.php';
checkAuth();

$destino = ['origem' => 'egresso'];
if (!empty($_GET['search'])) $destino['search'] = (string) $_GET['search'];
if (!empty($_GET['curso']))  $destino['curso']  = (string) $_GET['curso'];

header('Location: jobs.php?' . http_build_query($destino));
exit;
