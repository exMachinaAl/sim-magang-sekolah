@extends('layouts.app')
@section('title','FAQ & Panduan Penggunaan')
@section('breadcrumb','Bantuan / FAQ & Panduan')
@section('subtitle','Petunjuk yang disesuaikan dengan role '.auth()->user()->roleLabel().'.')
@section('content')
<div class="card faq-intro">
 <div><span class="faq-icon" aria-hidden="true">?</span></div>
 <div><h2>Pusat bantuan {{ auth()->user()->roleLabel() }}</h2><p>{{ $introduction }}</p><p class="small muted">Petunjuk singkat juga tersedia melalui tombol <strong>?</strong> di kanan bawah setiap halaman.</p></div>
</div>

<div class="card" id="faq-search">
 <label for="faq-query">Cari pertanyaan atau petunjuk</label>
 <div class="faq-search-row"><input id="faq-query" type="search" placeholder="Contoh: jurnal, QR, nilai, sertifikat, kata sandi" autocomplete="off"><button class="btn btn-ghost" type="button" id="faq-clear">Bersihkan</button></div>
 <p class="small muted" id="faq-result" role="status">Menampilkan seluruh panduan untuk role Anda.</p>
</div>

<div class="faq-sections" data-faq-list>
@foreach($sections as $section)
 <section class="card faq-section">
  <div class="card-head"><div><h2>{{ $section['title'] }}</h2><p>{{ $section['description'] }}</p></div></div>
  <div class="faq-list">
  @foreach($section['items'] as $item)
   <details class="faq-entry" data-faq-entry data-faq-text="{{ mb_strtolower($item['question'].' '.$item['answer']) }}">
    <summary><span>{{ $item['question'] }}</span><span aria-hidden="true">+</span></summary>
    <div class="faq-answer"><p>{{ $item['answer'] }}</p></div>
   </details>
  @endforeach
  </div>
 </section>
@endforeach
</div>

<div class="card faq-empty" data-faq-empty hidden>
 <span aria-hidden="true">?</span><h2>Petunjuk tidak ditemukan</h2><p>Coba kata yang lebih singkat. Jika masalah menyangkut data tertentu, catat nama/NIS, tahun, menu, dan pesan yang terlihat sebelum menghubungi petugas.</p>
</div>
@endsection

@push('scripts')
<script>
(() => {
 const input=document.getElementById('faq-query'),clear=document.getElementById('faq-clear'),status=document.getElementById('faq-result');
 const entries=Array.from(document.querySelectorAll('[data-faq-entry]')),empty=document.querySelector('[data-faq-empty]');
 if(!input)return;
 const filter=()=>{
  const query=input.value.trim().toLocaleLowerCase('id-ID');let visible=0;
  entries.forEach(entry=>{const show=!query||entry.dataset.faqText.includes(query);entry.hidden=!show;if(show)visible++;});
  document.querySelectorAll('.faq-section').forEach(section=>{section.hidden=!Array.from(section.querySelectorAll('[data-faq-entry]')).some(entry=>!entry.hidden);});
  empty.hidden=visible!==0;status.textContent=query?`${visible} petunjuk ditemukan untuk “${input.value.trim()}”.`:'Menampilkan seluruh panduan untuk role Anda.';
 };
 input.addEventListener('input',filter);clear.addEventListener('click',()=>{input.value='';filter();input.focus();});
})();
</script>
@endpush
