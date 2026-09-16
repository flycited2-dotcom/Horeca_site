<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;

it('redirects guests to the login page', function () {
    $this->get('/manage')->assertRedirect('/manage/login');
});

it('serves the login page in Russian and keeps it out of search engines', function () {
    $this->get('/manage/login')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('lang="ru"', false);
});

it('forbids customers', function () {
    $this->actingAs(User::factory()->withApprovedCompany()->create())
        ->get('/manage')
        ->assertForbidden();
});

it('forbids deactivated staff', function () {
    $this->actingAs(User::factory()->admin()->inactive()->create())
        ->get('/manage')
        ->assertForbidden();
});

it('sends staff without two-factor authentication to its setup', function (UserRole $role) {
    $user = User::factory()->state(['role' => $role])->create();

    $this->actingAs($user)
        ->get('/manage')
        ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
})->with([UserRole::Manager, UserRole::Admin]);

it('lets staff with two-factor authentication open the dashboard', function (UserRole $role) {
    $user = User::factory()->state([
        'role' => $role,
        'app_authentication_secret' => app(AppAuthentication::class)->generateSecret(),
    ])->create();

    $this->actingAs($user)
        ->get('/manage')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with([UserRole::Manager, UserRole::Admin]);
