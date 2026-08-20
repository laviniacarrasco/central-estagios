<?php
require_once __DIR__ . '/config.php';
checkAuth();

$userName  = $_SESSION['user_name'] ?? 'Visitante';
$userId    = $_SESSION['user_id'] ?? 0;
$isAdmin   = !empty($_SESSION['is_admin']);
$pageTitle = $pageTitle ?? 'Central de Estágios';

if (!function_exists('getInitials')) {
    function getInitials($name) {
        $parts = explode(' ', trim($name));
        $first = strtoupper(substr($parts[0], 0, 1));
        $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
        return $first . $last;
    }
}

// Foto de perfil por usuário (agora via config.php)
$userProfile = loadData('userProfile', true) ?? [];
$fotoSalva   = $userProfile['foto'] ?? null;
$temFoto     = $fotoSalva && file_exists(__DIR__ . '/../' . $fotoSalva);

/* =========================================================================
 * BLOQUEIO POR MA CONDUTA
 * -------------------------------------------------------------------------
 * Verificado aqui no header para valer em TODAS as paginas de uma vez so:
 * - esconde o icone "Solicitar Vaga" da sidebar
 * - exibe uma faixa de aviso fixa no topo, abaixo do header, explicando
 *   o motivo do bloqueio (definido pelo admin em admin-jobs.php)
 * ========================================================================= */
$isBloqueado = false;
$motivoBloqueioAluno = '';
$usuariosHeaderCheck = loadData('usuarios');
if (is_array($usuariosHeaderCheck)) {
    foreach ($usuariosHeaderCheck as $uCheck) {
        if (($uCheck['id'] ?? null) == $userId) {
            $isBloqueado = $uCheck['bloqueado'] ?? false;
            $motivoBloqueioAluno = $uCheck['motivoBloqueio'] ?? '';
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - FSA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.5s ease-out; }

        /* =========================================================
           SIDEBAR EM CSS PURO
           -----------------------------------------------------------
           Motivo: o Tailwind via CDN gera as classes utilitárias
           dinamicamente no navegador, escaneando todo o HTML da
           página. Em páginas com muito conteúdo (ex: dashboards com
           dezenas de cards/gráficos), esse processo demora mais e a
           sidebar chega a ser exibida momentaneamente sem o layout
           correto (ícones "soltos") até o Tailwind terminar de gerar
           o CSS. Escrevendo a sidebar em CSS puro aqui, o navegador
           aplica o estilo imediatamente, sem depender de nenhum
           script — garantindo tamanho e espaçamento idênticos em
           TODAS as páginas, independente do tamanho do conteúdo.

           OBS: espaçamento entre os ícones foi ajustado para caber
           tudo em 100vh sem gerar barra de rolagem, mesmo com o
           bloco admin completo.
        ========================================================= */
        .sidebar{
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 64px;
            background: linear-gradient(180deg, #4A9FCA 0%, #2B7FA6 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 0;
            z-index: 40;
            overflow: hidden; /* sem scroll */
            box-sizing: border-box;
        }
        .sidebar-logo{
            width: 48px;
            height: 48px;
            padding: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
            margin-bottom: 18px;
            box-sizing: border-box;
        }
        .sidebar-logo img{
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }
        .sidebar-nav{
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            width: 100%;
            flex-shrink: 0;
        }
        .sidebar-spacer{
            flex: 1 1 auto;
            width: 100%;
            min-height: 6px;
        }
        .sidebar-link{
            padding: 7px;
            color: #fff;
            border-radius: 8px;
            transition: background .15s;
            text-decoration: none;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-link:hover{ background: rgba(255,255,255,.2); }
        .sidebar-link i{ font-size: 17px; line-height: 1; }
        .sidebar-link.sidebar-link-sm i{ font-size: 14px; }
        .sidebar-link-muted{ color: rgba(255,255,255,.7); }
        .sidebar-divider{
            border: none;
            border-top: 1px solid rgba(255,255,255,.2);
            margin: 6px 0;
            width: 28px;
            flex-shrink: 0;
        }
        .sidebar-logout-wrap{
            flex-shrink: 0;
            margin-bottom: 4px;
        }

        /* =========================================================
           FAIXA DE AVISO: ALUNO BLOQUEADO POR MA CONDUTA
        ========================================================= */
        .aviso-bloqueio{
            position: fixed;
            top: 64px;
            left: 64px;
            right: 0;
            z-index: 35;
            background: #fef2f2;
            border-bottom: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 0.85rem;
            padding: 0.65rem 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .aviso-bloqueio i{ flex-shrink: 0; }
        .aviso-bloqueio strong{ font-weight: 700; }
    </style>
</head>
<body class="min-h-screen bg-gray-50">

    <!-- ================================ -->
    <!-- SIDEBAR                         -->
    <!-- ================================ -->
    <div class="sidebar">

        <!-- Logo FSA -->
        <div class="sidebar-logo">
            <img
                src="https://vectorseek.com/wp-content/uploads/2024/02/Fundacao-Santo-Andre-Logo-Vector.svg-.png"
                alt="FSA Logo"
                title="Fundação Santo André"
            >
        </div>

        <!-- Navegação -->
        <nav class="sidebar-nav">

            <!-- Início: visível para todos -->
            <a href="dashboard.php"  class="sidebar-link" title="Início">
                <i class="fas fa-home"></i>
            </a>

            <?php if (!$isAdmin): ?>
                <!-- ===== ÁREA DO ALUNO (oculta para admin) ===== -->
                <a href="profile.php"    class="sidebar-link" title="Perfil">
                    <i class="fas fa-user"></i>
                </a>
                <a href="curriculum.php" class="sidebar-link" title="Currículo">
                    <i class="fas fa-file-alt"></i>
                </a>
                <a href="documents.php"  class="sidebar-link" title="Documentos">
                    <i class="fas fa-folder"></i>
                </a>
            <?php endif; ?>

            <!-- Vagas: visível para todos (aluno usa pra buscar, admin também pode ver a listagem pública) -->
            <a href="jobs.php" class="sidebar-link" title="Vagas">
                <i class="fas fa-briefcase"></i>
            </a>

            <?php if (!$isAdmin): ?>
                <a href="signature.php" class="sidebar-link" title="Assinaturas">
                    <i class="fas fa-file-signature"></i>
                </a>
                <a href="career-tips.php" class="sidebar-link" title="Dicas de Carreira">
                    <i class="fas fa-lightbulb"></i>
                </a>

                <!-- Icone de Solicitar Vaga: escondido para alunos bloqueados por ma conduta -->
                <?php if (!$isBloqueado): ?>
                    <a href="request-job.php" class="sidebar-link" title="Solicitar Vaga">
                        <i class="fas fa-plus-circle"></i>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <hr class="sidebar-divider">

                <!-- ===== ÁREA ADMIN (ordem coesa: gestão -> conteúdo -> visão geral) ===== -->
                <a href="admin-jobs.php" class="sidebar-link sidebar-link-muted sidebar-link-sm" title="Admin Vagas">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="admin-signature.php" class="sidebar-link sidebar-link-muted sidebar-link-sm" title="Admin Assinaturas">
                    <i class="fas fa-file-signature"></i>
                </a>
                <a href="posts.php" class="sidebar-link sidebar-link-muted sidebar-link-sm" title="Admin Posts">
                    <i class="fas fa-newspaper"></i>
                </a>
                <a href="admin-dashboard.php" class="sidebar-link sidebar-link-muted sidebar-link-sm" title="Dashboard Admin">
                    <i class="fas fa-chart-line"></i>
                </a>
            <?php endif; ?>
        </nav>
        <!-- Espaçador flexível: é ESTE elemento que absorve o espaço
             sobrando entre a navegação e o botão de logout, mantendo
             o grupo de ícones sempre "colado" no topo com espaçamento
             fixo, independente da altura da página/tela. -->
        <div class="sidebar-spacer"></div>

        <!-- Logout -->
        <div class="sidebar-logout-wrap">
            <a href="index.php?logout=1" class="sidebar-link sidebar-link-muted" title="Sair">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>

    </div><!-- fim sidebar -->

    <!-- ================================ -->
    <!-- HEADER                          -->
    <!-- ================================ -->
    <header class="fixed top-0 right-0 left-16 h-16 bg-white border-b border-gray-200 flex items-center px-4 sm:px-8 z-30">

        <h1 class="text-lg sm:text-xl font-bold text-gray-900 truncate">
            <?php echo htmlspecialchars($pageTitle); ?>
        </h1>

        <!-- Avatar / Perfil -->
        <a href="profile.php"
           class="ml-auto flex items-center gap-3 px-4 py-2 border-2 border-gray-200 rounded-full hover:border-[#4A9FCA] hover:bg-gray-50 transition-all cursor-pointer flex-shrink-0"
           title="Ver Perfil">

            <span class="text-sm text-gray-700 font-medium hidden sm:inline">
                <?php echo htmlspecialchars($userName); ?>
            </span>

            <?php if ($temFoto): ?>
                <img
                    src="<?php echo htmlspecialchars($fotoSalva); ?>"
                    alt="Foto de perfil"
                    class="w-9 h-9 rounded-full object-cover border-2 border-[#4A9FCA]"
                    onerror="this.style.display='none'; document.getElementById('headerInitials').style.display='flex';">
                <div id="headerInitials"
                     class="w-9 h-9 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-full items-center justify-center text-white font-bold text-sm hidden">
                    <?php echo getInitials($userName); ?>
                </div>
            <?php else: ?>
                <div class="w-9 h-9 bg-gradient-to-br from-[#4A9FCA] to-[#3A8FB0] rounded-full flex items-center justify-center text-white font-bold text-sm">
                    <?php echo getInitials($userName); ?>
                </div>
            <?php endif; ?>

        </a>
    </header>

    <!-- ================================ -->
    <!-- AVISO DE BLOQUEIO POR MA CONDUTA -->
    <!-- Aparece em TODAS as paginas, ja que o header e incluido nelas -->
    <!-- ================================ -->
    <?php if ($isBloqueado): ?>
        <div class="aviso-bloqueio">
            <i class="fas fa-ban"></i>
            <span>
                Você foi bloqueado de enviar novas solicitações de vaga por má conduta.
                <?php if (!empty($motivoBloqueioAluno)): ?>
                    Motivo: <strong><?php echo htmlspecialchars($motivoBloqueioAluno); ?></strong>.
                <?php endif; ?>
                Em caso de dúvidas, entre em contato com a coordenação.
            </span>
        </div>
    <?php endif; ?>

    <script>
        <?php if ($isBloqueado): ?>
        // Empurra o <main> para baixo o suficiente para nao ficar
        // escondido atras da faixa de aviso de bloqueio.
        document.addEventListener('DOMContentLoaded', function() {
            const aviso = document.querySelector('.aviso-bloqueio');
            const main = document.querySelector('main');
            if (aviso && main) {
                main.style.paddingTop = (64 + aviso.offsetHeight) + 'px';
            }
        });
        <?php endif; ?>

        /* =========================================================
           FIX: caixinha "Ver todas as solicitações" fixa de verdade
           -----------------------------------------------------------
           Centralizado aqui no header (incluido em TODAS as paginas)
           para nao precisar repetir esse script em request-job.php,
           signature.php e admin-signature.php.

           Motivo do bug: position:fixed passa a se comportar como
           "relativo ao ancestral" quando algum elemento pai tem
           transform/filter/perspective aplicado (comum em containers
           de layout, cards com transition, etc.). Isso faz a caixinha
           "flutuante" rolar junto com a pagina em vez de ficar fixa
           na tela.

           Solucao: mover a caixinha para ser filha direta do <body>,
           garantindo que o fixed sempre se refira a janela do
           navegador, independente da estrutura do restante da pagina.
        ========================================================= */
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.ver-todas-float').forEach(function (el) {
                if (el.parentElement !== document.body) {
                    document.body.appendChild(el);
                }
            });
        });
    </script>
