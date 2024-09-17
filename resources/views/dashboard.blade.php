@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
<h1>Dashboard</h1>
<!-- <button id="darkModeToggle">Cambiar Modo</button> -->

@stop

@section('content')
<div class="row">
    @include('partials.dashboard.card', [
        'color' => 'info',
        'count' => $activeClients,
        'title' => 'Total de clientes Activos',
        'icon' => 'fas fa-users',
        'link' => '#',
        'linkText' => 'Más información'
    ])

    @include('partials.dashboard.card', [
        'color' => 'success',
        'count' => $rolesCount,
        'title' => 'Total de Roles',
        'icon' => 'fas fa-user-lock',
        'link' => '#',
        'linkText' => 'Más información'
    ])

    @include('partials.dashboard.card', [
        'color' => 'warning',
        'count' => $activeUsers,
        'title' => 'Usuarios Activos',
        'icon' => 'fas fa-user-tag',
        'link' => '#',
        'linkText' => 'Más información'
    ])
</div>
@stop

@section('css')
{{-- Add here extra stylesheets --}}
{{-- <link rel="stylesheet" href="/css/admin_custom.css"> --}}
@stop

@section('js')
<!-- <script>
document.getElementById('darkModeToggle').addEventListener('click', function() {
    document.body.classList.toggle('dark-mode');
});
</script> -->
@stop
