<div class="evidence-image-preview">
 <button type="button" class="evidence-preview-trigger" data-evidence-preview
  data-preview-url="{{ $previewUrl }}" data-download-url="{{ $downloadUrl }}" data-open-url="{{ $openUrl ?? $previewUrl }}"
  data-preview-title="{{ $title }}" aria-label="{{ $actionLabel ?? 'Lihat foto bukti lebih besar' }}">
  <img class="evidence-thumbnail" src="{{ $previewUrl }}" alt="Preview {{ $title }}" loading="lazy">
  <span>{{ $actionLabel ?? 'Lihat foto bukti lebih besar' }}</span>
 </button>
 <div class="evidence-preview-actions">
  <a class="btn btn-secondary btn-sm" href="{{ $downloadUrl }}">Unduh gambar</a>
  <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="{{ $openUrl ?? $previewUrl }}">Buka di tab lain</a>
 </div>
</div>
