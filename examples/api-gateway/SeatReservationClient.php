<?php

namespace Examples\ApiGateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SeatReservationClient
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.seat_reservation.url'), '/');
        $this->apiKey = $apiKey ?? (string) config('services.seat_reservation.api_key');
    }

    public function listEvents(array $query = []): Response
    {
        return $this->client()->get('/api/v1/events', $query);
    }

    public function createEvent(array $payload): Response
    {
        return $this->client()->post('/api/v1/events', $payload);
    }

    public function listSectors(int $eventId, array $query = []): Response
    {
        return $this->client()->get("/api/v1/events/{$eventId}/sectors", $query);
    }

    public function createSector(int $eventId, array $payload): Response
    {
        return $this->client()->post("/api/v1/events/{$eventId}/sectors", $payload);
    }

    // etc...

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-Internal-Api-Key' => $this->apiKey,
            ])
            ->timeout(10);
    }
}
