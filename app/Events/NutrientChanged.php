<?php

namespace App\Events;

use App\Models\Nutrient;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NutrientChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action;
    public Nutrient $nutrient;

    /**
     * Create a new event instance.
     */
    public function __construct(Nutrient $nutrient, string $action)
    {
        $this->nutrient = $nutrient;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
