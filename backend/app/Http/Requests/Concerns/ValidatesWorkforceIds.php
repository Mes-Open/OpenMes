<?php

namespace App\Http\Requests\Concerns;

use App\Extension\Contracts\WorkforceProvider;

/**
 * Validating crews, wage groups, personnel classes and skills without assuming
 * their tables exist.
 *
 * `exists:crews,id` is wrong here twice over. It queries a table an installation
 * without the workforce module does not have — a 500 where a 422 belongs — and
 * even where the table does exist it accepts values the form never offered.
 * Asking the contract instead answers both: an installation with no module
 * offers nothing, so nothing validates, and one with the module validates
 * against exactly what the user could pick.
 *
 * The lists are memoised per request. Rule sets name several of them and rules()
 * runs on every request; with the module installed each call is a query.
 */
trait ValidatesWorkforceIds
{
    /** @var array<string, list<int>> */
    private array $workforceIds = [];

    /**
     * Ids the pickers offered, plus whatever this record already holds.
     *
     * The second part matters: an option list may be narrower than the table —
     * crewOptions() serves only active crews — and without this, editing
     * somebody already assigned to a deactivated crew would stop saving for a
     * reason nobody could see.
     *
     * @return list<int>
     */
    protected function offeredWorkforceIds(string $list, ?int ...$current): array
    {
        $ids = $this->workforceIds[$list] ??= array_column(
            app(WorkforceProvider::class)->{$list}(),
            'id',
        );

        return array_values(array_unique([...$ids, ...array_filter($current)]));
    }
}
