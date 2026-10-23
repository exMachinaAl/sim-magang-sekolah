@if(auth()->user()->isRole('koordinator'))
@foreach(config('legacy_modules', []) as $warning)
@if(request()->routeIs($warning['routes']))
<section class="legacy-warning" role="note" aria-label="Peringatan mekanisme lama">
    <strong>RED FLAG — modul belum sepenuhnya sinkron</strong>
    <p>{{ $warning['message'] }}</p>
    <a href="{{ route('internships.index') }}">Buka Kontrol PKL</a>
    <small>Menu tetap tersedia untuk data lama. Peringatan ini belum berarti masalahnya sudah diperbaiki.</small>
</section>
@endif
@endforeach
@endif
