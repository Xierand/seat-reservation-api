<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;

class EventController extends Controller
{
    public function index()
    {
        return EventResource::collection(Event::paginate());
    }

    public function store(StoreEventRequest $request)
    {
        $event = Event::create(
            $request->validated()
        );

        return new EventResource($event)->response()->setStatusCode(201);
    }

    public function show(Event $event)
    {
        return new EventResource($event);
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $event->update($request->validated());

        return new EventResource($event);
    }

    public function destroy(Event $event)
    {
        if ($event->orders()->whereHas('reservations')->exists()) {
            return response()->json([
                'error' => 'event_has_reservations',
                'message' => 'Cannot delete event with existing reservations. Set status to cancelled instead.',
            ], 409);
        }

        $event->delete();

        return response()->noContent();
    }
}
