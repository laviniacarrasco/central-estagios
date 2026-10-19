<?php
/**
 * Cartao "Meus Alertas" para a pagina do Perfil.
 * Uso: no profile.php, antes do fechamento do main, incluir este arquivo (veja o LEIA-ME).
 * Os dados sao salvos por alertas-salvar.php, que volta para profile.php#alertas.
 * Tudo roda dentro de uma funcao para nao misturar variaveis com as do profile.php.
 */
require_once __DIR__ . '/alertas.php';

function alertasCardRender(): void {
    if (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_egresso'])) return;

    $usuario = egressoCarregarUsuario((int) ($_SESSION['user_id'] ?? 0));
    if (!$usuario) return;

    $prefs     = alertasPrefs($usuario);
    $temEmail  = alertasEmailValido($usuario);
    $fsaCursos = getFsaCourses();

    $mensagens = [
        'salvo'       => ['emerald', 'Preferências de alerta salvas.'],
        'teste_ok'    => ['emerald', 'Resumo de teste enviado. Confira sua caixa de entrada (e o spam).'],
        'teste_falha' => ['red', 'Não consegui enviar o e-mail de teste. Verifique a configuração do Gmail no servidor.'],
        'sem_email'   => ['red', 'Seu cadastro não tem um e-mail válido. Fale com a coordenação para corrigir.'],
    ];
    $msg = $_GET['alertas'] ?? '';

    // So aparece quando o aluno clica na engrenagem do topo (ou depois de salvar/testar)
    if (($_GET['config'] ?? '') !== 'alertas' && $msg === '') return;
    ?>
    <section id="alertas" class="mt-8 bg-white p-8 rounded-2xl border-2 border-[#4A9FCA] shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <h3 class="text-xl font-bold text-slate-900 mb-1"><i class="fas fa-bell text-[#4A9FCA] mr-2"></i>Meus Alertas</h3>
            <a href="profile.php" class="text-sm text-gray-400 hover:text-gray-600" title="Fechar"><i class="fas fa-times mr-1"></i>Fechar</a>
        </div>
        <p class="text-sm text-gray-500 mb-5">Receba por e-mail as vagas dos cursos que você acompanha.</p>

        <?php if (isset($mensagens[$msg])): [$cor, $txt] = $mensagens[$msg]; ?>
            <div class="mb-5 p-4 bg-<?= $cor ?>-50 border border-<?= $cor ?>-200 text-<?= $cor ?>-700 rounded-xl text-sm"><?= htmlspecialchars($txt) ?></div>
        <?php endif; ?>

        <?php if (!$temEmail): ?>
            <div class="mb-5 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm">
                <i class="fas fa-exclamation-triangle mr-1"></i> Seu cadastro não tem e-mail válido, então nenhum alerta chega até você.
            </div>
        <?php endif; ?>

        <form method="POST" action="alertas-salvar.php" class="space-y-5">
            <input type="hidden" name="action" value="salvar">

            <label class="flex items-start gap-3">
                <input type="checkbox" name="resumoSemanal" class="mt-1 w-5 h-5" <?= $prefs['resumoSemanal'] ? 'checked' : '' ?>>
                <span>
                    <strong class="text-slate-900">Resumo semanal</strong>
                    <span class="block text-sm text-gray-500">Um e-mail por semana (segunda-feira) com as vagas novas, as vagas salvas que estão encerrando e as que mais combinam com seu currículo. Só é enviado se houver novidade.</span>
                </span>
            </label>

            <label class="flex items-start gap-3">
                <input type="checkbox" name="vagaNova" class="mt-1 w-5 h-5" <?= $prefs['vagaNova'] ? 'checked' : '' ?>>
                <span>
                    <strong class="text-slate-900">Aviso na hora</strong>
                    <span class="block text-sm text-gray-500">Um e-mail assim que sair uma vaga para os seus cursos. Pode gerar vários e-mails.</span>
                </span>
            </label>

            <div>
                <p class="font-semibold text-slate-900 mb-1">Cursos que quero acompanhar</p>
                <p class="text-sm text-gray-500 mb-3">Vagas abertas a todos os cursos sempre entram. Se não marcar nenhum, usamos o seu curso<?= !empty($usuario['curso']) && $usuario['curso'] !== '—' ? ': <strong>' . htmlspecialchars($usuario['curso']) . '</strong>' : '' ?>.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-1 max-h-56 overflow-y-auto p-3 bg-gray-50 rounded-xl">
                    <?php foreach ($fsaCursos as $c): ?>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="cursos[]" value="<?= htmlspecialchars($c) ?>" <?= in_array($c, $prefs['cursos'], true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <p class="text-xs text-gray-400">Os e-mails vão para: <strong><?= htmlspecialchars($usuario['email'] ?? '—') ?></strong></p>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="px-8 h-12 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl font-semibold">
                    <i class="fas fa-save mr-1"></i> Salvar alertas
                </button>
                <button type="submit" form="formTesteAlertas" class="px-6 h-12 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 text-sm font-semibold">
                    <i class="fas fa-paper-plane mr-1"></i> Enviar um resumo de teste agora
                </button>
            </div>
        </form>
        <form id="formTesteAlertas" method="POST" action="alertas-salvar.php"><input type="hidden" name="action" value="teste"></form>
    </section>
    <script>document.getElementById('alertas').scrollIntoView({behavior:'smooth', block:'start'});</script>
    <?php
}

alertasCardRender();
