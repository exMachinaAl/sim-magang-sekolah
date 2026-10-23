@if(config('legacy_modules.'.$module))
<small class="legacy-badge" title="{{ config('legacy_modules.'.$module.'.message') }}" aria-label="Peringatan: modul belum sepenuhnya sinkron dengan Kontrol PKL">RED FLAG</small>
@endif
