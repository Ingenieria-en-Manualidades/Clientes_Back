// Función para obtener usuarios deshabilitados
async function fetchDeletedUsers() {
    try {
        const response = await fetch('/usuarios/deshabilitados');
        const data = await response.json();
        updateUserTable(data, 'No se encontraron datos eliminados.');
        updateTrashButton('Regresar', 'btn-warning', 'btn-success', fetchActiveUsers);
    } catch (error) {
        console.error('Error fetching deleted users:', error);
        displayError('Ocurrió un error al obtener los datos eliminados.');
    }
}

// Función para actualizar la tabla de usuarios
const updateUserTable = (data, emptyMessage) => {
    let table = $('#table3').DataTable();
    table.clear().draw();

    if (data.length === 0) {
        $('#table3 tbody').append(`<tr><td colspan="4" class="text-center">${emptyMessage}</td></tr>`);
    } else {
        data.forEach(usuario => {
            let roles = usuario.roles && usuario.roles.length > 0
                ? usuario.roles.map(role => `<span class="badge bg-info text-dark">${role.name}</span>`).join(' ')
                : 'null';

            table.row.add([
                usuario.id,
                usuario.name,
                roles,
                `<nobr>
                    <a href="/usuarios/${usuario.id}/restaurar" class="btn btn-xs btn-default text-primary mx-1 shadow" title="restaurar">
                       <i class="fa fa-lg fa-fw fa-undo"></i>
                    </a>
                </nobr>`
            ]);
        });
        table.draw();
    }
};

// Función para actualizar el botón de papelera
const updateTrashButton = (label, oldClass, newClass, onClickHandler) => {
    const trashButton = document.getElementById('trash-button');
    if (trashButton) {
        trashButton.innerHTML = label;
        trashButton.classList.remove(oldClass);
        trashButton.classList.add(newClass);
        trashButton.onclick = onClickHandler;
    }
};

// Función para mostrar un mensaje de error en la tabla
const displayError = (message) => {
    $('#table3 tbody').append(`<tr><td colspan="4" class="text-center text-danger">${message}</td></tr>`);
};

// Función para obtener usuarios activos (refresca la página)
const fetchActiveUsers = () => {
    location.reload();
};

// Función para confirmar la deshabilitación de un usuario
const confirmDeshabilitar = (userId) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡OMG!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, deshabilitar',
        cancelButtonText: 'No, cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById(`deshabilitar-form-${userId}`).submit();
        }
    });
};

// Función para auto-ocultar mensajes de éxito y error
const autoHideMessages = () => {
    setTimeout(() => {
        $('#success-message').fadeOut('slow');
        $('#error-message').fadeOut('slow');
    }, 3000);
};

document.addEventListener('DOMContentLoaded', async () => {
    autoHideMessages();

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let roles = [];
    let clientes = [];

    try {
        const response = await fetch('/api/roles-clientes');
        const data = await response.json();
        roles = data.roles;
        clientes = data.clientes;
    } catch (error) {
        console.error('Error fetching roles and clients:', error);
        displayError('Ocurrió un error al obtener los datos.');
    }

    // Función para manejar el clic en el botón de crear
    const handleCreateButtonClick = () => {
        const modalBody = document.querySelector('#modalPurple .modal-body');
        const rolesOptions = roles.map(role => `<option value="${role.id}">${role.name}</option>`).join('');
        const clientesList = clientes.map(cliente => `
            <li>
                <label class="flex items-center space-x-3 py-2 px-4 hover:bg-gray-100">
                    <input type="checkbox" class="form-checkbox h-5 w-5 text-indigo-600" value="${cliente.id}" name="clientes[]">
                    <span class="text-gray-700">${cliente.nombre}</span>
                </label>
            </li>`).join('');
    
        modalBody.innerHTML = `
            <form id="createUserForm" action="/usuarios" method="POST">
                <input type="hidden" name="_token" value="${csrfToken}">
                <div class="form-group mb-4">
                    <label for="nombre" class="block text-gray-700 text-sm font-bold mb-2">Nombre de Usuario</label>
                    <input type="text" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="nombre" name="nombre" required>
                </div>
                <div class="form-group mb-4">
                    <label for="rol" class="block text-gray-700 text-sm font-bold mb-2">Rol</label>
                    <select name="rol" id="rol" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                        <option value="">Seleccionar Cliente</option>
                        ${rolesOptions}
                    </select>
                </div>
                <div class="form-group mb-4">
                    <label for="clientes" class="block text-gray-700 text-sm font-bold mb-2">Clientes</label>
                    <div class="relative">
                        <button type="button" id="toggle-clientes" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 text-left">
                            Selecciona clientes
                        </button>
                        <div id="clientes-list" class="hidden absolute mt-2 w-full rounded-md shadow-lg bg-white z-10">
                            <ul class="max-h-48 overflow-y-auto">
                                ${clientesList}
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-4">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Contraseña</label>
                    <input type="password" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="password" name="password" required>
                </div>
                <div class="form-group mb-4">
                    <label for="password_confirmation" class="block text-gray-700 text-sm font-bold mb-2">Repetir Contraseña</label>
                    <input type="password" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="password_confirmation" name="password_confirmation" required>
                </div>
                <button type="submit" class="btn btn-primary bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-md">Crear Usuario</button>
            </form>`;
    
        $('#toggle-clientes').on('click', () => {
            $('#clientes-list').toggleClass('hidden');
        });
    
        $(document).on('click', (event) => {
            if (!$(event.target).closest('#toggle-clientes').length && !$(event.target).closest('#clientes-list').length) {
                $('#clientes-list').addClass('hidden');
            }
        });
    
        document.getElementById('createUserForm').addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            if (password !== confirmPassword) {
                event.preventDefault();
                Swal.fire({
                    title: 'Error',
                    text: 'Las contraseñas no coinciden.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    };
    
    // Asignar manejador de eventos al botón de crear usuario
    $('#create-button').on('click', handleCreateButtonClick);

    // Asignar manejador de eventos a los botones de editar usuario
    document.querySelectorAll('.edit-button').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const nombre = this.getAttribute('data-nombre');
            const rolId = this.getAttribute('data-rol-id');
            const usuarioActivo = this.getAttribute('data-usuario-activo');
            const clientesSeleccionados = this.getAttribute('data-clientes-seleccionados').split(',').map(Number);
    
            const modalBody = document.querySelector('#modalPurple .modal-body');
            const rolesOptions = roles.map(role => `<option value="${role.id}"${role.id == rolId ? ' selected' : ''}>${role.name}</option>`).join('');
            const clientesList = clientes.map(cliente => `
                <li>
                    <label class="flex items-center space-x-3 py-2 px-4 hover:bg-gray-100">
                        <input type="checkbox" class="form-checkbox h-5 w-5 text-indigo-600" value="${cliente.id}" name="clientes[]" ${clientesSeleccionados.includes(cliente.id) ? 'checked' : ''}>
                        <span class="text-gray-700">${cliente.nombre}</span>
                    </label>
                </li>`).join('');
    
            modalBody.innerHTML = `
                <form id="user-edit-form" action="/usuarios/${id}" method="POST" class="space-y-4">
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <input type="hidden" name="_method" value="PUT">
                    <div class="form-group mb-4">
                        <label for="nombre" class="block text-gray-700 text-sm font-bold mb-2">Usuario</label>
                        <input type="text" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="nombre" name="nombre" value="${nombre}">
                    </div>
                    <div class="form-group mb-4">
                        <label for="rol" class="block text-gray-700 text-sm font-bold mb-2">Rol</label>
                        <select name="rol" id="rol" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Seleccionar Cliente</option>
                            ${rolesOptions}
                        </select>
                    </div>
                    <div class="form-group mb-4">
                        <label for="clientes" class="block text-gray-700 text-sm font-bold mb-2">Clientes</label>
                        <div class="relative">
                            <button type="button" id="toggle-clientes-edit" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 text-left">
                                Selecciona clientes
                            </button>
                            <div id="clientes-list-edit" class="hidden absolute mt-2 w-full rounded-md shadow-lg bg-white z-10">
                                <ul class="max-h-48 overflow-y-auto">
                                    ${clientesList}
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-4">
                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Cambiar Contraseña</label>
                        <input type="password" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="password" name="password">
                    </div>
                    <div class="form-group mb-4">
                        <label for="password_confirmation" class="block text-gray-700 text-sm font-bold mb-2">Confirmar Contraseña</label>
                        <input type="password" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="password_confirmation" name="password_confirmation">
                    </div>
                    <div class="flex justify-between">
                        <button type="submit" class="btn btn-primary bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-md">Guardar cambios</button>
                        <button type="button" id="toggle-active-button" class="btn ${usuarioActivo === 's' ? 'btn-danger' : 'btn-success'} font-bold py-2 px-4 rounded-md">
                            ${usuarioActivo === 's' ? 'Desactivar' : 'Activar'}
                        </button>
                    </div>
                </form>`;
    
            $('#toggle-clientes-edit').on('click', () => {
                $('#clientes-list-edit').toggleClass('hidden');
            });
    
            $(document).on('click', (event) => {
                if (!$(event.target).closest('#toggle-clientes-edit').length && !$(event.target).closest('#clientes-list-edit').length) {
                    $('#clientes-list-edit').addClass('hidden');
                }
            });
    
            document.getElementById('user-edit-form').addEventListener('submit', function(event) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('password_confirmation').value;
                if (password !== confirmPassword) {
                    event.preventDefault();
                    Swal.fire({
                        title: 'Error',
                        text: 'Las contraseñas no coinciden.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                }
            });
    
            document.getElementById('toggle-active-button').addEventListener('click', function() {
                fetch(`/usuarios/toggle-active/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Éxito',
                            text: 'El estado del usuario ha sido actualizado.',
                            icon: 'success',
                            confirmButtonText: 'Aceptar'
                        });
                        // Actualizar el botón según el nuevo estado
                        const button = document.getElementById('toggle-active-button');
                        if (button.classList.contains('btn-danger')) {
                            button.classList.remove('btn-danger');
                            button.classList.add('btn-success');
                            button.textContent = 'Activar';
                        } else {
                            button.classList.remove('btn-success');
                            button.classList.add('btn-danger');
                            button.textContent = 'Desactivar';
                        }
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: 'Ocurrió un error al actualizar el estado del usuario.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                }).catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: 'Ocurrió un error al actualizar el estado del usuario.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                });
            });
        });
    });
    
    
    
    
});
