@foreach(config('main_menu_health.modules', []) as $module => $health)
@if(request()->routeIs(...$health['routes']))
<section class="module-health-warning {{ $health['status'] }}" aria-labelledby="module-health-title" data-module-health="{{ $module }}">
    <strong id="module-health-title">{{ $health['status'] === 'red' ? 'RED FLAG' : 'UNSTABLE' }} — {{ $health['name'] }}</strong>
    <p>{{ $health['summary'] }}</p>
    <details>
        <summary>Lihat masalah dan rencana perbaikan · urutan {{ $health['order'] }} / 6 ({{ $health['priority'] }})</summary>
        <p><strong>Sudah terhubung:</strong> {{ $health['working'] }}</p>
        <ul>@foreach($health['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>
        <p><strong>Perbaikan berikutnya:</strong> {{ $health['next'] }}</p>
        @if(auth()->user()->isRole('koordinator'))
        <a href="{{ route('internships.index') }}">Periksa sumber jadwal di Kontrol PKL</a>
        @endif
    </details>
    <small>Audit {{ config('main_menu_health.reviewed_at') }} · Penanda keterbatasan modul, bukan pernyataan data rusak atau perbaikan sudah selesai.</small>
</section>
@endif
@endforeach
