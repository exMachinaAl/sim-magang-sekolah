@foreach(config('main_menu_health.modules', []) as $module => $health)
@if($health['status'] !== 'stable' && request()->routeIs(...$health['routes']))
<section class="module-health-warning {{ $health['status'] }}" aria-labelledby="module-health-title" data-module-health="{{ $module }}">
    <strong id="module-health-title">{{ $health['status'] === 'red' ? 'RED FLAG' : 'UNSTABLE' }} — {{ $health['name'] }}</strong>
    @if($module==='certificates')
    <p><strong>PDF bawaan sudah bisa digunakan.</strong> Flag ini tidak mengunci seluruh fitur sertifikat.</p>
    <p class="small">DOCX dari arsip lengkap yang sudah disahkan juga bisa disiapkan dan diunduh tanpa LibreOffice melalui detail sertifikat.</p>
    <p class="small">DOCX → PDF: {{ config('certificate_print.libreoffice') && is_file(config('certificate_print.libreoffice')) ? 'mesin sudah dikonfigurasi; hasil cetak masih perlu diuji.' : 'belum siap, menunggu LibreOffice. Gunakan PDF bawaan untuk pengujian sekarang.' }}</p>
    @else<p>{{ $health['summary'] }}</p>@endif
    <details>
        <summary>Lihat masalah dan rencana perbaikan · urutan {{ $health['order'] }} / 6 ({{ $health['priority'] }})</summary>
        @if($module==='certificates')<p>{{ $health['summary'] }}</p>@endif
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
