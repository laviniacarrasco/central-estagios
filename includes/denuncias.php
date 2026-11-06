<?php
/**
 * =============================================================================
 * CANAL DE DENÚNCIAS
 * -----------------------------------------------------------------------------
 * Qualquer pessoa logada (aluno, professor, egresso, coordenações) abre uma
 * denúncia num formulário, com anexos (imagem ou PDF; dá para colar a imagem
 * direto na mensagem). Quem avalia e responde é o perfil "admin_denuncias"
 * (Admin de Denúncias), que também cuida dos chats e denúncias da mentoria.
 *
 * Avisos dos dois lados: bolinha vermelha no ícone (chave "denuncias" em
 * includes/avisos.php) e e-mail (notificarEmail).
 *
 * Dados: canal_denuncias (JSON ou banco, via loadData/saveData)
 *   { id, protocolo, autorId, autorNome, autorPerfil, autorEmail, anonimo,
 *     categoria, assunto, status, criadoEm, criadoTs, atualizadoTs,
 *     mensagens: [ {id, de: 'autor'|'admin', nome, texto, anexos: [..], ts} ],
 *     naoLidoAutor: bool, naoLidoAdmin: bool }
 * Anexos: data/denuncias_anexos/<id>/ (a pasta data/ não é pública; os arquivos
 *         são entregues por denuncia-anexo.php, só para o autor e o admin).
 * =============================================================================
 */
require_once __DIR__ . '/notificacoes.php';

const DENUNCIA_MAX_ANEXOS  = 5;                  // por mensagem
const DENUNCIA_MAX_BYTES   = 5 * 1024 * 1024;    // 5 MB por arquivo
const DENUNCIA_TIPOS_ANEXO = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];

function denunciaCategorias(): array {
    return [
        'assedio'     => 'Assédio ou discriminação',
        'conduta'     => 'Conduta inadequada (aluno, professor, funcionário ou empresa)',
        'vaga'        => 'Vaga suspeita ou golpe',
        'estagio'     => 'Problema no estágio (contrato, horas, condições)',
        'privacidade' => 'Uso indevido de dados pessoais',
        'outro'       => 'Outro assunto',
    ];
}

function denunciaStatusInfo(string $s): array {
    switch ($s) {
        case 'em_analise': return ['Em análise', 'bg-amber-50 text-amber-700 border-amber-200', 'fa-search'];
        case 'concluida':  return ['Concluída', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-check-circle'];
        case 'arquivada':  return ['Arquivada', 'bg-gray-100 text-gray-600 border-gray-200', 'fa-archive'];
        default:           return ['Recebida', 'bg-[#e8f3fa] text-[#2B7FA6] border-[#cfe6f4]', 'fa-inbox'];
    }
}

function denunciaSouAdmin(): bool { return function_exists('perfilPode') && perfilPode('gestao_denuncias'); }

function denunciaTodas(): array {
    $d = loadData('canal_denuncias');
    return is_array($d) ? array_values($d) : [];
}
function denunciaSalvarTodas(array $lista): void { saveData('canal_denuncias', array_values($lista)); }

function denunciaPorId(int $id): ?array {
    foreach (denunciaTodas() as $d) if ((int) ($d['id'] ?? 0) === $id) return $d;
    return null;
}

/** A pessoa logada pode ver esta denúncia? */
function denunciaPodeVer(array $d): bool {
    return denunciaSouAdmin() || (int) ($d['autorId'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
}

/** Admins de denúncias: [[email, nome], ...] */
function denunciaAdmins(): array {
    $out = [];
    foreach (loadData('usuarios') as $u) {
        if (!is_array($u) || perfilDoUsuario($u) !== 'admin_denuncias') continue;
        $email = trim((string) ($u['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $out[$email] = [$email, (string) ($u['nome'] ?? '')];
    }
    return array_values($out);
}

/* ---------------------------------------------------------------------------
 * Anexos
 * ------------------------------------------------------------------------- */

function denunciaPastaAnexos(int $id): string { return DATA_PATH . 'denuncias_anexos/' . $id . '/'; }

/** Recebe $_FILES['anexos'] (vários arquivos). Confere o conteúdo real (não só a extensão). */
function denunciaReceberAnexos(int $id, $files): array {
    $saida = [];
    if (!is_array($files) || empty($files['name'])) return ['ok' => true, 'anexos' => [], 'erro' => ''];
    $nomes = (array) $files['name'];
    $total = 0;
    foreach ($nomes as $i => $nome) if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $total++;
    if ($total > DENUNCIA_MAX_ANEXOS) return ['ok' => false, 'anexos' => [], 'erro' => 'Envie no máximo ' . DENUNCIA_MAX_ANEXOS . ' arquivos por mensagem.'];

    $pasta = denunciaPastaAnexos($id);
    foreach ($nomes as $i => $nome) {
        $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'anexos' => [], 'erro' => 'Não foi possível receber o arquivo "' . $nome . '".'];
        $tmp = (string) $files['tmp_name'][$i];
        if ((int) $files['size'][$i] > DENUNCIA_MAX_BYTES) return ['ok' => false, 'anexos' => [], 'erro' => 'O arquivo "' . $nome . '" passa de 5 MB.'];
        $mime = function_exists('mime_content_type') ? (string) @mime_content_type($tmp) : '';
        if ($mime === '' && ($gi = @getimagesize($tmp))) $mime = (string) ($gi['mime'] ?? '');
        if (!isset(DENUNCIA_TIPOS_ANEXO[$mime])) return ['ok' => false, 'anexos' => [], 'erro' => 'O arquivo "' . $nome . '" precisa ser imagem (JPG, PNG, WEBP, GIF) ou PDF.'];
        if (strpos($mime, 'image/') === 0 && @getimagesize($tmp) === false) return ['ok' => false, 'anexos' => [], 'erro' => 'A imagem "' . $nome . '" parece estar corrompida.'];
        if (!is_dir($pasta)) @mkdir($pasta, 0775, true);
        $arquivo = bin2hex(random_bytes(8)) . '.' . DENUNCIA_TIPOS_ANEXO[$mime];
        if (!@move_uploaded_file($tmp, $pasta . $arquivo)) return ['ok' => false, 'anexos' => [], 'erro' => 'Não foi possível salvar o arquivo "' . $nome . '".'];
        $saida[] = ['arquivo' => $arquivo, 'nome' => mb_substr(preg_replace('/[^\pL\pN ._()-]+/u', '_', (string) $nome), 0, 80), 'mime' => $mime, 'tamanho' => (int) $files['size'][$i]];
    }
    return ['ok' => true, 'anexos' => $saida, 'erro' => ''];
}

/* ---------------------------------------------------------------------------
 * Ações
 * ------------------------------------------------------------------------- */

function denunciaNovoId(): int { return (int) (time() . random_int(100, 999)) % 2000000000; }

/** Abre uma denúncia. Devolve ['ok', 'denuncia', 'erro'] */
function denunciaCriar(array $autor, string $categoria, string $assunto, string $texto, bool $anonimo, $files): array {
    if (!isset(denunciaCategorias()[$categoria])) return ['ok' => false, 'erro' => 'Escolha o tipo da denúncia.'];
    $assunto = mb_substr(trim($assunto), 0, 120);
    $texto   = mb_substr(trim(preg_replace("/\r\n?/", "\n", $texto)), 0, 4000);
    if (mb_strlen($assunto) < 5) return ['ok' => false, 'erro' => 'Escreva um assunto com pelo menos 5 caracteres.'];
    if (mb_strlen($texto) < 20)  return ['ok' => false, 'erro' => 'Conte o que aconteceu com pelo menos 20 caracteres.'];

    $id = denunciaNovoId();
    $an = denunciaReceberAnexos($id, $files);
    if (!$an['ok']) return ['ok' => false, 'erro' => $an['erro']];

    $agora = time();
    $d = [
        'id'           => $id,
        'protocolo'    => 'DEN-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
        'autorId'      => (int) ($autor['id'] ?? 0),
        'autorNome'    => (string) ($autor['nome'] ?? ''),
        'autorEmail'   => (string) ($autor['email'] ?? ''),
        'autorPerfil'  => perfilRotulo(perfilDoUsuario($autor)),
        'anonimo'      => $anonimo,
        'categoria'    => $categoria,
        'assunto'      => $assunto,
        'status'       => 'recebida',
        'criadoEm'     => date('d/m/Y H:i'),
        'criadoTs'     => $agora,
        'atualizadoTs' => $agora,
        'mensagens'    => [[
            'id' => bin2hex(random_bytes(6)), 'de' => 'autor', 'nome' => $anonimo ? 'Anônimo' : (string) ($autor['nome'] ?? ''),
            'texto' => $texto, 'anexos' => $an['anexos'], 'ts' => $agora,
        ]],
        'naoLidoAutor' => false,
        'naoLidoAdmin' => true,
    ];
    $lista = denunciaTodas();
    array_unshift($lista, $d);
    denunciaSalvarTodas($lista);
    denunciaEmailAdmins($d, 'Nova denúncia recebida (' . $d['protocolo'] . ')', 'Chegou uma nova denúncia pelo Canal de Denúncias.');
    return ['ok' => true, 'denuncia' => $d, 'erro' => ''];
}

/** Nova mensagem na conversa (autor ou admin). O admin pode mudar a situação junto. */
function denunciaResponder(int $id, string $texto, $files, string $novoStatus = ''): array {
    $lista = denunciaTodas();
    foreach ($lista as $i => $d) {
        if ((int) ($d['id'] ?? 0) !== $id) continue;
        if (!denunciaPodeVer($d)) break;
        $souAdmin = denunciaSouAdmin();
        $texto = mb_substr(trim(preg_replace("/\r\n?/", "\n", $texto)), 0, 4000);
        $temArquivo = is_array($files) && array_filter((array) ($files['error'] ?? []), fn($e) => (int) $e !== UPLOAD_ERR_NO_FILE);
        $statusValido = $souAdmin && in_array($novoStatus, ['recebida', 'em_analise', 'concluida', 'arquivada'], true);
        $statusMudou  = $statusValido && $novoStatus !== ($d['status'] ?? '');
        if ($texto === '' && !$temArquivo && !$statusMudou) return ['ok' => false, 'erro' => 'Escreva uma mensagem, anexe um arquivo ou mude a situação.'];
        if (!$souAdmin && in_array($d['status'] ?? '', ['concluida', 'arquivada'], true)) return ['ok' => false, 'erro' => 'Esta denúncia já foi encerrada. Abra uma nova se precisar.'];

        $an = ['anexos' => []];
        if ($temArquivo) {
            $an = denunciaReceberAnexos($id, $files);
            if (!$an['ok']) return ['ok' => false, 'erro' => $an['erro']];
        }
        if ($texto !== '' || $an['anexos']) {
            $d['mensagens'][] = [
                'id' => bin2hex(random_bytes(6)), 'de' => $souAdmin ? 'admin' : 'autor',
                'nome' => $souAdmin ? 'Equipe de Denúncias' : (!empty($d['anonimo']) ? 'Anônimo' : (string) ($_SESSION['user_name'] ?? '')),
                'texto' => $texto, 'anexos' => $an['anexos'], 'ts' => time(),
            ];
        }
        if ($statusMudou) $d['status'] = $novoStatus;
        elseif ($souAdmin && ($d['status'] ?? '') === 'recebida' && $texto !== '') $d['status'] = 'em_analise';
        $d['atualizadoTs'] = time();
        if ($souAdmin) { $d['naoLidoAutor'] = true; $d['naoLidoAdmin'] = false; }
        else           { $d['naoLidoAdmin'] = true; }
        $lista[$i] = $d;
        denunciaSalvarTodas($lista);
        if ($souAdmin) denunciaEmailAutor($d, $texto !== '' || $an['anexos'], $statusMudou);
        else           denunciaEmailAdmins($d, 'Nova mensagem na denúncia ' . $d['protocolo'], 'A pessoa que abriu a denúncia mandou uma mensagem.');
        return ['ok' => true, 'denuncia' => $d, 'erro' => ''];
    }
    return ['ok' => false, 'erro' => 'Denúncia não encontrada.'];
}

/** Marca como lida por quem abriu a tela */
function denunciaMarcarLida(int $id): void {
    $lista = denunciaTodas();
    $mudou = false;
    foreach ($lista as $i => $d) {
        if ((int) ($d['id'] ?? 0) !== $id) continue;
        if (denunciaSouAdmin() && !empty($d['naoLidoAdmin'])) { $lista[$i]['naoLidoAdmin'] = false; $mudou = true; }
        elseif ((int) ($d['autorId'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0) && !empty($d['naoLidoAutor'])) { $lista[$i]['naoLidoAutor'] = false; $mudou = true; }
    }
    if ($mudou) denunciaSalvarTodas($lista);
}

/** Número da bolinha vermelha */
function denunciaContagemParaMim(): int {
    if (empty($_SESSION['user_id'])) return 0;
    $eu = (int) $_SESSION['user_id'];
    $admin = denunciaSouAdmin();
    $n = 0;
    foreach (denunciaTodas() as $d) {
        if ($admin) { if (!empty($d['naoLidoAdmin'])) $n++; }
        elseif ((int) ($d['autorId'] ?? 0) === $eu && !empty($d['naoLidoAutor'])) $n++;
    }
    return $n;
}

/* ---------------------------------------------------------------------------
 * E-mails (nunca derrubam a página). Por sigilo, o relato fica só na Central.
 * ------------------------------------------------------------------------- */

function denunciaEmailAdmins(array $d, string $assunto, string $intro): void {
    try {
        $cat = denunciaCategorias()[$d['categoria'] ?? 'outro'] ?? 'Outro';
        $html = '<p>' . emH($intro) . '</p><ul style="margin:0 0 12px 18px;padding:0">'
              . '<li><strong>Protocolo:</strong> ' . emH($d['protocolo'] ?? '') . '</li>'
              . '<li><strong>Tipo:</strong> ' . emH($cat) . '</li>'
              . '<li><strong>Assunto:</strong> ' . emH($d['assunto'] ?? '') . '</li>'
              . '<li><strong>Quem abriu:</strong> ' . (!empty($d['anonimo']) ? 'Anônimo' : emH(($d['autorNome'] ?? '') . ' (' . ($d['autorPerfil'] ?? '') . ')')) . '</li></ul>'
              . '<p style="color:#667085;font-size:12px">Por sigilo, o relato completo e os anexos ficam só na Central.</p>';
        foreach (denunciaAdmins() as [$email, $nome]) {
            notificarEmail($email, $nome, $assunto, 'Canal de Denúncias', $html, 'Abrir na Central', appBaseUrl() . '/admin-denuncias.php?id=' . (int) $d['id']);
        }
    } catch (Throwable $e) { error_log('denunciaEmailAdmins: ' . $e->getMessage()); }
}
function denunciaEmailAutor(array $d, bool $temMensagem, bool $statusMudou): void {
    try {
        $email = trim((string) ($d['autorEmail'] ?? ''));
        if ($email === '') $email = trim((string) (notificacoesEmailDoUsuario((int) ($d['autorId'] ?? 0)) ?? ''));
        if ($email === '') return;
        $st = denunciaStatusInfo((string) ($d['status'] ?? ''))[0];
        $html = '<p>Sua denúncia <strong>' . emH($d['protocolo'] ?? '') . '</strong> (' . emH($d['assunto'] ?? '') . ') teve uma atualização.</p>'
              . ($statusMudou ? '<p>Situação: <strong>' . emH($st) . '</strong></p>' : '')
              . ($temMensagem ? '<p>A equipe enviou uma mensagem. Por sigilo, leia na Central.</p>' : '');
        notificarEmail($email, (string) ($d['autorNome'] ?? ''), 'Atualização da sua denúncia (' . ($d['protocolo'] ?? '') . ')', 'Canal de Denúncias', $html, 'Ver na Central', appBaseUrl() . '/denuncias.php?id=' . (int) $d['id']);
    } catch (Throwable $e) { error_log('denunciaEmailAutor: ' . $e->getMessage()); }
}

/* ---------------------------------------------------------------------------
 * Pedaços de tela usados pelas duas páginas
 * ------------------------------------------------------------------------- */

function denunciaHtmlAnexos(array $d, array $anexos): string {
    if (!$anexos) return '';
    $h = '<div class="flex flex-wrap gap-2 mt-2">';
    foreach ($anexos as $a) {
        $url = 'denuncia-anexo.php?id=' . (int) $d['id'] . '&amp;a=' . rawurlencode((string) $a['arquivo']);
        if (strpos((string) $a['mime'], 'image/') === 0) {
            $h .= '<a href="' . $url . '" target="_blank" rel="noopener" class="block w-24 h-24 rounded-xl overflow-hidden border border-slate-200 bg-white"><img src="' . $url . '" alt="" class="w-full h-full object-cover"></a>';
        } else {
            $h .= '<a href="' . $url . '" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs text-slate-700 hover:bg-slate-50"><i class="fas fa-file-pdf text-red-500"></i>' . htmlspecialchars((string) $a['nome']) . '</a>';
        }
    }
    return $h . '</div>';
}

/** Campo de mensagem com anexos: escolher arquivo, arrastar ou COLAR imagem (Ctrl+V) */
function denunciaHtmlCampoMensagem(string $nome, string $placeholder, bool $obrigatorio = true, int $linhas = 6): string {
    return '<div class="den-campo">'
        . '<textarea name="' . $nome . '" rows="' . $linhas . '" maxlength="4000" ' . ($obrigatorio ? 'required minlength="20"' : '') . ' placeholder="' . htmlspecialchars($placeholder) . '"'
        . ' class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm"></textarea>'
        . '<div class="flex flex-wrap items-center gap-3 mt-2">'
        . '<label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-600 cursor-pointer hover:bg-slate-50">'
        . '<i class="fas fa-paperclip"></i> Anexar imagem ou PDF'
        . '<input type="file" name="anexos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" class="hidden"></label>'
        . '<span class="text-xs text-slate-400">Também dá para colar uma imagem (Ctrl+V) na mensagem. Até ' . DENUNCIA_MAX_ANEXOS . ' arquivos de 5 MB.</span>'
        . '</div><div class="den-previas flex flex-wrap gap-2 mt-2"></div></div>';
}

/** Script do campo acima (colar imagem, arrastar, prévias, remover) */
function denunciaJsCampoMensagem(): string {
    return <<<'JS'
<script>
(function () {
    document.querySelectorAll('.den-campo').forEach(function (caixa) {
        if (caixa.dataset.pronto) return; caixa.dataset.pronto = '1';
        var input = caixa.querySelector('input[type=file]'), txt = caixa.querySelector('textarea'), previas = caixa.querySelector('.den-previas');
        var lista = new DataTransfer();
        function desenhar() {
            previas.textContent = '';
            Array.from(lista.files).forEach(function (f, i) {
                var item = document.createElement('div');
                item.className = 'relative w-20 h-20 rounded-xl overflow-hidden border border-slate-200 bg-slate-50 flex items-center justify-center';
                if (f.type.indexOf('image/') === 0) { var img = document.createElement('img'); img.src = URL.createObjectURL(f); img.className = 'w-full h-full object-cover'; item.appendChild(img); }
                else { item.innerHTML = '<i class="fas fa-file-pdf text-red-500 text-2xl"></i>'; }
                var x = document.createElement('button'); x.type = 'button'; x.title = 'Remover';
                x.className = 'absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white text-xs'; x.innerHTML = '&times;';
                x.onclick = function () { var n = new DataTransfer(); Array.from(lista.files).forEach(function (g, j) { if (j !== i) n.items.add(g); }); lista = n; input.files = lista.files; desenhar(); };
                item.appendChild(x); previas.appendChild(item);
            });
        }
        function juntar(files) {
            Array.from(files).forEach(function (f) {
                if (lista.files.length >= 5) { if (window.fsaAviso) fsaAviso('Até 5 arquivos por mensagem.', 'Arquivos demais'); return; }
                if (!/^image\/(jpeg|png|webp|gif)$|^application\/pdf$/.test(f.type)) { if (window.fsaAviso) fsaAviso('Use imagem (JPG, PNG, WEBP, GIF) ou PDF.', 'Arquivo não aceito'); return; }
                if (f.size > 5 * 1024 * 1024) { if (window.fsaAviso) fsaAviso('Cada arquivo pode ter até 5 MB.', 'Arquivo grande demais'); return; }
                lista.items.add(f);
            });
            input.files = lista.files; desenhar();
        }
        input.addEventListener('change', function () { var novos = Array.from(input.files); input.files = lista.files; juntar(novos); });
        txt.addEventListener('paste', function (e) {
            var arqs = Array.from((e.clipboardData || {}).items || []).filter(function (it) { return it.kind === 'file'; }).map(function (it) {
                var f = it.getAsFile(); if (!f) return null;
                var ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                return new File([f], 'imagem-colada-' + Date.now() + '.' + ext, { type: f.type });
            }).filter(Boolean);
            if (arqs.length) { e.preventDefault(); juntar(arqs); }
        });
        caixa.addEventListener('dragover', function (e) { e.preventDefault(); });
        caixa.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) { e.preventDefault(); juntar(e.dataTransfer.files); } });
    });
})();
</script>
JS;
}
