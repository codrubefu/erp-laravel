<?php

use App\Subscription\Jobs\SendExpiringSubscriptionSms;
use App\Articles\Jobs\TransitionArticlePublicationStatus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendExpiringSubscriptionSms)->daily();
Schedule::job(new TransitionArticlePublicationStatus)->everyMinute()->withoutOverlapping();
