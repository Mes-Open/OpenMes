<?php

namespace App\Extension\Contracts;

use App\Models\Worker;
use Carbon\CarbonInterface;

/**
 * Where the application asks about people rather than production.
 *
 * Absences, crew break windows and wage groups are workforce administration:
 * useful, but not something every installation records. Scheduling and costing
 * need answers either way, so they ask through this contract instead of
 * querying those tables directly. An installation that tracks none of it binds
 * the null implementation and every caller keeps working.
 *
 * The rule for implementers — and the reason NullWorkforceProvider returns what
 * it returns — is that "nothing recorded" must produce the same answer as an
 * empty table did: nobody is absent, nobody is on a break, and there is no rate
 * beyond whatever the caller falls back to.
 */
interface WorkforceProvider
{
    /** Is the worker free across the whole inclusive [$start, $end] date span? */
    public function isAvailable(Worker $worker, CarbonInterface $start, CarbonInterface $end): bool;

    /** Is the worker away on this single date? */
    public function isAbsentOn(Worker $worker, CarbonInterface $date): bool;

    /**
     * Workers away on a date — for "absent today" badges and capacity planning.
     *
     * @return array<int>
     */
    public function absentWorkerIds(CarbonInterface $date): array;

    /** Is the worker on a scheduled break at this moment? */
    public function isOnBreak(Worker $worker, CarbonInterface $moment): bool;

    /**
     * The worker's hourly pay basis, or null when nothing here decides it — the
     * caller then falls back to the rate on the worker, or the configured
     * default. Null means "no opinion", never "free".
     */
    public function hourlyRate(Worker $worker): ?float;

    /**
     * Crews a worker can be assigned to, as `[['id' => int, 'name' => string]]`.
     *
     * Empty when nothing here records crews — the form then offers no crew and
     * the validation rules built from this list reject any crew submitted
     * anyway, so a hand-written request cannot set one either.
     *
     * @return list<array{id: int, name: string}>
     */
    public function crewOptions(): array;

    /**
     * Wage groups a worker can belong to.
     *
     * @return list<array{id: int, name: string}>
     */
    public function wageGroupOptions(): array;

    /**
     * Skills that can be recorded against a worker.
     *
     * @return list<array{id: int, name: string}>
     */
    public function skillOptions(): array;

    /**
     * Personnel classes a worker can be graded as.
     *
     * @return list<array{id: int, name: string}>
     */
    public function personnelClassOptions(): array;

    /**
     * Certification levels a personnel class can require, lowest first.
     *
     * Empty when nothing here grades people — the form then offers no level and
     * the field is inert, rather than presenting a scale nothing acts on.
     *
     * @return list<string>
     */
    public function certificationLevels(): array;

    /**
     * Crews to build the planner's crew axis from, each with its workers, break
     * windows and lines already loaded.
     *
     * Returned as models rather than arrays because the caller walks them like
     * one — `$crew->workers`, `$crew->breakWindows` — and core must not name the
     * class. Empty when nothing records crews, which is what makes the axis
     * collapse to its "unassigned demand" row instead of failing.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function crewsForCapacity(): \Illuminate\Support\Collection;

    /**
     * Names of the skills carrying the given ids, keyed by id.
     *
     * Used where core holds a list of skill ids of its own — a process segment's
     * required skills are a JSON column, not a relation — and needs them
     * labelled. Unknown ids are simply absent from the result.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function skillNames(array $ids): array;
}
