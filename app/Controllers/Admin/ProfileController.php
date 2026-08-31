<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Support\Validator;

/**
 * Self-service profile (Phase 2 §14). A user may edit their own name/email/phone
 * and change their password (via /admin/password). Users can NEVER change their
 * own roles here. Any authenticated admin user can access their own profile.
 */
final class ProfileController extends AdminController
{
    private function users(): UserRepository { return $this->container->get(UserRepository::class); }

    public function show(Request $request): Response
    {
        $id = (int) $this->currentUserId();
        $user = $this->users()->findActive($id);
        return $this->adminView('admin.profile', [
            'title'  => 'My Profile',
            'user'   => $user,
            'roles'  => $this->users()->rolesForUser($id),
            'errors' => (array) $this->session()->getFlash('profile_errors', []),
            'pwError'=> $this->session()->getFlash('pw_error'),
        ], '');
    }

    public function update(Request $request): Response
    {
        $id = (int) $this->currentUserId();
        $user = $this->users()->findActive($id);
        if ($user === null) {
            return Response::redirect('/admin/login');
        }

        $input = [
            'name'     => trim((string) $request->input('name', '')),
            'email'    => strtolower(trim((string) $request->input('email', ''))),
            'username' => trim((string) $request->input('username', '')),
            'phone'    => trim((string) $request->input('phone', '')),
        ];

        $v = new Validator();
        $v->validate($input, ['name' => 'required|max:150', 'email' => 'required|email|max:190', 'phone' => 'max:40', 'username' => 'max:60']);
        $errors = $v->errors();
        if (!isset($errors['email']) && $this->users()->emailExists($input['email'], $id)) {
            $errors['email'] = 'That email is already in use.';
        }
        if ($errors !== []) {
            $this->session()->flash('profile_errors', $errors);
            return Response::redirect('/admin/profile');
        }

        $this->users()->updateProfile($id, [
            'name'     => mb_substr($input['name'], 0, 150),
            'email'    => $input['email'],
            'username' => $input['username'] !== '' ? mb_substr($input['username'], 0, 60) : null,
            'phone'    => $input['phone'] !== '' ? mb_substr($input['phone'], 0, 40) : null,
        ]);

        // Refresh the session identity (name/email may have changed).
        $auth = $this->auth();
        $sessUser = $auth->user() ?? [];
        $sessUser['name'] = $input['name'];
        $sessUser['email'] = $input['email'];
        $auth->login($sessUser);
        // Re-track the regenerated session id.
        $sid = $this->container->get(\App\Core\Session::class)->id();
        if ($sid !== '') {
            $this->container->get(\App\Repositories\UserSessionRepository::class)->touch($sid, $id, $request->ip(), $request->userAgent());
        }

        $this->audit('USER_UPDATED', ['entity_type' => 'user', 'entity_id' => $id, 'meta' => ['self' => true]]);
        $this->flash('success', 'Profile updated.');
        return Response::redirect('/admin/profile');
    }
}
