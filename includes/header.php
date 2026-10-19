<?php
require_once __DIR__ . '/config.php';
checkAuth();

$userName  = $_SESSION['user_name'] ?? 'Visitante';
$userId    = $_SESSION['user_id'] ?? 0;
$isAdmin   = !empty($_SESSION['is_admin']);
$isEgresso = !empty($_SESSION['is_egresso']);
$isAdminEgressos = $isAdmin && !empty($_SESSION['is_admin_egressos']);

// Envio de e-mails em fila/resumo semanal: roda depois que a pagina ja foi entregue
require_once __DIR__ . '/alertas.php';
alertasAgendarTick();
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

            <?php if ($isEgresso): ?>
                <!-- ===== ÁREA DO EGRESSO (só enxerga o que é de egressos) ===== -->
                <a href="egressos.php" class="sidebar-link" title="Vagas de Egressos">
                    <i class="fas fa-briefcase"></i>
                </a>
                <a href="egresso-publicar.php" class="sidebar-link" title="Divulgar Vaga">
                    <i class="fas fa-plus-circle"></i>
                </a>
                <a href="egresso-perfil.php" class="sidebar-link" title="Meu Perfil de Egresso">
                    <i class="fas fa-user-graduate"></i>
                </a>
            <?php else: ?>

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
                <a href="minhas-vagas.php" class="sidebar-link" title="Minhas Vagas">
                    <i class="fas fa-clipboard-check"></i>
                </a>
                <a href="vagas-para-voce.php" class="sidebar-link" title="Vagas para Você">
                    <i class="fas fa-star"></i>
                </a>
            <?php endif; ?>

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
                <a href="admin-alertas.php" class="sidebar-link sidebar-link-muted sidebar-link-sm" title="Alertas e E-mails">
                    <i class="fas fa-envelope"></i>
                </a>
                <?php if ($isAdminEgressos): ?>
                    <a href="admin-egressos.php" class="sidebar-link sidebar-link-muted sidebar-link-sm" title="Painel de Egressos">
                        <i class="fas fa-users"></i>
                    </a>
                    <a href="admin-relatorios.php" class="sidebar-link sidebar-link-muted sidebar-link-sm" title="Relatórios de Egressos">
                        <i class="fas fa-file-export"></i>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <?php endif; /* fim: nao egresso */ ?>
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

        <?php $mostrarEngrenagem = !$isAdmin && !$isEgresso; ?>
        <?php if ($mostrarEngrenagem): ?>
        <!-- Engrenagem: abre Meus Alertas dentro do Perfil -->
        <a href="profile.php?config=alertas"
           class="ml-auto mr-3 w-10 h-10 flex items-center justify-center rounded-full border-2 border-gray-200 text-gray-500 hover:border-[#4A9FCA] hover:text-[#4A9FCA] hover:bg-gray-50 transition-all flex-shrink-0"
           title="Configurar alertas por e-mail">
            <i class="fas fa-cog"></i>
        </a>
        <?php endif; ?>

        <!-- Avatar / Perfil -->
        <a href="<?php echo $isEgresso ? 'egresso-perfil.php' : 'profile.php'; ?>"
           class="<?php echo $mostrarEngrenagem ? '' : 'ml-auto'; ?> flex items-center gap-3 px-4 py-2 border-2 border-gray-200 rounded-full hover:border-[#4A9FCA] hover:bg-gray-50 transition-all cursor-pointer flex-shrink-0"
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

    <!-- ================================================================ -->
    <!-- CAIXA PADRAO DE CONFIRMACAO / AVISO (mesmo visual do "Excluir Vaga?") -->
    <!-- Substitui o confirm()/alert() do navegador em todas as paginas.        -->
    <!-- Uso em formularios:  <form data-confirm="Texto" data-confirm-titulo="Titulo"
         data-confirm-botao="Excluir" data-confirm-tipo="perigo|aviso" data-confirm-icone="fa-trash-alt">
         Uso em JS:  fsaConfirmar({titulo, texto, botao, tipo, icone}, function(){...});  fsaAviso('texto', 'titulo'); -->
    <!-- ================================================================ -->
    <div id="fsaModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="fsaModalTitulo">
        <div id="fsaModalFundo" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">
            <div id="fsaModalIconeBox" class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5">
                <i id="fsaModalIcone" class="fas fa-trash-alt text-3xl"></i>
            </div>
            <h3 id="fsaModalTitulo" class="text-xl font-bold text-gray-900 text-center mb-2"></h3>
            <p id="fsaModalTexto" class="text-gray-500 text-sm text-center mb-8 leading-relaxed"></p>
            <div class="flex gap-3">
                <button type="button" id="fsaModalCancelar"
                        class="flex-1 py-3 px-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                    <i class="fas fa-times mr-1"></i> Cancelar
                </button>
                <button type="button" id="fsaModalOk"
                        class="flex-1 py-3 px-4 rounded-2xl text-white font-semibold transition-all text-sm shadow-lg"></button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var modal = document.getElementById('fsaModal');
        if (!modal) return;
        var $ = function (id) { return document.getElementById(id); };
        var acaoOk = null;
        var focoAnterior = null;

        function fechar() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            acaoOk = null;
            if (focoAnterior && focoAnterior.focus) { try { focoAnterior.focus(); } catch (e) {} }
        }

        function abrir(o) {
            var perigo = o.tipo !== 'aviso';
            var icone  = o.icone || (perigo ? 'fa-trash-alt' : 'fa-question-circle');
            $('fsaModalTitulo').textContent = o.titulo || 'Confirmar ação?';
            $('fsaModalTexto').textContent  = o.texto || '';
            $('fsaModalIcone').className    = 'fas ' + icone + ' text-3xl';
            $('fsaModalIcone').style.color  = perigo ? '#ef4444' : '#4A9FCA';
            $('fsaModalIconeBox').style.background = perigo ? '#fee2e2' : '#e8f3fa';

            var ok = $('fsaModalOk');
            ok.style.background = perigo ? '#ef4444' : '#4A9FCA';
            ok.style.boxShadow  = perigo ? '0 10px 15px -3px rgba(254,226,226,1)' : '0 10px 15px -3px rgba(214,235,247,1)';
            ok.textContent = '';
            var ic = document.createElement('i');
            ic.className = 'fas ' + (o.soAviso ? 'fa-check' : (o.icone || (perigo ? 'fa-trash-alt' : 'fa-check'))) + ' mr-1';
            ok.appendChild(ic);
            ok.appendChild(document.createTextNode(' ' + (o.botao || (o.soAviso ? 'Entendi' : 'Confirmar'))));

            $('fsaModalCancelar').style.display = o.soAviso ? 'none' : '';
            acaoOk = o.onOk || null;
            focoAnterior = document.activeElement;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            (o.soAviso ? ok : $('fsaModalCancelar')).focus();
        }

        $('fsaModalCancelar').addEventListener('click', fechar);
        $('fsaModalFundo').addEventListener('click', fechar);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) fechar();
        });
        $('fsaModalOk').addEventListener('click', function () {
            var f = acaoOk;
            fechar();
            if (f) f();
        });

        // Formularios com data-confirm abrem a caixa antes de enviar
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.getAttribute) return;
            var texto = form.getAttribute('data-confirm');
            if (!texto || form.__fsaConfirmado) return;
            e.preventDefault();
            var quemEnviou = e.submitter || null;
            abrir({
                titulo: form.getAttribute('data-confirm-titulo'),
                texto:  texto,
                botao:  form.getAttribute('data-confirm-botao'),
                tipo:   form.getAttribute('data-confirm-tipo'),
                icone:  form.getAttribute('data-confirm-icone'),
                onOk: function () {
                    form.__fsaConfirmado = true;
                    if (form.requestSubmit) { form.requestSubmit(quemEnviou || undefined); }
                    else { form.submit(); }
                }
            });
        }, true);

        window.fsaConfirmar = function (opcoes, aoConfirmar) {
            opcoes = opcoes || {};
            opcoes.onOk = aoConfirmar;
            abrir(opcoes);
        };
        window.fsaAviso = function (texto, titulo) {
            abrir({ titulo: titulo || 'Aviso', texto: texto, tipo: 'aviso', icone: 'fa-exclamation-circle', soAviso: true });
        };
    })();
    </script>

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
