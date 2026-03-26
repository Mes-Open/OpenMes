<?php

namespace App\Events\Issue;

use App\Models\Issue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueAcknowledged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Issue $issue) {}
}
