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
    // Leitura e gravação com trava (arquivo ou banco: includes/armazenamento.php)
    return dadosOperar('mentoria_pedidos', function (array &$lista) use ($fn) {
        $resultado = $fn($lista);
        $lista = array_values($lista);
        return $resultado;
    });
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
    $d = dadosLer('users/' . $id . '/userProfile');
    $foto = $d['foto'] ?? null;
    if (!$foto || strpos((string) $foto, '..') !== false) return null;
    return is_file(__DIR__ . '/../' . $foto) ? (string) $foto : null;
}

/** Numero para a bolinha vermelha do menu (pedidos para o mentor / mentores para aprovar) */
function mentoriaPendentesParaMim(): int {
    if (empty($_SESSION['user_id'])) return 0;
    $userId = (int) $_SESSION['user_id'];

    // Egresso: pedidos aguardando resposta + mensagens novas no chat
    if (isEgresso()) {
        return mentoriaPendentesDoMentor($userId, mentoriaPedidos()) + mentoriaNaoLidasTotal($userId);
    }
    // Admin de egressos: mentores aguardando aprovação + denúncias novas
    if (isAdminEgressos()) {
        $n = 0;
        foreach (loadData('usuarios') as $u) {
            if (($u['tipo'] ?? '') === 'egresso' && ($u['mentoriaStatus'] ?? '') === 'pendente') $n++;
        }
        return $n + mentoriaDenunciasAbertas();
    }
    // Aluno: mensagens novas no chat
    if (empty($_SESSION['is_admin'])) {
        return mentoriaNaoLidasTotal($userId);
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

/* =============================================================================
 * CHAT DA MENTORIA
 * -----------------------------------------------------------------------------
 * As mensagens ficam DENTRO de cada pedido, no mentoria_pedidos.json:
 *
 *   "mensagens":  [ { "id": "...", "de": 12, "texto": "Oi!", "ts": 1761650000.123 }, ... ]
 *   "lidoAluno":  até quando o aluno já leu (timestamp)
 *   "lidoMentor": até quando o mentor já leu (timestamp)
 *
 * Regras:
 *   - só o aluno e o mentor daquele pedido podem ler e escrever;
 *   - dá para escrever enquanto a mentoria está "aceito" (em andamento);
 *   - depois de concluída, a conversa fica só para leitura;
 *   - mensagem que a pessoa ainda não leu vira a bolinha vermelha no ícone Mentoria.
 *
 * A tela conversa com estas funções pelo mentoria-chat.php (JSON).
 * ========================================================================== */

const MENTORIA_MSG_MAX       = 1000;  // caracteres por mensagem
const MENTORIA_MSG_GUARDADAS = 2000;   // histórico completo para a coordenação consultar   // mensagens guardadas por conversa (as mais antigas saem)

/** 'aluno', 'mentor' ou '' (quando a pessoa não faz parte do pedido) */
function mentoriaPapelNoPedido(array $p, int $userId): string {
    if ($userId > 0 && (int) ($p['alunoId'] ?? 0) === $userId)  return 'aluno';
    if ($userId > 0 && (int) ($p['mentorId'] ?? 0) === $userId) return 'mentor';
    return '';
}

/** Pode escrever? (mentoria em andamento) */
function mentoriaChatAberto(array $p): bool {
    return ($p['status'] ?? '') === 'aceito';
}

/** Pode ver a conversa? (em andamento ou já concluída) */
function mentoriaChatVisivel(array $p): bool {
    return in_array($p['status'] ?? '', ['aceito', 'concluido'], true);
}

/** Campo que guarda até quando cada lado já leu */
function mentoriaCampoLido(string $papel): string {
    return $papel === 'aluno' ? 'lidoAluno' : 'lidoMentor';
}

/** Quantas mensagens deste pedido o usuário ainda não leu */
function mentoriaNaoLidasPedido(array $p, int $userId): int {
    $papel = mentoriaPapelNoPedido($p, $userId);
    if ($papel === '' || !mentoriaChatVisivel($p)) return 0;

    $lidoAte = (float) ($p[mentoriaCampoLido($papel)] ?? 0);
    $n = 0;
    foreach ((array) ($p['mensagens'] ?? []) as $m) {
        // conta só o que a OUTRA pessoa mandou depois da última leitura
        if ((int) ($m['de'] ?? 0) !== $userId && (float) ($m['ts'] ?? 0) > $lidoAte) $n++;
    }
    return $n;
}

/** Total de mensagens não lidas do usuário, somando todas as conversas */
function mentoriaNaoLidasTotal(int $userId): int {
    $total = 0;
    foreach (mentoriaPedidos() as $p) {
        $total += mentoriaNaoLidasPedido($p, $userId);
    }
    return $total;
}

/** Busca um pedido pelo id (ou null) */
function mentoriaPedidoPorId(int $pedidoId): ?array {
    foreach (mentoriaPedidos() as $p) {
        if ((int) ($p['id'] ?? 0) === $pedidoId) return $p;
    }
    return null;
}

/**
 * Envia uma mensagem no chat.
 * Devolve ['ok' => true, 'mensagem' => [...]] ou ['ok' => false, 'erro' => 'motivo'].
 */
function mentoriaEnviarMensagem(int $pedidoId, int $userId, string $texto): array {
    $texto = trim(preg_replace("/\r\n?/", "\n", $texto));
    if ($texto === '') return ['ok' => false, 'erro' => 'vazia'];
    $texto = mb_substr($texto, 0, MENTORIA_MSG_MAX);

    $res = mentoriaOperar(function (array &$lista) use ($pedidoId, $userId, $texto) {
        foreach ($lista as $k => $p) {
            if ((int) ($p['id'] ?? 0) !== $pedidoId) continue;

            $papel = mentoriaPapelNoPedido($p, $userId);
            if ($papel === '')           return ['ok' => false, 'erro' => 'nao_encontrado'];
            if (!mentoriaChatAberto($p)) return ['ok' => false, 'erro' => 'fechado'];

            $agora = microtime(true);
            $msg = [
                'id'    => uniqid('m', true),
                'de'    => $userId,
                'texto' => $texto,
                'ts'    => $agora,
            ];

            $mensagens   = (array) ($p['mensagens'] ?? []);
            $mensagens[] = $msg;
            if (count($mensagens) > MENTORIA_MSG_GUARDADAS) {
                $mensagens = array_slice($mensagens, -MENTORIA_MSG_GUARDADAS);
            }

            $lista[$k]['mensagens']               = $mensagens;
            $lista[$k][mentoriaCampoLido($papel)] = $agora;   // quem mandou já "leu"
            $lista[$k]['ultimaMensagemTs']        = $agora;
            return ['ok' => true, 'mensagem' => $msg];
        }
        return ['ok' => false, 'erro' => 'nao_encontrado'];
    });

    return is_array($res) ? $res : ['ok' => false, 'erro' => 'falha'];
}

/** Marca a conversa como lida (só grava se havia algo não lido) */
function mentoriaMarcarLido(int $pedidoId, int $userId): void {
    $p = mentoriaPedidoPorId($pedidoId);
    if (!$p || mentoriaNaoLidasPedido($p, $userId) === 0) return;

    mentoriaOperar(function (array &$lista) use ($pedidoId, $userId) {
        foreach ($lista as $k => $p) {
            if ((int) ($p['id'] ?? 0) !== $pedidoId) continue;
            $papel = mentoriaPapelNoPedido($p, $userId);
            if ($papel !== '') $lista[$k][mentoriaCampoLido($papel)] = microtime(true);
            return true;
        }
        return false;
    });
}

/* ==================================================================== */
/* DENÚNCIAS (aluno ou mentor denuncia uma mensagem ou uma conversa)     */
/* -------------------------------------------------------------------- */
/* data/mentoria_denuncias.json. Quem denunciou fica em sigilo: a outra  */
/* pessoa não é avisada. A Coordenação de Egressos recebe e-mail e vê a   */
/* bolinha vermelha no ícone Mentoria (Admin).                           */
/* Status: aberta (nova) | em_analise | resolvida | arquivada            */
/* ==================================================================== */

require_once __DIR__ . '/notificacoes.php';

const MENTORIA_DENUNCIA_MAX_ABERTAS = 10;   // por pessoa, ao mesmo tempo (evita abuso)

function mentoriaDenunciaCategorias(): array {
    return [
        'assedio'    => 'Assédio ou constrangimento',
        'ofensa'     => 'Ofensa, preconceito ou discriminação',
        'golpe'      => 'Golpe, cobrança ou pedido de dinheiro',
        'dados'      => 'Pedido de dados pessoais ou senhas',
        'spam'       => 'Spam ou propaganda',
        'inadequado' => 'Conteúdo inadequado',
        'outro'      => 'Outro motivo',
    ];
}

function mentoriaDenunciaStatusInfo($status): array {
    switch ($status) {
        case 'em_analise': return ['Em análise', 'bg-amber-50 text-amber-700 border-amber-200', 'fa-search'];
        case 'resolvida':  return ['Resolvida', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-check-circle'];
        case 'arquivada':  return ['Arquivada', 'bg-gray-100 text-gray-600 border-gray-200', 'fa-archive'];
        default:           return ['Nova', 'bg-red-50 text-red-700 border-red-200', 'fa-flag'];
    }
}

function mentoriaDenuncias(): array {
    $d = loadData('mentoria_denuncias');
    return is_array($d) ? array_values($d) : [];
}

/** Denúncias novas, ainda não abertas pela coordenação (bolinha vermelha) */
function mentoriaDenunciasAbertas(): int {
    $n = 0;
    foreach (mentoriaDenuncias() as $d) if (($d['status'] ?? 'aberta') === 'aberta') $n++;
    return $n;
}

/** IDs das mensagens já denunciadas (para marcar no log) */
function mentoriaMensagensDenunciadas(): array {
    $ids = [];
    foreach (mentoriaDenuncias() as $d) if (!empty($d['mensagemId'])) $ids[(string) $d['mensagemId']] = true;
    return $ids;
}

/**
 * Cria a denúncia. $mensagemId vazio = denúncia da conversa inteira.
 * Devolve ['ok' => true, 'denuncia' => [...]] ou ['ok' => false, 'erro' => chave].
 */
function mentoriaCriarDenuncia(int $userId, int $pedidoId, string $categoria, string $descricao, string $mensagemId = ''): array {
    $p = mentoriaPedidoPorId($pedidoId);
    $papel = $p ? mentoriaPapelNoPedido($p, $userId) : '';
    if (!$p || $papel === '') return ['ok' => false, 'erro' => 'nao_encontrado'];

    $cats = mentoriaDenunciaCategorias();
    if (!isset($cats[$categoria])) $categoria = 'outro';
    $descricao = trim(preg_replace("/\r\n?/", "\n", $descricao));
    if (mb_strlen($descricao) < JUSTIFICATIVA_MIN) return ['ok' => false, 'erro' => 'descricao'];
    $descricao = mb_substr($descricao, 0, 1000);

    // Mensagem denunciada: precisa ser da OUTRA pessoa. Guarda uma cópia do texto.
    $msgTexto = ''; $msgTs = 0;
    if ($mensagemId !== '') {
        foreach ((array) ($p['mensagens'] ?? []) as $m) {
            if ((string) ($m['id'] ?? '') !== $mensagemId) continue;
            if ((int) ($m['de'] ?? 0) === $userId) return ['ok' => false, 'erro' => 'mensagem'];
            $msgTexto = (string) ($m['texto'] ?? '');
            $msgTs    = (float) ($m['ts'] ?? 0);
        }
        if ($msgTexto === '') return ['ok' => false, 'erro' => 'mensagem'];
    }

    $souAluno = $papel === 'aluno';
    $nova = [
        'pedidoId'        => $pedidoId,
        'assunto'         => (string) ($p['assunto'] ?? ''),
        'denuncianteId'   => $userId,
        'denuncianteNome' => (string) ($souAluno ? ($p['alunoNome'] ?? '') : ($p['mentorNome'] ?? '')),
        'denunciantePapel'=> $souAluno ? 'Aluno' : 'Mentor',
        'denunciadoId'    => (int) ($souAluno ? ($p['mentorId'] ?? 0) : ($p['alunoId'] ?? 0)),
        'denunciadoNome'  => (string) ($souAluno ? ($p['mentorNome'] ?? '') : ($p['alunoNome'] ?? '')),
        'denunciadoPapel' => $souAluno ? 'Mentor' : 'Aluno',
        'categoria'       => $categoria,
        'descricao'       => $descricao,
        'mensagemId'      => $mensagemId,
        'mensagemTexto'   => $msgTexto,
        'mensagemTs'      => $msgTs,
        'status'          => 'aberta',
        'resposta'        => '',
        'criadoEm'        => date('d/m/Y H:i'),
        'criadoTs'        => time(),
        'historico'       => [],
    ];

    $r = dadosOperar('mentoria_denuncias', function (array &$lista) use ($nova, $userId, $mensagemId, $pedidoId) {
        $abertas = 0; $maxId = 0;
        foreach ($lista as $d) {
            $maxId = max($maxId, (int) ($d['id'] ?? 0));
            $ativa = in_array($d['status'] ?? 'aberta', ['aberta', 'em_analise'], true);
            if ((int) ($d['denuncianteId'] ?? 0) !== $userId || !$ativa) continue;
            $abertas++;
            if ((int) ($d['pedidoId'] ?? 0) === $pedidoId && (string) ($d['mensagemId'] ?? '') === $mensagemId) return ['ok' => false, 'erro' => 'duplicada'];
        }
        if ($abertas >= MENTORIA_DENUNCIA_MAX_ABERTAS) return ['ok' => false, 'erro' => 'limite'];
        $nova['id'] = $maxId + 1;
        $lista[] = $nova;
        return ['ok' => true, 'denuncia' => $nova];
    });
    if (!is_array($r)) return ['ok' => false, 'erro' => 'falha'];
    if (!empty($r['ok'])) mentoriaEmailDenuncia($r['denuncia']);
    return $r;
}

/** Texto do erro para mostrar na tela */
function mentoriaDenunciaErro(string $chave): string {
    $t = [
        'nao_encontrado' => 'Conversa não encontrada.',
        'descricao'      => 'Conte o que aconteceu (pelo menos ' . JUSTIFICATIVA_MIN . ' caracteres).',
        'mensagem'       => 'Só dá para denunciar mensagens da outra pessoa.',
        'duplicada'      => 'Você já denunciou isso. A coordenação está analisando.',
        'limite'         => 'Você já tem ' . MENTORIA_DENUNCIA_MAX_ABERTAS . ' denúncias em análise. Aguarde a coordenação.',
        'falha'          => 'Não foi possível enviar agora. Tente de novo.',
    ];
    return $t[$chave] ?? $t['falha'];
}

/** E-mails da Coordenação de Egressos */
function mentoriaAdminsEgressos(): array {
    $out = [];
    foreach (loadData('usuarios') as $u) {
        if (empty($u['is_admin']) || ($u['tipo'] ?? '') !== 'admin_egressos') continue;
        $email = trim((string) ($u['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $out[$email] = [$email, (string) ($u['nome'] ?? '')];
    }
    return array_values($out);
}

/** Avisa a Coordenação de Egressos de uma denúncia nova */
function mentoriaEmailDenuncia(array $d): void {
    try {
        $cat = mentoriaDenunciaCategorias()[$d['categoria'] ?? 'outro'] ?? 'Outro motivo';
        $msg = trim((string) ($d['mensagemTexto'] ?? ''));
        $html = '<p>Chegou uma <strong>denúncia na mentoria</strong>:</p>'
              . '<ul style="margin:0 0 12px 18px;padding:0">'
              . '<li><strong>Motivo:</strong> ' . emH($cat) . '</li>'
              . '<li><strong>Quem denunciou:</strong> ' . emH($d['denuncianteNome'] ?? '') . ' (' . emH($d['denunciantePapel'] ?? '') . ')</li>'
              . '<li><strong>Denunciado:</strong> ' . emH($d['denunciadoNome'] ?? '') . ' (' . emH($d['denunciadoPapel'] ?? '') . ')</li>'
              . '<li><strong>Conversa:</strong> pedido #' . (int) ($d['pedidoId'] ?? 0) . ' &mdash; ' . emH($d['assunto'] ?? '') . '</li>'
              . '</ul>'
              . ($msg !== '' ? '<p style="background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px"><strong>Mensagem denunciada:</strong><br>' . nl2br(emH($msg)) . '</p>' : '<p>A denúncia é sobre a conversa inteira.</p>')
              . '<p><strong>Relato:</strong><br>' . nl2br(emH($d['descricao'] ?? '')) . '</p>'
              . '<p style="color:#667085;font-size:12px">A pessoa denunciada não é avisada. Trate as informações com sigilo.</p>';
        foreach (mentoriaAdminsEgressos() as [$email, $nome]) {
            notificarEmail($email, $nome, 'Nova denúncia na mentoria (#' . (int) ($d['id'] ?? 0) . ')', 'Denúncia na mentoria', $html,
                'Analisar denúncia', appBaseUrl() . '/admin-mentoria.php?tab=denuncias');
        }
    } catch (Throwable $e) {
        error_log('mentoriaEmailDenuncia: ' . $e->getMessage());
    }
}

/** Avisa quem denunciou que a coordenação concluiu a análise */
function mentoriaEmailDenunciaConcluida(array $d): void {
    try {
        $email = notificacoesEmailDoUsuario((int) ($d['denuncianteId'] ?? 0));
        if (!$email) return;
        [$st] = mentoriaDenunciaStatusInfo($d['status'] ?? '');
        notificarEmail($email, (string) ($d['denuncianteNome'] ?? ''), 'Sua denúncia foi analisada', 'Denúncia analisada',
            '<p>Olá, <strong>' . emH($d['denuncianteNome'] ?? '') . '</strong>,</p>'
            . '<p>A Coordenação de Egressos analisou a denúncia que você fez na mentoria (situação: <strong>' . emH($st) . '</strong>).</p>'
            . '<p style="background:#f2f4f7;border-left:4px solid #4A9FCA;padding:10px 14px"><strong>Resposta da coordenação:</strong><br>' . nl2br(emH($d['resposta'] ?? '')) . '</p>'
            . '<p>Obrigado por nos avisar.</p>',
            'Ver minhas denúncias', appBaseUrl() . '/mentoria.php?aba=denuncias');
    } catch (Throwable $e) {
        error_log('mentoriaEmailDenunciaConcluida: ' . $e->getMessage());
    }
}
