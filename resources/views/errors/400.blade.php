@extends('errors.layout')

@section('title', '400 - Permintaan Tidak Valid')
@section('code', '400')
@section('status_text', 'Bad Request')
@section('category', 'Format & Parameter')
@section('category_icon', 'code_off')
@section('badge_icon', 'error_outline')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <!-- Outer Glow Circle -->
    <circle cx="120" cy="100" r="85" fill="#BA1A1A" fill-opacity="0.04" />
    <circle cx="120" cy="100" r="65" stroke="#BA1A1A" stroke-opacity="0.12" stroke-width="1.5" stroke-dasharray="4 4" />

    <!-- Bad Request Card -->
    <g filter="url(#drop-shadow-400)">
        <rect x="55" y="45" width="130" height="110" rx="16" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        
        <!-- Code syntax lines -->
        <rect x="70" y="65" width="40" height="8" rx="4" fill="#002746" fill-opacity="0.2" />
        <rect x="115" y="65" width="50" height="8" rx="4" fill="#BA1A1A" fill-opacity="0.3" />
        
        <!-- Error snippet box -->
        <rect x="70" y="85" width="100" height="48" rx="8" fill="#FFF1F2" stroke="#FECDD3" stroke-width="1" />
        <path d="M85 109L97 97M97 109L85 97" stroke="#BA1A1A" stroke-width="2.5" stroke-linecap="round" />
        <line x1="108" y1="100" x2="155" y2="100" stroke="#BA1A1A" stroke-width="2.5" stroke-linecap="round" />
        <line x1="108" y1="108" x2="140" y2="108" stroke="#BA1A1A" stroke-opacity="0.5" stroke-width="2" stroke-linecap="round" />
    </g>

    <!-- Floating Badge -->
    <g transform="translate(155, 38)">
        <circle cx="14" cy="14" r="14" fill="#BA1A1A" />
        <text x="14" y="19" fill="#FFFFFF" font-size="14" font-weight="900" font-family="Inter, sans-serif" text-anchor="middle">!</text>
    </g>

    <defs>
        <filter id="drop-shadow-400" x="45" y="38" width="150" height="130" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
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

@section('title', 'Permintaan Tidak Valid')

@section('message')
    Server tidak dapat memproses permintaan Anda karena format data, sintaks, atau parameter URL yang dikirim tidak sesuai dengan spesifikasi sistem.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Pastikan nilai formulir, tanggal, atau nominal diisi dengan format yang benar.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Hindari memodifikasi tautan atau query URL secara manual.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Muat ulang halaman dan ulangi proses input data dari awal.</span>
    </li>
@endsection

@section('actions')
    <a href="{{ url('/dashboard') }}" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">dashboard</span>
        <span>Kembali ke Dashboard</span>
    </a>

    <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'" class="btn btn-secondary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">arrow_back</span>
        <span>Halaman Sebelumnya</span>
    </button>
@endsection
