<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * A finished recording by default — the state most tests care about, since
     * a meeting is only worth reading once its transcript is in.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 month', 'now');
        $minutes = fake()->numberBetween(5, 90);
        $durationMs = $minutes * 60_000;

        return [
            'tenant_id' => fn (): int => Tenant::create([
                'name' => fake()->company(),
                'slug' => Str::slug(fake()->unique()->company()),
            ])->id,
            'created_by' => null,
            'title' => 'Rapat '.fake()->randomElement(['Mingguan', 'Koordinasi', 'Evaluasi', 'Perencanaan']),
            'location' => fake()->optional()->city(),
            'source' => 'mobile_live',
            'status' => Meeting::STATUS_READY,
            'visibility' => Meeting::VISIBILITY_PARTICIPANTS,
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify("+{$minutes} minutes"),
            'duration_ms' => $durationMs,
            // Everything heard has been paid for: the default is a recording
            // that finished cleanly, not one abandoned mid-bill.
            'billed_ms' => $durationMs,
            'language' => 'id',
            'stt_model' => 'nova-2',
        ];
    }

    /**
     * Still streaming: nothing billed yet, no summary to read.
     */
    public function recording(): self
    {
        return $this->state(fn (): array => [
            'status' => Meeting::STATUS_RECORDING,
            'ended_at' => null,
            'duration_ms' => 0,
            'billed_ms' => 0,
        ]);
    }

    /**
     * Readable by the whole company rather than only the people in the room.
     */
    public function openToTenant(): self
    {
        return $this->state(fn (): array => ['visibility' => Meeting::VISIBILITY_TENANT]);
    }
}
