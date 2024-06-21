@extends('adminlte::page')

@section('title', 'API Tokens')

@section('content_header')
    <h1>API Tokens</h1>
@stop

@section('content')
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('api.api-token-manager')
    </div>
@stop

@section('css')
    {{-- Add here extra stylesheets --}}
    {{-- <link rel="stylesheet" href="/css/admin_custom.css"> --}}
    @livewireStyles
@stop

@section('js')
    <script> console.log("Hi, I'm using the Laravel-AdminLTE package!"); </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@stop
