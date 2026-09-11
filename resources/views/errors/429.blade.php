@extends('errors.layout')

@section('title', 'Terlalu Banyak Permintaan')
@section('code', '429')
@section('status_text', 'Too Many Requests')
@section('category', 'Batas Frekuensi')
@section('category_icon', 'speed')
@section('badge_icon', 'timer')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="120" cy="100" r="85" fill="#D97706" fill-opacity="0.04" />
    <circle cx="120" cy="100" r="65" stroke="#D97706" stroke-opacity="0.12" stroke-width="1.5" stroke-dasharray="4 4" />
    <g filter="url(#drop-shadow-429)">
        <rect x="55" y="45" width="130" height="110" rx="16" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <path d="M78 120C78 96.804 96.804 78 120 78C143.196 78 162 96.804 162 120" stroke="#E2E8F0" stroke-width="10" stroke-linecap="round" />
        <path d="M78 120C78 96.804 96.804 78 120 78" stroke="#006D3D" stroke-width="10" stroke-linecap="round" />
        <path d="M120 78C135 78 148 86 156 98" stroke="#D97706" stroke-width="10" stroke-linecap="round" />
        <path d="M156 98C160 104 162 112 162 120" stroke="#BA1A1A" stroke-width="10" stroke-linecap="round" />
        <circle cx="120" cy="120" r="7" fill="#002746" />
        <line x1="120" y1="120" x2="148" y2="94" stroke="#BA1A1A" stroke-width="3" stroke-linecap="round" />
    </g>
    <g transform="translate(155, 38)">
        <circle cx="14" cy="14" r="14" fill="#D97706" />
        <path d="M14 9V14L18 16" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" />
    </g>
    <defs>
        <filter id="drop-shadow-429" x="45" y="38" width="150" height="130" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
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
    Sistem mendeteksi terlalu banyak permintaan dalam waktu singkat. Pembatasan sementara ini diberlakukan demi menjaga stabilitas dan performa seluruh pengguna siupk Next.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Tunggu 30 hingga 60 detik sebelum mengklik kembali atau memuat ulang halaman.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Hindari menekan tombol 'Simpan' atau 'Proses' berulang-ulang dalam waktu cepat.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Pastikan tidak ada plugin otomatis atau multi-tab yang memicu request bersamaan.</span>
    </li>
@endsection

@section('actions')
    <button onclick="setTimeout(function(){ window.location.reload(); }, 1000)" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">refresh</span>
        <span>Coba Muat Ulang</span>
    </button>

    <a href="{{ url('/dashboard') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">dashboard</span>
        <span>Ke Dashboard</span>
    </a>
@endsection