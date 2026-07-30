<?php

use App\Models\Meeting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The migration that moved decisions out of the summary text has to rescue what
 * was already written under the old "## Keputusan" heading — otherwise the
 * change quietly deletes content from every meeting recorded before it.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::create([
        'name' => 'PT Lama',
        'company_name' => 'PT Lama',
        'slug' => 'lama-backfill',
        'status' => 'active',
    ]);
});

/** Re-run just the decisions migration over rows written the old way. */
function rerunDecisionMigration(): void
{
    $migration = require database_path(
        'migrations/2026_07_30_162759_add_decisions_to_meetings_table.php'
    );

    $migration->down();
    $migration->up();
}

it('lifts the decisions out of an old summary and leaves the prose behind', function (): void {
    $meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Rapat Lama',
        'status' => Meeting::STATUS_READY,
        'started_at' => now(),
    ]);

    // Written the way the old code wrote it, straight past the model cast.
    DB::table('meetings')->where('id', $meeting->id)->update([
        'summary' => "Rapat membahas anggaran.\n\n## Keputusan\n"
            ."- Anggaran naik 10%\n- Vendor dikunci pekan depan",
        'decisions' => null,
    ]);

    rerunDecisionMigration();

    $fresh = $meeting->fresh();

    expect($fresh->summary)->toBe('Rapat membahas anggaran.')
        ->and($fresh->decisions)->toBe([
            'Anggaran naik 10%',
            'Vendor dikunci pekan depan',
        ]);
});

it('leaves a summary that never had the heading untouched', function (): void {
    $meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Rapat Tanpa Keputusan',
        'status' => Meeting::STATUS_READY,
        'started_at' => now(),
    ]);

    DB::table('meetings')->where('id', $meeting->id)->update([
        'summary' => 'Diskusi terbuka, tidak ada keputusan yang diambil.',
        'decisions' => null,
    ]);

    rerunDecisionMigration();

    $fresh = $meeting->fresh();

    expect($fresh->summary)->toBe('Diskusi terbuka, tidak ada keputusan yang diambil.')
        ->and($fresh->decisions)->toBeNull();
});

it('folds the decisions back into the summary when rolled back', function (): void {
    $meeting = Meeting::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Rapat Baru',
        'status' => Meeting::STATUS_READY,
        'started_at' => now(),
        'summary' => 'Rapat membahas anggaran.',
        'decisions' => ['Anggaran naik 10%'],
    ]);

    $migration = require database_path(
        'migrations/2026_07_30_162759_add_decisions_to_meetings_table.php'
    );

    // Rolling back must not lose them either — they go back where the old
    // readers would look.
    $migration->down();

    $summary = DB::table('meetings')->where('id', $meeting->id)->value('summary');

    expect($summary)->toContain('Rapat membahas anggaran.')
        ->toContain('## Keputusan')
        ->toContain('- Anggaran naik 10%');

    $migration->up();
});
