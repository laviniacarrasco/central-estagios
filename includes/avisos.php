<?php
/**
 * =============================================================================
 * AVISOS: bolinha vermelha no menu + e-mails de "chegou algo para voce"
 * -----------------------------------------------------------------------------
 * Bolinhas (contadores calculados na hora, sem depender de cron):
 *   admin_jobs          admin comum: solicitacoes de vaga dos alunos aguardando analise
 *   admin_assinaturas   admin: assinaturas solicitadas e ainda nao atendidas
 *   admin_egressos      admin de egressos: vagas de egressos aguardando analise
 *   aluno_assinaturas   aluno: documento devolvido (assinado ou com erro) que ele ainda nao viu
 *   mentoria            egresso: pedidos para responder / admin de egressos: mentores para aprovar
 *
 * E-mails (nunca derrubam a pagina: usam notificarEmail):
 *   aluno pede vaga          -> todos os admins
 *   aluno envia assinatura   -> todos os admins
 *   admin devolve assinado   -> aluno
 *   admin marca erro         -> aluno
 * =============================================================================
 */

require_once __DIR__ . '/notificacoes.php';

/* ------------------------------------------------------------------ */
/* Bolinhas                                                            */
/* ------------------------------------------------------------------ */

function avisosContagens(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $c = [
        'admin_jobs'        => 0,
        'admin_assinaturas' => 0,
        'admin_egressos'    => 0,
        'aluno_assinaturas' => 0,
        'mentoria'          => 0,
    ];
    $isAdmin   = !empty($_SESSION['is_admin']);
    $isEgresso = !empty($_SESSION['is_egresso']);
    $userId    = (int) ($_SESSION['user_id'] ?? 0);

    try {
        if ($isAdmin) {
            foreach (loadData('job_requests') as $r) {
                if (($r['status'] ?? '') === 'pendente') $c['admin_jobs']++;
            }
            foreach (loadData('assinaturas') as $s) {
                if (($s['status'] ?? '') === 'solicitado') $c['admin_assinaturas']++;
            }
            if (!empty($_SESSION['is_admin_egressos'])) {
                foreach (loadData('egresso_jobs') as $v) {
                    if (($v['status'] ?? 'pendente') === 'pendente') $c['admin_egressos']++;
                }
            }
        } elseif (!$isEgresso) {
            foreach (loadData('assinaturas') as $s) {
                if ((int) ($s['userId'] ?? 0) === $userId && !empty($s['avisoAluno'])) $c['aluno_assinaturas']++;
            }
        }
    } catch (Throwable $e) {
        error_log('avisosContagens: ' . $e->getMessage());
    }

    try {
        require_once __DIR__ . '/mentoria.php';
        $c['mentoria'] = (int) mentoriaPendentesParaMim();
    } catch (Throwable $e) {
        error_log('avisosContagens (mentoria): ' . $e->getMessage());
    }

    $cache = $c;
    return $cache;
}

/** HTML da bolinha vermelha (vazio quando nao ha nada) */
function avisosBadge(string $chave): string {
    $n = (int) (avisosContagens()[$chave] ?? 0);
    if ($n <= 0) return '';
    return '<span class="sidebar-badge">' . ($n > 99 ? '99+' : $n) . '</span>';
}

/* ------------------------------------------------------------------ */
/* Apoio                                                               */
/* ------------------------------------------------------------------ */

function avisosAssinaturaLabel(array $sol): string {
    $tipo = $sol['tipo'] ?? '';
    if ($tipo === 'contrato_estagio') {
        $area = $sol['area'] ?? null;
        $txt  = $area === 'dentro' ? ' (dentro da área)' : ($area === 'fora' ? ' (fora da área)' : '');
        return 'Contrato de Estágio' . $txt;
    }
    if ($tipo === 'fim_estagio') return 'Fim de Estágio';
    return (string) $tipo;
}

/** [[email, nome], ...] de todos os admins com e-mail valido */
function avisosAdmins(): array {
    $out = [];
    foreach (loadData('usuarios') as $u) {
        if (empty($u['is_admin'])) continue;
        $email = trim((string) ($u['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        $out[$email] = [$email, (string) ($u['nome'] ?? '')];
    }
    return array_values($out);
}

/* ------------------------------------------------------------------ */
/* E-mails para os admins                                              */
/* ------------------------------------------------------------------ */

/** Aluno enviou (ou reenviou apos rejeicao) uma solicitacao de vaga */
function avisosNotificarAdminsSolicitacaoVaga(array $req, bool $reenvio = false): void {
    try {
        $nome = (string) ($req['studentName'] ?? 'Um aluno');
        $assunto = $reenvio ? 'Solicitação de vaga reenviada para análise' : 'Nova solicitação de vaga para analisar';
        $titulo  = $reenvio ? 'Vaga reenviada para análise' : 'Vaga aguardando análise';
        foreach (avisosAdmins() as [$email, $nomeAdmin]) {
            notificarEmail($email, $nomeAdmin, $assunto, $titulo,
                '<p>O aluno <strong>' . emH($nome) . '</strong> ' . ($reenvio ? 'corrigiu e reenviou' : 'enviou') . ' uma solicitação de vaga:</p>'
                . '<p><strong>' . emH($req['title'] ?? '') . '</strong> &mdash; ' . emH($req['company'] ?? '') . '</p>'
                . '<p>Ela só aparece para os alunos depois de aprovada.</p>',
                'Analisar agora', appBaseUrl() . '/admin-jobs.php');
        }
    } catch (Throwable $e) {
        error_log('avisosNotificarAdminsSolicitacaoVaga: ' . $e->getMessage());
    }
}

/** Aluno enviou documentos para assinar ($solicitacoes = lista de registros de assinaturas.json) */
function avisosNotificarAdminsAssinatura(array $solicitacoes, array $aluno, bool $reenvio = false): void {
    try {
        if (empty($solicitacoes)) return;
        $nome = (string) ($aluno['nome'] ?? 'Um aluno');
        $itens = '';
        foreach ($solicitacoes as $s) {
            $itens .= '<li>' . emH(avisosAssinaturaLabel($s)) . '</li>';
        }
        $qtd = count($solicitacoes);
        $assunto = $reenvio ? 'Documento reenviado para assinatura' : ($qtd > 1 ? 'Novos documentos para assinar' : 'Novo documento para assinar');
        foreach (avisosAdmins() as [$email, $nomeAdmin]) {
            notificarEmail($email, $nomeAdmin, $assunto, $assunto,
                '<p>O aluno <strong>' . emH($nome) . '</strong> ' . ($reenvio ? 'reenviou' : 'enviou') . ' para assinatura:</p>'
                . '<ul style="margin:0 0 12px 18px;padding:0">' . $itens . '</ul>',
                'Abrir assinaturas', appBaseUrl() . '/admin-signature.php');
        }
    } catch (Throwable $e) {
        error_log('avisosNotificarAdminsAssinatura: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------ */
/* E-mail para o aluno                                                 */
/* ------------------------------------------------------------------ */

/** $tipo: 'assinado' (documento devolvido) ou 'erro' (precisa de ajuste) */
function avisosNotificarAlunoAssinatura(array $sol, array $aluno, string $tipo): void {
    try {
        $email = trim((string) ($aluno['email'] ?? ''));
        if ($email === '') return;
        $nome = (string) ($aluno['nome'] ?? '');
        $ola  = '<p>Olá, <strong>' . emH($nome !== '' ? $nome : 'aluno(a)') . '</strong>,</p>';
        $doc  = '<strong>' . emH(avisosAssinaturaLabel($sol)) . '</strong>';
        $url  = appBaseUrl() . '/signature.php';

        if ($tipo === 'assinado') {
            notificarEmail($email, $nome, 'Seu documento foi assinado', 'Documento assinado',
                $ola . '<p>A coordenação assinou o seu documento: ' . $doc . '.</p>'
                     . '<p>O arquivo assinado já está disponível para baixar na página de Assinaturas.</p>',
                'Baixar documento', $url);
            return;
        }

        $motivo = trim((string) ($sol['mensagemErro'] ?? ''));
        notificarEmail($email, $nome, 'Seu documento precisa de ajuste', 'Documento com problema',
            $ola . '<p>Encontramos um problema no documento: ' . $doc . '.</p>'
                 . '<p style="background:#fef2f2;border-left:4px solid #ef4444;padding:10px 14px"><strong>Motivo:</strong> '
                 . emH($motivo !== '' ? $motivo : 'Houve um problema com os arquivos enviados.') . '</p>'
                 . '<p>Corrija o arquivo e reenvie pela página de Assinaturas.</p>',
            'Reenviar documento', $url);
    } catch (Throwable $e) {
        error_log('avisosNotificarAlunoAssinatura: ' . $e->getMessage());
    }
}
