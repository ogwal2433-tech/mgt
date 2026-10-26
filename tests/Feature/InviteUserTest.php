<?php

use App\Mail\UserInviteMail;
use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('invites a user by email with a temporary password and forces a change at first login', function () {
    Mail::fake();

    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');

    $response = $this->actingAs($admin)->post("/users/{$staff->id}/invite");
    $response->assertSessionHas('invite');
    $response->assertSessionHas('success');

    $invite = session('invite');
    expect($invite['temporary_password'])->not->toBeEmpty();
    expect($invite['username'])->toBe($staff->username);
    expect($invite['email'])->toBe($staff->email);

    Mail::assertSent(UserInviteMail::class, function ($mail) use ($staff, $invite) {
        return $mail->hasTo($staff->email) && $mail->temporaryPassword === $invite['temporary_password'];
    });

    // The temporary password replaces the old one and a change is forced.
    $staff->refresh();
    expect($staff->must_change_password)->toBeTrue();
    expect($staff->is_active)->toBeTrue();

    // The email delivery is tracked.
    expect(EmailDelivery::where('user_id', $staff->id)->where('type', 'user_invite')->count())->toBe(1);

    // Sign in with the temporary password → sent to the profile page to change it.
    auth()->logout();
    $this->post('/login', ['login' => $staff->username, 'password' => $invite['temporary_password']])
        ->assertRedirect('/profile?force_password=1');

    // The profile page prompts for the change.
    $this->get('/profile')->assertInertia(fn ($page) => $page
        ->component('Profile/Edit')
        ->where('mustChangePassword', true)
    );

    // Set a personal password.
    $this->put('/password', [
        'current_password' => $invite['temporary_password'],
        'password' => 'NewSecretPass1!',
        'password_confirmation' => 'NewSecretPass1!',
    ])->assertSessionHas('success');

    $staff->refresh();
    expect($staff->must_change_password)->toBeFalse();

    // The temporary password no longer works; the new one does.
    auth()->logout();
    $this->post('/login', ['login' => $staff->username, 'password' => $invite['temporary_password']])
        ->assertSessionHasErrors('login');

    $this->post('/login', ['login' => $staff->username, 'password' => 'NewSecretPass1!'])
        ->assertRedirect('/dashboard');
});

it('mentions the invited user by name in the email', function () {
    Mail::fake();

    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');
    $staff->employee->update(['first_name' => 'Esther', 'last_name' => 'Wanjiku']);

    $this->actingAs($admin)->post("/users/{$staff->id}/invite")->assertSessionHas('invite');

    Mail::assertSent(UserInviteMail::class, function ($mail) {
        $html = $mail->render();

        return str_contains($html, 'Hello Esther Wanjiku')
            && str_contains($html, 'Biiteek')
            && str_contains($html, 'set a new password');
    });
});

it('blocks non-administrators from inviting users', function () {
    Mail::fake();

    $lead = makeUser('technical_lead');
    $staff = makeUser('technical_staff');

    $this->actingAs($lead)->post("/users/{$staff->id}/invite")->assertForbidden();

    Mail::assertNothingSent();
    expect($staff->fresh()->must_change_password)->toBeFalse();
});

it('records the invitation in the audit log', function () {
    Mail::fake();

    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');

    $this->actingAs($admin)->post("/users/{$staff->id}/invite");

    expect(\App\Models\AuditLog::where('action', 'user.invited')->where('entity_id', $staff->id)->count())->toBe(1);
});

it('invites the employee by email when creating their account', function () {
    Mail::fake();

    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');

    $response = $this->actingAs($admin)->post('/users', [
        'username' => 'newjoiner',
        'name' => 'New Joiner',
        'email' => 'joiner@biiteek.test',
        'employee_id' => $staff->employee_id,
        'roles' => [\App\Models\Role::where('slug', 'technical_staff')->first()->id],
        'send_invitation' => true,
    ]);

    $response->assertSessionHas('invite');
    $response->assertSessionHas('success');

    $invite = session('invite');
    expect($invite['temporary_password'])->not->toBeEmpty();
    expect($invite['username'])->toBe('newjoiner');

    Mail::assertSent(UserInviteMail::class, function ($mail) use ($invite) {
        return $mail->hasTo('joiner@biiteek.test') && $mail->temporaryPassword === $invite['temporary_password'];
    });

    $created = User::where('username', 'newjoiner')->firstOrFail();
    expect($created->must_change_password)->toBeTrue();
    expect($created->is_active)->toBeTrue();

    // The temporary password signs the new employee in (and forces a change).
    auth()->logout();
    $this->post('/login', ['login' => 'newjoiner', 'password' => $invite['temporary_password']])
        ->assertRedirect('/profile?force_password=1');
});

it('can create an account without sending an invitation', function () {
    Mail::fake();

    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');

    $this->actingAs($admin)->post('/users', [
        'username' => 'manualuser',
        'name' => 'Manual User',
        'email' => 'manual@biiteek.test',
        'employee_id' => $staff->employee_id,
        'password' => 'SturdyPass123!',
        'password_confirmation' => 'SturdyPass123!',
        'roles' => [\App\Models\Role::where('slug', 'technical_staff')->first()->id],
        'send_invitation' => false,
    ])->assertSessionHas('success');

    // No invitation email with a temporary password is issued,
    // only the standard account-created notice.
    Mail::assertNotSent(UserInviteMail::class);

    $created = User::where('username', 'manualuser')->firstOrFail();
    $this->post('/login', ['login' => 'manualuser', 'password' => 'SturdyPass123!'])
        ->assertRedirect('/dashboard');
});

it('saves changes from the manage user dialog', function () {
    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');
    $leadRole = \App\Models\Role::where('slug', 'technical_lead')->first();

    $response = $this->actingAs($admin)->put("/users/{$staff->id}", [
        'name' => 'Updated Name',
        'email' => $staff->email,
        'roles' => [$leadRole->id],
        'is_active' => true,
        'reset_password' => '',
    ]);

    $response->assertSessionHasNoErrors();

    $staff->refresh();
    expect($staff->name)->toBe('Updated Name');
    expect($staff->roles->pluck('slug')->all())->toBe(['technical_lead']);
});

it('reflects name and email changes on the linked employee profile', function () {
    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');

    $this->actingAs($admin)->put("/users/{$staff->id}", [
        'name' => 'Esther Wanjiku',
        'email' => 'esther@biiteek.test',
        'roles' => [$staff->roles->first()->id],
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $staff->refresh();
    expect($staff->name)->toBe('Esther Wanjiku');

    // The employee profile (what the interface displays) is updated too.
    $staff->employee->refresh();
    expect($staff->employee->first_name)->toBe('Esther');
    expect($staff->employee->last_name)->toBe('Wanjiku');
    expect($staff->employee->full_name)->toBe('Esther Wanjiku');
    expect($staff->employee->email)->toBe('esther@biiteek.test');
});

it('suspends and renews an account from the manage dialog', function () {
    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');
    makeRequisition($staff);

    // Suspend
    $this->actingAs($admin)->post("/users/{$staff->id}/suspend")->assertSessionHas('success');

    $staff->refresh();
    expect($staff->is_active)->toBeFalse();
    expect($staff->deactivated_at)->not->toBeNull();

    // Suspended users cannot sign in.
    auth()->logout();
    $this->post('/login', ['login' => $staff->username, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    // Renew
    $this->actingAs($admin)->post("/users/{$staff->id}/renew")->assertSessionHas('success');

    $staff->refresh();
    expect($staff->is_active)->toBeTrue();
    expect($staff->deactivated_at)->toBeNull();

    // Historical records were preserved throughout.
    expect(\App\Models\Requisition::where('requester_user_id', $staff->id)->count())->toBeGreaterThan(0);
    expect(\App\Models\AuditLog::where('action', 'user.suspended')->count())->toBe(1);
    expect(\App\Models\AuditLog::where('action', 'user.renewed')->count())->toBe(1);
});

it('shows a validation error for a too-short reset password', function () {
    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');

    $this->actingAs($admin)->put("/users/{$staff->id}", [
        'name' => 'Richard Ogwal',
        'email' => 'richardogwal97@gmail.com',
        'roles' => [$staff->roles->first()->id],
        'is_active' => true,
        'reset_password' => 'zxcvbnm',
    ])->assertSessionHasErrors('reset_password');

    // The account is unchanged when validation fails.
    $staff->refresh();
    expect($staff->name)->not->toBe('Richard Ogwal');
});

it('updates the hr account with a valid email and password', function () {
    $admin = makeUser('system_admin');
    $hr = makeUser('hr');

    $this->actingAs($admin)->put("/users/{$hr->id}", [
        'name' => 'Richard Ogwal',
        'email' => 'richardogwal97@gmail.com',
        'roles' => [$hr->roles->first()->id],
        'is_active' => true,
        'reset_password' => 'zxcvbnm1',
    ])->assertSessionHasNoErrors();

    $hr->refresh();
    expect($hr->email)->toBe('richardogwal97@gmail.com');
    expect($hr->must_change_password)->toBeTrue();

    auth()->logout();
    $this->post('/login', ['login' => $hr->username, 'password' => 'zxcvbnm1'])
        ->assertRedirect('/profile?force_password=1');
});

it('lets an admin email a password reset link to a user', function () {
    Notification::fake();

    $admin = makeUser('system_admin');
    $hr = makeUser('hr');

    $this->actingAs($admin)->post("/users/{$hr->id}/reset-link")->assertSessionHas('success');

    Notification::assertSentTo($hr, \App\Notifications\ResetPasswordNotification::class);

    expect(\App\Models\AuditLog::where('action', 'user.reset_link_sent')->where('entity_id', $hr->id)->count())->toBe(1);
});

it('sends a branded password reset email naming the user', function () {
    Notification::fake();

    $admin = makeUser('system_admin');
    $hr = makeUser('hr');
    $hr->employee->update(['first_name' => 'Richard', 'last_name' => 'Ogwal']);

    $this->actingAs($admin)->post("/users/{$hr->id}/reset-link");

    Notification::assertSentTo($hr, \App\Notifications\ResetPasswordNotification::class, function ($notification) use ($hr) {
        $html = $notification->toMail($hr)->render();

        return str_contains($html, 'Hello Richard Ogwal')
            && str_contains($html, 'Reset my password')
            && str_contains($html, 'Biiteek');
    });
});

it('blocks non-administrators from sending reset links', function () {
    Notification::fake();

    $lead = makeUser('technical_lead');
    $hr = makeUser('hr');

    $this->actingAs($lead)->post("/users/{$hr->id}/reset-link")->assertForbidden();

    Notification::assertNothingSent();
});

it('sends the forgot-password email from the login page flow', function () {
    Notification::fake();

    $staff = makeUser('technical_staff');

    $this->post('/forgot-password', ['email' => $staff->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($staff, \App\Notifications\ResetPasswordNotification::class);
});
