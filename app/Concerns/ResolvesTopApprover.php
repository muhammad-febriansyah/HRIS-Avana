<?php

namespace App\Concerns;

use App\Models\Employee;

/**
 * Derives the "approver puncak" flag from the Atasan Langsung field.
 *
 * The employee form has one control for the reporting line. Picking a colleague
 * sets a manager; picking the explicit {@see Employee::NO_MANAGER} entry
 * declares that nobody sits above this person, which is what makes their own
 * requests auto-approve. Leaving the field untouched stays untouched, so the
 * bulk import and the mobile API keep working unchanged.
 *
 * The two sentinels live on {@see Employee} rather than here: the org chart
 * reads them from a controller that does not use this trait, and a trait
 * constant cannot be reached through the trait's own name.
 */
trait ResolvesTopApprover
{
    /**
     * What the Atasan Langsung control was set to before the sentinel was
     * folded away. Both sentinels leave `manager_id` null, so this is the only
     * thing that still tells "direksi" and "belum ditentukan" apart from a
     * control the admin never touched.
     */
    protected ?string $managerChoice = null;

    /**
     * Translate the sentinel into the two columns the app actually stores.
     */
    protected function resolveTopApprover(): void
    {
        if (! $this->has('manager_id')) {
            return;
        }

        $manager = $this->input('manager_id');
        $this->managerChoice = $manager === null ? null : (string) $manager;

        if ($manager === Employee::NO_MANAGER) {
            $this->merge(['manager_id' => null, 'is_top_approver' => true]);

            return;
        }

        if ($manager === Employee::UNASSIGNED_MANAGER) {
            $this->merge(['manager_id' => null, 'is_top_approver' => false]);

            return;
        }

        // A real manager was chosen, so this person is by definition not the
        // top of the chain — even if the flag was set earlier.
        if ($manager !== null && $manager !== '') {
            $this->merge(['is_top_approver' => false]);
        }
    }
}
