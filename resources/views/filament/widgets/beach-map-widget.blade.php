<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            GPS Location Map
        </x-slot>

        <x-slot name="description">
            @if($this->hasCoordinates())
                Showing location for: {{ $this->getBeachName() }}
            @else
                No GPS coordinates set. Add latitude and longitude to see the map.
            @endif
        </x-slot>

        <div class="space-y-4">
            @if($this->hasCoordinates())
                <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700" style="height: 450px;">
                    <iframe
                        width="100%"
                        height="450"
                        style="border:0"
                        loading="lazy"
                        allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $this->getLongitude() - 0.01 }},{{ $this->getLatitude() - 0.01 }},{{ $this->getLongitude() + 0.01 }},{{ $this->getLatitude() + 0.01 }}&layer=mapnik&marker={{ $this->getLatitude() }},{{ $this->getLongitude() }}">
                    </iframe>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-gray-500 dark:text-gray-400 text-xs font-medium mb-1">Latitude</div>
                        <div class="font-semibold">{{ number_format($this->getLatitude(), 6) }}</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                        <div class="text-gray-500 dark:text-gray-400 text-xs font-medium mb-1">Longitude</div>
                        <div class="font-semibold">{{ number_format($this->getLongitude(), 6) }}</div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <a href="https://www.google.com/maps?q={{ $this->getLatitude() }},{{ $this->getLongitude() }}"
                       target="_blank"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Open in Google Maps
                    </a>
                    <a href="https://www.openstreetmap.org/?mlat={{ $this->getLatitude() }}&mlon={{ $this->getLongitude() }}&zoom=15"
                       target="_blank"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        Open in OpenStreetMap
                    </a>
                </div>
            @else
                <div class="flex items-center justify-center h-64 bg-gray-50 dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No GPS Coordinates</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add latitude and longitude to display the map</p>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

