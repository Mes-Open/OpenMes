<?php

namespace App\Services\Schedule\Insights;

/**
 * The planner refused an insight's move because the target overlaps another
 * order; carries its message out of the transaction so it rolls back.
 */
class MoveConflict extends \RuntimeException {}
