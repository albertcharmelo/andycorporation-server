<?php

namespace App\Jobs;

use App\Events\OrderMessageSent;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastOrderMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Message $message,
        public User $sender,
        public Order $order
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Dispatch del evento que será broadcast por Pusher
        event(new OrderMessageSent($this->message, $this->sender, $this->order));
    }
}
