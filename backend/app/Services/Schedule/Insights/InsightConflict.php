<?php

namespace App\Services\Schedule\Insights;

/**
 * An insight action that lost a race or would clobber a later edit: someone
 * already handled the insight, or the order an undo would revert was edited
 * since. Answered with 409 so the drawer refreshes instead of retrying.
 */
class InsightConflict extends \RuntimeException {}
