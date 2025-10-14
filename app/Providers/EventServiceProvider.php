<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSent;
use App\Listeners\MarkMailLogAsSent;

use Illuminate\Queue\Events\JobFailed;
use App\Listeners\MarkMailLogAsFailedFromQueue as MarkMailLogAsFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessageSent::class   => [MarkMailLogAsSent::class],
        JobFailed::class => [MarkMailLogAsFailed::class],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
