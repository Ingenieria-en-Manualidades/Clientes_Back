@props(['permission'])

<tr>
    <td class="font-bold">{{ $permission->id }}</td>
    <td class="font-bold">{{ $permission->name }}</td>

    <td>
        @can('Gestionar Roles')
        <button class="btn btn-xs btn-default text-primary mx-1 shadow edit-button"
            title="Edit"
            data-toggle="modal"
            data-target="#modalPurple"
            data-id="{{ $permission->id }}"
            data-name="{{ $permission->name }}">
            <i class="fa fa-lg fa-fw fa-pen"></i>
        </button>

        <form id="deshabilitar-form-{{ $permission->id }}" action="{{ route('permisos.destroy', $permission->id) }}" method="POST" class="inline delete-form">
            @csrf
            @method('DELETE')
            <button type="button" onclick="confirmDeshabilitar('{{ $permission->id }}')" class="btn btn-xs btn-default text-danger mx-1 shadow" title="Delete">
                <i class="fa fa-lg fa-fw fa-trash"></i>
            </button>
        </form>
        @endcan
    </td>
</tr>