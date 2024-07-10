@extends('adminlte::page')

@section('title', '404 - Página no encontrada')

@section('content_header')
    <h1 class="text-2xl font-bold text-gray-800">Página no encontrada</h1>
@stop

@section('content')
    <div class="flex flex-col items-center justify-center h-3/4">
        <h2 class="text-6xl text-yellow-500">404</h2>
        <div class="text-center mt-4">
            <h3 class="text-xl text-yellow-500"><i class="fas fa-exclamation-triangle"></i> Oops! Página no encontrada.</h3>
            <p class="mt-2 text-gray-600">
                No pudimos encontrar la página que estabas buscando.
                Mientras tanto, puedes <a href="{{ url('/dashboard') }}" class="text-blue-500 underline">regresar al inicio</a>.
            </p>
        </div>
    </div>
@stop

@section('css')
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
@stop

@section('js')
    <script> console.log("Página 404 no encontrada"); </script>
@stop
