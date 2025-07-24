<?php

namespace App\Http\Controllers\Peoplecount;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peoplecount\EventCreateRequest;
use App\Http\Requests\Peoplecount\EventDestroyRequest;
use App\Http\Requests\Peoplecount\EventEditRequest;
use App\Http\Requests\Peoplecount\EventIndexRequest;
use App\Http\Requests\Peoplecount\EventShowRequest;
use App\Http\Requests\Peoplecount\EventStoreRequest;
use App\Http\Requests\Peoplecount\EventUpdateRequest;
use App\Models\Organization;
use App\Models\Peoplecount\Event;
use App\Services\Peoplecount\EventService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(EventIndexRequest $request, Organization $organization): Response
    {
        return Inertia::render('peoplecount/Events', [
            'events' => $this->eventService->getEvents(),
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EventStoreRequest $request, Organization $organization): RedirectResponse
    {
        $event = $this->eventService->create([
            'name' => $request->input('name'),
            'organization_id' => $organization->id,
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
        ]);

        return redirect()->route('peoplecount.events.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Event created successfully ('.$event->name.').');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(EventCreateRequest $request, Organization $organization): Response
    {
        return Inertia::render('peoplecount/NewEvent', [
            'organization' => $organization,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  EventShowRequest  $request  Required for Authorization
     */
    public function show(EventShowRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        return redirect()->route('peoplecount.events.edit', [
            'organization' => $organization,
            'event' => $event,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EventEditRequest $request, Organization $organization, Event $event): Response
    {
        return Inertia::render('peoplecount/EditEvent', [
            'organization' => $organization,
            'event' => $event,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  EventUpdateRequest  $request  Required for Authorization
     */
    public function update(EventUpdateRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $event = $this->eventService->update($event, [
            'name' => $request->input('name'),
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
        ]);

        return redirect()->route('peoplecount.events.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Event '.$event->name.' updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  EventDestroyRequest  $request  Required for Authorization
     */
    public function destroy(EventDestroyRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $name = $event->name;
        $event->delete();

        return redirect()->route('peoplecount.events.index', [
            'organization' => $organization,
        ])
            ->with('status', 'Event '.$name.' deleted successfully.');
    }
}
