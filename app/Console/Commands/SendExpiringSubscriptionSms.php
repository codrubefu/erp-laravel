<?php

namespace App\Console\Commands;

use App\Subscription\Jobs\SendExpiringSubscriptionSms as SendExpiringSubscriptionSmsJob;
use Illuminate\Console\Command;

class SendExpiringSubscriptionSms extends Command
{
    protected $signature = 'subscriptions:send-expiring-sms
        {--sync : Run the job immediately instead of queueing it}';

    protected $description = 'Send SMS notifications for subscriptions that are about to expire.';

    public function handle(): int
    {
        $job = new SendExpiringSubscriptionSmsJob;

        if ($this->option('sync')) {
            app()->call([$job, 'handle']);

            $this->info('Expiring subscription SMS job ran synchronously.');

            return self::SUCCESS;
        }

        dispatch($job);

        $this->info('Expiring subscription SMS job was queued.');
        $this->line('Run php artisan queue:work --once to process it now.');

        return self::SUCCESS;
    }
}