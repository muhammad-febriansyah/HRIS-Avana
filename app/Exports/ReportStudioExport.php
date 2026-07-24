<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A polished single-sheet .xlsx export for a Report Studio pivot: a company
 * header, a coloured header band, zebra-striped rows, per-column number
 * formats (currency/decimal/integer), a frozen header, and generous column
 * widths so the table is comfortable to read.
 */
final class ReportStudioExport implements FromArray, WithEvents, WithTitle
{
    private const BRAND = '2547F9';

    private const HEADER_ROW = 4;

    private const LABEL_WIDTH = 38;

    private const VALUE_WIDTH = 22;

    /**
     * @param  array<int, array{label: string, format: string}>  $columns
     * @param  array<int, array{label: string, cells: array<int, int|float|null>}>  $rows
     */
    public function __construct(
        private array $columns,
        private array $rows,
        private string $rowHeader,
        private string $subtitle,
        private string $meta,
    ) {}

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    public function array(): array
    {
        $width = 1 + count($this->columns);

        $grid = [
            $this->pad([$this->subtitle], $width),
            $this->pad([$this->meta], $width),
            array_fill(0, $width, null),
            array_merge([$this->rowHeader], array_map(fn (array $c): string => $c['label'], $this->columns)),
        ];

        foreach ($this->rows as $row) {
            $grid[] = array_merge([$row['label']], $row['cells']);
        }

        return $grid;
    }

    public function title(): string
    {
        return 'Report Studio';
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $width = 1 + count($this->columns);
                $lastCol = Coordinate::stringFromColumnIndex($width);
                $headerRow = self::HEADER_ROW;
                $firstDataRow = $headerRow + 1;
                $lastRow = $headerRow + count($this->rows);

                $this->setColumnWidths($sheet, $width);
                $this->styleHeadings($sheet, $lastCol);
                $this->styleHeader($sheet, $lastCol, $headerRow);
                $this->styleBody($sheet, $lastCol, $headerRow, $firstDataRow, $lastRow);
                $this->applyColumnFormats($sheet, $firstDataRow, $lastRow);

                $sheet->freezePane('A'.$firstDataRow);
            },
        ];
    }

    /**
     * @param  array<int, string|int|float|null>  $first
     * @return array<int, string|int|float|null>
     */
    private function pad(array $first, int $width): array
    {
        return array_merge($first, array_fill(0, max(0, $width - count($first)), null));
    }

    private function setColumnWidths(Worksheet $sheet, int $width): void
    {
        $sheet->getColumnDimension('A')->setWidth(self::LABEL_WIDTH);
        for ($i = 2; $i <= $width; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(self::VALUE_WIDTH);
        }
    }

    private function styleHeadings(Worksheet $sheet, string $lastCol): void
    {
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0E1A3A');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9.5, 'color' => ['rgb' => '6B7280']],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(15);
    }

    private function styleHeader(Worksheet $sheet, string $lastCol, int $headerRow): void
    {
        $range = "A{$headerRow}:{$lastCol}{$headerRow}";

        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("A{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        for ($i = 2; $i <= 1 + count($this->columns); $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle("{$col}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getRowDimension($headerRow)->setRowHeight(26);
    }

    private function styleBody(Worksheet $sheet, string $lastCol, int $headerRow, int $firstDataRow, int $lastRow): void
    {
        if ($lastRow < $firstDataRow) {
            return;
        }

        for ($row = $firstDataRow; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(20);

            if (($row - $firstDataRow) % 2 === 1) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F7FC');
            }
        }

        for ($i = 2; $i <= 1 + count($this->columns); $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E0F0');

        $sheet->getStyle("A{$firstDataRow}:A{$lastRow}")->getFont()->setBold(true);
    }

    private function applyColumnFormats(Worksheet $sheet, int $firstDataRow, int $lastRow): void
    {
        if ($lastRow < $firstDataRow) {
            return;
        }

        foreach ($this->columns as $index => $column) {
            $col = Coordinate::stringFromColumnIndex($index + 2);
            $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastRow}")
                ->getNumberFormat()->setFormatCode($this->formatCode($column['format']));
        }
    }

    private function formatCode(string $format): string
    {
        return match ($format) {
            'currency' => '"Rp" #,##0',
            'decimal' => '#,##0.0',
            default => '#,##0',
        };
    }
}
