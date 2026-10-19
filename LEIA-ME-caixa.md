# Caixa de confirmacao padrao (igual ao "Excluir Vaga?")

Copie para a raiz da Central, mantendo as pastas:
  includes/header.php         (caixa padrao + engrenagem de alertas)
  admin-alertas.php, admin-egressos.php, egresso-publicar.php, minhas-vagas.php
  admin-jobs.php, curriculum.php  (alert() do navegador trocado pela caixa)

Como usar em telas futuras:
  <form method="POST" data-confirm="Texto" data-confirm-titulo="Titulo"
        data-confirm-botao="Excluir" data-confirm-tipo="perigo" data-confirm-icone="fa-trash-alt">
  tipo: "perigo" (vermelho, padrao) ou "aviso" (azul).
  Em JS: fsaConfirmar({titulo, texto, botao, tipo, icone}, function(){ ... });   fsaAviso('texto', 'titulo');
NUNCA use onsubmit="return confirm(...)" nem alert().
