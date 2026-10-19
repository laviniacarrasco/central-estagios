# Fase 4: Mentoria e Empresas Parceiras

Copie tudo para a raiz da Central, mantendo as pastas, aceitando substituir.

NOVOS:  mentoria.php, admin-mentoria.php, empresas.php, admin-empresas.php,
        includes/mentoria.php, includes/empresas.php
AJUSTADOS (so pequenas linhas): includes/header.php (menu + bolinha),
        includes/config.php (permissoes), egresso-perfil.php (link para Mentoria)

Nao precisa criar nada no .env. Os dados novos sao criados sozinhos em data/:
mentoria_pedidos.json e empresas_parceiras.json. As logos ficam em uploads/empresas/.
O header.php deste pacote ja inclui caixa de confirmacao e engrenagem de alertas.

MENTORIA
  Egresso : menu Mentoria -> "Minha mentoria" -> Enviar para aprovacao
  Admin de egressos: menu Mentoria (Admin) -> aprova/recusa/suspende
  Aluno   : menu Mentoria -> Encontrar mentores -> Pedir mentoria
  Regras  : aluno ate 3 pedidos em andamento; mentor ate 5 pedidos aguardando;
            contato (e-mail/LinkedIn) so aparece depois do aceite.

EMPRESAS PARCEIRAS
  Admin : menu Empresas Parceiras (icone aperto de mao) -> cadastrar
  Aluno : menu Empresas Parceiras -> ve as ativas e as vagas abertas de cada uma
