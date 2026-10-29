<?php
/**
 * O cadastro em massa de alunos por planilha (CSV/Excel) foi DESATIVADO.
 * Agora ele é feito direto do banco de dados / sistema acadêmico (TOTVS),
 * na tela admin-totvs.php. Esta página continua existindo só para links antigos.
 */
header('Location: admin-totvs.php');
exit;
