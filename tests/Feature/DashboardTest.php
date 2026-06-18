<?php

use Watchtower\Watchtower;

it('blocks dashboard access when the gate denies', function () {
    // Default gate allows only "local"; Testbench runs as "testing".
    $this->get('watchtower')->assertForbidden();
});

it('renders the SPA shell when authorized', function () {
    Watchtower::auth(fn () => true);

    $response = $this->get('watchtower');

    $response->assertOk();
    $response->assertSee('watchtower-app', false);
    $response->assertSee('window.Watchtower', false);
});

it('exposes the resolved config to the SPA', function () {
    Watchtower::auth(fn () => true);

    $this->get('watchtower')
        ->assertOk()
        ->assertSee('"pollingInterval":5000', false);
});

afterEach(function () {
    Watchtower::$authUsing = null;
});
