@props(['role'])

<tr>
    <td class="font-bold">{{ $role->id }}</td>
    <td class="font-bold">{{ $role->name }}</td>
    <td class="font-bold">
        @foreach($role->permissions as $permission)
        <span class="badge bg-info text-dark">{{ $permission->name }}</span>
        @endforeach
    </td>
    <td>
        <button class="btn btn-xs btn-default text-primary mx-1 shadow edit-button" 
                title="Edit" 
                data-toggle="modal" 
                data-target="#modalPurple" 
                data-id="{{ $role->id }}" 
                data-name="{{ $role->name }}" 
                data-rol-id="{{ $role->id }}" 
                data-permissions='@json($role->permissions->pluck("name"))'>
            <i class="fa fa-lg fa-fw fa-pen"></i>
        </button>
        <form id="deshabilitar-form-{{ $role->id }}" action="{{ route('roles.destroy', $role->id) }}" method="POST" class="inline delete-form">
            @csrf
            @method('DELETE')
            <button type="button" onclick="confirmDeshabilitar('{{ $role->id }}')" class="btn btn-xs btn-default text-danger mx-1 shadow" title="Delete">
                <i class="fa fa-lg fa-fw fa-trash"></i>
            </button>
        </form>
    </td>
</tr>
