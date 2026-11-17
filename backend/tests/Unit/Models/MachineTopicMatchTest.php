<?php

namespace Tests\Unit\Models;

use App\Models\MachineTopic;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MachineTopicMatchTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'exact' => ['hall1/press3/count', 'hall1/press3/count', true],
            'exact, other topic' => ['hall1/press3/count', 'hall1/press4/count', false],
            '+ one level' => ['hall1/+/count', 'hall1/press3/count', true],
            '+ is not two levels' => ['hall1/+/count', 'hall1/a/b/count', false],
            '+ needs a level' => ['hall1/+', 'hall1', false],
            '# below' => ['hall1/#', 'hall1/press3/state', true],
            '# includes the parent' => ['hall1/#', 'hall1', true],
            '# other prefix' => ['hall1/#', 'hall2/press3', false],
            '+ then #' => ['hall1/+/#', 'hall1/press3/state/code', true],
            '+ then # with no tail' => ['hall1/+/#', 'hall1/press3', true],
            '+ then # wrong root' => ['hall1/+/#', 'hall2/press3/state', false],
            '+ in the middle, # at the end' => ['plant/+/press/#', 'plant/hall1/press/a/b', true],
            'two +' => ['+/+/count', 'hall1/press3/count', true],
            'pattern shorter' => ['hall1/press3', 'hall1/press3/count', false],
            'pattern longer' => ['hall1/press3/count', 'hall1/press3', false],
            '# matches all' => ['#', 'anything/at/all', true],
            'wildcard skips $SYS' => ['#', '$SYS/broker/uptime', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_topic_matching(string $pattern, string $topic, bool $expected): void
    {
        $model = new MachineTopic;
        $model->topic_pattern = $pattern;

        $this->assertSame($expected, $model->matchesTopic($topic));
    }
}
