<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a tenant mark an employee as not subject to PPh 21 withholding.
 *
 * Some people on a payroll are genuinely outside the tenant's Article 21
 * obligation — an expatriate withheld under PPh 26, a contractor already
 * withheld at source, an employee whose tax is settled by another entity.
 * Until now every payslip ran through the TER tables regardless, and the only
 * workaround was mis-stating the subject category, which corrupts the 1721.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_profiles', function (Blueprint $table): void {
            $table->boolean('is_pph21_exempt')->default(false)->after('tax_subject');
            $table->string('pph21_exempt_reason')->nullable()->after('is_pph21_exempt');
        });
    }

    public function down(): void
    {
        Schema::table('tax_profiles', function (Blueprint $table): void {
            $table->dropColumn(['is_pph21_exempt', 'pph21_exempt_reason']);
        });
    }
};
