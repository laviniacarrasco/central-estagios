<?php
/**
 * =============================================================================
 * HELPERS DE VAGAS: VALIDADE (encerramento automatico) E FAVORITOS
 * -----------------------------------------------------------------------------
 * Validade: campo "expiresAt" (Y-m-d) nas vagas da FSA (platform_jobs.json) e nas
 * vagas de egressos (egresso_jobs.json). A vaga vale ATE o fim desse dia.
 * Vaga sem "expiresAt" (as antigas) nunca expira.
 * O encerramento e calculado na hora de exibir: nao depende de cron.
 *
 * Favoritos: data/users/<id>/userFavoritos.json
 *   [ { "origem": "faculdade|egresso", "jobId": 123, "title": "...",
 *       "company": "...", "savedTs": 1700000000 } ]
 * =============================================================================
 */

/** Aceita 'Y-m-d'. Devolve '' se invalida, no passado ou alem do limite de dias. */
function vagaNormalizarValidade($v, ?int $maxDias = null): string {
    $v = trim((string) $v);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return '';
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) return '';
    if ($v < date('Y-m-d')) return '';
    if ($maxDias !== null && $v > date('Y-m-d', strtotime("+{$maxDias} days"))) return '';
    return $v;
}

function vagaValidadePadrao(int $dias = 60): string {
    return date('Y-m-d', strtotime("+{$dias} days"));
}

function vagaExpirou(array $v): bool {
    $d = (string) ($v['expiresAt'] ?? '');
    return $d !== '' && $d < date('Y-m-d');
}

/** Vaga da FSA visivel para os alunos: status Ativa e dentro da validade */
function vagaVigente(array $v): bool {
    return ($v['status'] ?? '') === 'Ativa' && !vagaExpirou($v);
}

function vagaDiasRestantes(array $v): ?int {
    $d = (string) ($v['expiresAt'] ?? '');
    if ($d === '') return null;
    return (int) round((strtotime($d) - strtotime(date('Y-m-d'))) / 86400);
}

/** Texto pequeno para colocar embaixo da data da vaga */
function vagaValidadeHtml(array $v): string {
    $d = (string) ($v['expiresAt'] ?? '');
    if ($d === '') return '';
    $dias = vagaDiasRestantes($v);
    $fmt  = date('d/m/Y', strtotime($d));
    if ($dias < 0)  return '<span class="block text-xs text-gray-400">Expirada em ' . $fmt . '</span>';
    if ($dias === 0) return '<span class="block text-xs text-red-500 font-semibold">Encerra hoje</span>';
    if ($dias <= 7) return '<span class="block text-xs text-amber-600 font-semibold">Encerra em ' . $dias . ' dia' . ($dias > 1 ? 's' : '') . '</span>';
    return '<span class="block text-xs text-gray-400">Aberta até ' . $fmt . '</span>';
}

/* ------------------------------------------------------------------ */
/* Favoritos (so alunos)                                               */
/* ------------------------------------------------------------------ */

function favoritosLista(): array {
    $d = loadData('userFavoritos', true);
    return is_array($d) ? array_values($d) : [];
}

function favoritoExiste(string $origem, int $id): bool {
    static $set = null;
    if ($set === null) {
        $set = [];
        foreach (favoritosLista() as $f) {
            $set[($f['origem'] ?? '') . ':' . (int) ($f['jobId'] ?? 0)] = true;
        }
    }
    return isset($set[$origem . ':' . $id]);
}

/** Botao "Salvar" (coracao). Nao mostra nada para admin e egresso. */
function favoritoBotaoHtml(string $origem, int $id): string {
    if (function_exists('perfilPode') ? !perfilPode('favoritar') : (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_egresso']))) return '';
    $salvo = favoritoExiste($origem, $id);
    $volta = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'jobs.php', ENT_QUOTES, 'UTF-8');
    return '<form method="POST" action="favoritar.php" class="inline">'
        . '<input type="hidden" name="origem" value="' . htmlspecialchars($origem, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="job_id" value="' . $id . '">'
        . '<input type="hidden" name="volta" value="' . $volta . '">'
        . '<button type="submit" title="' . ($salvo ? 'Remover das vagas salvas' : 'Salvar vaga') . '" '
        . 'class="px-4 py-2 border rounded-xl text-sm transition-all ' . ($salvo ? 'border-red-200 text-red-500 bg-red-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50') . '">'
        . '<i class="' . ($salvo ? 'fas' : 'far') . ' fa-heart mr-1"></i> ' . ($salvo ? 'Salva' : 'Salvar')
        . '</button></form>';
}
