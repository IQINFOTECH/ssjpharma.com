<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\Auth;
use App\Auth\Rbac;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class RbacTest extends TestCase
{
    private function rbacFor(array $roles, array $permissions): Rbac
    {
        $_SESSION = [];
        $session = new Session([
            'name' => 't', 'lifetime' => 120, 'secure' => false,
            'http_only' => true, 'same_site' => 'Lax', 'save_path' => sys_get_temp_dir(),
        ]);
        $auth = new Auth($session);
        $auth->login(['id' => 1, 'name' => 'T', 'email' => 't@t.com', 'roles' => $roles, 'permissions' => $permissions]);
        return new Rbac($auth);
    }

    public function testSuperAdminIsWildcard(): void
    {
        $rbac = $this->rbacFor(['super_admin'], []);
        $this->assertTrue($rbac->can('anything.at.all'));
        $this->assertTrue($rbac->can('users.delete'));
    }

    public function testSpecificPermissionGrantedAndDenied(): void
    {
        $rbac = $this->rbacFor(['content_manager'], ['pages.view', 'pages.edit']);
        $this->assertTrue($rbac->can('pages.edit'));
        $this->assertFalse($rbac->can('users.view'));
        $this->assertTrue($rbac->cannot('settings.edit'));
    }

    public function testGuestCanDoNothing(): void
    {
        $_SESSION = [];
        $session = new Session(['name' => 't', 'lifetime' => 120, 'secure' => false, 'http_only' => true, 'same_site' => 'Lax', 'save_path' => sys_get_temp_dir()]);
        $rbac = new Rbac(new Auth($session));
        $this->assertFalse($rbac->can('dashboard.view'));
    }
}
