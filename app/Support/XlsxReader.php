<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads an .xlsx workbook without a spreadsheet library.
 *
 * An .xlsx is a zip of XML: `xl/workbook.xml` names the sheets, the rels file
 * points each name at a worksheet part, `xl/sharedStrings.xml` holds every
 * string once, and a worksheet stores cells as `<c r="B5" t="s"><v>12</v></c>`.
 * That is all this needs — enough to read a tariff table an official workbook
 * publishes, and nothing more.
 *
 * Values come back as strings keyed by column letter, e.g. `['A' => '1',
 * 'B' => '5400000']`; empty cells are absent rather than empty-string, so a
 * caller can tell "no value" from "zero".
 */
final class XlsxReader
{
    private const MAX_ARCHIVE_ENTRIES = 1_000;

    private const MAX_TOTAL_UNCOMPRESSED_BYTES = 25_000_000;

    private const MAX_ENTRY_UNCOMPRESSED_BYTES = 8_000_000;

    private const MAX_SHEETS = 50;

    private const MAX_ROWS_PER_SHEET = 10_000;

    private const MAX_CELLS_PER_ROW = 100;

    private const MAX_SHARED_STRINGS = 20_000;

    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @param  array<int, string>  $shared
     * @param  array<string, string>  $sheetPaths  sheet name => zip entry
     */
    private function __construct(
        private readonly ZipArchive $zip,
        private readonly array $shared,
        private readonly array $sheetPaths,
    ) {}

    /**
     * Open a workbook from a path on disk.
     *
     * @throws RuntimeException when the file is not a readable workbook
     */
    public static function open(string $path): self
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas tidak bisa dibuka sebagai .xlsx.');
        }

        self::assertArchiveLimits($zip);
        self::assertPlainWorkbook($zip);

        $workbook = $zip->getFromName('xl/workbook.xml');

        if ($workbook === false) {
            $zip->close();

            throw new RuntimeException('Berkas bukan workbook Excel (.xlsx) yang valid.');
        }

        $sheetPaths = self::readSheetPaths($zip, $workbook);

        if (count($sheetPaths) > self::MAX_SHEETS) {
            $zip->close();

            throw new RuntimeException('Workbook memiliki terlalu banyak sheet.');
        }

        return new self($zip, self::readSharedStrings($zip), $sheetPaths);
    }

    /**
     * The sheet names in workbook order.
     *
     * @return list<string>
     */
    public function sheetNames(): array
    {
        return array_keys($this->sheetPaths);
    }

    /**
     * Every non-empty row of a sheet, in order.
     *
     * @return list<array<string, string>>
     *
     * @throws RuntimeException when the sheet does not exist
     */
    public function rows(string $sheetName): array
    {
        $entry = $this->sheetPaths[$sheetName] ?? null;

        if ($entry === null) {
            throw new RuntimeException(sprintf('Sheet "%s" tidak ada di berkas.', $sheetName));
        }

        $xml = $this->zip->getFromName($entry);

        if ($xml === false) {
            throw new RuntimeException(sprintf('Sheet "%s" tidak bisa dibaca.', $sheetName));
        }

        $sheet = new SimpleXMLElement($xml);
        $sheet->registerXPathNamespace('m', self::MAIN_NS);

        $rows = [];

        foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $row) {
            if (count($rows) >= self::MAX_ROWS_PER_SHEET) {
                throw new RuntimeException(sprintf('Sheet "%s" melebihi batas %d baris.', $sheetName, self::MAX_ROWS_PER_SHEET));
            }

            $cells = [];
            $index = 0;

            foreach ($row->children(self::MAIN_NS)->c ?? [] as $cell) {
                if (count($cells) >= self::MAX_CELLS_PER_ROW) {
                    throw new RuntimeException(sprintf('Sheet "%s" memiliki baris dengan terlalu banyak sel.', $sheetName));
                }

                // A writer may omit `r` on cells it stores in strict order, so
                // fall back to the running position within the row.
                $ref = rtrim(self::attr($cell, 'r'), '0123456789');
                $column = $ref !== '' ? $ref : self::columnName($index);
                $index = $ref !== '' ? self::columnIndex($ref) + 1 : $index + 1;

                $value = $this->cellValue($cell);

                if ($value !== null && $value !== '') {
                    $cells[$column] = $value;
                }
            }

            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * Read an unprefixed attribute.
     *
     * `$element['t']` returns nothing once the element came out of
     * `children($ns)` — SimpleXML then looks the attribute up inside that
     * namespace, and these attributes carry none.
     */
    private static function attr(SimpleXMLElement $element, string $name): string
    {
        return (string) ($element->attributes()[$name] ?? '');
    }

    /**
     * Zero-based position to a spreadsheet column name: 0 => A, 26 => AA.
     */
    private static function columnName(int $index): string
    {
        $name = '';

        for ($n = $index; $n >= 0; $n = intdiv($n, 26) - 1) {
            $name = chr(65 + ($n % 26)).$name;
        }

        return $name;
    }

    /**
     * The inverse of {@see columnName()}: A => 0, AA => 26.
     */
    private static function columnIndex(string $name): int
    {
        $index = 0;

        foreach (str_split(strtoupper($name)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * Resolve one cell to its text, following the shared-string table when the
     * cell is a string reference rather than a literal.
     */
    private function cellValue(SimpleXMLElement $cell): ?string
    {
        $type = self::attr($cell, 't');
        $children = $cell->children(self::MAIN_NS);

        if ($type === 'inlineStr') {
            return isset($children->is) ? trim(strip_tags($children->is->asXML() ?: '')) : null;
        }

        if (! isset($children->v)) {
            return null;
        }

        $raw = (string) $children->v;

        return $type === 's' ? ($this->shared[(int) $raw] ?? null) : $raw;
    }

    /**
     * @return array<int, string>
     */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $doc = new SimpleXMLElement($xml);
        $doc->registerXPathNamespace('m', self::MAIN_NS);
        $strings = [];

        foreach ($doc->xpath('/m:sst/m:si') ?: [] as $item) {
            if (count($strings) >= self::MAX_SHARED_STRINGS) {
                throw new RuntimeException('Workbook memiliki terlalu banyak shared string.');
            }

            // A string can be split across formatting runs; concatenate them.
            $text = '';

            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $part) {
                $text .= (string) $part;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @return array<string, string>
     */
    private static function readSheetPaths(ZipArchive $zip, string $workbookXml): array
    {
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $targets = [];

        if ($relsXml !== false) {
            foreach ((new SimpleXMLElement($relsXml))->children() as $rel) {
                $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }
        }

        $workbook = new SimpleXMLElement($workbookXml);
        $workbook->registerXPathNamespace('m', self::MAIN_NS);
        $paths = [];

        // xpath rather than child traversal: `children($ns)->sheets->sheet`
        // drops the namespace on the second hop and comes back empty.
        foreach ($workbook->xpath('//m:sheets/m:sheet') ?: [] as $sheet) {
            $id = (string) $sheet->attributes(self::REL_NS)->id;
            $target = $targets[$id] ?? null;

            if ($target === null) {
                continue;
            }

            $target = ltrim($target, '/');
            $paths[(string) $sheet['name']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        return $paths;
    }

    /**
     * Refuse workbooks that are not a plain, self-contained spreadsheet.
     *
     * An encrypted workbook is an OLE container, not a zip of XML parts, and
     * reading one yields nothing usable. A workbook that pulls values from
     * another file over an external link is worse: the numbers on screen are
     * not the numbers in the file, so a tariff imported from one is unverifiable.
     */
    private static function assertPlainWorkbook(ZipArchive $zip): void
    {
        $refuse = function (ZipArchive $zip, string $message): never {
            $zip->close();

            throw new RuntimeException($message);
        };

        // The OLE parts an ECMA-376 encrypted package carries.
        foreach (['EncryptionInfo', 'EncryptedPackage'] as $entry) {
            if ($zip->locateName($entry, ZipArchive::FL_NOCASE) !== false) {
                $refuse($zip, 'Workbook terenkripsi tidak bisa dibaca. Simpan ulang tanpa password.');
            }
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_starts_with($name, 'xl/externalLinks/')) {
                $refuse($zip, 'Workbook memuat tautan ke berkas lain (external link). Simpan sebagai nilai, bukan rumus antar-berkas.');
            }

            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                $refuse($zip, 'Struktur arsip workbook tidak wajar.');
            }
        }
    }

    private static function assertArchiveLimits(ZipArchive $zip): void
    {
        if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            $zip->close();

            throw new RuntimeException('Workbook memiliki terlalu banyak bagian arsip.');
        }

        $totalSize = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;

            if ($size > self::MAX_ENTRY_UNCOMPRESSED_BYTES) {
                $zip->close();

                throw new RuntimeException('Workbook memiliki bagian terkompresi yang terlalu besar.');
            }

            $totalSize += $size;

            if ($totalSize > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
                $zip->close();

                throw new RuntimeException('Ukuran isi workbook setelah diekstrak terlalu besar.');
            }
        }
    }
}
