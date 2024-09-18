// Espera a que el DOM esté completamente cargado antes de ejecutar el código
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Obtener los roles mediante AJAX
    fetch('/api/roles', {
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
        .then(response => response.json())
        .then(data => {
            // Obtiene todos los permisos únicos de los roles
            const roles = data.roles;
            const allPermissions = data.permissions.map(permission => permission.name);  // Obtén todos los permisos únicos

            // Genera los checkboxes para los permisos
            const generatePermissionsCheckboxes = (selectedPermissions = []) => {
                return allPermissions.map(permission => {
                    const checked = selectedPermissions.includes(permission) ? 'checked' : '';
                    return `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="${escapeHtml(permission)}" id="permission-${escapeHtml(permission)}" ${checked}>
                    <label class="form-check-label" for="permission-${escapeHtml(permission)}">
                        ${escapeHtml(permission)}
                    </label>
                </div>`;
                }).join('');
            };

            // Maneja el clic en el botón de editar
            const handleEditButtonClick = (event) => {
                const button = event.currentTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const permissions = JSON.parse(button.getAttribute('data-permissions'));

                const modalBody = document.querySelector('#modalPurple .modal-body');
                modalBody.innerHTML = `
                <form action="/roles/${id}" method="POST">
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <input type="hidden" name="_method" value="PUT">
                    <div class="form-group mb-4">
                        <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Nombre del Rol</label>
                        <input type="text" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="name" name="name" value="${escapeHtml(name)}" required>
                    </div>
                    <div class="form-group mb-4">
                        <label for="permissions" class="block text-gray-700 text-sm font-bold mb-2">Permisos</label>
                        <div id="permissions">
                            ${generatePermissionsCheckboxes(permissions)}
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary bg-blue hover:bg-cyan-200 text-white font-bold py-2 px-4 rounded-md">Guardar Cambios</button>
                </form>`;
            };
            // Añade event listeners a los botones de edición
            document.querySelectorAll('.edit-button').forEach(button => {
                button.addEventListener('click', handleEditButtonClick);
            });

            // Maneja el clic en el botón de crear
            const handleCreateButtonClick = () => {
                const modalBody = document.querySelector('#modalPurple .modal-body');
                modalBody.innerHTML = `
                <form action="/roles" method="POST">
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <div class="form-group mb-4">
                        <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Nombre del Rol</label>
                        <input type="text" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="name" name="name" required>
                    </div>
                    <div class="form-group mb-4">
                        <label for="permissions" class="block text-gray-700 text-sm font-bold mb-2">Permisos</label>
                        <div id="permissions">
                            ${generatePermissionsCheckboxes()}
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary bg-blue hover:bg-cyan-200 text-white font-bold py-2 px-4 rounded-md">Crear Rol</button>
                </form>`;
            };
            // Añade event listener al botón de crear
            document.getElementById('create-button').addEventListener('click', handleCreateButtonClick);

            


            // Función para ocultar automáticamente los mensajes de éxito y error
            const autoHideMessages = () => {
                setTimeout(() => {
                    $('#success-message').fadeOut('slow');
                    $('#error-message').fadeOut('slow');
                }, 2000);
            };
            autoHideMessages();
        })
        .catch(error => {
            console.error('Error fetching roles:', error);
            displayError('Ocurrió un error al obtener los datos de los roles.');
        });


    //crear permiso
    const handlePermissionButtonClick = () => {
        const modalBody = document.querySelector('#modalPurple .modal-body');
        modalBody.innerHTML = `
            <form action="/permissions" method="POST">
                <input type="hidden" name="_token" value="${csrfToken}">
                <div class="form-group mb-4">
                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Nuevo permiso</label>
                    <input type="text" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="name" name="name" required>
                </div>
                <button type="submit" class="btn btn-primary bg-blue hover:bg-cyan-200 text-white font-bold py-2 px-4 rounded-md">Crear Permisos</button>
            </form>`;
    };

    document.getElementById('permission-button').addEventListener('click', handlePermissionButtonClick);


});

// Muestra una alerta de confirmación utilizando SweetAlert
const confirmDeshabilitar = (roleId) => {
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
            document.getElementById(`deshabilitar-form-${roleId}`).submit();
        }
    });
};

// Función para obtener y mostrar los roles eliminados
const fetchDeletedRoles = async () => {
    try {
        const response = await fetch('/roles/deshabilitados', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const data = await response.json();
        updateRoleTable(data, 'No se encontraron datos eliminados.');
        updateTrashButton('<-- Volver Atras', 'btn-warning', 'btn-primary', fetchActiveRoles);

    } catch (error) {
        console.error('Error fetching deleted roles:', error);
        displayError('Ocurrió un error al obtener los datos eliminados.');
    }
};

// Recarga la página para mostrar los roles activos
const fetchActiveRoles = () => {
    location.reload();
};

// Actualiza la tabla de roles con los datos obtenidos
const updateRoleTable = (data, emptyMessage) => {
    const table = $('#table3').DataTable();
    table.clear().draw();
    if (data.length === 0) {
        $('#table3 tbody').append(`<tr><td colspan="4" class="text-center">${escapeHtml(emptyMessage)}</td></tr>`);
    } else {
        data.forEach(role => {
            table.row.add([
                role.id,
                escapeHtml(role.name),
                role.permissions ? role.permissions.map(permission => `<span class="badge bg-info text-dark">${escapeHtml(permission)}</span>`).join(' ') : 'null',
                `<nobr>
                    <a href="/roles/${role.id}/restaurar" class="btn btn-xs btn-default text-primary mx-1 shadow" title="Restaurar">
                        <i class="fa fa-lg fa-fw fa-undo"></i>
                    </a>
                </nobr>`
            ]);
        });
        table.draw();
    }
};

// Actualiza el botón de basura para mostrar los roles activos
const updateTrashButton = (label, oldClass, newClass, onClickHandler) => {
    const trashButton = document.getElementById('trash-button');
    if (trashButton) {
        trashButton.innerHTML = escapeHtml(label);
        trashButton.classList.remove(oldClass);
        trashButton.classList.add(newClass);
        trashButton.onclick = onClickHandler;
    }
};

// Muestra un mensaje de error en la tabla
const displayError = (message) => {
    $('#table3 tbody').append(`<tr><td colspan="4" class="text-center text-danger">${escapeHtml(message)}</td></tr>`);
};

// Escapar HTML para evitar XSS
const escapeHtml = (unsafe) => {
    if (typeof unsafe !== 'string') {
        return '';
    }
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};




