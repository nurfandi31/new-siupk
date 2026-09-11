@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan')
@section('code', '404')
@section('status_text', 'Not Found')
@section('category', 'Navigasi & URL')
@section('category_icon', 'explore_off')
@section('badge_icon', 'search_off')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="120" cy="100" r="85" fill="#002746" fill-opacity="0.04" />
    <circle cx="120" cy="100" r="65" stroke="#002746" stroke-opacity="0.1" stroke-width="1.5" stroke-dasharray="4 4" />
    <g filter="url(#drop-shadow-404)">
        <rect x="48" y="35" width="144" height="130" rx="16" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <rect x="62" y="55" width="60" height="8" rx="4" fill="#002746" fill-opacity="0.2" />
        <rect x="62" y="70" width="40" height="6" rx="3" fill="#64748B" fill-opacity="0.2" />
        <rect x="62" y="90" width="116" height="58" rx="8" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1" />
        <path d="M72 132L94 112L112 122L140 100L168 116" stroke="#006D3D" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="94" cy="112" r="3.5" fill="#006D3D" />
        <circle cx="140" cy="100" r="3.5" fill="#97F3B5" stroke="#006D3D" stroke-width="2" />
    </g>
    <g transform="translate(130, 85)">
        <circle cx="28" cy="28" r="26" fill="#002746" fill-opacity="0.08" />
        <circle cx="28" cy="28" r="22" fill="#FFFFFF" stroke="#002746" stroke-width="3" />
        <circle cx="28" cy="28" r="16" fill="#D1E4FF" fill-opacity="0.4" />
        <path d="M28 16L32 28L28 40L24 28Z" fill="#BA1A1A" />
        <circle cx="28" cy="28" r="3.5" fill="#002746" />
        <line x1="44" y1="44" x2="62" y2="62" stroke="#002746" stroke-width="4.5" stroke-linecap="round" />
    </g>
    <g transform="translate(42, 28)">
        <rect width="36" height="24" rx="6" fill="#BA1A1A" />
        <text x="18" y="16" fill="#FFFFFF" font-size="11" font-weight="bold" font-family="Inter, sans-serif" text-anchor="middle">404</text>
    </g>
    <defs>
        <filter id="drop-shadow-404" x="38" y="28" width="164" height="152" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
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
    Maaf, halaman atau dokumen keuangan yang Anda cari tidak ditemukan. Tautan mungkin telah kedaluwarsa, dipindahkan, atau alamat URL yang Anda masukkan salah.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Periksa kembali penulisan URL di bilah alamat browser Anda.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Gunakan menu navigasi utama untuk mencari modul atau laporan yang diinginkan.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Jika Anda membuka tautan dari notifikasi atau pesan lama, data mungkin telah diarsipkan.</span>
    </li>
@endsection

@section('actions')
    <a href="{{ url('/dashboard') }}" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">dashboard</span>
        <span>Ke Dashboard Utama</span>
    </a>

    <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'" class="btn btn-secondary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">arrow_back</span>
        <span>Halaman Sebelumnya</span>
    </button>

    <a href="{{ url('/') }}" class="btn btn-ghost">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">home</span>
        <span>Beranda</span>
    </a>
@endsection