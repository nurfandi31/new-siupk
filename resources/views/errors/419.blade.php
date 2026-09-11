@extends('errors.layout')

@section('title', 'Sesi Formulir Kedaluwarsa')
@section('code', '419')
@section('status_text', 'Page Expired')
@section('category', 'Keamanan Sesi')
@section('category_icon', 'timer_off')
@section('badge_icon', 'hourglass_disabled')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="120" cy="100" r="85" fill="#006D3D" fill-opacity="0.04" />
    <circle cx="120" cy="100" r="65" stroke="#006D3D" stroke-opacity="0.12" stroke-width="1.5" stroke-dasharray="4 4" />
    <g filter="url(#drop-shadow-419)">
        <rect x="75" y="40" width="90" height="120" rx="16" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <path d="M96 60H144M96 140H144" stroke="#002746" stroke-width="3.5" stroke-linecap="round" />
        <path d="M102 60C102 85 116 95 120 100C124 95 138 85 138 60H102Z" fill="#002746" fill-opacity="0.08" stroke="#002746" stroke-width="2" />
        <path d="M102 140C102 115 116 105 120 100C124 105 138 115 138 140H102Z" fill="#006D3D" fill-opacity="0.2" stroke="#002746" stroke-width="2" />
        <circle cx="120" cy="102" r="1.5" fill="#006D3D" />
        <circle cx="120" cy="110" r="1.5" fill="#006D3D" />
        <circle cx="120" cy="118" r="1.5" fill="#006D3D" />
        <path d="M108 136C108 130 114 125 120 125C126 125 132 130 132 136H108Z" fill="#006D3D" />
    </g>
    <g transform="translate(150, 48)">
        <circle cx="16" cy="16" r="16" fill="#006D3D" />
        <path d="M16 10V13M16 19V22M10 16H13M19 16H22" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" />
        <circle cx="16" cy="16" r="3" fill="#97F3B5" />
    </g>
    <defs>
        <filter id="drop-shadow-419" x="65" y="32" width="110" height="142" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
            <feFlood flood-opacity="0" result="BackgroundImageFix"/>
            <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0"/>
            <feOffset dy="6"/>
            <feGaussianBlur stdDeviation="5"/>
            <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0.15 0 0 0 0 0.27 0 0 0 0.08 0"/>
            <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow"/>
            <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow" result="shape"/>
        </filter>
    </defs>
</svg>
@endsection

@section('message')
    Sesi keamanan (CSRF Token) formulir Anda telah kedaluwarsa karena halaman tidak aktif dalam durasi tertentu. Hal ini otomatis dilakukan oleh sistem demi melindungi keamanan data keuangan Anda.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Klik tombol 'Muat Ulang Halaman' di bawah untuk memperbarui token keamanan sesi Anda.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Pastikan untuk menyimpan draf atau formulir secara berkala saat bekerja.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Jika Anda diarahkan ke halaman login, silakan masukkan kembali akun Anda.</span>
    </li>
@endsection

@section('actions')
    <button onclick="window.location.reload()" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">refresh</span>
        <span>Muat Ulang Halaman</span>
    </button>

    <a href="{{ url('/login') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">login</span>
        <span>Masuk Kembali</span>
    </a>

    <a href="{{ url('/dashboard') }}" class="btn btn-ghost">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">dashboard</span>
        <span>Dashboard</span>
    </a>
@endsection