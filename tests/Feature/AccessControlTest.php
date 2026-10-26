<?php

it('rejects unauthenticated access to protected pages', function (string $uri) {
    $this->get($uri)->assertRedirect('/login');
})->with([
    '/dashboard',
    '/employees',
    '/requisitions',
    '/approvals',
    '/finance',
    '/disbursements',
    '/receipts',
    '/accountability',
    '/contracts',
    '/users',
    '/audit',
    '/settings',
    '/reports',
]);

it('blocks system administrators from financial and hr modules by default', function (string $uri) {
    $admin = makeUser('system_admin');

    $this->actingAs($admin)->get($uri)->assertForbidden();
})->with([
    '/requisitions',
    '/approvals',
    '/finance',
    '/disbursements',
    '/employees',
    '/contracts',
    '/reports',
]);

it('allows system administrators to manage users and settings and view audit logs', function () {
    $admin = makeUser('system_admin');

    $this->actingAs($admin)->get('/users')->assertOk();
    $this->actingAs($admin)->get('/settings')->assertOk();
    $this->actingAs($admin)->get('/audit')->assertOk();
});

it('blocks technical staff from approval and finance modules', function (string $uri) {
    $staff = makeUser('technical_staff');

    $this->actingAs($staff)->get($uri)->assertForbidden();
})->with([
    '/approvals',
    '/finance',
    '/disbursements',
    '/users',
    '/audit',
    '/settings',
]);

it('rejects unauthorised direct api actions', function () {
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff);

    // A staff member cannot approve, and cannot pay.
    $this->actingAs($staff)->post("/requisitions/{$req->id}/approve")->assertForbidden();
    $this->actingAs($staff)->post("/requisitions/{$req->id}/payments", [
        'amount' => 100, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash', 'payee_name' => 'X',
    ])->assertForbidden();
});

it('allows an employee to create and track only permitted requisitions', function () {
    $staff = makeUser('technical_staff');
    $other = makeUser('technical_staff');

    $mine = makeRequisition($staff);
    $theirs = makeRequisition($other);

    $this->actingAs($staff)->get('/requisitions')->assertOk();

    $response = $this->actingAs($staff)->get("/requisitions/{$mine->id}");
    $response->assertOk();

    // Other employees' requisitions are not listed for a plain staff member.
    $response = $this->actingAs($staff)->get('/requisitions');
    $response->assertInertia(fn ($page) => $page
        ->component('Requisitions/Index')
        ->has('requisitions.data', 1)
        ->where('requisitions.data.0.id', $mine->id)
    );
});

it('deactivates accounts without deleting historical records', function () {
    $admin = makeUser('system_admin');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff);

    $this->actingAs($admin)->put("/users/{$staff->id}", [
        'is_active' => false,
    ])->assertRedirect();

    $staff->refresh();
    expect($staff->is_active)->toBeFalse();
    expect($staff->deactivated_at)->not->toBeNull();
    expect(\App\Models\Requisition::find($req->id))->not->toBeNull();

    // A deactivated account cannot sign in.
    auth()->logout();
    $this->post('/login', ['login' => $staff->username, 'password' => 'password'])->assertSessionHasErrors('login');
});
