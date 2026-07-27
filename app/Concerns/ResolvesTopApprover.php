<?php

namespace App\Concerns;

/**
 * Derives the "approver puncak" flag from the Atasan Langsung field.
 *
 * The employee form has one control for the reporting line. Picking a colleague
 * sets a manager; picking the explicit {@see self::NO_MANAGER} entry declares
 * that nobody sits above this person, which is what makes their own requests
 * auto-approve. Leaving the field untouched stays untouched, so the bulk import
 * and the mobile API keep working unchanged.
 */
trait ResolvesTopApprover
{
    /**
     * Sentinel the form posts for "Tidak ada — Approver Puncak". A deliberate
     * choice, unlike an empty value which just means "not filled in yet".
     */
    public const NO_MANAGER = 'none';

    /**
     * Translate the sentinel into the two columns the app actually stores.
     */
    protected function resolveTopApprover(): void
    {
        if (! $this->has('manager_id')) {
            return;
        }

        $manager = $this->input('manager_id');

        if ($manager === self::NO_MANAGER) {
            $this->merge(['manager_id' => null, 'is_top_approver' => true]);

            return;
        }

        // A real manager was chosen, so this person is by definition not the
        // top of the chain — even if the flag was set earlier.
        if ($manager !== null && $manager !== '') {
            $this->merge(['is_top_approver' => false]);
        }
    }
}
