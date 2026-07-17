<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSeatRequest;
use App\Http\Requests\UpdateSeatRequest;
use App\Http\Resources\SeatResource;
use App\Models\Event;
use App\Models\Seat;
use App\Models\Sector;
use Illuminate\Database\QueryException;
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
                'event_id' => $event->id,
            ]);
        } catch (QueryException $e) {
            $this->throwIfDuplicateSeat($e);

            throw $e;
        }

        return (new SeatResource($seat))
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
        $seat->delete();

        return response()->noContent();
    }

    private function throwIfDuplicateSeat(QueryException $e): void
    {
        $message = strtolower($e->getMessage());

        $isDuplicate = str_contains($message, 'uq_sector_row_number')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed');

        if (! $isDuplicate) {
            return;
        }

        throw ValidationException::withMessages([
            'row' => 'A seat with this row and number already exists in this sector.',
        ]);
    }
}
