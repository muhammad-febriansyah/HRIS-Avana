<?php

namespace App\Imports;

use Maatwebsite\Excel\Facades\Excel;

/**
 * A no-op import used only with {@see Excel::toArray()}
 * to read a bulk-employee sheet into raw indexed rows. Parsing, name resolution
 * and validation happen in EmployeeController::bulkPreview().
 */
final class EmployeeBulkRowsImport {}
