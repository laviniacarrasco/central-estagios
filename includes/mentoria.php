<?php
/**
 * =============================================================================
 * MODULO MENTORIA (egressos orientam alunos)
 * -----------------------------------------------------------------------------
 * Fluxo:
 *   1. Egresso se cadastra como mentor em mentoria.php (aba "Minha mentoria").
 *   2. O admin de egressos aprova em admin-mentoria.php.
 *   3. Aluno ve os mentores aprovados e envia um pedido.
 *   4. O mentor aceita ou recusa. So depois do ACEITE os contatos sao liberados.
 *
 * Dados:
 *   usuarios.json (egresso): mentoriaStatus (pendente|aprovado|recusado|suspenso),
 *     mentoriaAreas, mentoriaMax, mentoriaContato (email|linkedin|ambos),
 *     mentoriaMotivo, mentoriaSolicitadoEm, aceitaMentoria (aparece para alunos)
 *   mentoria_pedidos.json: um registro por pedido (gravado com trava de arquivo)
 *
 * Status do pedido: pendente | aceito | recusado | cancelado | concluido
 * =============================================================================
 */

require_once __DIR__ . '/egressos.php';

const MENTORIA_MAX_PEDIDOS_ALUNO = 3;  // pedidos em andamento (aguardando + ativos) por aluno
const MENTORIA_MAX_FILA_MENTOR   = 5;  // pedidos aguardando resposta por mentor

function mentoriaAssuntos(): array {
    return [
        'Carreira e primeiros passos',
        'Entrevistas e processo seletivo',
        'Currículo e LinkedIn',
        'Transição de área',
        'Pós-graduação e estudos',
        'Empreendedorismo',
        'Outro',
    ];
}

/** [texto, classes tailwind, icone] do cadastro de mentor */
function mentoriaMentorStatusInfo($status): array {
    switch ($status) {
        case 'aprovado': return ['Ativo', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-check-circle'];
        case 'pendente': return ['Aguardando aprovação', 'bg-amber-50 text-amber-700 border-amber-200', 'fa-clock'];
        case 'recusado': return ['Não aprovado', 'bg-red-50 text-red-700 border-red-200', 'fa-times-circle'];
        case 'suspenso': return ['Suspenso', 'bg-gray-100 text-gray-600 border-gray-200', 'fa-pause-circle'];
        default:         return ['Não cadastrado', 'bg-gray-100 text-gray-500 border-gray-200', 'fa-minus-circle'];
    }
}

/** [texto, classes tailwind, icone] do pedido */
function mentoriaPedidoStatusInfo($status): array {
    switch ($status) {
        case 'aceito':    return ['Em andamento', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-handshake'];
        case 'recusado':  return ['Recusado', 'bg-red-50 text-red-700 border-red-200', 'fa-times-circle'];
        case 'cancelado': return ['Cancelado', 'bg-gray-100 text-gray-600 border-gray-200', 'fa-ban'];
        case 'concluido': return ['Concluída', 'bg-blue-50 text-blue-700 border-blue-200', 'fa-flag-checkered'];
        default:          return ['Aguardando resposta', 'bg-amber-50 text-amber-700 border-amber-200', 'fa-clock'];
    }
}

function mentoriaEmAndamento($status): bool {
    return in_array($status, ['pendente', 'aceito'], true);
}

/* ------------------------------------------------------------------ */
/* Armazenamento (com trava, para dois cliques ao mesmo tempo)          */
/* ------------------------------------------------------------------ */

function mentoriaOperar(callable $fn) {
    $path = DATA_PATH . 'mentoria_pedidos.json';
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $fp = @fopen($path, 'c+');
    if (!$fp) return null;
    flock($fp, LOCK_EX);
    $raw   = stream_get_contents($fp);
    $lista = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
    if (!is_array($lista)) $lista = [];
    $res = $fn($lista);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(array_values($lista), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $res;
}

function mentoriaPedidos(): array {
    $d = loadData('mentoria_pedidos');
    return is_array($d) ? array_values($d) : [];
}

/* ------------------------------------------------------------------ */
/* Mentores                                                            */
/* ------------------------------------------------------------------ */

function mentoriaAreasLista($texto, int $max = 6): array {
    $itens = preg_split('/[,;\n]+/', (string) $texto);
    $out = [];
    foreach ($itens as $i) {
        $i = trim($i);
        if ($i === '') continue;
        $out[] = mb_substr($i, 0, 40);
        if (count($out) >= $max) break;
    }
    return $out;
}

function mentoriaMax(array $u): int {
    $m = (int) ($u['mentoriaMax'] ?? 2);
    return max(1, min(5, $m));
}

/** Egresso aprovado e que marcou "aparecer para alunos" */
function mentoriaMentorVisivel(array $u): bool {
    return ($u['tipo'] ?? '') === 'egresso'
        && ($u['mentoriaStatus'] ?? '') === 'aprovado'
        && !empty($u['aceitaMentoria']);
}

function mentoriaAceitosDoMentor(int $mentorId, array $pedidos): int {
    $n = 0;
    foreach ($pedidos as $p) {
        if ((int) ($p['mentorId'] ?? 0) === $mentorId && ($p['status'] ?? '') === 'aceito') $n++;
    }
    return $n;
}

function mentoriaPendentesDoMentor(int $mentorId, array $pedidos): int {
    $n = 0;
    foreach ($pedidos as $p) {
        if ((int) ($p['mentorId'] ?? 0) === $mentorId && ($p['status'] ?? '') === 'pendente') $n++;
    }
    return $n;
}

/** Mentores visiveis para os alunos, com vagas livres calculadas (_livres) */
function mentoriaMentores(): array {
    $pedidos = mentoriaPedidos();
    $lista = [];
    foreach (loadData('usuarios') as $u) {
        if (!mentoriaMentorVisivel($u)) continue;
        $id = (int) ($u['id'] ?? 0);
        $u['_livres'] = max(0, mentoriaMax($u) - mentoriaAceitosDoMentor($id, $pedidos));
        $lista[] = $u;
    }
    usort($lista, function ($a, $b) {
        $porVaga = ($b['_livres'] > 0) <=> ($a['_livres'] > 0);
        if ($porVaga !== 0) return $porVaga;
        return strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
    });
    return $lista;
}

/** Contato que o mentor libera depois do aceite, conforme a preferencia dele */
function mentoriaContatoMentor(array $m): array {
    $pref  = $m['mentoriaContato'] ?? 'ambos';
    $email = trim((string) ($m['email'] ?? ''));
    $lk    = trim((string) ($m['linkedin'] ?? ''));
    $out   = ['email' => '', 'linkedin' => ''];
    if (($pref === 'email' || $pref === 'ambos' || $lk === '') && $email !== '') $out['email'] = $email;
    if (($pref === 'linkedin' || $pref === 'ambos') && $lk !== '') $out['linkedin'] = $lk;
    if ($out['email'] === '' && $out['linkedin'] === '') $out['email'] = $email;
    return $out;
}

function mentoriaIniciais($nome): string {
    $partes = explode(' ', trim((string) $nome));
    $a = strtoupper(substr($partes[0], 0, 1));
    $b = count($partes) > 1 ? strtoupper(substr(end($partes), 0, 1)) : '';
    return $a . $b;
}

function mentoriaFotoUsuario(int $id): ?string {
    $f = DATA_PATH . 'users/' . $id . '/userProfile.json';
    if (!is_file($f)) return null;
    $d = json_decode((string) @file_get_contents($f), true);
    $foto = is_array($d) ? ($d['foto'] ?? null) : null;
    if (!$foto || strpos((string) $foto, '..') !== false) return null;
    return is_file(__DIR__ . '/../' . $foto) ? (string) $foto : null;
}

/** Numero para a bolinha vermelha do menu (pedidos para o mentor / mentores para aprovar) */
function mentoriaPendentesParaMim(): int {
    if (empty($_SESSION['user_id'])) return 0;
    if (isEgresso()) {
        return mentoriaPendentesDoMentor((int) $_SESSION['user_id'], mentoriaPedidos());
    }
    if (isAdminEgressos()) {
        $n = 0;
        foreach (loadData('usuarios') as $u) {
            if (($u['tipo'] ?? '') === 'egresso' && ($u['mentoriaStatus'] ?? '') === 'pendente') $n++;
        }
        return $n;
    }
    return 0;
}

/* ------------------------------------------------------------------ */
/* Operacoes nos pedidos                                               */
/* ------------------------------------------------------------------ */

/** Cria o pedido respeitando os limites. Retorna ['ok'=>bool, 'erro'=>chave, 'pedido'=>array] */
function mentoriaCriarPedido(array $aluno, array $mentor, string $assunto, string $mensagem): array {
    $res = mentoriaOperar(function (array &$lista) use ($aluno, $mentor, $assunto, $mensagem) {
        $alunoId  = (int) ($aluno['id'] ?? 0);
        $mentorId = (int) ($mentor['id'] ?? 0);
        $emAndamento = 0;
        $fila = 0;
        $aceitos = 0;
        foreach ($lista as $p) {
            $st = $p['status'] ?? '';
            if ((int) ($p['alunoId'] ?? 0) === $alunoId && mentoriaEmAndamento($st)) {
                $emAndamento++;
                if ((int) ($p['mentorId'] ?? 0) === $mentorId) return ['ok' => false, 'erro' => 'duplicado'];
            }
            if ((int) ($p['mentorId'] ?? 0) === $mentorId) {
                if ($st === 'pendente') $fila++;
                if ($st === 'aceito') $aceitos++;
            }
        }
        if ($emAndamento >= MENTORIA_MAX_PEDIDOS_ALUNO) return ['ok' => false, 'erro' => 'limite'];
        if ($aceitos >= mentoriaMax($mentor))           return ['ok' => false, 'erro' => 'sem_vaga'];
        if ($fila >= MENTORIA_MAX_FILA_MENTOR)          return ['ok' => false, 'erro' => 'fila_cheia'];

        $pedido = [
            'id'           => egressoNovoId(),
            'alunoId'      => $alunoId,
            'alunoNome'    => (string) ($aluno['nome'] ?? ''),
            'alunoEmail'   => (string) ($aluno['email'] ?? ''),
            'alunoCurso'   => (string) ($aluno['curso'] ?? ''),
            'alunoPeriodo' => (string) ($aluno['periodo'] ?? ''),
            'mentorId'     => $mentorId,
            'mentorNome'   => (string) ($mentor['nome'] ?? ''),
            'assunto'      => $assunto,
            'mensagem'     => $mensagem,
            'status'       => 'pendente',
            'resposta'     => '',
            'criadoEm'     => date('d/m/Y H:i'),
            'criadoTs'     => time(),
        ];
        array_unshift($lista, $pedido);
        return ['ok' => true, 'pedido' => $pedido];
    });
    return is_array($res) ? $res : ['ok' => false, 'erro' => 'falha'];
}

/** O mentor aceita ou recusa um pedido pendente dele */
function mentoriaResponderPedido(int $pedidoId, int $mentorId, bool $aceitar, string $resposta, array $mentor): array {
    $res = mentoriaOperar(function (array &$lista) use ($pedidoId, $mentorId, $aceitar, $resposta, $mentor) {
        $idx = null;
        $aceitos = 0;
        foreach ($lista as $k => $p) {
            if ((int) ($p['mentorId'] ?? 0) === $mentorId && ($p['status'] ?? '') === 'aceito') $aceitos++;
            if ((int) ($p['id'] ?? 0) === $pedidoId) $idx = $k;
        }
        if ($idx === null || (int) ($lista[$idx]['mentorId'] ?? 0) !== $mentorId) {
            return ['ok' => false, 'erro' => 'nao_encontrado'];
        }
        if (($lista[$idx]['status'] ?? '') !== 'pendente') return ['ok' => false, 'erro' => 'ja_respondido'];
        if ($aceitar && $aceitos >= mentoriaMax($mentor)) return ['ok' => false, 'erro' => 'sem_vaga'];

        $lista[$idx]['status']       = $aceitar ? 'aceito' : 'recusado';
        $lista[$idx]['resposta']     = $resposta;
        $lista[$idx]['respondidoTs'] = time();
        return ['ok' => true, 'pedido' => $lista[$idx]];
    });
    return is_array($res) ? $res : ['ok' => false, 'erro' => 'falha'];
}

/** Cancelar (so o aluno, pedido pendente) ou encerrar (aluno ou mentor, pedido aceito) */
function mentoriaMudarStatus(int $pedidoId, int $usuarioId, array $permitidos, string $para, bool $somenteAluno = false): array {
    $res = mentoriaOperar(function (array &$lista) use ($pedidoId, $usuarioId, $permitidos, $para, $somenteAluno) {
        foreach ($lista as $k => $p) {
            if ((int) ($p['id'] ?? 0) !== $pedidoId) continue;
            $ehAluno  = (int) ($p['alunoId'] ?? 0) === $usuarioId;
            $ehMentor = (int) ($p['mentorId'] ?? 0) === $usuarioId;
            if (!$ehAluno && !$ehMentor) return ['ok' => false, 'erro' => 'nao_encontrado'];
            if ($somenteAluno && !$ehAluno) return ['ok' => false, 'erro' => 'nao_encontrado'];
            if (!in_array($p['status'] ?? '', $permitidos, true)) return ['ok' => false, 'erro' => 'status'];
            $lista[$k]['status']      = $para;
            $lista[$k]['encerradoTs'] = time();
            return ['ok' => true, 'pedido' => $lista[$k]];
        }
        return ['ok' => false, 'erro' => 'nao_encontrado'];
    });
    return is_array($res) ? $res : ['ok' => false, 'erro' => 'falha'];
}

/** Quando o admin suspende um mentor, os pedidos que ainda aguardavam resposta sao cancelados */
function mentoriaCancelarPendentesDoMentor(int $mentorId, string $motivo): int {
    $n = mentoriaOperar(function (array &$lista) use ($mentorId, $motivo) {
        $c = 0;
        foreach ($lista as $k => $p) {
            if ((int) ($p['mentorId'] ?? 0) === $mentorId && ($p['status'] ?? '') === 'pendente') {
                $lista[$k]['status']      = 'cancelado';
                $lista[$k]['resposta']    = $motivo !== '' ? $motivo : 'Este mentor não está disponível no momento.';
                $lista[$k]['encerradoTs'] = time();
                $c++;
            }
        }
        return $c;
    });
    return (int) $n;
}

/* ------------------------------------------------------------------ */
/* E-mails (nunca derrubam a pagina: usam notificarEmail)               */
/* ------------------------------------------------------------------ */

function mentoriaLink(string $aba = ''): string {
    return appBaseUrl() . '/mentoria.php' . ($aba !== '' ? '?aba=' . $aba : '');
}

function mentoriaEmailNovoPedido(array $mentor, array $pedido): void {
    $email = trim((string) ($mentor['email'] ?? ''));
    if ($email === '') return;
    $curso = trim((string) ($pedido['alunoCurso'] ?? ''));
    notificarEmail($email, (string) ($mentor['nome'] ?? ''), 'Novo pedido de mentoria', 'Você recebeu um pedido de mentoria',
        '<p>Olá, <strong>' . emH($mentor['nome'] ?? 'egresso(a)') . '</strong>,</p>'
        . '<p><strong>' . emH($pedido['alunoNome'] ?? '') . '</strong>' . ($curso !== '' ? ' (' . emH($curso) . ')' : '')
        . ' quer conversar com você sobre: <strong>' . emH($pedido['assunto'] ?? '') . '</strong>.</p>'
        . '<p>Você decide se aceita. Seu contato só é compartilhado com o aluno se você aceitar.</p>',
        'Ver pedido', mentoriaLink('pedidos'));
}

function mentoriaEmailResposta(array $pedido, bool $aceito, array $mentor): void {
    $email = trim((string) ($pedido['alunoEmail'] ?? ''));
    if ($email === '') return;
    $nomeMentor = (string) ($pedido['mentorNome'] ?? ($mentor['nome'] ?? ''));
    $resposta = trim((string) ($pedido['resposta'] ?? ''));
    $extra = $resposta !== '' ? '<p style="background:#f3f6f9;padding:12px;border-radius:8px"><em>' . emH($resposta) . '</em></p>' : '';
    if ($aceito) {
        notificarEmail($email, (string) ($pedido['alunoNome'] ?? ''), 'Seu pedido de mentoria foi aceito', 'Pedido de mentoria aceito',
            '<p>Olá, <strong>' . emH($pedido['alunoNome'] ?? 'aluno(a)') . '</strong>,</p>'
            . '<p><strong>' . emH($nomeMentor) . '</strong> aceitou o seu pedido de mentoria.</p>'
            . '<p>O contato dessa pessoa já está disponível na aba "Meus pedidos".</p>' . $extra,
            'Ver contato', mentoriaLink('pedidos'));
    } else {
        notificarEmail($email, (string) ($pedido['alunoNome'] ?? ''), 'Sobre o seu pedido de mentoria', 'Pedido de mentoria não aceito',
            '<p>Olá, <strong>' . emH($pedido['alunoNome'] ?? 'aluno(a)') . '</strong>,</p>'
            . '<p>Desta vez <strong>' . emH($nomeMentor) . '</strong> não conseguiu aceitar o seu pedido. Isso costuma acontecer por falta de agenda.</p>'
            . '<p>Você pode pedir mentoria a outros egressos.</p>' . $extra,
            'Ver outros mentores', mentoriaLink('mentores'));
    }
}

/** tipo: aprovado | recusado | suspenso */
function mentoriaEmailMentorStatus(array $u, string $tipo, string $motivo = ''): void {
    $email = trim((string) ($u['email'] ?? ''));
    if ($email === '') return;
    $extra = $motivo !== '' ? '<p style="background:#f3f6f9;padding:12px;border-radius:8px"><em>' . emH($motivo) . '</em></p>' : '';
    $ola = '<p>Olá, <strong>' . emH($u['nome'] ?? 'egresso(a)') . '</strong>,</p>';
    if ($tipo === 'aprovado') {
        notificarEmail($email, (string) ($u['nome'] ?? ''), 'Você é mentor(a) da Central de Estágios', 'Cadastro de mentor aprovado',
            $ola . '<p>Seu cadastro como mentor(a) foi aprovado. Agora os alunos podem enviar pedidos de mentoria para você.</p>'
                 . '<p>Obrigado por ajudar quem está começando!</p>',
            'Abrir Mentoria', mentoriaLink('pedidos'));
    } elseif ($tipo === 'recusado') {
        notificarEmail($email, (string) ($u['nome'] ?? ''), 'Sobre o seu cadastro de mentor', 'Cadastro de mentor não aprovado',
            $ola . '<p>Seu cadastro como mentor(a) não foi aprovado desta vez. Você pode ajustar as informações e enviar de novo.</p>' . $extra,
            'Revisar cadastro', mentoriaLink('config'));
    } else {
        notificarEmail($email, (string) ($u['nome'] ?? ''), 'Seu cadastro de mentor foi suspenso', 'Cadastro de mentor suspenso',
            $ola . '<p>Seu cadastro como mentor(a) foi suspenso temporariamente. Em caso de dúvidas, fale com a coordenação.</p>' . $extra);
    }
}

/** Avisa os admins de egressos que ha um mentor novo para analisar */
function mentoriaNotificarAdminsNovoMentor(array $egresso): void {
    foreach (loadData('usuarios') as $u) {
        if (empty($u['is_admin']) || ($u['tipo'] ?? '') !== 'admin_egressos') continue;
        $email = trim((string) ($u['email'] ?? ''));
        if ($email === '') continue;
        notificarEmail($email, (string) ($u['nome'] ?? ''), 'Novo mentor para analisar', 'Mentor aguardando aprovação',
            '<p>O egresso <strong>' . emH($egresso['nome'] ?? '') . '</strong> quer ser mentor(a).</p>'
            . '<p>Ele só aparece para os alunos depois de aprovado.</p>',
            'Analisar agora', appBaseUrl() . '/admin-mentoria.php');
    }
}
