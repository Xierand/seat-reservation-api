<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateSeatsRequest;
use App\Http\Requests\StoreSeatRequest;
use App\Http\Requests\UpdateSeatRequest;
use App\Http\Resources\SeatResource;
use App\Models\Event;
use App\Models\Seat;
use App\Models\Sector;
use App\Services\SeatGenerationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SeatController extends Controller
{
    public function index(Event $event, Sector $sector)
    {
        return SeatResource::collection($sector->seats()->paginate());
    }

    public function store(StoreSeatRequest $request, Event $event, Sector $sector)
    {
        try {
            $seat = $sector->seats()->create([
                ...$request->safe()->only(['row', 'number', 'status', 'base_price']),
                'event_id' => $sector->event_id,
            ]);
        } catch (QueryException $e) {
            $this->throwIfDuplicateSeat($e);

            throw $e;
        }

        return (new SeatResource($seat))
            ->response()
            ->setStatusCode(201);
    }

    public function generate(
        GenerateSeatsRequest $request,
        Event $event,
        Sector $sector,
        SeatGenerationService $generationService,
    ): JsonResponse {
        $seats = $generationService->generate($event, $sector, $request->validated());

        return SeatResource::collection($seats)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Event $event, Sector $sector, Seat $seat)
    {
        return new SeatResource($seat);
    }

    public function update(UpdateSeatRequest $request, Event $event, Sector $sector, Seat $seat)
    {
        try {
            $seat->update($request->safe()->only(['row', 'number', 'status', 'base_price']));
        } catch (QueryException $e) {
            $this->throwIfDuplicateSeat($e);

            throw $e;
        }

        return new SeatResource($seat);
    }

    public function destroy(Event $event, Sector $sector, Seat $seat)
    {
        try {
            $seat->delete();
        } catch (QueryException $e) {
            if ($this->isForeignKeyRestrictError($e)) {
                return response()->json([
                    'error' => 'seat_has_reservations',
                    'message' => 'Cannot delete seat with existing reservations.',
                ], 409);
            }

            throw $e;
        }

        return response()->noContent();
    }

    private function throwIfDuplicateSeat(QueryException $e): void
    {
        if (! $this->isDuplicateSeatError($e)) {
            return;
        }

        throw ValidationException::withMessages([
            'row' => 'A seat with this row and number already exists in this sector.',
        ]);
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

    private function isForeignKeyRestrictError(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'foreign key constraint')
            || str_contains($message, 'integrity constraint violation')
            || str_contains($message, 'cannot delete or update a parent row')) {
            return true;
        }

        return in_array($e->getCode(), ['23000', '23503'], true)
            || (int) ($e->errorInfo[1] ?? 0) === 1451;
    }
}
