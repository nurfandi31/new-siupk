@extends('errors.layout')

@section('title', 'Sesi Belum Terautentikasi')
@section('code', '401')
@section('status_text', 'Unauthorized')
@section('category', 'Autentikasi Pengguna')
@section('category_icon', 'vpn_key')
@section('badge_icon', 'key')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="120" cy="100" r="85" fill="#059669" fill-opacity="0.04" />
    <circle cx="120" cy="100" r="65" stroke="#059669" stroke-opacity="0.1" stroke-width="1.5" stroke-dasharray="4 4" />
    <g filter="url(#drop-shadow-401)">
        <rect x="65" y="38" width="110" height="130" rx="14" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <rect x="106" y="46" width="28" height="6" rx="3" fill="#E2E8F0" />
        <circle cx="120" cy="80" r="20" fill="#F1F5F9" stroke="#E2E8F0" stroke-width="1.5" />
        <circle cx="120" cy="74" r="8" fill="#94A3B8" />
        <path d="M106 94C106 86 112 85 120 85C128 85 134 86 134 94" fill="#94A3B8" />
        <rect x="85" y="112" width="70" height="6" rx="3" fill="#059669" fill-opacity="0.25" />
        <rect x="95" y="124" width="50" height="5" rx="2.5" fill="#64748B" fill-opacity="0.2" />
        <rect x="90" y="142" width="60" height="14" rx="4" fill="#006D3D" fill-opacity="0.12" />
        <text x="120" y="152" fill="#006D3D" font-size="8" font-weight="bold" font-family="Inter, sans-serif" text-anchor="middle">LOGIN REQUIRED</text>
    </g>
    <g transform="translate(142, 105)">
        <circle cx="18" cy="18" r="14" fill="#059669" stroke="#FFFFFF" stroke-width="2.5" />
        <circle cx="18" cy="18" r="6" fill="#FFFFFF" />
        <path d="M28 28L48 48M42 42L47 37M46 46L51 41" stroke="#059669" stroke-width="4" stroke-linecap="round" />
    </g>
    <defs>
        <filter id="drop-shadow-401" x="55" y="30" width="130" height="152" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
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
    Akses ke modul atau layanan ini membutuhkan identifikasi pengguna yang valid. Silakan masuk terlebih dahulu untuk melanjutkan pekerjaan Anda di siupk Next.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Masuk menggunakan alamat email atau username serta kata sandi akun Anda.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Pastikan Anda tidak sedang membuka tautan ini dalam mode penyamaran jika sesi sering terputus.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Jika lupa kata sandi atau akun dinonaktifkan, hubungi administrator BUMDesma Anda.</span>
    </li>
@endsection

@section('actions')
    <a href="{{ url('/login') }}" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">login</span>
        <span>Masuk ke Portal</span>
    </a>

    <a href="{{ url('/') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">home</span>
        <span>Kembali ke Beranda</span>
    </a>
@endsection


