<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * The Absensi page's "Export Rekap" as a document HR can read and forward:
 * the company letterhead, the period, a one-line status summary, then one row
 * per employee per day with the status coloured the way the screen colours it.
 *
 * Plain Laporan exports stay on ReportExport; this one exists because a rekap
 * is read by people, and a bare CSV showed employee numbers as 1,11111E+15 and
 * dates as text Excel could not sort.
 */
final class AttendanceRecapExport extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithDrawings, WithEvents, WithTitle
{
    /** @var list<string> */
    private const HEADINGS = ['No', 'Tanggal', 'Nama Karyawan', 'No. Karyawan', 'Cabang', 'Shift', 'Masuk', 'Keluar', 'Telat (menit)', 'Status', 'Keterangan'];

    /** Letterhead rows above the table: company, title, period, summary, spacer. */
    private const HEADER_ROWS = 6;

    private const LAST_COLUMN = 'K';

    /** @var array<string, int> */
    private const COLUMN_WIDTHS = [
        'A' => 6, 'B' => 13, 'C' => 30, 'D' => 20, 'E' => 16, 'F' => 24,
        'G' => 9, 'H' => 9, 'I' => 12, 'J' => 16, 'K' => 42,
    ];

    /**
     * Status label => [text colour, fill colour], matching the rekap badges.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const STATUS_COLOURS = [
        'Hadir' => ['15803D', 'DCFCE7'],
        'Terlambat' => ['B45309', 'FEF3C7'],
        'Cuti' => ['1D4ED8', 'DBEAFE'],
        'Alpa' => ['B91C1C', 'FEE2E2'],
        'Belum Absen' => ['6D28D9', 'EDE9FE'],
        'Belum Tercatat' => ['6D28D9', 'EDE9FE'],
        'Belum Lengkap' => ['B45309', 'FEF3C7'],
        'Perlu Koreksi' => ['B45309', 'FEF3C7'],
    ];

    /**
     * @param  array<int, array<int, string|int|null>>  $rows  Laporan absensi rows:
     *                                                         date (Y-m-d), name, number, branch, shift, in, out, late, status, note
     */
    public function __construct(
        private array $rows,
        private string $companyName,
        private string $periodLabel,
        private string $printedBy,
        private ?string $logoPath = null,
    ) {}

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    public function array(): array
    {
        $blank = array_fill(0, count(self::HEADINGS), null);

        $sheet = [
            $this->line($this->hasLogo() ? null : $this->companyName),
            $this->line('Rekap Absensi Karyawan'),
            $this->line('Periode: '.$this->periodLabel.'  ·  Dicetak '.Carbon::now()->translatedFormat('j M Y H:i').' oleh '.$this->printedBy),
            $this->line($this->summary()),
            $blank,
            self::HEADINGS,
        ];

        foreach ($this->rows as $index => $row) {
            $date = $row[0] ?? null;

            $sheet[] = [
                $index + 1,
                // A real date, so the column sorts and filters as one.
                is_string($date) && $date !== '' ? ExcelDate::PHPToExcel(Carbon::parse($date)) : null,
                $row[1] ?? null,
                $row[2] ?? null,
                $row[3] ?? null,
                $row[4] ?? null,
                $row[5] ?? null,
                $row[6] ?? null,
                (int) ($row[7] ?? 0),
                $row[8] ?? null,
                $row[9] ?? null,
            ];
        }

        return $sheet;
    }

    /**
     * Employee numbers are identifiers, not quantities: written as text so a
     * 16-digit number keeps every digit instead of turning into 1,11111E+15.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getColumn() === 'D' && $cell->getRow() > self::HEADER_ROWS && $value !== null) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * @return array<int, Drawing>
     */
    public function drawings(): array
    {
        if (! $this->hasLogo()) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setName('Logo');
        $drawing->setDescription($this->companyName);
        $drawing->setPath((string) $this->logoPath);
        $drawing->setHeight(40);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(4);

        return [$drawing];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COLUMN;
                $headerRow = self::HEADER_ROWS;
                $firstRow = $headerRow + 1;
                $lastRow = $headerRow + count($this->rows);

                foreach ([1, 2, 3, 4] as $line) {
                    $sheet->mergeCells("A{$line}:{$last}{$line}");
                    $sheet->getStyle("A{$line}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('0F172A');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1E3A8A');
                $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('64748B');
                $sheet->getStyle('A4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '334155']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                ]);
                $sheet->getRowDimension(1)->setRowHeight($this->hasLogo() ? 36 : 24);
                $sheet->getRowDimension(4)->setRowHeight(20);
                $sheet->getRowDimension(5)->setRowHeight(6);

                $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F54C9']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(24);

                foreach (self::COLUMN_WIDTHS as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                if ($lastRow >= $firstRow) {
                    $sheet->getStyle("A{$headerRow}:{$last}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D8DEE9']]],
                    ]);
                    $sheet->getStyle("A{$firstRow}:{$last}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("B{$firstRow}:B{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');

                    foreach (['A', 'B', 'G', 'H', 'I', 'J'] as $column) {
                        $sheet->getStyle("{$column}{$firstRow}:{$column}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    $sheet->getStyle("K{$firstRow}:K{$lastRow}")->getAlignment()->setWrapText(true);

                    for ($row = $firstRow; $row <= $lastRow; $row++) {
                        // Zebra rows keep a long list readable across the page.
                        if (($row - $firstRow) % 2 === 1) {
                            $sheet->getStyle("A{$row}:I{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                            $sheet->getStyle("K{$row}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                        }

                        $colours = self::STATUS_COLOURS[(string) $sheet->getCell("J{$row}")->getValue()] ?? null;

                        if ($colours !== null) {
                            $sheet->getStyle("J{$row}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => $colours[0]]],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colours[1]]],
                            ]);
                        }
                    }

                    $sheet->setAutoFilter("A{$headerRow}:{$last}{$lastRow}");
                }

                $sheet->freezePane('C'.$firstRow);
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
            },
        ];
    }

    private function hasLogo(): bool
    {
        return $this->logoPath !== null && is_file($this->logoPath);
    }

    /**
     * One letterhead line in the first column; the rest of the row is merged.
     *
     * @return array<int, string|null>
     */
    private function line(?string $text): array
    {
        return [$text, ...array_fill(0, count(self::HEADINGS) - 1, null)];
    }

    /** "Hadir 13 · Terlambat 0 · …" across every status the rows carry. */
    private function summary(): string
    {
        $counts = array_count_values(array_map(
            fn (array $row): string => (string) ($row[8] ?? ''),
            $this->rows,
        ));

        $order = ['Hadir', 'Terlambat', 'Cuti', 'Alpa', 'Belum Absen', 'Belum Tercatat', 'Belum Lengkap'];
        $parts = ['Total '.count($this->rows).' baris'];

        foreach ($order as $label) {
            if (($counts[$label] ?? 0) > 0) {
                $parts[] = $label.' '.$counts[$label];
            }
        }

        return 'Ringkasan: '.implode('  ·  ', $parts);
    }
}
