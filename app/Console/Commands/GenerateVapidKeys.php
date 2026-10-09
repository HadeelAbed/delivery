<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a VAPID keypair for Web Push (print only — never stored or committed)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('Add these to your .env (never commit real values):');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:you@example.com');

        return self::SUCCESS;
    }
}
