<?php
namespace FIREGENTO\Magento\Command\Eav;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use N98\Util\Console\Helper\Table\Renderer\RendererFactory;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanUpEntityTypeValuesCommand extends AbstractCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('eav:clean:entity-type-values')
            ->setDescription('Remove attribute values with wrong entity_type_id')
            ->addOption('dry-run')
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Output Format. One of [' . implode(',', RendererFactory::getFormats()) . ']'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_input = $input;
        $this->_output = $output;

        $isDryRun = $input->getOption('dry-run');

        if(!$isDryRun) {
            $output->writeln('WARNING: this is not a dry run. If you want to do a dry-run, add --dry-run.');
            $question = new ConfirmationQuestion('Are you sure you want to continue? [No] ', false);

            $this->questionHelper = $this->getHelper('question');
            if (!$this->questionHelper->ask($input, $output, $question)) {
                return;
            }
        }

        $this->detectMagento($output);

        if ($this->initMagento()) {
            $resource = \Mage::getModel('core/resource');
            $db = $resource->getConnection('core_write');
            $types = array('varchar', 'int', 'decimal', 'text', 'datetime');
            $entityTypeCodes = array('catalog_product', 'catalog_category', 'customer', 'customer_address');
            foreach($entityTypeCodes as $code) {
                $entityType = \Mage::getModel('eav/entity_type')->loadByCode($code);
                $output->writeln("<info>Cleaning values for $code where entity_type_id != " . $entityType->getEntityTypeId() . "</info>");
                foreach ($types as $type) {
                    $entityValueTable = $this->_prefixTable($code . '_entity_' . $type);
                    $query = "SELECT * FROM $entityValueTable WHERE `entity_type_id` <> " . $entityType->getEntityTypeId();
                    $results = $db->fetchAll($query);
                    $output->writeln("Clean up " . count($results) . " rows in $entityValueTable");
                    $this->verboseWriteLine($output, $query);
                    $this->printVerboseQueryResult($input, $output, $results);

                    if (!$isDryRun) {
                        $db->query("DELETE FROM $entityValueTable WHERE `entity_type_id` <> " . $entityType->getEntityTypeId());
                    }
                }
            }
        }
    }
}
