@extends('errors.layout')

@section('title', 'Akses Dibatasi')
@section('code', '403')
@section('status_text', 'Forbidden')
@section('category', 'Otorisasi & Wewenang')
@section('category_icon', 'shield_lock')
@section('badge_icon', 'lock')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="120" cy="100" r="85" fill="#BA1A1A" fill-opacity="0.04" />
    <circle cx="120" cy="100" r="65" stroke="#BA1A1A" stroke-opacity="0.12" stroke-width="1.5" stroke-dasharray="4 4" />
    <g filter="url(#drop-shadow-403)">
        <path d="M120 30L175 52V104C175 142 148 168 120 178C92 168 65 142 65 104V52L120 30Z" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <path d="M120 42L162 59V101C162 132 141 153 120 162C99 153 78 132 78 101V59L120 42Z" fill="linear-gradient(180deg, rgba(0, 39, 70, 0.05) 0%, rgba(186, 26, 26, 0.08) 100%)" />
        <rect x="100" y="96" width="40" height="34" rx="8" fill="#002746" />
        <path d="M107 96V84C107 76.8203 112.82 71 120 71C127.18 71 133 76.8203 133 84V96" stroke="#002746" stroke-width="5" stroke-linecap="round" />
        <circle cx="120" cy="110" r="3.5" fill="#FFFFFF" />
        <path d="M118 110L117 122H123L122 110H118Z" fill="#FFFFFF" />
    </g>
    <g transform="translate(160, 40)">
        <circle cx="14" cy="14" r="14" fill="#BA1A1A" />
        <path d="M9 14H19" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
    </g>
    <g transform="translate(48, 120)">
        <rect width="32" height="22" rx="6" fill="#006D3D" />
        <text x="16" y="15" fill="#FFFFFF" font-size="10" font-weight="bold" font-family="Inter, sans-serif" text-anchor="middle">ROLE</text>
    </g>
    <defs>
        <filter id="drop-shadow-403" x="55" y="24" width="130" height="166" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
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
    Anda tidak memiliki hak akses (permission) yang memadai untuk membuka modul, data keuangan, atau tindakan ini. Area ini dibatasi khusus untuk wewenang pengguna tertentu.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Pastikan Anda masuk menggunakan akun dengan hak akses yang sesuai (Superadmin, Supervisor, Bendahara, atau Pengelola).</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Hubungi administrator BUMDesma / Tenancy Anda untuk mengajukan penambahan izin atau wewenang.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Jika baru saja diberikan perubahan hak akses, silakan logout dan login kembali untuk memperbarui sesi Anda.</span>
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

    <a href="{{ url('/login') }}" class="btn btn-ghost">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">switch_account</span>
        <span>Ganti Akun</span>
    </a>
@endsection