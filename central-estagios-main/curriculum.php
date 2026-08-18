<?php
require_once 'includes/config.php';

if (function_exists('checkAuth')) {
    checkAuth();
} elseif (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

$pageTitle = 'Meu Currículo';

function fmtData($ymd) {
    if (empty($ymd)) return '';
    $m = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $p = explode('-', $ymd);
    return ($m[intval($p[1] ?? 0)] ?? '') . '/' . ($p[0] ?? '');
}

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $first . $last;
}

function getCorMap() {
    return [
        'azul-cinza'     => ['primary' => '#1F3A5F', 'secondary' => '#5B6572', 'label' => 'Azul &amp; Cinza'],
        'verde-vinho'    => ['primary' => '#2F5233', 'secondary' => '#6B2737', 'label' => 'Verde &amp; Vinho'],
        'petroleo-cinza' => ['primary' => '#1C4A4A', 'secondary' => '#55606B', 'label' => 'Petr&oacute;leo &amp; Cinza'],
        'preto-dourado'  => ['primary' => '#1C1C1C', 'secondary' => '#A67C3D', 'label' => 'Preto &amp; Dourado'],
        'vinho-cinza'    => ['primary' => '#6B2737', 'secondary' => '#5B6572', 'label' => 'Vinho &amp; Cinza'],
        'marinho-verde'  => ['primary' => '#1F3A5F', 'secondary' => '#2F5233', 'label' => 'Marinho &amp; Verde'],
    ];
}

function getLayoutMap() {
    return [
        'classico'    => ['label' => 'Cl&aacute;ssico',   'desc' => 'Cabe&ccedil;alho preenchido, visual institucional'],
        'moderno'     => ['label' => 'Moderno',            'desc' => 'Sidebar lateral s&oacute;lida, estilo corporativo'],
        'minimalista' => ['label' => 'Minimalista',        'desc' => 'S&oacute;brio, sem cores fortes no fundo'],
    ];
}

function getNiveisIdioma() {
    return ['Basico','Pre-intermediario','Intermediario','Avancado','Proficiente','Fluente','Nativo'];
}

$usuarios = loadData('usuarios');
$usuarioAtual = null;
foreach ($usuarios as $u) {
    if ($u['id'] == ($_SESSION['user_id'] ?? 0)) {
        $usuarioAtual = $u;
        break;
    }
}

$defaultData = [
    'nome'         => $usuarioAtual['nome']  ?? ($_SESSION['user_name'] ?? ''),
    'email'        => $usuarioAtual['email'] ?? '',
    'telefone'     => '',
    'cidade'       => '',
    'resumo'       => '',
    'habilidades'  => [],
    'experiencias' => [],
    'formacoes'    => [],
    'idiomas'      => [],
    'layout'       => 'classico',
    'corTema'      => 'azul-cinza',
    'exibirFoto'   => false,
    'fotoCV'       => null,
];

function montarUserDataDoPost($postArr, $dadosAtuais) {
    $raw = str_replace("\r", "", $postArr['habilidades'] ?? '');
    $hab = array_values(array_filter(array_map('trim', explode("\n", $raw))));

    $expCount = intval($postArr['exp_count'] ?? 0);
    $experiencias = [];
    for ($i = 0; $i < $expCount; $i++) {
        $cargo = trim($postArr["exp_{$i}_cargo"] ?? '');
        if (empty($cargo)) continue;
        $experiencias[] = [
            'cargo'     => $cargo,
            'empresa'   => trim($postArr["exp_{$i}_empresa"]    ?? ''),
            'cidade'    => trim($postArr["exp_{$i}_cidade"]     ?? ''),
            'inicio'    => $postArr["exp_{$i}_inicio"]          ?? '',
            'fim'       => $postArr["exp_{$i}_fim"]             ?? '',
            'atual'     => (($postArr["exp_{$i}_atual"] ?? '0') === '1') ? '1' : '0',
            'descricao' => trim($postArr["exp_{$i}_descricao"]  ?? ''),
        ];
    }

    $formCount = intval($postArr['form_count'] ?? 0);
    $formacoes = [];
    for ($i = 0; $i < $formCount; $i++) {
        $curso = trim($postArr["form_{$i}_curso"] ?? '');
        if (empty($curso)) continue;
        $formacoes[] = [
            'curso'       => $curso,
            'instituicao' => trim($postArr["form_{$i}_instituicao"] ?? ''),
            'tipo'        => trim($postArr["form_{$i}_tipo"]         ?? ''),
            'inicio'      => $postArr["form_{$i}_inicio"]            ?? '',
            'fim'         => $postArr["form_{$i}_fim"]               ?? '',
            'atual'       => (($postArr["form_{$i}_atual"] ?? '0') === '1') ? '1' : '0',
        ];
    }

    $idiomaCount = intval($postArr['idioma_count'] ?? 0);
    $idiomas = [];
    for ($i = 0; $i < $idiomaCount; $i++) {
        $idioma = trim($postArr["idioma_{$i}_idioma"] ?? '');
        if (empty($idioma)) continue;
        $idiomas[] = [
            'idioma' => $idioma,
            'nivel'  => trim($postArr["idioma_{$i}_nivel"] ?? ''),
        ];
    }

    $telefoneRaw = preg_replace('/\D/', '', $postArr['telefone'] ?? '');
    $telefoneFormatado = $postArr['telefone'] ?? '';
    if (strlen($telefoneRaw) === 11) {
        $telefoneFormatado = sprintf('(%s) %s-%s', substr($telefoneRaw,0,2), substr($telefoneRaw,2,5), substr($telefoneRaw,7,4));
    } elseif (strlen($telefoneRaw) === 10) {
        $telefoneFormatado = sprintf('(%s) %s-%s', substr($telefoneRaw,0,2), substr($telefoneRaw,2,4), substr($telefoneRaw,6,4));
    }

    return [
        'nome'         => trim($postArr['nome']     ?? ''),
        'email'        => trim($postArr['email']    ?? ''),
        'telefone'     => $telefoneFormatado,
        'cidade'       => trim($postArr['cidade']   ?? ''),
        'resumo'       => trim($postArr['resumo']   ?? ''),
        'habilidades'  => $hab,
        'experiencias' => $experiencias,
        'formacoes'    => $formacoes,
        'idiomas'      => $idiomas,
        'layout'       => $dadosAtuais['layout']     ?? 'classico',
        'corTema'      => $dadosAtuais['corTema']    ?? 'azul-cinza',
        'exibirFoto'   => $dadosAtuais['exibirFoto'] ?? false,
        'fotoCV'       => $dadosAtuais['fotoCV']     ?? null,
    ];
}

$dadosSalvos = loadData('userCurriculum', true);
if (empty($dadosSalvos)) {
    $dadosSalvos = $defaultData;
}
foreach ($defaultData as $k => $v) {
    if (!isset($dadosSalvos[$k])) $dadosSalvos[$k] = $v;
}
if (!array_key_exists($dadosSalvos['corTema'], getCorMap())) {
    $dadosSalvos['corTema'] = 'azul-cinza';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'autosave') {
    $novo = montarUserDataDoPost($_POST, $dadosSalvos);
    saveData('userCurriculum', $novo, true);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $novo = montarUserDataDoPost($_POST, $dadosSalvos);
    saveData('userCurriculum', $novo, true);
    header('Location: curriculum.php?saved=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_layout') {
    $layoutsValidos = array_keys(getLayoutMap());
    $novoLayout = $_POST['layout'] ?? 'classico';
    if (in_array($novoLayout, $layoutsValidos)) {
        $dadosSalvos['layout'] = $novoLayout;
        saveData('userCurriculum', $dadosSalvos, true);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_cor') {
    $coresValidas = array_keys(getCorMap());
    $novaCor = $_POST['corTema'] ?? 'azul-cinza';
    if (in_array($novaCor, $coresValidas)) {
        $dadosSalvos['corTema'] = $novaCor;
        saveData('userCurriculum', $dadosSalvos, true);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_foto') {
    $dadosSalvos['exibirFoto'] = (($_POST['exibirFoto'] ?? '0') === '1');
    saveData('userCurriculum', $dadosSalvos, true);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'exibirFoto' => $dadosSalvos['exibirFoto'], 'temFoto' => !empty($dadosSalvos['fotoCV'])]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_foto') {
    if (!empty($_FILES['foto_cv']['tmp_name']) && is_uploaded_file($_FILES['foto_cv']['tmp_name'])) {
        $permitidas = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['foto_cv']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $permitidas) && $_FILES['foto_cv']['size'] <= 5 * 1024 * 1024) {
            $userId = $_SESSION['user_id'] ?? 0;
            $dir = DATA_PATH . "users/{$userId}/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            if (!empty($dadosSalvos['fotoCV'])) {
                $antiga = DATA_PATH . $dadosSalvos['fotoCV'];
                if (file_exists($antiga)) @unlink($antiga);
            }

            $novoNome = 'cv_foto_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['foto_cv']['tmp_name'], $dir . $novoNome);

            $dadosSalvos['fotoCV']     = "users/{$userId}/{$novoNome}";
            $dadosSalvos['exibirFoto'] = true;
            saveData('userCurriculum', $dadosSalvos, true);
        }
    }
    header('Location: curriculum.php?foto_ok=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remover_foto') {
    if (!empty($dadosSalvos['fotoCV'])) {
        $caminho = DATA_PATH . $dadosSalvos['fotoCV'];
        if (file_exists($caminho)) @unlink($caminho);
    }
    $dadosSalvos['fotoCV'] = null;
    saveData('userCurriculum', $dadosSalvos, true);
    header('Location: curriculum.php?foto_removida=1');
    exit;
}

$userData = $dadosSalvos;
$certs = loadData('userCertificates', true);

$corMap    = getCorMap();
$layoutMap = getLayoutMap();
$niveisIdioma = getNiveisIdioma();
$corAtual    = $corMap[$userData['corTema']] ?? $corMap['azul-cinza'];
$layoutAtual = $userData['layout'] ?? 'classico';

$fotoUrl = null;
if (!empty($userData['fotoCV'])) {
    $fotoUrl = 'data/' . $userData['fotoCV'];
}

include 'includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
:root{
    --cv-primary: <?php echo $corAtual['primary']; ?>;
    --cv-secondary: <?php echo $corAtual['secondary']; ?>;
}

.cdp-wrapper{position:relative;width:100%;}
.cdp-display{width:100%;padding:12px 14px;cursor:pointer;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;color:#374151;font-size:14px;box-sizing:border-box;transition:border-color .2s;user-select:none;min-height:46px;display:flex;align-items:center;}
.cdp-display:hover{border-color:var(--cv-primary);}
.cdp-display.active{border-color:var(--cv-primary);box-shadow:0 0 0 3px rgba(0,0,0,.06);}
.cdp-display.cdp-disabled{opacity:.4;cursor:not-allowed;pointer-events:none;}
.cdp-dropdown{position:absolute;top:calc(100% + 8px);left:0;z-index:99999;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.18);width:300px;display:none;overflow:hidden;animation:cdpFade .15s ease;}
@keyframes cdpFade{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.cdp-dropdown.open{display:block;}
.cdp-header{background:var(--cv-primary);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;color:#fff;gap:8px;}
.cdp-header-info{display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;}
.cdp-header-month{font-weight:800;font-size:15px;padding:3px 10px;border-radius:6px;transition:background .15s;}
.cdp-header-month:hover{background:rgba(255,255,255,.2);}
.cdp-header-year{font-size:11px;opacity:.8;padding:2px 8px;border-radius:6px;transition:background .15s;}
.cdp-header-year:hover{background:rgba(255,255,255,.2);}
.cdp-nav{background:rgba(255,255,255,.15);border:none;color:#fff;font-size:20px;cursor:pointer;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0;}
.cdp-nav:hover{background:rgba(255,255,255,.3);}
.cdp-tabs{display:flex;border-bottom:1px solid #f3f4f6;background:#fafafa;}
.cdp-tab{flex:1;padding:9px;text-align:center;font-size:11px;font-weight:700;color:#9ca3af;cursor:pointer;text-transform:uppercase;letter-spacing:.8px;transition:all .15s;border-bottom:2px solid transparent;}
.cdp-tab:hover{color:var(--cv-primary);}
.cdp-tab.active{color:var(--cv-primary);border-bottom-color:var(--cv-primary);background:#fff;}
.cdp-weekdays{display:grid;grid-template-columns:repeat(7,1fr);padding:10px 12px 4px;background:#f7f8f9;}
.cdp-weekday{text-align:center;font-size:10px;font-weight:800;color:var(--cv-primary);text-transform:uppercase;padding:4px 0;}
.cdp-days{display:grid;grid-template-columns:repeat(7,1fr);padding:8px 12px 10px;gap:2px;}
.cdp-day{aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:13px;border-radius:50%;cursor:pointer;color:#374151;font-weight:500;transition:all .15s;border:2px solid transparent;}
.cdp-day:hover{background:#eef1f5;color:var(--cv-primary);}
.cdp-day.selected{background:var(--cv-primary)!important;color:#fff!important;font-weight:800;}
.cdp-day.today{border-color:var(--cv-primary);color:var(--cv-primary);font-weight:800;}
.cdp-day.today.selected{border-color:transparent;}
.cdp-day.other-month{color:#d1d5db;font-weight:400;}
.cdp-months{display:grid;grid-template-columns:repeat(3,1fr);padding:14px;gap:6px;}
.cdp-month-item{padding:11px 6px;text-align:center;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;transition:all .15s;border:2px solid transparent;}
.cdp-month-item:hover{background:#eef1f5;color:var(--cv-primary);}
.cdp-month-item.selected{background:var(--cv-primary);color:#fff;}
.cdp-month-item.cur-month{border-color:var(--cv-primary);color:var(--cv-primary);}
.cdp-month-item.cur-month.selected{border-color:transparent;color:#fff;}
.cdp-years{display:grid;grid-template-columns:repeat(4,1fr);padding:14px;gap:6px;}
.cdp-year-item{padding:10px 4px;text-align:center;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;transition:all .15s;border:2px solid transparent;}
.cdp-year-item:hover{background:#eef1f5;color:var(--cv-primary);}
.cdp-year-item.selected{background:var(--cv-primary);color:#fff;}
.cdp-year-item.cur-year{border-color:var(--cv-primary);color:var(--cv-primary);}
.cdp-year-item.cur-year.selected{border-color:transparent;color:#fff;}
.cdp-footer{border-top:1px solid #f3f4f6;padding:9px 14px;display:flex;justify-content:space-between;align-items:center;background:#fafafa;}
.cdp-sel-display{font-size:12px;color:var(--cv-primary);font-weight:600;}
.cdp-btn{font-size:12px;font-weight:700;padding:6px 14px;border-radius:6px;border:none;cursor:pointer;transition:all .15s;}
.cdp-btn-clear{background:#fdecea;color:#b3261e;}
.cdp-btn-clear:hover{background:#b3261e;color:#fff;}
.cdp-btn-today{background:#eef1f5;color:var(--cv-primary);}
.cdp-btn-today:hover{background:var(--cv-primary);color:#fff;}

.selector-card{border:2px solid #e5e7eb;border-radius:12px;padding:14px;cursor:pointer;transition:all .15s;background:#fff;}
.selector-card:hover{border-color:var(--cv-primary);}
.selector-card.active{border-color:var(--cv-primary);background:#f8fafb;box-shadow:0 0 0 3px rgba(0,0,0,.04);}
.selector-preview{width:100%;height:56px;border-radius:6px;margin-bottom:8px;overflow:hidden;position:relative;background:#eef0f2;display:flex;align-items:center;justify-content:center;}

.color-dot{
    width:36px;
    height:36px;
    border-radius:8px;
    cursor:pointer;
    border:3px solid #fff;
    box-shadow:0 0 0 2px #e5e7eb;
    transition:all .15s;
    display:block;
}
.color-dot:hover{ transform:scale(1.05); }
.color-dot.active{
    box-shadow:0 0 0 2px var(--cv-dot-color, #000);
    transform:scale(1.08);
}

.cv-shell, .cv-shell *{ font-family: Arial, "Helvetica Neue", Helvetica, sans-serif; }

.cv-shell{display:grid;gap:20px;}
.cv-shell[data-layout="classico"],
.cv-shell[data-layout="minimalista"]{
    grid-template-columns:1fr;
    grid-template-areas:"hero" "resumo" "exp" "form" "skills" "certs";
}
.cv-shell[data-layout="moderno"]{
    grid-template-columns:280px 1fr;
    grid-template-areas:
        "hero resumo"
        "skills exp"
        "skills form"
        "skills certs";
}
@media (max-width: 768px){
    .cv-shell[data-layout="moderno"]{grid-template-columns:1fr;grid-template-areas:"hero" "resumo" "skills" "exp" "form" "certs";}
}
[data-area="hero"]{grid-area:hero;}
[data-area="resumo"]{grid-area:resumo;}
[data-area="exp"]{grid-area:exp;}
[data-area="form"]{grid-area:form;}
[data-area="skills"]{grid-area:skills;}
[data-area="certs"]{grid-area:certs;}

.cv-hero{ border-radius:8px; padding:30px 38px; position:relative; }
.cv-avatar{
    width:78px;height:78px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:24px;font-weight:800;
    flex-shrink:0;overflow:hidden;
}
.cv-avatar img{width:100%;height:100%;object-fit:cover;}
.cv-contact-pill{ display:inline-flex;align-items:center;gap:7px; font-size:12.5px;font-weight:500; }
.cv-contact-pill svg{width:14px;height:14px;flex-shrink:0;}

.cv-section{background:#fff;border-radius:8px;box-shadow:none;border:1px solid #e9e9ea;height:100%;box-sizing:border-box;}
.cv-section-title{
    font-size:12.5px;font-weight:800;text-transform:uppercase;letter-spacing:1.4px;
    color:#1a1a1a;border-bottom:2px solid var(--cv-primary);
    padding-bottom:8px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;
}

.cv-timeline-item{position:relative;padding-bottom:14px;margin-bottom:14px;border-bottom:1px solid #eee;}
.cv-timeline-item:last-child{padding-bottom:0;margin-bottom:0;border-bottom:none;}
.cv-badge-period{background:transparent;color:var(--cv-secondary);font-weight:700;font-size:11.5px;padding:0;border-radius:0;white-space:nowrap;text-transform:uppercase;letter-spacing:.4px;}

.cv-skill-pill{background:#fff;color:var(--cv-secondary);border:1px solid var(--cv-primary);border-radius:4px;font-weight:600;}

.cv-idioma-item{background:transparent;border-bottom:1px dashed #ddd;border-radius:0;padding:9px 0;display:flex;align-items:center;justify-content:space-between;gap:10px;}
.cv-idioma-item:last-child{border-bottom:none;}
.cv-idioma-nome{color:#1a1a1a;font-weight:700;font-size:13px;}
.cv-idioma-nivel{font-size:11px;color:var(--cv-secondary);opacity:1;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}

[data-layout="classico"] .cv-hero{ background:var(--cv-primary); border:none; color:#fff; }
[data-layout="classico"] .cv-hero input,
[data-layout="classico"] .cv-hero p{ color:#fff !important; }
[data-layout="classico"] .cv-hero input::placeholder{ color:rgba(255,255,255,.65) !important; }
[data-layout="classico"] .cv-hero .cv-avatar{ background:rgba(255,255,255,.16);border:2px solid rgba(255,255,255,.55);color:#fff; }
[data-layout="classico"] .cv-hero .cv-contact-pill{ color:#fff; }
[data-layout="classico"] .cv-hero .cv-contact-pill svg{ color:rgba(255,255,255,.9); }

[data-layout="minimalista"] .cv-hero{ background:#fff;border:1px solid #e5e7eb; }
[data-layout="minimalista"] .cv-hero .cv-avatar{ background:#f0f1f3;border:2px solid var(--cv-primary);color:var(--cv-primary); }
[data-layout="minimalista"] .cv-hero .cv-contact-pill{ color:#4b5563; }
[data-layout="minimalista"] .cv-hero .cv-contact-pill svg{ color:var(--cv-primary); }
[data-layout="minimalista"] .cv-section{box-shadow:none;border:1px solid #ececec;}

[data-layout="moderno"] [data-area="hero"].cv-hero{
    display:flex;flex-direction:column;align-items:center;text-align:center;justify-content:flex-start;gap:10px;
    border-radius:8px 8px 0 0;border:none;
    background:var(--cv-secondary);color:#fff;padding:34px 22px 24px;
    margin-bottom:0;
}
[data-layout="moderno"] [data-area="hero"].cv-hero .flex-1{width:100%;}
[data-layout="moderno"] [data-area="hero"].cv-hero input[name="nome"]{
    font-size:1.3rem;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    border-bottom:none;padding:0 4px;color:#fff !important;
}
[data-layout="moderno"] [data-area="hero"].cv-hero input,
[data-layout="moderno"] [data-area="hero"].cv-hero p{ color:#fff !important; }
[data-layout="moderno"] [data-area="hero"].cv-hero input::placeholder{ color:rgba(255,255,255,.6) !important; }
[data-layout="moderno"] [data-area="hero"].cv-hero .cv-contact-pill{ color:#fff; }
[data-layout="moderno"] [data-area="hero"].cv-hero .cv-contact-pill svg{ color:rgba(255,255,255,.85); }
[data-layout="moderno"] [data-area="hero"].cv-hero .cv-avatar{ background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.5);color:#fff; }

[data-layout="moderno"] [data-area="skills"].cv-section{
    background:var(--cv-secondary);color:#fff;border:none;
    border-radius:0 0 8px 8px;margin-top:-20px;padding-top:24px;height:calc(100% + 20px);
}
[data-layout="moderno"] [data-area="skills"] .cv-section-title{ color:#fff;border-bottom:2px solid rgba(255,255,255,.35); }
[data-layout="moderno"] [data-area="skills"] .cv-skill-pill{ background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.5); }
[data-layout="moderno"] [data-area="skills"] textarea{ background:rgba(255,255,255,.9); }
[data-layout="moderno"] [data-area="skills"] .cv-idioma-item{ border-bottom:1px dashed rgba(255,255,255,.3); }
[data-layout="moderno"] [data-area="skills"] .cv-idioma-nome{ color:#fff; }
[data-layout="moderno"] [data-area="skills"] .cv-idioma-nivel{ color:rgba(255,255,255,.75); }
[data-layout="moderno"] [data-area="skills"] p.text-gray-400{ color:rgba(255,255,255,.6) !important; }

#curriculumPDF{ position:absolute; left:-99999px; top:0; width:794px; background:#fff; }

@keyframes modalPop{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
</style>
<main class="ml-16 pt-16 bg-gray-50 min-h-screen">
<div class="p-8 max-w-6xl mx-auto">

    <div class="flex justify-between items-center mb-6 flex-wrap gap-4">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Meu Curr&iacute;culo</h2>
            <p class="text-gray-500">Mantenha suas informa&ccedil;&otilde;es atualizadas</p>
        </div>
        <div class="flex gap-3">
            <button id="btnEditar"   onclick="toggleEdit()"      class="flex items-center gap-2 px-4 py-2 bg-[var(--cv-primary)] text-white rounded-lg hover:opacity-90 transition-colors shadow-sm"><i class="fas fa-edit"></i> Editar</button>
            <button id="btnSalvar"   onclick="salvarCurriculo()" class="hidden flex items-center gap-2 px-4 py-2 bg-[var(--cv-primary)] text-white rounded-lg hover:opacity-90 transition-colors shadow-sm"><i class="fas fa-save"></i> Salvar</button>
            <button id="btnCancelar" onclick="cancelarEdicao()"  class="hidden flex items-center gap-2 px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 transition-colors shadow-sm"><i class="fas fa-times"></i> Cancelar</button>
            <button id="btnPDF"      onclick="baixarPDF()"       class="flex items-center gap-2 px-4 py-2 bg-[#8B1E2D] text-white rounded-lg hover:opacity-90 transition-colors shadow-sm"><i class="fas fa-file-pdf"></i> Baixar PDF</button>
        </div>
    </div>

    <div id="autosaveToast" class="hidden fixed bottom-6 right-6 z-[999] bg-gray-900 text-white text-sm font-semibold px-4 py-3 rounded-xl shadow-lg items-center gap-2 flex">
        <i class="fas fa-check-circle text-green-400"></i> <span id="autosaveToastText">Salvo automaticamente</span>
    </div>

    <div id="modalConfirmacao" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="fecharModalConfirmacao()"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
        <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center mb-4 mx-auto">
            <i class="fas fa-trash-alt text-red-500 text-2xl"></i>
        </div>

        <h3 id="modalConfirmacaoTitulo" class="text-xl font-bold text-gray-900 mb-2"></h3>
        <p id="modalConfirmacaoTexto" class="text-gray-500 text-sm mb-6"></p>

        <div class="flex gap-3">
            <button type="button" onclick="fecharModalConfirmacao()"
                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-all">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" id="modalConfirmacaoBtnOk"
                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-red-500 rounded-xl text-white font-medium hover:bg-red-600 transition-all">
                <i class="fas fa-trash-alt"></i> Remover
            </button>
        </div>
    </div>
</div>


    <div class="cv-section p-6 mb-6" style="height:auto;">
        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest mb-4">Modelo do Curr&iacute;culo</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($layoutMap as $key => $info): ?>
                <div class="selector-card <?php echo $layoutAtual === $key ? 'active' : ''; ?>" data-layout-option="<?php echo $key; ?>" onclick="selecionarLayout('<?php echo $key; ?>')">
                    <div class="selector-preview">
                        <?php if ($key === 'classico'): ?>
                            <div style="width:88%;height:80%;background:<?php echo $corAtual['primary']; ?>;border-radius:4px;display:flex;flex-direction:column;gap:4px;justify-content:center;padding:6px 8px;box-sizing:border-box;">
                                <div style="height:6px;background:rgba(255,255,255,.9);border-radius:2px;width:55%;"></div>
                                <div style="height:4px;background:rgba(255,255,255,.6);border-radius:2px;width:80%;"></div>
                                <div style="height:4px;background:rgba(255,255,255,.6);border-radius:2px;width:65%;"></div>
                            </div>
                        <?php elseif ($key === 'moderno'): ?>
                            <div style="width:88%;height:80%;display:flex;gap:4px;">
                                <div style="width:32%;background:<?php echo $corAtual['secondary']; ?>;border-radius:3px;"></div>
                                <div style="flex:1;background:#fff;border:1px solid #e5e7eb;border-radius:3px;display:flex;flex-direction:column;gap:4px;justify-content:center;padding:0 6px;box-sizing:border-box;">
                                    <div style="height:4px;background:#bbb;border-radius:2px;width:90%;"></div>
                                    <div style="height:4px;background:#bbb;border-radius:2px;width:70%;"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="width:88%;height:80%;background:#fff;border:1px solid #eee;border-radius:4px;display:flex;flex-direction:column;gap:4px;justify-content:center;padding:6px 8px;box-sizing:border-box;">
                                <div style="height:5px;background:#ccc;border-radius:2px;width:50%;"></div>
                                <div style="height:3px;background:#e5e5e5;border-radius:2px;width:80%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm font-bold text-gray-800"><?php echo $info['label']; ?></p>
                    <p class="text-xs text-gray-500"><?php echo $info['desc']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest mb-3 mt-6">Cor do Tema</h3>
        <div class="flex gap-4 flex-wrap">
            <?php foreach ($corMap as $key => $info): ?>
                <div class="text-center">
                    <div class="color-dot <?php echo $userData['corTema'] === $key ? 'active' : ''; ?>"
                         style="background:linear-gradient(135deg,<?php echo $info['primary']; ?> 0% 50%,<?php echo $info['secondary']; ?> 50% 100%); --cv-dot-color: <?php echo $info['primary']; ?>;"
                         data-cor-option="<?php echo $key; ?>"
                         onclick="selecionarCor('<?php echo $key; ?>')"></div>
                    <p class="text-xs text-gray-500 mt-1"><?php echo $info['label']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-widest mb-3 mt-6">Foto no Curr&iacute;culo</h3>
        <div class="flex items-center gap-4 flex-wrap">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="toggleFotoCB" <?php echo !empty($userData['exibirFoto']) ? 'checked' : ''; ?> onchange="toggleFoto(this.checked)" class="w-5 h-5 accent-[var(--cv-primary)] cursor-pointer">
                <span class="text-sm font-medium text-gray-700">Exibir foto no curr&iacute;culo</span>
            </label>

            <div id="fotoUploadArea" class="flex items-center gap-3 <?php echo !empty($userData['exibirFoto']) ? '' : 'hidden'; ?>">
                <?php if ($fotoUrl): ?>
                    <img src="<?php echo htmlspecialchars($fotoUrl); ?>" class="w-12 h-12 rounded-lg object-cover border-2 border-gray-200" alt="Foto atual">
                <?php endif; ?>
                <form method="POST" action="curriculum.php" enctype="multipart/form-data" accept-charset="UTF-8" class="flex items-center gap-2">
                    <input type="hidden" name="action" value="upload_foto">
                    <label class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs font-semibold cursor-pointer text-gray-700">
                        <i class="fas fa-upload mr-1"></i> <?php echo $fotoUrl ? 'Trocar foto' : 'Escolher foto'; ?>
                        <input type="file" name="foto_cv" accept="image/png,image/jpeg,image/webp" class="hidden" onchange="this.form.submit()">
                    </label>
                </form>
                <?php if ($fotoUrl): ?>
                    <form method="POST" action="curriculum.php" accept-charset="UTF-8" id="formRemoverFoto">
                        <input type="hidden" name="action" value="remover_foto">
                        <button type="button"
                            onclick="abrirModalConfirmacao('Remover foto do curr&iacute;culo', 'Tem certeza que deseja remover sua foto? Essa a&ccedil;&atilde;o n&atilde;o pode ser desfeita.', () => document.getElementById('formRemoverFoto').submit())"
                            class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg text-xs font-semibold"><i class="fas fa-trash-alt mr-1"></i> Remover</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form id="curriculumForm" method="POST" action="curriculum.php" accept-charset="UTF-8">
        <input type="hidden" name="action"      value="save">
        <input type="hidden" name="exp_count"   id="exp_count"    value="<?php echo count($userData['experiencias']); ?>">
        <input type="hidden" name="form_count"  id="form_count"   value="<?php echo count($userData['formacoes']); ?>">
        <input type="hidden" name="idioma_count" id="idioma_count" value="<?php echo count($userData['idiomas']); ?>">

        <div class="cv-shell" id="cvShell" data-layout="<?php echo htmlspecialchars($layoutAtual); ?>" data-tema="<?php echo htmlspecialchars($userData['corTema']); ?>">

            <section class="cv-hero" data-area="hero">
                <div class="relative z-10 flex flex-col md:flex-row md:items-center gap-6">
                    <?php if (!empty($userData['exibirFoto'])): ?>
                        <div class="cv-avatar">
                            <?php if ($fotoUrl): ?>
                                <img src="<?php echo htmlspecialchars($fotoUrl); ?>" alt="Foto">
                            <?php else: ?>
                                <?php echo htmlspecialchars(getInitials($userData['nome'] ?: '?')); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1">
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($userData['nome']); ?>"
                            class="curriculum-field w-full bg-transparent text-2xl md:text-3xl font-extrabold placeholder-gray-400 focus:outline-none border-b-2 border-transparent focus:border-gray-300 pb-1" readonly placeholder="Seu nome completo">
                    </div>
                </div>
                <div class="relative z-10 flex flex-wrap gap-5 mt-6 justify-center md:justify-start">
                    <div class="cv-contact-pill">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"></path></svg>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>"
                            class="curriculum-field bg-transparent placeholder-gray-400 focus:outline-none w-auto" readonly placeholder="seuemail@exemplo.com" size="26">
                    </div>
                    <div class="cv-contact-pill">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24 11.36 11.36 0 0 0 3.58.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.4 21 3 13.6 3 4.5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.58a1 1 0 0 1-.25 1.02z"></path></svg>
                        <input type="text" id="telefoneInput" name="telefone" value="<?php echo htmlspecialchars($userData['telefone']); ?>"
                            class="curriculum-field bg-transparent placeholder-gray-400 focus:outline-none w-auto" readonly placeholder="(00) 00000-0000" maxlength="15" size="15">
                    </div>
                    <div class="cv-contact-pill">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"></path></svg>
                        <input type="text" name="cidade" value="<?php echo htmlspecialchars($userData['cidade']); ?>"
                            class="curriculum-field bg-transparent placeholder-gray-400 focus:outline-none w-auto" readonly placeholder="Cidade, Estado" size="18">
                    </div>
                </div>
            </section>

            <section class="cv-section p-8" data-area="resumo">
                <div class="cv-section-title"><span>Resumo Profissional</span></div>
                <textarea name="resumo" rows="4" class="curriculum-field w-full p-4 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 focus:outline-none focus:border-[var(--cv-primary)] min-h-[100px] leading-relaxed" readonly placeholder="Escreva um breve resumo sobre voce, seus objetivos e diferenciais..."><?php echo htmlspecialchars($userData['resumo']); ?></textarea>
            </section>

            <section class="cv-section p-8" data-area="exp">
                <div class="cv-section-title">
                    <span>Hist&oacute;rico Profissional</span>
                    <button id="btnAddExp" type="button" onclick="addExperiencia()" style="display:none"
                        class="flex items-center gap-1 px-3 py-1.5 bg-[var(--cv-primary)] text-white text-xs font-bold rounded-lg hover:opacity-90 transition-colors normal-case tracking-normal">
                        <i class="fas fa-plus"></i> Adicionar Experi&ecirc;ncia
                    </button>
                </div>
                <div id="expViewContainer">
                    <?php if (empty($userData['experiencias'])): ?>
                        <p class="text-gray-400 text-sm italic">Nenhuma experi&ecirc;ncia cadastrada ainda.</p>
                    <?php else: foreach ($userData['experiencias'] as $exp):
                        $ini = fmtData($exp['inicio']);
                        $fim = ($exp['atual'] ?? '0') === '1' ? 'Atual' : fmtData($exp['fim']);
                        $per = $ini . ($fim ? ' - ' . $fim : '');
                    ?>
                    <div class="cv-timeline-item">
                        <div class="flex justify-between items-start flex-wrap gap-2">
                            <div>
                                <span class="font-bold text-gray-900"><?php echo htmlspecialchars($exp['cargo']); ?></span>
                                <span class="text-gray-500 text-sm"> . <?php echo htmlspecialchars($exp['empresa']); ?> - <?php echo htmlspecialchars($exp['cidade']); ?></span>
                            </div>
                            <span class="cv-badge-period"><?php echo $per; ?></span>
                        </div>
                        <p class="text-sm text-gray-600 mt-2 leading-relaxed"><?php echo htmlspecialchars($exp['descricao']); ?></p>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <div id="expEditContainer" class="hidden space-y-4"></div>
            </section>

            <section class="cv-section p-8" data-area="form">
                <div class="cv-section-title">
                    <span>Forma&ccedil;&atilde;o Acad&ecirc;mica</span>
                    <button id="btnAddForm" type="button" onclick="addFormacao()" style="display:none"
                        class="flex items-center gap-1 px-3 py-1.5 bg-[var(--cv-primary)] text-white text-xs font-bold rounded-lg hover:opacity-90 transition-colors normal-case tracking-normal">
                        <i class="fas fa-plus"></i> Adicionar Forma&ccedil;&atilde;o
                    </button>
                </div>
                <div id="formViewContainer">
                    <?php if (empty($userData['formacoes'])): ?>
                        <p class="text-gray-400 text-sm italic">Nenhuma forma&ccedil;&atilde;o cadastrada ainda.</p>
                    <?php else: foreach ($userData['formacoes'] as $form):
                        $ini       = fmtData($form['inicio']);
                        $fim       = fmtData($form['fim']);
                        $periodo   = $ini . ($fim ? ' - ' . $fim : '');
                    ?>
                    <div class="cv-timeline-item">
                        <div class="flex justify-between items-start flex-wrap gap-2">
                            <div>
                                <p class="font-bold text-gray-900"><?php echo htmlspecialchars($form['instituicao']); ?></p>
                                <p class="text-sm text-gray-600 mt-0.5"><?php echo htmlspecialchars($form['tipo']); ?> em <?php echo htmlspecialchars($form['curso']); ?></p>
                            </div>
                            <span class="cv-badge-period"><?php echo $periodo; ?></span>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <div id="formEditContainer" class="hidden space-y-4"></div>
            </section>

            <section class="cv-section p-8" data-area="skills">
                <div class="cv-section-title"><span>Habilidades e Compet&ecirc;ncias</span></div>
                <div id="skillsView" class="flex flex-wrap gap-2">
                    <?php if (empty($userData['habilidades'])): ?>
                        <p class="text-gray-400 text-sm italic opacity-80">Nenhuma habilidade cadastrada ainda.</p>
                    <?php else: foreach ($userData['habilidades'] as $skill): ?>
                    <span class="cv-skill-pill inline-flex items-center px-3 py-1.5 text-xs">
                        <?php echo htmlspecialchars(trim($skill)); ?>
                    </span>
                    <?php endforeach; endif; ?>
                </div>
                <div id="skillsEdit" class="hidden">
                    <p class="text-xs text-gray-400 mb-2">Uma habilidade por linha</p>
                    <textarea name="habilidades" rows="8" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 focus:outline-none focus:border-[var(--cv-primary)] text-sm" placeholder="Proatividade&#10;Trabalho em equipe&#10;..."><?php echo htmlspecialchars(implode("\n", $userData['habilidades'])); ?></textarea>
                </div>

                <div class="cv-section-title" style="margin-top:26px;">
                    <span>Idiomas</span>
                    <button id="btnAddIdioma" type="button" onclick="addIdioma()" style="display:none"
                        class="flex items-center gap-1 px-3 py-1.5 bg-[var(--cv-primary)] text-white text-xs font-bold rounded-lg hover:opacity-90 transition-colors normal-case tracking-normal">
                        <i class="fas fa-plus"></i> Adicionar Idioma
                    </button>
                </div>
                <div id="idiomasView">
                    <?php if (empty($userData['idiomas'])): ?>
                        <p class="text-gray-400 text-sm italic opacity-80">Nenhum idioma cadastrado ainda.</p>
                    <?php else: foreach ($userData['idiomas'] as $idi): ?>
                    <div class="cv-idioma-item">
                        <span class="cv-idioma-nome"><?php echo htmlspecialchars($idi['idioma']); ?></span>
                        <span class="cv-idioma-nivel"><?php echo htmlspecialchars($idi['nivel']); ?></span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <div id="idiomasEditContainer" class="hidden space-y-3"></div>
            </section>

            <section class="cv-section p-8" data-area="certs">
                <div class="cv-section-title"><span>Cursos Complementares</span></div>
                <?php if (empty($certs)): ?>
                    <p class="text-gray-400 text-sm italic">Nenhum certificado encontrado.</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($certs as $cert): ?>
                    <div class="flex items-center justify-between gap-4 p-4 bg-gray-50 rounded-lg border border-gray-100">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($cert['title']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($cert['institution']); ?></p>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <span class="text-[var(--cv-primary)] font-semibold text-sm"><?php echo htmlspecialchars($cert['hours']); ?></span>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"><i class="fas fa-check text-[9px]"></i> Aprovado</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

        </div>
    </form>
</div>
</main>

<div id="curriculumPDF">
<?php if ($layoutAtual === 'moderno'): ?>
    <div style="display:flex;align-items:stretch;width:100%;">
        <div style="width:250px;flex:0 0 250px;background:<?php echo $corAtual['secondary']; ?>;padding:36px 24px;color:#fff;box-sizing:border-box;">
            <?php if (!empty($userData['exibirFoto']) && $fotoUrl): ?>
                <img src="<?php echo htmlspecialchars($fotoUrl); ?>" style="width:90px;height:90px;border-radius:10px;object-fit:cover;border:2px solid rgba(255,255,255,.5);display:block;margin:0 auto 16px;">
            <?php endif; ?>
            <div style="text-align:center;font-size:19px;font-weight:900;margin-bottom:4px;word-break:break-word;"><?php echo htmlspecialchars($userData['nome']); ?></div>
            <div style="text-align:center;font-size:10px;opacity:.85;margin-bottom:20px;">Curriculo Profissional</div>
            <div style="font-size:10px;line-height:2.4;margin-bottom:20px;word-break:break-word;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="#fff" style="flex-shrink:0;"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"></path></svg>
                    <span><?php echo htmlspecialchars($userData['email']); ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="#fff" style="flex-shrink:0;"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24 11.36 11.36 0 0 0 3.58.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.4 21 3 13.6 3 4.5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.58a1 1 0 0 1-.25 1.02z"></path></svg>
                    <span><?php echo htmlspecialchars($userData['telefone']); ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="#fff" style="flex-shrink:0;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"></path></svg>
                    <span><?php echo htmlspecialchars($userData['cidade']); ?></span>
                </div>
            </div>
            <div style="border-top:1px solid rgba(255,255,255,.3);padding-top:14px;margin-bottom:20px;">
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Habilidades</div>
                <?php foreach ($userData['habilidades'] as $s): ?>
                    <div style="font-size:11px;padding:4px 0;"><?php echo htmlspecialchars(trim($s)); ?></div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($userData['idiomas'])): ?>
            <div style="border-top:1px solid rgba(255,255,255,.3);padding-top:14px;">
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Idiomas</div>
                <?php foreach ($userData['idiomas'] as $idi): ?>
                    <div style="font-size:11px;padding:4px 0;">
                        <div style="font-weight:700;"><?php echo htmlspecialchars($idi['idioma']); ?></div>
                        <div style="opacity:.85;font-size:10px;"><?php echo htmlspecialchars($idi['nivel']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0;padding:36px 40px;box-sizing:border-box;">
            <?php include __DIR__ . '/_pdf_secoes.php'; ?>
        </div>
    </div>

<?php elseif ($layoutAtual === 'minimalista'): ?>
    <div style="padding:36px 52px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:20px;">
        <?php if (!empty($userData['exibirFoto']) && $fotoUrl): ?>
            <img src="<?php echo htmlspecialchars($fotoUrl); ?>" style="width:70px;height:70px;border-radius:10px;object-fit:cover;border:2px solid #e5e7eb;">
        <?php endif; ?>
        <div>
            <div style="font-size:26px;font-weight:800;color:#111;margin-bottom:6px;"><?php echo htmlspecialchars($userData['nome']); ?></div>
            <div style="font-size:11px;color:#666;line-height:1.8;display:flex;flex-wrap:wrap;gap:14px;align-items:center;">
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="<?php echo $corAtual['primary']; ?>"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"></path></svg>
                    <?php echo htmlspecialchars($userData['email']); ?>
                </span>
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="<?php echo $corAtual['primary']; ?>"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24 11.36 11.36 0 0 0 3.58.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.4 21 3 13.6 3 4.5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.58a1 1 0 0 1-.25 1.02z"></path></svg>
                    <?php echo htmlspecialchars($userData['telefone']); ?>
                </span>
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="<?php echo $corAtual['primary']; ?>"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"></path></svg>
                    <?php echo htmlspecialchars($userData['cidade']); ?>
                </span>
            </div>
        </div>
    </div>
    <div style="padding:30px 52px 40px;">
        <?php include __DIR__ . '/_pdf_secoes.php'; ?>
    </div>

<?php else: ?>
    <div style="background:<?php echo $corAtual['primary']; ?>;padding:34px 52px 26px;display:flex;align-items:center;gap:20px;color:#fff;">
        <?php if (!empty($userData['exibirFoto']) && $fotoUrl): ?>
            <img src="<?php echo htmlspecialchars($fotoUrl); ?>" style="width:80px;height:80px;border-radius:10px;object-fit:cover;border:2px solid rgba(255,255,255,.55);">
        <?php endif; ?>
        <div>
            <div style="font-size:27px;font-weight:900;color:#fff;margin-bottom:8px;"><?php echo htmlspecialchars($userData['nome']); ?></div>
            <div style="font-size:12px;color:rgba(255,255,255,.85);margin-bottom:6px;">Curriculo Profissional</div>
            <div style="font-size:11px;color:rgba(255,255,255,.9);line-height:2;display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="#fff"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"></path></svg>
                    <?php echo htmlspecialchars($userData['email']); ?>
                </span>
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="#fff"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24 11.36 11.36 0 0 0 3.58.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.4 21 3 13.6 3 4.5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.58a1 1 0 0 1-.25 1.02z"></path></svg>
                    <?php echo htmlspecialchars($userData['telefone']); ?>
                </span>
                <span style="display:flex;align-items:center;gap:5px;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="#fff"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"></path></svg>
                    <?php echo htmlspecialchars($userData['cidade']); ?>
                </span>
            </div>
        </div>
    </div>
    <div style="padding:30px 52px 40px;">
        <?php include __DIR__ . '/_pdf_secoes.php'; ?>
    </div>
<?php endif; ?>
</div>
<script>
const curriculumData = <?php echo json_encode($userData, JSON_UNESCAPED_UNICODE); ?>;
const TIPOS_FORMACAO = ['Graduacao','Tecnico','Pos-Graduacao','MBA','Mestrado','Doutorado'];
const NIVEIS_IDIOMA = <?php echo json_encode($niveisIdioma, JSON_UNESCAPED_UNICODE); ?>;

function escHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function mkEl(tag,cls){const e=document.createElement(tag);if(cls)e.className=cls;return e;}

let toastTimer = null;
function mostrarToast(texto){
    const toast = document.getElementById('autosaveToast');
    document.getElementById('autosaveToastText').textContent = texto;
    toast.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.add('hidden'), 2200);
}

let modalConfirmCallback = null;

function abrirModalConfirmacao(titulo, texto, onConfirmar){
    document.getElementById('modalConfirmacaoTitulo').textContent = titulo;
    document.getElementById('modalConfirmacaoTexto').textContent = texto;
    modalConfirmCallback = onConfirmar;
    document.getElementById('modalConfirmacao').classList.remove('hidden');
}

function fecharModalConfirmacao(){
    document.getElementById('modalConfirmacao').classList.add('hidden');
    modalConfirmCallback = null;
}

document.addEventListener('DOMContentLoaded', () => {
    const btnOk = document.getElementById('modalConfirmacaoBtnOk');
    if (btnOk) {
        btnOk.addEventListener('click', () => {
            if (typeof modalConfirmCallback === 'function') modalConfirmCallback();
            fecharModalConfirmacao();
        });
    }
    const modal = document.getElementById('modalConfirmacao');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target.id === 'modalConfirmacao') fecharModalConfirmacao();
        });
    }
});

function selecionarLayout(layout){
    document.getElementById('cvShell').setAttribute('data-layout', layout);
    document.querySelectorAll('[data-layout-option]').forEach(el => {
        el.classList.toggle('active', el.dataset.layoutOption === layout);
    });
    const fd = new FormData();
    fd.append('action', 'set_layout');
    fd.append('layout', layout);
    fetch('curriculum.php', {method:'POST', body:fd}).then(() => {
        mostrarToast('Modelo alterado -- atualizando...');
        setTimeout(() => location.reload(), 400);
    });
}

function selecionarCor(cor){
    document.querySelectorAll('[data-cor-option]').forEach(el => {
        el.classList.toggle('active', el.dataset.corOption === cor);
    });
    const fd = new FormData();
    fd.append('action', 'set_cor');
    fd.append('corTema', cor);
    fetch('curriculum.php', {method:'POST', body:fd}).then(() => {
        mostrarToast('Cor alterada -- atualizando...');
        setTimeout(() => location.reload(), 400);
    });
}

function toggleFoto(marcado){
    document.getElementById('fotoUploadArea').classList.toggle('hidden', !marcado);
    const fd = new FormData();
    fd.append('action', 'toggle_foto');
    fd.append('exibirFoto', marcado ? '1' : '0');
    fetch('curriculum.php', {method:'POST', body:fd}).then(() => {
        mostrarToast(marcado ? 'Foto ativada' : 'Foto desativada');
        setTimeout(() => location.reload(), 400);
    });
}

let autosaveTimer = null;
function agendarAutosave(){
    if (!modoEdicao) return;
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(() => {
        const form = document.getElementById('curriculumForm');
        const fd = new FormData(form);
        fd.set('action', 'autosave');
        fetch('curriculum.php', {method:'POST', body:fd})
            .then(r => r.json())
            .then(() => mostrarToast('Salvo automaticamente'))
            .catch(() => {});
    }, 900);
}

function maskPhone(value){
    let v = value.replace(/\D/g,'').slice(0,11);
    if (v.length > 10) {
        v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, function(_, a, b, c){ return c ? `(${a}) ${b}-${c}` : `(${a}) ${b}`; });
    } else if (v.length > 5) {
        v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, function(_, a, b, c){ return c ? `(${a}) ${b}-${c}` : `(${a}) ${b}`; });
    } else if (v.length > 2) {
        v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    } else if (v.length > 0) {
        v = v.replace(/(\d{0,2})/, '($1');
    }
    return v;
}
function bindPhoneMask(el){
    if (!el || el.dataset.maskBound) return;
    el.dataset.maskBound = '1';
    el.addEventListener('input', () => { el.value = maskPhone(el.value); agendarAutosave(); });
}
bindPhoneMask(document.getElementById('telefoneInput'));

const dpRegistry={};

class DatePicker{
    constructor(hiddenEl,disabled=false){
        this.hidden=hiddenEl; this.dis=disabled; this.view='days';
        this.today=new Date();
        this.MONTHS=['Janeiro','Fevereiro','Marco','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        this.MONTHS_S=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        const raw=hiddenEl.value||'';
        if(raw){const p=raw.split('-');this.sel={y:+p[0],m:+p[1]-1,d:+(p[2]||1)};this.vY=this.sel.y;this.vM=this.sel.m;}
        else{this.sel=null;this.vY=this.today.getFullYear();this.vM=this.today.getMonth();}
        this.build();this.render();
    }
    build(){
        const w=this.hidden.parentNode;
        this.disp=mkEl('div','cdp-display'+(this.dis?' cdp-disabled':''));
        this.disp.innerHTML=this.sel?`<span style="color:#374151">${this.fmtFull(this.sel.d,this.sel.m,this.sel.y)}</span>`:`<span style="color:#9ca3af">Selecionar data...</span>`;
        this.dd=mkEl('div','cdp-dropdown');
        w.appendChild(this.disp);w.appendChild(this.dd);
        if(!this.dis)this.disp.addEventListener('click',e=>{e.stopPropagation();this.toggle();});
        this.dd.addEventListener('click',e=>e.stopPropagation());
        document.addEventListener('click',()=>this.close());
    }
    toggle(){document.querySelectorAll('.cdp-dropdown.open').forEach(el=>{if(el!==this.dd)el.classList.remove('open');});this.dd.classList.toggle('open');this.disp.classList.toggle('active',this.dd.classList.contains('open'));}
    close(){this.dd.classList.remove('open');this.disp.classList.remove('active');}
    setDisabled(v){this.dis=v;this.disp.classList.toggle('cdp-disabled',v);if(v)this.close();}
    render(){
        this.dd.innerHTML='';
        const hdr=mkEl('div','cdp-header');
        const prev=mkEl('button','cdp-nav');prev.innerHTML='&#8249;';prev.type='button';prev.onclick=()=>this.nav(-1);
        const info=mkEl('div','cdp-header-info');
        info.onclick=()=>{if(this.view==='days')this.view='months';else if(this.view==='months')this.view='years';this.render();};
        const mSpan=mkEl('div','cdp-header-month');
        const ySpan=mkEl('div','cdp-header-year');
        if(this.view==='days'){mSpan.textContent=this.MONTHS[this.vM];ySpan.textContent=this.vY+' v';}
        else if(this.view==='months'){mSpan.textContent=this.vY;ySpan.textContent='Selecione o mes v';}
        else{const s=Math.floor(this.vY/16)*16;mSpan.textContent=`${s} - ${s+15}`;ySpan.textContent='Selecione o ano';}
        info.append(mSpan,ySpan);
        const next=mkEl('button','cdp-nav');next.innerHTML='&#8250;';next.type='button';next.onclick=()=>this.nav(1);
        hdr.append(prev,info,next);this.dd.appendChild(hdr);
        const tabs=mkEl('div','cdp-tabs');
        [['days','Dia'],['months','Mes'],['years','Ano']].forEach(([v,lbl])=>{
            const t=mkEl('div','cdp-tab'+(this.view===v?' active':''));t.textContent=lbl;
            t.onclick=()=>{this.view=v;this.render();};tabs.appendChild(t);
        });this.dd.appendChild(tabs);
        if(this.view==='days')this.renderDays();
        else if(this.view==='months')this.renderMonths();
        else this.renderYears();
        const foot=mkEl('div','cdp-footer');
        const selD=mkEl('div','cdp-sel-display');
        selD.textContent=this.sel?`${String(this.sel.d).padStart(2,'0')}/${String(this.sel.m+1).padStart(2,'0')}/${this.sel.y}`:'';
        const bClr=mkEl('button','cdp-btn cdp-btn-clear');bClr.type='button';bClr.textContent='Limpar';
        bClr.onclick=()=>{this.sel=null;this.hidden.value='';this.disp.innerHTML=`<span style="color:#9ca3af">Selecionar data...</span>`;this.close();agendarAutosave();};
        const bTod=mkEl('button','cdp-btn cdp-btn-today');bTod.type='button';bTod.textContent='Hoje';
        bTod.onclick=()=>{const t=this.today;this.pick(t.getFullYear(),t.getMonth(),t.getDate());};
        foot.append(bClr,selD,bTod);this.dd.appendChild(foot);
    }
    renderDays(){
        const wk=mkEl('div','cdp-weekdays');
        ['Dom','Seg','Ter','Qua','Qui','Sex','Sab'].forEach(d=>{const e=mkEl('div','cdp-weekday');e.textContent=d;wk.appendChild(e);});
        this.dd.appendChild(wk);
        const grid=mkEl('div','cdp-days');
        const first=new Date(this.vY,this.vM,1).getDay();
        const total=new Date(this.vY,this.vM+1,0).getDate();
        const prev=new Date(this.vY,this.vM,0).getDate();
        for(let i=first-1;i>=0;i--){const pY=this.vM===0?this.vY-1:this.vY,pM=this.vM===0?11:this.vM-1;grid.appendChild(this.dayEl(prev-i,pY,pM,true));}
        for(let d=1;d<=total;d++){
            const e=this.dayEl(d,this.vY,this.vM,false);
            if(d===this.today.getDate()&&this.vM===this.today.getMonth()&&this.vY===this.today.getFullYear())e.classList.add('today');
            if(this.sel&&d===this.sel.d&&this.vM===this.sel.m&&this.vY===this.sel.y)e.classList.add('selected');
            grid.appendChild(e);
        }
        const rem=(first+total)%7;
        if(rem>0){for(let d=1;d<=7-rem;d++){const nY=this.vM===11?this.vY+1:this.vY,nM=this.vM===11?0:this.vM+1;grid.appendChild(this.dayEl(d,nY,nM,true));}}
        this.dd.appendChild(grid);
    }
    dayEl(d,y,m,other){const e=mkEl('div','cdp-day'+(other?' other-month':''));e.textContent=d;e.onclick=()=>this.pick(y,m,d);return e;}
    renderMonths(){
        const grid=mkEl('div','cdp-months');
        this.MONTHS_S.forEach((name,i)=>{
            const e=mkEl('div','cdp-month-item');e.textContent=name;
            if(i===this.today.getMonth()&&this.vY===this.today.getFullYear())e.classList.add('cur-month');
            if(this.sel&&i===this.sel.m&&this.vY===this.sel.y)e.classList.add('selected');
            e.onclick=()=>{this.vM=i;this.view='days';this.render();};grid.appendChild(e);
        });this.dd.appendChild(grid);
    }
    renderYears(){
        const start=Math.floor(this.vY/16)*16;
        const grid=mkEl('div','cdp-years');
        for(let y=start;y<start+16;y++){
            const e=mkEl('div','cdp-year-item');e.textContent=y;
            if(y===this.today.getFullYear())e.classList.add('cur-year');
            if(this.sel&&y===this.sel.y)e.classList.add('selected');
            e.onclick=()=>{this.vY=y;this.view='months';this.render();};grid.appendChild(e);
        }this.dd.appendChild(grid);
    }
    nav(dir){
        if(this.view==='days'){this.vM+=dir;if(this.vM>11){this.vM=0;this.vY++;}if(this.vM<0){this.vM=11;this.vY--;}}
        else if(this.view==='months'){this.vY+=dir;}
        else{this.vY+=dir*16;}
        this.render();
    }
    pick(y,m,d){
        this.sel={y,m,d};this.vY=y;this.vM=m;
        const mm=String(m+1).padStart(2,'0'),dd=String(d).padStart(2,'0');
        this.hidden.value=`${y}-${mm}-${dd}`;
        this.disp.innerHTML=`<span style="color:#374151">${this.fmtFull(d,m,y)}</span>`;
        this.close();this.render();
        agendarAutosave();
    }
    fmtFull(d,m,y){return `${String(d).padStart(2,'0')} de ${this.MONTHS[m]} de ${y}`;}
}

function initDP(fieldId,disabled=false){
    const el=document.getElementById(fieldId);
    if(el&&!dpRegistry[fieldId])dpRegistry[fieldId]=new DatePicker(el,disabled);
}

function buildExpHTML(idx,data={}){
    const isAtual=(data.atual||'0')==='1';
    return `
    <div class="exp-entry bg-gray-50 border-2 border-gray-100 rounded-2xl p-5" data-idx="${idx}">
        <div class="flex justify-between items-center mb-4">
            <span class="text-xs font-bold text-[var(--cv-primary)] uppercase tracking-wide">Experiencia</span>
            <button type="button" onclick="removeExp(this)" class="remove-exp-btn text-xs text-red-400 hover:text-red-600 font-semibold px-2 py-1 rounded-lg hover:bg-red-50 transition-colors"><i class="fas fa-trash-alt"></i> Remover</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Cargo *</label>
                <input type="text" name="exp_${idx}_cargo" value="${escHtml(data.cargo||'')}" placeholder="Ex: Estagiario de TI" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Empresa</label>
                <input type="text" name="exp_${idx}_empresa" value="${escHtml(data.empresa||'')}" placeholder="Nome da empresa" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Cidade</label>
                <input type="text" name="exp_${idx}_cidade" value="${escHtml(data.cidade||'')}" placeholder="Cidade, Estado" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 items-end">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Data de Inicio</label>
                <div class="cdp-wrapper"><input type="hidden" name="exp_${idx}_inicio" id="dp_exp_${idx}_inicio" value="${escHtml(data.inicio||'')}"></div></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Data de Termino</label>
                <div class="cdp-wrapper"><input type="hidden" name="exp_${idx}_fim" id="dp_exp_${idx}_fim" value="${escHtml(!isAtual?(data.fim||''):'')}"></div></div>
            <div class="flex items-center gap-2 pb-1">
                <input type="hidden" name="exp_${idx}_atual" id="exp_${idx}_atual_val" value="${isAtual?'1':'0'}">
                <input type="checkbox" id="exp_${idx}_atual_cb" ${isAtual?'checked':''} onchange="toggleExpAtual(${idx})" class="w-4 h-4 accent-[var(--cv-primary)] cursor-pointer">
                <label for="exp_${idx}_atual_cb" class="text-sm text-gray-600 font-medium cursor-pointer">Emprego atual</label>
            </div>
        </div>
        <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Descricao das Atividades</label>
            <textarea name="exp_${idx}_descricao" rows="3" placeholder="Descreva suas principais atividades..." class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]">${escHtml(data.descricao||'')}</textarea></div>
    </div>`;
}

function buildFormHTML(idx,data={}){
    const isAtual=(data.atual||'0')==='1';
    const tipoOpts=TIPOS_FORMACAO.map(t=>`<option value="${t}" ${(data.tipo||'')===t?'selected':''}>${t}</option>`).join('');
    return `
    <div class="form-entry bg-gray-50 border-2 border-gray-100 rounded-2xl p-5" data-idx="${idx}">
        <div class="flex justify-between items-center mb-4">
            <span class="text-xs font-bold text-[var(--cv-primary)] uppercase tracking-wide">Formacao</span>
            <button type="button" onclick="removeForm(this)" class="remove-form-btn text-xs text-red-400 hover:text-red-600 font-semibold px-2 py-1 rounded-lg hover:bg-red-50 transition-colors"><i class="fas fa-trash-alt"></i> Remover</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Curso *</label>
                <input type="text" name="form_${idx}_curso" value="${escHtml(data.curso||'')}" placeholder="Ex: Ciencia de Dados" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Instituicao</label>
                <input type="text" name="form_${idx}_instituicao" value="${escHtml(data.instituicao||'')}" placeholder="Nome da instituicao" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Tipo</label>
                <select name="form_${idx}_tipo" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]">${tipoOpts}</select></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Data de Inicio</label>
                                <div class="cdp-wrapper"><input type="hidden" name="form_${idx}_inicio" id="dp_form_${idx}_inicio" value="${escHtml(data.inicio||'')}"></div></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Previsao de Conclusao</label>
                <div class="cdp-wrapper"><input type="hidden" name="form_${idx}_fim" id="dp_form_${idx}_fim" value="${escHtml(data.fim||'')}"></div></div>
            <div class="flex items-center gap-2 pb-1">
                <input type="hidden" name="form_${idx}_atual" id="form_${idx}_atual_val" value="${isAtual?'1':'0'}">
                <input type="checkbox" id="form_${idx}_atual_cb" ${isAtual?'checked':''} onchange="toggleFormAtual(${idx})" class="w-4 h-4 accent-[var(--cv-primary)] cursor-pointer">
                <label for="form_${idx}_atual_cb" class="text-sm text-gray-600 font-medium cursor-pointer">Cursando atualmente</label>
            </div>
        </div>
    </div>`;
}

function buildIdiomaHTML(idx,data={}){
    const nivelOpts=NIVEIS_IDIOMA.map(n=>`<option value="${n}" ${(data.nivel||'')===n?'selected':''}>${n}</option>`).join('');
    return `
    <div class="idioma-entry bg-gray-50 border-2 border-gray-100 rounded-2xl p-4" data-idx="${idx}">
        <div class="flex justify-between items-center mb-3">
            <span class="text-xs font-bold text-[var(--cv-primary)] uppercase tracking-wide">Idioma</span>
            <button type="button" onclick="removeIdioma(this)" class="remove-idioma-btn text-xs text-red-400 hover:text-red-600 font-semibold px-2 py-1 rounded-lg hover:bg-red-50 transition-colors"><i class="fas fa-trash-alt"></i> Remover</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Idioma *</label>
                <input type="text" name="idioma_${idx}_idioma" value="${escHtml(data.idioma||'')}" placeholder="Ex: Ingles" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]"></div>
            <div><label class="block text-xs font-semibold text-gray-400 mb-1 uppercase">Nivel</label>
                <select name="idioma_${idx}_nivel" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-gray-700 focus:outline-none focus:border-[var(--cv-primary)]">${nivelOpts}</select></div>
        </div>
    </div>`;
}

function initExpDPs(idx,data={}){
    initDP(`dp_exp_${idx}_inicio`,false);
    initDP(`dp_exp_${idx}_fim`,(data.atual||'0')==='1');
}
function initFormDPs(idx){
    initDP(`dp_form_${idx}_inicio`,false);
    initDP(`dp_form_${idx}_fim`,false);
}

function toggleExpAtual(idx){
    const cb=document.getElementById(`exp_${idx}_atual_cb`);
    const val=document.getElementById(`exp_${idx}_atual_val`);
    val.value=cb.checked?'1':'0';
    const dp=dpRegistry[`dp_exp_${idx}_fim`];
    if(dp){dp.setDisabled(cb.checked);if(cb.checked){dp.sel=null;dp.hidden.value='';dp.disp.innerHTML=`<span style="color:#9ca3af">Selecionar data...</span>`;}}
    agendarAutosave();
}
function toggleFormAtual(idx){
    const cb=document.getElementById(`form_${idx}_atual_cb`);
    document.getElementById(`form_${idx}_atual_val`).value=cb.checked?'1':'0';
    agendarAutosave();
}

function removeExp(btn){
    const entry=btn.closest('.exp-entry');
    entry.querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);
    entry.style.display='none';
    updateRemoveExpBtns();
    agendarAutosave();
}
function removeForm(btn){
    const entry=btn.closest('.form-entry');
    entry.querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);
    entry.style.display='none';
    updateRemoveFormBtns();
    agendarAutosave();
}
function removeIdioma(btn){
    const entry=btn.closest('.idioma-entry');
    entry.querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);
    entry.style.display='none';
    updateRemoveIdiomaBtns();
    agendarAutosave();
}
function updateRemoveExpBtns(){
    const vis=[...document.querySelectorAll('#expEditContainer .exp-entry')].filter(e=>e.style.display!=='none');
    vis.forEach(e=>{const b=e.querySelector('.remove-exp-btn');if(b)b.style.display=vis.length<=1?'none':'flex';});
}
function updateRemoveFormBtns(){
    const vis=[...document.querySelectorAll('#formEditContainer .form-entry')].filter(e=>e.style.display!=='none');
    vis.forEach(e=>{const b=e.querySelector('.remove-form-btn');if(b)b.style.display=vis.length<=1?'none':'flex';});
}
function updateRemoveIdiomaBtns(){
    const vis=[...document.querySelectorAll('#idiomasEditContainer .idioma-entry')].filter(e=>e.style.display!=='none');
    vis.forEach(e=>{const b=e.querySelector('.remove-idioma-btn');if(b)b.style.display=vis.length===0?'none':'flex';});
}

let expCounter=0, formCounter=0, idiomaCounter=0, modoEdicao=false;

function addExperiencia(){
    const idx=expCounter++;
    document.getElementById('expEditContainer').insertAdjacentHTML('beforeend',buildExpHTML(idx));
    document.getElementById('exp_count').value=expCounter;
    initExpDPs(idx);
    updateRemoveExpBtns();
    bindAutosaveListeners();
    document.getElementById('expEditContainer').lastElementChild.scrollIntoView({behavior:'smooth',block:'nearest'});
    agendarAutosave();
}
function addFormacao(){
    const idx=formCounter++;
    document.getElementById('formEditContainer').insertAdjacentHTML('beforeend',buildFormHTML(idx));
    document.getElementById('form_count').value=formCounter;
    initFormDPs(idx);
    updateRemoveFormBtns();
    bindAutosaveListeners();
    document.getElementById('formEditContainer').lastElementChild.scrollIntoView({behavior:'smooth',block:'nearest'});
    agendarAutosave();
}
function addIdioma(){
    const idx=idiomaCounter++;
    document.getElementById('idiomasEditContainer').insertAdjacentHTML('beforeend',buildIdiomaHTML(idx));
    document.getElementById('idioma_count').value=idiomaCounter;
    updateRemoveIdiomaBtns();
    bindAutosaveListeners();
    document.getElementById('idiomasEditContainer').lastElementChild.scrollIntoView({behavior:'smooth',block:'nearest'});
    agendarAutosave();
}

function bindAutosaveListeners(){
    document.querySelectorAll('#curriculumForm input[type="text"], #curriculumForm input[type="email"], #curriculumForm textarea, #curriculumForm select').forEach(el => {
        if (el.dataset.autosaveBound) return;
        el.dataset.autosaveBound = '1';
        el.addEventListener('input', agendarAutosave);
        el.addEventListener('change', agendarAutosave);
    });
}
function toggleEdit(){
    modoEdicao=true;
    document.querySelectorAll('.curriculum-field').forEach(f=>{f.removeAttribute('readonly');});
    document.getElementById('btnEditar').classList.add('hidden');
    document.getElementById('btnSalvar').classList.remove('hidden');
    document.getElementById('btnCancelar').classList.remove('hidden');
    document.getElementById('btnAddExp').style.display='flex';
    document.getElementById('btnAddForm').style.display='flex';
    document.getElementById('btnAddIdioma').style.display='flex';

    document.getElementById('expViewContainer').classList.add('hidden');
    const expEdit = document.getElementById('expEditContainer');
    expEdit.classList.remove('hidden');
    expEdit.innerHTML='';
    expCounter=0;
    if (curriculumData.experiencias && curriculumData.experiencias.length){
        curriculumData.experiencias.forEach(exp=>{
            const idx=expCounter++;
            expEdit.insertAdjacentHTML('beforeend', buildExpHTML(idx, exp));
            initExpDPs(idx, exp);
        });
    } else {
        addExperiencia();
    }
    document.getElementById('exp_count').value = expCounter;
    updateRemoveExpBtns();

    document.getElementById('formViewContainer').classList.add('hidden');
    const formEdit = document.getElementById('formEditContainer');
    formEdit.classList.remove('hidden');
    formEdit.innerHTML='';
    formCounter=0;
    if (curriculumData.formacoes && curriculumData.formacoes.length){
        curriculumData.formacoes.forEach(form=>{
            const idx=formCounter++;
            formEdit.insertAdjacentHTML('beforeend', buildFormHTML(idx, form));
            initFormDPs(idx);
        });
    } else {
        addFormacao();
    }
    document.getElementById('form_count').value = formCounter;
    updateRemoveFormBtns();

    document.getElementById('skillsView').classList.add('hidden');
    document.getElementById('skillsEdit').classList.remove('hidden');

    document.getElementById('idiomasView').classList.add('hidden');
    const idiomasEdit = document.getElementById('idiomasEditContainer');
    idiomasEdit.classList.remove('hidden');
    idiomasEdit.innerHTML='';
    idiomaCounter=0;
    if (curriculumData.idiomas && curriculumData.idiomas.length){
        curriculumData.idiomas.forEach(idi=>{
            const idx=idiomaCounter++;
            idiomasEdit.insertAdjacentHTML('beforeend', buildIdiomaHTML(idx, idi));
        });
    }
    document.getElementById('idioma_count').value = idiomaCounter;
    updateRemoveIdiomaBtns();

    bindAutosaveListeners();
}

function cancelarEdicao(){
    location.reload();
}

function salvarCurriculo(){
    document.getElementById('curriculumForm').submit();
}

function baixarPDF(){
    const el = document.getElementById('curriculumPDF');

    html2canvas(el, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        windowWidth: el.scrollWidth,
        windowHeight: el.scrollHeight
    }).then(canvas => {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageWidth = 210;
        const pageHeight = 297;
        const imgWidth = pageWidth;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        let heightLeft = imgHeight;
        let position = 0;
        const imgData = canvas.toDataURL('image/png');

        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft > 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        const nomeArquivo = (curriculumData.nome || 'curriculo')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-zA-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'curriculo';

        pdf.save(`Curriculo_${nomeArquivo}.pdf`);
    }).catch(err => {
        console.error('Erro ao gerar PDF:', err);
        alert('Nao foi possivel gerar o PDF. Tente novamente.');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_GET['saved'])): ?>
        mostrarToast('Currículo salvo com sucesso!');
    <?php endif; ?>
    <?php if (isset($_GET['foto_ok'])): ?>
        mostrarToast('Foto atualizada!');
    <?php endif; ?>
    <?php if (isset($_GET['foto_removida'])): ?>
        mostrarToast('Foto removida!');
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>

