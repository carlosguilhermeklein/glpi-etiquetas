<?php

/**
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Etiquetas;

use CommonDBTM;
use Html;
use Session;

/**
 * Label template: label size, layout on the roll, code (QR or barcode),
 * logo and text lines.
 */
class Template extends CommonDBTM
{
    public static $rightname = 'config';

    public $dohistory = false;

    /** Fields that can be printed on a text line */
    public const FIELDS = [
        'text', 'inventory', 'description', 'holder', 'name', 'type', 'manufacturer',
        'model', 'serial', 'location', 'group', 'entity', 'purchase_date', 'id',
    ];

    /** Fields that cannot be edited on the review page */
    private const FIXED_FIELDS = ['text', 'inventory', 'id'];

    private const ENUMS = [
        'code_type'     => ['qrcode', 'code128', 'none'],
        'code_content'  => ['url', 'inventory', 'serial', 'name'],
        'code_position' => ['left', 'right'],
        'logo_position' => ['none', 'left', 'right'],
        'text_valign'   => ['spread', 'top', 'center'],
        'text_align'    => ['L', 'C', 'R'],
        'dpi'           => [203, 300, 600],
    ];

    /** [min, max] of the numeric fields (mm, except columns and threshold) */
    public const RANGES = [
        'label_width'    => [10, 200],
        'label_height'   => [5, 200],
        'label_columns'  => [1, 6],
        'column_gap'     => [0, 50],
        'margin_left'    => [-10, 50],
        'page_width'     => [0, 300],
        'shift_y'        => [-10, 10],
        'inner_margin'   => [0, 10],
        'code_size'      => [3, 100],
        'logo_size'      => [3, 100],
        'logo_threshold' => [1, 254],
    ];

    public const DEFAULTS = [
        'label_width'    => 50,
        'label_height'   => 20,
        'label_columns'  => 2,
        'column_gap'     => 2,
        'margin_left'    => 1,
        'page_width'     => 0,
        'shift_y'        => 0,
        'inner_margin'   => 1.5,
        'dpi'            => 203,
        'code_type'      => 'qrcode',
        'code_content'   => 'url',
        'code_position'  => 'left',
        'code_size'      => 17,
        'logo_position'  => 'right',
        'logo_size'      => 11.5,
        'logo_threshold' => 160,
        'text_valign'    => 'spread',
        'text_align'     => 'L',
    ];

    public const DEFAULT_LINES = [
        ['field' => 'entity', 'text' => '', 'size' => 6.5, 'bold' => 0, 'uppercase' => 0, 'rows' => 1],
        ['field' => 'inventory', 'text' => '', 'size' => 11, 'bold' => 1, 'uppercase' => 0, 'rows' => 1],
        ['field' => 'description', 'text' => '', 'size' => 6, 'bold' => 0, 'uppercase' => 1, 'rows' => 2],
        ['field' => 'holder', 'text' => '', 'size' => 6, 'bold' => 0, 'uppercase' => 0, 'rows' => 1],
    ];

    private const LOGO_TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_BMP => 'bmp'];

    public static function getTable($classname = null)
    {
        if ($classname !== null && $classname !== static::class) {
            return parent::getTable($classname);
        }
        return 'glpi_plugin_etiquetas_templates';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Label template', 'Label templates', $nb, 'etiquetas');
    }

    public static function getIcon()
    {
        return 'ti ti-tag';
    }

    /** @return array<string, string> */
    public static function getFieldLabels(): array
    {
        return [
            'text'          => __('Fixed text', 'etiquetas'),
            'inventory'     => __('Inventory number', 'etiquetas'),
            'description'   => __('Description (type + manufacturer + model)', 'etiquetas'),
            'holder'        => __('User (or location)', 'etiquetas'),
            'name'          => __('Name', 'etiquetas'),
            'type'          => __('Type', 'etiquetas'),
            'manufacturer'  => __('Manufacturer', 'etiquetas'),
            'model'         => __('Model', 'etiquetas'),
            'serial'        => __('Serial number', 'etiquetas'),
            'location'      => __('Location', 'etiquetas'),
            'group'         => __('Group', 'etiquetas'),
            'entity'        => __('Entity', 'etiquetas'),
            'purchase_date' => __('Purchase date', 'etiquetas'),
            'id'            => __('GLPI ID', 'etiquetas'),
        ];
    }

    /** @return array<string, string> */
    public static function getCodeTypes(): array
    {
        return [
            'qrcode'  => __('QR code', 'etiquetas'),
            'code128' => __('Barcode (Code 128, at the bottom)', 'etiquetas'),
            'none'    => __('None', 'etiquetas'),
        ];
    }

    /** @return array<string, string> */
    public static function getCodeContents(): array
    {
        return [
            'url'       => __('Link to the asset in GLPI', 'etiquetas'),
            'inventory' => __('Inventory number', 'etiquetas'),
            'serial'    => __('Serial number', 'etiquetas'),
            'name'      => __('Name', 'etiquetas'),
        ];
    }

    /** @return array<string, string> */
    public static function getLogoPositions(): array
    {
        return ['none' => __('No logo', 'etiquetas')] + self::getSides();
    }

    /** @return array<string, string> */
    private static function getSides(): array
    {
        return ['left' => __('Left', 'etiquetas'), 'right' => __('Right', 'etiquetas')];
    }

    /** @return array<string, string> */
    private static function getVerticalAlignments(): array
    {
        return [
            'spread' => __('Spread over the height', 'etiquetas'),
            'top'    => __('Top', 'etiquetas'),
            'center' => __('Center', 'etiquetas'),
        ];
    }

    /** @return array<string, string> */
    private static function getHorizontalAlignments(): array
    {
        return ['L' => __('Left', 'etiquetas'), 'C' => __('Center', 'etiquetas'), 'R' => __('Right', 'etiquetas')];
    }

    /** @return array<int, string> */
    private static function getResolutions(): array
    {
        return [
            203 => __('203 dpi (8 dots/mm)', 'etiquetas'),
            300 => __('300 dpi (12 dots/mm)', 'etiquetas'),
            600 => __('600 dpi (24 dots/mm)', 'etiquetas'),
        ];
    }

    public static function getDocDir(): string
    {
        $base = defined('GLPI_PLUGIN_DOC_DIR') ? GLPI_PLUGIN_DOC_DIR : GLPI_VAR_DIR . '/_plugins';
        return $base . '/etiquetas';
    }

    /** Removes uploaded logos and the logo cache (used on uninstall) */
    public static function deleteDocDir(): void
    {
        $dir = self::getDocDir();
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }

    public function rawSearchOptions()
    {
        return [
            ['id' => 'common', 'name' => self::getTypeName(1)],
            [
                'id'            => 1,
                'table'         => self::getTable(),
                'field'         => 'name',
                'name'          => __('Name'),
                'datatype'      => 'itemlink',
                'massiveaction' => false,
            ],
            [
                'id'            => 2,
                'table'         => self::getTable(),
                'field'         => 'id',
                'name'          => __('ID'),
                'datatype'      => 'number',
                'massiveaction' => false,
            ],
        ];
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->normalizeInput($input + ['text_lines' => json_encode(self::DEFAULT_LINES, JSON_UNESCAPED_UNICODE)]);
        if (($input['name'] ?? '') === '') {
            Session::addMessageAfterRedirect(__('Give the template a name.', 'etiquetas'), false, ERROR);
            return false;
        }
        if (countElementsInTable(self::getTable()) === 0) {
            $input['is_default'] = 1;
        }
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->normalizeInput($input);
        if (array_key_exists('name', $input) && $input['name'] === '') {
            Session::addMessageAfterRedirect(__('Give the template a name.', 'etiquetas'), false, ERROR);
            return false;
        }
        // The default template only changes when another template is set as default
        if (isset($input['is_default']) && !$input['is_default'] && $this->fields['is_default']) {
            unset($input['is_default']);
        }
        return $input;
    }

    private function normalizeInput(array $input): array
    {
        if (isset($input['name'])) {
            $input['name'] = trim((string) $input['name']);
        }
        foreach (self::RANGES as $key => [$min, $max]) {
            if (array_key_exists($key, $input)) {
                $value = (float) str_replace(',', '.', (string) $input[$key]);
                $input[$key] = max($min, min($max, $value));
            }
        }
        foreach (['label_columns', 'logo_threshold'] as $key) {
            if (isset($input[$key])) {
                $input[$key] = (int) round($input[$key]);
            }
        }
        foreach (self::ENUMS as $key => $allowed) {
            if (isset($input[$key]) && !in_array((string) $input[$key], array_map('strval', $allowed), true)) {
                $input[$key] = self::DEFAULTS[$key];
            }
        }
        if (isset($input['dpi'])) {
            $input['dpi'] = (int) $input['dpi'];
        }
        if (isset($input['is_default'])) {
            $input['is_default'] = (int) (bool) $input['is_default'];
        }
        if (isset($input['lines']) && is_array($input['lines'])) {
            $input['text_lines'] = json_encode(self::parseLines($input['lines']), JSON_UNESCAPED_UNICODE);
        }
        unset($input['lines']);
        return $input;
    }

    /** Text lines posted by the form (parallel lists field[], text[], ...) */
    private static function parseLines(array $posted): array
    {
        $lines = [];
        foreach (array_values((array) ($posted['field'] ?? [])) as $i => $field) {
            if (!in_array($field, self::FIELDS, true)) {
                continue;
            }
            $text = LabelPrinter::cleanText($posted['text'][$i] ?? '');
            if ($field === 'text' && $text === '') {
                continue;
            }
            $size = (float) str_replace(',', '.', (string) ($posted['size'][$i] ?? 7));
            $lines[] = [
                'field'     => $field,
                'text'      => $field === 'text' ? $text : '',
                'size'      => max(3, min(48, $size)),
                'bold'      => !empty($posted['bold'][$i]) ? 1 : 0,
                'uppercase' => !empty($posted['uppercase'][$i]) ? 1 : 0,
                'rows'      => max(1, min(3, (int) ($posted['rows'][$i] ?? 1))),
            ];
        }
        return $lines;
    }

    public function post_addItem()
    {
        if ((int) $this->fields['is_default'] === 1) {
            $this->clearOtherDefaults();
        }
    }

    public function post_updateItem($history = true)
    {
        if (in_array('is_default', $this->updates, true) && (int) $this->fields['is_default'] === 1) {
            $this->clearOtherDefaults();
        }
    }

    private function clearOtherDefaults(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->update(self::getTable(), ['is_default' => 0], ['NOT' => ['id' => $this->getID()]]);
    }

    public function pre_deleteItem()
    {
        if ((int) $this->fields['is_default'] === 1) {
            Session::addMessageAfterRedirect(
                __('This is the default template. Set another template as default before deleting this one.', 'etiquetas'),
                false,
                ERROR
            );
            return false;
        }
        return true;
    }

    public function cleanDBonPurge()
    {
        $logo = $this->getLogoPath();
        if ($logo !== null) {
            unlink($logo);
        }
    }

    /** Default template (or the first one) */
    public static function getDefault(): ?self
    {
        /** @var \DBmysql $DB */
        global $DB;

        $row = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'ORDER'  => ['is_default DESC', 'id ASC'],
            'LIMIT'  => 1,
        ])->current();
        $template = new self();
        return $row !== null && $template->getFromDB($row['id']) ? $template : null;
    }

    /** @return array<int, string> id => name */
    public static function getList(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $list = [];
        foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => self::getTable(), 'ORDER' => 'name']) as $row) {
            $list[(int) $row['id']] = $row['name'];
        }
        return $list;
    }

    public function num(string $key): float
    {
        // On a new template (getEmpty) the fields are empty: the plugin defaults apply
        $value = $this->fields[$key] ?? '';
        return (float) ($value === '' ? (self::DEFAULTS[$key] ?? 0) : $value);
    }

    public function getLines(): array
    {
        $lines = json_decode((string) ($this->fields['text_lines'] ?? ''), true);
        return is_array($lines) ? $lines : [];
    }

    /** Page width = one row of the roll; 0 in the template means automatic */
    public function getPageWidth(): float
    {
        return $this->num('page_width') > 0 ? $this->num('page_width') : $this->getAutoPageWidth();
    }

    public function getAutoPageWidth(): float
    {
        $columns = (int) $this->num('label_columns');
        return max(
            $this->num('label_width'),
            2 * $this->num('margin_left') + $columns * $this->num('label_width') + ($columns - 1) * $this->num('column_gap')
        );
    }

    public function getSummary(): string
    {
        return sprintf(
            __('%1$s x %2$s mm, %3$d per row, page of %4$s mm', 'etiquetas'),
            self::mm($this->num('label_width')),
            self::mm($this->num('label_height')),
            (int) $this->num('label_columns'),
            self::mm($this->getPageWidth())
        );
    }

    private static function mm(float $value): string
    {
        return preg_replace('/[.,]0$/', '', Html::formatNumber($value, false, 1));
    }

    /** @return array<string, string> field => label, in the order of the lines */
    public function getEditableFields(): array
    {
        $labels = self::getFieldLabels();
        $fields = [];
        foreach ($this->getLines() as $line) {
            if (!in_array($line['field'], self::FIXED_FIELDS, true)) {
                $fields[$line['field']] = $labels[$line['field']] ?? $line['field'];
            }
        }
        return $fields;
    }

    /** Does the template use the inventory number (on a line or in the code)? */
    public function requiresNumber(): bool
    {
        if ($this->fields['code_type'] !== 'none' && $this->fields['code_content'] === 'inventory') {
            return true;
        }
        foreach ($this->getLines() as $line) {
            if ($line['field'] === 'inventory') {
                return true;
            }
        }
        return false;
    }

    public function getLogoPath(): ?string
    {
        $file = basename((string) ($this->fields['logo_file'] ?? ''));
        $path = self::getDocDir() . '/' . $file;
        return $file !== '' && is_file($path) ? $path : null;
    }

    /** Stores the logo uploaded through the form (PNG, JPG, GIF or BMP) */
    public function handleLogoUpload(?array $file): void
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        $info = $file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])
            ? getimagesize($file['tmp_name'])
            : false;
        $extension = $info !== false ? (self::LOGO_TYPES[$info[2]] ?? null) : null;
        if ($extension === null) {
            Session::addMessageAfterRedirect(__('The logo must be a PNG, JPG, GIF or BMP image.', 'etiquetas'), false, ERROR);
            return;
        }

        $name = sprintf('logo-%d-%d.%s', $this->getID(), time(), $extension);
        if (!is_dir(self::getDocDir())) {
            mkdir(self::getDocDir(), 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], self::getDocDir() . '/' . $name)) {
            Session::addMessageAfterRedirect(__('Could not save the logo.', 'etiquetas'), false, ERROR);
            return;
        }
        $this->replaceLogo($name);
    }

    public function removeLogo(): void
    {
        $this->replaceLogo('');
    }

    /** Copies the logo of this template to another one (used by duplicate) */
    public function copyLogoTo(Template $target): void
    {
        $source = $this->getLogoPath();
        if ($source === null) {
            return;
        }
        $name = sprintf('logo-%d-%d.%s', $target->getID(), time(), pathinfo($source, PATHINFO_EXTENSION));
        if (copy($source, self::getDocDir() . '/' . $name)) {
            $target->replaceLogo($name);
        }
    }

    private function replaceLogo(string $name): void
    {
        $old = $this->getLogoPath();
        $this->update(['id' => $this->getID(), 'logo_file' => $name]);
        if ($old !== null && basename($old) !== $name) {
            unlink($old);
        }
    }

    /**
     * Pure black and white logo, already sized in printer dots (thermal printers
     * have no grey). Cached per file, size and threshold.
     *
     * @return array{0: string, 1: int, 2: int}|null [PNG file, width px, height px]
     */
    public function getMonoLogo(): ?array
    {
        $source = $this->getLogoPath();
        if ($source === null) {
            return null;
        }
        $box       = max(8, (int) round($this->num('logo_size') * (int) $this->num('dpi') / 25.4));
        $threshold = (int) $this->num('logo_threshold');
        $cache_dir = self::getDocDir() . '/cache';
        $cache     = sprintf('%s/%s.png', $cache_dir, md5(implode('|', [$source, filemtime($source), $box, $threshold])));

        if (!is_file($cache)) {
            $image = imagecreatefromstring((string) file_get_contents($source));
            if ($image === false) {
                return null;
            }
            $width  = imagesx($image);
            $height = imagesy($image);

            // Transparency becomes white
            $flat = imagecreatetruecolor($width, $height);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagealphablending($flat, true);
            imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);

            // Crop the white border around the drawing
            [$min_x, $min_y, $max_x, $max_y] = [$width, $height, -1, -1];
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    if (self::luminance(imagecolorat($flat, $x, $y)) < 235) {
                        $min_x = min($min_x, $x);
                        $max_x = max($max_x, $x);
                        $min_y = min($min_y, $y);
                        $max_y = max($max_y, $y);
                    }
                }
            }
            if ($max_x < 0) {
                return null;
            }
            $crop_w = $max_x - $min_x + 1;
            $crop_h = $max_y - $min_y + 1;
            $scale  = $box / max($crop_w, $crop_h);
            $out_w  = max(1, (int) round($crop_w * $scale));
            $out_h  = max(1, (int) round($crop_h * $scale));

            $mono = imagecreatetruecolor($out_w, $out_h);
            imagefill($mono, 0, 0, imagecolorallocate($mono, 255, 255, 255));
            imagecopyresampled($mono, $flat, 0, 0, $min_x, $min_y, $out_w, $out_h, $crop_w, $crop_h);
            $black = imagecolorallocate($mono, 0, 0, 0);
            $white = imagecolorallocate($mono, 255, 255, 255);
            for ($y = 0; $y < $out_h; $y++) {
                for ($x = 0; $x < $out_w; $x++) {
                    imagesetpixel($mono, $x, $y, self::luminance(imagecolorat($mono, $x, $y)) < $threshold ? $black : $white);
                }
            }

            if (!is_dir($cache_dir)) {
                mkdir($cache_dir, 0755, true);
            }
            imagepng($mono, $cache);
        }

        $size = getimagesize($cache);
        return $size === false ? null : [$cache, $size[0], $size[1]];
    }

    private static function luminance(int $rgb): float
    {
        return 0.2126 * (($rgb >> 16) & 0xFF) + 0.7152 * (($rgb >> 8) & 0xFF) + 0.0722 * ($rgb & 0xFF);
    }

    public function showEditForm(): void
    {
        $is_new = $this->isNewItem();
        // On a new template the getEmpty() fields are empty: the plugin defaults win
        $values = $is_new ? self::DEFAULTS + $this->fields : $this->fields + self::DEFAULTS;
        $lines  = $is_new ? self::DEFAULT_LINES : $this->getLines();
        $action = htmlescape(self::getFormURL());

        $number = static function (string $name, string $label, string $help = '') use ($values): string {
            [$min, $max] = self::RANGES[$name];
            $step = in_array($name, ['label_columns', 'logo_threshold'], true) ? '1' : '0.1';
            return "<div class='col-sm-6 col-lg-3 mb-3'><label class='form-label'>" . htmlescape($label) . '</label>'
                . "<input type='number' class='form-control' name='$name' min='$min' max='$max' step='$step' value='"
                . htmlescape((string) (float) $values[$name]) . "'>"
                . ($help !== '' ? "<div class='form-text'>" . htmlescape($help) . '</div>' : '')
                . '</div>';
        };
        $select = static function (string $name, string $label, array $options) use ($values): string {
            return "<div class='col-sm-6 col-lg-3 mb-3'><label class='form-label'>" . htmlescape($label) . '</label>'
                . self::selectHtml($name, $options, $values[$name], 'form-select') . '</div>';
        };
        $card = static fn(string $title): string => "<div class='card mb-3'><div class='card-header'><h3 class='card-title'>"
            . htmlescape($title) . "</h3></div><div class='card-body row'>";

        echo "<form method='post' action='$action' enctype='multipart/form-data'>";
        if (!$is_new) {
            echo "<input type='hidden' name='id' value='" . (int) $this->getID() . "'>";
        }

        echo $card(__('Identification', 'etiquetas'));
        echo "<div class='col-lg-6 mb-3'><label class='form-label'>" . htmlescape(__('Template name', 'etiquetas')) . '</label>'
            . "<input type='text' class='form-control' name='name' required maxlength='255' value='"
            . htmlescape((string) ($values['name'] ?? '')) . "'></div>";
        echo "<div class='col-lg-6 mb-3 d-flex align-items-end'>";
        if (!$is_new && (int) $values['is_default'] === 1) {
            echo "<span class='badge bg-green-lt'><i class='ti ti-star me-1'></i>" . htmlescape(__('Default template', 'etiquetas')) . '</span>';
        } else {
            echo "<label class='form-check'><input type='checkbox' class='form-check-input' name='is_default' value='1'>"
                . "<span class='form-check-label'>" . htmlescape(__('Use as default template', 'etiquetas')) . '</span></label>';
        }
        echo '</div></div></div>';

        echo $card(__('Size and position on the roll (mm)', 'etiquetas'));
        echo $number('label_width', __('Label width', 'etiquetas'));
        echo $number('label_height', __('Label height', 'etiquetas'));
        echo $number('label_columns', __('Labels per row (columns)', 'etiquetas'));
        echo $number('column_gap', __('Gap between columns', 'etiquetas'));
        echo $number('margin_left', __('Left margin to the first label', 'etiquetas'));
        echo $number('page_width', __('Page width', 'etiquetas'), sprintf(__('0 = automatic (%s mm)', 'etiquetas'), self::mm($this->getAutoPageWidth())));
        echo $number('shift_y', __('Vertical offset', 'etiquetas'), __('Positive moves the content down', 'etiquetas'));
        echo $number('inner_margin', __('Inner margin of the label', 'etiquetas'));
        echo $select('dpi', __('Printer resolution', 'etiquetas'), self::getResolutions());
        echo '</div></div>';

        echo $card(__('Code', 'etiquetas'));
        echo $select('code_type', __('Type', 'etiquetas'), self::getCodeTypes());
        echo $select('code_content', __('Content', 'etiquetas'), self::getCodeContents());
        echo $select('code_position', __('QR code side', 'etiquetas'), self::getSides());
        echo $number('code_size', __('Size', 'etiquetas'), __('QR code side length or barcode height', 'etiquetas'));
        echo '</div></div>';

        echo $card(__('Logo', 'etiquetas'));
        echo $select('logo_position', __('Position', 'etiquetas'), self::getLogoPositions());
        echo $number('logo_size', __('Maximum size', 'etiquetas'));
        echo $number('logo_threshold', __('Black threshold (1-254)', 'etiquetas'), __('Higher = more areas become black', 'etiquetas'));
        echo "<div class='col-sm-6 col-lg-3 mb-3'><label class='form-label'>" . htmlescape(__('Upload image (PNG, JPG, GIF, BMP)', 'etiquetas')) . '</label>'
            . "<input type='file' class='form-control' name='logo' accept='image/png,image/jpeg,image/gif,image/bmp'></div>";
        $logo = $is_new ? null : $this->getMonoLogo();
        if ($logo !== null) {
            $data = base64_encode((string) file_get_contents($logo[0]));
            echo "<div class='col-12'><div class='form-text mb-1'>" . htmlescape(__('How the logo will be printed (zoomed 3x):', 'etiquetas')) . '</div>'
                . "<img alt='Logo' src='data:image/png;base64,$data' style='image-rendering: pixelated; width: "
                . ($logo[1] * 3) . "px; border: 1px solid #888; background: #fff'>"
                . " <button type='submit' name='remove_logo' value='1' class='btn btn-sm btn-outline-danger ms-2'>"
                . "<i class='ti ti-trash me-1'></i>" . htmlescape(__('Remove logo', 'etiquetas')) . '</button></div>';
        }
        echo '</div></div>';

        echo "<div class='card mb-3'><div class='card-header'><h3 class='card-title'>" . htmlescape(__('Text lines', 'etiquetas'))
            . "</h3></div><div class='card-body'>";
        echo "<div class='row'>";
        echo $select('text_valign', __('Vertical alignment', 'etiquetas'), self::getVerticalAlignments());
        echo $select('text_align', __('Horizontal alignment', 'etiquetas'), self::getHorizontalAlignments());
        echo '</div>';
        echo "<div class='table-responsive'><table class='table table-sm align-middle' id='etiquetas-lines'><thead><tr>";
        foreach ([__('Field', 'etiquetas'), __('Text (fixed text only)', 'etiquetas'), __('Font (pt)', 'etiquetas'), __('Bold', 'etiquetas'), __('Uppercase', 'etiquetas'), __('Lines', 'etiquetas')] as $title) {
            echo '<th>' . htmlescape($title) . '</th>';
        }
        echo '<th></th></tr></thead><tbody>';
        foreach ($lines as $line) {
            echo self::lineRow($line);
        }
        echo '</tbody></table></div>';
        echo "<template id='etiquetas-line-template'>"
            . self::lineRow(['field' => 'name', 'text' => '', 'size' => 6, 'bold' => 0, 'uppercase' => 0, 'rows' => 1])
            . '</template>';
        echo "<button type='button' class='btn btn-sm btn-outline-secondary' id='etiquetas-add-line'>"
            . "<i class='ti ti-plus me-1'></i>" . htmlescape(__('Add line', 'etiquetas')) . '</button>';
        echo "<div class='form-text'>"
            . htmlescape(__('Lines wider than the label are compressed; if the lines do not fit in the height, all fonts shrink in the same proportion.', 'etiquetas'))
            . '</div>';
        echo '</div></div>';

        echo "<div class='d-flex flex-wrap gap-2 mb-4'>";
        if ($is_new) {
            echo "<button type='submit' name='add' value='1' class='btn btn-primary'><i class='ti ti-plus me-1'></i>" . htmlescape(__('Add', 'etiquetas')) . '</button>';
        } else {
            $id      = (int) $this->getID();
            $confirm = htmlescape(json_encode(__('Delete this template?', 'etiquetas')));
            echo "<button type='submit' name='update' value='1' class='btn btn-primary'><i class='ti ti-device-floppy me-1'></i>" . htmlescape(__('Save', 'etiquetas')) . '</button>';
            echo "<a class='btn btn-outline-secondary' target='_blank' href='$action?id=$id&amp;preview=1'><i class='ti ti-file-type-pdf me-1'></i>" . htmlescape(__('Preview PDF', 'etiquetas')) . '</a>';
            echo "<a class='btn btn-outline-secondary' target='_blank' href='$action?id=$id&amp;calibration=1'><i class='ti ti-ruler-measure me-1'></i>" . htmlescape(__('Calibration PDF', 'etiquetas')) . '</a>';
            echo "<button type='submit' name='duplicate' value='1' class='btn btn-outline-secondary'><i class='ti ti-copy me-1'></i>" . htmlescape(__('Duplicate', 'etiquetas')) . '</button>';
            echo "<button type='submit' name='purge' value='1' class='btn btn-outline-danger' onclick='return confirm($confirm)'>"
                . "<i class='ti ti-trash me-1'></i>" . htmlescape(__('Delete', 'etiquetas')) . '</button>';
            echo "<span class='form-text align-self-center'>" . htmlescape(__('The PDFs use the saved values: save before previewing.', 'etiquetas')) . '</span>';
        }
        echo '</div>';
        Html::closeForm();

        echo Html::scriptBlock(<<<JS
            (() => {
                const tbody = document.querySelector('#etiquetas-lines tbody');
                document.getElementById('etiquetas-add-line').addEventListener('click', () => {
                    tbody.append(document.getElementById('etiquetas-line-template').content.cloneNode(true));
                });
                tbody.addEventListener('click', (event) => {
                    const button = event.target.closest('button[data-action]');
                    if (!button) {
                        return;
                    }
                    const row = button.closest('tr');
                    if (button.dataset.action === 'remove') {
                        row.remove();
                    } else if (button.dataset.action === 'up' && row.previousElementSibling) {
                        row.previousElementSibling.before(row);
                    } else if (button.dataset.action === 'down' && row.nextElementSibling) {
                        row.nextElementSibling.after(row);
                    }
                });
            })();
JS);
    }

    private static function lineRow(array $line): string
    {
        $yes_no = [0 => __('No', 'etiquetas'), 1 => __('Yes', 'etiquetas')];
        $button = static fn(string $action, string $icon, string $title, string $color): string =>
            "<button type='button' class='btn btn-sm btn-ghost-$color' data-action='$action' title='" . htmlescape($title) . "'><i class='ti ti-$icon'></i></button>";

        return '<tr>'
            . '<td>' . self::selectHtml('lines[field][]', self::getFieldLabels(), $line['field'], 'form-select form-select-sm') . '</td>'
            . "<td><input type='text' class='form-control form-control-sm' name='lines[text][]' maxlength='" . LabelPrinter::MAX_TEXT
            . "' value='" . htmlescape((string) $line['text']) . "'></td>"
            . "<td style='width: 7em'><input type='number' class='form-control form-control-sm' name='lines[size][]' min='3' max='48' step='0.5' value='"
            . htmlescape((string) (float) $line['size']) . "'></td>"
            . '<td>' . self::selectHtml('lines[bold][]', $yes_no, (int) $line['bold'], 'form-select form-select-sm') . '</td>'
            . '<td>' . self::selectHtml('lines[uppercase][]', $yes_no, (int) $line['uppercase'], 'form-select form-select-sm') . '</td>'
            . '<td>' . self::selectHtml('lines[rows][]', [1 => '1', 2 => '2', 3 => '3'], (int) $line['rows'], 'form-select form-select-sm') . '</td>'
            . "<td class='text-nowrap'>"
            . $button('up', 'arrow-up', __('Move up', 'etiquetas'), 'secondary')
            . $button('down', 'arrow-down', __('Move down', 'etiquetas'), 'secondary')
            . $button('remove', 'x', __('Remove', 'etiquetas'), 'danger')
            . '</td></tr>';
    }

    private static function selectHtml(string $name, array $options, mixed $current, string $class): string
    {
        $html = "<select class='$class' name='" . htmlescape($name) . "'>";
        foreach ($options as $value => $label) {
            $selected = (string) $value === (string) $current ? ' selected' : '';
            $html .= "<option value='" . htmlescape((string) $value) . "'$selected>" . htmlescape($label) . '</option>';
        }
        return $html . '</select>';
    }
}
