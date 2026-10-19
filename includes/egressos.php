<?php
/**
 * =============================================================================
 * MODULO EGRESSOS - funcoes compartilhadas
 * -----------------------------------------------------------------------------
 * Tipos de usuario (campo "tipo" em usuarios.json):
 *   (vazio)          -> aluno comum
 *   "egresso"        -> ex-aluno (so enxerga a area de egressos)
 *   "admin_egressos" -> admin (is_admin=true) que TAMBEM cuida dos egressos
 * O admin comum continua sendo apenas is_admin=true, sem "tipo".
 *
 * Dados novos (todos em data/):
 *   egresso_jobs.json                  -> vagas divulgadas por egressos
 *   users/<id>/userCandidaturas.json   -> candidaturas de cada aluno
 * Os dados do perfil do egresso ficam no proprio usuarios.json.
 * =============================================================================
 */

require_once __DIR__ . '/vagas.php';
require_once __DIR__ . '/notificacoes.php';

function isEgresso(): bool {
    return !empty($_SESSION['is_egresso']);
}

function isAdminEgressos(): bool {
    return !empty($_SESSION['is_admin']) && !empty($_SESSION['is_admin_egressos']);
}

/** Escapa texto para HTML */
function egh($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** JSON seguro para colocar dentro de <script> */
function egJson($v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/** valor gravado => texto exibido (os 4 primeiros sao os mesmos dos alunos) */
function egressoStatusOptions(): array {
    return [
        'Empregado na area'         => 'Empregado(a) na área',
        'Empregado fora da area'    => 'Empregado(a) fora da área',
        'Estagiando na area'        => 'Estagiando na área',
        'Estagiando fora da area'   => 'Estagiando fora da área',
        'Empreendedor / autonomo'   => 'Empreendedor(a) / autônomo(a)',
        'Estudando (pos-graduacao)' => 'Estudando (pós-graduação)',
        'Buscando oportunidade'     => 'Buscando oportunidade',
    ];
}

function egressoStatusLabel($status): string {
    $op = egressoStatusOptions();
    return $op[trim((string) $status)] ?? 'Não informado';
}

/**
 * Agrupa o status em: na_area | fora_area | empreendedor | estudando | buscando | nao_informado
 * (estagio e emprego contam igual, como no dashboard dos alunos)
 */
function egressoGrupoStatus($status): string {
    switch (trim((string) $status)) {
        case 'Empregado na area':
        case 'Estagiando na area':
            return 'na_area';
        case 'Empregado fora da area':
        case 'Estagiando fora da area':
            return 'fora_area';
        case 'Empreendedor / autonomo':
            return 'empreendedor';
        case 'Estudando (pos-graduacao)':
            return 'estudando';
        case 'Buscando oportunidade':
            return 'buscando';
        default:
            return 'nao_informado';
    }
}

function egressoCategorias(): array {
    return ['Estágio', 'Emprego (CLT)', 'Trainee', 'Temporário / PJ', 'Outro'];
}

/** pendente | aprovada | rejeitada | encerrada  =>  [texto, classes tailwind, icone] */
function egressoVagaStatusInfo($status): array {
    switch ($status) {
        case 'aprovada':  return ['Publicada', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-check-circle'];
        case 'rejeitada': return ['Não aprovada', 'bg-red-50 text-red-700 border-red-200', 'fa-times-circle'];
        case 'expirada':  return ['Expirada', 'bg-gray-100 text-gray-500 border-gray-200', 'fa-hourglass-end'];
        case 'encerrada': return ['Encerrada', 'bg-gray-100 text-gray-600 border-gray-200', 'fa-lock'];
        default:          return ['Aguardando análise', 'bg-amber-50 text-amber-700 border-amber-200', 'fa-clock'];
    }
}

/** Status "de verdade": uma vaga aprovada cuja validade passou vira 'expirada' */
function egressoVagaStatusEfetivo(array $v): string {
    $s = $v['status'] ?? 'pendente';
    return ($s === 'aprovada' && vagaExpirou($v)) ? 'expirada' : $s;
}

function egressoVagas(): array {
    $d = loadData('egresso_jobs');
    return is_array($d) ? array_values($d) : [];
}

function egressoSalvarVagas(array $vagas): void {
    saveData('egresso_jobs', array_values($vagas));
}

function egressoNovoId(): int {
    return time() * 1000 + random_int(0, 999);
}

/**
 * Aceita URL ou e-mail e devolve um link seguro (http, https ou mailto).
 * Retorna '' se for invalido (bloqueia javascript:, data:, etc.).
 */
function egressoNormalizarLink(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (filter_var($v, FILTER_VALIDATE_EMAIL)) return 'mailto:' . $v;
    if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $v)) $v = 'https://' . $v;
    if (preg_match('#^mailto:#i', $v)) {
        return filter_var(substr($v, 7), FILTER_VALIDATE_EMAIL) ? $v : '';
    }
    if (!preg_match('#^https?://#i', $v)) return '';
    return filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
}

function egressoCarregarUsuario(int $id): ?array {
    foreach (loadData('usuarios') as $u) {
        if (($u['id'] ?? null) == $id) return $u;
    }
    return null;
}

/** Atualiza campos de um usuario em usuarios.json */
function egressoAtualizarUsuario(int $id, array $campos): bool {
    $usuarios = loadData('usuarios');
    $achou = false;
    foreach ($usuarios as &$u) {
        if (($u['id'] ?? null) == $id) {
            foreach ($campos as $k => $v) $u[$k] = $v;
            $achou = true;
            break;
        }
    }
    unset($u);
    if ($achou) saveData('usuarios', $usuarios);
    return $achou;
}

function candidaturaStatusOptions(): array {
    return ['Enviada', 'Em processo', 'Entrevista', 'Aprovado(a)', 'Não selecionado(a)', 'Desisti'];
}
