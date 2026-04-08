<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Infrastructure\Command;

use DateTimeImmutable;
use Ksef\Frontend\Dashboard\Application\Contract\SubmittedInvoiceRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:invoices:mark-overdue',
    description: 'Mark unpaid invoices with past due date as overdue.'
)]
final class MarkOverdueInvoicesCommand extends Command
{
    public function __construct(
        private readonly SubmittedInvoiceRepositoryInterface $repository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new DateTimeImmutable('today');
        $count = $this->repository->markOverdueByDueDate($today);

        $output->writeln(sprintf('Marked %d invoice(s) as overdue.', $count));

        return Command::SUCCESS;
    }
}
