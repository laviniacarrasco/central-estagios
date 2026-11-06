<?php
/**
 * =============================================================================
 * RESERVA DAS INTEGRAÇÕES — cópia em JSON do TOTVS e do banco / data lake
 * -----------------------------------------------------------------------------
 * O banco da Central já tem os JSON como reserva (includes/armazenamento.php).
 * Este arquivo faz o mesmo para o que vem DE FORA:
 *
 *   - TOTVS (modo "real" ou "banco"): tabela "TOTVS: alunos" dos Painéis e
 *     relatórios com origem TOTVS
 *   - Banco / data lake (BI_SQL): consultas SQL dos Painéis e Relatórios
 *
 * Como funciona:
 *   1. Toda leitura que dá certo é guardada em data/reserva/<nome>.json
 *      (no máximo a cada RESERVA_INTERVALO segundos, padrão 600 = 10 min).
 *      Com o banco da Central ligado, a cópia vai também para a tabela
 *      central_reservas, então ela sobrevive aos deploys do Render.
 *   2. Se o TOTVS ou o banco externo não responder, os Painéis e Relatórios
 *      usam essa cópia e mostram um aviso amarelo com a data dela.
 *   3. Se ainda não existir cópia, aparece o erro normal.
 *
 * O QUE NÃO USA A RESERVA (de propósito):
 *   - A sincronização de alunos (Cadastro de Alunos / TOTVS): ela grava no
 *     cadastro, e gravar dados velhos por cima dos atuais seria pior do que
 *     esperar o TOTVS voltar.
 *   - O botão "testar consulta" ao criar uma consulta SQL.
 *
 * Privacidade: colunas com "cpf" no nome NÃO são guardadas na reserva.
 * Os arquivos ficam em data/reserva/ (bloqueados no navegador pelo router.php).
 * Documentação: docs/BANCO-DE-DADOS.md, seção "Reserva das integrações".
 * =============================================================================
 */

/** Chave da reserva: reserva/<nome> */
function reservaChave(string $nome): string {
    return 'reserva/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $nome);
}

/** Avisos desta página ("usando a cópia de ...") para mostrar na tela */
function reservaAvisos(?string $novo = null): array {
    static $avisos = [];
    if ($novo !== null && !in_array($novo, $avisos, true)) $avisos[] = $novo;
    return $avisos;
}

/** Tira as colunas de CPF (não guardamos CPF na reserva) */
function reservaSemCpf(array $linhas): array {
    $saida = [];
    foreach ($linhas as $l) {
        if (is_array($l)) {
            foreach (array_keys($l) as $k) if (stripos((string) $k, 'cpf') !== false) unset($l[$k]);
        }
        $saida[] = $l;
    }
    return $saida;
}

/**
 * Guarda a cópia de uma leitura que deu certo.
 * $assinatura: identifica a consulta (se a consulta mudar, a cópia antiga não vale mais).
 */
function reservaGuardar(string $nome, string $rotulo, array $linhas, string $assinatura = ''): void {
    $chave = reservaChave($nome);
    $intervalo = max(0, (int) bancoEnv('RESERVA_INTERVALO', '600'));
    $arq = dadosJsonCaminho($chave);
    // Não regrava a cada página: só se a cópia tiver mais de RESERVA_INTERVALO segundos
    if ($intervalo > 0 && is_file($arq) && (time() - filemtime($arq)) < $intervalo) {
        $atual = json_decode((string) @file_get_contents($arq), true);
        if (is_array($atual) && ($atual['assinatura'] ?? '') === $assinatura) return;
    }
    try {
        dadosGravar($chave, [
            'rotulo'     => $rotulo,
            'assinatura' => $assinatura,
            'quando'     => date('Y-m-d H:i:s'),
            'total'      => count($linhas),
            'linhas'     => array_values(reservaSemCpf($linhas)),
        ]);
    } catch (Throwable $e) {
        error_log('Reserva ' . $nome . ': não foi possível guardar a cópia: ' . $e->getMessage());
    }
}

/** Lê a cópia (null se não existir ou se for de outra consulta) */
function reservaLer(string $nome, string $assinatura = ''): ?array {
    $d = dadosLer(reservaChave($nome));
    if (!isset($d['linhas']) || !is_array($d['linhas'])) return null;
    if ($assinatura !== '' && ($d['assinatura'] ?? '') !== $assinatura) return null;
    return $d;
}

/**
 * A origem não respondeu: devolve as linhas da cópia e registra o aviso.
 * Se não houver cópia, lança o erro original.
 */
function reservaUsar(string $nome, string $rotulo, string $erro, string $assinatura = ''): array {
    $d = reservaLer($nome, $assinatura);
    if (!$d) {
        throw new RuntimeException($rotulo . ' não respondeu e ainda não existe cópia de reserva. Erro: ' . $erro);
    }
    $quando = strtotime((string) ($d['quando'] ?? '')) ?: time();
    reservaAvisos($rotulo . ' não respondeu. Mostrando a cópia de reserva de ' . date('d/m/Y', $quando) . ' às ' . date('H:i', $quando) . '.');
    error_log('Reserva ' . $nome . ' em uso: ' . $erro);
    return (array) $d['linhas'];
}

/** Roda um SELECT externo guardando a cópia; se falhar, usa a cópia. */
function bancoSelecionarComReserva(string $prefixo, string $sql, int $limite, string $nome, string $rotulo): array {
    if (!bancoConsultaSegura($sql)) throw new RuntimeException('Só é permitido um comando SELECT (somente leitura).');
    $assinatura = md5($prefixo . '|' . $sql . '|' . $limite);
    try {
        $linhas = bancoSelecionar($prefixo, $sql, $limite);
    } catch (Throwable $e) {
        return reservaUsar($nome, $rotulo, $e->getMessage(), $assinatura);
    }
    reservaGuardar($nome, $rotulo, $linhas, $assinatura);
    return $linhas;
}

/** Lista as cópias existentes (tela Banco de dados) */
function reservaListar(): array {
    $lista = [];
    $chaves = dadosBancoDisponivel() ? dadosBancoListarChaves() : dadosJsonListarChaves();
    foreach ($chaves as $c) {
        if (strpos($c, 'reserva/') !== 0) continue;
        $d = dadosLer($c);
        $lista[] = ['nome' => substr($c, 8), 'rotulo' => (string) ($d['rotulo'] ?? ''), 'quando' => (string) ($d['quando'] ?? ''), 'total' => (int) ($d['total'] ?? 0)];
    }
    return $lista;
}
