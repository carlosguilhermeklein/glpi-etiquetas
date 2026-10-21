# Etiquetas – asset labels for GLPI

Print asset labels (QR code or barcode, logo and text) for the assets you select in GLPI,
laid out for label printers that use rolls with one or more labels per row (Zebra and similar).

*Português: [veja abaixo](#português).*

## Features

- **Print labels** massive action on Computers, Monitors, Printers, Network devices,
  Peripherals and Phones – select only the assets you want, from the list or from the
  asset form (*Actions* menu).
- Review page before printing: choose the template, check and edit the texts of each label.
  Assets without inventory number are blocked when the template prints the number.
- **Label templates** (*Setup > Label templates*):
  - label width and height, labels per row (columns), gap, margins, printer resolution;
  - QR code (left or right) or Code 128 barcode (bottom), with the link to the asset in GLPI,
    the inventory number, the serial number or the name;
  - logo uploaded from the form, converted to pure black and white at the printer resolution;
  - text lines in any order: fixed text, inventory number, description
    (type + manufacturer + model), user (or location), name, type, manufacturer, model,
    serial number, location, group, entity, purchase date, GLPI ID – with font size, bold,
    uppercase and 1 to 3 rows each;
  - preview PDF and calibration PDF (draws the outline of each label).
- One PDF page per row of the roll, sized in millimetres, with QR and barcode modules
  aligned to the printer dots for sharp thermal printing.
- Command line: `php bin/console plugins:etiquetas:generate-pdf Computer:1 Monitor:3 --output=/tmp/labels.pdf`.

## Requirements

- GLPI 11.0.x
- PHP `gd` extension (included in the official GLPI Docker image)

## Installation

1. Download `glpi-etiquetas-<version>.tar.bz2` from the
   [releases](https://github.com/carlosguilhermeklein/glpi-etiquetas/releases).
2. Extract it into the GLPI `plugins` (or `marketplace`) directory; the folder must be named `etiquetas`.
3. In GLPI go to *Setup > Plugins*, install and enable **Asset labels**
   (or run `php bin/console plugin:install -u glpi etiquetas` and `php bin/console plugin:activate etiquetas`).

The installation creates a default template (50 x 20 mm, 2 per row). Log out and in again
to see *Label templates* in the *Setup* menu.

## Printing

1. Open an asset list, select the assets and choose *Actions > Print labels*.
2. Check the texts on the review page and click **Generate PDF**.
3. Print the PDF on the label printer with the scale set to **actual size (100%)** and margins set to **none**.

The printer driver page size must match one row of the roll: *page width* x *label height*
(the page width is shown in the template summary). Use the **Calibration PDF** of the template
and adjust the left margin, the gap between columns and the vertical offset until the outline
matches the die-cut of the labels.

## Translations

The source strings are in English. Translations live in `locales/`
(`etiquetas.pot` is the template). After editing a `.po` file, rebuild the `.mo` files with:

```bash
php tools/compile-mo.php
```

## Releases

Publishing a GitHub release whose tag equals the version in `setup.php` (for example `1.2.0`)
runs the workflow that builds `glpi-etiquetas-<version>.tar.bz2` and attaches it to the release.
`tools/release.sh` does the same locally.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).

## Português

Plugin para imprimir **etiquetas de patrimônio** no GLPI 11 só dos ativos escolhidos, em
impressoras de etiquetas com rolo de uma ou mais colunas (Zebra e similares). Marque os ativos
numa lista, use *Ações > Imprimir etiquetas*, confira os textos e gere o PDF. Os modelos
(*Configurar > Modelos de etiqueta*) definem tamanho, colunas, QR code ou código de barras,
logo e as linhas de texto. Interface traduzida para português do Brasil.
