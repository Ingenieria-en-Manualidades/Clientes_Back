// Función para obtener usuarios deshabilitados
async function fetchDeletedUsers() {
    try {
        const response = await fetch('/usuarios/deshabilitados');
        const data = await response.json();
        updateUserTable(data, 'No se encontraron datos eliminados.');
        updateTrashButton('<-- Volver Atras', 'btn-warning', 'btn-primary', fetchActiveUsers);
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


//PARA CREAR EL USUARIO 
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
                    <label for="clientes" c
                    lass="block text-gray-700 text-sm font-bold mb-2">Clientes</label>
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
                    <p id="password-error" class="text-red-500 text-sm mt-1 hidden">La contraseña debe ser alfanumérica y tener al menos 8 caracteres.</p>
                </div>
                <div class="form-group mb-4">
                    <label for="password_confirmation" class="block text-gray-700 text-sm font-bold mb-2">Repetir Contraseña</label>
                    <input type="password" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="password_confirmation" name="password_confirmation" required>
                    <p id="password-confirm-error" class="text-red-500 text-sm mt-1 hidden">Las contraseñas no coinciden.</p>
                </div>
                <button type="submit" id="confirm-change-btn" class="btn btn-primary bg-blue hover:bg-cyan-200  hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-md" disabled>Crear Usuario</button>
            </form>`;

        // Desplegar lista de clientes
        $('#toggle-clientes').on('click', () => {
            $('#clientes-list').toggleClass('hidden');
        });

        $(document).on('click', (event) => {
            if (!$(event.target).closest('#toggle-clientes').length && !$(event.target).closest('#clientes-list').length) {
                $('#clientes-list').addClass('hidden');
            }
        });

        // Validación de contraseñas en tiempo real
        const passwordInput = document.getElementById('password');
        const passwordConfirmationInput = document.getElementById('password_confirmation');
        const passwordError = document.getElementById('password-error');
        const passwordConfirmError = document.getElementById('password-confirm-error');
        const confirmButton = document.getElementById('confirm-change-btn');
        const passwordRegex = /^(?=.*[a-zA-Z])(?=.*\d)[A-Za-z\d]{8,}$/;

        const checkPasswordValidity = () => {
            const isPasswordValid = passwordRegex.test(passwordInput.value);
            const doPasswordsMatch = passwordInput.value === passwordConfirmationInput.value;

            if (isPasswordValid) {
                passwordError.classList.add('hidden');
            } else {
                passwordError.classList.remove('hidden');
            }

            if (doPasswordsMatch) {
                passwordConfirmError.classList.add('hidden');
            } else {
                passwordConfirmError.classList.remove('hidden');
            }

            // Habilitar o deshabilitar el botón si las contraseñas son válidas
            confirmButton.disabled = !(isPasswordValid && doPasswordsMatch);
        };

        passwordInput.addEventListener('input', checkPasswordValidity);
        passwordConfirmationInput.addEventListener('input', checkPasswordValidity);
    };

    // Asignar manejador de eventos al botón de crear usuario
    $('#create-button').on('click', handleCreateButtonClick);

//FIN CREAR USUARIO


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
                            <option value="">Seleccionar Rol</option>
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
                    <div class="flex justify-between">
                         <button type="submit" class="btn btn-primary bg-blue hover:bg-cyan-200  hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-md">Guardar cambios</button>
                        <button type="button" id="toggle-active-button" class="btn ${usuarioActivo === 's' ? 'btn-danger' : 'btn-success'} font-bold py-2 px-4 rounded-md">
                            ${usuarioActivo === 's' ? 'Desactivar Usuario' : 'Activar Usuario'}
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


            // SweetAlert para confirmar activar o desactivar usuario
            document.getElementById('toggle-active-button').addEventListener('click', function () {
                const action = usuarioActivo === 's' ? 'desactivar' : 'activar';
                Swal.fire({
                    title: `¿Estás seguro de que deseas ${action} este usuario?`,
                    text: `El usuario será ${action}ado.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: `Sí, ${action}!`,
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
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
                                        text: `El usuario ha sido ${action}ado.`,
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
                    }
                });
            });

            // SweetAlert para confirmar guardar cambios
            document.getElementById('user-edit-form').addEventListener('submit', function (e) {
                e.preventDefault(); // Prevenir el envío automático del formulario
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¿Deseas guardar los cambios?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, guardar!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit(); // Enviar el formulario si se confirma la acción
                    }
                });
            });
        });
    });



    // Asignar manejador de eventos a los botones de resetear contraseña del usuario usuario
    document.querySelectorAll('.reset-button').forEach(button => {
        button.addEventListener('click', function () {
            const userId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-nombre');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            Swal.fire({
                title: '¿Restablecer contraseña?',
                text: `¿Deseas restablecer la contraseña del usuario "${userName}"? Se asignará una contraseña temporal.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, restablecer',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/usuarios/${userId}/reset-user`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.fire({
                            title: data.title,
                            text: data.message,
                            icon: data.success ? 'success' : 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: 'Ocurrió un error al restablecer la contraseña.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    });
                }
            });
        });
    });
});

