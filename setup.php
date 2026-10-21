<?php

/**
 * Etiquetas - asset label printing for GLPI.
 *
 * Adds the "Print labels" massive action to asset lists and builds a PDF
 * from configurable label templates (label size, columns on the roll,
 * QR code or barcode, logo and text lines).
 *
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Etiquetas\Template;

define('PLUGIN_ETIQUETAS_VERSION', '1.2.0');
// Minimal GLPI version, inclusive
define('PLUGIN_ETIQUETAS_MIN_GLPI', '11.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_ETIQUETAS_MAX_GLPI', '11.0.99');

function plugin_init_etiquetas()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['etiquetas'] = true;

    if (Plugin::isPluginActive('etiquetas')) {
        $PLUGIN_HOOKS['use_massive_action']['etiquetas'] = 1;
        $PLUGIN_HOOKS['config_page']['etiquetas'] = 'front/template.php';
        $PLUGIN_HOOKS['menu_toadd']['etiquetas'] = ['config' => Template::class];
    }
}

function plugin_version_etiquetas()
{
    return [
        'name'         => __('Asset labels', 'etiquetas'),
        'version'      => PLUGIN_ETIQUETAS_VERSION,
        'author'       => 'cgklein',
        'license'      => 'GPL v3+',
        'homepage'     => 'https://github.com/carlosguilhermeklein/glpi-etiquetas',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ETIQUETAS_MIN_GLPI,
                'max' => PLUGIN_ETIQUETAS_MAX_GLPI,
            ],
            'php'  => [
                'exts' => [
                    'gd' => [
                        'required' => true,
                        'function' => 'imagecreatefromstring',
                    ],
                ],
            ],
        ],
    ];
}

function plugin_etiquetas_check_prerequisites()
{
    return true;
}

function plugin_etiquetas_check_config($verbose = false)
{
    return true;
}
