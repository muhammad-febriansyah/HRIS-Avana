<?php

namespace App\Http\Resources\Avana;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollPeriod
 */
final class PayrollPeriodResource extends JsonResource
{
    /**
     * Indonesian labels for the payroll period/run status enum.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'calculated' => 'Dihitung',
        'approved' => 'Disetujui',
        'locked' => 'Terkunci',
        'paid' => 'Dibayar',
        'published' => 'Terbit',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestRun = $this->latestRun();

        return [
            'id' => $this->id,
            'periode' => $this->name,
            'bayar' => $this->pay_date?->format('d M Y'),
            'karyawan' => (int) ($latestRun?->employee_count
                ?? ($this->relationLoaded('runs') ? $this->runs->sum('employee_count') : 0)),
            'netR' => self::rupiah($latestRun?->total_net ?? 0),
            'grossR' => self::rupiah($latestRun?->total_gross ?? 0),
            'status' => $this->status,
            'status_label' => self::statusLabel($this->status),
        ];
    }

    /**
     * Resolve the most recent run for the period when the relation is loaded.
     */
    private function latestRun(): ?PayrollRun
    {
        if (! $this->relationLoaded('runs')) {
            return null;
        }

        return $this->runs->sortByDesc('id')->first();
    }

    /**
     * Map a payroll period/run status to its Indonesian label.
     */
    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status] ?? (string) $status;
    }

    /**
     * Format a numeric value as an Indonesian rupiah string.
     */
    private static function rupiah(int|float|string $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}
