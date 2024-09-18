@props(['usuario'])

<tr class="{{ $usuario->activo === 'n' ? 'text-red-600' : '' }}">
    <td class="font-bold">{{ $usuario->id }}</td>
    <td class="font-bold">{{ $usuario->name }}</td>
    <td class="font-bold">
        {{ $usuario->roles->isNotEmpty() ? $usuario->roles->pluck('name')->implode(', ') : 'null' }} 
     </td><!--si el usuaio no tiene rol pues sera null -->

    <td>
        <nobr>
        @can('activar/desactivar clientes')
            <button class="btn btn-xs btn-default text-primary mx-1 shadow edit-button" title="Editar Usuario" data-toggle="modal" data-target="#modalPurple"   data-usuario-activo="{{ $usuario->activo }}"   data-id="{{ $usuario->id }}" data-nombre="{{ $usuario->name }}" data-rol-id="{{ $usuario->roles->pluck('id')->implode(',') }}" data-clientes-seleccionados="{{ $usuario->clientes->pluck('id')->implode(',') }}">
                <i class="fa fa-lg fa-fw fa-pen"></i>
            </button>
        
            <button class="btn btn-xs btn-default text-primary mx-1 shadow reset-button" title="Restablecer Contraseña" data-toggle="modal" data-target="#modalPurple"   data-usuario-activo="{{ $usuario->activo }}"   data-id="{{ $usuario->id }}" data-nombre="{{ $usuario->name }}">
                <i class="fa fa-lg fa-fw fa-undo"></i>
            </button>
            <form id="deshabilitar-form-{{ $usuario->id }}" action="{{ route('usuarios.destroy', $usuario->id) }}" method="POST" class="inline delete-form">
                @csrf
                @method('DELETE')
                <button type="button" onclick="confirmDeshabilitar('{{ $usuario->id }}')" class="btn btn-xs btn-default text-danger mx-1 shadow" title="Eliminar">
                    <i class="fa fa-lg fa-fw fa-trash"></i>
                </button>
            </form>
            @endcan
        </nobr>
    </td>
</tr>