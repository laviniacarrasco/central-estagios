<?php
/**
 * =============================================================================
 * PAINÉIS (estilo Salesforce + Power BI) — só funcionários
 * -----------------------------------------------------------------------------
 * Um lugar só para todos os indicadores. Substitui o Dashboard Admin (gráficos),
 * os Painéis BI, o Funil e Indicadores e a aba Painel do Painel de Egressos.
 *
 * TELA INICIAL: pastas à esquerda (Alunos, Vagas e Funil, Egressos, as pastas
 *   criadas e "Meus painéis particulares") e a lista de painéis à direita, com
 *   "Novo painel" e "Nova pasta". A aba Relatórios leva às exportações.
 * PAINEL: os visuais (cartões, barras, colunas, linha, pizza, rosca, tabela e
 *   funil). Clicar num gráfico filtra os outros. "Editar painel" abre o painel
 *   lateral de Visualizações; tudo é salvo sozinho.
 *
 * Os dados vêm de bi-dados.php; as contas são feitas aqui, no navegador.
 * Pastas e painéis ficam em data/bi/pastas.json e data/bi/paineis.json.
 * =============================================================================
 */
require_once 'includes/config.php';
checkAuth();
if (!perfilPode('gestao')) { header('Location: dashboard.php'); exit; }

$pageTitle    = 'Painéis';
$podeEgressos = perfilPode('gestao_egressos');
include 'includes/header.php';
?>
<style>
    .bi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px; }
    @media (max-width: 1100px) { .bi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px)  { .bi-grid { grid-template-columns: 1fr; } .bi-card { grid-column: span 1 !important; } }
    .bi-card { background: #fff; border: 1px solid #eef0f3; border-radius: 16px; padding: 18px; box-shadow: 0 1px 2px rgba(0,0,0,.04); position: relative; min-width: 0; }
    .bi-editando .bi-card { cursor: pointer; border-style: dashed; }
    .bi-card.bi-sel { outline: 3px solid #4A9FCA; outline-offset: 2px; }
    .bi-card.bi-arrastando { opacity: .4; }
    .bi-grafico { position: relative; height: 260px; }
    .bi-campo { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13px; background: #fff; }
    .bi-rotulo { display: block; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin: 12px 0 4px; }
    .bi-tipo { width: 38px; height: 38px; border: 1px solid #e5e7eb; border-radius: 10px; color: #6b7280; }
    .bi-tipo.ativo { background: #4A9FCA; color: #fff; border-color: #4A9FCA; }
    .bi-pasta { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 12px; border-radius: 10px; font-size: 14px; color: #475467; text-align: left; }
    .bi-pasta:hover { background: #f2f4f7; }
    .bi-pasta.ativa { background: #4A9FCA1a; color: #1f6f96; font-weight: 600; }
    .bi-aba { padding: 12px 18px; font-size: 14px; font-weight: 600; border-bottom: 2px solid transparent; margin-bottom: -1px; color: #667085; }
    .bi-aba.ativa { border-color: #4A9FCA; color: #2B7FA6; }
    .bi-btn { height: 40px; padding: 0 16px; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #e5e7eb; border-radius: 12px; font-size: 14px; color: #475467; background: #fff; }
    .bi-btn:hover { background: #f9fafb; }
    .bi-btn-azul { background: #4A9FCA; border-color: #4A9FCA; color: #fff; font-weight: 600; }
    .bi-btn-azul:hover { background: #3A8FB0; }
    .mp-con.ativa { border-color: #4A9FCA; background: #4A9FCA1a; color: #1f6f96; }
    .rl-origem.ativa { background: #4A9FCA; color: #fff; border-color: #4A9FCA; }
    @media print { .sidebar, header, #biBarra, #biEditor, #biSegmentacao { display: none !important; } main { margin: 0 !important; padding: 0 !important; } }
</style>

<main class="ml-16 pt-16">
<div class="p-6 max-w-[1600px] mx-auto" id="biRaiz">

    <!-- ======================= TELA INICIAL ======================= -->
    <section id="vInicio">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div>
                <h2 class="text-3xl font-bold text-slate-900">Painéis</h2>
                <p class="text-slate-500 text-sm">Todos os indicadores da Central num lugar só. Abra um painel, crie o seu ou organize em pastas.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="bi-btn" onclick="biModalPasta()"><i class="fas fa-folder-plus"></i>Nova pasta</button>
                <button type="button" class="bi-btn" onclick="relNovo()"><i class="fas fa-table"></i>Novo relatório</button>
                <button type="button" class="bi-btn bi-btn-azul" onclick="biModalPainel()"><i class="fas fa-plus"></i>Novo painel</button>
            </div>
        </div>

        <div class="flex gap-1 border-b border-gray-200 mb-6">
            <button type="button" class="bi-aba ativa" data-aba="paineis" onclick="biAba('paineis')"><i class="fas fa-chart-pie mr-1"></i>Painéis</button>
            <button type="button" class="bi-aba" data-aba="relatorios" onclick="biAba('relatorios')"><i class="fas fa-table mr-1"></i>Relatórios</button>
        </div>

        <!-- Aba Painéis -->
        <div id="abaPaineis" class="flex flex-col lg:flex-row gap-6 items-start">
            <aside class="w-full lg:w-64 flex-shrink-0 bg-white border border-gray-100 rounded-2xl p-3">
                <p class="px-3 pt-1 pb-2 text-xs font-bold text-gray-400 uppercase">Painéis</p>
                <div id="biAtalhos" class="space-y-0.5"></div>
                <p class="px-3 pt-4 pb-2 text-xs font-bold text-gray-400 uppercase">Pastas</p>
                <div id="biPastas" class="space-y-0.5"></div>
            </aside>

            <div class="flex-1 min-w-0 w-full">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div>
                        <h3 id="biTituloLista" class="text-lg font-bold text-slate-900">Todos os painéis</h3>
                        <p id="biContLista" class="text-xs text-gray-400"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="biAcoesPasta" class="hidden flex gap-2">
                            <button type="button" class="bi-btn" onclick="biRenomearPasta()"><i class="fas fa-pen"></i>Renomear pasta</button>
                            <button type="button" class="bi-btn text-red-600" onclick="biExcluirPasta()"><i class="fas fa-trash-alt"></i>Apagar pasta</button>
                        </span>
                        <input id="biBusca" class="bi-campo" style="width:240px" placeholder="Buscar painel...">
                    </div>
                </div>
                <div class="bg-white border border-gray-100 rounded-2xl overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-gray-400 border-b bg-gray-50">
                            <th class="p-3">Nome do painel</th><th class="p-3">Pasta</th><th class="p-3">Criado por</th><th class="p-3">Última alteração</th><th class="p-3 text-right">Ações</th>
                        </tr></thead>
                        <tbody id="biLista"><tr><td colspan="5" class="p-8 text-center text-gray-400">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Aba Relatórios (estilo Salesforce) -->
        <div id="abaRelatorios" class="hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Relatórios</h3>
                    <p class="text-xs text-gray-400">Cada relatório lê uma origem: <strong>TOTVS</strong>, uma <strong>planilha Excel</strong> ou um <strong>banco de dados / data lake</strong>.
                        Depois de salvo, ele pode ser usado como fonte em qualquer painel.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input id="relBusca" class="bi-campo" style="width:220px" placeholder="Buscar relatório...">
                    <button type="button" class="bi-btn bi-btn-azul" onclick="relNovo()"><i class="fas fa-plus"></i>Novo relatório</button>
                </div>
            </div>
            <div class="bg-white border border-gray-100 rounded-2xl overflow-x-auto mb-8">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-400 border-b bg-gray-50">
                        <th class="p-3">Nome do relatório</th><th class="p-3">Origem</th><th class="p-3">Pasta</th><th class="p-3">Criado por</th><th class="p-3">Última alteração</th><th class="p-3 text-right">Ações</th>
                    </tr></thead>
                    <tbody id="relLista"><tr><td colspan="6" class="p-8 text-center text-gray-400">Carregando...</td></tr></tbody>
                </table>
            </div>

            <h3 class="text-sm font-bold text-gray-400 uppercase mb-3">Exportações prontas</h3>
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php if ($podeEgressos): ?>
            <a href="admin-relatorios.php" class="block bg-white border border-gray-100 rounded-2xl p-6 hover:border-[#4A9FCA]">
                <i class="fas fa-user-graduate text-2xl text-[#4A9FCA] mb-3"></i>
                <h4 class="font-bold text-slate-900 mb-1">Empregabilidade dos egressos</h4>
                <p class="text-sm text-gray-500">Por curso e por ano de conclusão, com filtros. Baixa em Excel ou PDF.</p>
            </a>
            <?php endif; ?>
            <a href="admin-dashboard.php" class="block bg-white border border-gray-100 rounded-2xl p-6 hover:border-[#4A9FCA]">
                <i class="fas fa-user-cog text-2xl text-[#4A9FCA] mb-3"></i>
                <h4 class="font-bold text-slate-900 mb-1">Visão geral dos alunos</h4>
                <p class="text-sm text-gray-500">Indicadores, estágio obrigatório por curso (com aviso por e-mail) e últimos matriculados.</p>
            </a>
            <div class="bg-white border border-gray-100 rounded-2xl p-6">
                <i class="fas fa-download text-2xl text-[#27AE60] mb-3"></i>
                <h4 class="font-bold text-slate-900 mb-1">Excel de qualquer painel</h4>
                <p class="text-sm text-gray-500">Em qualquer painel, <strong>clique com o botão direito</strong> num bloco: exporte aquele bloco, as linhas detalhadas ou o painel inteiro (uma aba por bloco).</p>
            </div>
            </div>
        </div>
    </section>

    <!-- ======================= UM PAINEL ======================= -->
    <section id="vPainel" class="hidden">
        <div id="biBarra" class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="min-w-0">
                <p class="text-sm text-gray-400 mb-1">
                    <a href="admin-paineis.php" onclick="event.preventDefault(); biIrInicio();" class="hover:text-[#4A9FCA]">Painéis</a>
                    <i class="fas fa-chevron-right text-[10px] mx-1"></i><a href="#" id="biMigalhaPasta" class="hover:text-[#4A9FCA]"></a>
                </p>
                <h2 id="biNomePainel" class="text-3xl font-bold text-slate-900 truncate"></h2>
                <p id="biDescPainel" class="text-slate-500 text-sm"></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span id="biSalvo" class="text-xs text-gray-400 mr-2"></span>
                <button type="button" class="bi-btn" onclick="biModalPainel(BI.atual)"><i class="fas fa-cog"></i>Propriedades</button>
                <button type="button" class="bi-btn" onclick="biDuplicarPainel(BI.atual.id)"><i class="fas fa-clone"></i>Duplicar</button>
                <button type="button" class="bi-btn" onclick="window.print()"><i class="fas fa-print"></i>Imprimir / PDF</button>
                <span class="bi-so-edicao hidden flex gap-2">
                    <button type="button" class="bi-btn" style="border-color:#4A9FCA;color:#2B7FA6" onclick="biNovoVisual()"><i class="fas fa-plus"></i>Novo visual</button>
                    <button type="button" class="bi-btn" onclick="biAbrirFontes()"><i class="fas fa-database"></i>Fontes de dados</button>
                    <button type="button" id="biBotaoRestaurar" class="bi-btn" onclick="biRestaurar()"><i class="fas fa-undo"></i>Restaurar padrão</button>
                </span>
                <button type="button" id="biBotaoEditar" class="bi-btn bi-btn-azul" onclick="biAlternarEdicao()"><i class="fas fa-pen"></i>Editar painel</button>
            </div>
        </div>

        <!-- Segmentação (filtra o painel inteiro) -->
        <div id="biSegmentacao" class="flex flex-wrap items-center gap-3 mb-5 text-sm">
            <span class="font-semibold text-gray-600"><i class="fas fa-filter mr-1 text-[#4A9FCA]"></i>Segmentação:</span>
            <select id="biCurso" class="bi-campo" style="width:auto;min-width:260px">
                <option value="">Todos os cursos</option>
                <?php foreach (getFsaCourses() as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="biPeriodo" class="bi-campo" style="width:auto" title="Vale para os dados que têm mês ou dia (candidaturas, vagas, assinaturas, acessos...)">
                <option value="">Todo o período</option>
                <option value="1">Últimos 30 dias</option>
                <option value="3">Últimos 3 meses</option>
                <option value="6">Últimos 6 meses</option>
                <option value="12">Últimos 12 meses</option>
            </select>
            <span id="biRealce" class="hidden px-3 py-1.5 bg-[#4A9FCA]/10 text-[#2B7FA6] rounded-full font-semibold"></span>
        </div>

        <div class="flex gap-5 items-start">
            <div id="biGrade" class="bi-grid flex-1 min-w-0"></div>

            <!-- Painel lateral de Visualizações (modo edição) -->
            <aside id="biEditor" class="hidden w-80 flex-shrink-0 bg-white border border-gray-100 rounded-2xl p-5 sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto">
                <h3 class="font-bold text-slate-900 mb-1"><i class="fas fa-sliders-h mr-2 text-[#4A9FCA]"></i>Visualizações</h3>
                <p id="biEditorVazio" class="text-sm text-gray-400 mt-3">Clique num visual para editar, ou em "Novo visual".</p>
                <div id="biEditorCampos" class="hidden">
                    <label class="bi-rotulo">Título</label>
                    <input id="edTitulo" class="bi-campo" maxlength="80">

                    <label class="bi-rotulo">Tipo de visual</label>
                    <div id="edTipos" class="flex flex-wrap gap-1.5"></div>

                    <label class="bi-rotulo">Fonte de dados</label>
                    <select id="edFonte" class="bi-campo"></select>

                    <div id="edBlocoEixo">
                        <label class="bi-rotulo">Eixo (agrupar por)</label>
                        <select id="edEixo" class="bi-campo"></select>
                        <p id="edDicaFunil" class="hidden text-xs text-gray-400 mt-1">No funil, as etapas seguem a ordem alfabética do eixo (por isso "1. ...", "2. ..."). Com "Contagem", cada etapa soma também as seguintes.</p>
                    </div>

                    <label class="bi-rotulo">Valor</label>
                    <select id="edAgg" class="bi-campo"></select>
                    <select id="edCampo" class="bi-campo mt-1.5"></select>
                    <input id="edValor" class="bi-campo mt-1.5 hidden" list="edValores" placeholder="Valores que contam (separe por vírgula)">

                    <label class="bi-rotulo">Filtros deste visual</label>
                    <div id="edFiltros" class="space-y-1.5"></div>
                    <datalist id="edValores"></datalist>

                    <div id="edBlocoOrdem" class="grid grid-cols-2 gap-2">
                        <div><label class="bi-rotulo">Ordenar por</label>
                            <select id="edOrdem" class="bi-campo"><option value="valor">Maior valor</option><option value="rotulo">Rótulo (A-Z / data)</option></select></div>
                        <div><label class="bi-rotulo">Mostrar só</label>
                            <select id="edLimite" class="bi-campo"><option value="0">Todos</option><option value="5">Top 5</option><option value="8">Top 8</option><option value="10">Top 10</option><option value="15">Top 15</option></select></div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="bi-rotulo">Largura</label>
                            <select id="edLargura" class="bi-campo"><option value="1">1/4</option><option value="2">2/4</option><option value="3">3/4</option><option value="4">Inteira</option></select></div>
                        <div><label class="bi-rotulo">Cor</label><input id="edCor" type="color" class="bi-campo p-1"></div>
                    </div>

                    <div class="flex gap-2 mt-5">
                        <button type="button" onclick="biDuplicar()" class="flex-1 h-10 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50"><i class="fas fa-copy mr-1"></i>Duplicar</button>
                        <button type="button" onclick="biExcluir()" class="flex-1 h-10 border border-red-200 text-red-600 rounded-xl text-sm hover:bg-red-50"><i class="fas fa-trash-alt mr-1"></i>Excluir</button>
                    </div>
                    <p class="text-xs text-gray-400 mt-4"><i class="fas fa-arrows-alt mr-1"></i>Arraste os visuais para mudar a ordem. Tudo é salvo sozinho.</p>
                </div>
            </aside>
        </div>
    </section>
    <!-- ======================= UM RELATÓRIO (criar / editar) ======================= -->
    <section id="vRelatorio" class="hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="min-w-0">
                <p class="text-sm text-gray-400 mb-1">
                    <a href="admin-paineis.php?aba=relatorios" onclick="event.preventDefault(); relFechar();" class="hover:text-[#4A9FCA]">Relatórios</a>
                </p>
                <input id="rlNome" class="text-3xl font-bold text-slate-900 bg-transparent border-b border-dashed border-gray-300 focus:outline-none focus:border-[#4A9FCA] w-full max-w-xl" maxlength="80" placeholder="Nome do relatório">
                <input id="rlDesc" class="text-sm text-slate-500 bg-transparent w-full max-w-xl mt-1 focus:outline-none" maxlength="200" placeholder="Descrição (opcional)">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span id="rlSalvo" class="text-xs text-gray-400 mr-2"></span>
                <select id="rlPasta" class="bi-campo" style="width:auto" title="Pasta"></select>
                <button type="button" class="bi-btn" onclick="relExportar()"><i class="fas fa-file-excel" style="color:#22a06b"></i>Exportar Excel</button>
                <button type="button" class="bi-btn" onclick="relCriarPainel()"><i class="fas fa-chart-pie"></i>Criar painel com este relatório</button>
                <button type="button" class="bi-btn bi-btn-azul" onclick="relSalvar()"><i class="fas fa-save"></i>Salvar</button>
            </div>
        </div>

        <div class="flex flex-col xl:flex-row gap-5 items-start">
            <!-- Construtor -->
            <aside class="w-full xl:w-96 flex-shrink-0 bg-white border border-gray-100 rounded-2xl p-5">
                <h3 class="font-bold text-slate-900"><i class="fas fa-database mr-2 text-[#4A9FCA]"></i>1. Origem dos dados <span class="text-red-500">*</span></h3>
                <div class="grid grid-cols-3 gap-2 mt-3" id="rlOrigens">
                    <button type="button" data-origem="totvs" class="rl-origem p-3 border border-gray-200 rounded-xl text-xs text-gray-600 hover:border-[#4A9FCA]"><i class="fas fa-university text-lg block mb-1"></i>TOTVS</button>
                    <button type="button" data-origem="excel" class="rl-origem p-3 border border-gray-200 rounded-xl text-xs text-gray-600 hover:border-[#4A9FCA]"><i class="fas fa-file-excel text-lg block mb-1"></i>Planilha Excel</button>
                    <button type="button" data-origem="sql" class="rl-origem p-3 border border-gray-200 rounded-xl text-xs text-gray-600 hover:border-[#4A9FCA]"><i class="fas fa-server text-lg block mb-1"></i>Banco / data lake</button>
                </div>
                <div id="rlOrigemTotvs" class="hidden mt-3 text-xs text-gray-500 bg-gray-50 p-3 rounded-xl"></div>
                <div id="rlOrigemExcel" class="hidden mt-3 space-y-2">
                    <select id="rlPlanilha" class="bi-campo"></select>
                    <details class="text-xs"><summary class="cursor-pointer text-[#2B7FA6] font-semibold">Importar uma planilha nova</summary>
                        <input id="rlXNome" class="bi-campo mt-2" placeholder="Nome (ex.: Metas 2026)" maxlength="60">
                        <input id="rlXArq" type="file" accept=".xlsx,.xls,.csv" class="text-xs mt-2 w-full">
                        <button type="button" onclick="relImportarExcel()" class="w-full h-9 mt-2 bg-[#27AE60] text-white rounded-lg text-xs font-semibold">Importar</button>
                    </details>
                </div>
                <div id="rlOrigemSql" class="hidden mt-3 space-y-2">
                    <select id="rlConsulta" class="bi-campo"></select>
                    <details class="text-xs"><summary class="cursor-pointer text-[#2B7FA6] font-semibold">Nova consulta SQL</summary>
                        <p id="rlSqlAviso" class="text-gray-500 mt-2"></p>
                        <input id="rlSNome" class="bi-campo mt-2" placeholder="Nome da consulta" maxlength="60">
                        <textarea id="rlSSql" rows="4" class="w-full p-2 mt-2 border border-gray-200 rounded-lg font-mono" placeholder="SELECT curso, COUNT(*) AS total FROM ..."></textarea>
                        <button type="button" id="rlSBotao" onclick="relNovaConsulta()" class="w-full h-9 mt-1 bg-[#2B7FA6] text-white rounded-lg text-xs font-semibold">Testar e salvar</button>
                    </details>
                </div>

                <h3 class="font-bold text-slate-900 mt-6"><i class="fas fa-columns mr-2 text-[#4A9FCA]"></i>2. Colunas</h3>
                <div class="flex gap-3 text-xs mt-1"><button type="button" class="text-[#2B7FA6]" onclick="relTodasColunas(true)">todas</button><button type="button" class="text-gray-400" onclick="relTodasColunas(false)">nenhuma</button></div>
                <div id="rlColunas" class="mt-2 grid grid-cols-2 gap-1 text-sm max-h-48 overflow-y-auto"></div>

                <h3 class="font-bold text-slate-900 mt-6"><i class="fas fa-filter mr-2 text-[#4A9FCA]"></i>3. Filtros</h3>
                <div id="rlFiltros" class="space-y-2 mt-2"></div>
                <button type="button" onclick="relAddFiltro()" class="text-xs text-[#2B7FA6] font-semibold mt-2"><i class="fas fa-plus mr-1"></i>Adicionar filtro</button>

                <h3 class="font-bold text-slate-900 mt-6"><i class="fas fa-layer-group mr-2 text-[#4A9FCA]"></i>4. Agrupar e resumir</h3>
                <label class="bi-rotulo">Agrupar linhas por</label>
                <select id="rlAgrupar" class="bi-campo"></select>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="bi-rotulo">Resumo</label>
                        <select id="rlResAgg" class="bi-campo"><option value="contagem">Contagem</option><option value="distinto">Contagem distinta</option><option value="soma">Soma</option><option value="media">Média</option></select></div>
                    <div><label class="bi-rotulo">De</label><select id="rlResCampo" class="bi-campo"></select></div>
                </div>

                <h3 class="font-bold text-slate-900 mt-6"><i class="fas fa-sort mr-2 text-[#4A9FCA]"></i>5. Ordem</h3>
                <div class="grid grid-cols-3 gap-2 mt-2">
                    <select id="rlOrdenar" class="bi-campo col-span-2"></select>
                    <select id="rlDirecao" class="bi-campo"><option value="asc">A → Z</option><option value="desc">Z → A</option></select>
                </div>
                <label class="bi-rotulo">Mostrar só as primeiras</label>
                <select id="rlLimite" class="bi-campo"><option value="0">Todas as linhas</option><option value="10">10</option><option value="50">50</option><option value="100">100</option><option value="500">500</option><option value="1000">1000</option></select>
            </aside>

            <!-- Prévia -->
            <div class="flex-1 min-w-0 w-full bg-white border border-gray-100 rounded-2xl p-5" id="rlPreviaBox" data-exportar="Relatório">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <p class="font-bold text-slate-900"><i class="fas fa-eye mr-2 text-[#4A9FCA]"></i>Prévia</p>
                    <p id="rlContagem" class="text-xs text-gray-400"></p>
                </div>
                <div id="rlResumo" class="hidden mb-4"></div>
                <div id="rlPrevia" class="overflow-x-auto max-h-[65vh] overflow-y-auto"><p class="text-sm text-gray-400 p-6 text-center">Escolha a origem dos dados para começar.</p></div>
                <p class="text-xs text-gray-400 mt-3"><i class="fas fa-mouse-pointer mr-1"></i>Clique com o botão direito na prévia para exportar para o Excel.</p>
            </div>
        </div>
    </section>
</div>
</main>

<!-- Janela: novo painel / propriedades -->
<div id="biModalPainel" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-7">
        <div class="flex justify-between items-center mb-5">
            <h3 id="mpTitulo" class="text-xl font-bold text-slate-900">Novo painel</h3>
            <button type="button" onclick="biFechar('biModalPainel')" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <label class="bi-rotulo">Nome do painel</label>
        <input id="mpNome" class="bi-campo" maxlength="80" placeholder="Ex.: Estágios de Engenharia 2026">
        <label class="bi-rotulo">Descrição (opcional)</label>
        <input id="mpDesc" class="bi-campo" maxlength="200" placeholder="Para que serve este painel">
        <label class="bi-rotulo">Pasta</label>
        <select id="mpPasta" class="bi-campo"></select>
        <div id="mpBlocoModelo">
            <label class="bi-rotulo">Conectar o painel a</label>
            <div class="grid grid-cols-2 gap-2" id="mpConexoes">
                <button type="button" data-con="tabela" class="mp-con p-3 border border-gray-200 rounded-xl text-xs text-gray-600 text-left"><i class="fas fa-table text-lg block mb-1"></i><strong>Uma tabela</strong><br>Dados da Central, TOTVS, planilha ou banco</button>
                <button type="button" data-con="relatorio" class="mp-con p-3 border border-gray-200 rounded-xl text-xs text-gray-600 text-left"><i class="fas fa-file-alt text-lg block mb-1"></i><strong>Um relatório</strong><br>Já filtrado e organizado</button>
                <button type="button" data-con="modelo" class="mp-con p-3 border border-gray-200 rounded-xl text-xs text-gray-600 text-left"><i class="fas fa-magic text-lg block mb-1"></i><strong>Um modelo pronto</strong><br>Alunos, funil ou egressos</button>
                <button type="button" data-con="branco" class="mp-con p-3 border border-gray-200 rounded-xl text-xs text-gray-600 text-left"><i class="far fa-square text-lg block mb-1"></i><strong>Em branco</strong><br>Escolho a fonte em cada visual</button>
            </div>
            <div id="mpBlocoFonte" class="hidden">
                <label class="bi-rotulo" id="mpRotFonte">Tabela</label>
                <select id="mpFonte" class="bi-campo"></select>
                <p id="mpDicaFonte" class="text-xs text-gray-400 mt-1"></p>
            </div>
            <select id="mpModelo" class="hidden"></select>
        </div>
        <div class="flex justify-between items-center gap-2 mt-6">
            <button type="button" id="mpExcluir" class="hidden h-10 px-4 text-red-600 text-sm" onclick="biExcluirPainel(BI.editandoMeta.id)"><i class="fas fa-trash-alt mr-1"></i>Excluir painel</button>
            <span></span>
            <div class="flex gap-2">
                <button type="button" class="bi-btn" onclick="biFechar('biModalPainel')">Cancelar</button>
                <button type="button" id="mpSalvar" class="bi-btn bi-btn-azul" onclick="biSalvarModalPainel()">Criar painel</button>
            </div>
        </div>
    </div>
</div>

<!-- Janela: nova pasta -->
<div id="biModalPasta" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-7">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-xl font-bold text-slate-900">Nova pasta</h3>
            <button type="button" onclick="biFechar('biModalPasta')" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <label class="bi-rotulo">Nome da pasta</label>
        <input id="mfNome" class="bi-campo" maxlength="60" placeholder="Ex.: Reuniões da coordenação">
        <?php if ($podeEgressos): ?>
        <label class="bi-rotulo">Quem vê</label>
        <select id="mfAcesso" class="bi-campo">
            <option value="todos">Todos os funcionários</option>
            <option value="egressos">Só a Coordenação de Egressos</option>
        </select>
        <?php endif; ?>
        <p class="text-xs text-gray-400 mt-3">Para um painel que só você vê, use a pasta "Meus painéis particulares".</p>
        <div class="flex justify-end gap-2 mt-6">
            <button type="button" class="bi-btn" onclick="biFechar('biModalPasta')">Cancelar</button>
            <button type="button" class="bi-btn bi-btn-azul" onclick="biCriarPasta()">Criar pasta</button>
        </div>
    </div>
</div>

<!-- Janela: fontes de dados -->
<div id="biModalFontes" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl p-7 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-xl font-bold text-slate-900"><i class="fas fa-database mr-2 text-[#4A9FCA]"></i>Fontes de dados</h3>
            <button type="button" onclick="biFechar('biModalFontes')" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-sm text-gray-500 mb-4">As fontes da Central (alunos, vagas, candidaturas...) já estão prontas. Aqui você acrescenta outras, que ficam disponíveis em todos os painéis.</p>
        <div id="biListaCriadas" class="space-y-2 mb-6"></div>
        <div class="grid md:grid-cols-2 gap-5">
            <div class="p-5 border border-gray-100 rounded-2xl">
                <h4 class="font-bold text-slate-900 mb-1"><i class="fas fa-file-excel text-[#27AE60] mr-1"></i>Planilha (Excel ou CSV)</h4>
                <p class="text-xs text-gray-500 mb-3">A primeira linha precisa ter os nomes das colunas. Até 5000 linhas.</p>
                <input id="fxNome" class="bi-campo mb-2" placeholder="Nome da fonte (ex.: Metas 2026)" maxlength="60">
                <input id="fxArquivo" type="file" accept=".xlsx,.xls,.csv" class="text-sm mb-3 w-full">
                <button type="button" onclick="biEnviarExcel()" class="w-full h-10 bg-[#27AE60] text-white rounded-xl text-sm font-semibold">Importar planilha</button>
            </div>
            <div class="p-5 border border-gray-100 rounded-2xl">
                <h4 class="font-bold text-slate-900 mb-1"><i class="fas fa-server text-[#2B7FA6] mr-1"></i>Banco de dados / data lake (SQL)</h4>
                <p id="sqlAviso" class="text-xs text-gray-500 mb-3"></p>
                <input id="sqNome" class="bi-campo mb-2" placeholder="Nome da fonte" maxlength="60">
                <textarea id="sqSql" rows="4" class="w-full p-2 border border-gray-200 rounded-xl text-xs font-mono mb-3" placeholder="SELECT curso, COUNT(*) AS total FROM ..."></textarea>
                <button type="button" id="sqBotao" onclick="biEnviarSql()" class="w-full h-10 bg-[#2B7FA6] text-white rounded-xl text-sm font-semibold">Testar e salvar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
/* =============================================================================
 * PAINÉIS — tudo o que roda no navegador
 * ========================================================================== */
const H = { 'X-Requested-With': 'fetch' };
const CORES = ['#4A9FCA', '#27AE60', '#E67E22', '#E35444', '#8E44AD', '#F1C40F', '#2B7FA6', '#16A085', '#D35400', '#7F8C8D'];
const TIPOS = { cartao: ['Cartão', 'fa-square'], barras: ['Barras', 'fa-chart-bar'], colunas: ['Colunas', 'fa-chart-column'],
                linha: ['Linha', 'fa-chart-line'], pizza: ['Pizza', 'fa-chart-pie'], rosca: ['Rosca', 'fa-circle-notch'],
                tabela: ['Tabela', 'fa-table'], funil: ['Funil', 'fa-filter'] };
const AGGS  = { contagem: 'Contagem de linhas', distinto: 'Contagem distinta de', soma: 'Soma de', media: 'Média de', pct: '% de linhas em que' };
const PARTICULAR = 'particular';

const BI = {
    // tela inicial
    pastas: [], paineis: [], modelos: {}, usuario: 0, podeEgressos: false, filtroLista: 'todos', editandoMeta: null,
    // painel aberto
    atual: null, painel: [], fontes: {}, criadas: [], sql: false, cache: {}, editando: false, sel: null, charts: {}, realce: null, timer: null,
};

/* ---------- Utilidades ---------- */
function el(tag, cls, texto) { const e = document.createElement(tag); if (cls) e.className = cls; if (texto !== undefined) e.textContent = texto; return e; }
function biGet(url) { return fetch(url, { headers: H, cache: 'no-store' }).then(r => r.json()); }
function biPost(dados) {
    const fd = new FormData();
    Object.keys(dados).forEach(k => fd.append(k, dados[k]));
    return fetch('bi-dados.php', { method: 'POST', headers: H, body: fd }).then(r => r.json());
}
function biAvisar(t, tit) { window.fsaAviso ? fsaAviso(t, tit || 'Aviso') : alert(t); }
function biConfirmar(op, fazer) { window.fsaConfirmar ? fsaConfirmar(op, fazer) : (confirm(op.texto) && fazer()); }
function biAbrir(id) { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function biFechar(id) { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }
function biData(s) { if (!s) return '—'; const p = String(s).split(/[- :]/); return p.length >= 3 ? p[2] + '/' + p[1] + '/' + p[0] + (p[3] ? ' ' + p[3] + ':' + p[4] : '') : s; }
function biNomePasta(id) { if (id === PARTICULAR) return 'Meus painéis particulares'; const p = BI.pastas.find(x => x.id === id); return p ? p.nome : '—'; }

/* =============================================================================
 * TELA INICIAL: pastas e lista de painéis
 * ========================================================================== */
function biCarregarInicio() {
    return biGet('bi-dados.php?inicio=1').then(d => {
        if (!d.ok) { biAvisar(d.erro || 'Não foi possível abrir os painéis.'); return; }
        Object.assign(BI, { pastas: d.pastas, paineis: d.paineis, modelos: d.modelos, usuario: d.usuario, podeEgressos: d.podeEgressos,
                            fontes: d.fontes, criadas: d.criadas, sql: d.sqlConfigurado });
        biDesenharPastas(); biDesenharLista();
    });
}

function biBotaoPasta(chave, icone, rotulo, qtd) {
    const b = el('button', 'bi-pasta' + (BI.filtroLista === chave ? ' ativa' : ''));
    b.type = 'button';
    const i = el('i', 'fas ' + icone + ' w-4 text-center'); const t = el('span', 'flex-1 truncate', rotulo); const n = el('span', 'text-xs text-gray-400', String(qtd));
    b.append(i, t, n);
    b.onclick = () => { BI.filtroLista = chave; history.replaceState(null, '', chave === 'todos' ? 'admin-paineis.php' : '?pasta=' + encodeURIComponent(chave)); biDesenharPastas(); biDesenharLista(); };
    return b;
}

function biDesenharPastas() {
    const at = document.getElementById('biAtalhos'); at.innerHTML = '';
    at.append(
        biBotaoPasta('todos', 'fa-th-large', 'Todos os painéis', BI.paineis.length),
        biBotaoPasta('meus', 'fa-user', 'Criados por mim', BI.paineis.filter(p => p.criadoPor === BI.usuario).length),
        biBotaoPasta(PARTICULAR, 'fa-lock', 'Meus painéis particulares', BI.paineis.filter(p => p.pasta === PARTICULAR).length),
    );
    const ps = document.getElementById('biPastas'); ps.innerHTML = '';
    BI.pastas.forEach(p => ps.appendChild(biBotaoPasta(p.id, p.acesso === 'egressos' ? 'fa-user-graduate' : 'fa-folder', p.nome,
        BI.paineis.filter(x => x.pasta === p.id).length)));
}

function biDesenharLista() {
    const f = BI.filtroLista, q = document.getElementById('biBusca').value.trim().toLowerCase();
    let lista = BI.paineis.filter(p => f === 'todos' || (f === 'meus' ? p.criadoPor === BI.usuario : p.pasta === f));
    if (q) lista = lista.filter(p => (p.nome + ' ' + p.descricao).toLowerCase().includes(q));
    lista.sort((a, b) => String(b.modificadoEm).localeCompare(String(a.modificadoEm)));

    const rot = { todos: 'Todos os painéis', meus: 'Criados por mim' };
    document.getElementById('biTituloLista').textContent = rot[f] || biNomePasta(f);
    document.getElementById('biContLista').textContent = lista.length + ' painel(is)';
    const pasta = BI.pastas.find(p => p.id === f);
    document.getElementById('biAcoesPasta').classList.toggle('hidden', !pasta || !!pasta.fixa);

    const tb = document.getElementById('biLista'); tb.innerHTML = '';
    if (!lista.length) {
        const tr = tb.insertRow(); const td = tr.insertCell(); td.colSpan = 5; td.className = 'p-10 text-center text-gray-400';
        td.textContent = q ? 'Nenhum painel com esse nome.' : 'Nenhum painel aqui ainda. Clique em "Novo painel".';
        return;
    }
    lista.forEach(p => {
        const tr = tb.insertRow(); tr.className = 'border-b last:border-0 hover:bg-gray-50';
        const c1 = tr.insertCell(); c1.className = 'p-3';
        const a = el('a', 'font-semibold text-[#2B7FA6] hover:underline', p.nome);
        a.href = p.pagina || ('?painel=' + encodeURIComponent(p.id));           // painel "página" (ex.: Visão geral dos alunos) abre a tela dele
        if (!p.pagina) a.onclick = e => { e.preventDefault(); biAbrirPainel(p.id, true); };
        c1.appendChild(a);
        if (p.descricao) c1.appendChild(el('p', 'text-xs text-gray-400', p.descricao));
        const c2 = tr.insertCell(); c2.className = 'p-3 text-gray-600';
        c2.append(el('i', 'fas ' + (p.pasta === PARTICULAR ? 'fa-lock' : 'fa-folder') + ' text-gray-300 mr-1'), document.createTextNode(biNomePasta(p.pasta)));
        tr.insertCell().append(el('span', 'text-gray-600', p.criadoPorNome || '—')); tr.cells[2].className = 'p-3';
        const c4 = tr.insertCell(); c4.className = 'p-3 text-gray-500';
        c4.append(el('span', '', biData(p.modificadoEm)), el('p', 'text-xs text-gray-400', p.modificadoPorNome || ''));
        const c5 = tr.insertCell(); c5.className = 'p-3 text-right whitespace-nowrap';
        [['fa-cog', 'Propriedades', () => biModalPainel(p)], ['fa-clone', 'Duplicar', () => biDuplicarPainel(p.id)],
         ['fa-trash-alt', 'Excluir', () => biExcluirPainel(p.id)]].forEach(([ic, t, fn]) => {
            const b = el('button', 'w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[#2B7FA6]'); b.type = 'button'; b.title = t;
            b.appendChild(el('i', 'fas ' + ic)); b.onclick = fn; c5.appendChild(b);
        });
    });
}

function biAba(nome) {
    document.querySelectorAll('.bi-aba').forEach(b => b.classList.toggle('ativa', b.dataset.aba === nome));
    document.getElementById('abaPaineis').classList.toggle('hidden', nome !== 'paineis');
    document.getElementById('abaRelatorios').classList.toggle('hidden', nome !== 'relatorios');
    if (nome === 'relatorios') relCarregarLista();
    history.replaceState(null, '', nome === 'relatorios' ? '?aba=relatorios' : 'admin-paineis.php');
}

/* ---------- Novo painel / propriedades ---------- */
function biModalPainel(meta) {
    BI.editandoMeta = meta || null;
    const novo = !meta;
    document.getElementById('mpTitulo').textContent = novo ? 'Novo painel' : 'Propriedades do painel';
    document.getElementById('mpSalvar').textContent = novo ? 'Criar painel' : 'Salvar';
    document.getElementById('mpNome').value = novo ? '' : meta.nome;
    document.getElementById('mpDesc').value = novo ? '' : (meta.descricao || '');
    document.getElementById('mpBlocoModelo').classList.toggle('hidden', !novo);
    document.getElementById('mpExcluir').classList.toggle('hidden', novo);

    const sel = document.getElementById('mpPasta'); sel.innerHTML = '';
    BI.pastas.forEach(p => sel.appendChild(new Option(p.nome + (p.acesso === 'egressos' ? ' (só Coordenação de Egressos)' : ''), p.id)));
    sel.appendChild(new Option('Meus painéis particulares (só eu vejo)', PARTICULAR));
    const atual = novo ? (BI.pastas.some(p => p.id === BI.filtroLista) || BI.filtroLista === PARTICULAR ? BI.filtroLista : (BI.pastas[0] || {}).id) : meta.pasta;
    sel.value = atual || PARTICULAR;

    BI.conexao = 'tabela';
    biMostrarConexao();
    biAbrir('biModalPainel');
    setTimeout(() => document.getElementById('mpNome').focus(), 50);
}

/* ---------- Fontes separadas em Tabelas e Relatórios ---------- */
function biGruposFontes(fontes) {
    const tab = [], rel = [];
    Object.entries(fontes || {}).forEach(([k, v]) => (k.indexOf('rel:') === 0 ? rel : tab).push([k, v]));
    return { tabelas: tab, relatorios: rel };
}
function opcoesFontes(sel, fontes, valor, so) {
    sel.innerHTML = '';
    const g = biGruposFontes(fontes);
    [['tabelas', 'Tabelas'], ['relatorios', 'Relatórios']].forEach(([k, rot]) => {
        if (so && so !== k) return;
        const og = document.createElement('optgroup'); og.label = rot;
        if (!g[k].length) { const o = new Option(k === 'relatorios' ? '(nenhum relatório salvo ainda)' : '(nenhuma)', ''); o.disabled = true; og.appendChild(o); }
        g[k].forEach(([v, r]) => { const o = new Option(r.replace(/^Relatório: /, ''), v); if (v === valor) o.selected = true; og.appendChild(o); });
        sel.appendChild(og);
    });
}

function biMostrarConexao() {
    document.querySelectorAll('.mp-con').forEach(b => b.classList.toggle('ativa', b.dataset.con === BI.conexao));
    const bloco = document.getElementById('mpBlocoFonte'), sel = document.getElementById('mpFonte'), dica = document.getElementById('mpDicaFonte');
    bloco.classList.toggle('hidden', BI.conexao === 'branco');
    if (BI.conexao === 'tabela') {
        document.getElementById('mpRotFonte').textContent = 'Tabela';
        opcoesFontes(sel, BI.fontes, 'alunos', 'tabelas');
        dica.textContent = 'O painel lê a tabela direto. Para filtrar antes ou juntar colunas, crie um relatório.';
    } else if (BI.conexao === 'relatorio') {
        document.getElementById('mpRotFonte').textContent = 'Relatório';
        opcoesFontes(sel, BI.fontes, '', 'relatorios');
        dica.innerHTML = '';
        dica.append('O painel usa as linhas já filtradas do relatório. ');
        const a = el('a', 'text-[#2B7FA6] font-semibold', 'Criar um relatório'); a.href = '#'; a.onclick = e => { e.preventDefault(); biFechar('biModalPainel'); relNovo(); };
        dica.appendChild(a);
    } else if (BI.conexao === 'modelo') {
        document.getElementById('mpRotFonte').textContent = 'Modelo';
        sel.innerHTML = '';
        Object.entries(BI.modelos).forEach(([k, v]) => sel.appendChild(new Option(v[0] + ' — ' + v[1], k)));
        dica.textContent = 'Uma cópia editável do modelo.';
    }
}
document.querySelectorAll('.mp-con').forEach(b => b.addEventListener('click', () => { BI.conexao = b.dataset.con; biMostrarConexao(); }));

/* Visuais iniciais de um painel conectado a uma tabela ou relatório */
function biVisuaisIniciais(fonteId) {
    return biFonte(fonteId).then(f => {
        if (!f.ok) throw new Error(f.erro || 'Não foi possível ler a fonte.');
        const campos = Object.entries(f.campos);
        const texto = campos.filter(([k, c]) => c.tipo === 'texto');
        // eixo: primeiro campo de texto com poucos valores diferentes (bom para gráfico)
        let eixo = '', melhor = 1e9;
        texto.forEach(([k]) => { const n = new Set(f.linhas.map(l => l[k])).size; if (n > 1 && n < melhor && n <= 40) { melhor = n; eixo = k; } });
        if (!eixo && texto.length) eixo = texto[0][0];
        const data = campos.find(([k, c]) => c.tipo === 'data');
        const base = { fonte: fonteId, agg: 'contagem', campo: '', valor: '', filtros: [], ordem: 'valor', limite: 0, cor: '#4A9FCA' };
        const v = [Object.assign({}, base, { id: 'v1', titulo: 'Total de registros', tipo: 'cartao', eixo: '', largura: 1 })];
        if (eixo) v.push(Object.assign({}, base, { id: 'v2', titulo: 'Por ' + f.campos[eixo].rotulo, tipo: 'colunas', eixo: eixo, largura: 3, limite: 15 }));
        if (data) v.push(Object.assign({}, base, { id: 'v3', titulo: 'Por ' + data[1].rotulo, tipo: 'linha', eixo: data[0], largura: 2, ordem: 'rotulo' }));
        if (eixo) v.push(Object.assign({}, base, { id: 'v4', titulo: f.campos[eixo].rotulo + ' (tabela)', tipo: 'tabela', eixo: eixo, largura: data ? 2 : 4 }));
        return v;
    });
}

function biSalvarModalPainel() {
    const meta = BI.editandoMeta;
    const dados = { nome: document.getElementById('mpNome').value.trim(), descricao: document.getElementById('mpDesc').value.trim(),
                    pasta: document.getElementById('mpPasta').value };
    if (!dados.nome) return biAvisar('Dê um nome para o painel.');
    if (!meta) {
        const con = BI.conexao, escolha = document.getElementById('mpFonte').value;
        if ((con === 'tabela' || con === 'relatorio') && !escolha) return biAvisar(con === 'relatorio' ? 'Escolha um relatório (ou crie um antes).' : 'Escolha a tabela.');
        dados.action = 'painel_criar'; dados.modelo = con === 'modelo' ? escolha : '';
        const iniciais = (con === 'tabela' || con === 'relatorio') ? biVisuaisIniciais(escolha) : Promise.resolve(null);
        iniciais.then(visuais => biPost(dados).then(d => {
            if (!d.ok) return biAvisar(d.erro);
            biFechar('biModalPainel');
            const salvar = visuais ? biPost({ action: 'salvar_painel', id: d.id, visuais: JSON.stringify(visuais) }) : Promise.resolve();
            salvar.then(() => biCarregarInicio()).then(() => biAbrirPainel(d.id, true, con !== 'modelo'));
        })).catch(e => biAvisar(e.message));
        return;
    }
    dados.action = 'painel_editar'; dados.id = meta.id;
    biPost(dados).then(d => {
        if (!d.ok) return biAvisar(d.erro);
        biFechar('biModalPainel');
        if (BI.atual && BI.atual.id === meta.id) { Object.assign(BI.atual, d.painel); biCabecalhoPainel(); }
        biCarregarInicio();
    });
}

function biDuplicarPainel(id) {
    biPost({ action: 'painel_duplicar', id: id }).then(d => {
        if (!d.ok) return biAvisar(d.erro);
        biCarregarInicio().then(() => biAbrirPainel(d.id, true));
    });
}

function biExcluirPainel(id) {
    const p = BI.paineis.find(x => x.id === id) || BI.atual || {};
    biConfirmar({ titulo: 'Excluir o painel?', texto: '"' + (p.nome || '') + '" será apagado para todos. Isso não apaga nenhum dado da Central.', botao: 'Excluir', tipo: 'perigo', icone: 'fa-trash-alt' }, () => {
        biPost({ action: 'painel_excluir', id: id }).then(d => {
            if (!d.ok) return biAvisar(d.erro);
            biFechar('biModalPainel');
            biCarregarInicio().then(biIrInicio);
        });
    });
}

/* ---------- Pastas ---------- */
function biModalPasta() { document.getElementById('mfNome').value = ''; biAbrir('biModalPasta'); setTimeout(() => document.getElementById('mfNome').focus(), 50); }
function biCriarPasta() {
    const nome = document.getElementById('mfNome').value.trim();
    if (!nome) return biAvisar('Dê um nome para a pasta.');
    const ac = document.getElementById('mfAcesso');
    biPost({ action: 'pasta_criar', nome: nome, acesso: ac ? ac.value : 'todos' }).then(d => {
        if (!d.ok) return biAvisar(d.erro);
        biFechar('biModalPasta'); BI.filtroLista = d.pasta.id; biCarregarInicio();
    });
}
function biRenomearPasta() {
    const p = BI.pastas.find(x => x.id === BI.filtroLista); if (!p) return;
    const nome = prompt('Novo nome da pasta:', p.nome);
    if (!nome || !nome.trim()) return;
    biPost({ action: 'pasta_renomear', id: p.id, nome: nome.trim() }).then(d => d.ok ? biCarregarInicio() : biAvisar(d.erro));
}
function biExcluirPasta() {
    const p = BI.pastas.find(x => x.id === BI.filtroLista); if (!p) return;
    biConfirmar({ titulo: 'Apagar a pasta?', texto: 'A pasta "' + p.nome + '" precisa estar vazia.', botao: 'Apagar', tipo: 'perigo', icone: 'fa-folder-minus' }, () => {
        biPost({ action: 'pasta_excluir', id: p.id }).then(d => {
            if (!d.ok) return biAvisar(d.erro);
            BI.filtroLista = 'todos'; biCarregarInicio();
        });
    });
}

/* =============================================================================
 * UM PAINEL
 * ========================================================================== */
function biIrInicio() {
    if (BI.editando) biAlternarEdicao();
    BI.atual = null;
    document.getElementById('vPainel').classList.add('hidden');
    document.getElementById('vRelatorio').classList.add('hidden');
    document.getElementById('vInicio').classList.remove('hidden');
    history.pushState(null, '', BI.filtroLista === 'todos' ? 'admin-paineis.php' : '?pasta=' + encodeURIComponent(BI.filtroLista));
    biDesenharPastas(); biDesenharLista();
}

function biAbrirPainel(id, empurrar, editar) {
    return biGet('bi-dados.php?painel=' + encodeURIComponent(id)).then(d => {
        if (!d.ok) { biAvisar(d.erro || 'Painel não encontrado.'); return; }
        if (d.painel.pagina) { location.href = d.painel.pagina; return; }      // painel com tela própria
        BI.atual = d.painel; BI.painel = Array.isArray(d.painel.visuais) ? d.painel.visuais : [];
        BI.sel = null; BI.realce = null;
        document.getElementById('vInicio').classList.add('hidden');
        document.getElementById('vRelatorio').classList.add('hidden');
        document.getElementById('vPainel').classList.remove('hidden');
        if (empurrar) history.pushState(null, '', '?painel=' + encodeURIComponent(id));
        biCabecalhoPainel();
        document.getElementById('biSalvo').textContent = '';
        biDesenhar();
        if (editar && !BI.editando) biAlternarEdicao();
        window.scrollTo({ top: 0 });
    });
}

function biCabecalhoPainel() {
    const p = BI.atual;
    document.getElementById('biNomePainel').textContent = p.nome;
    document.getElementById('biDescPainel').textContent = p.descricao || '';
    const m = document.getElementById('biMigalhaPasta'); m.textContent = biNomePasta(p.pasta);
    m.onclick = e => { e.preventDefault(); BI.filtroLista = p.pasta; biIrInicio(); };
    document.getElementById('biBotaoRestaurar').classList.toggle('hidden', !p.modelo);
    document.title = p.nome + ' - Painéis';
}

function biFonte(id) {                        // cada fonte é buscada uma vez só
    if (!BI.cache[id]) BI.cache[id] = biGet('bi-dados.php?fonte=' + encodeURIComponent(id));
    return BI.cache[id];
}
function biSalvar() {                         // salva sozinho, 700 ms depois da última mudança
    if (!BI.atual) return;
    clearTimeout(BI.timer);
    document.getElementById('biSalvo').textContent = 'Salvando...';
    const id = BI.atual.id, visuais = JSON.stringify(BI.painel);
    BI.timer = setTimeout(() => biPost({ action: 'salvar_painel', id: id, visuais: visuais })
        .then(d => document.getElementById('biSalvo').textContent = d.ok ? 'Salvo' : (d.erro || 'Não foi possível salvar')), 700);
}

/* ---------- Contas (o "motor" do painel) ---------- */
function lista(txt) { return String(txt || '').split(',').map(s => s.trim().toLowerCase()).filter(Boolean); }

function biCorte() {                          // início do período escolhido, em "AAAA-MM-DD"
    const m = +document.getElementById('biPeriodo').value;
    if (!m) return '';
    const d = new Date();
    if (m === 1) d.setDate(d.getDate() - 30); else d.setMonth(d.getMonth() - m);
    return d.toISOString().slice(0, 10);
}

function filtrar(linhas, w, campos) {
    const curso = document.getElementById('biCurso').value, corte = biCorte();
    return linhas.filter(l => {
        for (const f of (w.filtros || [])) {
            if (!f.campo) continue;
            const vals = lista(f.valor);
            if (vals.length && !vals.includes(String(l[f.campo] ?? '').toLowerCase())) return false;
        }
        if (curso && campos.curso && l.curso !== curso) return false;                        // segmentação por curso
        if (corte) {                                                                         // segmentação por período
            if (campos.dia && l.dia && l.dia < corte) return false;
            else if (!campos.dia && campos.mes && l.mes && l.mes < corte.slice(0, 7)) return false;
        }
        if (BI.realce && BI.realce.id !== w.id && campos[BI.realce.campo]
            && String(l[BI.realce.campo] ?? '') !== BI.realce.valor) return false;           // clique em outro gráfico
        return true;
    });
}

function medir(linhas, w) {
    const nums = () => linhas.map(l => parseFloat(l[w.campo])).filter(n => !isNaN(n));
    switch (w.agg) {
        case 'distinto': return new Set(linhas.map(l => l[w.campo])).size;
        case 'soma':     return nums().reduce((s, n) => s + n, 0);
        case 'media':    { const n = nums(); return n.length ? n.reduce((s, x) => s + x, 0) / n.length : 0; }
        case 'pct':      { if (!linhas.length) return 0; const v = lista(w.valor);
                           return linhas.filter(l => v.includes(String(l[w.campo] ?? '').toLowerCase())).length / linhas.length * 100; }
        default:         return linhas.length;
    }
}

function agrupar(linhas, w) {
    const grupos = {};
    linhas.forEach(l => { const k = String(l[w.eixo] ?? '') || '(vazio)'; (grupos[k] = grupos[k] || []).push(l); });
    let pares = Object.keys(grupos).map(k => [k, medir(grupos[k], w)]);
    const porRotulo = w.ordem === 'rotulo' || w.tipo === 'funil';
    pares.sort(porRotulo ? (a, b) => a[0].localeCompare(b[0], 'pt-BR', { numeric: true }) : (a, b) => b[1] - a[1]);
    if (w.tipo === 'funil' && (w.agg || 'contagem') === 'contagem') {          // cada etapa soma as seguintes
        for (let i = pares.length - 2; i >= 0; i--) pares[i][1] += pares[i + 1][1];
    }
    if (+w.limite > 0) pares = pares.slice(0, +w.limite);
    return pares;
}

function fmt(n, w) {
    const casas = (w.agg === 'media' || w.agg === 'pct') ? 1 : (Number.isInteger(n) ? 0 : 2);
    return n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: casas }) + (w.agg === 'pct' ? '%' : '');
}

/* ---------- Desenho ---------- */
function biDesenhar() {
    const grade = document.getElementById('biGrade');
    Object.values(BI.charts).forEach(c => c.destroy());
    BI.charts = {};
    grade.innerHTML = '';
    if (!BI.painel.length) grade.appendChild(el('p', 'text-gray-400 text-sm', 'Painel vazio. Clique em "Editar painel" e depois em "Novo visual".'));
    BI.painel.forEach(w => grade.appendChild(biCartao(w)));
    const r = document.getElementById('biRealce');
    if (BI.realce) {
        r.innerHTML = '';
        r.append('Filtro: ' + BI.realce.rotulo + ' = ' + BI.realce.valor + ' ');
        const x = el('button'); x.type = 'button'; x.appendChild(el('i', 'fas fa-times')); x.onclick = () => { BI.realce = null; biDesenhar(); };
        r.appendChild(x); r.classList.remove('hidden');
    } else r.classList.add('hidden');
}

function biCartao(w) {
    const card = el('div', 'bi-card' + (BI.editando && BI.sel === w.id ? ' bi-sel' : ''));
    card.style.gridColumn = 'span ' + Math.min(4, Math.max(1, +w.largura || 1));
    card.dataset.id = w.id;
    card.fsaExportar = () => biDadosExcel(w);                 // botão direito > Exportar (includes/exportar-excel.php)

    const topo = el('div', 'flex items-start justify-between gap-2 mb-2');
    const exp = el('button', 'text-gray-300 hover:text-[#22a06b] text-xs'); exp.type = 'button'; exp.title = 'Exportar para Excel (ou clique com o botão direito)';
    exp.appendChild(el('i', 'fas fa-file-excel')); exp.onclick = e => { e.stopPropagation(); window.fsaExcel && fsaExcel.exportar(card); };
    topo.append(el('p', 'text-sm font-semibold text-gray-600', w.titulo || 'Sem título'), exp);
    card.appendChild(topo);

    const corpo = el('div'); card.appendChild(corpo);
    corpo.appendChild(el('p', 'text-xs text-gray-300', 'Calculando...'));

    if (BI.editando) {                         // selecionar e arrastar
        card.draggable = true;
        card.onclick = () => { BI.sel = w.id; biDesenhar(); biEditorAbrir(); };
        card.ondragstart = e => { e.dataTransfer.setData('text/plain', w.id); card.classList.add('bi-arrastando'); };
        card.ondragend = () => card.classList.remove('bi-arrastando');
        card.ondragover = e => e.preventDefault();
        card.ondrop = e => {
            e.preventDefault();
            const de = BI.painel.findIndex(x => x.id === e.dataTransfer.getData('text/plain'));
            const para = BI.painel.findIndex(x => x.id === w.id);
            if (de < 0 || para < 0 || de === para) return;
            BI.painel.splice(para, 0, BI.painel.splice(de, 1)[0]);
            biSalvar(); biDesenhar();
        };
    }
    biPreencher(w, corpo);
    return card;
}

function biPreencher(w, corpo) {
    biFonte(w.fonte).then(f => {
        corpo.innerHTML = '';
        if (!f.ok) { corpo.appendChild(el('p', 'text-xs text-red-500', f.erro || 'Erro na fonte.')); return; }
        const linhas = filtrar(f.linhas, w, f.campos);

        if (w.tipo === 'cartao') {
            const n = el('p', 'text-4xl font-bold', fmt(medir(linhas, w), w)); n.style.color = w.cor || '#1F2D3A';
            corpo.append(n, el('p', 'text-xs text-gray-400 mt-1', f.nome + ' · ' + linhas.length + ' linha(s)'));
            return;
        }
        if (!w.eixo || !f.campos[w.eixo]) { corpo.appendChild(el('p', 'text-xs text-amber-600', 'Escolha o eixo no painel de Visualizações.')); return; }
        const pares = agrupar(linhas, w);
        if (!pares.length) { corpo.appendChild(el('p', 'text-sm text-gray-400', 'Sem dados para esse filtro.')); return; }

        if (w.tipo === 'funil') {
            const max = Math.max(1, ...pares.map(p => p[1]));
            const box = el('div', 'space-y-4');
            pares.forEach(([k, v], i) => {
                const linha = el('div');
                const cab = el('div', 'flex justify-between text-sm mb-1');
                const extra = i > 0 && pares[i - 1][1] > 0 ? ' · ' + Math.round(v / pares[i - 1][1] * 100) + '% da etapa anterior' : '';
                cab.append(el('span', 'font-semibold text-gray-700', k), el('span', 'text-gray-500', fmt(v, w) + extra));
                const trilho = el('div', 'h-4 bg-gray-100 rounded-full');
                const barra = el('div', 'h-full rounded-full');
                barra.style.width = Math.max(v > 0 ? 2 : 0, Math.round(v / max * 100)) + '%';
                barra.style.background = CORES[i % CORES.length];
                trilho.appendChild(barra); linha.append(cab, trilho); box.appendChild(linha);
            });
            corpo.appendChild(box);
            return;
        }

        if (w.tipo === 'tabela') {
            const box = el('div', 'max-h-64 overflow-y-auto');
            const tb = el('table', 'w-full text-sm');
            const cab = tb.insertRow();
            [f.campos[w.eixo].rotulo, 'Valor'].forEach((x, i) => cab.appendChild(el('th', 'text-left py-1 text-xs text-gray-500 ' + (i ? 'text-right' : ''), x)));
            pares.forEach(([k, v]) => {
                const r = tb.insertRow(); r.className = 'border-t border-gray-50';
                const a = r.insertCell(); a.className = 'py-1.5'; a.textContent = k;
                const b = r.insertCell(); b.className = 'py-1.5 text-right font-semibold'; b.textContent = fmt(v, w);
            });
            box.appendChild(tb); corpo.appendChild(box); return;
        }

        const caixa = el('div', 'bi-grafico'); const canvas = el('canvas'); caixa.appendChild(canvas); corpo.appendChild(caixa);
        const circular = w.tipo === 'pizza' || w.tipo === 'rosca';
        const tipo = { barras: 'bar', colunas: 'bar', linha: 'line', pizza: 'pie', rosca: 'doughnut' }[w.tipo] || 'bar';
        BI.charts[w.id] = new Chart(canvas, {
            type: tipo,
            data: { labels: pares.map(p => p[0]), datasets: [{
                label: w.titulo, data: pares.map(p => +p[1].toFixed(2)),
                backgroundColor: circular ? pares.map((_, i) => CORES[i % CORES.length]) : (w.cor || '#4A9FCA'),
                borderColor: w.cor || '#4A9FCA', borderRadius: circular ? 0 : 6, tension: .3, pointRadius: 3,
            }] },
            options: {
                responsive: true, maintainAspectRatio: false, indexAxis: w.tipo === 'barras' ? 'y' : 'x',
                plugins: { legend: { display: circular, position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: circular ? {} : { x: { beginAtZero: true, ticks: { font: { size: 10 } } }, y: { beginAtZero: true, ticks: { font: { size: 10 } } } },
                onClick: (ev, itens) => {             // filtro cruzado, como no Power BI
                    if (BI.editando || !itens.length) return;
                    const valor = pares[itens[0].index][0];
                    BI.realce = (BI.realce && BI.realce.id === w.id && BI.realce.valor === valor) ? null
                              : { id: w.id, campo: w.eixo, valor: valor, rotulo: f.campos[w.eixo].rotulo };
                    biDesenhar();
                },
            },
        });
    });
}

function biDadosExcel(w) {                    // o que vai para o Excel: resumo do visual + linhas detalhadas
    return biFonte(w.fonte).then(f => {
        if (!f.ok) return { nome: w.titulo || 'Visual', aoa: [[f.erro || 'Erro na fonte']] };
        const linhas = filtrar(f.linhas, w, f.campos);
        const campos = Object.keys(f.campos);
        const rotV = (AGGS[w.agg || 'contagem'] || 'Valor') + (w.campo && f.campos[w.campo] ? ' ' + f.campos[w.campo].rotulo : '');
        const aoa = w.tipo === 'cartao'
            ? [['Indicador', 'Valor'], [w.titulo || 'Valor', +medir(linhas, w).toFixed(2)]]
            : [[(f.campos[w.eixo] || {}).rotulo || 'Item', rotV]].concat(agrupar(linhas, w).map(p => [p[0], +p[1].toFixed(2)]));
        return {
            nome: w.titulo || 'Visual', aoa: aoa,
            detalhado: () => ({ nome: (w.titulo || 'Visual') + ' - linhas',
                                aoa: [campos.map(k => f.campos[k].rotulo)].concat(linhas.map(l => campos.map(k => l[k] ?? ''))) }),
        };
    });
}

/* ---------- Editor (painel de Visualizações) ---------- */
function biAtual() { return BI.painel.find(w => w.id === BI.sel); }
function opcoes(sel, pares, valor) {
    sel.innerHTML = '';
    pares.forEach(([v, r]) => { const o = new Option(r, v); if (String(v) === String(valor)) o.selected = true; sel.appendChild(o); });
}

function biEditorAbrir() {
    const w = biAtual();
    document.getElementById('biEditorVazio').classList.toggle('hidden', !!w);
    document.getElementById('biEditorCampos').classList.toggle('hidden', !w);
    if (!w) return;

    document.getElementById('edTitulo').value = w.titulo || '';
    const tipos = document.getElementById('edTipos'); tipos.innerHTML = '';
    Object.entries(TIPOS).forEach(([k, [rot, ic]]) => {
        const b = el('button', 'bi-tipo' + (w.tipo === k ? ' ativo' : '')); b.type = 'button'; b.title = rot;
        b.appendChild(el('i', 'fas ' + ic));
        b.onclick = () => { w.tipo = k; if (k === 'funil') w.ordem = 'rotulo'; biMudou(); };
        tipos.appendChild(b);
    });
    opcoesFontes(document.getElementById('edFonte'), BI.fontes, w.fonte);
    opcoes(document.getElementById('edAgg'), Object.entries(AGGS), w.agg || 'contagem');
    document.getElementById('edOrdem').value = w.ordem || 'valor';
    document.getElementById('edLimite').value = String(w.limite || 0);
    document.getElementById('edLargura').value = String(w.largura || 1);
    document.getElementById('edCor').value = w.cor || '#4A9FCA';
    document.getElementById('edBlocoEixo').classList.toggle('hidden', w.tipo === 'cartao');
    document.getElementById('edBlocoOrdem').classList.toggle('hidden', w.tipo === 'cartao' || w.tipo === 'funil');
    document.getElementById('edDicaFunil').classList.toggle('hidden', w.tipo !== 'funil');

    biFonte(w.fonte).then(f => {
        const campos = f.ok ? Object.entries(f.campos).map(([k, c]) => [k, c.rotulo]) : [];
        opcoes(document.getElementById('edEixo'), [['', 'Escolha...']].concat(campos), w.eixo);
        const precisaCampo = w.agg && w.agg !== 'contagem';
        const campoSel = document.getElementById('edCampo');
        campoSel.classList.toggle('hidden', !precisaCampo);
        opcoes(campoSel, [['', 'Escolha o campo...']].concat(campos), w.campo);
        const valor = document.getElementById('edValor');
        valor.classList.toggle('hidden', w.agg !== 'pct'); valor.value = w.valor || '';
        valor.onfocus = () => biSugerir(f, w.campo);

        const box = document.getElementById('edFiltros'); box.innerHTML = '';
        const filtros = (w.filtros || []).concat([{}, {}]).slice(0, 2);
        filtros.forEach((flt, i) => {
            const linha = el('div', 'grid grid-cols-2 gap-1.5');
            const s = el('select', 'bi-campo'); opcoes(s, [['', 'Sem filtro']].concat(campos), flt.campo || '');
            const v = el('input', 'bi-campo'); v.placeholder = 'valor(es)'; v.value = flt.valor || ''; v.setAttribute('list', 'edValores');
            const salvar = () => {
                const atuais = (w.filtros || []).concat([{}, {}]).slice(0, 2);
                atuais[i] = { campo: s.value, valor: v.value };
                w.filtros = atuais.filter(x => x && x.campo);
                biMudou(false);
            };
            s.onchange = salvar; v.oninput = salvar;
            v.onfocus = () => biSugerir(f, s.value);
            linha.append(s, v); box.appendChild(linha);
        });
    });
}

function biSugerir(f, campo) {                 // sugestões de valores no filtro
    const dl = document.getElementById('edValores'); dl.innerHTML = '';
    if (!f.ok || !campo) return;
    [...new Set(f.linhas.map(l => String(l[campo] ?? '')))].slice(0, 100).forEach(v => dl.appendChild(new Option(v, v)));
}

function biMudou(reabrir = true) { biSalvar(); biDesenhar(); if (reabrir) biEditorAbrir(); }

[['edTitulo', 'titulo', 'input'], ['edEixo', 'eixo'], ['edCampo', 'campo'], ['edValor', 'valor', 'input'], ['edOrdem', 'ordem'],
 ['edLimite', 'limite'], ['edLargura', 'largura'], ['edCor', 'cor', 'input'], ['edAgg', 'agg'], ['edFonte', 'fonte']].forEach(([id, prop, ev]) => {
    document.getElementById(id).addEventListener(ev || 'change', e => {
        const w = biAtual(); if (!w) return;
        w[prop] = e.target.value;
        if (prop === 'fonte') { w.eixo = ''; w.campo = ''; w.filtros = []; }      // trocou a fonte: os campos mudam
        biMudou(!['titulo', 'valor', 'cor'].includes(prop));
    });
});

function biNovoVisual() {
    const w = { id: 'v' + Date.now(), titulo: 'Novo visual', fonte: 'alunos', tipo: 'colunas', eixo: 'curso', agg: 'contagem',
                campo: '', valor: '', filtros: [], largura: 2, ordem: 'valor', limite: 0, cor: '#4A9FCA' };
    BI.painel.unshift(w); BI.sel = w.id; biMudou();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function biDuplicar() { const w = biAtual(); if (!w) return; const c = JSON.parse(JSON.stringify(w)); c.id = 'v' + Date.now(); c.titulo += ' (cópia)'; BI.painel.splice(BI.painel.indexOf(w) + 1, 0, c); BI.sel = c.id; biMudou(); }
function biExcluir() {
    const w = biAtual(); if (!w) return;
    biConfirmar({ titulo: 'Excluir visual?', texto: '"' + (w.titulo || '') + '" sai do painel.', botao: 'Excluir', tipo: 'perigo', icone: 'fa-trash-alt' },
        () => { BI.painel = BI.painel.filter(x => x !== w); BI.sel = null; biMudou(); });
}
function biRestaurar() {
    biConfirmar({ titulo: 'Restaurar o painel padrão?', texto: 'Os visuais deste painel voltam a ser os do modelo original.', botao: 'Restaurar', tipo: 'aviso', icone: 'fa-undo' }, () =>
        biPost({ action: 'padrao', id: BI.atual.id }).then(d => {
            if (!d.ok) return biAvisar(d.erro);
            BI.painel = d.painel.visuais || []; BI.sel = null; biDesenhar(); biEditorAbrir();
        }));
}
function biAlternarEdicao() {
    BI.editando = !BI.editando; BI.realce = null;
    document.getElementById('biRaiz').classList.toggle('bi-editando', BI.editando);
    document.getElementById('biEditor').classList.toggle('hidden', !BI.editando);
    document.querySelectorAll('.bi-so-edicao').forEach(e => e.classList.toggle('hidden', !BI.editando));
    document.getElementById('biBotaoEditar').innerHTML = BI.editando ? '<i class="fas fa-check"></i>Concluir edição' : '<i class="fas fa-pen"></i>Editar painel';
    if (BI.atual) { biDesenhar(); biEditorAbrir(); }
}

/* ---------- Fontes de dados (Excel e SQL) ---------- */
function biRecarregarFontes() {
    return biGet('bi-dados.php?inicio=1').then(d => { BI.fontes = d.fontes; BI.criadas = d.criadas; BI.sql = d.sqlConfigurado; biListarCriadas(); biEditorAbrir(); });
}
function biListarCriadas() {
    const box = document.getElementById('biListaCriadas'); box.innerHTML = '';
    if (!BI.criadas.length) box.appendChild(el('p', 'text-xs text-gray-400', 'Nenhuma fonte criada ainda.'));
    BI.criadas.forEach(f => {
        const l = el('div', 'flex items-center justify-between p-3 bg-gray-50 rounded-xl text-sm');
        const b = el('button', 'text-red-500 text-xs'); b.type = 'button'; b.append(el('i', 'fas fa-trash-alt mr-1'), 'Apagar');
        b.onclick = () => biPost({ action: 'fonte_apagar', id: f.id }).then(() => { delete BI.cache['custom:' + f.id]; biRecarregarFontes().then(biDesenhar); });
        l.append(el('span', '', (f.tipo === 'sql' ? 'SQL: ' : 'Planilha: ') + f.nome), b); box.appendChild(l);
    });
    document.getElementById('sqBotao').disabled = !BI.sql;
    document.getElementById('sqBotao').classList.toggle('opacity-40', !BI.sql);
    document.getElementById('sqlAviso').textContent = BI.sql
        ? 'Escreva um SELECT (só leitura). Ele roda toda vez que o painel abre.'
        : 'Desligado: configure BI_SQL_DSN, BI_SQL_USUARIO e BI_SQL_SENHA no .env ou no Render (veja docs/PAINEIS-BI.md).';
}
function biAbrirFontes() { biListarCriadas(); biAbrir('biModalFontes'); }

function biEnviarExcel() {
    const nome = document.getElementById('fxNome').value.trim(), arq = document.getElementById('fxArquivo').files[0];
    if (!nome || !arq) return biAvisar('Informe o nome e escolha a planilha.');
    const leitor = new FileReader();
    leitor.onload = e => {
        try {
            const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
            const linhas = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '' }).slice(0, 5000);
            biPost({ action: 'fonte_excel', nome: nome, linhas: JSON.stringify(linhas) }).then(d => {
                if (!d.ok) return biAvisar(d.erro);
                biRecarregarFontes(); biAvisar(linhas.length + ' linha(s) importada(s). Escolha a fonte "' + nome + '" num visual.', 'Planilha importada');
            });
        } catch (err) { biAvisar('Não consegui ler a planilha. Confira se é .xlsx, .xls ou .csv.'); }
    };
    leitor.readAsArrayBuffer(arq);
}
function biEnviarSql() {
    const nome = document.getElementById('sqNome').value.trim(), sql = document.getElementById('sqSql').value.trim();
    if (!nome || !sql) return biAvisar('Informe o nome e a consulta.');
    biPost({ action: 'fonte_sql', nome: nome, sql: sql }).then(d => {
        if (!d.ok) return biAvisar(d.erro, 'A consulta não funcionou');
        biRecarregarFontes(); biAvisar('Consulta salva. Escolha a fonte "' + nome + '" num visual.', 'Fonte criada');
    });
}

/* =============================================================================
 * RELATÓRIOS (estilo Salesforce): origem TOTVS, planilha ou banco/data lake
 * ========================================================================== */
const REL = { lista: [], ops: {}, planilhas: [], consultas: [], totvsModo: '', sql: false, def: null, id: '', campos: {}, linhas: [], timer: null, carregado: false };
const ORIGENS = { totvs: 'TOTVS', excel: 'Planilha Excel', sql: 'Banco / data lake' };

function relCarregarLista() {
    return biGet('bi-dados.php?relatorios=1').then(d => {
        if (!d.ok) return;
        Object.assign(REL, { lista: d.relatorios, ops: d.operadores, planilhas: d.planilhas, consultas: d.consultas, totvsModo: d.totvsModo, sql: d.sqlConfigurado, carregado: true });
        relDesenharLista();
    });
}

function relDesenharLista() {
    const tb = document.getElementById('relLista'); if (!tb) return;
    const q = document.getElementById('relBusca').value.trim().toLowerCase();
    const lista = REL.lista.filter(r => !q || (r.nome + ' ' + r.descricao).toLowerCase().includes(q));
    tb.innerHTML = '';
    if (!lista.length) {
        const td = tb.insertRow().insertCell(); td.colSpan = 6; td.className = 'p-10 text-center text-gray-400';
        td.textContent = q ? 'Nenhum relatório com esse nome.' : 'Nenhum relatório ainda. Clique em "Novo relatório".';
        return;
    }
    lista.forEach(r => {
        const tr = tb.insertRow(); tr.className = 'border-b last:border-0 hover:bg-gray-50';
        const c1 = tr.insertCell(); c1.className = 'p-3';
        const a = el('a', 'font-semibold text-[#2B7FA6] hover:underline', r.nome); a.href = '?relatorio=' + encodeURIComponent(r.id);
        a.onclick = e => { e.preventDefault(); relAbrir(r.id, true); };
        c1.appendChild(a); if (r.descricao) c1.appendChild(el('p', 'text-xs text-gray-400', r.descricao));
        const c2 = tr.insertCell(); c2.className = 'p-3 text-gray-600'; c2.textContent = ORIGENS[r.origem] || r.origem;
        const c3 = tr.insertCell(); c3.className = 'p-3 text-gray-600'; c3.textContent = biNomePasta(r.pasta);
        const c4 = tr.insertCell(); c4.className = 'p-3 text-gray-600'; c4.textContent = r.criadoPorNome || '—';
        const c5 = tr.insertCell(); c5.className = 'p-3 text-gray-500'; c5.append(el('span', '', biData(r.modificadoEm)), el('p', 'text-xs text-gray-400', r.modificadoPorNome || ''));
        const c6 = tr.insertCell(); c6.className = 'p-3 text-right whitespace-nowrap';
        [['fa-clone', 'Duplicar', () => relDuplicar(r.id)], ['fa-trash-alt', 'Excluir', () => relExcluir(r)]].forEach(([ic, t, fn]) => {
            const b = el('button', 'w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[#2B7FA6]'); b.type = 'button'; b.title = t;
            b.appendChild(el('i', 'fas ' + ic)); b.onclick = fn; c6.appendChild(b);
        });
    });
}

function relMostrarTela() {
    if (BI.editando) biAlternarEdicao();
    BI.atual = null;
    ['vInicio', 'vPainel'].forEach(id => document.getElementById(id).classList.add('hidden'));
    document.getElementById('vRelatorio').classList.remove('hidden');
    const sel = document.getElementById('rlPasta'); sel.innerHTML = '';
    BI.pastas.forEach(p => sel.appendChild(new Option('Pasta: ' + p.nome, p.id)));
    sel.appendChild(new Option('Pasta: Meus relatórios particulares', PARTICULAR));
    window.scrollTo({ top: 0 });
}

function relNovo() {
    const p = () => {
        REL.id = ''; REL.def = { nome: '', descricao: '', pasta: (BI.pastas[0] || {}).id || PARTICULAR, origem: '', fonte: '', colunas: [], filtros: [],
                                 agrupar: '', resumoAgg: 'contagem', resumoCampo: '', ordenar: '', direcao: 'asc', limite: 0 };
        relMostrarTela(); relPreencher(); history.pushState(null, '', '?relatorio=novo');
        document.getElementById('rlSalvo').textContent = 'Relatório novo (ainda não salvo)';
    };
    return REL.carregado ? p() : relCarregarLista().then(p);
}

function relAbrir(id, empurrar) {
    const abrir = () => biGet('bi-dados.php?relatorio=' + encodeURIComponent(id)).then(d => {
        if (!d.ok) { biAvisar(d.erro || 'Relatório não encontrado.'); return; }
        REL.id = d.relatorio.id; REL.def = d.relatorio;
        relMostrarTela(); relPreencher(true);
        if (empurrar) history.pushState(null, '', '?relatorio=' + encodeURIComponent(id));
        document.getElementById('rlSalvo').textContent = 'Última alteração: ' + biData(d.relatorio.modificadoEm) + ' · ' + (d.relatorio.modificadoPorNome || '');
        document.title = d.relatorio.nome + ' - Relatórios';
    });
    return REL.carregado ? abrir() : relCarregarLista().then(abrir);
}

function relFechar() {
    document.getElementById('vRelatorio').classList.add('hidden');
    document.getElementById('vInicio').classList.remove('hidden');
    biAba('relatorios'); relCarregarLista();
}

/* ---------- Preencher o construtor ---------- */
function relPreencher(rodar) {
    const d = REL.def;
    document.getElementById('rlNome').value = d.nome || '';
    document.getElementById('rlDesc').value = d.descricao || '';
    document.getElementById('rlPasta').value = d.pasta || PARTICULAR;
    document.querySelectorAll('.rl-origem').forEach(b => b.classList.toggle('ativa', b.dataset.origem === d.origem));
    document.getElementById('rlOrigemTotvs').classList.toggle('hidden', d.origem !== 'totvs');
    document.getElementById('rlOrigemExcel').classList.toggle('hidden', d.origem !== 'excel');
    document.getElementById('rlOrigemSql').classList.toggle('hidden', d.origem !== 'sql');
    document.getElementById('rlOrigemTotvs').innerHTML = '';
    document.getElementById('rlOrigemTotvs').append(el('i', 'fas fa-info-circle mr-1 text-[#4A9FCA]'),
        'Alunos do sistema acadêmico. Modo atual: ', el('strong', '', REL.totvsModo || 'desligado'),
        REL.totvsModo === 'teste' ? ' (lendo data/totvs_exemplo.json).' : REL.totvsModo === 'desligado' ? ' — ligue em TOTVS_MODO.' : '.');
    opcoes(document.getElementById('rlPlanilha'), [['', REL.planilhas.length ? 'Escolha a planilha...' : 'Nenhuma planilha: importe abaixo']].concat(REL.planilhas.map(f => [f.id, f.nome])), d.origem === 'excel' ? d.fonte : '');
    opcoes(document.getElementById('rlConsulta'), [['', REL.consultas.length ? 'Escolha a consulta...' : 'Nenhuma consulta: crie abaixo']].concat(REL.consultas.map(f => [f.id, f.nome])), d.origem === 'sql' ? d.fonte : '');
    document.getElementById('rlSqlAviso').textContent = REL.sql ? 'Só SELECT (leitura). A consulta roda toda vez que o relatório abre.'
        : 'Desligado: configure BI_SQL_DSN, BI_SQL_USUARIO e BI_SQL_SENHA (veja docs/PAINEIS-BI.md).';
    document.getElementById('rlSBotao').disabled = !REL.sql;
    document.getElementById('rlSBotao').classList.toggle('opacity-40', !REL.sql);
    document.getElementById('rlResAgg').value = d.resumoAgg || 'contagem';
    document.getElementById('rlDirecao').value = d.direcao || 'asc';
    document.getElementById('rlLimite').value = String(d.limite || 0);
    relCamposUI();
    if (rodar !== false && d.origem && d.fonte) relRodar(true);
    else relRodarVazio();
}

function relRodarVazio() {
    REL.campos = {}; REL.linhas = [];
    document.getElementById('rlPrevia').innerHTML = '<p class="text-sm text-gray-400 p-6 text-center">Escolha a origem dos dados para começar.</p>';
    document.getElementById('rlContagem').textContent = '';
    document.getElementById('rlResumo').classList.add('hidden');
    relCamposUI();
}

/* Colunas, filtros, agrupar e ordem dependem dos campos da origem */
function relCamposUI() {
    const d = REL.def, todos = REL.camposOrigem || {};
    const pares = Object.entries(todos).map(([k, c]) => [k, c.rotulo]);
    const box = document.getElementById('rlColunas'); box.innerHTML = '';
    if (!pares.length) box.appendChild(el('p', 'text-xs text-gray-400 col-span-2', 'Os campos aparecem depois de escolher a origem.'));
    pares.forEach(([k, rot]) => {
        const lb = el('label', 'flex items-center gap-2 text-gray-700 truncate');
        const cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = !d.colunas.length || d.colunas.includes(k);
        cb.onchange = () => { d.colunas = pares.map(p => p[0]).filter(c => box.querySelector('[data-c="' + c + '"]').checked); relMudou(); };
        cb.dataset.c = k; lb.append(cb, document.createTextNode(rot)); box.appendChild(lb);
    });
    const comVazio = [['', '—']].concat(pares);
    opcoes(document.getElementById('rlAgrupar'), [['', 'Sem agrupamento']].concat(pares), d.agrupar || '');
    opcoes(document.getElementById('rlResCampo'), comVazio, d.resumoCampo || '');
    opcoes(document.getElementById('rlOrdenar'), [['', 'Sem ordem']].concat(pares), d.ordenar || '');
    const fb = document.getElementById('rlFiltros'); fb.innerHTML = '';
    (d.filtros || []).forEach((f, i) => {
        const linha = el('div', 'grid grid-cols-12 gap-1 items-center');
        const s1 = el('select', 'bi-campo col-span-4'); opcoes(s1, comVazio, f.campo);
        const s2 = el('select', 'bi-campo col-span-3'); opcoes(s2, Object.entries(REL.ops), f.op || 'igual');
        const v = el('input', 'bi-campo col-span-4'); v.value = f.valor || ''; v.placeholder = 'valor'; v.setAttribute('list', 'rlVals' + i);
        const dl = document.createElement('datalist'); dl.id = 'rlVals' + i;
        [...new Set(REL.linhasOrigem ? REL.linhasOrigem.map(l => String(l[f.campo] ?? '')) : [])].slice(0, 80).forEach(x => dl.appendChild(new Option(x, x)));
        const x = el('button', 'col-span-1 text-gray-400 hover:text-red-500'); x.type = 'button'; x.appendChild(el('i', 'fas fa-times'));
        const salvar = () => { d.filtros[i] = { campo: s1.value, op: s2.value, valor: v.value }; v.classList.toggle('opacity-40', ['vazio', 'nao_vazio'].includes(s2.value)); relMudou(); };
        s1.onchange = () => { salvar(); relCamposUI(); }; s2.onchange = salvar; v.oninput = salvar;
        x.onclick = () => { d.filtros.splice(i, 1); relCamposUI(); relMudou(); };
        linha.append(s1, s2, v, x, dl); fb.appendChild(linha);
    });
}
function relAddFiltro() { REL.def.filtros.push({ campo: '', op: 'igual', valor: '' }); relCamposUI(); }
function relTodasColunas(sim) { REL.def.colunas = sim ? [] : [Object.keys(REL.camposOrigem || {})[0]].filter(Boolean); relCamposUI(); relMudou(); }

/* ---------- Rodar (prévia) ---------- */
function relLerTela() {
    const d = REL.def;
    d.nome = document.getElementById('rlNome').value.trim();
    d.descricao = document.getElementById('rlDesc').value.trim();
    d.pasta = document.getElementById('rlPasta').value;
    d.agrupar = document.getElementById('rlAgrupar').value;
    d.resumoAgg = document.getElementById('rlResAgg').value;
    d.resumoCampo = document.getElementById('rlResCampo').value;
    d.ordenar = document.getElementById('rlOrdenar').value;
    d.direcao = document.getElementById('rlDirecao').value;
    d.limite = +document.getElementById('rlLimite').value;
    return d;
}
function relMudou() { clearTimeout(REL.timer); REL.timer = setTimeout(() => relRodar(false), 400); }

function relRodar(novaOrigem) {
    const d = relLerTela();
    if (!d.origem || !d.fonte) { relRodarVazio(); return Promise.resolve(); }
    document.getElementById('rlContagem').textContent = 'Carregando...';
    const base = Promise.resolve(novaOrigem || !REL.camposOrigem
        ? biGet('bi-dados.php?fonte=' + encodeURIComponent(d.fonte)).then(f => {
              if (!f.ok) throw new Error(f.erro || 'Não foi possível ler a origem.');
              REL.camposOrigem = f.campos; REL.linhasOrigem = f.linhas.slice(0, 2000);
              d.colunas = d.colunas.filter(c => f.campos[c]); relCamposUI();
          })
        : null);
    return base.then(() => biPost({ action: 'rel_previa', def: JSON.stringify(d) })).then(r => {
        if (!r.ok) throw new Error(r.erro);
        REL.campos = r.campos; REL.linhas = r.linhas;
        relDesenharPrevia(r);
    }).catch(e => {
        document.getElementById('rlContagem').textContent = '';
        document.getElementById('rlPrevia').innerHTML = '';
        document.getElementById('rlPrevia').appendChild(el('p', 'text-sm text-red-600 p-6 text-center', e.message || 'Erro ao rodar o relatório.'));
    });
}

function relResumir(linhas) {
    const d = REL.def, c = d.resumoCampo;
    const nums = () => linhas.map(l => parseFloat(String(l[c]).replace(',', '.'))).filter(n => !isNaN(n));
    if (d.resumoAgg === 'distinto' && c) return new Set(linhas.map(l => l[c])).size;
    if (d.resumoAgg === 'soma' && c) return nums().reduce((s, n) => s + n, 0);
    if (d.resumoAgg === 'media' && c) { const n = nums(); return n.length ? n.reduce((s, x) => s + x, 0) / n.length : 0; }
    return linhas.length;
}
function relRotuloResumo() {
    const d = REL.def, rot = (REL.campos[d.resumoCampo] || REL.camposOrigem?.[d.resumoCampo] || {}).rotulo || '';
    return { contagem: 'Registros', distinto: 'Distintos de ' + rot, soma: 'Soma de ' + rot, media: 'Média de ' + rot }[d.resumoAgg] || 'Registros';
}
function relFmt(n) { return (+n).toLocaleString('pt-BR', { maximumFractionDigits: 2 }); }

function relDesenharPrevia(r) {
    const d = REL.def, cols = Object.keys(r.campos), box = document.getElementById('rlPrevia');
    document.getElementById('rlContagem').textContent = r.total + ' registro(s) na origem filtrada · origem: ' + r.origemNome
        + (r.linhas.length < r.total ? ' · mostrando ' + r.linhas.length : '');
    box.innerHTML = '';
    const tabela = el('table', 'w-full text-sm');
    const cab = tabela.createTHead().insertRow();
    cols.forEach(c => cab.appendChild(el('th', 'text-left p-2 text-xs uppercase text-gray-500 bg-gray-50 sticky top-0', r.campos[c].rotulo)));
    const tb = tabela.createTBody();
    const MAX = 500;
    const linhaHtml = l => { const tr = tb.insertRow(); tr.className = 'border-t border-gray-50'; cols.forEach(c => { const td = tr.insertCell(); td.className = 'p-2 text-gray-700'; td.textContent = l[c] ?? ''; }); };
    const resumo = document.getElementById('rlResumo'); resumo.innerHTML = '';
    const grupoValido = d.agrupar && (REL.camposOrigem || {})[d.agrupar];
    if (grupoValido) {
        // Relatório de resumo: cabeçalho do grupo com subtotal
        const campoG = d.agrupar, grupos = {};
        const fonteG = d.agrupar in r.campos ? r.linhas : null;
        if (!fonteG) { resumo.classList.remove('hidden'); resumo.appendChild(el('p', 'text-xs text-amber-600', 'Para agrupar, marque a coluna "' + REL.camposOrigem[campoG].rotulo + '" em Colunas.')); }
        (fonteG || []).forEach(l => { const k = String(l[campoG] ?? '') || '(vazio)'; (grupos[k] = grupos[k] || []).push(l); });
        const chaves = Object.keys(grupos).sort((a, b) => a.localeCompare(b, 'pt-BR', { numeric: true }));
        if (fonteG) {
            resumo.classList.remove('hidden');
            const t = el('div', 'flex flex-wrap gap-2');
            t.appendChild(el('span', 'px-3 py-1.5 bg-[#4A9FCA] text-white rounded-full text-xs font-bold', 'Total: ' + relFmt(relResumir(r.linhas))));
            chaves.slice(0, 30).forEach(k => t.appendChild(el('span', 'px-3 py-1.5 bg-[#4A9FCA]/10 text-[#2B7FA6] rounded-full text-xs font-semibold', k + ': ' + relFmt(relResumir(grupos[k])))));
            resumo.appendChild(t);
        }
        let n = 0;
        chaves.forEach(k => {
            const g = tb.insertRow(); const td = g.insertCell(); td.colSpan = cols.length; td.className = 'p-2 bg-[#eaf4fa] text-[#2B7FA6] font-bold text-xs';
            td.textContent = REL.camposOrigem[campoG].rotulo + ': ' + k + '  ·  ' + relRotuloResumo() + ': ' + relFmt(relResumir(grupos[k])) + '  (' + grupos[k].length + ' linha(s))';
            grupos[k].forEach(l => { if (n++ < MAX) linhaHtml(l); });
        });
    } else {
        resumo.classList.add('hidden');
        r.linhas.slice(0, MAX).forEach(linhaHtml);
        if (d.resumoAgg !== 'contagem' && d.resumoCampo) {
            resumo.classList.remove('hidden');
            resumo.appendChild(el('span', 'px-3 py-1.5 bg-[#4A9FCA] text-white rounded-full text-xs font-bold', relRotuloResumo() + ': ' + relFmt(relResumir(r.linhas))));
        }
    }
    if (!r.linhas.length) { const td = tb.insertRow().insertCell(); td.colSpan = cols.length || 1; td.className = 'p-6 text-center text-gray-400'; td.textContent = 'Nenhuma linha com esses filtros.'; }
    box.appendChild(tabela);
    if (r.linhas.length > MAX) box.appendChild(el('p', 'text-xs text-gray-400 p-2', 'A prévia mostra ' + MAX + ' linhas. O Excel leva todas (' + r.linhas.length + ').'));
}

/* Botão direito na prévia e botão "Exportar Excel": todas as linhas, com o resumo numa segunda aba */
function relDadosExcel() {
    const cols = Object.keys(REL.campos), nome = REL.def.nome || 'Relatório';
    const aoa = [cols.map(c => REL.campos[c].rotulo)].concat(REL.linhas.map(l => cols.map(c => l[c] ?? '')));
    return { nome: nome, aoa: aoa };
}
document.getElementById('rlPreviaBox').fsaExportar = () => relDadosExcel();
function relExportar() {
    if (!REL.linhas.length) return biAvisar('Rode o relatório antes de exportar.');
    if (window.fsaExcel) fsaExcel.exportar(document.getElementById('rlPreviaBox'));
}

/* ---------- Origem ---------- */
document.querySelectorAll('.rl-origem').forEach(b => b.addEventListener('click', () => {
    const d = REL.def; d.origem = b.dataset.origem; d.fonte = d.origem === 'totvs' ? 'totvs' : '';
    d.colunas = []; d.filtros = []; d.agrupar = ''; d.resumoCampo = ''; d.ordenar = '';
    REL.camposOrigem = null; REL.linhasOrigem = null;
    relPreencher(false);
    if (d.fonte) relRodar(true);
}));
['rlPlanilha', 'rlConsulta'].forEach(id => document.getElementById(id).addEventListener('change', e => {
    const d = REL.def; d.fonte = e.target.value; d.colunas = []; d.filtros = []; d.agrupar = ''; d.ordenar = '';
    REL.camposOrigem = null; relCamposUI(); if (d.fonte) relRodar(true); else relRodarVazio();
}));
['rlAgrupar', 'rlResAgg', 'rlResCampo', 'rlOrdenar', 'rlDirecao', 'rlLimite'].forEach(id => document.getElementById(id).addEventListener('change', relMudou));
document.getElementById('relBusca').addEventListener('input', relDesenharLista);

function relImportarExcel() {
    const nome = document.getElementById('rlXNome').value.trim(), arq = document.getElementById('rlXArq').files[0];
    if (!nome || !arq) return biAvisar('Informe o nome e escolha a planilha.');
    const leitor = new FileReader();
    leitor.onload = e => {
        try {
            const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
            const linhas = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '' }).slice(0, 5000);
            biPost({ action: 'fonte_excel', nome: nome, linhas: JSON.stringify(linhas) }).then(d => {
                if (!d.ok) return biAvisar(d.erro);
                relCarregarLista().then(() => { REL.def.fonte = d.id; REL.camposOrigem = null; relPreencher(false); relRodar(true); });
            });
        } catch (err) { biAvisar('Não consegui ler a planilha. Confira se é .xlsx, .xls ou .csv.'); }
    };
    leitor.readAsArrayBuffer(arq);
}
function relNovaConsulta() {
    const nome = document.getElementById('rlSNome').value.trim(), sql = document.getElementById('rlSSql').value.trim();
    if (!nome || !sql) return biAvisar('Informe o nome e a consulta.');
    biPost({ action: 'fonte_sql', nome: nome, sql: sql }).then(d => {
        if (!d.ok) return biAvisar(d.erro, 'A consulta não funcionou');
        relCarregarLista().then(() => { REL.def.fonte = d.id; REL.camposOrigem = null; relPreencher(false); relRodar(true); });
    });
}

/* ---------- Salvar, duplicar, excluir, criar painel ---------- */
function relSalvar() {
    const d = relLerTela();
    if (!d.nome) { document.getElementById('rlNome').focus(); return biAvisar('Dê um nome para o relatório.'); }
    if (!d.origem || !d.fonte) return biAvisar('Escolha a origem dos dados: TOTVS, planilha ou banco de dados.');
    return biPost({ action: 'rel_salvar', id: REL.id, def: JSON.stringify(d) }).then(r => {
        if (!r.ok) { biAvisar(r.erro); return null; }
        REL.id = r.id; history.replaceState(null, '', '?relatorio=' + encodeURIComponent(r.id));
        document.getElementById('rlSalvo').textContent = 'Salvo';
        relCarregarLista(); biCarregarInicio();
        return r.id;
    });
}
function relDuplicar(id) { biPost({ action: 'rel_duplicar', id: id }).then(d => d.ok ? relCarregarLista() : biAvisar(d.erro)); }
function relExcluir(r) {
    biConfirmar({ titulo: 'Excluir o relatório?', texto: '"' + r.nome + '" será apagado. Painéis que usam este relatório como fonte vão mostrar erro.', botao: 'Excluir', tipo: 'perigo', icone: 'fa-trash-alt' },
        () => biPost({ action: 'rel_excluir', id: r.id }).then(d => d.ok ? relCarregarLista() : biAvisar(d.erro)));
}
function relCriarPainel() {
    Promise.resolve(REL.id ? REL.id : relSalvar()).then(id => {
        if (!id) return;
        const d = REL.def, fonte = 'rel:' + id;
        const eixo = d.agrupar || Object.keys(REL.campos)[0] || '';
        biPost({ action: 'painel_criar', nome: 'Painel: ' + (d.nome || 'relatório'), descricao: 'Feito a partir do relatório "' + d.nome + '".', pasta: d.pasta, modelo: '' })
            .then(p => {
                if (!p.ok) return biAvisar(p.erro);
                const visuais = [
                    { id: 'v1', titulo: 'Total de registros', fonte: fonte, tipo: 'cartao', eixo: '', agg: 'contagem', campo: '', valor: '', filtros: [], largura: 1, ordem: 'valor', limite: 0, cor: '#4A9FCA' },
                    { id: 'v2', titulo: 'Por ' + ((REL.campos[eixo] || {}).rotulo || 'grupo'), fonte: fonte, tipo: 'colunas', eixo: eixo, agg: 'contagem', campo: '', valor: '', filtros: [], largura: 3, ordem: 'valor', limite: 15, cor: '#4A9FCA' },
                    { id: 'v3', titulo: d.nome || 'Relatório', fonte: fonte, tipo: 'tabela', eixo: eixo, agg: 'contagem', campo: '', valor: '', filtros: [], largura: 4, ordem: 'valor', limite: 0, cor: '#4A9FCA' },
                ];
                return biPost({ action: 'salvar_painel', id: p.id, visuais: JSON.stringify(visuais) }).then(() => {
                    BI.cache = {};
                    document.getElementById('vRelatorio').classList.add('hidden');
                    biCarregarInicio().then(() => biAbrirPainel(p.id, true, true));
                });
            });
    });
}

/* ---------- Início ---------- */
document.getElementById('biCurso').addEventListener('change', biDesenhar);
document.getElementById('biPeriodo').addEventListener('change', biDesenhar);
document.getElementById('biBusca').addEventListener('input', biDesenharLista);
document.addEventListener('keydown', e => { if (e.key === 'Escape') ['biModalPainel', 'biModalPasta', 'biModalFontes'].forEach(biFechar); });

function biRota() {                            // lê o endereço: ?painel=..., ?pasta=... ou ?aba=relatorios
    const q = new URLSearchParams(location.search);
    document.getElementById('vRelatorio').classList.add('hidden');
    if (q.get('relatorio')) return q.get('relatorio') === 'novo' ? relNovo() : relAbrir(q.get('relatorio'), false);
    if (q.get('painel')) return biAbrirPainel(q.get('painel'), false);
    if (BI.editando) biAlternarEdicao();
    BI.atual = null; document.getElementById('vPainel').classList.add('hidden'); document.getElementById('vInicio').classList.remove('hidden');
    if (q.get('pasta')) BI.filtroLista = q.get('pasta');
    biDesenharPastas(); biDesenharLista();
    if (q.get('aba') === 'relatorios') biAba('relatorios');
}
window.addEventListener('popstate', biRota);
biCarregarInicio().then(biRota);
</script>

<?php include 'includes/exportar-excel.php'; ?>
<?php include 'includes/footer.php'; ?>
