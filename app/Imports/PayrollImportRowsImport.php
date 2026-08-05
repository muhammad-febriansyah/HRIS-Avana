<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Reads an uploaded payroll sheet into raw indexed rows for
 * {@see Excel::toArray()}; parsing and validation live in
 * PayrollImportController::store().
 *
 * Every cell is bound as a string on purpose. The default binder casts
 * "300.000" to 300.0 — an Indonesian sheet's three hundred thousand read as
 * three hundred, silently under-paying by a factor of a thousand. Keeping the
 * text lets the controller resolve the separators itself.
 */
final class PayrollImportRowsImport extends StringValueBinder implements WithCustomValueBinder {}
