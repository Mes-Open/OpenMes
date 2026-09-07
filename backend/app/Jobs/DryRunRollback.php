<?php

namespace App\Jobs;

/**
 * Thrown to roll back a dry-run import chunk. Never escapes ProcessDataImport:
 * it exists only because throwing is the one way out of DB::transaction()
 * without committing, and a dedicated type keeps that rollback from swallowing
 * a real failure the way catching \Throwable would.
 */
class DryRunRollback extends \RuntimeException {}
