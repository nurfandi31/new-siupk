@extends('errors.layout')

@section('title', 'Terjadi Kesalahan Server')
@section('code', '500')
@section('status_text', 'Internal Server Error')
@section('category', 'Kendala Teknis Server')
@section('category_icon', 'dns')
@section('badge_icon', 'error')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="120" cy="100" r="85" fill="#BA1A1A" fill-opacity="0.04" />
    <circle cx="120" cy="100" r="65" stroke="#BA1A1A" stroke-opacity="0.12" stroke-width="1.5" stroke-dasharray="4 4" />
    <g filter="url(#drop-shadow-500)">
        <rect x="58" y="44" width="124" height="32" rx="8" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <circle cx="74" cy="60" r="4" fill="#006D3D" />
        <circle cx="86" cy="60" r="4" fill="#006D3D" />
        <line x1="102" y1="60" x2="166" y2="60" stroke="#E2E8F0" stroke-width="3" stroke-linecap="round" />

        <rect x="58" y="84" width="124" height="32" rx="8" fill="#FFFFFF" stroke="#BA1A1A" stroke-width="2" />
        <circle cx="74" cy="100" r="4" fill="#BA1A1A" />
        <circle cx="86" cy="100" r="4" fill="#BA1A1A" />
        <line x1="102" y1="100" x2="152" y2="100" stroke="#FFDAD6" stroke-width="3" stroke-linecap="round" />
        <circle cx="166" cy="100" r="5" fill="#BA1A1A" />

        <rect x="58" y="124" width="124" height="32" rx="8" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <circle cx="74" cy="140" r="4" fill="#006D3D" />
        <circle cx="86" cy="140" r="4" fill="#006D3D" />
        <line x1="102" y1="140" x2="166" y2="140" stroke="#E2E8F0" stroke-width="3" stroke-linecap="round" />
    </g>
    <g transform="translate(155, 70)">
        <circle cx="18" cy="18" r="18" fill="#BA1A1A" />
        <path d="M18 10V18M18 22V23" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
    </g>
    <defs>
        <filter id="drop-shadow-500" x="48" y="38" width="144" height="130" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
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
    Terjadi kendala teknis internal pada server saat memproses transaksi atau permintaan Anda. Tenang, seluruh data yang telah tersimpan sebelumnya tetap aman di basis data.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Coba muat ulang halaman ini dalam beberapa saat.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Gunakan tombol 'Salin Info Error' di bawah jika ingin meneruskan laporan ke tim IT BUMDesma.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Kembali ke dashboard untuk melanjutkan modul pembukuan lainnya.</span>
    </li>
@endsection

@section('actions')
    <button onclick="window.location.reload()" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">refresh</span>
        <span>Muat Ulang Halaman</span>
    </button>

    <a href="{{ url('/dashboard') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">dashboard</span>
        <span>Ke Dashboard</span>
    </a>

    <button onclick="copyDiagnosticInfo()" class="btn btn-ghost">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">content_copy</span>
        <span>Salin Info</span>
    </button>
@endsection