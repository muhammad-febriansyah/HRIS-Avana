<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Meeting;
use App\Models\MeetingChunk;
use App\Models\User;
use App\Support\FaceMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use Throwable;

/**
 * Finds the part of a meeting that answers a question.
 *
 * Without this the assistant would have to be handed whole transcripts, and an
 * hour of speech costs more to read than the answer is worth — so a question is
 * embedded once and only the closest few slices of transcript are quoted.
 *
 * Ranking is cosine similarity computed in PHP, over the tenant's own chunks.
 * MySQL 9 has a VECTOR type but this edition ships no distance function, and a
 * vector database would be a new dependency for a set of rows that fits in
 * memory. {@see FaceMatcher::cosine()} already does exactly this arithmetic for
 * face embeddings, so it is reused rather than rewritten.
 */
final class MeetingSearch
{
    /**
     * Chunks quoted back to the model. Enough to cover a topic discussed in two
     * places, few enough to stay cheap.
     */
    private const TOP_K = 6;

    /**
     * Ignore anything below this similarity: a transcript that never mentions
     * the topic should return nothing, not its least-unrelated paragraph.
     */
    private const MIN_SIMILARITY = 0.2;

    /**
     * Chunks pulled into memory for one search, newest meetings first. A cap
     * rather than a full table scan: a company with years of recordings would
     * otherwise embed-compare tens of thousands of rows to answer one question.
     */
    private const CANDIDATE_LIMIT = 4_000;

    /**
     * The passages of the caller's readable meetings closest to a question.
     *
     * @return Collection<int, array{meeting: Meeting, chunk: MeetingChunk, similarity: float}>
     */
    public function search(User $user, string $question): Collection
    {
        $vector = $this->embed($question);

        if ($vector === null || $user->tenant_id === null) {
            return collect();
        }

        $meetings = Meeting::query()
            ->forTenant($user->tenant_id)
            ->where('status', Meeting::STATUS_READY)
            ->readableBy($user->employee?->id, $user->id, $user->hasPermissionTo('meeting.view'))
            ->orderByDesc('started_at')
            ->get(['id', 'title', 'started_at', 'location'])
            ->keyBy('id');

        if ($meetings->isEmpty()) {
            return collect();
        }

        return MeetingChunk::query()
            ->forTenant($user->tenant_id)
            ->whereIn('meeting_id', $meetings->keys())
            ->whereNotNull('embedding')
            ->orderByDesc('meeting_id')
            ->orderBy('ordinal')
            ->limit(self::CANDIDATE_LIMIT)
            ->get()
            ->map(fn (MeetingChunk $chunk): array => [
                'meeting' => $meetings[$chunk->meeting_id],
                'chunk' => $chunk,
                'similarity' => FaceMatcher::cosine($vector, $chunk->embedding ?? []),
            ])
            ->filter(fn (array $hit): bool => $hit['similarity'] >= self::MIN_SIMILARITY)
            ->sortByDesc('similarity')
            ->take(self::TOP_K)
            ->values();
    }

    /**
     * The question as a vector, or null when embeddings are unavailable.
     *
     * A failure here is not surfaced: the assistant's tool falls back to saying
     * it found nothing, which is a better answer than a provider error message.
     *
     * @return array<int, float>|null
     */
    private function embed(string $question): ?array
    {
        $question = trim($question);

        if ($question === '') {
            return null;
        }

        $settings = AiSetting::current();

        if (! $settings->isReady()) {
            return null;
        }

        $models = $settings->resolvedMeetingModels();

        if ($models['api_key'] !== '') {
            config(["prism.providers.{$models['provider']}.api_key" => $models['api_key']]);
        }

        try {
            $response = Prism::embeddings()
                ->using($models['provider'], $models['embedding'])
                ->fromInput($question)
                ->asEmbeddings();
        } catch (Throwable $e) {
            Log::error('Meeting search embedding failed', ['message' => $e->getMessage()]);

            return null;
        }

        $embedding = $response->embeddings[0]->embedding ?? null;

        return is_array($embedding) ? array_map('floatval', $embedding) : null;
    }
}
