<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Attrition (resign-risk) scoring weights
    |--------------------------------------------------------------------------
    |
    | Points each factor contributes when it is triggered. Mirrors the client's
    | scoring table and sums to 100. The scorer normalises over the factors that
    | actually have data, so a missing factor lowers neither the numerator nor
    | the denominator (graceful degradation) — the 0-100 scale and the bands
    | below stay meaningful even with partial data.
    |
    */
    'weights' => [
        'tenure' => 15,          // masa kerja < 1 tahun
        'no_raise' => 15,        // tidak ada kenaikan gaji > 2 tahun
        'lateness' => 10,        // sering terlambat (> 5x / bulan)
        'overtime' => 10,        // lembur > 40 jam/bulan selama 3 bulan
        'performance' => 10,     // penilaian kinerja menurun
        'engagement' => 20,      // engagement survey rendah
        'leave_spike' => 5,      // pengajuan cuti meningkat drastis
        'manager_change' => 5,   // pergantian atasan dalam 6 bulan
        'no_promotion' => 10,    // tidak dipromosikan > 3 tahun
    ],

    /*
    | Upper bound (inclusive) of each risk band on the 0-100 scale.
    |   0..low        => rendah 🟢
    |   (low+1)..med  => sedang 🟡
    |   (med+1)..100  => tinggi 🔴
    */
    'bands' => [
        'low' => 29,
        'medium' => 59,
    ],

    /*
    | Thresholds each factor uses to decide "triggered".
    */
    'rules' => [
        'tenure_months' => 12,          // < 12 months tenure
        'no_raise_months' => 24,        // no salary raise within 24 months
        'lateness_per_month' => 5,      // > 5 late days in the trailing month
        'overtime_hours_month' => 40,   // > 40 approved OT hours/month...
        'overtime_months' => 3,         // ...for each of the last 3 months
        'engagement_low' => 3.0,        // average rating <= 3.0 (of 5) is low
        'leave_spike_factor' => 2.0,    // recent month >= 2x prior monthly avg
        'manager_change_months' => 6,   // manager changed within 6 months
        'no_promotion_years' => 3,      // no promotion within 3 years
    ],
];
