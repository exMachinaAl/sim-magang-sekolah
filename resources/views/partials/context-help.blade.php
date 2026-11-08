<aside class="context-help" data-context-help data-hover-delay="1000" aria-label="Bantuan halaman">
 <button class="context-help-button" type="button" aria-expanded="false" aria-controls="context-help-panel" title="Petunjuk halaman ini"><span aria-hidden="true">?</span><span class="sr-only">Buka petunjuk halaman ini</span></button>
 <section class="context-help-panel" id="context-help-panel" role="dialog" aria-modal="false" aria-labelledby="context-help-title" hidden>
  <div class="context-help-head"><div><small>PETUNJUK HALAMAN</small><h2 id="context-help-title">{{ $contextHelp['title'] }}</h2></div><button type="button" class="context-help-close" aria-label="Tutup petunjuk">×</button></div>
  <p>{{ $contextHelp['summary'] }}</p>
  <ol>@foreach($contextHelp['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol>
  <div class="context-help-role"><strong>{{ auth()->user()->roleLabel() }}</strong><span>{{ $contextHelp['role_note'] }}</span></div>
  <a href="{{ route('guide',['q'=>$contextHelp['faq_query'] ?? $contextHelp['key']]) }}#faq-search">Buka FAQ & panduan lengkap →</a>
 </section>
</aside>
