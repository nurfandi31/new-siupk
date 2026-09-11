@extends('errors.layout')

@section('title', 'Langganan Tenant Diperlukan')
@section('code', '402')
@section('status_text', 'Payment Required')
@section('category', 'Langganan & Tagihan')
@section('category_icon', 'credit_card')
@section('badge_icon', 'payments')

@section('illustration')
<svg width="240" height="200" viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="120" cy="100" r="85" fill="#D97706" fill-opacity="0.05" />
    <circle cx="120" cy="100" r="65" stroke="#D97706" stroke-opacity="0.15" stroke-width="1.5" stroke-dasharray="4 4" />
    <g filter="url(#drop-shadow-402)">
        <rect x="45" y="55" width="150" height="96" rx="14" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
        <rect x="45" y="75" width="150" height="20" fill="#002746" />
        <rect x="62" y="105" width="22" height="16" rx="3" fill="#D97706" fill-opacity="0.25" stroke="#D97706" stroke-width="1.2" />
        <line x1="62" y1="113" x2="84" y2="113" stroke="#D97706" stroke-width="1" />
        <line x1="73" y1="105" x2="73" y2="121" stroke="#D97706" stroke-width="1" />
        <rect x="94" y="108" width="60" height="5" rx="2.5" fill="#64748B" fill-opacity="0.3" />
        <rect x="94" y="118" width="40" height="5" rx="2.5" fill="#64748B" fill-opacity="0.2" />
    </g>
    <g transform="translate(160, 42)">
        <circle cx="16" cy="16" r="16" fill="#D97706" />
        <text x="16" y="21" fill="#FFFFFF" font-size="14" font-weight="900" font-family="Inter, sans-serif" text-anchor="middle">Rp</text>
    </g>
    <defs>
        <filter id="drop-shadow-402" x="35" y="48" width="170" height="116" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
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
    Masa aktif langganan instansi BUMDesma Anda telah berakhir atau akun tenant memerlukan paket berlangganan aktif untuk dapat menggunakan modul ini.
@endsection

@section('recommendations')
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Periksa status tagihan dan langganan aktif pada menu Pengaturan Billing & Invoicing.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Lakukan perpanjangan paket melalui opsi pembayaran instan yang tersedia.</span>
    </li>
    <li class="recommendation-item">
        <span class="material-symbols-outlined recommendation-icon">check_circle</span>
        <span>Jika telah melakukan pembayaran tetapi status belum aktif, hubungi Admin siupk Next.</span>
    </li>
@endsection

@section('actions')
    <a href="{{ url('/billing') }}" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">receipt_long</span>
        <span>Menu Tagihan & Billing</span>
    </a>

    <a href="{{ url('/dashboard') }}" class="btn btn-secondary">
        <span class="material-symbols-outlined" style="font-size: 1.15rem;">dashboard</span>
        <span>Ke Dashboard</span>
    </a>
@endsection