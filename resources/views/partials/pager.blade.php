@if($items->hasPages())<div class="pagination">
 <span>Halaman {{ $items->currentPage() }} dari {{ $items->lastPage() }} ({{ $items->total() }} data)</span>
 @if($items->onFirstPage())<span>← Sebelumnya</span>@else<a class="btn btn-sm btn-ghost" href="{{ $items->previousPageUrl() }}">← Sebelumnya</a>@endif
 @if($items->hasMorePages())<a class="btn btn-sm btn-ghost" href="{{ $items->nextPageUrl() }}">Berikutnya →</a>@else<span>Berikutnya →</span>@endif
 </div>@endif
