<?php
/**
 * =============================================================================
 * CAIXA DE CONFIGURAÇÕES (engrenagem do topo)
 * -----------------------------------------------------------------------------
 * Abre por cima de QUALQUER página, no mesmo estilo da caixa de "excluir".
 * Salva sem recarregar (configuracoes.php): a pessoa continua onde estava.
 *
 * Abrir: botão da engrenagem, qualquer link com href="#configuracoes"
 *        ("#configuracoes-alertas", "#configuracoes-prefs" ou "#configuracoes-conta"
 *        abrem direto na aba), atributo data-configuracoes="alertas",
 *        ou em JS: fsaConfiguracoes('alertas').
 * Incluído pelo includes/header.php.
 * =============================================================================
 */
?>
<div id="cfgModal" class="fixed inset-0 z-[998] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="cfgTitulo">
    <div id="cfgFundo" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col z-10 overflow-hidden fsa-pop">
        <div class="flex items-center gap-4 px-7 pt-7 pb-5 border-b border-slate-100">
            <div class="w-12 h-12 rounded-2xl bg-[#e8f3fa] text-[#2B7FA6] flex items-center justify-center flex-shrink-0"><i class="fas fa-cog text-xl"></i></div>
            <div class="min-w-0 flex-1">
                <h3 id="cfgTitulo" class="text-xl font-bold text-slate-900">Configurações</h3>
                <p id="cfgSub" class="text-sm text-slate-500 truncate">Carregando...</p>
            </div>
            <button type="button" id="cfgFechar" class="w-10 h-10 rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600" title="Fechar (Esc)" aria-label="Fechar"><i class="fas fa-times"></i></button>
        </div>
        <div id="cfgAbas" class="flex gap-1 px-7 pt-4 overflow-x-auto"></div>
        <div id="cfgCorpo" class="px-7 py-5 overflow-y-auto flex-1">
            <div class="py-16 text-center text-slate-400"><i class="fas fa-circle-notch fa-spin text-2xl"></i></div>
        </div>
        <div id="cfgMsg" class="hidden mx-7 mb-5 px-4 py-3 rounded-xl text-sm" role="status"></div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('cfgModal');
    if (!modal) return;
    var $ = function (id) { return document.getElementById(id); };
    var H = { 'X-Requested-With': 'fetch' };
    var dados = null, focoAnterior = null;

    function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt !== undefined) e.textContent = txt; return e; }
    function msg(texto, ok) {
        var m = $('cfgMsg');
        m.className = 'mx-7 mb-5 px-4 py-3 rounded-xl text-sm ' + (ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
        m.textContent = '';
        m.appendChild(el('i', 'fas ' + (ok ? 'fa-check-circle' : 'fa-exclamation-circle') + ' mr-2'));
        m.appendChild(document.createTextNode(texto));
        clearTimeout(msg.t); msg.t = setTimeout(function () { m.classList.add('hidden'); }, 4500);
    }
    function post(campos) {
        var f = new FormData();
        Object.keys(campos).forEach(function (k) { [].concat(campos[k]).forEach(function (v) { f.append(k, v); }); });
        return fetch('configuracoes.php', { method: 'POST', headers: H, body: f }).then(function (r) { return r.json(); });
    }
    function botao(txt, icone, primario) {
        var b = el('button', 'fsa-btn ' + (primario ? 'fsa-btn-primario' : 'fsa-btn-secundario'));
        b.type = 'button';
        b.appendChild(el('i', 'fas ' + icone + ' mr-2')); b.appendChild(document.createTextNode(txt));
        return b;
    }
    function ocupado(b, sim) { b.disabled = sim; b.classList.toggle('opacity-60', sim); }
    function campo(rotulo, input) {
        var w = el('label', 'block');
        w.appendChild(el('span', 'block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5', rotulo));
        w.appendChild(input);
        return w;
    }
    function entrada(tipo, valor, ph) { var i = el('input', 'fsa-input'); i.type = tipo; i.value = valor || ''; if (ph) i.placeholder = ph; return i; }
    function selecao(opcoes, valor) {
        var s = el('select', 'fsa-input');
        s.appendChild(new Option('Não informado', ''));
        opcoes.forEach(function (o) { var op = new Option(o, o); if (o === valor) op.selected = true; s.appendChild(op); });
        return s;
    }
    function chave(rotulo, desc, ligado) {
        var w = el('label', 'flex items-start gap-4 p-4 rounded-2xl border border-slate-200 hover:border-[#4A9FCA] cursor-pointer transition');
        var cb = el('input', 'fsa-switch mt-0.5'); cb.type = 'checkbox'; cb.checked = !!ligado;
        var t = el('span', 'flex-1');
        t.appendChild(el('span', 'block font-semibold text-slate-900', rotulo));
        t.appendChild(el('span', 'block text-sm text-slate-500 mt-0.5', desc));
        w.appendChild(t); w.appendChild(cb);
        return { caixa: w, cb: cb };
    }
    function salvarCom(b, campos, depois) {
        ocupado(b, true);
        post(campos).then(function (d) { msg(d.ok ? d.msg : d.erro, d.ok); if (d.ok && depois) depois(d); })
            .catch(function () { msg('Não foi possível salvar. Confira sua conexão.', false); })
            .then(function () { ocupado(b, false); });
    }

    var ABAS = {
        alertas: ['Alertas de vagas', 'fa-bell', function (c) {
            var a = dados.alertas || {};
            if (!dados.temEmail) {
                var av = el('div', 'p-4 mb-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl text-sm');
                av.appendChild(el('i', 'fas fa-exclamation-triangle mr-2'));
                av.appendChild(document.createTextNode('Seu cadastro não tem e-mail válido, então os alertas não chegam. Fale com a coordenação.'));
                c.appendChild(av);
            }
            var r = chave('Resumo semanal', 'Toda segunda-feira: vagas novas, vagas salvas que estão encerrando e as que mais combinam com você. Só chega se houver novidade.', a.resumoSemanal);
            var n = chave('Aviso na hora', 'Um e-mail assim que sair uma vaga para os cursos que você acompanha.', a.vagaNova);
            var g = el('div', 'space-y-3 mb-6'); g.appendChild(r.caixa); g.appendChild(n.caixa); c.appendChild(g);

            c.appendChild(el('p', 'font-semibold text-slate-900 mb-1', 'Cursos que quero acompanhar'));
            c.appendChild(el('p', 'text-sm text-slate-500 mb-3', 'Vagas abertas a todos os cursos sempre entram. Sem nenhum marcado, usamos o seu curso' + (dados.curso ? ' (' + dados.curso + ')' : '') + '.'));
            var lista = el('div', 'grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto p-1');
            var marcados = a.cursos || [];
            dados.cursos.forEach(function (curso) {
                var l = el('label', 'flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 cursor-pointer hover:bg-slate-50');
                var cb = el('input', 'w-4 h-4'); cb.type = 'checkbox'; cb.value = curso; cb.checked = marcados.indexOf(curso) >= 0; cb.style.accentColor = '#4A9FCA';
                l.appendChild(cb); l.appendChild(document.createTextNode(curso)); lista.appendChild(l);
            });
            c.appendChild(lista);
            c.appendChild(el('p', 'text-xs text-slate-400 mt-4', 'Os e-mails vão para: ' + (dados.email || '—')));

            var acoes = el('div', 'flex flex-wrap gap-3 mt-5');
            var salvar = botao('Salvar alertas', 'fa-save', true), teste = botao('Enviar resumo de teste', 'fa-paper-plane', false);
            salvar.onclick = function () {
                var cursos = [].slice.call(lista.querySelectorAll('input:checked')).map(function (x) { return x.value; });
                salvarCom(salvar, { acao: 'salvar_alertas', resumoSemanal: r.cb.checked ? '1' : '0', vagaNova: n.cb.checked ? '1' : '0', 'cursos[]': cursos },
                    function () { dados.alertas = { resumoSemanal: r.cb.checked, vagaNova: n.cb.checked, cursos: cursos }; });
            };
            teste.onclick = function () { salvarCom(teste, { acao: 'teste_alertas' }); };
            acoes.appendChild(salvar); acoes.appendChild(teste); c.appendChild(acoes);
        }],

        prefs: ['Disponibilidade e links', 'fa-calendar-check', function (c) {
            var p = dados.prefs || {};
            c.appendChild(el('p', 'text-sm text-slate-500 mb-5', 'Ajuda a coordenação e as recomendações de vagas a entenderem o que você procura.'));
            var g = el('div', 'grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5');
            var turno = selecao(dados.turnos, p.turno), modal = selecao(dados.modalidades, p.modalidade), inicio = entrada('date', p.inicio);
            g.appendChild(campo('Turno disponível', turno)); g.appendChild(campo('Modalidade', modal)); g.appendChild(campo('Posso começar em', inicio));
            c.appendChild(g);
            var areas = entrada('text', p.areas, 'Ex.: dados, marketing, projetos');
            c.appendChild(campo('Áreas de interesse', areas));
            var l = el('div', 'grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5');
            var li = entrada('url', p.linkedin, 'linkedin.com/in/...'), gh = entrada('url', p.github, 'github.com/...'), pf = entrada('url', p.portfolio, 'seusite.com');
            l.appendChild(campo('LinkedIn', li)); l.appendChild(campo('GitHub', gh)); l.appendChild(campo('Portfólio', pf));
            c.appendChild(l);
            var acoes = el('div', 'flex gap-3 mt-6'), salvar = botao('Salvar', 'fa-save', true);
            salvar.onclick = function () {
                salvarCom(salvar, { acao: 'salvar_prefs', turno: turno.value, modalidade: modal.value, inicio: inicio.value, areas: areas.value, linkedin: li.value, github: gh.value, portfolio: pf.value },
                    function (d) { dados.prefs = d.prefs; });
            };
            acoes.appendChild(salvar); c.appendChild(acoes);
        }],

        conta: ['Conta e privacidade', 'fa-user-shield', function (c) {
            var g = el('div', 'grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4');
            [['Nome', dados.nome], ['Perfil', dados.perfil], ['E-mail', dados.email || '—'], ['Curso', dados.curso || '—']].forEach(function (x) {
                var b = el('div', 'p-4 rounded-2xl bg-slate-50');
                b.appendChild(el('p', 'text-xs font-bold text-slate-400 uppercase tracking-wide mb-1', x[0]));
                b.appendChild(el('p', 'text-sm font-semibold text-slate-800 break-words', x[1]));
                g.appendChild(b);
            });
            c.appendChild(g);
            c.appendChild(el('p', 'text-xs text-slate-400 mb-6', 'Algum dado errado? Fale com a coordenação: esses dados vêm do cadastro acadêmico.'));
            if (!dados.podeIa) return;
            var ia = el('div', 'p-4 rounded-2xl border border-slate-200');
            ia.appendChild(el('p', 'font-semibold text-slate-900 mb-1', 'IA do currículo (Gemini)'));
            ia.appendChild(el('p', 'text-sm text-slate-500 mb-3', dados.iaConsentimento
                ? 'Você autorizou em ' + dados.iaConsentimento.split(' ')[0].split('-').reverse().join('/') + ' o envio do currículo, sem nome, e-mail e telefone, para a IA avaliar e sugerir vagas.'
                : 'Você ainda não autorizou. A IA só é usada quando você clica em "Avaliar com IA" ou "Analisar com IA" e concorda com o aviso.'));
            if (dados.iaConsentimento) {
                var rv = botao('Retirar autorização', 'fa-ban', false);
                rv.onclick = function () { salvarCom(rv, { acao: 'revogar_ia' }, function () { dados.iaConsentimento = ''; mostrar('conta'); }); };
                ia.appendChild(rv);
            }
            c.appendChild(ia);
        }]
    };

    function mostrar(aba) {
        var abas = $('cfgAbas'); abas.textContent = '';
        Object.keys(ABAS).forEach(function (k) {
            if (k === 'alertas' && !dados.podeAlertas) return;
            if (k === 'prefs' && !dados.podePrefs) return;
            var b = el('button', 'px-4 py-2 rounded-xl text-sm font-semibold whitespace-nowrap transition ' + (k === aba ? 'bg-[#4A9FCA] text-white' : 'text-slate-500 hover:bg-slate-100'));
            b.type = 'button';
            b.appendChild(el('i', 'fas ' + ABAS[k][1] + ' mr-2')); b.appendChild(document.createTextNode(ABAS[k][0]));
            b.onclick = function () { mostrar(k); };
            abas.appendChild(b);
        });
        var corpo = $('cfgCorpo'); corpo.textContent = '';
        ABAS[aba][2](corpo);
    }

    function abrir(aba) {
        focoAnterior = document.activeElement;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        $('cfgMsg').classList.add('hidden');
        var escolher = function () {
            var ok = { alertas: dados.podeAlertas, prefs: dados.podePrefs, conta: true };
            if (!aba || !ok[aba]) aba = dados.podeAlertas ? 'alertas' : (dados.podePrefs ? 'prefs' : 'conta');
            $('cfgSub').textContent = dados.nome + (dados.email ? ' · ' + dados.email : '');
            mostrar(aba);
            $('cfgFechar').focus();
        };
        if (dados) return escolher();
        fetch('configuracoes.php?carregar=1', { headers: H, cache: 'no-store' }).then(function (r) { return r.json(); })
            .then(function (d) { if (!d.ok) throw new Error(d.erro); dados = d; escolher(); })
            .catch(function () { $('cfgCorpo').textContent = ''; $('cfgCorpo').appendChild(el('p', 'py-12 text-center text-slate-500', 'Não foi possível carregar as configurações. Tente de novo.')); });
    }
    function fechar() {
        modal.classList.add('hidden'); modal.classList.remove('flex');
        document.body.style.overflow = '';
        if (location.hash.indexOf('#configuracoes') === 0) history.replaceState(null, '', location.pathname + location.search);
        if (focoAnterior && focoAnterior.focus) try { focoAnterior.focus(); } catch (e) {}
    }
    $('cfgFechar').onclick = fechar;
    $('cfgFundo').onclick = fechar;
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) fechar(); });

    // Links href="#configuracoes..." e botões data-configuracoes em qualquer página
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href*="#configuracoes"], [data-configuracoes]');
        if (!a) return;
        e.preventDefault();
        var h = a.hasAttribute('data-configuracoes') ? a.getAttribute('data-configuracoes') : ((a.getAttribute('href') || '').split('#configuracoes')[1] || '');
        abrir(h.replace(/^-/, ''));
    });
    window.fsaConfiguracoes = abrir;
    if (location.hash.indexOf('#configuracoes') === 0) abrir(location.hash.replace('#configuracoes', '').replace(/^-/, ''));
    else if (/[?&]config=alertas/.test(location.search)) abrir('alertas');   // links antigos
})();
</script>
