<?php

namespace App\Concerns;

use App\Support\ContractType;

/**
 * Store the one spelling of a contract kind that the rest of the app reads.
 *
 * The employee form's dropdown posts the canonical key, but the same request is
 * reachable with "PKWTT" or "Tetap" typed by hand. Normalising before the rules
 * run is what lets `required_unless:contract_type,pkwtt` recognise a permanent
 * contract — and it saves the stored kind from depending on how it was typed.
 */
trait NormalisesContractType
{
    protected function normaliseContractType(): void
    {
        if (! $this->has('contract_type')) {
            return;
        }

        $this->merge([
            'contract_type' => ContractType::normalise($this->input('contract_type')),
        ]);
    }
}
