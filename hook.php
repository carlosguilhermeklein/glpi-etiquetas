<?php

/**
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Etiquetas\LabelPrinter;
use GlpiPlugin\Etiquetas\Template;

function plugin_etiquetas_install()
{
    /** @var \DBmysql $DB */
    global $DB;

    $table = Template::getTable();
    if (!$DB->tableExists($table)) {
        $DB->doQuery(
            "CREATE TABLE `$table` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT '',
                `is_default` tinyint NOT NULL DEFAULT '0',
                `label_width` decimal(6,2) NOT NULL DEFAULT '50.00',
                `label_height` decimal(6,2) NOT NULL DEFAULT '20.00',
                `label_columns` int NOT NULL DEFAULT '2',
                `column_gap` decimal(6,2) NOT NULL DEFAULT '2.00',
                `margin_left` decimal(6,2) NOT NULL DEFAULT '1.00',
                `page_width` decimal(6,2) NOT NULL DEFAULT '0.00',
                `shift_y` decimal(6,2) NOT NULL DEFAULT '0.00',
                `inner_margin` decimal(6,2) NOT NULL DEFAULT '1.50',
                `dpi` int NOT NULL DEFAULT '203',
                `code_type` varchar(20) NOT NULL DEFAULT 'qrcode',
                `code_content` varchar(20) NOT NULL DEFAULT 'url',
                `code_position` varchar(10) NOT NULL DEFAULT 'left',
                `code_size` decimal(6,2) NOT NULL DEFAULT '17.00',
                `logo_position` varchar(10) NOT NULL DEFAULT 'right',
                `logo_size` decimal(6,2) NOT NULL DEFAULT '11.50',
                `logo_threshold` int NOT NULL DEFAULT '160',
                `logo_file` varchar(255) NOT NULL DEFAULT '',
                `text_valign` varchar(10) NOT NULL DEFAULT 'spread',
                `text_align` varchar(1) NOT NULL DEFAULT 'L',
                `text_lines` text,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `is_default` (`is_default`),
                KEY `date_creation` (`date_creation`),
                KEY `date_mod` (`date_mod`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
        );
    }

    if (!is_dir(Template::getDocDir())) {
        mkdir(Template::getDocDir(), 0755, true);
    }

    plugin_etiquetas_migrate_from_1_1($table);

    if (countElementsInTable($table) === 0) {
        $now = date('Y-m-d H:i:s');
        $DB->insert($table, [
            'name'          => __('Asset label 50x20 - 2 columns', 'etiquetas'),
            'is_default'    => 1,
            'text_lines'    => json_encode(Template::DEFAULT_LINES, JSON_UNESCAPED_UNICODE),
            'date_creation' => $now,
            'date_mod'      => $now,
        ] + Template::DEFAULTS);
    }

    return true;
}

/**
 * Version 1.1 stored the text lines and the code content with Portuguese keys.
 */
function plugin_etiquetas_migrate_from_1_1(string $table): void
{
    /** @var \DBmysql $DB */
    global $DB;

    $fields = [
        'fixo'        => 'text',
        'numero'      => 'inventory',
        'descricao'   => 'description',
        'usuario'     => 'holder',
        'nome'        => 'name',
        'tipo'        => 'type',
        'fabricante'  => 'manufacturer',
        'modelo'      => 'model',
        'serie'       => 'serial',
        'localizacao' => 'location',
        'grupo'       => 'group',
        'entidade'    => 'entity',
        'data_compra' => 'purchase_date',
        'id'          => 'id',
    ];
    $contents = ['numero' => 'inventory', 'serie' => 'serial', 'nome' => 'name'];

    foreach ($DB->request(['FROM' => $table]) as $row) {
        $changes = [];
        $lines   = json_decode((string) $row['text_lines'], true);
        if (is_array($lines) && isset($lines[0]['campo'])) {
            $changes['text_lines'] = json_encode(array_map(static fn(array $line) => [
                'field'     => $fields[$line['campo']] ?? 'name',
                'text'      => $line['texto'] ?? '',
                'size'      => $line['tamanho'] ?? 7,
                'bold'      => $line['negrito'] ?? 0,
                'uppercase' => $line['maiusculas'] ?? 0,
                'rows'      => $line['linhas'] ?? 1,
            ], $lines), JSON_UNESCAPED_UNICODE);
        }
        if (isset($contents[$row['code_content']])) {
            $changes['code_content'] = $contents[$row['code_content']];
        }
        if ($changes !== []) {
            $DB->update($table, $changes, ['id' => $row['id']]);
        }
    }
}

function plugin_etiquetas_uninstall()
{
    /** @var \DBmysql $DB */
    global $DB;

    $DB->doQuery('DROP TABLE IF EXISTS `' . Template::getTable() . '`');
    Template::deleteDocDir();
    return true;
}

/**
 * @param class-string<CommonDBTM> $type
 *
 * @return array<string, string>
 */
function plugin_etiquetas_MassiveActions($type)
{
    if (!in_array($type, LabelPrinter::ITEMTYPES, true) || !$type::canView()) {
        return [];
    }

    return [
        LabelPrinter::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'print'
            => "<i class='ti ti-tag'></i>" . __s('Print labels', 'etiquetas'),
    ];
}
