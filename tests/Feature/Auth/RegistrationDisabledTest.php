<?php

test('registration route is disabled', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('registration submission route is disabled', function () {
    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => 'new-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
});
