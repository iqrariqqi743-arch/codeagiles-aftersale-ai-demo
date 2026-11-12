<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use Throwable;

class TestGmailImap extends Command
{
    protected $signature = 'gmail:test';

    protected $description = 'Test Gmail IMAP connection';

    public function handle(): int
    {
        $this->info('Connecting to Gmail IMAP...');

        $client = null;

        try {
            $manager = new ClientManager();

            $client = $manager->make([
                'host' => env('IMAP_HOST', 'imap.gmail.com'),
                'port' => (int) env('IMAP_PORT', 993),
                'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
                'validate_cert' => true,
                'username' => config('mail.mailers.smtp.username'),
                'password' => config('mail.mailers.smtp.password'),
                'protocol' => 'imap',
            ]);

            $client->connect();

            $this->info('Gmail IMAP connected successfully!');

            $folder = $client->getFolder('INBOX');

            if (!$folder) {
                $this->error('Gmail INBOX not found.');
                return self::FAILURE;
            }

            $this->info('Gmail INBOX found successfully!');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Gmail IMAP connection failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($client) {
                try {
                    $client->disconnect();
                } catch (Throwable $e) {
                    // Ignore disconnect errors.
                }
            }
        }
    }
}
