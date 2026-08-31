<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredAndEmailAndConsent(): void
    {
        $v = new Validator();
        $ok = $v->validate(
            ['name' => '', 'email' => 'bad', 'consent' => ''],
            ['name' => 'required', 'email' => 'required|email', 'consent' => 'accepted']
        );
        $this->assertFalse($ok);
        $this->assertArrayHasKey('name', $v->errors());
        $this->assertArrayHasKey('email', $v->errors());
        $this->assertArrayHasKey('consent', $v->errors());
    }

    public function testPassesValidData(): void
    {
        $v = new Validator();
        $ok = $v->validate(
            ['name' => 'Jane', 'email' => 'jane@example.com', 'phone' => '+91 98765 43210', 'consent' => '1'],
            ['name' => 'required|max:150', 'email' => 'required|email', 'phone' => 'required|phone', 'consent' => 'accepted']
        );
        $this->assertTrue($ok, json_encode($v->errors()));
        $this->assertSame([], $v->errors());
    }

    public function testMaxAndIn(): void
    {
        $v = new Validator();
        $ok = $v->validate(
            ['a' => str_repeat('x', 20), 'b' => 'nope'],
            ['a' => 'max:10', 'b' => 'in:email,phone,whatsapp']
        );
        $this->assertFalse($ok);
        $this->assertArrayHasKey('a', $v->errors());
        $this->assertArrayHasKey('b', $v->errors());
    }
}
