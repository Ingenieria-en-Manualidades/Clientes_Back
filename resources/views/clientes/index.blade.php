@extends('adminlte::page')

@section('title', 'Clientes')

@section('content_header')
<h1>Clientes</h1>
@stop

@section('content')
<div class="mt-4 mb-4">
    @include('partials.alerts')
    <div class="flex justify-end mb-4">
        @include('partials.clientes.endpoint_button')
        <x-adminlte-button id="trash-button" label="Eliminados" theme="warning" icon="fa fa-trash" onclick="fetchDeletedClients()" />
    </div>

    @php
    $heads = [
        'ID',
        'Nombre',
        'Cliente ID',
        ['label' => 'Acciones', 'no-export' => true, 'width' => 5],
    ];
    @endphp
    <x-adminlte-datatable id="table3" :heads="$heads" head-theme="dark" theme="light" striped hoverable>
        @foreach ($clientes as $cliente)
        <x-cliente-row :cliente="$cliente" />
        @endforeach
    </x-adminlte-datatable>

    @include('modals.modal')
</div>
@stop

@section('css')
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
@stop

@section('js')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/clientes.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.2.2/dist/cdn.min.js" defer></script>
<script id="clientes-data" type="application/json">
    @json($clientes)
</script>
@stop
