@if(session('success'))
<x-adminlte-callout theme="success" class="bg-teal" icon="fas fa-lg fa-thumbs-up" title="Done" id="success-message">
    <i class="text-dark">{{ session('success') }}</i>
</x-adminlte-callout>
@endif
@if ($errors->any())
<x-adminlte-alert theme="danger" title="Danger" icon="fas fa-lg fa-exclamation-triangle" id="error-message">
    @foreach ($errors->all() as $error)
    <li >{{ $error }}</li>
    @endforeach
    </x-adminlte-alert>
@endif