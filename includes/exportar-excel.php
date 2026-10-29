<?php
/**
 * =============================================================================
 * BOTÃO DIREITO > EXPORTAR PARA EXCEL (usado nos painéis)
 * -----------------------------------------------------------------------------
 * Uso: include 'includes/exportar-excel.php';  (antes do footer)
 *
 * Funciona sozinho em qualquer bloco com a classe .stat-card, .panel-card ou
 * .bi-card, ou com o atributo data-exportar="Nome do bloco". Ele lê:
 *   tabela  -> as linhas (sem a coluna "Ação")
 *   gráfico -> os rótulos e os valores (Chart.js)
 *   lista   -> os textos de cada item
 *   números -> pares indicador / valor
 * Uma tela pode ensinar o próprio jeito: elemento.fsaExportar = () => ({ nome, aoa, detalhado })
 * (aoa = lista de linhas; detalhado = função opcional que devolve outro { nome, aoa }).
 * =============================================================================
 */
?>
<script>if (!window.XLSX) document.write('<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"><\/script>');</script>
<style>
    #fsaMenuExcel { position: fixed; z-index: 1000; min-width: 250px; background: #fff; border: 1px solid #e4e7ec; border-radius: 12px;
                    box-shadow: 0 12px 32px rgba(16,24,40,.16); padding: 6px; font-size: 13px; display: none; }
    #fsaMenuExcel button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 12px; border-radius: 8px; color: #344054; text-align: left; }
    #fsaMenuExcel button:hover { background: #f2f4f7; }
    #fsaMenuExcel .fsa-menu-tit { padding: 6px 12px 8px; font-size: 11px; font-weight: 700; color: #98a2b3; text-transform: uppercase; border-bottom: 1px solid #f2f4f7; margin-bottom: 4px;
                                  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px; }
    .fsa-exportando { outline: 2px dashed #22a06b; outline-offset: 3px; }
</style>
<div id="fsaMenuExcel" role="menu"></div>
<script>
(function () {
    var SELETOR = '[data-exportar], .bi-card, .panel-card, .stat-card';
    var menu = document.getElementById('fsaMenuExcel');
    var alvo = null;

    function limpo(t) { return String(t || '').replace(/\s+/g, ' ').trim(); }
    function numero(v) {                                   // "12" e "3,5" viram número no Excel
        var t = limpo(v);
        return /^-?\d+([.,]\d+)?$/.test(t) ? Number(t.replace(',', '.')) : t;
    }

    function nomeDoBloco(el) {
        if (el.getAttribute('data-exportar')) return el.getAttribute('data-exportar');
        var t = el.querySelector('.panel-title, .stat-label, p.font-semibold, h3, h4');
        if (!t) return 'Dados';
        var c = t.cloneNode(true);
        c.querySelectorAll('button, .fake-data-tag, .real-data-tag').forEach(function (x) { x.remove(); });
        return limpo(c.textContent) || 'Dados';
    }

    /* ---------- leitura automática de um bloco ---------- */
    function lerTabela(tb) {
        var linhas = Array.prototype.map.call(tb.rows, function (r) { return Array.prototype.map.call(r.cells, function (c) { return limpo(c.innerText); }); });
        if (!linhas.length) return [];
        var tirar = [];
        linhas[0].forEach(function (h, i) { if (/^aç(ão|ões)$/i.test(h)) tirar.push(i); });
        return linhas.filter(function (l) { return l.length > 1 || linhas.length === 1; })
                     .map(function (l) { return l.filter(function (_, i) { return tirar.indexOf(i) < 0; }).map(numero); });
    }
    function lerGrafico(cv) {
        var ch = window.Chart && Chart.getChart ? Chart.getChart(cv) : null;
        if (!ch) return [];
        var ds = ch.data.datasets || [];
        var cab = ['Item'].concat(ds.map(function (d, i) { return d.label || (ds.length > 1 ? 'Série ' + (i + 1) : 'Valor'); }));
        return [cab].concat((ch.data.labels || []).map(function (rot, i) {
            return [String(rot)].concat(ds.map(function (d) { var v = d.data[i]; return typeof v === 'object' && v ? (v.y ?? v.x) : v; }));
        }));
    }
    function lerLista(box) {
        return Array.prototype.map.call(box.children, function (item) {
            var folhas = Array.prototype.filter.call(item.querySelectorAll('*'), function (e) {
                return !e.children.length && limpo(e.textContent) && !e.closest('.mini-progress-track');
            });
            return (folhas.length ? folhas : [item]).map(function (e) { return numero(e.textContent); });
        }).filter(function (l) { return l.length; });
    }
    function lerNumeros(el) {
        var pares = [];
        el.querySelectorAll('.stat-value').forEach(function (v) {
            var caixa = v.parentElement, rot = caixa.querySelector('.stat-label');
            pares.push([limpo(rot ? rot.textContent : ''), numero(v.textContent)]);
        });
        return pares.length ? [['Indicador', 'Valor']].concat(pares) : [];
    }

    function lerBloco(el) {
        if (typeof el.fsaExportar === 'function') return Promise.resolve(el.fsaExportar());
        var nome = nomeDoBloco(el), partes = [];
        var tb = el.querySelector('table'), cv = el.querySelector('canvas'), lista = el.querySelector('.space-y-3');
        if (!el.classList.contains('stat-card') && el.querySelector('.stat-value') && !cv) partes.push(lerNumeros(el));
        if (el.classList.contains('stat-card')) partes.push([['Indicador', 'Valor'], [nome, numero((el.querySelector('.stat-value') || {}).textContent)]]);
        if (tb) partes.push(lerTabela(tb));
        else if (cv) partes.push(lerGrafico(cv));
        else if (lista) partes.push(lerLista(lista));
        var aoa = [];
        partes.filter(function (p) { return p.length; }).forEach(function (p, i) { if (i) aoa.push([]); aoa = aoa.concat(p); });
        if (!aoa.length) aoa = limpo(el.innerText).split(/(?<=\.)\s/).map(function (l) { return [l]; });
        return Promise.resolve({ nome: nome, aoa: aoa });
    }

    /* ---------- Excel ---------- */
    function nomeAba(n, usados) {
        var b = String(n || 'Dados').replace(/[\[\]:*?\/\\]/g, ' ').trim().slice(0, 28) || 'Dados', s = b, i = 2;
        while (usados[s.toLowerCase()]) s = b.slice(0, 25) + ' ' + (i++);
        usados[s.toLowerCase()] = true;
        return s;
    }
    function folha(aoa) {
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        var larg = [];
        aoa.forEach(function (l) { l.forEach(function (c, i) { larg[i] = Math.min(60, Math.max(larg[i] || 8, String(c ?? '').length + 2)); }); });
        ws['!cols'] = larg.map(function (w) { return { wch: w }; });
        return ws;
    }
    function arquivo(n) { return String(n || 'dados').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^\w\- ]+/g, '').trim().replace(/\s+/g, '_').slice(0, 60) || 'dados'; }
    function baixar(blocos, nomeArq) {
        if (!window.XLSX) { alert('A biblioteca do Excel ainda não carregou. Tente de novo em alguns segundos.'); return; }
        var wb = XLSX.utils.book_new(), usados = {};
        blocos.forEach(function (b) { XLSX.utils.book_append_sheet(wb, folha(b.aoa.length ? b.aoa : [['Sem dados']]), nomeAba(b.nome, usados)); });
        var d = new Date(), hoje = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        XLSX.writeFile(wb, arquivo(nomeArq) + '_' + hoje + '.xlsx');
    }
    function blocosDaPagina() {
        return Array.prototype.filter.call(document.querySelectorAll(SELETOR), function (el) {
            return el.offsetParent !== null && !el.closest('#fsaMenuExcel') && !(el.parentElement && el.parentElement.closest(SELETOR));
        });
    }

    var fsaExcel = {
        exportar: function (el) { return lerBloco(el).then(function (b) { baixar([b], b.nome); }); },
        detalhado: function (el) { return lerBloco(el).then(function (b) { return b.detalhado ? Promise.resolve(b.detalhado()) : b; }).then(function (b) { baixar([b], b.nome); }); },
        tudo: function () {
            var titulo = (document.querySelector('main h2') || {}).textContent || document.title;
            return Promise.all(blocosDaPagina().map(lerBloco)).then(function (bs) { baixar(bs, limpo(titulo)); });
        },
    };
    window.fsaExcel = fsaExcel;

    /* ---------- menu do botão direito ---------- */
    function item(icone, texto, fn) {
        var b = document.createElement('button'); b.type = 'button';
        var i = document.createElement('i'); i.className = 'fas ' + icone; i.style.color = '#22a06b'; i.style.width = '16px';
        b.append(i, document.createTextNode(texto));
        b.onclick = function () { var el = alvo; fechar(); fn(el); };
        menu.appendChild(b);
    }
    function fechar() { menu.style.display = 'none'; if (alvo) alvo.classList.remove('fsa-exportando'); alvo = null; }

    document.addEventListener('contextmenu', function (e) {
        var el = e.target.closest && e.target.closest(SELETOR);
        if (!el || e.target.closest('input, textarea, select')) return;      // em campos, o menu normal do navegador
        e.preventDefault();
        fechar();
        alvo = el; el.classList.add('fsa-exportando');
        menu.innerHTML = '';
        var t = document.createElement('div'); t.className = 'fsa-menu-tit'; t.textContent = nomeDoBloco(el); menu.appendChild(t);
        item('fa-file-excel', 'Exportar este bloco para Excel', fsaExcel.exportar);
        if (el.classList.contains('bi-card')) item('fa-table', 'Exportar as linhas detalhadas', fsaExcel.detalhado);
        item('fa-layer-group', 'Exportar o painel inteiro (uma aba por bloco)', function () { fsaExcel.tudo(); });
        menu.style.display = 'block';
        var x = Math.min(e.clientX, innerWidth - menu.offsetWidth - 8), y = Math.min(e.clientY, innerHeight - menu.offsetHeight - 8);
        menu.style.left = x + 'px'; menu.style.top = y + 'px';
    });
    document.addEventListener('click', function (e) { if (!menu.contains(e.target)) fechar(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });
    window.addEventListener('scroll', fechar, true);
})();
</script>
