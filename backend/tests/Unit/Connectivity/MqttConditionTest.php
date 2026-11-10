<?php

namespace Tests\Unit\Connectivity;

use App\Services\Connectivity\MqttMessageParser;
use PHPUnit\Framework\TestCase;

class MqttConditionTest extends TestCase
{
    public function test_no_condition_always_holds(): void
    {
        $this->assertTrue((new MqttMessageParser)->evaluateCondition(null, 0));
        $this->assertTrue((new MqttMessageParser)->evaluateCondition('', 0));
    }

    public function test_a_condition_is_evaluated_against_the_value(): void
    {
        $parser = new MqttMessageParser;

        $this->assertTrue($parser->evaluateCondition('value == 1', 1));
        $this->assertFalse($parser->evaluateCondition('value == 1', 0));
        $this->assertTrue($parser->evaluateCondition('value > 80', 85.5));
    }

    public function test_a_condition_that_cannot_be_read_does_not_hold(): void
    {
        // A typo must not turn "only when the alarm is on" into "on every message".
        $this->assertFalse((new MqttMessageParser)->evaluateCondition('alarm == 1', 0));
        $this->assertFalse((new MqttMessageParser)->evaluateCondition('value is 1', 1));
    }
}
