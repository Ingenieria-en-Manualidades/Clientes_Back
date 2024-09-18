@props(['cliente'])

<tr class="{{ $cliente->activo === 'n' ? 'text-red-600' : '' }}">
    <td class="font-bold">{{ $cliente->id }}</td>
    <td class="font-bold">{{ $cliente->nombre }}</td>
    <td class="font-bold">{{ $cliente->cliente_endpoint_id }}</td>
    <td>
        <nobr>
          @can('GESTIONAR CLIENTES')
            <button class="btn btn-xs btn-default text-primary mx-1 shadow edit-button" 
                    title="Edit" 
                    data-toggle="modal" 
                    data-target="#modalPurple"
                    data-id="{{ $cliente->id }}" 
                    data-nombre="{{ $cliente->nombre }}" 
                    data-cliente-activo="{{ $cliente->activo }}"
                    data-cliente-id="{{ $cliente->cliente_id }}">
                <i class="fa fa-lg fa-fw fa-pen"></i>
            </button>
            <form id="deshabilitar-form-{{ $cliente->id }}" action="{{ route('clientes.destroy', $cliente->id) }}" method="POST" class="inline delete-form">
                @csrf
                @method('DELETE')
                <button type="button" onclick="confirmDeshabilitar('{{ $cliente->id }}')" class="btn btn-xs btn-default text-danger mx-1 shadow" title="Delete">
                    <i class="fa fa-lg fa-fw fa-trash"></i>
                </button>
            </form>
            @endcan
        </nobr>
    </td>
</tr>
