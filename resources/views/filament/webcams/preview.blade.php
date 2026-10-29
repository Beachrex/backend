{{-- TASK-006: same iframe as the website (no third-party script). --}}
<div style="aspect-ratio: 16 / 9; width: 100%;">
    <iframe src="{{ config('webcams.player_base_url') }}?webcamId={{ $id }}&playerType=day&loop=0&autoPlay=0&interactive=1"
            title="Webcam {{ $id }}" loading="lazy" allow="fullscreen" allowfullscreen
            style="border: 0; width: 100%; height: 100%;"></iframe>
</div>
@if ($url)
    <p style="margin-top: .5rem;"><a href="{{ $url }}" target="_blank" rel="noopener" style="text-decoration: underline;">Open on windy.com</a></p>
@endif
