<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * An error nobody caught has to end up in the log.
 *
 * The exception handler also counts faults for telemetry, in a `report`
 * callback. Laravel reads `false` returned from such a callback as "dealt
 * with, skip the default logger" -- so a callback that only wanted to observe
 * must return nothing.
 */
class UnhandledErrorsAreLoggedTest extends TestCase
{
    public function test_a_reported_exception_reaches_the_log(): void
    {
        Event::fake([MessageLogged::class]);

        app(ExceptionHandler::class)->report(new RuntimeException('The press line caught fire.'));

        Event::assertDispatched(
            MessageLogged::class,
            fn (MessageLogged $logged) => $logged->level === 'error'
                && $logged->message === 'The press line caught fire.',
        );
    }
}
