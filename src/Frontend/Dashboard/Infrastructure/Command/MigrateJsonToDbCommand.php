<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Infrastructure\Command;

use Ksef\Frontend\Dashboard\Application\Contract\SubmittedInvoiceRepositoryInterface;
use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;
use Ksef\Frontend\Dashboard\Infrastructure\SubmittedInvoiceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-json-to-db',
    description: 'Migrates submitted_invoices from JSON file to PostgreSQL'
)]
final class MigrateJsonToDbCommand extends Command
{
    public function __construct(
        private readonly SubmittedInvoiceRepository $jsonRepository,
        private readonly SubmittedInvoiceRepositoryInterface $dbRepository,
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be migrated without writing to DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $entries = $this->jsonRepository->all();
        $count = count($entries);

        if ($count === 0) {
            $io->success('No entries found in JSON file. Nothing to migrate.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d entries in JSON file.', $count));

        if ($dryRun) {
            $io->warning('Dry-run mode — no changes will be made.');
            foreach ($entries as $entry) {
                $io->writeln(sprintf(
                    '  [DRY-RUN] Would migrate: sessionRef=%s invoiceRef=%s submittedAt=%s',
                    $entry->sessionReferenceNumber,
                    $entry->invoiceReferenceNumber,
                    $entry->submittedAt
                ));
            }

            return Command::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            try {
                $this->dbRepository->add($entry);
                $migrated++;
                $io->writeln(sprintf(
                    '  Migrated: sessionRef=%s invoiceRef=%s',
                    $entry->sessionReferenceNumber,
                    $entry->invoiceReferenceNumber
                ));
            } catch (\Throwable $e) {
                $skipped++;
                $io->writeln(sprintf(
                    '  Skipped (duplicate or error): sessionRef=%s — %s',
                    $entry->sessionReferenceNumber,
                    $e->getMessage()
                ));
            }
        }

        if ($migrated > 0) {
            $jsonPath = rtrim($this->projectDir, '/') . '/var/submitted_invoices.json';
            if (is_file($jsonPath)) {
                rename($jsonPath, $jsonPath . '.bak');
                $io->writeln(sprintf('  JSON file backed up to: %s.bak', $jsonPath));
            }
        }

        $io->success(sprintf('Migration complete. Migrated: %d, Skipped: %d', $migrated, $skipped));

        return Command::SUCCESS;
    }
}
