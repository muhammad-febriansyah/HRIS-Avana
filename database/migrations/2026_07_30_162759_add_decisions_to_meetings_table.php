<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep a meeting's decisions as data instead of as a heading inside its summary.
 *
 * They arrive from the model already structured, and were being flattened into
 * the summary text under a "## Keputusan" heading that the readers then had to
 * find again by string search. That made a literal heading the contract between
 * the writer and every screen: a model that wrote "## Keputusan Rapat", or a
 * change of wording here, would drop the decisions into the prose silently —
 * no error, no failing test. Storing them as they came removes the round trip.
 *
 * Existing summaries are parsed once here so nothing already written is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->json('decisions')->nullable()->after('summary');
        });

        DB::table('meetings')
            ->whereNotNull('summary')
            ->where('summary', 'like', '%## Keputusan%')
            ->select('id', 'summary')
            ->orderBy('id')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    [$body, $decisions] = self::split((string) $row->summary);

                    DB::table('meetings')->where('id', $row->id)->update([
                        'summary' => $body,
                        'decisions' => json_encode($decisions),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Fold the decisions back into the summary so rolling back does not
        // lose them either.
        DB::table('meetings')
            ->whereNotNull('decisions')
            ->select('id', 'summary', 'decisions')
            ->orderBy('id')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $decisions = json_decode((string) $row->decisions, true) ?: [];

                    if ($decisions === []) {
                        continue;
                    }

                    $bullets = implode("\n", array_map(
                        static fn (string $decision): string => '- '.$decision,
                        $decisions,
                    ));

                    DB::table('meetings')->where('id', $row->id)->update([
                        'summary' => trim((string) $row->summary."\n\n## Keputusan\n".$bullets),
                    ]);
                }
            });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropColumn('decisions');
        });
    }

    /**
     * Pull the bullet list out from under the heading.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private static function split(string $summary): array
    {
        $marker = mb_strpos($summary, '## Keputusan');

        if ($marker === false) {
            return [$summary, []];
        }

        $decisions = collect(explode("\n", mb_substr($summary, $marker)))
            ->map(static fn (string $line): string => ltrim($line))
            ->filter(static fn (string $line): bool => str_starts_with($line, '-'))
            ->map(static fn (string $line): string => trim(mb_substr($line, 1)))
            ->filter()
            ->values()
            ->all();

        return [trim(mb_substr($summary, 0, $marker)), $decisions];
    }
};
