<?php

declare(strict_types=1);

namespace Tests\Unit\Stream;

use Basis\Nats\Stream\ConsumerLimits;
use Exception;
use Tests\FunctionalTestCase;

class ConsumerLimitsTest extends FunctionalTestCase
{
    public function testValidateWithValidLimits(): void
    {
        $limits = [
            ConsumerLimits::MAX_ACK_PENDING => 100,
            ConsumerLimits::INACTIVE_THRESHOLD => 5000000000,
        ];

        $result = ConsumerLimits::validate($limits);

        $this->assertSame($limits, $result);
    }

    public function testValidateWithEmptyLimits(): void
    {
        $result = ConsumerLimits::validate([]);

        $this->assertEmpty($result);
    }

    public function testValidateWithInvalidMaxAckPending(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid param: max_ack_pending");

        ConsumerLimits::validate([
            ConsumerLimits::MAX_ACK_PENDING => 'invalid',
        ]);
    }

    public function testValidateWithInvalidInactiveThreshold(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid param: inactive_threshold");

        ConsumerLimits::validate([
            ConsumerLimits::INACTIVE_THRESHOLD => 'invalid',
        ]);
    }

    public function testValidateWithUnknownParam(): void
    {
        // Unknown params should pass validation (returns true in paramIsValid)
        $limits = [
            'unknown_param' => 'any_value',
        ];

        $result = ConsumerLimits::validate($limits);

        $this->assertSame($limits, $result);
    }
}
