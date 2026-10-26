<?php

it('serves the login page straight from the main url', function () {
    $this->get('/')->assertOk();
});

it('takes signed-in users from the main url to their dashboard', function () {
    $user = makeUser('technical_staff');

    $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
});
