@extends('industry.layout')
@section('content')
<div class="card"><h1>{{ $title }}</h1><p>Pembimbing: <strong>{{ $mentor->name }}</strong> · {{ $mentor->company->name }}</p>
@isset($journal)<p>{{ $journal->placement->student->user->name }} · NIS {{ $journal->placement->student->nis }}</p><p>Tanggal kegiatan {{ $journal->activity_date->format('d/m/Y') }} · versi {{ $journal->revision }}</p>
<p style="white-space:pre-wrap">{{ $journal->activity }}</p><p><strong>Nilai {{ $journal->industry_score }} · {{ $journal->scoreCategory() }}</strong></p>
<p id="scan-status" role="status">Mengarahkan ke portal kiosk untuk pemeriksaan dan konfirmasi…</p>
<a class="btn btn-primary" href="{{ $portal }}">Buka Portal Kiosk</a>
@else
<p>QR sekali pakai ini mendaftarkan browser Anda selama 180 hari, sampai dicabut koordinator. QR pendaftaran berlaku 24 jam.</p>
<p id="scan-status" role="status">Mendaftarkan perangkat…</p>
<form method="POST" action="{{ $action }}" id="scan-form">@csrf<button class="btn btn-primary">Lanjutkan jika proses otomatis tidak berjalan</button></form>
@endisset
</div>
@endsection
@push('scripts')
@isset($journal)<script>window.location.replace(@json($portal));</script>@else<script>document.getElementById('scan-form').requestSubmit();</script>@endisset
@endpush
