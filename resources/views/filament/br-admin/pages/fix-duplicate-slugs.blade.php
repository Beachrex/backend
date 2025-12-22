<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status Card --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Duplicate Beach Slugs Status
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Check and fix duplicate beach slugs across all 14 languages
                    </p>
                </div>
                <div class="text-right">
                    @if($duplicateCount > 0)
                        <div class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-800 dark:bg-red-900 dark:text-red-200">
                            <svg class="mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            {{ $duplicateCount }} duplicate(s) found
                        </div>
                    @else
                        <div class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                            <svg class="mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            No duplicates
                        </div>
                    @endif
                </div>
            </div>

            {{-- Duplicate Details --}}
            @if($duplicateCount > 0)
                <div class="mt-4 space-y-2">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Duplicates by Language:</h4>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7">
                        @foreach($duplicates as $locale => $items)
                            <div class="rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-700">
                                <span class="font-medium uppercase text-gray-900 dark:text-white">{{ $locale }}</span>:
                                <span class="text-gray-600 dark:text-gray-400">{{ count($items) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Action Buttons --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Actions</h3>
            
            <div class="flex flex-wrap gap-3">
                {{-- Refresh Button --}}
                <x-filament::button
                    wire:click="checkDuplicates"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    :disabled="$isRunning"
                >
                    Refresh Status
                </x-filament::button>

                {{-- Dry Run Button --}}
                <x-filament::button
                    wire:click="runDryRun"
                    color="info"
                    icon="heroicon-o-eye"
                    :disabled="$isRunning || $duplicateCount === 0"
                >
                    Preview Changes (Dry Run)
                </x-filament::button>

                {{-- Fix Button --}}
                <x-filament::button
                    wire:click="runFix"
                    color="danger"
                    icon="heroicon-o-wrench-screwdriver"
                    :disabled="$isRunning || $duplicateCount === 0"
                >
                    Fix All Duplicates
                </x-filament::button>
            </div>

            @if($duplicateCount === 0)
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    ✓ No duplicate slugs found. All beach slugs are unique!
                </p>
            @else
                <div class="mt-3 rounded-md bg-yellow-50 p-3 dark:bg-yellow-900/20">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <strong>⚠️ Warning:</strong> Fixing duplicates will modify beach slugs by appending city names.
                        Run "Preview Changes" first to see what will be changed.
                    </p>
                </div>
            @endif
        </div>

        {{-- Output Console --}}
        @if($output)
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Command Output</h3>
                <div class="overflow-x-auto">
                    <pre class="rounded-md bg-gray-900 p-4 text-xs text-green-400 dark:bg-gray-950"><code>{{ $output }}</code></pre>
                </div>
            </div>
        @endif

        {{-- Information Card --}}
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
            <h3 class="mb-2 flex items-center text-lg font-semibold text-blue-900 dark:text-blue-100">
                <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                How It Works
            </h3>
            <div class="space-y-2 text-sm text-blue-800 dark:text-blue-200">
                <p><strong>1. Preview Changes:</strong> Shows what slugs will be changed without making any modifications.</p>
                <p><strong>2. Fix Duplicates:</strong> Automatically fixes duplicate slugs by appending city names (e.g., <code>beach-solaris</code> → <code>beach-solaris-vabriga</code>).</p>
                <p><strong>3. Redirects:</strong> Old URLs will still work via automatic redirects stored in the database.</p>
                <p><strong>4. All Languages:</strong> Processes all 14 supported languages in one command.</p>
                <p><strong>5. Cache:</strong> API cache is automatically cleared after fixing.</p>
            </div>
        </div>
    </div>

    {{-- Loading Overlay --}}
    @if($isRunning)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
            <div class="rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                <div class="flex items-center space-x-3">
                    <svg class="h-8 w-8 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-lg font-medium text-gray-900 dark:text-white">Processing...</span>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>

