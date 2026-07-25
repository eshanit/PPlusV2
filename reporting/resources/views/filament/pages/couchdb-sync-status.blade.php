<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">CouchDB Sync</h1>
            <p class="mt-1 text-sm text-gray-600">
                Pull sessions, gaps, users, districts and facilities from CouchDB into MySQL.
                Runs automatically every 5 minutes; use the buttons below to sync on demand.
            </p>
        </div>
        <button
            type="button"
            wire:click="syncAll"
            wire:loading.attr="disabled"
            wire:target="syncAll"
            class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-600 transition-colors disabled:opacity-50"
        >
            <svg wire:loading wire:target="syncAll" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Sync All Now
        </button>
    </div>

    <!-- Checkpoints table -->
    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Database</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">CouchDB name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Last synced</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($checkpoints as $checkpoint)
                    <tr wire:key="checkpoint-{{ $checkpoint['logical_name'] }}">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 capitalize">{{ $checkpoint['logical_name'] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $checkpoint['db_name'] }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $checkpoint['last_synced_at'] ?? 'Never synced' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                wire:click="syncOne('{{ $checkpoint['logical_name'] }}')"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50"
                            >
                                Sync
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">
                            No CouchDB databases configured.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
