<?php

use App\Models\Event;

test('it rejects requests without internal api key', function () {
    $this->withHeaders(['X-Internal-Api-Key' => '']);

    $this->getJson('/api/v1/events')
        ->assertUnauthorized()
        ->assertJsonPath('error', 'unauthorized');
});

test('it rejects requests with invalid internal api key', function () {
    $this->withHeader('X-Internal-Api-Key', 'wrong-key');

    $this->getJson('/api/v1/events')
        ->assertUnauthorized()
        ->assertJsonPath('error', 'unauthorized');
});

test('it allows requests with valid internal api key', function () {
    Event::factory()->create();

    $this->getJson('/api/v1/events')
        ->assertOk();
});

test('it skips ip allowlist when allowed ips are empty', function () {
    config(['services.internal.allowed_ips' => []]);

    Event::factory()->create();

    $this->getJson('/api/v1/events')->assertOk();
});

test('it rejects requests from ips outside the allowlist', function () {
    config(['services.internal.allowed_ips' => ['203.0.113.10']]);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->getJson('/api/v1/events')
        ->assertForbidden()
        ->assertJsonPath('error', 'forbidden');
});

test('it allows requests from an exact allowed ip', function () {
    config(['services.internal.allowed_ips' => ['127.0.0.1', '203.0.113.10']]);

    Event::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->getJson('/api/v1/events')
        ->assertOk();
});

test('it allows requests from an ip inside an allowed cidr', function () {
    config(['services.internal.allowed_ips' => ['198.51.100.0/24']]);

    Event::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
        ->getJson('/api/v1/events')
        ->assertOk();
});
