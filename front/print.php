<?php

/**
 * Review of the labels selected through the massive action, and PDF output.
 *
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Etiquetas\LabelPrinter;
use GlpiPlugin\Etiquetas\Template;
use Symfony\Component\HttpFoundation\Response;

/** @var array $CFG_GLPI */
global $CFG_GLPI;

Session::checkLoginUser();

$template = LabelPrinter::getTemplateForUse((int) ($_REQUEST['template'] ?? 0));

if (isset($_POST['generate']) && $template !== null) {
    $editable = array_keys($template->getEditableFields());
    $labels   = [];
    foreach ((array) ($_POST['labels'] ?? []) as $row) {
        $itemtype = (string) ($row['itemtype'] ?? '');
        $id       = (int) ($row['id'] ?? 0);
        $label    = LabelPrinter::buildLabel($itemtype, $id);
        if ($label === null) {
            continue;
        }

        // Edited texts are kept in the session; number and ID always come from the database
        foreach ($editable as $field) {
            if (isset($row['fields'][$field])) {
                $value = LabelPrinter::cleanText($row['fields'][$field]);
                LabelPrinter::rememberText($itemtype, $id, $field, $value);
                $label['values'][$field] = $value;
            }
        }

        if (!empty($row['print']) && LabelPrinter::isPrintable($template, $label)) {
            $labels[] = $label;
        }
    }

    if ($labels === []) {
        Session::addMessageAfterRedirect(__('No label selected for printing.', 'etiquetas'), false, ERROR);
        Html::back();
    }

    return new Response(LabelPrinter::renderPdf($template, $labels), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="labels.pdf"',
        'Cache-Control'       => 'no-store',
    ]);
}

$labels = [];
foreach (LabelPrinter::getSelection() as $selected) {
    $label = LabelPrinter::buildLabel($selected['itemtype'], $selected['id']);
    if ($label !== null) {
        $labels[] = $label;
    }
}

$self      = htmlescape($CFG_GLPI['root_doc'] . '/plugins/etiquetas/front/print.php');
$can_admin = Session::haveRight('config', UPDATE);

Html::header(LabelPrinter::getTypeName(), '', 'assets');

echo '<div class="container-fluid">';
echo '<h2 class="my-3"><i class="ti ti-tag me-2"></i>' . htmlescape(LabelPrinter::getTypeName()) . '</h2>';

if ($template === null) {
    echo '<div class="alert alert-warning">' . htmlescape(__('No label template defined.', 'etiquetas'))
        . ($can_admin ? " <a href='" . htmlescape(Template::getFormURL()) . "'>" . htmlescape(__('Create a template', 'etiquetas')) . '</a>' : '')
        . '</div></div>';
    Html::footer();
    return;
}

// Template choice (GET: reloads the page with the chosen template)
echo "<form method='get' action='$self' class='row g-2 align-items-center mb-3'>";
echo "<div class='col-auto'><label class='col-form-label' for='etiquetas-template'>" . htmlescape(Template::getTypeName(1)) . '</label></div>';
echo "<div class='col-auto'><select class='form-select' id='etiquetas-template' name='template' onchange='this.form.submit()'>";
foreach (Template::getList() as $id => $name) {
    $selected = $id === (int) $template->getID() ? ' selected' : '';
    echo "<option value='$id'$selected>" . htmlescape($name) . '</option>';
}
echo '</select></div>';
echo "<div class='col-auto text-muted'>" . htmlescape($template->getSummary()) . '</div>';
if ($can_admin) {
    echo "<div class='col-auto'><a href='" . htmlescape(Template::getSearchURL()) . "'><i class='ti ti-settings me-1'></i>"
        . htmlescape(__('Manage templates', 'etiquetas')) . '</a></div>';
}
echo '</form>';

if ($labels === []) {
    echo '<div class="alert alert-info">'
        . sprintf(
            htmlescape(__('No asset selected. Open an asset list, select the assets to print and use %s.', 'etiquetas')),
            '<b>' . htmlescape(__('Actions') . ' → ' . __('Print labels', 'etiquetas')) . '</b>'
        )
        . '</div></div>';
    Html::footer();
    return;
}

$editable     = $template->getEditableFields();
$needs_number = $template->requiresNumber();
$printable    = count(array_filter($labels, fn($label) => LabelPrinter::isPrintable($template, $label)));
$columns      = max(1, (int) $template->num('label_columns'));

echo '<p>' . sprintf(
    htmlescape(__('Check the texts before generating. The number comes from the %s field of the asset.', 'etiquetas')),
    '<b>' . htmlescape(__('Inventory number', 'etiquetas')) . '</b>'
);
if ($needs_number) {
    echo ' ' . htmlescape(__('As this template uses the number, assets without one stay blocked until it is filled in.', 'etiquetas'));
}
echo '</p>';
echo "<form method='post' action='$self' target='_blank' id='etiquetas-form'>";
echo "<input type='hidden' name='template' value='" . (int) $template->getID() . "'>";
echo '<div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr>';
foreach (array_merge([__('Print', 'etiquetas'), __('Asset', 'etiquetas'), __('Number', 'etiquetas')], array_values($editable)) as $title) {
    echo '<th>' . htmlescape($title) . '</th>';
}
echo '</tr></thead><tbody>';

foreach ($labels as $index => $label) {
    $prefix = "labels[$index]";
    $number = $label['values']['inventory'];

    echo '<tr><td>';
    echo "<input type='hidden' name='{$prefix}[itemtype]' value='" . htmlescape($label['itemtype']) . "'>";
    echo "<input type='hidden' name='{$prefix}[id]' value='" . (int) $label['id'] . "'>";
    echo "<input type='checkbox' class='form-check-input' name='{$prefix}[print]' value='1'"
        . (LabelPrinter::isPrintable($template, $label) ? ' checked' : ' disabled') . '>';
    echo '</td>';
    echo "<td><a href='" . htmlescape($label['link']) . "' target='_blank'>" . htmlescape($label['asset']) . '</a></td>';
    if ($number !== '') {
        echo '<td><b>' . htmlescape($number) . '</b></td>';
    } elseif ($needs_number) {
        echo "<td><span class='badge bg-warning text-dark'>" . htmlescape(__('no number', 'etiquetas')) . '</span></td>';
    } else {
        echo "<td class='text-muted'>-</td>";
    }
    foreach (array_keys($editable) as $field) {
        echo "<td><input type='text' class='form-control form-control-sm' maxlength='" . LabelPrinter::MAX_TEXT . "'"
            . " name='{$prefix}[fields][$field]' value='" . htmlescape($label['values'][$field] ?? '') . "'></td>";
    }
    echo '</tr>';
}
echo '</tbody></table></div>';

echo "<p class='text-muted'>"
    . htmlescape(sprintf(__('%1$d label(s) = %2$d page(s) (rows of the roll).', 'etiquetas'), $printable, (int) ceil($printable / $columns)))
    . ' '
    . htmlescape(__('To print: in the PDF press Ctrl+P, choose the label printer, set the scale to actual size (100%) and the margins to none.', 'etiquetas'))
    . '</p>';
echo '<button type="submit" name="generate" value="1" class="btn btn-primary"'
    . ($printable === 0 ? ' disabled' : '') . '><i class="ti ti-file-type-pdf me-1"></i>'
    . htmlescape(__('Generate PDF', 'etiquetas')) . '</button>';
Html::closeForm();
echo '</div>';

// The CSRF token of the form is single-use: after the PDF opens in another tab,
// reload this page (edited texts are kept in the session).
echo Html::scriptBlock(<<<JS
    document.getElementById('etiquetas-form').addEventListener('submit', () => {
        setTimeout(() => window.location.reload(), 1500);
    });
JS);

Html::footer();
