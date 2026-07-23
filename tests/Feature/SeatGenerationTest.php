<?php

use App\Enums\SectorType;
use App\Models\Event;
use App\Models\Seat;
use App\Models\Sector;

test('it generates standing seats by capacity', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::STANDING,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'capacity' => 3,
        'base_price' => 50,
    ])->assertCreated()
        ->assertJsonCount(3, 'data');

    expect(Seat::query()->where('sector_id', $sector->id)->count())->toBe(3);
    expect(Seat::query()->where('sector_id', $sector->id)->whereNull('row')->whereNull('number')->count())->toBe(3);
});

test('it generates a seated grid with alphabet rows and number seats', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'base_price' => 100,
        'row' => [
            'prefix' => 'ALPHABET',
            'count' => 2,
        ],
        'number' => [
            'suffix' => 'NUMBER',
            'count' => 3,
        ],
    ])->assertCreated()
        ->assertJsonCount(6, 'data');

    $this->assertDatabaseHas('seats', [
        'sector_id' => $sector->id,
        'row' => 'A',
        'number' => '1',
    ]);
    $this->assertDatabaseHas('seats', [
        'sector_id' => $sector->id,
        'row' => 'B',
        'number' => '3',
    ]);
});

test('it generates alphabet labels beyond Z as AA', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'base_price' => 100,
        'row' => [
            'prefix' => 'ALPHABET',
            'count' => 27,
        ],
        'number' => [
            'suffix' => 'NUMBER',
            'count' => 1,
        ],
    ])->assertCreated();

    $this->assertDatabaseHas('seats', [
        'sector_id' => $sector->id,
        'row' => 'Z',
        'number' => '1',
    ]);
    $this->assertDatabaseHas('seats', [
        'sector_id' => $sector->id,
        'row' => 'AA',
        'number' => '1',
    ]);
});

test('it generates roman labels', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'base_price' => 100,
        'row' => [
            'prefix' => 'ROMAN',
            'count' => 4,
        ],
        'number' => [
            'suffix' => 'NUMBER',
            'count' => 1,
        ],
    ])->assertCreated()
        ->assertJsonCount(4, 'data');

    $this->assertDatabaseHas('seats', ['sector_id' => $sector->id, 'row' => 'I', 'number' => '1']);
    $this->assertDatabaseHas('seats', ['sector_id' => $sector->id, 'row' => 'II', 'number' => '1']);
    $this->assertDatabaseHas('seats', ['sector_id' => $sector->id, 'row' => 'IV', 'number' => '1']);
});

test('it generates standing seats for mixed sectors by capacity', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'capacity' => 2,
        'base_price' => 40,
    ])->assertCreated()
        ->assertJsonCount(2, 'data');
});

test('it generates a grid for mixed sectors', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'base_price' => 80,
        'row' => [
            'prefix' => 'ALPHABET',
            'name' => '-',
            'count' => 1,
        ],
        'number' => [
            'suffix' => 'NUMBER',
            'count' => 2,
        ],
    ])->assertCreated()
        ->assertJsonCount(2, 'data');

    $this->assertDatabaseHas('seats', [
        'sector_id' => $sector->id,
        'row' => 'A-',
        'number' => '1',
    ]);
});

test('it rejects capacity generation for seated sectors', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'capacity' => 5,
        'base_price' => 100,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['capacity']);
});

test('it rejects grid generation for standing sectors', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::STANDING,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'base_price' => 100,
        'row' => [
            'prefix' => 'ALPHABET',
            'count' => 2,
        ],
        'number' => [
            'suffix' => 'NUMBER',
            'count' => 2,
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['row']);
});

test('it rejects generation that conflicts with existing seats', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
        'row' => 'A',
        'number' => '1',
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/generate", [
        'base_price' => 100,
        'row' => [
            'prefix' => 'ALPHABET',
            'count' => 1,
        ],
        'number' => [
            'suffix' => 'NUMBER',
            'count' => 1,
        ],
    ])->assertStatus(409)
        ->assertJsonPath('error', 'seat_generation_conflict');
});
