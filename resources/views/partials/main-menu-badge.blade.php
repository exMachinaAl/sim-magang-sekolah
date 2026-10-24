@if($health = config('main_menu_health.modules.'.$module))
@if($health['status'] !== 'stable')
<small class="module-health-badge {{ $health['status'] }}" title="{{ $health['summary'] }}" aria-label="{{ $health['name'] }}: {{ $health['status'] === 'red' ? 'RED FLAG' : 'UNSTABLE' }}">{{ $health['status'] === 'red' ? 'RED FLAG' : 'UNSTABLE' }}</small>
@endif
@endif
