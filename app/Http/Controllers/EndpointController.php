<?php

namespace App\Http\Controllers;

use App\Models\Endpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EndpointController extends Controller
{
    public function index(Request $request): View
    {
        $endpoints = $request->user()
            ->endpoints()
            ->withCount('events')
            ->latest()
            ->get();

        return view('dashboard', [
            'endpoints' => $endpoints,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'forward_to_url' => ['nullable', 'url:http,https', 'max:2000'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $endpoint = $request->user()->endpoints()->create($validated);

        return redirect()
            ->route('endpoints.show', $endpoint)
            ->with('status', 'endpoint-created');
    }

    public function show(Request $request, Endpoint $endpoint): View
    {
        Gate::authorize('view', $endpoint);

        $events = $endpoint->events()
            ->with('deliveries')
            ->limit(50)
            ->get();

        return view('endpoints.show', [
            'endpoint' => $endpoint,
            'events' => $events,
        ]);
    }

    public function update(Request $request, Endpoint $endpoint): RedirectResponse
    {
        Gate::authorize('update', $endpoint);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'forward_to_url' => ['nullable', 'url:http,https', 'max:2000'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $endpoint->fill($validated)->save();

        return back()->with('status', 'endpoint-updated');
    }

    public function destroy(Request $request, Endpoint $endpoint): RedirectResponse
    {
        Gate::authorize('delete', $endpoint);

        $endpoint->delete(); // events + deliveries cascade via FK

        return redirect()
            ->route('dashboard')
            ->with('status', 'endpoint-deleted');
    }
}
