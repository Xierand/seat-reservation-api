<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectorRequest;
use App\Http\Requests\UpdateSectorRequest;
use App\Http\Resources\SectorResource;
use App\Models\Event;
use App\Models\Sector;

class SectorController extends Controller
{
    public function index(Event $event)
    {
        return SectorResource::collection($event->sectors()->paginate());
    }

    public function store(StoreSectorRequest $request, Event $event)
    {
        $sector = $event->sectors()->create($request->validated());

        return (new SectorResource($sector))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Event $event, Sector $sector)
    {
        return new SectorResource($sector);
    }

    public function update(UpdateSectorRequest $request, Event $event, Sector $sector)
    {
        $sector->update($request->validated());

        return new SectorResource($sector);
    }

    public function destroy(Event $event, Sector $sector)
    {
        $sector->delete();

        return response()->noContent();
    }
}
