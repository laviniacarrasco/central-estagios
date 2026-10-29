<?php
/**
 * =============================================================================
 * INTEGRAÇÃO TOTVS — 4/4: SINCRONIZAÇÃO (aplica os dados na Central)
 * -----------------------------------------------------------------------------
 * Junta as outras três partes:
 *   1. busca os alunos no TOTVS          (totvs-cliente.php)
 *   2. traduz cada linha                 (totvs-mapeamento.php)
 *   3. compara com o usuarios.json e atualiza ou cria o aluno   (este arquivo)
 *
 * REGRAS DE SEGURANÇA (de propósito):
 *   - NUNCA apaga ninguém. Aluno que sumiu do TOTVS continua na Central.
 *   - NUNCA mexe em admin, egresso nem professor. Só em aluno.
 *   - NUNCA mexe na senha de quem já existe.
 *   - Só troca um campo se o TOTVS mandou um valor (campo vazio não apaga nada).
 *   - Antes de gravar, guarda uma cópia em data/usuarios_backup_totvs.json.
 *   - Tem o modo SIMULAR: mostra o que mudaria, sem gravar nada.
 *
 * Aluno novo é criado SEM senha. Para entrar, ele usa "Esqueci minha senha"
 * (que pede o CPF e o e-mail), então o e-mail precisa vir do TOTVS.
 * =============================================================================
 */

require_once __DIR__ . '/totvs-cliente.php';
require_once __DIR__ . '/totvs-mapeamento.php';

/** Campos que o TOTVS pode atualizar num aluno que já existe. */
function totvsCamposAtualizaveis(): array {
    return ['nome', 'email', 'curso', 'periodo', 'turno', 'situacao', 'ra', 'cpf'];
}

/**
 * O usuário é aluno? (aluno comum ou aluno restrito)
 * Admin, egresso e professor ficam de fora da sincronização.
 */
function totvsEhAluno(array $usuario): bool {
    $admin = !empty($usuario['is_admin'])
          && $usuario['is_admin'] !== 'false'
          && $usuario['is_admin'] !== 'False';   // no JSON antigo há "True"/"False" em texto

    $tipo = (string) ($usuario['tipo'] ?? '');
    return !$admin && ($tipo === '' || $tipo === 'aluno_restrito');
}

/** Acrescenta uma linha no relatório (limitado a 200 para não ficar gigante). */
function totvsDetalhe(array &$relatorio, string $tipo, string $texto): void {
    if (count($relatorio['detalhes']) < 200) {
        $relatorio['detalhes'][] = ['tipo' => $tipo, 'texto' => $texto];
    }
}

/**
 * Roda a sincronização.
 *   $simular = true  -> só calcula e devolve o relatório (NÃO grava)
 *   $simular = false -> grava as mudanças no usuarios.json
 *
 * Devolve um relatório com os totais e a lista do que aconteceu.
 */
function totvsSincronizar(bool $simular = true): array {
    $relatorio = [
        'quando'              => date('Y-m-d H:i:s'),
        'modo'                => totvsModo(),
        'simulacao'           => $simular,
        'ok'                  => false,
        'erro'                => '',
        'lidos'               => 0,   // linhas recebidas do TOTVS
        'criados'             => 0,   // alunos novos
        'atualizados'         => 0,   // alunos que tiveram algum campo trocado
        'sem_mudanca'         => 0,   // alunos que já estavam iguais
        'ignorados'           => 0,   // linhas que não deu para usar
        'cursosDesconhecidos' => [],  // cursos que não existem na Central
        'detalhes'            => [],
    ];

    /* ---------- 1) Busca no TOTVS ---------- */
    $busca = totvsBuscarAlunos();
    if (!$busca['ok']) {
        $relatorio['erro'] = $busca['erro'];
        totvsGuardarRelatorio($relatorio);
        return $relatorio;
    }

    /* ---------- 2) Prepara os usuários da Central ---------- */
    $usuarios = loadData('usuarios');
    $original = $usuarios;          // cópia intacta, para o backup

    // Índices para achar rápido um usuário pelo RA ou pelo CPF
    $porRa  = [];
    $porCpf = [];
    $maiorId = 0;

    foreach ($usuarios as $posicao => $u) {
        $maiorId = max($maiorId, (int) ($u['id'] ?? 0));

        $ra = trim((string) ($u['ra'] ?? ''));
        if ($ra !== '' && $ra !== '—') $porRa[$ra] = $posicao;

        $cpf = preg_replace('/\D/', '', (string) ($u['cpf'] ?? ''));
        if ($cpf !== '') $porCpf[$cpf] = $posicao;
    }

    $cursosOficiais = function_exists('getFsaCourses') ? getFsaCourses() : [];

    /* ---------- 3) Passa por cada aluno do TOTVS ---------- */
    foreach ($busca['linhas'] as $numero => $linha) {
        $relatorio['lidos']++;
        $numeroLinha = $numero + 1;   // para mostrar "linha 1" e não "linha 0"

        if (!is_array($linha)) {
            $relatorio['ignorados']++;
            totvsDetalhe($relatorio, 'ignorado', "Linha {$numeroLinha}: formato inválido.");
            continue;
        }

        $aluno = totvsConverterLinha($linha);
        if ($aluno === null) {
            $relatorio['ignorados']++;
            totvsDetalhe($relatorio, 'ignorado', "Linha {$numeroLinha}: sem RA e sem CPF.");
            continue;
        }

        // Curso que não existe na lista oficial: grava assim mesmo, mas avisa
        if ($aluno['curso'] !== '' && $cursosOficiais && !in_array($aluno['curso'], $cursosOficiais, true)) {
            $relatorio['cursosDesconhecidos'][$aluno['curso']] = true;
        }

        // Procura o aluno: primeiro pelo RA, depois pelo CPF
        $posicao = null;
        if ($aluno['ra'] !== '' && isset($porRa[$aluno['ra']])) {
            $posicao = $porRa[$aluno['ra']];
        } elseif ($aluno['cpf'] !== '' && isset($porCpf[$aluno['cpf']])) {
            $posicao = $porCpf[$aluno['cpf']];
        }

        $quem = ($aluno['nome'] !== '' ? $aluno['nome'] : 'Sem nome') . ' (RA ' . ($aluno['ra'] ?: '—') . ')';

        /* ----- 3a) Já existe: atualiza ----- */
        if ($posicao !== null) {

            if (!totvsEhAluno($usuarios[$posicao])) {
                $relatorio['ignorados']++;
                totvsDetalhe($relatorio, 'ignorado', "{$quem}: o cadastro na Central não é de aluno (admin, egresso ou professor).");
                continue;
            }

            $mudancas = [];
            foreach (totvsCamposAtualizaveis() as $campo) {
                $valorNovo = $aluno[$campo];
                if ($valorNovo === '') continue;   // TOTVS não mandou: não apaga o que já existe

                $valorAtual = (string) ($usuarios[$posicao][$campo] ?? '');
                if ($valorAtual !== $valorNovo) {
                    $mudancas[] = "{$campo}: \"{$valorAtual}\" → \"{$valorNovo}\"";
                    $usuarios[$posicao][$campo] = $valorNovo;
                }
            }

            if ($mudancas) {
                $usuarios[$posicao]['totvsAtualizadoEm'] = date('Y-m-d H:i:s');
                $relatorio['atualizados']++;
                totvsDetalhe($relatorio, 'atualizado', $quem . ': ' . implode('; ', $mudancas));
            } else {
                $relatorio['sem_mudanca']++;
            }
            continue;
        }

        /* ----- 3b) Não existe: cria um aluno novo ----- */
        if ($aluno['cpf'] === '') {
            // Sem CPF o aluno não consegue fazer login, então não criamos
            $relatorio['ignorados']++;
            totvsDetalhe($relatorio, 'ignorado', "{$quem}: aluno novo sem CPF, não dá para criar o login.");
            continue;
        }

        $maiorId++;
        $usuarios[] = [
            'id'                => $maiorId,
            'nome'              => $aluno['nome'],
            'cpf'               => $aluno['cpf'],
            'senha'             => '',          // sem senha: o aluno cria pelo "Esqueci minha senha"
            'is_admin'          => false,
            'email'             => $aluno['email'],
            'curso'             => $aluno['curso'],
            'ra'                => $aluno['ra'],
            'periodo'           => $aluno['periodo'],
            'turno'             => $aluno['turno'],
            'situacao'          => $aluno['situacao'] !== '' ? $aluno['situacao'] : 'Regular',
            'dataMatricula'     => null,
            'origem'            => 'totvs',     // marca que veio do TOTVS
            'totvsAtualizadoEm' => date('Y-m-d H:i:s'),
        ];

        // Atualiza os índices, para o mesmo aluno não ser criado duas vezes
        $novaPosicao = count($usuarios) - 1;
        if ($aluno['ra'] !== '') $porRa[$aluno['ra']] = $novaPosicao;
        $porCpf[$aluno['cpf']] = $novaPosicao;

        $relatorio['criados']++;
        $aviso = $aluno['email'] === '' ? ' Atenção: sem e-mail, ele não consegue criar a senha.' : '';
        totvsDetalhe($relatorio, 'criado', $quem . ': aluno novo.' . $aviso);
    }

    $relatorio['cursosDesconhecidos'] = array_keys($relatorio['cursosDesconhecidos']);
    $relatorio['ok'] = true;

    /* ---------- 4) Grava (só se não for simulação e se algo mudou) ---------- */
    if (!$simular && ($relatorio['criados'] + $relatorio['atualizados']) > 0) {
        saveData('usuarios_backup_totvs', $original);   // cópia de segurança antes de mudar
        saveData('usuarios', $usuarios);
    }

    totvsGuardarRelatorio($relatorio);
    return $relatorio;
}

/** Guarda o relatório da última execução (aparece na tela admin-totvs.php). */
function totvsGuardarRelatorio(array $relatorio): void {
    saveData('totvs_ultima_sincronizacao', $relatorio);
}
