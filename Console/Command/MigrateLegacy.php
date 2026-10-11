<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Console\Command;

use Coresh\QuoteRequest\Model\Migration\LegacyDataMigrator;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Dry-run by default; store attribution is explicit because legacy rows had none. */
class MigrateLegacy extends Command
{
    public function __construct(private readonly LegacyDataMigrator $migrator, private readonly State $state)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('coresh:quote-request:migrate-legacy')
            ->setDescription('Preview or explicitly import verified legacy requests, flags and configuration without deletion.')
            ->addOption('store-id', null, InputOption::VALUE_REQUIRED, 'Original storefront store ID (required).')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Write changes; without this flag only inspect.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = (string) $input->getOption('store-id');
        if (!ctype_digit($storeId) || (int) $storeId < 1) {
            $output->writeln('<error>Provide a positive --store-id. No changes made.</error>');
            return Command::INVALID;
        }
        try {
            $report = $this->state->emulateAreaCode(Area::AREA_ADMINHTML,
                fn (): array => $this->migrator->migrate((int) $storeId, (bool) $input->getOption('execute')));
            foreach ($report as $key => $value) {
                $output->writeln($key . ': ' . $value);
            }
            if ($input->getOption('execute')) {
                $output->writeln('Import complete. Clean config/block_html/full_page caches and reindex catalog as appropriate.');
            }
            return Command::SUCCESS;
        } catch (\Throwable) {
            $output->writeln('<error>Migration failed. Original data was not deleted. Check prerequisites; partial imports can be rerun.</error>');
            return Command::FAILURE;
        }
    }
}
