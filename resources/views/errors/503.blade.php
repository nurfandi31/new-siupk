@extends('errors.layout')

@section('title', 'Sistem Dalam Pemeliharaan')
@section('code', '503')
@section('status_text', 'Service Unavailable')
@section('category', 'Pemeliharaan Sistem')
@section('category_icon', 'engineering')
@section('badge_icon', 'construction')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="120" cy="100" r="85" fill="#006D3D" fill-opacity="0.04" />
    <circle cx="120" cy="100" r="65" stroke="#006D3D" stroke-opacity="0.12" stroke-width="1.5" stroke-dasharray="4 4" />
    <g filter="url(#drop-shadow-503)">
        <rect x="55" y="45" width="130" height="110" rx="18" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <g transform="translate(120, 95)">
            <circle cx="0" cy="0" r="28" fill="#059669" />
            <rect x="-4" y="-36" width="8" height="72" rx="3" fill="#059669" />
            <rect x="-4" y="-36" width="8" height="72" rx="3" fill="#059669" transform="rotate(45)" />
            <rect x="-4" y="-36" width="8" height="72" rx="3" fill="#059669" transform="rotate(90)" />
            <rect x="-4" y="-36" width="8" height="72" rx="3" fill="#059669" transform="rotate(135)" />
            <circle cx="0" cy="0" r="14" fill="#FFFFFF" />
            <circle cx="0" cy="0" r="6" fill="#006D3D" />
        </g>
    </g>
    <g transform="translate(155, 40)">
        <circle cx="16" cy="16" r="16" fill="#D97706" />
        <path d="M16 8L23 23H9L16 8Z" fill="#FFFFFF" />
        <path d="M12.5 17H19.5" stroke="#D97706" stroke-width="2" />
    </g>
    <defs>
        <filter id="drop-shadow-503" x="45" y="38" width="150" height="130" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
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
    Kami sedang melakukan peningkatan performa dan pemeliharaan sistem rutin. Layanan siupk Next akan segera beroperasi normal kembali.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Pemeliharaan berkala biasanya berlangsung dalam beberapa menit.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Seluruh data transaksi dan catatan keuangan Anda tetap aman.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Silakan klik 'Coba Lagi' secara berkala untuk mengecek kesiapan server.</span>
    </li>
@endsection

@section('actions')
    <button onclick="window.location.reload()" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">refresh</span>
        <span>Coba Lagi Sekarang</span>
    </button>

    <a href="{{ url('/') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">home</span>
        <span>Halaman Depan</span>
    </a>
@endsection


