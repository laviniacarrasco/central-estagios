<?php
/**
 * Partial reutilizado pelo template de PDF em curriculum.php.
 * Espera que $userData, $corAtual, $certs, $layoutAtual, $textos,
 * $tiposFormacao, $niveisIdioma e fmtData() ja existam.
 * Usa apenas 'primary' e 'secondary' -- sem preenchimentos solidos de caixa.
 */
$mostrarHabilidadesAqui = ($layoutAtual !== 'moderno');
?>

<div style="margin-bottom:22px;">
    <div style="border-bottom:2px solid <?php echo $corAtual['primary']; ?>;padding-bottom:6px;margin-bottom:10px;">
        <span style="font-size:11px;font-weight:800;color:<?php echo $corAtual['primary']; ?>;text-transform:uppercase;letter-spacing:1.5px;"><?php echo $textos['titulo_resumo']; ?></span>
    </div>
    <div style="font-size:12px;color:#444;line-height:1.7;"><?php echo htmlspecialchars($userData['resumo']); ?></div>
</div>
<div style="margin-bottom:22px;">
    <div style="border-bottom:2px solid <?php echo $corAtual['primary']; ?>;padding-bottom:6px;margin-bottom:10px;">
        <span style="font-size:11px;font-weight:800;color:<?php echo $corAtual['primary']; ?>;text-transform:uppercase;letter-spacing:1.5px;"><?php echo $textos['titulo_experiencia']; ?></span>
    </div>
    <?php foreach ($userData['experiencias'] as $exp):
        $ini = fmtData($exp['inicio']);
        $fim = ($exp['atual'] ?? '0') === '1' ? 'Atual' : fmtData($exp['fim']);
        $per = $ini . ($fim ? ' - ' . $fim : '');
    ?>
    <div style="margin-bottom:14px;padding-left:12px;border-left:2px solid #eee;">
        <table style="width:100%;border-collapse:collapse;margin-bottom:4px;">
            <tr>
                <td style="font-size:12px;color:#222;">
                    <span style="font-weight:700;"><?php echo htmlspecialchars($exp['cargo']); ?></span>
                    <span style="color:#555;"> &nbsp;|&nbsp; <?php echo htmlspecialchars($exp['empresa']); ?> - <?php echo htmlspecialchars($exp['cidade']); ?></span>
                </td>
                <td style="text-align:right;font-size:11px;color:<?php echo $corAtual['secondary']; ?>;font-weight:700;white-space:nowrap;width:130px;text-transform:uppercase;letter-spacing:.3px;"><?php echo $per; ?></td>
            </tr>
        </table>
        <div style="font-size:12px;color:#555;line-height:1.6;"><?php echo htmlspecialchars($exp['descricao']); ?></div>
    </div>
    <?php endforeach; ?>
</div>
<div style="margin-bottom:22px;">
    <div style="border-bottom:2px solid <?php echo $corAtual['primary']; ?>;padding-bottom:6px;margin-bottom:10px;">
        <span style="font-size:11px;font-weight:800;color:<?php echo $corAtual['primary']; ?>;text-transform:uppercase;letter-spacing:1.5px;"><?php echo $textos['titulo_formacao']; ?></span>
    </div>
    <?php foreach ($userData['formacoes'] as $form):
        $ini        = fmtData($form['inicio']);
        $fim        = fmtData($form['fim']);
        $periodo    = $ini . ($fim ? ' - ' . $fim : '');
        $tipoLabel  = $tiposFormacao[$form['tipo']] ?? $form['tipo'];
    ?>
    <div style="margin-bottom:10px;padding-left:12px;border-left:2px solid #eee;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top;">
                    <div style="font-size:13px;font-weight:700;color:<?php echo $corAtual['primary']; ?>;margin-bottom:3px;"><?php echo htmlspecialchars($form['instituicao']); ?></div>
                    <div style="font-size:12px;color:#555;"><?php echo htmlspecialchars($tipoLabel); ?> em <?php echo htmlspecialchars($form['curso']); ?></div>
                </td>
                <td style="text-align:right;vertical-align:top;font-size:11px;color:<?php echo $corAtual['secondary']; ?>;font-weight:700;white-space:nowrap;width:160px;text-transform:uppercase;letter-spacing:.3px;"><?php echo $periodo; ?></td>
            </tr>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($mostrarHabilidadesAqui): ?>
<div style="margin-bottom:22px;">
    <div style="border-bottom:2px solid <?php echo $corAtual['primary']; ?>;padding-bottom:6px;margin-bottom:10px;">
        <span style="font-size:11px;font-weight:800;color:<?php echo $corAtual['primary']; ?>;text-transform:uppercase;letter-spacing:1.5px;"><?php echo $textos['titulo_habilidades']; ?></span>
    </div>
    <?php
    $habs = $userData['habilidades'];
    $mid  = ceil(count($habs) / 2);
    $c1   = array_slice($habs, 0, $mid);
    $c2   = array_slice($habs, $mid);
    ?>
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="vertical-align:top;width:50%;padding-right:20px;">
                <?php foreach ($c1 as $s): ?>
                <div style="font-size:12px;color:#444;padding:4px 0;border-bottom:1px dashed #e5e5e5;"><?php echo htmlspecialchars(trim($s)); ?></div>
                <?php endforeach; ?>
            </td>
            <td style="vertical-align:top;width:50%;">
                <?php foreach ($c2 as $s): ?>
                <div style="font-size:12px;color:#444;padding:4px 0;border-bottom:1px dashed #e5e5e5;"><?php echo htmlspecialchars(trim($s)); ?></div>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>
</div>
<?php endif; ?>
<?php if (!empty($userData['idiomas'])): ?>
<div style="margin-bottom:22px;">
    <div style="border-bottom:2px solid <?php echo $corAtual['primary']; ?>;padding-bottom:6px;margin-bottom:10px;">
        <span style="font-size:11px;font-weight:800;color:<?php echo $corAtual['primary']; ?>;text-transform:uppercase;letter-spacing:1.5px;"><?php echo $textos['titulo_idiomas']; ?></span>
    </div>
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <?php foreach ($userData['idiomas'] as $idi):
                $nivelLabel = $niveisIdioma[$idi['nivel']] ?? $idi['nivel'];
            ?>
            <td style="padding:0 24px 0 0;vertical-align:top;">
                <div style="border-bottom:1px dashed #ddd;padding:6px 0;">
                    <div style="font-size:12px;font-weight:700;color:#1a1a1a;"><?php echo htmlspecialchars($idi['idioma']); ?></div>
                    <div style="font-size:10px;color:<?php echo $corAtual['secondary']; ?>;font-weight:700;text-transform:uppercase;letter-spacing:.3px;"><?php echo htmlspecialchars($nivelLabel); ?></div>
                </div>
            </td>
            <?php endforeach; ?>
        </tr>
    </table>
</div>
<?php endif; ?>
<?php if (!empty($certs)): ?>
<div style="margin-bottom:20px;">
    <div style="border-bottom:2px solid <?php echo $corAtual['primary']; ?>;padding-bottom:6px;margin-bottom:10px;">
        <span style="font-size:11px;font-weight:800;color:<?php echo $corAtual['primary']; ?>;text-transform:uppercase;letter-spacing:1.5px;"><?php echo $textos['titulo_certificados']; ?></span>
    </div>
    <table style="width:100%;border-collapse:collapse;">
        <thead><tr style="border-bottom:1px solid #e5e7eb;">
            <th style="text-align:left;padding:6px 4px;font-size:10px;color:#999;font-weight:700;text-transform:uppercase;"><?php echo $textos['tabela_curso']; ?></th>
            <th style="text-align:left;padding:6px 4px;font-size:10px;color:#999;font-weight:700;text-transform:uppercase;"><?php echo $textos['tabela_instituicao']; ?></th>
            <th style="text-align:center;padding:6px 4px;font-size:10px;color:#999;font-weight:700;text-transform:uppercase;"><?php echo $textos['tabela_carga']; ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($certs as $cert): ?>
        <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:8px 4px;font-size:12px;font-weight:600;color:#1a1a1a;"><?php echo htmlspecialchars($cert['title']); ?></td>
            <td style="padding:8px 4px;font-size:12px;color:#666;"><?php echo htmlspecialchars($cert['institution']); ?></td>
            <td style="padding:8px 4px;font-size:12px;color:<?php echo $corAtual['primary']; ?>;font-weight:600;text-align:center;"><?php echo htmlspecialchars($cert['hours']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
