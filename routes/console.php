<?php

use App\Notifications\Jobs\DispatchSubscriptionLifecycleNotifications;
use App\Articles\Jobs\TransitionArticlePublicationStatus;
use App\Subscription\Jobs\SendExpiringSubscriptionSms;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DispatchSubscriptionLifecycleNotifications)
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SendExpiringSubscriptionSms)->daily();
Schedule::job(new TransitionArticlePublicationStatus)->everyMinute()->withoutOverlapping();
