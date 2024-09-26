// Función para escapar caracteres especiales y prevenir inyección de código malicioso en el HTML
const escapeHtml = (unsafe) => {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};

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
        // Crear nuevo permiso
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

        // Editar permiso
        const handleEditButtonClick = (event) => {
            const button = event.currentTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
        
            const modalBody = document.querySelector('#modalPurple .modal-body');
            modalBody.innerHTML = `
            <form id="update-form-${id}" action="/permisos/${id}" method="POST">
                <input type="hidden" name="_token" value="${csrfToken}">
                <input type="hidden" name="_method" value="PUT">
                <div class="form-group mb-4">
                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Nombre del Permiso</label>
                    <input type="text" class="form-control block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" id="name" name="name" value="${escapeHtml(name)}" required>
                </div>
                <!-- Botón ahora es type="button" para evitar el envío automático -->
                <button type="button" class="btn btn-primary bg-blue hover:bg-cyan-200 text-white font-bold py-2 px-4 rounded-md" onclick="confirmUpdate(${id})">Guardar Cambios</button>
            </form>`;
        };
        

        // Añade event listeners a los botones de edición
        document.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', handleEditButtonClick);
        });

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
});

const confirmDeshabilitar = (permissionId) => {
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
            document.getElementById(`deshabilitar-form-${permissionId}`).submit();
        }
    });
};

function confirmUpdate(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¿Deseas actualizar el nombre del permiso?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Si el usuario confirma, envía el formulario
            document.getElementById(`update-form-${id}`).submit();
        }
    });
}
