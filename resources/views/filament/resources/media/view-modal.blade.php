<div class="space-y-4">
    @if($record->isImage())
        <div class="flex justify-center">
            <img src="{{ $record->url }}" alt="{{ $record->file_name }}" class="max-w-full h-auto rounded-lg shadow-lg">
        </div>
    @elseif($record->isVideo())
        <div class="flex justify-center">
            <video controls class="max-w-full h-auto rounded-lg shadow-lg">
                <source src="{{ $record->url }}" type="{{ $record->mime_type }}">
                Your browser does not support the video tag.
            </video>
        </div>
    @else
        <div class="flex justify-center p-8 bg-gray-100 rounded-lg">
            <p class="text-gray-500">Preview not available for this file type.</p>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <span class="font-semibold">File Name:</span>
            <p class="text-gray-600">{{ $record->file_name }}</p>
        </div>
        <div>
            <span class="font-semibold">Type:</span>
            <p class="text-gray-600">{{ $record->mediaType->name ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="font-semibold">Collection:</span>
            <p class="text-gray-600">{{ $record->mediaCollection->name ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="font-semibold">Size:</span>
            <p class="text-gray-600">{{ $record->human_readable_size }}</p>
        </div>
        @if($record->width && $record->height)
        <div>
            <span class="font-semibold">Dimensions:</span>
            <p class="text-gray-600">{{ $record->width }} x {{ $record->height }}</p>
        </div>
        @endif
        <div>
            <span class="font-semibold">MIME Type:</span>
            <p class="text-gray-600">{{ $record->mime_type }}</p>
        </div>
        <div>
            <span class="font-semibold">Disk:</span>
            <p class="text-gray-600">{{ $record->disk }}</p>
        </div>
        <div>
            <span class="font-semibold">Status:</span>
            <p class="text-gray-600">{{ $record->status->name ?? 'N/A' }}</p>
        </div>
    </div>

    <div>
        <span class="font-semibold">File Path:</span>
        <p class="text-xs text-gray-600 break-all">{{ $record->file_path }}</p>
    </div>

    <div>
        <span class="font-semibold">URL:</span>
        <p class="text-xs text-gray-600 break-all">
            <a href="{{ $record->url }}" target="_blank" class="text-blue-600 hover:underline">{{ $record->url }}</a>
        </p>
    </div>
</div>

