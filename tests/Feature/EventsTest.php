<?php

use App\Models\Event;

test('it creates an event', function () {
    $response = $this->postJson('/api/v1/events', [
        'name' => 'Test Event',
        'status' => 'draft',
        'active_since' => '2026-07-16 12:00:00',
        'currency' => 'USD',
        'event_date' => '2026-07-16 16:00:00',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test Event')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'description',
                'status',
                'active_since',
                'currency',
                'event_date',
                'created_at',
                'updated_at',
            ],
        ]);

    $this->assertDatabaseHas('events', [
        'name' => 'Test Event',
        'status' => 'draft',
        'currency' => 'USD',
    ]);
});

test('it rejects invalid create payloads', function (array $payload, array $errors) {
    $response = $this->postJson('/api/v1/events', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing name' => [
        [
            'status' => 'draft',
            'active_since' => '2026-07-16 12:00:00',
            'currency' => 'USD',
            'event_date' => '2026-07-16 16:00:00',
        ],
        ['name'],
    ],
    'event_date before active_since' => [
        [
            'name' => 'Test Event',
            'status' => 'draft',
            'active_since' => '2026-07-16 16:01:00',
            'currency' => 'USD',
            'event_date' => '2026-07-16 16:00:00',
        ],
        ['event_date'],
    ],
    'invalid status' => [
        [
            'name' => 'Test Event',
            'status' => 'test',
            'active_since' => '2026-07-16 12:00:00',
            'currency' => 'USD',
            'event_date' => '2026-07-16 16:00:00',
        ],
        ['status'],
    ],
    'invalid currency' => [
        [
            'name' => 'Test Event',
            'status' => 'draft',
            'active_since' => '2026-07-16 12:00:00',
            'currency' => 'XPF',
            'event_date' => '2026-07-16 16:00:00',
        ],
        ['currency'],
    ],
]);

test('it lists events with pagination', function () {
    Event::factory()->count(3)->create();

    $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('it shows an event', function () {
    $event = Event::factory()->create([
        'name' => 'Concert',
        'currency' => 'EUR',
    ]);

    $this->getJson("/api/v1/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $event->id)
        ->assertJsonPath('data.name', 'Concert')
        ->assertJsonPath('data.currency', 'EUR');
});

test('it returns 404 for a missing event', function () {
    $this->getJson('/api/v1/events/999999')->assertNotFound();
});

test('it updates an event', function () {
    $event = Event::factory()->create([
        'name' => 'Old Name',
        'currency' => 'EUR',
    ]);

    $this->patchJson("/api/v1/events/{$event->id}", [
        'name' => 'Updated Name',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.currency', 'EUR');

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'name' => 'Updated Name',
        'currency' => 'EUR',
    ]);
});

test('it rejects event_date before active_since on update', function () {
    $event = Event::factory()->create([
        'active_since' => '2026-07-16 12:00:00',
        'event_date' => '2026-07-20 12:00:00',
    ]);

    $this->patchJson("/api/v1/events/{$event->id}", [
        'event_date' => '2026-07-10 12:00:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['event_date']);
});

test('it rejects empty name on update', function () {
    $event = Event::factory()->create();

    $this->patchJson("/api/v1/events/{$event->id}", [
        'name' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('it deletes an event', function () {
    $event = Event::factory()->create();

    $this->deleteJson("/api/v1/events/{$event->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
    $this->getJson("/api/v1/events/{$event->id}")->assertNotFound();
});
