<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Queue\Events\JobFailed;

use App\Listeners\MarkMailLogAsSent;
use App\Listeners\MarkMailLogAsFailedFromQueue;  

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessageSent::class   => [MarkMailLogAsSent::class],
        JobFailed::class     => [MarkMailLogAsFailedFromQueue::class], 
    ];

    public function shouldDiscoverEvents(): bool { return false; }
}
