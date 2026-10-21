<?php

/**
 * Label template form, preview PDF and calibration PDF.
 *
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Etiquetas\LabelPrinter;
use GlpiPlugin\Etiquetas\Template;
use Symfony\Component\HttpFoundation\Response;

Session::checkRight('config', UPDATE);

$template = new Template();
$id       = (int) ($_REQUEST['id'] ?? 0);

if (isset($_POST['add'])) {
    $new_id = $template->add($_POST);
    if ($new_id) {
        $template->handleLogoUpload($_FILES['logo'] ?? null);
        Html::redirect(Template::getFormURLWithID($new_id));
    }
    Html::back();
}

if ($id > 0 && !$template->getFromDB($id)) {
    Session::addMessageAfterRedirect(__('Template not found.', 'etiquetas'), false, ERROR);
    Html::redirect(Template::getSearchURL());
}

if (isset($_POST['update'])) {
    if ($template->update($_POST)) {
        $template->getFromDB($id);
        $template->handleLogoUpload($_FILES['logo'] ?? null);
    }
    Html::back();
}

if (isset($_POST['remove_logo'])) {
    $template->removeLogo();
    Html::back();
}

if (isset($_POST['duplicate'])) {
    $input = $template->fields;
    unset($input['id'], $input['date_creation'], $input['date_mod']);
    $input['name']       = sprintf(__('%s (copy)', 'etiquetas'), $input['name']);
    $input['is_default'] = 0;
    $input['logo_file']  = '';
    $copy   = new Template();
    $new_id = $copy->add($input);
    if ($new_id) {
        $template->copyLogoTo($copy);
        Html::redirect(Template::getFormURLWithID($new_id));
    }
    Html::back();
}

if (isset($_POST['purge'])) {
    if ($template->delete(['id' => $id], true)) {
        Html::redirect(Template::getSearchURL());
    }
    Html::back();
}

if ($id > 0 && (isset($_GET['preview']) || isset($_GET['calibration']))) {
    $frame = isset($_GET['calibration']);
    return new Response(LabelPrinter::renderPdf($template, LabelPrinter::sampleLabels($template), $frame), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . ($frame ? 'labels-calibration.pdf' : 'labels-preview.pdf') . '"',
        'Cache-Control'       => 'no-store',
    ]);
}

if ($id <= 0) {
    $template->getEmpty();
}

Html::header(Template::getTypeName(Session::getPluralNumber()), '', 'config', Template::class);
echo '<div class="container-fluid" style="max-width: 1100px">';
echo '<div class="d-flex align-items-center my-3">';
echo '<h2 class="mb-0"><i class="ti ti-tag me-2"></i>'
    . htmlescape($id > 0 ? sprintf(__('Template: %s', 'etiquetas'), $template->fields['name']) : __('New label template', 'etiquetas'))
    . '</h2>';
echo "<a class='ms-auto' href='" . htmlescape(Template::getSearchURL()) . "'><i class='ti ti-list me-1'></i>"
    . htmlescape(__('All templates', 'etiquetas')) . '</a>';
echo '</div>';
$template->showEditForm();
echo '</div>';
Html::footer();
