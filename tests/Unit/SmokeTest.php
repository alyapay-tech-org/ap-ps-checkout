<?php

namespace AlyaPay\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Proves the PHPUnit harness runs against a plain module class with no
 * running PrestaShop instance required.
 */
class SmokeTest extends TestCase
{
    public function testErrorContextConstantsAreDefined(): void
    {
        $this->assertSame('webhook', \AlyaPayErrorContext::WEBHOOK);
        $this->assertSame('status_check', \AlyaPayErrorContext::STATUS_CHECK);
    }
}
