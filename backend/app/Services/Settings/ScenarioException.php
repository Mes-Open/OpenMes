<?php

namespace App\Services\Settings;

/** A production scenario event that could not be replayed; the message names the event. */
class ScenarioException extends \RuntimeException {}
