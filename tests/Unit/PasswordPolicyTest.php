<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testRejectsTooShort(): void
    {
        $this->assertNotNull(PasswordPolicy::validate('short', 10));
    }

    public function testRejectsRepeatedChar(): void
    {
        $this->assertNotNull(PasswordPolicy::validate('aaaaaaaaaaaa', 10));
    }

    public function testRejectsAllNumeric(): void
    {
        $this->assertNotNull(PasswordPolicy::validate('1234567890123', 10));
    }

    public function testRejectsEqualToEmailLocalPart(): void
    {
        $this->assertNotNull(PasswordPolicy::validate('johnsmith', 8, 'johnsmith@example.com'));
    }

    public function testAcceptsReasonablePassword(): void
    {
        $this->assertNull(PasswordPolicy::validate('Str0ngEnough!', 10, 'jane@example.com'));
    }
}
