<?php

/**
 * List of label templates.
 *
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Etiquetas\Template;

/** @var \DBmysql $DB */
global $DB;

Session::checkRight('config', UPDATE);

Html::header(Template::getTypeName(Session::getPluralNumber()), '', 'config', Template::class);

echo '<div class="container-fluid" style="max-width: 1100px">';
echo '<div class="d-flex align-items-center my-3">';
echo '<h2 class="mb-0"><i class="ti ti-tag me-2"></i>' . htmlescape(Template::getTypeName(Session::getPluralNumber())) . '</h2>';
echo "<a class='btn btn-primary ms-auto' href='" . htmlescape(Template::getFormURL()) . "'><i class='ti ti-plus me-1'></i>"
    . htmlescape(__('New template', 'etiquetas')) . '</a>';
echo '</div>';
echo '<p class="text-muted">'
    . htmlescape(__('A template defines the label size, how many labels fit in a row of the roll, the code, the logo and the text lines. When printing, the review page lets you choose the template.', 'etiquetas'))
    . '</p>';

$field_labels   = Template::getFieldLabels();
$code_types     = Template::getCodeTypes();
$code_contents  = Template::getCodeContents();
$logo_positions = Template::getLogoPositions();

echo '<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr>';
foreach ([__('Name', 'etiquetas'), __('Label', 'etiquetas'), __('Code', 'etiquetas'), __('Logo', 'etiquetas'), __('Text lines', 'etiquetas'), ''] as $title) {
    echo '<th>' . htmlescape($title) . '</th>';
}
echo '</tr></thead><tbody>';

$template = new Template();
foreach ($DB->request(['SELECT' => ['id'], 'FROM' => Template::getTable(), 'ORDER' => 'name']) as $row) {
    $template->getFromDB($row['id']);
    $fields = array_map(
        fn($line) => $line['field'] === 'text' ? '"' . $line['text'] . '"' : ($field_labels[$line['field']] ?? $line['field']),
        $template->getLines()
    );
    $code = $template->fields['code_type'] === 'none'
        ? $code_types['none']
        : ($code_types[$template->fields['code_type']] ?? '') . ': ' . ($code_contents[$template->fields['code_content']] ?? '');

    echo '<tr>';
    echo "<td><a href='" . htmlescape(Template::getFormURLWithID($row['id'])) . "'>" . htmlescape($template->fields['name']) . '</a></td>';
    echo '<td>' . htmlescape($template->getSummary()) . '</td>';
    echo '<td>' . htmlescape($code) . '</td>';
    echo '<td>' . htmlescape($logo_positions[$template->fields['logo_position']] ?? '') . '</td>';
    echo '<td>' . htmlescape(implode(', ', $fields)) . '</td>';
    echo '<td>' . ((int) $template->fields['is_default'] === 1
        ? "<span class='badge bg-green-lt'>" . htmlescape(__('Default', 'etiquetas')) . '</span>'
        : '') . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div></div>';

Html::footer();
