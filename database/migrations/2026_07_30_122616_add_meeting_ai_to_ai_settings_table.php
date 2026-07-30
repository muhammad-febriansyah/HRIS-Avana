<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings for the meeting recorder: who transcribes, which model writes the
 * deep analyses, and what a minute of audio costs in tokens.
 *
 * Transcription is a separate provider from chat on purpose — a speech model
 * bills per second of audio and is an order of magnitude cheaper than feeding
 * the same audio to a general LLM, which is the whole saving this feature rests
 * on. The expensive reasoning model is named apart too (`meeting_pro_model`), so
 * the automatic summary can stay on the cheap chat model while only the premium
 * analyses somebody clicks reach for the costly one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table): void {
            $table->boolean('stt_enabled')->default(false)->after('image_token_cost');
            $table->string('stt_provider', 32)->default('deepgram')->after('stt_enabled');
            /** Project key. Never leaves the server — the phone gets a short-lived grant. */
            $table->text('stt_api_key')->nullable()->after('stt_provider');
            $table->string('stt_model')->nullable()->after('stt_api_key');
            $table->string('stt_language', 16)->nullable()->after('stt_model');

            // Tokens charged per minute of audio, same trick as
            // `image_token_cost`: the provider prices seconds, the wallet counts
            // tokens, so the price is converted here rather than becoming a
            // second currency the meter and the per-user caps would have to
            // learn. The platform sells 500.000 tokens for Rp 150.000
            // (Rp 0,30 each) while a streaming minute with diarization costs
            // roughly Rp 100 — about 330 tokens. 500 covers that with margin.
            $table->unsignedInteger('stt_token_cost_per_minute')->default(500)->after('stt_language');

            // A hard ceiling per recording. Audio bypasses the server, so a
            // phone whose socket hangs open is otherwise free to run all night
            // on the company's tokens.
            $table->unsignedInteger('meeting_max_minutes')->default(180)->after('stt_token_cost_per_minute');

            /** Keep the phone's audio file for replay, or transcript only. */
            $table->boolean('meeting_audio_keep')->default(true)->after('meeting_max_minutes');
            /** The costly reasoning model, used only for the premium analyses. */
            $table->string('meeting_pro_model')->nullable()->after('meeting_audio_keep');
            /** Model that turns transcript chunks into vectors for AI recall. */
            $table->string('embedding_model')->nullable()->after('meeting_pro_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'stt_enabled',
                'stt_provider',
                'stt_api_key',
                'stt_model',
                'stt_language',
                'stt_token_cost_per_minute',
                'meeting_max_minutes',
                'meeting_audio_keep',
                'meeting_pro_model',
                'embedding_model',
            ]);
        });
    }
};
