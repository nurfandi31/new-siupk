@extends('errors.layout')

@section('title')
    @yield('title', 'Terjadi Kesalahan')
@endsection

@section('code')
    @yield('code', '500')
@endsection

@section('message')
    @yield('message', 'Terjadi kendala saat memproses permintaan Anda.')
@endsection
