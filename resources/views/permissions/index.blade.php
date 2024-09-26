@extends('adminlte::page')

@section('title', 'Roles')

@section('content_header')
    <h1>Permisos</h1>
@stop

@section('content')
<div class="mt-4 mb-4">
@include('partials.alerts')
<div class="flex justify-between mb-4">
    <div class="flex justify-start space-x-4"> 
        @include('partials.permisos.create_permission_button')
    </div>
</div>

    @php
    $heads = [
        'ID',
        'Nombres Permisos',
        ['label' => 'Acciones', 'no-export' => true, 'width' => 5],
    ];
    @endphp

    <x-adminlte-datatable id="table3" :heads="$heads" head-theme="dark" theme="light" striped hoverable>
        @foreach($permissions as $permission)
            <x-permission-row :permission="$permission" />
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
    <!-- <meta name="csrf-token" content="{{ csrf_token() }}"> -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.2.2/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/permisos.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('#table3').DataTable();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.full.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();
        });
    </script>
@stop
