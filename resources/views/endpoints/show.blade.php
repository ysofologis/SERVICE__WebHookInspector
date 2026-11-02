<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $endpoint->name }}
                @if (! $endpoint->enabled)
                    <span class="ml-2 align-middle text-xs font-medium rounded-full bg-red-100 text-red-700 px-2 py-0.5">disabled</span>
                @endif
            </h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; All endpoints</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto sm:px-6 lg:px-8"
         x-data="endpointEvents({
             token: @js($endpoint->token),
             endpointId: @js($endpoint->id),
             initial: @js($events->map(fn ($e) => [
                 'id' => $e->id,
                 'method' => $e->method,
                 'path' => $e->path,
                 'content_type' => $e->content_type,
                 'payload_size' => $e->payload_size,
                 'received_at' => $e->created_at?->toISOString(),
                 'deliveries' => $e->deliveries->map(fn ($d) => [
                     'attempt' => $d->attempt,
                     'status_code' => $d->status_code,
                     'delivered' => $d->delivered,
                     'error' => $d->error,
                 ])->values()->all(),
             ])),
             urls: {
                 event: '{{ url("/endpoints/{$endpoint->id}/events") }}',
             },
         })">

        {{-- Flash --}}
        <template x-if="flash">
            <div class="mb-4 rounded-md bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm px-4 py-2"
                 x-text="flash"></div>
        </template>

        {{-- Capture URL --}}
        <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
            <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">Capture URL — point any sender at it</div>
            <div class="flex items-center gap-2">
                <code class="flex-1 text-sm bg-gray-50 rounded px-2 py-1 overflow-x-auto whitespace-nowrap">{{ $endpoint->captureUrl() }}</code>
                <button type="button"
                        class="text-xs px-2 py-1 rounded border border-gray-300 hover:bg-gray-50"
                        @click="navigator.clipboard.writeText($el.previousElementSibling.textContent.trim()); flashOnce('Copied.')">copy</button>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                try:
                <code class="bg-gray-50 rounded px-1">curl -X POST '{{ $endpoint->captureUrl() }}' -H 'Content-Type: application/json' -d '{"hello":"world"}'</code>
            </div>
        </div>

        {{-- Forward settings --}}
        <details class="bg-white shadow-sm rounded-lg p-4 mb-6">
            <summary class="cursor-pointer text-sm font-medium text-gray-700">
                Forwarding
                @if ($endpoint->forward_to_url)
                    <span class="text-xs text-gray-400">→ {{ \Illuminate\Support\Str::limit($endpoint->forward_to_url, 60) }}</span>
                @else
                    <span class="text-xs text-gray-400">off</span>
                @endif
            </summary>
            <form method="POST" action="{{ route('endpoints.update', $endpoint) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label for="forward_to_url" class="block text-xs text-gray-500 mb-1">Forward target URL (at-least-once delivery; retries with backoff)</label>
                    <input id="forward_to_url" name="forward_to_url" type="url" value="{{ old('forward_to_url', $endpoint->forward_to_url) }}"
                           class="w-full rounded-md border-gray-300 text-sm">
                    <x-input-error :messages="$errors->get('forward_to_url')" class="mt-1"/>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" @checked($endpoint->enabled) class="rounded border-gray-300">
                    Enabled
                </label>
                <x-primary-button>Save</x-primary-button>
            </form>
        </details>

        {{-- Live event list --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">
                    Events
                    <span class="text-gray-400">(latest 50 · retention 7 days)</span>
                </span>
                <span class="flex items-center gap-1.5 text-xs text-gray-400">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> live
                </span>
            </div>

            <template x-if="events.length === 0">
                <div class="px-4 py-10 text-center text-sm text-gray-400">
                    Nothing yet. Fire the curl above — events appear here in realtime.
                </div>
            </template>

            <ul class="divide-y divide-gray-100">
                <template x-for="event in events" :key="event.id">
                    <li>
                        <button type="button" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 flex items-center gap-3"
                                @click="open(event.id)">
                            <span class="text-[11px] font-bold font-mono rounded px-1.5 py-0.5"
                                  :class="event.method === 'GET' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'"
                                  x-text="event.method"></span>
                            <span class="font-mono text-sm text-gray-800 truncate" x-text="event.path"></span>
                            <span class="ml-auto flex items-center gap-2 shrink-0">
                                <template x-for="d in event.deliveries" :key="d.attempt">
                                    <span class="text-[10px] rounded px-1 py-0.5 font-mono"
                                          :class="d.delivered ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                          :title="d.error ?? ''"
                                          x-text="'#' + d.attempt + ' ' + (d.status_code ?? 'ERR')"></span>
                                </template>
                                <span class="text-xs text-gray-400" x-text="new Date(event.received_at).toLocaleTimeString()"></span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Detail drawer --}}
        <template x-if="selected">
            <div class="fixed inset-0 z-50 flex" x-show="selected" style="display: none">
                <div class="absolute inset-0 bg-black/30" @click="selected = null"></div>
                <div class="relative ml-auto w-full max-w-2xl h-full bg-white shadow-xl overflow-y-auto p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">Event <span x-text="'#'+selected"></span></h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="selected = null">✕</button>
                    </div>

                    <template x-if="detailLoading"><p class="text-sm text-gray-400">Loading…</p></template>

                    <template x-if="detail">
                        <div class="space-y-4">
                            <div class="text-xs text-gray-500 flex gap-4">
                                <span x-text="detail.method"></span>
                                <span class="font-mono" x-text="detail.path"></span>
                                <span x-text="'from ' + (detail.ip ?? 'unknown')"></span>
                            </div>

                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">Headers</div>
                                <table class="w-full text-xs font-mono">
                                    <tbody>
                                        <template x-for="(values, name) in detail.headers ?? {}" :key="name">
                                            <tr class="border-b border-gray-50">
                                                <td class="py-0.5 pr-3 text-gray-600 align-top" x-text="name"></td>
                                                <td class="py-0.5 text-gray-800 break-all" x-text="values.join(', ')"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">Body</div>
                                <pre class="bg-gray-900 text-gray-100 text-xs rounded p-3 overflow-x-auto whitespace-pre-wrap"
                                     x-text="prettyBody(detail.body, detail.content_type) || '— empty —'"></pre>
                            </div>

                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">Deliveries</div>
                                <template x-if="(detail.deliveries ?? []).length === 0">
                                    <p class="text-xs text-gray-400">No forward attempts (forwarding off, or captured before a target was configured).</p>
                                </template>
                                <ul class="text-xs font-mono space-y-1">
                                    <template x-for="d in detail.deliveries ?? []" :key="d.attempt">
                                        <li>
                                            <span :class="d.delivered ? 'text-emerald-600' : 'text-red-600'"
                                                  x-text="'attempt ' + d.attempt + ': ' + (d.status_code ?? 'transport error')"></span>
                                            <span class="text-gray-400" x-text="d.error ? '— ' + d.error : ''"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <button type="button"
                                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500 disabled:opacity-50"
                                    :disabled="replayBusy[selected]"
                                    @click="replay(selected)"
                                    x-text="replayBusy[selected] ? 'Dispatching…' : 'Replay to forward target'"></button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
