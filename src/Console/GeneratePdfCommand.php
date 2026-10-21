<?php

/**
 * @copyright 2026 cgklein
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Etiquetas\Console;

use Glpi\Console\AbstractCommand;
use GlpiPlugin\Etiquetas\LabelPrinter;
use GlpiPlugin\Etiquetas\Template;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Builds the label PDF from the command line, useful to test layouts.
 * E.g.: plugins:etiquetas:generate-pdf Computer:1 Monitor:3 --template=1 --output=/tmp/labels.pdf
 */
class GeneratePdfCommand extends AbstractCommand
{
    protected function configure()
    {
        parent::configure();

        $this->setName('plugins:etiquetas:generate-pdf');
        $this->setDescription('Build the asset labels PDF (layout test)');
        $this->addArgument('items', InputArgument::IS_ARRAY, 'Assets as Itemtype:id (e.g. Monitor:3)');
        $this->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Label template ID (default: the default template)', 0);
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output PDF file', '/tmp/labels.pdf');
        $this->addOption('calibration', 'c', InputOption::VALUE_NONE, 'Build the calibration PDF (sample labels with outline)');
        $this->addOption('include-without-number', null, InputOption::VALUE_NONE, 'Also print assets without inventory number');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $template = new Template();
        $id = (int) $input->getOption('template');
        if ($id > 0 ? !$template->getFromDB($id) : ($template = Template::getDefault()) === null) {
            $output->writeln('<error>Label template not found.</error>');
            return 1;
        }
        $output->writeln(sprintf('Template: %s (%s)', $template->fields['name'], $template->getSummary()));

        if ($input->getOption('calibration')) {
            $labels = LabelPrinter::sampleLabels($template);
        } else {
            $labels = [];
            foreach ($input->getArgument('items') as $spec) {
                [$itemtype, $item_id] = array_pad(explode(':', $spec, 2), 2, 0);
                $label = LabelPrinter::buildLabel($itemtype, (int) $item_id, false);
                if ($label === null) {
                    $output->writeln("<comment>Skipped: $spec (unsupported itemtype or missing asset)</comment>");
                    continue;
                }
                if (!LabelPrinter::isPrintable($template, $label) && !$input->getOption('include-without-number')) {
                    $output->writeln("<comment>Skipped: $spec (no inventory number)</comment>");
                    continue;
                }
                $output->writeln($spec . ' | ' . implode(' | ', array_filter($label['values'], 'strlen')));
                $labels[] = $label;
            }
        }

        if ($labels === []) {
            $output->writeln('<error>No label to build.</error>');
            return 1;
        }

        $file = $input->getOption('output');
        file_put_contents($file, LabelPrinter::renderPdf($template, $labels, (bool) $input->getOption('calibration')));
        $output->writeln(sprintf('<info>%d label(s) written to %s</info>', count($labels), $file));
        return 0;
    }
}
