<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Reads an uploaded "Saldo Cuti" sheet into raw indexed rows for
 * {@see Excel::toArray()}; parsing and validation live in
 * LeaveBalanceController::import().
 *
 * Cells are bound as strings so an Indonesian "12,5" reaches the controller
 * intact instead of being cast by the default binder.
 */
final class LeaveBalanceRowsImport extends StringValueBinder implements WithCustomValueBinder {}
