<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Dashboard') | SIMMAG</title>
<link rel="stylesheet" href="{{ asset('css/simmag.css') }}"></head>
<body><div class="app-shell">
<aside class="sidebar" id="sidebar">
 <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark">S</span><span><strong>SIMMAG</strong><small>SMKN 1 CERME GRESIK</small></span></a>
 <div class="sidebar-label">MENU UTAMA</div>
 <nav aria-label="Menu utama">
 <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><span>◫</span>Dashboard @include('partials.main-menu-badge',['module'=>'dashboard'])</a>
 <a class="nav-link {{ request()->routeIs('timeline.*')?'active':'' }}" href="{{ route('timeline.index') }}"><span>◷</span>Timeline PKL @include('partials.main-menu-badge',['module'=>'timeline'])</a>
 @if(auth()->user()->isRole('admin','koordinator','guru','siswa'))
 <a class="nav-link {{ request()->routeIs('journals.*')?'active':'' }}" href="{{ route('journals.index') }}"><span>▤</span>Jurnal & Foto @include('partials.main-menu-badge',['module'=>'journals'])</a>
 <a class="nav-link {{ request()->routeIs('assessments.*','assessment-templates.*')?'active':'' }}" href="{{ route('assessments.index') }}"><span>◇</span>Penilaian @include('partials.main-menu-badge',['module'=>'assessments'])</a>
 @endif
 @if(auth()->user()->isRole('admin','koordinator','guru'))
 <a class="nav-link {{ request()->routeIs('monitoring.*')?'active':'' }}" href="{{ route('monitoring.index') }}"><span>◎</span>Monitoring @include('partials.main-menu-badge',['module'=>'monitoring'])</a>
 @endif
 <a class="nav-link {{ request()->routeIs('certificates.*')?'active':'' }}" href="{{ route('certificates.index') }}"><span>▧</span>Sertifikat @include('partials.main-menu-badge',['module'=>'certificates'])</a>
 @if(auth()->user()->isRole('koordinator'))
 <div class="sidebar-label">MANAJEMEN</div>
 <a class="nav-link {{ request()->routeIs('students.*')?'active':'' }}" href="{{ route('students.index') }}"><span>♟</span>Data Siswa @include('partials.legacy-badge',['module'=>'students'])</a>
 <a class="nav-link {{ request()->routeIs('internships.*')?'active':'' }}" href="{{ route('internships.index') }}"><span>▦</span>Kontrol PKL</a>
 <a class="nav-link {{ request()->routeIs('placements.*')?'active':'' }}" href="{{ route('placements.index') }}"><span>⇄</span>Penempatan @include('partials.legacy-badge',['module'=>'placements'])</a>
 <a class="nav-link {{ request()->routeIs('companies.*')?'active':'' }}" href="{{ route('companies.index') }}"><span>▥</span>Perusahaan</a>
 <a class="nav-link {{ request()->routeIs('periods.*')?'active':'' }}" href="{{ route('periods.index') }}"><span>▦</span>Periode / Tag PKL @include('partials.legacy-badge',['module'=>'periods'])</a>
 <a class="nav-link {{ request()->routeIs('reports.*')?'active':'' }}" href="{{ route('reports.index') }}"><span>↗</span>Laporan & Audit @include('partials.legacy-badge',['module'=>'reports'])</a>
 @endif
 @if(auth()->user()->isRole('admin'))
 <div class="sidebar-label">ADMINISTRASI SISTEM</div>
 <a class="nav-link {{ request()->routeIs('users.*')?'active':'' }}" href="{{ route('users.index') }}"><span>⚙</span>Pengguna & Role</a>
 @endif
 <div class="sidebar-label">BANTUAN</div>
 <a class="nav-link {{ request()->routeIs('guide')?'active':'' }}" href="{{ route('guide') }}"><span>?</span>Panduan</a>
 <a class="nav-link {{ request()->routeIs('profile')?'active':'' }}" href="{{ route('profile') }}"><span>♙</span>Ubah Kata Sandi</a>
 </nav>
 <div class="sidebar-bottom"><div class="avatar">{{ strtoupper(mb_substr(auth()->user()->name,0,1)) }}</div><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></div></div>
</aside>
<div class="main-area">
<header class="topbar"><button class="mobile-menu" type="button" onclick="document.getElementById('sidebar').classList.toggle('shown')" aria-label="Buka menu">☰</button>
 <div><span class="topbar-kicker">Sistem Informasi Manajemen Magang</span><strong>SMK Negeri 1 Cerme Gresik</strong></div>
 <div class="top-actions"><span class="role-chip">{{ auth()->user()->roleLabel() }}</span>
 <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Keluar ↗</button></form></div>
</header>
<main class="page-content"><div class="page-head"><div><div class="eyebrow">SIMMAG / @yield('breadcrumb','Beranda')</div><h1>@yield('title','Dashboard')</h1><p>@yield('subtitle','Kelola pelaksanaan Praktik Kerja Lapangan secara terpusat.')</p></div><div>@yield('page_actions')</div></div>
@if(session('success'))<div class="alert success" role="status">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Periksa formulir Anda:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@include('partials.legacy-warning')
@include('partials.main-menu-warning')
@yield('content')
</main><footer class="footer">SIMMAG · SMKN 1 Cerme Gresik <span>Versi prototype Laravel · Data demo dapat diganti</span></footer></div></div>
<script>document.addEventListener('click',function(e){const el=e.target.closest('[data-confirm]');if(el&&!window.confirm(el.dataset.confirm)){e.preventDefault();}});</script>
@stack('scripts')
</body></html>
