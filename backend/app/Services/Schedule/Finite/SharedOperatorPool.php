<?php

namespace App\Services\Schedule\Finite;

/**
 * People who staff more than one line, counted once.
 *
 * Lines that share anyone form a group; within a group, whether a line can
 * take on `n` more operators at some moment is a matching question: the
 * operators already booked on every line of the group must each be some
 * present person eligible for that line, and so must the `n` new ones — Hall's
 * condition, checked exactly with augmenting paths rather than per line.
 *
 * What a line may add is the extra it can match *on top of* what is booked:
 * if another line is already short of people (an absence after its work was
 * committed), that shortage stays its own and never blocks this line from its
 * own people.
 *
 * Pure: no database, no clock. The scheduler feeds it presence and demand.
 */
final class SharedOperatorPool
{
    /** Slot kind of the places being asked for; never a line id. */
    private const NEW_SLOT = 'new';

    /** @var array<int, list<int>> line id => the lines of its group, sorted */
    private array $groups = [];

    /** @var array<int, array<string, true>> line id => keys nobody else in its group is assigned */
    private array $private = [];

    /**
     * @param  array<int, list<string>>  $assigned  line id => person keys
     */
    public function __construct(array $assigned)
    {
        $parent = [];
        $find = function (int $line) use (&$parent, &$find): int {
            if ($parent[$line] !== $line) {
                $parent[$line] = $find($parent[$line]);
            }

            return $parent[$line];
        };

        $firstLineOf = [];
        $linesOf = [];
        foreach ($assigned as $line => $keys) {
            $parent[$line] ??= $line;
            foreach ($keys as $key) {
                $linesOf[$key][$line] = true;
                if (! isset($firstLineOf[$key])) {
                    $firstLineOf[$key] = $line;

                    continue;
                }
                $a = $find($firstLineOf[$key]);
                $b = $find($line);
                if ($a !== $b) {
                    $parent[max($a, $b)] = min($a, $b);
                }
            }
        }

        $members = [];
        foreach (array_keys($parent) as $line) {
            $members[$find($line)][] = $line;
        }
        foreach ($members as $lines) {
            sort($lines);
            foreach ($lines as $line) {
                $this->groups[$line] = $lines;
            }
        }

        foreach ($assigned as $line => $keys) {
            $this->private[$line] = [];
            foreach ($keys as $key) {
                if (count($linesOf[$key]) === 1) {
                    $this->private[$line][$key] = true;
                }
            }
        }
    }

    /**
     * The lines whose people overlap with this one's, itself included.
     *
     * @return list<int>
     */
    public function groupOf(int $lineId): array
    {
        return $this->groups[$lineId] ?? [$lineId];
    }

    /** Whether the line shares anybody with another line. */
    public function isShared(int $lineId): bool
    {
        return count($this->groupOf($lineId)) > 1;
    }

    /**
     * How many of the given keys are assigned to this line only.
     *
     * @param  list<string>  $keys
     */
    public function privateCount(int $lineId, array $keys): int
    {
        $private = $this->private[$lineId] ?? [];

        return count(array_filter($keys, fn (string $key) => isset($private[$key])));
    }

    /**
     * How many more operators $lineId can get at one moment, up to $needed,
     * and which other lines hold the people it could have used.
     *
     * With $eligible given — e.g. those holding a step's skills — only they
     * may take the new places; booked work may still use anyone present on
     * its line.
     *
     * @param  array<int, list<string>>  $present  line id => keys present on it
     * @param  array<int, int>  $demand  line id => operators already booked at that moment
     * @param  list<string>|null  $eligible  who may fill the new places; null = anyone present on $lineId
     * @return array{spare: int, busy: array<int, int>} busy: other line id => how many of this line's usable people it holds
     */
    public function spare(int $lineId, array $present, array $demand, int $needed, ?array $eligible = null): array
    {
        // Persons are numbered; each demand slot (line) may take one of the
        // persons present on its line.
        $index = [];
        $candidates = [];
        foreach ($present as $line => $keys) {
            foreach ($keys as $key) {
                $candidates[$line][] = $index[$key] ??= count($index);
            }
        }
        // New places are their own kind of slot, so they can be narrower than the line.
        $new = self::NEW_SLOT;
        $candidates[$new] = $eligible === null
            ? ($candidates[$lineId] ?? [])
            : array_values(array_filter(array_map(fn (string $key) => $index[$key] ?? null, $eligible), fn ($p) => $p !== null));

        $slots = [];
        foreach ($demand as $line => $count) {
            for ($i = 0; $i < $count; $i++) {
                $slots[] = $line;
            }
        }

        $owner = []; // person => slot
        $try = function (int $slot, array &$seen) use (&$try, &$owner, &$slots, &$candidates): bool {
            foreach ($candidates[$slots[$slot]] ?? [] as $person) {
                if (isset($seen[$person])) {
                    continue;
                }
                $seen[$person] = true;
                if (! isset($owner[$person]) || $try($owner[$person], $seen)) {
                    $owner[$person] = $slot;

                    return true;
                }
            }

            return false;
        };

        foreach (array_keys($slots) as $slot) {
            $seen = [];
            $try($slot, $seen);
        }

        // Augmenting paths keep every slot already matched matched, so each
        // success here is one more operator for $lineId on top of the booked load.
        $spare = 0;
        while ($spare < $needed) {
            $slots[] = $new;
            $seen = [];
            if (! $try(array_key_last($slots), $seen)) {
                break;
            }
            $spare++;
        }

        $busy = [];
        foreach ($candidates[$new] as $person) {
            $line = isset($owner[$person]) ? $slots[$owner[$person]] : null;
            if ($line !== null && $line !== $lineId && $line !== $new) {
                $busy[$line] = ($busy[$line] ?? 0) + 1;
            }
        }
        ksort($busy);

        return ['spare' => $spare, 'busy' => $busy];
    }
}
