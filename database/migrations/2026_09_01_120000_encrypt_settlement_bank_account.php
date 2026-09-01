<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypt the bank account number snapshotted onto a settlement.
 *
 * The settlement copies the employee's account details at submission so a later
 * change cannot rewrite a past payout. That copy is the same personal data as
 * the row it came from, and was left in the clear when
 * `employee_bank_accounts.account_number` was encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table): void {
            $table->text('bank_account_number')->nullable()->change();
        });

        DB::table('settlements')
            ->select(['id', 'bank_account_number'])
            ->whereNotNull('bank_account_number')
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $value = (string) $row->bank_account_number;

                    if ($value === '' || $this->isEncrypted($value)) {
                        continue;
                    }

                    DB::table('settlements')->where('id', $row->id)->update([
                        'bank_account_number' => Crypt::encryptString($value),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('settlements')
            ->select(['id', 'bank_account_number'])
            ->whereNotNull('bank_account_number')
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $value = (string) $row->bank_account_number;

                    if (! $this->isEncrypted($value)) {
                        continue;
                    }

                    try {
                        DB::table('settlements')->where('id', $row->id)->update([
                            'bank_account_number' => Crypt::decryptString($value),
                        ]);
                    } catch (Throwable) {
                        // Undecryptable under the current key: leave it rather
                        // than destroy it.
                    }
                }
            });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->string('bank_account_number')->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
};
