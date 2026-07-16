<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;

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
        $event->delete();

        return response()->noContent();
    }
}
