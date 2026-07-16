<?php

use App\Enums\SectorType;
use App\Models\Event;
use App\Models\Sector;

test('it creates a sector for an event', function () {
    $event = Event::factory()->create();

    $this->postJson("/api/v1/events/{$event->id}/sectors", [
        'name' => 'Sector A',
        'type' => SectorType::SEATED,
        'description' => 'This is a test sector'
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Sector A')
        ->assertJsonPath('data.type', 'seated')
        ->assertJsonPath('data.description', 'This is a test sector')
        ->assertJsonStructure([
            'data' => [
                'id',
                'event_id',
                'name',
                'description',
                'type',
                'created_at',
                'updated_at',
            ]
        ]);

    $this->assertDatabaseHas('sectors', [
        'event_id' => $event->id,
        'name' => 'Sector A',
        'description' => 'This is a test sector',
        'type' => 'seated',
    ]);
});

test('it rejects create when name is missing', function () {
    $event = Event::factory()->create();

    $this->postJson("/api/v1/events/{$event->id}/sectors", [
        'type' => SectorType::SEATED
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('it rejects create when type is invalid', function () {
    $event = Event::factory()->create();

    $this->postJson("/api/v1/events/{$event->id}/sectors", [
        'name' => 'Test Sector',
        'type' => 'test'
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('it rejects create when type is missing', function () {
    $event = Event::factory()->create();

    $this->postJson("/api/v1/events/{$event->id}/sectors", [
        'name' => 'Test Sector'
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('it lists sectors for an event with pagination', function () {
    $event = Event::factory()->create();
    Sector::factory()->count(3)->create(['event_id' => $event->id]);

    $this->getJson("/api/v1/events/{$event->id}/sectors")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('it lists only sectors belonging to the given event', function () {
    $event1 = Event::factory()->create();
    $event2 = Event::factory()->create();

    Sector::factory()->create(['event_id' => $event1->id]);
    Sector::factory()->create(['event_id' => $event1->id]);
    Sector::factory()->create(['event_id' => $event2->id]);

    $this->getJson("/api/v1/events/{$event1->id}/sectors")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/v1/events/{$event2->id}/sectors")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('it shows a sector', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create(['event_id' => $event->id, 'name' => 'VIP', 'type' => 'standing']);

    $this->getJson("/api/v1/events/{$event->id}/sectors/{$sector->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $sector->id)
        ->assertJsonPath('data.name', 'VIP')
        ->assertJsonPath('data.type', 'standing')
        ->assertJsonPath('data.event_id', $event->id);
});

test('it returns 404 when sector does not belong to event', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    $sector = Sector::factory()->create(['event_id' => $eventA->id]);

    $this->getJson("/api/v1/events/{$eventB->id}/sectors/{$sector->id}")
        ->assertNotFound();
});

test('it returns 404 when sector does not exist', function () {
    $event = Event::factory()->create();

    $this->getJson("/api/v1/events/{$event->id}/sectors/999999")
        ->assertNotFound();
});

test('it updates a sector', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create(['event_id' => $event->id, 'name' => 'Old', 'type' => 'seated']);

    $this->patchJson("/api/v1/events/{$event->id}/sectors/{$sector->id}", [
        'name' => 'New',
    ])->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.type', 'seated');

    $this->assertDatabaseHas('sectors', [
        'id' => $sector->id,
        'name' => 'New',
        'type' => 'seated',
    ]);
});

test('it rejects empty name on update', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create(['event_id' => $event->id]);

    $this->patchJson("/api/v1/events/{$event->id}/sectors/{$sector->id}", ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test("it rejects invalid type on update", function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create(['event_id' => $event->id]);

    $this->patchJson("/api/v1/events/{$event->id}/sectors/{$sector->id}", ['type' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('it returns 404 when updating sector of another event', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    $sector = Sector::factory()->create(['event_id' => $eventA->id, 'name' => 'Original']);

    $this->patchJson("/api/v1/events/{$eventB->id}/sectors/{$sector->id}", [
        'name' => 'Updated',
    ])->assertNotFound();

    $this->assertDatabaseHas('sectors', [
        'id' => $sector->id,
        'name' => 'Original'
    ]);
});


test('it deletes a sector', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create(['event_id' => $event->id]);

    $this->deleteJson("/api/v1/events/{$event->id}/sectors/{$sector->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('sectors', [
        'id' => $sector->id
    ]);

    $this->getJson("/api/v1/events/{$event->id}/sectors/{$sector->id}")
        ->assertNotFound();
});

test("it returns 404 when deleting sector of antother event", function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    $sector = Sector::factory()->create(['event_id' => $eventA->id]);

    $this->deleteJson("/api/v1/events/{$eventB->id}/sectors/{$sector->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('sectors', [
        'id' => $sector->id
    ]);
});

test('it returns 404 when event does not exist', function () {
    $sector = Sector::factory()->create();

    $this->getJson("/api/v1/events/999999/sectors/{$sector->id}")
        ->assertNotFound();
});
