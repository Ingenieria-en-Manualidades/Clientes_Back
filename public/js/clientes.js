// Confirmar deshabilitar cliente
function confirmDeshabilitar(clienteId) {
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
            document.getElementById(`deshabilitar-form-${clienteId}`).submit();
        }
    });
}

// Escuchar clics en los botones de edición usando delegación de eventos
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edit-button').forEach(button => {
        button.addEventListener('click', handleEditButtonClick);
    });
});
// Evento para manejar clic en botón de edición
const handleEditButtonClick = (event) => {
    const button = event.currentTarget;
    const id = button.getAttribute('data-id');
    const name = button.getAttribute('data-nombre');
    const clienteActivo = button.getAttribute('data-cliente-activo');

    const modalBody = document.querySelector('#modalPurple .modal-body');
    modalBody.innerHTML = `
    <form id="toggle-form" action="/clientes/${id}" method="POST">
        <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
        <input type="hidden" name="_method" value="PUT">
        
         <div>
            <p>Nombre del Cliente: ${escapeHtml(name)}</p>
              <button type="submit" id="toggle-button" class="btn mt-3 ${clienteActivo === 's' ? 'btn-danger' : 'btn-success'}">
                ${clienteActivo === 's' ? 'Desactivar' : 'Activar'}
              </button>
         </div>
        </form>`;

    const toggleForm = document.getElementById('toggle-form');
    const toggleButton = document.getElementById('toggle-button');
    toggleButton.addEventListener('click', () => {
        toggleForm.submit();
    });
};




// Funciones adicionales
async function fetchDeletedClients() {
    try {
        const response = await fetch('/clientes/deshabilitados', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const data = await response.json();
        updateClientTable(data, 'No se encontraron datos eliminados.');
        updateTrashButton('<-- Volver Atras', 'btn-warning', 'btn-primary', fetchActiveClients);
    } catch (error) {
        console.error('Error fetching deleted clients:', error);
        displayError('Ocurrió un error al obtener los datos eliminados.');
    }
}

function fetchActiveClients() {
    location.reload();
}

function updateClientTable(data, emptyMessage) {
    const table = $('#table3').DataTable();
    table.clear().draw();
    if (data.length === 0) {
        $('#table3 tbody').append(`<tr><td colspan="4" class="text-center">${escapeHtml(emptyMessage)}</td></tr>`);
    } else {
        data.forEach(cliente => {
            table.row.add([
                cliente.id,
                escapeHtml(cliente.nombre ?? ''),
                escapeHtml(cliente.cliente_id ?? ''),
                `<nobr>
                    <a href="/clientes/${cliente.id}/restaurar" class="btn btn-xs btn-default text-primary mx-1 shadow" title="Restaurar">
                        <i class="fa fa-lg fa-fw fa-undo"></i>
                    </a>
                </nobr>`
            ]);
        });
        table.draw();
    }
}

function updateTrashButton(label, oldClass, newClass, onClickHandler) {
    const trashButton = document.getElementById('trash-button');
    if (trashButton) {
        trashButton.innerHTML = escapeHtml(label);
        trashButton.classList.remove(oldClass);
        trashButton.classList.add(newClass);
        trashButton.onclick = onClickHandler;
    }
}

function displayError(message) {
    $('#table3 tbody').append(`<tr><td colspan="4" class="text-center text-danger">${escapeHtml(message)}</td></tr>`);
}

function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') {
        return '';
    }
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Ocultar mensajes de éxito y error después de un tiempo
$(document).ready(function() {
    setTimeout(function() {
        $('#success-message').fadeOut('slow');
        $('#error-message').fadeOut('slow');
    }, 2000);

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
});
