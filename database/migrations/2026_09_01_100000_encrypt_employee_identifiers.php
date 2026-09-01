<?php

use App\Support\Pii;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypt the personal identifiers at rest: the KTP number, the tax number, and
 * bank account numbers.
 *
 * The columns are widened to text first — a ciphertext is far longer than the
 * 16 digits it protects — then every existing row is encrypted in place. NIK
 * also gains a keyed-hash column, because an encrypted column cannot be
 * searched: the ciphertext is different on every write, so the duplicate-NIK
 * check has to match on the hash instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->text('nik')->nullable()->change();
            $table->char('nik_hash', 64)->nullable()->after('nik');
            $table->index(['tenant_id', 'nik_hash']);
        });

        Schema::table('tax_profiles', function (Blueprint $table): void {
            $table->text('nik')->nullable()->change();
            $table->text('npwp')->nullable()->change();
        });

        Schema::table('employee_bank_accounts', function (Blueprint $table): void {
            $table->text('account_number')->nullable()->change();
        });

        $this->encryptColumn('employees', 'nik', hashInto: 'nik_hash');
        $this->encryptColumn('tax_profiles', 'nik');
        $this->encryptColumn('tax_profiles', 'npwp');
        $this->encryptColumn('employee_bank_accounts', 'account_number');
    }

    public function down(): void
    {
        $this->decryptColumn('employees', 'nik');
        $this->decryptColumn('tax_profiles', 'nik');
        $this->decryptColumn('tax_profiles', 'npwp');
        $this->decryptColumn('employee_bank_accounts', 'account_number');

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'nik_hash']);
            $table->dropColumn('nik_hash');
            $table->string('nik')->nullable()->change();
        });

        Schema::table('tax_profiles', function (Blueprint $table): void {
            $table->string('nik')->nullable()->change();
            $table->string('npwp')->nullable()->change();
        });

        Schema::table('employee_bank_accounts', function (Blueprint $table): void {
            $table->string('account_number')->nullable()->change();
        });
    }

    /**
     * Encrypt every plaintext value in the column, chunked so a large table
     * does not have to fit in memory. Already-encrypted rows are skipped, which
     * makes a re-run harmless.
     */
    private function encryptColumn(string $table, string $column, ?string $hashInto = null): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($table, $column, $hashInto): void {
                foreach ($rows as $row) {
                    $value = (string) $row->{$column};

                    if ($value === '' || $this->isEncrypted($value)) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update(array_filter([
                        $column => Crypt::encryptString($value),
                        $hashInto => $hashInto === null ? null : Pii::hash($value),
                    ], fn ($item): bool => $item !== null));
                }
            });
    }

    private function decryptColumn(string $table, string $column): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $value = (string) $row->{$column};

                    if (! $this->isEncrypted($value)) {
                        continue;
                    }

                    try {
                        DB::table($table)->where('id', $row->id)
                            ->update([$column => Crypt::decryptString($value)]);
                    } catch (Throwable) {
                        // Undecryptable under the current key: leave it be
                        // rather than destroy it.
                    }
                }
            });
    }

    /**
     * Laravel ciphertext is base64-encoded JSON carrying iv/value/mac.
     */
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
