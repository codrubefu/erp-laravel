<?php

namespace App\Users\Providers;

use App\Notifications\Events\NotificationRequested;
use App\Notifications\Listeners\QueueNotificationDeliveries;
use Illuminate\Support\Facades\Event;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(NotificationRequested::class, QueueNotificationDeliveries::class);
    }
}
