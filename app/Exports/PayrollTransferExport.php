<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The human-readable payroll disbursement list: who is paid, into which
 * account, how much.
 *
 * Separate from the per-bank CSV layouts on purpose. A bank's upload form wants
 * bare columns and rejects anything else, while this sheet is what finance
 * actually reads and forwards — so it carries the company's letterhead, states
 * the period, and prints amounts as rupiah rather than as raw digits nobody can
 * count at a glance.
 */
final class PayrollTransferExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithDrawings, WithEvents, WithStyles, WithTitle
{
    /** Rows of the table, in display order. */
    private const HEADINGS = ['No', 'Nama Karyawan', 'Bank', 'No. Rekening', 'Atas Nama', 'Nominal (Rp)'];

    /** How many rows the letterhead occupies before the table starts. */
    private const HEADER_ROWS = 5;

    /**
     * @param  array<int, array{name: string, bank: string, account: string, holder: string, net: int}>  $rows
     */
    public function __construct(
        private array $rows,
        private string $companyName,
        private string $periodLabel,
        private string $note,
        private ?string $logoPath = null,
    ) {}

    public function title(): string
    {
        return 'Transfer Gaji';
    }

    /**
     * The letterhead, then the table.
     *
     * @return array<int, array<int, string|int|null>>
     */
    public function array(): array
    {
        $sheet = [
            [$this->companyName, null, null, null, null, null],
            ['Daftar Transfer '.$this->note, null, null, null, null, null],
            ['Periode: '.$this->periodLabel, null, null, null, null, null],
            [null, null, null, null, null, null],
            self::HEADINGS,
        ];

        foreach ($this->rows as $index => $row) {
            $sheet[] = [
                $index + 1,
                $row['name'],
                $row['bank'],
                // Leading zeros are part of an account number, so it is written
                // as text; the column format below keeps Excel from eating them.
                $row['account'],
                $row['holder'],
                $row['net'],
            ];
        }

        $sheet[] = [null, null, null, null, 'TOTAL', array_sum(array_column($this->rows, 'net'))];

        return $sheet;
    }

    /**
     * Rupiah for the amounts, plain text for the account numbers.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'D' => '@',
            'F' => '_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"_);_(@_)',
        ];
    }

    /**
     * The company logo, when the tenant has uploaded one.
     *
     * @return array<int, Drawing>
     */
    public function drawings(): array
    {
        if ($this->logoPath === null || ! is_file($this->logoPath)) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setName('Logo');
        $drawing->setDescription($this->companyName);
        $drawing->setPath($this->logoPath);
        $drawing->setHeight(48);
        $drawing->setCoordinates('F1');
        $drawing->setOffsetX(4);

        return [$drawing];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '1E293B']]],
            2 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '475569']]],
            3 => ['font' => ['size' => 10, 'color' => ['rgb' => '64748B']]],
        ];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $headerRow = self::HEADER_ROWS;
                $firstRow = $headerRow + 1;
                $lastRow = $headerRow + count($this->rows);
                $totalRow = $lastRow + 1;

                $sheet->getStyle('A'.$headerRow.':F'.$headerRow)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2F54C9'],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->getRowDimension($headerRow)->setRowHeight(22);

                if ($lastRow >= $firstRow) {
                    $sheet->getStyle('A'.$headerRow.':F'.$totalRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D8DEE9'],
                            ],
                        ],
                    ]);

                    $sheet->getStyle('A'.$firstRow.':A'.$lastRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getStyle('A'.$totalRow.':F'.$totalRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'EEF2FF'],
                    ],
                ]);

                // The header stays put while finance scrolls a long list.
                $sheet->freezePane('A'.$firstRow);
                $sheet->getRowDimension(1)->setRowHeight(40);
            },
        ];
    }
}
