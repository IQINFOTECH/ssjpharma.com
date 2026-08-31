<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    private function csrf(): Csrf
    {
        $_SESSION = [];
        $session = new Session([
            'name' => 'test', 'lifetime' => 120, 'secure' => false,
            'http_only' => true, 'same_site' => 'Lax',
            'save_path' => sys_get_temp_dir(),
        ]);
        return new Csrf($session);
    }

    public function testTokenIsStableWithinSession(): void
    {
        $csrf = $this->csrf();
        $a = $csrf->token();
        $b = $csrf->token();
        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a)); // 32 bytes hex
    }

    public function testVerifyAcceptsCorrectTokenAndRejectsOthers(): void
    {
        $csrf = $this->csrf();
        $token = $csrf->token();

        $this->assertTrue($csrf->verify($token));
        $this->assertFalse($csrf->verify('wrong'));
        $this->assertFalse($csrf->verify(null));
        $this->assertFalse($csrf->verify(''));
    }

    public function testRotateChangesToken(): void
    {
        $csrf = $this->csrf();
        $first = $csrf->token();
        $csrf->rotate();
        $this->assertNotSame($first, $csrf->token());
    }
}
