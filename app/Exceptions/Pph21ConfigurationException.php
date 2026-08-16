<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The PPh 21 master data cannot answer a question payroll needs answered —
 * a PTKP status with no TER category mapped to it, say.
 *
 * Withholding on a guess is worse than not withholding at all: the employee is
 * charged the wrong tax and nobody finds out until the annual reconciliation.
 * So the run stops and names what has to be configured first.
 */
final class Pph21ConfigurationException extends RuntimeException {}
