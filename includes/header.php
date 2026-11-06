<?php
require_once __DIR__ . '/config.php';
checkAuth();

$userName  = $_SESSION['user_name'] ?? 'Visitante';
$userId    = $_SESSION['user_id'] ?? 0;
$isAdmin   = !empty($_SESSION['is_admin']);
$isEgresso = !empty($_SESSION['is_egresso']);
$perfilAtual = perfilAtual();                   // aluno | aluno_restrito | professor | egresso | admin | admin_egressos
$isRestrito  = ($perfilAtual === 'aluno_restrito');
$isAdminEgressos = $isAdmin && !empty($_SESSION['is_admin_egressos']);

// Envio de e-mails em fila/resumo semanal: roda depois que a pagina ja foi entregue
require_once __DIR__ . '/alertas.php';
alertasAgendarTick();

// Bolinhas vermelhas do menu (mentoria, vagas, assinaturas, egressos).
// Protegido: se algo falhar aqui, o resto do site continua funcionando.
try {
    require_once __DIR__ . '/avisos.php';
} catch (Throwable $avisosErro) {
    error_log('avisos: ' . $avisosErro->getMessage());
}
if (!function_exists('avisosBadge')) {
    function avisosBadge(string $chave): string { return ''; }
}
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
$paginaAtual = basename($_SERVER['SCRIPT_NAME'] ?? '');
$temConfig   = perfilPode('alertas') || perfilPode('area_aluno');
$linkAvatar  = perfilPaginaPermitida('profile.php') ? 'profile.php' : (perfilPaginaPermitida('egresso-perfil.php') ? 'egresso-perfil.php' : perfilHome());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - FSA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['Inter', 'system-ui', 'Segoe UI', 'Roboto', 'sans-serif'] },
            colors: { fsa: { 50: '#eef7fc', 100: '#d6ebf7', 500: '#4A9FCA', 600: '#3A8FB0', 700: '#2B7FA6', 900: '#173f55' } }
        } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* =========================================================
           DESIGN DA CENTRAL (vale para todas as páginas)
           -----------------------------------------------------------
           Cores: azul FSA #4A9FCA (escuro #2B7FA6). Fonte: Inter.
           Classes prontas para telas novas:
             .fsa-card          cartão branco com borda suave
             .fsa-btn + .fsa-btn-primario | .fsa-btn-secundario | .fsa-btn-perigo
             .fsa-input         campo de texto / select
             .fsa-chip          etiqueta pequena
             .fsa-switch        checkbox em forma de chave
           As regras "main ..." deixam as telas antigas no mesmo padrão
           sem precisar mexer em cada uma.
        ========================================================= */
        :root { --fsa: #4A9FCA; --fsa-escuro: #2B7FA6; --fsa-claro: #e8f3fa; --borda: #E6ECF2; --texto: #0f172a; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', system-ui, 'Segoe UI', Roboto, sans-serif; -webkit-font-smoothing: antialiased; background: #F4F7FA; color: var(--texto); }
        ::selection { background: #cfe6f4; }
        * { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @keyframes fsaPop { from { opacity: 0; transform: scale(.96) translateY(6px); } to { opacity: 1; transform: none; } }
        .animate-fade-in { animation: fadeIn .45s ease-out; }
        .fsa-pop { animation: fsaPop .18s ease-out; }

        /* Cartões */
        .fsa-card,
        main .bg-white.rounded-2xl, main .bg-white.rounded-3xl, main .bg-white.rounded-\[24px\] {
            border: 1px solid var(--borda);
            box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
        }
        .fsa-card { display: block; background: #fff; border-radius: 1.25rem; }
        main a.bg-white.rounded-2xl:hover, main a.fsa-card:hover { border-color: #b9dbee; box-shadow: 0 10px 24px -14px rgba(43, 127, 166, .45); }
        main h2.text-3xl { letter-spacing: -.02em; }

        /* Botões */
        main button, main a, .fsa-btn { transition: background-color .15s, border-color .15s, color .15s, box-shadow .15s, transform .15s; }
        .fsa-btn { display: inline-flex; align-items: center; justify-content: center; height: 2.75rem; padding: 0 1.25rem; border-radius: .9rem; font-size: .875rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .fsa-btn-primario { background: var(--fsa); color: #fff; box-shadow: 0 6px 14px -8px rgba(43, 127, 166, .8); }
        .fsa-btn-primario:hover { background: var(--fsa-escuro); }
        .fsa-btn-secundario { background: #fff; color: #334155; border: 1px solid var(--borda); }
        .fsa-btn-secundario:hover { background: #f8fafc; border-color: #cbd5e1; }
        .fsa-btn-perigo { background: #fff; color: #dc2626; border: 1px solid #fecaca; }
        .fsa-btn-perigo:hover { background: #fef2f2; }
        .fsa-btn:disabled { cursor: not-allowed; }

        /* Campos */
        .fsa-input { width: 100%; height: 2.75rem; padding: 0 .9rem; border: 1px solid var(--borda); border-radius: .8rem; background: #fff; font-size: .875rem; color: var(--texto); }
        main input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]):focus,
        main select:focus, main textarea:focus, .fsa-input:focus {
            outline: none; border-color: var(--fsa); box-shadow: 0 0 0 3px rgba(74, 159, 202, .18);
        }
        .fsa-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .7rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--fsa-claro); color: var(--fsa-escuro); }

        /* Chave liga/desliga */
        .fsa-switch { appearance: none; -webkit-appearance: none; width: 2.6rem; height: 1.5rem; border-radius: 999px; background: #cbd5e1; position: relative; cursor: pointer; flex-shrink: 0; transition: background .15s; }
        .fsa-switch::after { content: ''; position: absolute; top: 3px; left: 3px; width: 1.125rem; height: 1.125rem; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .15s; }
        .fsa-switch:checked { background: var(--fsa); }
        .fsa-switch:checked::after { transform: translateX(1.1rem); }

        /* Tabelas */
        main table thead th { font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; color: #64748b; font-weight: 700; }
        main table tbody tr { transition: background-color .12s; }
        main table tbody tr:hover { background: #f8fafc; }

        /* Consistência das telas antigas */
        main h2.text-3xl { font-weight: 800; }
        main .shadow-sm { box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        main select:not(.fsa-input) { border-color: var(--borda); }
        main .rounded-xl.bg-gray-50.border { background: #fff; }
        main details > summary { cursor: pointer; }

        /* Blocos que recolhem (data-recolher) */
        [data-recolher] .fa-chevron-down{ transition: transform .2s; }
        [data-recolher][aria-expanded="true"] .fa-chevron-down{ transform: rotate(180deg); }
        .fsa-carregando{ opacity: .55; pointer-events: none; transition: opacity .15s; }

        /* Celular: menos margem */
        @media (max-width: 640px) {
            main > div.p-8 { padding: 1.1rem !important; }
            main h2.text-3xl { font-size: 1.5rem; }
        }

        /* =========================================================
           MENU LATERAL (CSS puro: aparece certo antes do Tailwind)
        ========================================================= */
        .sidebar{
            position: fixed; left: 0; top: 0; height: 100vh; width: 64px;
            background: linear-gradient(180deg, #4A9FCA 0%, #2B7FA6 100%);
            display: flex; flex-direction: column; align-items: center;
            padding: 12px 0; z-index: 40; overflow: hidden; box-sizing: border-box;
        }
        .sidebar-logo{ width: 60px; height: auto; padding: 0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin: 2px 0 18px; box-sizing: border-box; }
        .sidebar-logo img{ width: 100%; height: 100%; object-fit: contain; }
        .sidebar-nav{ display: flex; flex-direction: column; align-items: center; gap: 6px; width: 100%; flex-shrink: 0; }
        .sidebar-spacer{ flex: 1 1 auto; width: 100%; min-height: 6px; }
        .sidebar-link{
            position: relative; width: 40px; height: 36px; color: rgba(255,255,255,.92); border-radius: 11px;
            transition: background .15s, color .15s; text-decoration: none; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .sidebar-link:hover{ background: rgba(255,255,255,.18); color: #fff; }
        .sidebar-link.ativo{ background: #fff; color: #2B7FA6; box-shadow: 0 4px 12px -4px rgba(0,0,0,.25); }
        .sidebar-link i{ font-size: 16px; line-height: 1; }
        .sidebar-link.sidebar-link-sm i{ font-size: 14px; }
        .sidebar-link-muted{ color: rgba(255,255,255,.72); }
        .sidebar-badge{
            position: absolute; top: -3px; right: -3px; min-width: 16px; height: 16px; padding: 0 4px;
            background: #ef4444; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;
            line-height: 12px; text-align: center; box-sizing: border-box; border: 2px solid #3A8FB0;
        }
        .sidebar-divider{ border: none; border-top: 1px solid rgba(255,255,255,.22); margin: 6px 0; width: 28px; flex-shrink: 0; }
        .sidebar-logout-wrap{ flex-shrink: 0; margin-bottom: 4px; }
        /* Nome do item ao passar o mouse (fica fora do menu, que não rola) */
        #sbDica{ position: fixed; left: 72px; z-index: 60; padding: 6px 10px; background: #0f172a; color: #fff; font-size: 12px; font-weight: 600; border-radius: 8px; pointer-events: none; opacity: 0; transform: translateX(-4px); transition: opacity .12s, transform .12s; white-space: nowrap; }
        #sbDica.on{ opacity: 1; transform: none; }

        /* Faixa: aluno bloqueado por má conduta */
        .aviso-bloqueio{
            position: fixed; top: 64px; left: 64px; right: 0; z-index: 35;
            background: #fef2f2; border-bottom: 1px solid #fecaca; color: #b91c1c;
            font-size: .85rem; padding: .65rem 2rem; display: flex; align-items: center; gap: 10px;
        }
        .aviso-bloqueio i{ flex-shrink: 0; }
        .aviso-bloqueio strong{ font-weight: 700; }
    </style>
</head>
<body class="min-h-screen">

    <!-- MENU LATERAL -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <!-- Logo da FSA sem o fundo branco (PNG transparente; o branco dentro dos círculos foi mantido) -->
            <a href="<?= htmlspecialchars(perfilHome()) ?>" title="Fundação Santo André" style="display:block;width:100%;">
                <img src="assets/img/logo-fsa.png" alt="Fundação Santo André" style="width:100%;height:auto;display:block;">
            </a>
        </div>

        <nav class="sidebar-nav">
            <?php
            /* Menu montado pelo registro de perfis (includes/perfis.php).
               Itens do grupo "gestao" ficam abaixo do divisor, com o ícone menor. */
            $grupoMenuAnterior = null;
            foreach (perfilMenu() as $itemMenu):
                if (($itemMenu['cond'] ?? '') === 'nao_bloqueado' && $isBloqueado) continue;
                $ehGestao = ($itemMenu['grupo'] ?? '') === 'gestao';
                if ($ehGestao && $grupoMenuAnterior !== 'gestao'): ?>
                <hr class="sidebar-divider">
            <?php endif;
                $grupoMenuAnterior = $itemMenu['grupo'] ?? '';
                $ativo = (strtok($itemMenu['href'], '?') === $paginaAtual); ?>
                <a href="<?php echo htmlspecialchars($itemMenu['href']); ?>"
                   class="sidebar-link<?php echo $ehGestao ? ' sidebar-link-muted sidebar-link-sm' : ''; ?><?php echo $ativo ? ' ativo' : ''; ?>"
                   data-dica="<?php echo htmlspecialchars($itemMenu['titulo']); ?>" aria-label="<?php echo htmlspecialchars($itemMenu['titulo']); ?>"
                   <?php echo $ativo ? 'aria-current="page"' : ''; ?>>
                    <i class="fas <?php echo htmlspecialchars($itemMenu['icone']); ?>"></i>
                    <?php if (!empty($itemMenu['badge'])) echo avisosBadge($itemMenu['badge']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-spacer"></div>
        <div class="sidebar-logout-wrap">
            <a href="index.php?logout=1" class="sidebar-link sidebar-link-muted" data-dica="Sair" aria-label="Sair">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
    <div id="sbDica"></div>

    <!-- TOPO -->
    <header class="fixed top-0 right-0 left-16 h-16 bg-white/90 backdrop-blur border-b border-slate-200/80 flex items-center gap-3 px-4 sm:px-8 z-30">
        <h1 class="text-base sm:text-lg font-bold text-slate-900 truncate"><?php echo htmlspecialchars($pageTitle); ?></h1>

        <div class="ml-auto flex items-center gap-2">
            <?php if ($temConfig): ?>
            <button type="button" data-configuracoes=""
                    class="w-10 h-10 flex items-center justify-center rounded-full text-slate-500 hover:text-[#2B7FA6] hover:bg-[#e8f3fa] transition"
                    title="Configurações" aria-label="Configurações">
                <i class="fas fa-cog"></i>
            </button>
            <?php endif; ?>

            <!-- Avatar com menu -->
            <div class="relative">
                <button type="button" id="avatarBtn" aria-haspopup="true" aria-expanded="false"
                        class="flex items-center gap-2.5 pl-1.5 pr-3 py-1.5 rounded-full border border-slate-200 hover:border-[#4A9FCA] hover:bg-slate-50 transition">
                    <?php if ($temFoto): ?>
                        <img src="<?php echo htmlspecialchars($fotoSalva); ?>" alt="" class="w-8 h-8 rounded-full object-cover"
                             onerror="this.style.display='none'; document.getElementById('headerInitials').style.display='flex';">
                        <span id="headerInitials" class="w-8 h-8 bg-gradient-to-br from-[#4A9FCA] to-[#2B7FA6] rounded-full items-center justify-center text-white font-bold text-xs hidden"><?php echo getInitials($userName); ?></span>
                    <?php else: ?>
                        <span class="w-8 h-8 bg-gradient-to-br from-[#4A9FCA] to-[#2B7FA6] rounded-full flex items-center justify-center text-white font-bold text-xs"><?php echo getInitials($userName); ?></span>
                    <?php endif; ?>
                    <span class="text-sm text-slate-700 font-semibold hidden sm:inline max-w-[160px] truncate"><?php echo htmlspecialchars($userName); ?></span>
                    <i class="fas fa-chevron-down text-[10px] text-slate-400 hidden sm:inline"></i>
                </button>
                <div id="avatarMenu" class="hidden absolute right-0 mt-2 w-60 bg-white rounded-2xl shadow-xl border border-slate-100 p-2 fsa-pop">
                    <div class="px-3 py-2 mb-1 border-b border-slate-100">
                        <p class="text-sm font-bold text-slate-900 truncate"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-xs text-slate-400"><?php echo htmlspecialchars(perfilRotulo()); ?></p>
                    </div>
                    <a href="<?php echo htmlspecialchars($linkAvatar); ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-700 hover:bg-slate-50"><i class="fas fa-user w-4 text-slate-400"></i> Meu perfil</a>
                    <?php if ($temConfig): ?>
                    <a href="#configuracoes" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-700 hover:bg-slate-50"><i class="fas fa-cog w-4 text-slate-400"></i> Configurações</a>
                    <?php endif; ?>
                    <a href="index.php?logout=1" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt w-4"></i> Sair</a>
                </div>
            </div>
        </div>
    </header>

    <script>
    (function () {
        // Nome do item do menu lateral ao passar o mouse
        var dica = document.getElementById('sbDica');
        document.querySelectorAll('.sidebar-link[data-dica]').forEach(function (a) {
            a.addEventListener('mouseenter', function () {
                var r = a.getBoundingClientRect();
                dica.textContent = a.getAttribute('data-dica');
                dica.style.top = (r.top + r.height / 2 - 14) + 'px';
                dica.classList.add('on');
            });
            a.addEventListener('mouseleave', function () { dica.classList.remove('on'); });
        });
        // Menu do avatar
        var btn = document.getElementById('avatarBtn'), menu = document.getElementById('avatarMenu');
        function fecharMenu() { menu.classList.add('hidden'); btn.setAttribute('aria-expanded', 'false'); }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var abrir = menu.classList.contains('hidden');
            menu.classList.toggle('hidden', !abrir);
            btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) { if (!menu.contains(e.target)) fecharMenu(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fecharMenu(); });
        menu.addEventListener('click', function (e) { if (e.target.closest('a[href*="#configuracoes"]')) fecharMenu(); });
    })();
    </script>

    <?php if ($temConfig) include __DIR__ . '/configuracoes-modal.php'; ?>

    <!-- ================================================================ -->
    <!-- CAIXA PADRAO DE CONFIRMACAO / AVISO (mesmo visual do "Excluir Vaga?") -->
    <!-- Substitui o confirm()/alert() do navegador em todas as paginas.        -->
    <!-- Uso em formularios:  <form data-confirm="Texto" data-confirm-titulo="Titulo"
         data-confirm-botao="Excluir" data-confirm-tipo="perigo|aviso" data-confirm-icone="fa-trash-alt">
         Uso em JS:  fsaConfirmar({titulo, texto, botao, tipo, icone}, function(){...});  fsaAviso('texto', 'titulo');
         JUSTIFICATIVA OBRIGATÓRIA: acrescente data-justificativa="motivo" no formulário (nome do campo enviado).
         Opcionais: data-justificativa-dica="Ex.: ..." e data-justificativa-max="300". A caixa só confirma com
         pelo menos 10 caracteres. Em JS: fsaConfirmar({..., justificativa: true}, function (texto) {...}); -->
    <!-- ================================================================ -->
    <div id="fsaModal" class="fixed inset-0 z-[999] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="fsaModalTitulo">
        <div id="fsaModalFundo" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">
            <div id="fsaModalIconeBox" class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5">
                <i id="fsaModalIcone" class="fas fa-trash-alt text-3xl"></i>
            </div>
            <h3 id="fsaModalTitulo" class="text-xl font-bold text-gray-900 text-center mb-2"></h3>
            <p id="fsaModalTexto" class="text-gray-500 text-sm text-center mb-8 leading-relaxed"></p>
            <div id="fsaModalJustBox" class="hidden -mt-4 mb-6">
                <label for="fsaModalJust" class="block text-xs font-bold text-gray-500 uppercase mb-1">Justificativa (obrigatória)</label>
                <textarea id="fsaModalJust" rows="3" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#4A9FCA]"></textarea>
                <p id="fsaModalJustErro" class="hidden text-xs text-red-600 mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Escreva a justificativa (pelo menos 10 caracteres).</p>
            </div>
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
        var exigeJust = false;
        var JUST_MIN = 10;

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

            // Justificativa obrigatória (negar, recusar, suspender, bloquear...)
            exigeJust = !!o.justificativa;
            var just = $('fsaModalJust');
            $('fsaModalJustBox').classList.toggle('hidden', !exigeJust);
            $('fsaModalJustErro').classList.add('hidden');
            just.value = o.justificativaValor || '';
            just.placeholder = o.justificativaDica || 'Explique o motivo. A pessoa afetada pode ver este texto.';
            just.maxLength = +o.justificativaMax || 500;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            (exigeJust ? just : (o.soAviso ? ok : $('fsaModalCancelar'))).focus();
        }

        $('fsaModalCancelar').addEventListener('click', fechar);
        $('fsaModalFundo').addEventListener('click', fechar);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) fechar();
        });
        $('fsaModalOk').addEventListener('click', function () {
            var f = acaoOk, texto = '';
            if (exigeJust) {
                texto = $('fsaModalJust').value.trim();
                if (texto.length < JUST_MIN) {
                    $('fsaModalJustErro').classList.remove('hidden');
                    $('fsaModalJust').focus();
                    return;
                }
            }
            fechar();
            if (f) f(texto);
        });

        // Formularios com data-confirm abrem a caixa antes de enviar
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.getAttribute) return;
            var texto = form.getAttribute('data-confirm');
            var campoJust = form.getAttribute('data-justificativa');
            if ((!texto && !campoJust) || form.__fsaConfirmado) return;
            e.preventDefault();
            var quemEnviou = e.submitter || null;
            abrir({
                titulo: form.getAttribute('data-confirm-titulo'),
                texto:  texto || '',
                botao:  form.getAttribute('data-confirm-botao'),
                tipo:   form.getAttribute('data-confirm-tipo'),
                icone:  form.getAttribute('data-confirm-icone'),
                justificativa:     !!campoJust,
                justificativaDica: form.getAttribute('data-justificativa-dica'),
                justificativaMax:  form.getAttribute('data-justificativa-max'),
                onOk: function (just) {
                    if (campoJust) {               // coloca o texto no formulário antes de enviar
                        var campo = form.querySelector('[name="' + campoJust + '"]');
                        if (!campo) {
                            campo = document.createElement('input');
                            campo.type = 'hidden';
                            campo.name = campoJust;
                            form.appendChild(campo);
                        }
                        campo.value = just;
                    }
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

<?php if (perfilPaginaPermitida('mentoria-chat.php')): ?>
<script>
/* Bolinha do ícone Mentoria: pergunta ao servidor a cada 30 segundos
   se chegou mensagem nova ou pedido novo, sem precisar recarregar a página. */
(function () {
    function atualizar() {
        fetch('mentoria-chat.php?contar=1', { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) return;
                document.querySelectorAll('a.sidebar-link[href="mentoria.php"]').forEach(function (link) {
                    var b = link.querySelector('.sidebar-badge');
                    if (d.total > 0) {
                        if (!b) { b = document.createElement('span'); b.className = 'sidebar-badge'; link.appendChild(b); }
                        b.textContent = d.total > 99 ? '99+' : d.total;
                    } else if (b) {
                        b.remove();
                    }
                });
            })
            .catch(function () {});
    }
    window.fsaAtualizarBolinhaMentoria = atualizar;
    setInterval(atualizar, 30000);
})();
</script>
<?php endif; ?>

    <script>
    /* =====================================================================
       ATUALIZAR SEM RECARREGAR A PÁGINA (vale para todas as telas)
       ---------------------------------------------------------------------
       fsaTrocarPartes(['#a', '#b'], url, html)
         busca a página de novo por trás, troca só esses pedaços e mantém a
         rolagem onde estava. Scripts dentro dos pedaços rodam de novo.
       Formulários dentro do <main>:
         - de BUSCA/FILTRO (method GET): atualizam sem recarregar, sozinhos;
         - com data-parcial: enviam por trás (POST) e atualizam a tela.
         Para manter o jeito antigo, use data-recarregar no formulário.
         Se a resposta for um arquivo (Excel, CSV, PDF), baixa normalmente.
       Links com data-parcial também abrem sem recarregar.
    ===================================================================== */
    (function () {
        function rodarScripts(raiz) {
            raiz.querySelectorAll('script').forEach(function (s) {
                var n = document.createElement('script');
                if (s.src) n.src = s.src; else n.textContent = s.textContent;
                s.replaceWith(n);
            });
        }
        window.fsaTrocarPartes = function (seletores, url, htmlPronto) {
            var y = window.scrollY;
            var alvos = seletores.map(function (s) { return document.querySelector(s); }).filter(Boolean);
            alvos.forEach(function (a) { a.classList.add('fsa-carregando'); });
            var obter = htmlPronto !== undefined ? Promise.resolve(htmlPronto)
                : fetch(url || location.href, { cache: 'no-store', credentials: 'same-origin' }).then(function (r) { return r.text(); });
            return obter.then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                seletores.forEach(function (sel) {
                    var velho = document.querySelector(sel), novo = doc.querySelector(sel);
                    if (!velho || !novo) return;
                    novo = document.importNode(novo, true);
                    velho.replaceWith(novo);
                    rodarScripts(novo);
                });
                window.scrollTo(0, y);
                if (window.fsaAplicarRecolher) fsaAplicarRecolher();
                return { doc: doc, html: html };
            }).catch(function (e) {
                alvos.forEach(function (a) { a.classList.remove('fsa-carregando'); });
                throw e;
            });
        };

        function enviarParcial(form, submitter) {
            var metodo = (form.getAttribute('method') || 'GET').toUpperCase();
            var dados = new FormData(form);
            if (submitter && submitter.name) dados.append(submitter.name, submitter.value);
            var url = form.getAttribute('action') || location.pathname;
            var opcoes = { method: metodo, credentials: 'same-origin', cache: 'no-store' };
            if (metodo === 'GET') url = url.split('?')[0] + '?' + new URLSearchParams(dados).toString();
            else opcoes.body = dados;
            function jeitoAntigo() { form.setAttribute('data-recarregar', '1'); if (submitter) submitter.click(); else form.submit(); }
            return fetch(url, opcoes).then(function (r) {
                if ((r.headers.get('content-type') || '').indexOf('text/html') === -1) { jeitoAntigo(); return; }   // arquivo para baixar
                return r.text().then(function (html) {
                    var destino = metodo === 'GET' ? url : r.url;
                    return fsaTrocarPartes([form.getAttribute('data-parcial-alvo') || 'main'], null, html).then(function () {
                        if (destino && new URL(destino, location.href).pathname === location.pathname) history.replaceState(null, '', destino);
                    });
                });
            }).catch(jeitoAntigo);
        }

        // Fica no fim do topo: roda DEPOIS da caixa de confirmação
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (e.defaultPrevented || !form.closest || !form.closest('main')) return;
            if (form.hasAttribute('data-recarregar')) return;
            var metodo = (form.getAttribute('method') || 'GET').toUpperCase();
            if (!(metodo === 'GET' || form.hasAttribute('data-parcial'))) return;
            if (form.target && form.target !== '_self') return;
            e.preventDefault();
            enviarParcial(form, e.submitter);
        });
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('main a[data-parcial]');
            if (!a || e.ctrlKey || e.metaKey) return;
            e.preventDefault();
            var url = a.href;
            fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.text(); })
                .then(function (html) { return fsaTrocarPartes([a.getAttribute('data-parcial-alvo') || 'main'], null, html); })
                .then(function () { history.replaceState(null, '', url); })
                .catch(function () { location.href = url; });
        });

        /* ---------- Blocos que recolhem: "Mostrar tudo" / "Mostrar menos" ----------
           <button data-recolher="chave" aria-controls="idDoBloco" data-padrao="fechado|aberto"
                   data-txt-fechado="Mostrar tudo" data-txt-aberto="Mostrar menos">
             <span data-rotulo></span> <i class="fas fa-chevron-down"></i></button>
           Elementos com data-resumo-de="idDoBloco" aparecem só quando está fechado.
           A escolha fica guardada no navegador. */
        function estado(btn) {
            var salvo = null;
            try { salvo = localStorage.getItem('fsaRecolher:' + btn.getAttribute('data-recolher')); } catch (e) {}
            return salvo !== null ? salvo === '1' : btn.getAttribute('data-padrao') === 'aberto';
        }
        function aplicar(btn, aberto) {
            var id = btn.getAttribute('aria-controls'), alvo = document.getElementById(id);
            if (alvo) alvo.classList.toggle('hidden', !aberto);
            btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            var r = btn.querySelector('[data-rotulo]');
            if (r) r.textContent = aberto ? (btn.getAttribute('data-txt-aberto') || 'Mostrar menos') : (btn.getAttribute('data-txt-fechado') || 'Mostrar tudo');
            document.querySelectorAll('[data-resumo-de="' + id + '"]').forEach(function (x) { x.classList.toggle('hidden', aberto); });
        }
        window.fsaAplicarRecolher = function () {
            document.querySelectorAll('[data-recolher]').forEach(function (b) { aplicar(b, estado(b)); });
        };
        document.addEventListener('click', function (e) {
            var b = e.target.closest && e.target.closest('[data-recolher]');
            if (!b) return;
            e.preventDefault();
            var aberto = b.getAttribute('aria-expanded') !== 'true';
            try { localStorage.setItem('fsaRecolher:' + b.getAttribute('data-recolher'), aberto ? '1' : '0'); } catch (e2) {}
            aplicar(b, aberto);
        });
        document.addEventListener('DOMContentLoaded', fsaAplicarRecolher);
    })();
    </script>
