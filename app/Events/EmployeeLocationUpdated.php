<?php

namespace App\Events;

use App\Models\EmployeeLastLocation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EmployeeLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly EmployeeLastLocation $location) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->location->tenant_id.'.tracking'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'EmployeeLocationUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'employee_id' => $this->location->employee_id,
            'latitude' => (float) $this->location->latitude,
            'longitude' => (float) $this->location->longitude,
            'accuracy' => (float) $this->location->accuracy,
            'speed' => $this->location->speed !== null ? (float) $this->location->speed : null,
            'heading' => $this->location->heading !== null ? (float) $this->location->heading : null,
            'battery_level' => $this->location->battery_level,
            'is_mocked' => $this->location->is_mocked,
            'is_suspicious' => $this->location->is_suspicious,
            'recorded_at' => $this->location->recorded_at?->toIso8601String(),
        ];
    }
}
