<?php

use App\Enums\SectorType;
use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Seat;
use App\Models\Sector;

test('it rejects duplicate row and number within the same sector', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats", [
        'row' => 'A',
        'number' => '1',
        'base_price' => 100,
    ])->assertCreated();

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats", [
        'row' => 'A',
        'number' => '1',
        'base_price' => 100,
    ])->assertUnprocessable();

    $this->assertDatabaseCount('seats', 1);
});

test('it rejects duplicate row and number after update', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats", [
        'row' => 'A',
        'number' => '1',
        'base_price' => 100,
    ])->assertCreated();

    $secondSeat = $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats", [
        'row' => 'A',
        'number' => '2',
        'base_price' => 100,
    ]);

    $secondSeat->assertCreated();

    $seatId = $secondSeat->json('data.id');

    $this->patchJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/{$seatId}", [
        'row' => 'A',
        'number' => '1',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['row']);
});

test('it requires row and number for seated and mixed sectors', function () {
    $event = Event::factory()->create();
    $sectorSeated = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $sectorMixed = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);

    $sectorStanding = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::STANDING,
    ]);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sectorSeated->id}/seats", [
        'base_price' => 100,
        'row' => 'A',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['number']);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sectorMixed->id}/seats", [
        'base_price' => 100,
        'number' => '1',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['row']);

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sectorStanding->id}/seats", [
        'base_price' => 50,
    ])->assertCreated();

    $this->postJson("/api/v1/events/{$event->id}/sectors/{$sectorStanding->id}/seats")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_price']);
});

test('it rejects clearing row or number on seated and mixed seat update', function () {
    $event = Event::factory()->create();
    $sectorSeated = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);

    $seat = $this->postJson("/api/v1/events/{$event->id}/sectors/{$sectorSeated->id}/seats", [
        'row' => 'A',
        'number' => '1',
        'base_price' => 100,
    ]);

    $seat->assertCreated();

    $seatId = $seat->json('data.id');

    $this->patchJson("/api/v1/events/{$event->id}/sectors/{$sectorSeated->id}/seats/{$seatId}", [
        'row' => null,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['row']);

    $this->assertDatabaseHas('seats', [
        'id' => $seatId,
        'row' => 'A',
    ]);
});

test('it strips row and number when creating or updating a standing-sector seat', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::STANDING,
    ]);

    $seat = $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats", [
        'row' => 'A',
        'number' => '1',
        'base_price' => 50,
    ]);

    $seat->assertCreated();

    $seatId = $seat->json('data.id');

    $this->assertDatabaseHas('seats', [
        'id' => $seatId,
        'row' => null,
        'number' => null,
        'base_price' => 50,
    ]);

    $this->patchJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/{$seatId}", [
        'row' => 'A',
        'number' => '1',
    ])->assertOk();

    $this->assertDatabaseHas('seats', [
        'id' => $seatId,
        'row' => null,
        'number' => null,
    ]);
});

test('it returns 404 when accessing a seat through the wrong event/sector', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);

    $sectorB = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::MIXED,
    ]);

    $seat = $this->postJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats", [
        'row' => 'A',
        'number' => '1',
        'base_price' => 100,
    ]);

    $seat->assertCreated();

    $seatId = $seat->json('data.id');

    $this->getJson("/api/v1/events/{$event->id}/sectors/{$sectorB->id}/seats/{$seatId}")
        ->assertNotFound();
    $this->patchJson("/api/v1/events/{$event->id}/sectors/{$sectorB->id}/seats/{$seatId}", [
        'row' => 'B',
    ])->assertNotFound();
    $this->deleteJson("/api/v1/events/{$event->id}/sectors/{$sectorB->id}/seats/{$seatId}")
        ->assertNotFound();

    $this->assertDatabaseHas('seats', [
        'id' => $seatId,
        'row' => 'A',
        'number' => '1',
        'base_price' => '100',
        'event_id' => $event->id,
        'sector_id' => $sector->id,
    ]);
});

test('it deletes seats when a sector is deleted', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
    ]);

    Seat::factory()->count(3)->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
    ]);

    $this->deleteJson("/api/v1/events/{$event->id}/sectors/{$sector->id}")
        ->assertNoContent();

    $this->assertDatabaseCount('seats', 0);
});

test('it deletes a seat without reservations', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);
    $seat = Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
    ]);

    $this->deleteJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/{$seat->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('seats', ['id' => $seat->id]);
});

test('it rejects deleting a seat with existing reservations', function () {
    $event = Event::factory()->create();
    $sector = Sector::factory()->create([
        'event_id' => $event->id,
        'type' => SectorType::SEATED,
    ]);
    $seat = Seat::factory()->create([
        'event_id' => $event->id,
        'sector_id' => $sector->id,
    ]);
    $order = Order::factory()->create(['event_id' => $event->id]);
    Reservation::factory()->create([
        'order_id' => $order->id,
        'seat_id' => $seat->id,
    ]);

    $this->deleteJson("/api/v1/events/{$event->id}/sectors/{$sector->id}/seats/{$seat->id}")
        ->assertStatus(409)
        ->assertJsonPath('error', 'seat_has_reservations')
        ->assertJsonPath('message', 'Cannot delete seat with existing reservations.');

    $this->assertDatabaseHas('seats', ['id' => $seat->id]);
});
