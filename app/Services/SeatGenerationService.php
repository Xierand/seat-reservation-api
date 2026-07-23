<?php

namespace App\Services;

use App\Enums\SeatLabelSequence;
use App\Enums\SeatStatus;
use App\Enums\SectorType;
use App\Exceptions\SeatGenerationConflictException;
use App\Models\Event;
use App\Models\Seat;
use App\Models\Sector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SeatGenerationService
{
    public const int MAX_SEATS_PER_REQUEST = 5000;

    public function __construct(
        private readonly SeatLabelGenerator $labelGenerator,
    ) {}

    /**
     * array{
     *     base_price: float,
     *     capacity?: int,
     *     row?: array{prefix?: string|null, name?: string|null, suffix?: string|null, count: int},
     *     number?: array{prefix?: string|null, name?: string|null, suffix?: string|null, count: int}
     * }
     */
    public function generate(Event $event, Sector $sector, array $data): Collection
    {
        $this->assertEventMatchesSector($event, $sector);

        if (isset($data['capacity'])) {
            return $this->generateStanding($sector, (int) $data['capacity'], $data['base_price']);
        }

        return $this->generateGrid($sector, $data);
    }

    private function generateStanding(Sector $sector, int $capacity, float|string|int $basePrice): Collection
    {
        if (! in_array($sector->type, [SectorType::STANDING, SectorType::MIXED], true)) {
            throw new InvalidArgumentException('Standing generation is only allowed for standing and mixed sectors.');
        }

        if ($capacity < 1 || $capacity > self::MAX_SEATS_PER_REQUEST) {
            throw new InvalidArgumentException('Capacity is out of allowed range.');
        }

        $now = now();
        $rows = [];

        for ($i = 0; $i < $capacity; $i++) {
            $rows[] = [
                'event_id' => $sector->event_id,
                'sector_id' => $sector->id,
                'row' => null,
                'number' => null,
                'status' => SeatStatus::FREE->value,
                'base_price' => $basePrice,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $this->insertSeats($sector, $rows);
    }

    /**
     * @param  array{
     *     base_price: float|string|int,
     *     row: array{prefix?: string|null, name?: string|null, suffix?: string|null, count: int},
     *     number: array{prefix?: string|null, name?: string|null, suffix?: string|null, count: int}
     * }  $data
     * @return Collection<int, Seat>
     */
    private function generateGrid(Sector $sector, array $data): Collection
    {
        if (! in_array($sector->type, [SectorType::SEATED, SectorType::MIXED], true)) {
            throw new InvalidArgumentException('Grid generation is only allowed for seated and mixed sectors.');
        }

        $rowCount = (int) $data['row']['count'];
        $numberCount = (int) $data['number']['count'];
        $total = $rowCount * $numberCount;

        if ($total < 1 || $total > self::MAX_SEATS_PER_REQUEST) {
            throw new InvalidArgumentException('Generated seat count is out of allowed range.');
        }

        $rowLabels = $this->labelGenerator->labels(
            $this->sequence($data['row']['prefix'] ?? null),
            $data['row']['name'] ?? null,
            $this->sequence($data['row']['suffix'] ?? null),
            $rowCount,
        );

        $numberLabels = $this->labelGenerator->labels(
            $this->sequence($data['number']['prefix'] ?? null),
            $data['number']['name'] ?? null,
            $this->sequence($data['number']['suffix'] ?? null),
            $numberCount,
        );

        $now = now();
        $rows = [];

        foreach ($rowLabels as $rowLabel) {
            foreach ($numberLabels as $numberLabel) {
                $rows[] = [
                    'event_id' => $sector->event_id,
                    'sector_id' => $sector->id,
                    'row' => $rowLabel,
                    'number' => $numberLabel,
                    'status' => SeatStatus::FREE->value,
                    'base_price' => $data['base_price'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $this->insertSeats($sector, $rows);
    }

    private function assertEventMatchesSector(Event $event, Sector $sector): void
    {
        if ($event->id !== $sector->event_id) {
            throw new InvalidArgumentException('Sector does not belong to the given event.');
        }
    }

    private function insertSeats(Sector $sector, array $rows): Collection
    {
        try {
            return DB::transaction(function () use ($sector, $rows) {
                $previousMaxId = (int) Seat::query()
                    ->where('sector_id', $sector->id)
                    ->max('id');

                Seat::query()->insert($rows);

                return Seat::query()
                    ->where('sector_id', $sector->id)
                    ->where('id', '>', $previousMaxId)
                    ->orderBy('id')
                    ->get();
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateSeatError($e)) {
                throw new SeatGenerationConflictException;
            }

            throw $e;
        }
    }

    private function sequence(mixed $value): ?SeatLabelSequence
    {
        if ($value === null || $value === '') {
            return null;
        }

        return SeatLabelSequence::from((string) $value);
    }

    private function isDuplicateSeatError(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'uq_sector_row_number')
            || str_contains($message, 'duplicate key value')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint')) {
            return true;
        }

        return $e->getCode() === '23505';
    }
}
