<?php

/**
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Etiquetas;

use CommonDBTM;
use CommonGLPI;
use Dropdown;
use Entity;
use Html;
use Infocom;
use MassiveAction;
use TCPDF;
use TCPDF2DBarcode;
use TCPDFBarcode;
use User;

/**
 * "Print labels" massive action, label data of each asset and PDF output
 * following a label template.
 *
 * Each PDF page is one row of the roll, holding as many labels as the
 * template has columns.
 */
class LabelPrinter extends CommonGLPI
{
    public const ITEMTYPES = ['Computer', 'Monitor', 'Printer', 'NetworkEquipment', 'Peripheral', 'Phone'];

    public const MAX_TEXT = 60;

    /** Height of a text line relative to the font size */
    private const LINE_RATIO = 1.15;
    /** Space between code, text and logo (mm) */
    private const GAP = 1.0;

    private const SESSION_ITEMS    = 'plugin_etiquetas_items';
    private const SESSION_TEXTS    = 'plugin_etiquetas_texts';
    private const SESSION_TEMPLATE = 'plugin_etiquetas_template';

    /** Manufacturers whose first word is not what goes on the label */
    private const MANUFACTURER_ALIAS = [
        'HEWLETT-PACKARD' => 'HP',
        'HEWLETT'         => 'HP',
    ];

    private const QR_STYLE = [
        'border'  => false,
        'padding' => 0,
        'fgcolor' => [0, 0, 0],
        'bgcolor' => false,
    ];

    public static function getTypeName($nb = 0)
    {
        return __('Asset labels', 'etiquetas');
    }

    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        if ($ma->getAction() !== 'print') {
            return false;
        }

        echo '<p>' . htmlescape(__('The selected assets will go to the label review page.', 'etiquetas')) . '</p>';
        echo Html::submit(__('Continue', 'etiquetas'), [
            'name'  => 'massiveaction',
            'icon'  => 'ti ti-tag',
            'class' => 'btn btn-sm btn-primary',
        ]);
        return true;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;
        // All itemtypes of one action are processed in the same request:
        // the previous selection is dropped on the first call only.
        static $new_selection = true;

        if ($ma->getAction() !== 'print') {
            return;
        }

        if ($new_selection) {
            $_SESSION[self::SESSION_ITEMS] = [];
            $new_selection = false;
        }

        $itemtype = $item::class;
        foreach ($ids as $id) {
            if (in_array($itemtype, self::ITEMTYPES, true) && $item->can($id, READ)) {
                $_SESSION[self::SESSION_ITEMS][] = ['itemtype' => $itemtype, 'id' => (int) $id];
                $ma->itemDone($itemtype, $id, MassiveAction::ACTION_OK);
            } else {
                $ma->itemDone($itemtype, $id, MassiveAction::ACTION_NORIGHT);
            }
        }

        $ma->setRedirect($CFG_GLPI['root_doc'] . '/plugins/etiquetas/front/print.php');
    }

    /** @return array<int, array{itemtype: string, id: int}> */
    public static function getSelection(): array
    {
        return $_SESSION[self::SESSION_ITEMS] ?? [];
    }

    /** Template chosen on the review page (remembered in the session) or the default one */
    public static function getTemplateForUse(int $id = 0): ?Template
    {
        $id = $id > 0 ? $id : (int) ($_SESSION[self::SESSION_TEMPLATE] ?? 0);
        $template = new Template();
        if ($id > 0 && $template->getFromDB($id)) {
            $_SESSION[self::SESSION_TEMPLATE] = $id;
            return $template;
        }
        return Template::getDefault();
    }

    public static function rememberText(string $itemtype, int $id, string $field, string $value): void
    {
        $_SESSION[self::SESSION_TEXTS]["$itemtype:$id"][$field] = $value;
    }

    public static function cleanText(mixed $value): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
        return mb_substr($text, 0, self::MAX_TEXT);
    }

    /**
     * Label data read from the asset, with the texts edited in this session (if any).
     * Returns null if the itemtype is not supported, the asset does not exist or
     * the user cannot see it.
     */
    public static function buildLabel(string $itemtype, int $id, bool $check_rights = true): ?array
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        if (!in_array($itemtype, self::ITEMTYPES, true)) {
            return null;
        }
        $item = getItemForItemtype($itemtype);
        if (!$item instanceof CommonDBTM || !$item->getFromDB($id) || $item->isDeleted()) {
            return null;
        }
        if ($check_rights && !$item->can($id, READ)) {
            return null;
        }

        $values = [
            'inventory'     => trim((string) ($item->fields['otherserial'] ?? '')),
            'name'          => (string) ($item->fields['name'] ?? ''),
            'description'   => self::defaultDescription($item),
            'holder'        => self::defaultHolder($item),
            'type'          => self::typeName($item),
            'manufacturer'  => self::shortManufacturer($item),
            'model'         => self::dropdownValue($item, $itemtype . 'Model'),
            'serial'        => trim((string) ($item->fields['serial'] ?? '')),
            'location'      => self::lastPart(self::dropdownValue($item, 'Location')),
            'group'         => self::groupNames($item),
            'entity'        => self::entityName((int) ($item->fields['entities_id'] ?? 0)),
            'purchase_date' => self::purchaseDate($item),
            'id'            => (string) $id,
        ];
        foreach ($_SESSION[self::SESSION_TEXTS]["$itemtype:$id"] ?? [] as $field => $value) {
            if (array_key_exists($field, $values) && !in_array($field, ['inventory', 'id'], true)) {
                $values[$field] = $value;
            }
        }

        return [
            'itemtype' => $itemtype,
            'id'       => $id,
            'asset'    => $item::getTypeName(1) . ' - ' . ($item->fields['name'] ?? ''),
            'link'     => $item::getFormURLWithID($id),
            // Same link as the QR code GLPI shows on the asset form (BarcodeManager)
            'url'      => $CFG_GLPI['url_base'] . $item::getFormURLWithID($id, false),
            'values'   => $values,
        ];
    }

    /** An asset without inventory number is skipped when the template uses the number */
    public static function isPrintable(Template $template, array $label): bool
    {
        return !$template->requiresNumber() || $label['values']['inventory'] !== '';
    }

    /** Sample labels (one row of the roll) for preview and calibration */
    public static function sampleLabels(Template $template): array
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $entity = self::entityName((int) ($_SESSION['glpiactive_entity'] ?? 0));
        $sample = [
            'url'    => $CFG_GLPI['url_base'] . '/front/computer.form.php?id=1',
            'values' => [
                'inventory'     => '000123',
                'name'          => 'PC-0001',
                'description'   => 'LAPTOP DELL LATITUDE 5440',
                'holder'        => 'Jane Doe',
                'type'          => 'Laptop',
                'manufacturer'  => 'Dell',
                'model'         => 'Latitude 5440',
                'serial'        => 'ABC1234',
                'location'      => 'IT',
                'group'         => 'IT',
                'entity'        => $entity !== '' ? $entity : 'ACME',
                'purchase_date' => Html::convDate(date('Y-m-d')),
                'id'            => '1',
            ],
        ];
        return array_fill(0, max(1, (int) $template->num('label_columns')), $sample);
    }

    /** TYPE + MANUFACTURER + MODEL, e.g. "MONITOR DELL S2421HN" */
    private static function defaultDescription(CommonDBTM $item): string
    {
        $manufacturer = mb_strtoupper(self::shortManufacturer($item));
        $model = self::dropdownValue($item, $item::class . 'Model');
        if ($model === '') {
            $model = (string) ($item->fields['name'] ?? '');
        }
        $model = mb_strtoupper($model);
        // Monitor models often already start with the manufacturer ("DELL S2421HN")
        if ($manufacturer !== '' && str_starts_with($model, $manufacturer)) {
            $manufacturer = '';
        }

        return self::cleanText(mb_strtoupper(implode(' ', array_filter([self::typeName($item), $manufacturer, $model]))));
    }

    /** Asset type set in GLPI (Laptop, Desktop...) or the name of the itemtype */
    private static function typeName(CommonDBTM $item): string
    {
        $type = self::dropdownValue($item, $item::class . 'Type');
        return $type !== '' ? $type : $item::getTypeName(1);
    }

    /** First word of the manufacturer ("Dell Inc." -> "Dell") */
    private static function shortManufacturer(CommonDBTM $item): string
    {
        $words = preg_split('/[\s,]+/u', self::dropdownValue($item, 'Manufacturer'), -1, PREG_SPLIT_NO_EMPTY);
        $first = $words[0] ?? '';
        return self::MANUFACTURER_ALIAS[mb_strtoupper($first)] ?? $first;
    }

    /** User of the asset ("John Smith"); without user, the location */
    private static function defaultHolder(CommonDBTM $item): string
    {
        $users_id = (int) ($item->fields['users_id'] ?? 0);
        $user = new User();
        if ($users_id > 0 && $user->getFromDB($users_id)) {
            $full = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
            if ($full === '') {
                $full = (string) $user->fields['name'];
            }
            $parts = preg_split('/\s+/u', $full, -1, PREG_SPLIT_NO_EMPTY);
            return self::cleanText(count($parts) <= 2 ? $full : $parts[0] . ' ' . end($parts));
        }

        return self::cleanText(self::lastPart(self::dropdownValue($item, 'Location')));
    }

    /** In GLPI 11 the groups of an asset are a list of ids */
    private static function groupNames(CommonDBTM $item): string
    {
        $names = [];
        foreach ((array) ($item->fields['groups_id'] ?? []) as $groups_id) {
            if ((int) $groups_id > 0) {
                $names[] = self::lastPart(Dropdown::getDropdownName('glpi_groups', (int) $groups_id));
            }
        }
        return self::cleanText(implode(', ', $names));
    }

    private static function entityName(int $entities_id): string
    {
        $entity = new Entity();
        return $entity->getFromDB($entities_id) ? (string) $entity->fields['name'] : '';
    }

    private static function purchaseDate(CommonDBTM $item): string
    {
        $infocom = new Infocom();
        if ($infocom->getFromDBforDevice($item::class, $item->getID()) && !empty($infocom->fields['buy_date'])) {
            return (string) Html::convDate($infocom->fields['buy_date']);
        }
        return '';
    }

    private static function dropdownValue(CommonDBTM $item, string $dropdown_itemtype): string
    {
        $field = getForeignKeyFieldForItemType($dropdown_itemtype);
        $value = (int) ($item->fields[$field] ?? 0);
        if ($value <= 0) {
            return '';
        }
        return trim(Dropdown::getDropdownName(getTableForItemType($dropdown_itemtype), $value));
    }

    /** "Headquarters > IT" -> "IT" */
    private static function lastPart(string $name): string
    {
        $parts = explode('>', $name);
        return trim((string) end($parts));
    }

    /**
     * Builds the PDF (binary content). With $frame = true the outline of each
     * label is drawn, to check the alignment against the die-cut of the roll.
     */
    public static function renderPdf(Template $template, array $labels, bool $frame = false): string
    {
        $width   = $template->num('label_width');
        $height  = $template->num('label_height');
        $columns = max(1, (int) $template->num('label_columns'));
        $page_w  = max($width, $template->getPageWidth());

        $pdf = new TCPDF($page_w >= $height ? 'L' : 'P', 'mm', [$page_w, $height], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);
        $pdf->setCellHeightRatio(self::LINE_RATIO);
        $pdf->SetCreator('GLPI - ' . __('Asset labels', 'etiquetas'));
        $pdf->SetTitle(__('Asset labels', 'etiquetas'));

        $logo = $template->fields['logo_position'] !== 'none' ? $template->getMonoLogo() : null;

        foreach (array_chunk($labels, $columns) as $row) {
            $pdf->AddPage();
            foreach ($row as $column => $label) {
                $x = $template->num('margin_left') + $column * ($width + $template->num('column_gap'));
                self::drawLabel($pdf, $template, $label, $x, $template->num('shift_y'), $logo, $frame);
            }
        }

        return $pdf->Output('labels.pdf', 'S');
    }

    private static function drawLabel(
        TCPDF $pdf,
        Template $template,
        array $label,
        float $x,
        float $y,
        ?array $logo,
        bool $frame
    ): void {
        $width  = $template->num('label_width');
        $height = $template->num('label_height');
        $margin = $template->num('inner_margin');
        // Size of one printer dot: multiples of it print sharp
        $dot = 25.4 / max(1, (int) $template->num('dpi'));

        if ($frame) {
            $pdf->SetLineWidth(0.2);
            $pdf->RoundedRect($x, $y, $width, $height, min(1.5, $height / 4));
        }

        $left    = $x + $margin;
        $right   = $x + $width - $margin;
        $top     = $y + $margin;
        $bottom  = $y + $height - $margin;
        $middle  = ($top + $bottom) / 2;
        $inner_h = $bottom - $top;

        // Reserves a strip of width $box on one side and returns its x
        $place = static function (float $box, string $side) use (&$left, &$right): float {
            if ($side === 'right') {
                $right -= $box;
                $position = $right;
                $right -= self::GAP;
                return $position;
            }
            $position = $left;
            $left += $box + self::GAP;
            return $position;
        };

        $code_type    = $template->fields['code_type'];
        $code_content = $template->fields['code_content'];
        $code_value   = $code_content === 'url' ? $label['url'] : (string) ($label['values'][$code_content] ?? '');

        if ($code_type === 'qrcode' && $code_value !== '') {
            $modules = (int) ((new TCPDF2DBarcode($code_value, 'QRCODE,M'))->getBarcodeArray()['num_cols'] ?? 0);
            if ($modules > 0) {
                $max_size = min($template->num('code_size'), $inner_h);
                $size     = max(1, floor($max_size / $modules / $dot)) * $dot * $modules;
                $qr_x     = $place($size, $template->fields['code_position']);
                $pdf->write2DBarcode($code_value, 'QRCODE,M', $qr_x, $middle - $size / 2, $size, $size, self::QR_STYLE, 'N');
            }
        }

        if ($logo !== null) {
            [$file, $px_w, $px_h] = $logo;
            $logo_w = $px_w * $dot;
            $logo_h = $px_h * $dot;
            if ($logo_h > $inner_h) {
                $logo_w *= $inner_h / $logo_h;
                $logo_h  = $inner_h;
            }
            $logo_x = $place($logo_w, $template->fields['logo_position']);
            $pdf->Image($file, $logo_x, $middle - $logo_h / 2, $logo_w, $logo_h, 'PNG');
        }

        $text_bottom = $bottom;
        if ($code_type === 'code128' && $code_value !== '' && $right > $left) {
            $modules = (int) ((new TCPDFBarcode($code_value, 'C128'))->getBarcodeArray()['maxw'] ?? 0);
            if ($modules > 0) {
                $band  = min($template->num('code_size'), $inner_h);
                $avail = $right - $left;
                $xres  = floor($avail / $modules / $dot) * $dot;
                if ($xres <= 0) {
                    // Does not fit with 1-dot bars: squeeze it
                    $xres = $avail / $modules;
                }
                $bar_w = $xres * $modules;
                $pdf->write1DBarcode($code_value, 'C128', $left + ($avail - $bar_w) / 2, $bottom - $band, $bar_w, $band, $xres, [
                    'stretch'  => false,
                    'fitwidth' => false,
                    'text'     => false,
                    'border'   => false,
                    'padding'  => 0,
                    'fgcolor'  => [0, 0, 0],
                    'bgcolor'  => false,
                ], 'N');
                $text_bottom = $bottom - $band - self::GAP;
            }
        }

        self::drawLines($pdf, $template, $label, $left, $right, $top, $text_bottom);
    }

    private static function drawLines(
        TCPDF $pdf,
        Template $template,
        array $label,
        float $left,
        float $right,
        float $top,
        float $bottom
    ): void {
        $lines = [];
        foreach ($template->getLines() as $line) {
            $text = $line['field'] === 'text' ? $line['text'] : (string) ($label['values'][$line['field']] ?? '');
            $line['value'] = !empty($line['uppercase']) ? mb_strtoupper($text) : $text;
            $lines[] = $line;
        }
        $width = $right - $left;
        $avail = $bottom - $top;
        if ($lines === [] || $width < 2 || $avail < 1) {
            return;
        }

        $point   = 25.4 / 72;
        $heights = array_map(
            fn($line) => (float) $line['size'] * $point * self::LINE_RATIO * max(1, (int) $line['rows']),
            $lines
        );
        // If the lines do not fit in the height, all fonts shrink in the same proportion
        $scale = min(1.0, $avail / array_sum($heights));
        $total = array_sum($heights) * $scale;

        $y   = $top;
        $gap = 0.0;
        if ($template->fields['text_valign'] === 'center' || count($lines) === 1) {
            $y = $top + ($avail - $total) / 2;
        } elseif ($template->fields['text_valign'] === 'spread') {
            $gap = ($avail - $total) / (count($lines) - 1);
        }

        $align = $template->fields['text_align'];
        foreach ($lines as $i => $line) {
            $line_h = $heights[$i] * $scale;
            $pdf->SetFont('helvetica', !empty($line['bold']) ? 'B' : '', (float) $line['size'] * $scale);
            if ((int) $line['rows'] > 1) {
                // fitcell shrinks the font if the text does not fit in the rows; the
                // margin keeps TCPDF rounding from cutting the last row
                $max_h = $line_h + 0.3;
                $pdf->MultiCell($width, $max_h, $line['value'], 0, $align, false, 0, $left, $y, true, 0, false, false, $max_h, 'T', true);
            } else {
                // stretch 1 compresses horizontally the text wider than the cell
                $pdf->SetXY($left, $y);
                $pdf->Cell($width, $line_h, $line['value'], 0, 0, $align, false, '', 1, false, 'T', 'M');
            }
            $y += $line_h + $gap;
        }
    }
}
