<?php

namespace App\Console\Commands;

use App\Services\GmailImapService;
use Illuminate\Console\Command;
use Throwable;

class SyncGmailReplies extends Command
{
    protected $signature = 'gmail:sync';

    protected $description = 'Sync customer Gmail replies into support tickets';

    public function handle(GmailImapService $gmailService): int
    {
        $this->info('Checking Gmail inbox for customer replies...');

        try {
            $stats = $gmailService->syncCustomerReplies();

            $this->newLine();

            $this->table(
                ['Checked', 'Saved', 'Duplicates', 'Skipped', 'Errors'],
                [[
                    $stats['checked'],
                    $stats['saved'],
                    $stats['duplicates'],
                    $stats['skipped'],
                    $stats['errors'],
                ]]
            );

            $this->info('Gmail sync completed.');

            return $stats['errors'] > 0
                ? self::FAILURE
                : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Gmail sync failed.');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
