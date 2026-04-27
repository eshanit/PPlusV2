<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Score Analysis</h1>
            <p class="mt-1 text-sm text-gray-600">
                Competency score distribution across evaluation tools
            </p>
        </div>
        <a href="{{ $this->getExportUrl() }}"
            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Export CSV
        </a>
    </div>

    <!-- Filters -->
    <div>
        {{ $this->form }}
    </div>

    <!-- Metadata Cards -->
    <div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-5 shadow-sm">
                <p class="text-sm font-medium text-gray-600">Total Sessions</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $metadata['totalSessions'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-5 shadow-sm">
                <p class="text-sm font-medium text-gray-600">Unique Mentees</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $metadata['uniqueMentees'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-5 shadow-sm">
                <p class="text-sm font-medium text-gray-600">Tools Included</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">{{ count($scoreDistribution) }}</p>
            </div>
        </div>
    </div>

    <!-- Score Distribution Table -->
    <div>
        <div class="rounded-lg border border-gray-200 bg-white shadow">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-semibold text-gray-900">Tool / Disease</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-900">N/A</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-900">1</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-900">2</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-900">3</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-900">4</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-900">5</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-900">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($scoreDistribution as $tool)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $tool['label'] }}
                                </td>
                                @php
                                    $service = app(\App\Services\ScoreDistributionService::class);
                                @endphp
                                @foreach (['null', '1', '2', '3', '4', '5'] as $score)
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex min-w-12 items-center justify-center rounded {{ $service->getCountColorClass($tool['scores'][$score] ?? 0) }} px-2 py-1 font-medium">
                                            {{ $tool['scores'][$score] ?? 0 }}
                                        </span>
                                    </td>
                                @endforeach
                                <td class="px-6 py-4 text-center font-semibold text-gray-900">
                                    {{ $tool['total'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="mb-3 h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z">
                                            </path>
                                        </svg>
                                        <p class="text-gray-500">No data available with selected filters</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="mb-3 font-semibold text-gray-900">Color Legend</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 md:grid-cols-7">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-6 w-6 rounded bg-gray-100"></span>
                    <span class="text-sm text-gray-600">0</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-6 w-6 rounded bg-gray-100"></span>
                    <span class="text-sm text-gray-600">1-5</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-6 w-6 rounded bg-blue-100"></span>
                    <span class="text-sm text-gray-600">6-10</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-6 w-6 rounded bg-blue-300"></span>
                    <span class="text-sm text-gray-600">11-20</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-6 w-6 rounded bg-emerald-400"></span>
                    <span class="text-sm text-gray-600">21-50</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-6 w-6 rounded bg-emerald-600"></span>
                    <span class="text-sm text-gray-600">51-100</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-6 w-6 rounded bg-emerald-800"></span>
                    <span class="text-sm text-gray-600">100+</span>
                </div>
            </div>
        </div>
    </div>
</div>
