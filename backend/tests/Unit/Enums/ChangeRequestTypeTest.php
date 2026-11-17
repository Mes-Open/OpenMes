<?php

namespace Tests\Unit\Enums;

use App\Enums\ChangeRequestType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChangeRequestTypeTest extends TestCase
{
    #[Test]
    public function it_lists_the_stored_values(): void
    {
        $this->assertSame(['CHANGE', 'CANCEL'], ChangeRequestType::values());
    }

    #[Test]
    public function every_type_has_a_label(): void
    {
        $this->assertSame('Change', ChangeRequestType::Change->label());
        $this->assertSame('Cancellation', ChangeRequestType::Cancellation->label());
    }
}
