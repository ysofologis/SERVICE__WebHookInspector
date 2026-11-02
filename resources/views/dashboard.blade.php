<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto sm:px-6 lg:px-8">
        @if (session('status') === 'endpoint-created')
            <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-2">Endpoint created.</div>
        @elseif (session('status') === 'endpoint-deleted')
            <div class="mb-4 rounded-md bg-gray-50 border text-gray-700 text-sm px-4 py-2">Endpoint deleted.</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 text-sm font-medium text-gray-700">Your endpoints</div>

                @forelse ($endpoints as $endpoint)
                    <a href="{{ route('endpoints.show', $endpoint) }}"
                       class="block px-4 py-3 border-b border-gray-50 hover:bg-gray-50">
                        <div class="flex items-center gap-3">
                            <span class="font-medium text-sm text-gray-800">{{ $endpoint->name }}</span>
                            @unless ($endpoint->enabled)
                                <span class="text-[10px] font-medium rounded-full bg-red-100 text-red-700 px-2 py-0.5">disabled</span>
                            @endunless
                            <span class="ml-auto text-xs text-gray-400">{{ $endpoint->events_count }} events</span>
                        </div>
                        <div class="text-xs text-gray-400 font-mono mt-0.5 truncate">/hook/{{ $endpoint->token }}</div>
                    </a>
                @empty
                    <div class="px-4 py-10 text-center text-sm text-gray-400">
                        No endpoints yet — create one on the right to get a capture URL.
                    </div>
                @endforelse
            </div>

            <div class="bg-white shadow-sm rounded-lg p-4">
                <div class="text-sm font-medium text-gray-700 mb-3">New endpoint</div>
                <form method="POST" action="{{ route('endpoints.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="name" class="block text-xs text-gray-500 mb-1">Name</label>
                        <input id="name" name="name" value="{{ old('name') }}" required
                               class="w-full rounded-md border-gray-300 text-sm" placeholder="stripe-sandbox">
                    </div>
                    <div>
                        <label for="forward_to_url" class="block text-xs text-gray-500 mb-1">Forward target URL <span class="text-gray-300">(optional)</span></label>
                        <input id="forward_to_url" name="forward_to_url" type="url" value="{{ old('forward_to_url') }}"
                               class="w-full rounded-md border-gray-300 text-sm" placeholder="https://localhost:3000/webhooks">
                    </div>
                    <x-primary-button>Create</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
