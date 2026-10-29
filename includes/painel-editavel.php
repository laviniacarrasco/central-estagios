<?php
/**
 * =============================================================================
 * PAINEL COM TELA PRÓPRIA, EDITÁVEL (usado em admin-dashboard.php)
 * -----------------------------------------------------------------------------
 * Uso: $painelEditavelId = 'alunos'; include 'includes/painel-editavel.php';
 *
 * A tela continua exatamente como foi feita. "Editar painel" permite:
 *   - mudar a ordem dos blocos (arrastar, ou setas)
 *   - esconder e mostrar blocos
 *   - renomear o título de um bloco
 *   - mudar a largura (1 a 4 colunas) dentro da grade
 *   - ADICIONAR VISUAIS novos, de qualquer fonte (Central, relatórios do
 *     TOTVS, planilhas ou banco/data lake), no fim do painel
 * Tudo fica salvo no painel (data/bi/paineis.json, campo "layout") e vale
 * para todos os funcionários. "Restaurar padrão" volta à tela original.
 * =============================================================================
 */
$painelEditavelId = $painelEditavelId ?? 'alunos';
?>
<style>
    .pe-bloco { position: relative; }
    .pe-editando .pe-bloco { outline: 2px dashed #4A9FCA66; outline-offset: 3px; cursor: grab; }
    .pe-editando .pe-bloco.pe-oculto { display: block !important; opacity: .35; }
    .pe-oculto { display: none !important; }
    .pe-barra { display: none; position: absolute; top: 6px; right: 6px; z-index: 20; gap: 4px; background: #fff; border: 1px solid #e4e7ec;
                border-radius: 10px; padding: 3px; box-shadow: 0 4px 12px rgba(16,24,40,.12); align-items: center; }
    .pe-editando .pe-barra { display: flex; }
    .pe-barra button { width: 26px; height: 26px; border-radius: 7px; color: #667085; font-size: 12px; }
    .pe-barra button:hover { background: #f2f4f7; color: #2B7FA6; }
    .pe-barra select { height: 26px; font-size: 11px; border: 1px solid #e4e7ec; border-radius: 7px; padding: 0 4px; }
    .pe-arrastando { opacity: .4; }
    #peExtras { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px; }
    @media (max-width: 1100px) { #peExtras { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px)  { #peExtras { grid-template-columns: 1fr; } #peExtras > * { grid-column: span 1 !important; } }
    .pe-extra { background: #fff; border: 1px solid #eef0f3; border-radius: 16px; padding: 18px; min-width: 0; }
    .pe-campo { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13px; background: #fff; }
    .pe-rot { display: block; font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin: 12px 0 4px; }
</style>

<!-- Janela: visual novo / editar visual -->
<div id="peModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-7 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-2">
            <h3 id="peModalTitulo" class="text-xl font-bold text-slate-900">Adicionar visual</h3>
            <button type="button" onclick="peFecharModal()" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-xs text-gray-400">Use os dados da Central ou um relatório (TOTVS, planilha ou banco/data lake).</p>
        <label class="pe-rot">Título</label><input id="peTitulo" class="pe-campo" maxlength="80">
        <label class="pe-rot">Tipo</label>
        <select id="peTipo" class="pe-campo">
            <option value="cartao">Cartão (um número)</option><option value="colunas">Colunas</option><option value="barras">Barras</option>
            <option value="linha">Linha</option><option value="pizza">Pizza</option><option value="rosca">Rosca</option><option value="tabela">Tabela</option>
        </select>
        <label class="pe-rot">Fonte de dados</label><select id="peFonte" class="pe-campo"></select>
        <div id="peBlocoEixo"><label class="pe-rot">Agrupar por</label><select id="peEixo" class="pe-campo"></select></div>
        <label class="pe-rot">Valor</label>
        <select id="peAgg" class="pe-campo">
            <option value="contagem">Contagem de linhas</option><option value="distinto">Contagem distinta de</option>
            <option value="soma">Soma de</option><option value="media">Média de</option><option value="pct">% de linhas em que</option>
        </select>
        <select id="peCampo" class="pe-campo mt-1.5"></select>
        <input id="peValor" class="pe-campo mt-1.5" placeholder="Valores que contam (separe por vírgula)">
        <div class="grid grid-cols-3 gap-2">
            <div><label class="pe-rot">Largura</label><select id="peLargura" class="pe-campo"><option value="1">1/4</option><option value="2">2/4</option><option value="3">3/4</option><option value="4">Inteira</option></select></div>
            <div><label class="pe-rot">Mostrar só</label><select id="peLimite" class="pe-campo"><option value="0">Todos</option><option value="5">Top 5</option><option value="10">Top 10</option><option value="15">Top 15</option></select></div>
            <div><label class="pe-rot">Cor</label><input id="peCor" type="color" class="pe-campo p-1" value="#4A9FCA"></div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
            <button type="button" onclick="peFecharModal()" class="h-10 px-4 border border-gray-200 rounded-xl text-sm text-gray-600">Cancelar</button>
            <button type="button" onclick="peSalvarVisual()" class="h-10 px-5 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold">Salvar visual</button>
        </div>
    </div>
</div>

<script>
(function () {
    var PAINEL = <?= json_encode($painelEditavelId) ?>;
    var H = { 'X-Requested-With': 'fetch' };
    var CORES = ['#4A9FCA', '#27AE60', '#E67E22', '#E35444', '#8E44AD', '#F1C40F', '#2B7FA6', '#16A085', '#D35400', '#7F8C8D'];
    var main = document.querySelector('main');
    if (!main) return;
    var raiz = main.firstElementChild || main;
    var L = { ordem: {}, ocultos: [], titulos: {}, larguras: {}, extras: [] };
    var fontes = {}, cache = {}, editando = false, timer = null, editandoExtra = null, charts = {};

    function el(t, c, x) { var e = document.createElement(t); if (c) e.className = c; if (x !== undefined) e.textContent = x; return e; }
    function get(u) { return fetch(u, { headers: H, cache: 'no-store' }).then(function (r) { return r.json(); }); }
    function post(d) { var f = new FormData(); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch('bi-dados.php', { method: 'POST', headers: H, body: f }).then(function (r) { return r.json(); }); }
    function avisar(t) { window.fsaAviso ? fsaAviso(t, 'Aviso') : alert(t); }

    /* ---------- 1. Identificar os blocos (ordem original da tela) ---------- */
    var SEL = '.stat-card, .panel-card';
    var blocos = Array.prototype.filter.call(raiz.querySelectorAll(SEL), function (b) { return !(b.parentElement && b.parentElement.closest(SEL)); });
    var grupos = [];
    blocos.forEach(function (b, i) {
        b.dataset.bloco = 'b' + (i + 1);
        b.classList.add('pe-bloco');
        if (grupos.indexOf(b.parentElement) < 0) grupos.push(b.parentElement);
    });
    grupos.forEach(function (g, i) { g.dataset.peGrupo = 'g' + (i + 1); });
    var porId = {}; blocos.forEach(function (b) { porId[b.dataset.bloco] = b; });
    function blocosDe(g) { return Array.prototype.filter.call(g.children, function (c) { return c.dataset && c.dataset.bloco; }); }
    /* Reordena só os blocos, mantendo as outras coisas do grupo (títulos, grades) no lugar */
    function reordenar(g, ids) {
        var atuais = blocosDe(g);
        var desejados = ids.map(function (id) { return porId[id]; }).filter(function (b) { return b && b.parentElement === g; });
        atuais.forEach(function (b) { if (desejados.indexOf(b) < 0) desejados.push(b); });
        var marcas = atuais.map(function (b) { var m = document.createComment('pe'); g.insertBefore(m, b); return m; });
        atuais.forEach(function (b) { g.removeChild(b); });
        marcas.forEach(function (m, i) { g.insertBefore(desejados[i], m); g.removeChild(m); });
    }
    function trocar(a, b) {                              // troca dois blocos de lugar
        var m = document.createComment('pe');
        a.parentElement.insertBefore(m, a); b.parentElement.insertBefore(a, b); m.parentElement.insertBefore(b, m); m.remove();
    }
    function vizinho(b, dir) {
        var lista = blocosDe(b.parentElement), i = lista.indexOf(b);
        return lista[i + dir] || null;
    }
    var ordemOriginal = {};
    grupos.forEach(function (g) { ordemOriginal[g.dataset.peGrupo] = blocosDe(g).map(function (b) { return b.dataset.bloco; }); });
    var original = {};                                   // para "Restaurar padrão" sem recarregar
    blocos.forEach(function (b) { var t = tituloEl(b); original[b.dataset.bloco] = { titulo: t ? textoTitulo(t) : '', pai: b.parentElement }; });

    function tituloEl(b) { return b.querySelector('.panel-title, .stat-label'); }
    function noTexto(t) {
        var w = document.createTreeWalker(t, NodeFilter.SHOW_TEXT, null), n;
        while ((n = w.nextNode())) if (n.nodeValue.trim() && !n.parentElement.closest('.fake-data-tag, .real-data-tag, button')) return n;
        return null;
    }
    function textoTitulo(t) { var n = noTexto(t); return n ? n.nodeValue.trim() : ''; }
    function mudarTitulo(b, texto) { var t = tituloEl(b), n = t && noTexto(t); if (n) n.nodeValue = ' ' + texto + ' '; }

    /* ---------- 2. Aplicar a arrumação salva ---------- */
    function aplicar() {
        grupos.forEach(function (g) {
            reordenar(g, (L.ordem || {})[g.dataset.peGrupo] || ordemOriginal[g.dataset.peGrupo]);
        });
        blocos.forEach(function (b) {
            var id = b.dataset.bloco;
            b.classList.toggle('pe-oculto', (L.ocultos || []).indexOf(id) >= 0);
            var tit = (L.titulos || {})[id];
            mudarTitulo(b, tit || original[id].titulo);
            var w = (L.larguras || {})[id];
            b.style.gridColumn = w ? 'span ' + w : '';
        });
        desenharExtras();
        window.dispatchEvent(new Event('resize'));        // os gráficos se ajustam ao novo tamanho
    }
    function guardarOrdem() {
        L.ordem = {};
        grupos.forEach(function (g) {
            L.ordem[g.dataset.peGrupo] = blocosDe(g).map(function (c) { return c.dataset.bloco; });
        });
    }
    function salvar() {
        clearTimeout(timer);
        status('Salvando...');
        timer = setTimeout(function () {
            post({ action: 'salvar_layout', id: PAINEL, layout: JSON.stringify(L) }).then(function (d) { status(d.ok ? 'Salvo' : (d.erro || 'Não foi possível salvar')); });
        }, 600);
    }

    /* ---------- 3. Barra de edição em cada bloco ---------- */
    function botao(icone, titulo, fn) { var b = el('button'); b.type = 'button'; b.title = titulo; b.appendChild(el('i', 'fas ' + icone)); b.onclick = function (e) { e.stopPropagation(); e.preventDefault(); fn(); }; return b; }
    blocos.forEach(function (b) {
        var id = b.dataset.bloco, barra = el('div', 'pe-barra');
        barra.append(
            botao('fa-arrow-up', 'Mover para antes', function () { var p = vizinho(b, -1); if (p) { trocar(b, p); guardarOrdem(); salvar(); window.dispatchEvent(new Event('resize')); } }),
            botao('fa-arrow-down', 'Mover para depois', function () { var n = vizinho(b, 1); if (n) { trocar(b, n); guardarOrdem(); salvar(); window.dispatchEvent(new Event('resize')); } }),
            botao('fa-pen', 'Renomear', function () {
                var atual = (L.titulos || {})[id] || original[id].titulo;
                var novo = prompt('Novo título do bloco (deixe vazio para voltar ao original):', atual);
                if (novo === null) return;
                L.titulos = L.titulos || {};
                if (novo.trim() && novo.trim() !== original[id].titulo) L.titulos[id] = novo.trim().slice(0, 80); else delete L.titulos[id];
                aplicar(); salvar();
            }),
            botao('fa-eye-slash', 'Esconder / mostrar', function () {
                L.ocultos = L.ocultos || [];
                var i = L.ocultos.indexOf(id);
                if (i >= 0) L.ocultos.splice(i, 1); else L.ocultos.push(id);
                aplicar(); salvar();
            })
        );
        if (getComputedStyle(b.parentElement).display === 'grid') {
            var s = el('select'); s.title = 'Largura';
            [['', 'Largura'], ['1', '1 col'], ['2', '2 col'], ['3', '3 col'], ['4', '4 col']].forEach(function (o) { s.appendChild(new Option(o[1], o[0])); });
            s.value = String((L.larguras || {})[id] || '');
            s.onclick = function (e) { e.stopPropagation(); };
            s.onchange = function () { L.larguras = L.larguras || {}; if (s.value) L.larguras[id] = +s.value; else delete L.larguras[id]; aplicar(); salvar(); };
            barra.appendChild(s);
            b._peLargura = s;
        }
        b.appendChild(barra);

        // arrastar (só dentro do mesmo grupo)
        b.addEventListener('dragstart', function (e) { if (!editando) return; e.dataTransfer.setData('text/plain', id); b.classList.add('pe-arrastando'); });
        b.addEventListener('dragend', function () { b.classList.remove('pe-arrastando'); });
        b.addEventListener('dragover', function (e) { if (editando) e.preventDefault(); });
        b.addEventListener('drop', function (e) {
            if (!editando) return;
            e.preventDefault();
            var de = porId[e.dataTransfer.getData('text/plain')];
            if (!de || de === b || de.parentElement !== b.parentElement) return;
            var ids = blocosDe(b.parentElement).map(function (x) { return x.dataset.bloco; });
            ids.splice(ids.indexOf(de.dataset.bloco), 1);
            var r = b.getBoundingClientRect(), depois = (e.clientX - r.left) > r.width / 2;
            ids.splice(ids.indexOf(b.dataset.bloco) + (depois ? 1 : 0), 0, de.dataset.bloco);
            reordenar(b.parentElement, ids);
            guardarOrdem(); salvar(); window.dispatchEvent(new Event('resize'));
        });
    });

    /* ---------- 4. Botões no topo ---------- */
    var cab = raiz.querySelector('h2') ? raiz.querySelector('h2').closest('.mb-6') || raiz.querySelector('h2').parentElement : raiz;
    var topo = el('div', 'flex flex-wrap items-center gap-2 mt-3');
    var st = el('span', 'text-xs text-gray-400 mr-1');
    function status(t) { st.textContent = t; }
    var bEditar = el('button', 'h-10 px-4 inline-flex items-center gap-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold');
    bEditar.type = 'button';
    var soEdicao = el('span', 'hidden flex flex-wrap gap-2');
    var bAdd = el('button', 'h-10 px-4 inline-flex items-center gap-2 border border-[#4A9FCA] text-[#2B7FA6] rounded-xl text-sm font-semibold bg-white'); bAdd.type = 'button';
    bAdd.append(el('i', 'fas fa-plus'), 'Adicionar visual'); bAdd.onclick = function () { abrirModal(null); };
    var bRest = el('button', 'h-10 px-4 inline-flex items-center gap-2 border border-gray-200 text-gray-600 rounded-xl text-sm bg-white'); bRest.type = 'button';
    bRest.append(el('i', 'fas fa-undo'), 'Restaurar padrão');
    bRest.onclick = function () {
        var fazer = function () {
            L = { ordem: {}, ocultos: [], titulos: {}, larguras: {}, extras: [] };   // aplicar() volta à ordem original
            blocos.forEach(function (b) { if (b._peLargura) b._peLargura.value = ''; });
            aplicar(); salvar();
        };
        window.fsaConfirmar ? fsaConfirmar({ titulo: 'Restaurar o painel padrão?', texto: 'A ordem, os títulos, as larguras e os blocos escondidos voltam ao original, e os visuais adicionados são apagados.', botao: 'Restaurar', tipo: 'aviso', icone: 'fa-undo' }, fazer) : (confirm('Restaurar o padrão?') && fazer());
    };
    soEdicao.append(bAdd, bRest);
    function rotuloEditar() { bEditar.innerHTML = ''; bEditar.append(el('i', 'fas ' + (editando ? 'fa-check' : 'fa-pen')), editando ? 'Concluir edição' : 'Editar painel'); }
    bEditar.onclick = function () {
        editando = !editando;
        document.body.classList.toggle('pe-editando', editando);
        blocos.forEach(function (b) { b.draggable = editando; });
        soEdicao.classList.toggle('hidden', !editando);
        rotuloEditar(); desenharExtras();
        status(editando ? 'Arraste os blocos, use as setas, esconda ou renomeie. Tudo é salvo sozinho.' : '');
    };
    rotuloEditar();
    topo.append(bEditar, soEdicao, st);
    cab.appendChild(topo);

    /* ---------- 5. Visuais adicionados ---------- */
    var secao = el('div', 'mb-8 hidden');
    var tit = el('h3', 'text-lg font-bold text-gray-900 mb-3'); tit.append(el('i', 'fas fa-plus-square mr-2 text-[#4A9FCA]'), 'Visuais adicionados');
    var grade = el('div'); grade.id = 'peExtras';
    secao.append(tit, grade);
    raiz.appendChild(secao);

    function fonte(id) { if (!cache[id]) cache[id] = get('bi-dados.php?fonte=' + encodeURIComponent(id)); return cache[id]; }
    function lista(t) { return String(t || '').split(',').map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean); }
    function medir(linhas, w) {
        var nums = function () { return linhas.map(function (l) { return parseFloat(l[w.campo]); }).filter(function (n) { return !isNaN(n); }); };
        if (w.agg === 'distinto') return new Set(linhas.map(function (l) { return l[w.campo]; })).size;
        if (w.agg === 'soma') return nums().reduce(function (s, n) { return s + n; }, 0);
        if (w.agg === 'media') { var n = nums(); return n.length ? n.reduce(function (s, x) { return s + x; }, 0) / n.length : 0; }
        if (w.agg === 'pct') { if (!linhas.length) return 0; var v = lista(w.valor); return linhas.filter(function (l) { return v.indexOf(String(l[w.campo] ?? '').toLowerCase()) >= 0; }).length / linhas.length * 100; }
        return linhas.length;
    }
    function agrupar(linhas, w) {
        var g = {};
        linhas.forEach(function (l) { var k = String(l[w.eixo] ?? '') || '(vazio)'; (g[k] = g[k] || []).push(l); });
        var pares = Object.keys(g).map(function (k) { return [k, medir(g[k], w)]; });
        pares.sort(w.eixo === 'mes' || w.eixo === 'dia' ? function (a, b) { return a[0].localeCompare(b[0]); } : function (a, b) { return b[1] - a[1]; });
        return +w.limite > 0 ? pares.slice(0, +w.limite) : pares;
    }
    function fmt(n, w) { return (+n).toLocaleString('pt-BR', { maximumFractionDigits: (w.agg === 'media' || w.agg === 'pct') ? 1 : 2 }) + (w.agg === 'pct' ? '%' : ''); }

    function desenharExtras() {
        Object.keys(charts).forEach(function (k) { charts[k].destroy(); }); charts = {};
        grade.innerHTML = '';
        var ex = L.extras || [];
        secao.classList.toggle('hidden', !ex.length && !editando);
        if (!ex.length) { grade.appendChild(el('p', 'text-sm text-gray-400', 'Nenhum visual adicionado. Use "Adicionar visual".')); return; }
        ex.forEach(function (w, idx) {
            var card = el('div', 'pe-extra bi-card'); card.style.gridColumn = 'span ' + Math.min(4, Math.max(1, +w.largura || 1));
            var top = el('div', 'flex items-start justify-between gap-2 mb-2');
            top.appendChild(el('p', 'text-sm font-semibold text-gray-600', w.titulo || 'Sem título'));
            var acoes = el('div', 'flex gap-1');
            if (editando) {
                acoes.append(botao('fa-arrow-left', 'Antes', function () { if (idx > 0) { ex.splice(idx - 1, 0, ex.splice(idx, 1)[0]); desenharExtras(); salvar(); } }),
                             botao('fa-arrow-right', 'Depois', function () { if (idx < ex.length - 1) { ex.splice(idx + 1, 0, ex.splice(idx, 1)[0]); desenharExtras(); salvar(); } }),
                             botao('fa-pen', 'Editar', function () { abrirModal(idx); }),
                             botao('fa-trash-alt', 'Excluir', function () { ex.splice(idx, 1); desenharExtras(); salvar(); }));
                acoes.querySelectorAll('button').forEach(function (b) { b.className = 'w-7 h-7 rounded-lg text-gray-400 hover:bg-gray-100 text-xs'; });
            }
            top.appendChild(acoes); card.appendChild(top);
            var corpo = el('div'); corpo.appendChild(el('p', 'text-xs text-gray-300', 'Calculando...')); card.appendChild(corpo);
            grade.appendChild(card);
            fonte(w.fonte).then(function (f) {
                corpo.innerHTML = '';
                if (!f.ok) { corpo.appendChild(el('p', 'text-xs text-red-500', f.erro || 'Erro na fonte.')); return; }
                var linhas = f.linhas;
                card.fsaExportar = function () {                     // botão direito > Exportar
                    var aoa = w.tipo === 'cartao' ? [['Indicador', 'Valor'], [w.titulo, +medir(linhas, w).toFixed(2)]]
                        : [[(f.campos[w.eixo] || {}).rotulo || 'Item', 'Valor']].concat(agrupar(linhas, w).map(function (p) { return [p[0], +p[1].toFixed(2)]; }));
                    var cs = Object.keys(f.campos);
                    return { nome: w.titulo || 'Visual', aoa: aoa, detalhado: function () { return { nome: (w.titulo || 'Visual') + ' - linhas', aoa: [cs.map(function (c) { return f.campos[c].rotulo; })].concat(linhas.map(function (l) { return cs.map(function (c) { return l[c] ?? ''; }); })) }; } };
                };
                if (w.tipo === 'cartao') {
                    var n = el('p', 'text-4xl font-bold', fmt(medir(linhas, w), w)); n.style.color = w.cor || '#1F2D3A';
                    corpo.append(n, el('p', 'text-xs text-gray-400 mt-1', f.nome + ' · ' + linhas.length + ' linha(s)'));
                    return;
                }
                if (!w.eixo || !f.campos[w.eixo]) { corpo.appendChild(el('p', 'text-xs text-amber-600', 'Escolha o campo de agrupamento (Editar).')); return; }
                var pares = agrupar(linhas, w);
                if (!pares.length) { corpo.appendChild(el('p', 'text-sm text-gray-400', 'Sem dados.')); return; }
                if (w.tipo === 'tabela') {
                    var tb = el('table', 'w-full text-sm'), h = tb.insertRow();
                    h.append(el('th', 'text-left py-1 text-xs text-gray-500', f.campos[w.eixo].rotulo), el('th', 'text-right py-1 text-xs text-gray-500', 'Valor'));
                    pares.forEach(function (p) { var r = tb.insertRow(); r.className = 'border-t border-gray-50'; var a = r.insertCell(); a.className = 'py-1.5'; a.textContent = p[0]; var b = r.insertCell(); b.className = 'py-1.5 text-right font-semibold'; b.textContent = fmt(p[1], w); });
                    var box = el('div', 'max-h-64 overflow-y-auto'); box.appendChild(tb); corpo.appendChild(box); return;
                }
                if (!window.Chart) { corpo.appendChild(el('p', 'text-xs text-red-500', 'Chart.js não carregou.')); return; }
                var cx = el('div'); cx.style.position = 'relative'; cx.style.height = '260px'; var cv = el('canvas'); cx.appendChild(cv); corpo.appendChild(cx);
                var circ = w.tipo === 'pizza' || w.tipo === 'rosca';
                charts[idx] = new Chart(cv, {
                    type: { barras: 'bar', colunas: 'bar', linha: 'line', pizza: 'pie', rosca: 'doughnut' }[w.tipo] || 'bar',
                    data: { labels: pares.map(function (p) { return p[0]; }), datasets: [{ label: w.titulo, data: pares.map(function (p) { return +p[1].toFixed(2); }),
                        backgroundColor: circ ? pares.map(function (_, i) { return CORES[i % CORES.length]; }) : (w.cor || '#4A9FCA'), borderColor: w.cor || '#4A9FCA', borderRadius: circ ? 0 : 6, tension: .3 }] },
                    options: { responsive: true, maintainAspectRatio: false, indexAxis: w.tipo === 'barras' ? 'y' : 'x',
                        plugins: { legend: { display: circ, position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } },
                        scales: circ ? {} : { x: { beginAtZero: true, ticks: { font: { size: 10 } } }, y: { beginAtZero: true, ticks: { font: { size: 10 } } } } },
                });
            });
        });
    }

    /* ---------- 6. Janela do visual ---------- */
    function $(id) { return document.getElementById(id); }
    function opcoes(sel, pares, valor) { sel.innerHTML = ''; pares.forEach(function (p) { var o = new Option(p[1], p[0]); if (String(p[0]) === String(valor)) o.selected = true; sel.appendChild(o); }); }
    function camposDaFonte(valorEixo, valorCampo) {
        fonte($('peFonte').value).then(function (f) {
            var pares = f.ok ? Object.keys(f.campos).map(function (k) { return [k, f.campos[k].rotulo]; }) : [];
            opcoes($('peEixo'), [['', 'Escolha...']].concat(pares), valorEixo);
            opcoes($('peCampo'), [['', 'Escolha o campo...']].concat(pares), valorCampo);
        });
    }
    function ajustar() {
        $('peBlocoEixo').classList.toggle('hidden', $('peTipo').value === 'cartao');
        $('peCampo').classList.toggle('hidden', $('peAgg').value === 'contagem');
        $('peValor').classList.toggle('hidden', $('peAgg').value !== 'pct');
    }
    function abrirModal(idx) {
        editandoExtra = idx;
        var w = idx === null ? { titulo: 'Novo visual', tipo: 'colunas', fonte: Object.keys(fontes)[0] || 'alunos', eixo: 'curso', agg: 'contagem', campo: '', valor: '', largura: 2, limite: 0, cor: '#4A9FCA' } : L.extras[idx];
        $('peModalTitulo').textContent = idx === null ? 'Adicionar visual' : 'Editar visual';
        $('peTitulo').value = w.titulo || ''; $('peTipo').value = w.tipo; $('peAgg').value = w.agg || 'contagem';
        $('peValor').value = w.valor || ''; $('peLargura').value = String(w.largura || 1); $('peLimite').value = String(w.limite || 0); $('peCor').value = w.cor || '#4A9FCA';
        $('peFonte').innerHTML = '';
        [['Tabelas', false], ['Relatórios', true]].forEach(function (g) {
            var og = document.createElement('optgroup'); og.label = g[0];
            Object.keys(fontes).filter(function (k) { return (k.indexOf('rel:') === 0) === g[1]; }).forEach(function (k) {
                var o = new Option(fontes[k].replace(/^Relatório: /, ''), k); if (k === w.fonte) o.selected = true; og.appendChild(o);
            });
            if (og.children.length) $('peFonte').appendChild(og);
        });
        camposDaFonte(w.eixo, w.campo); ajustar();
        var m = $('peModal'); m.classList.remove('hidden'); m.classList.add('flex');
    }
    window.peFecharModal = function () { var m = $('peModal'); m.classList.add('hidden'); m.classList.remove('flex'); };
    window.peSalvarVisual = function () {
        var w = { id: 'x' + Date.now(), titulo: $('peTitulo').value.trim() || 'Visual', tipo: $('peTipo').value, fonte: $('peFonte').value,
                  eixo: $('peEixo').value, agg: $('peAgg').value, campo: $('peCampo').value, valor: $('peValor').value,
                  largura: +$('peLargura').value, limite: +$('peLimite').value, cor: $('peCor').value };
        if (w.tipo !== 'cartao' && !w.eixo) return avisar('Escolha o campo de agrupamento.');
        if (w.agg !== 'contagem' && !w.campo) return avisar('Escolha o campo do valor.');
        L.extras = L.extras || [];
        if (editandoExtra === null) L.extras.push(w); else { w.id = L.extras[editandoExtra].id; L.extras[editandoExtra] = w; }
        peFecharModal(); desenharExtras(); salvar();
    };
    $('peFonte').addEventListener('change', function () { camposDaFonte('', ''); });
    $('peTipo').addEventListener('change', ajustar);
    $('peAgg').addEventListener('change', ajustar);

    /* ---------- 7. Carregar ---------- */
    get('bi-dados.php?layout=' + encodeURIComponent(PAINEL)).then(function (d) {
        if (!d.ok) { status(d.erro || ''); return; }
        fontes = d.fontes || {};
        var l = d.layout || {};
        L = { ordem: l.ordem || {}, ocultos: l.ocultos || [], titulos: l.titulos || {}, larguras: l.larguras || {}, extras: l.extras || [] };
        blocos.forEach(function (b) { if (b._peLargura) b._peLargura.value = String(L.larguras[b.dataset.bloco] || ''); });
        aplicar();
    }).catch(function () { status('Não foi possível carregar a arrumação salva.'); });
})();
</script>
