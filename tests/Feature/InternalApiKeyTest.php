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
