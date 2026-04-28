<?php
session_start();
header('Content-Type: application/javascript');
$usuarioId = isset($_SESSION['usuario_id']) ? addslashes($_SESSION['usuario_id']) : '';
?>

const apiBaseUrl = `${window.location.origin}/api`;

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) {
        alert(message);
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('toast-visible'));

    setTimeout(() => {
        toast.classList.remove('toast-visible');
        setTimeout(() => toast.remove(), 300);
    }, 4200);
}

async function requestApi(endpoint, init = {}) {
    const response = await fetch(`${apiBaseUrl}/${endpoint}`, {
        credentials: 'same-origin',
        ...init
    });

    const text = await response.text();
    const contentType = response.headers.get('Content-Type') || '';

    if (response.redirected || contentType.includes('text/html')) {
        throw new Error('Se ha redirigido a una página HTML. Comprueba que la sesión esté activa y que la API esté en el mismo origen.');
    }

    let data;
    try {
        data = text ? JSON.parse(text) : {};
    } catch (error) {
        throw new Error(`Respuesta no válida del servidor (${response.status}): ${text}`);
    }

    if (!response.ok) {
        const message = data.message || response.statusText || 'Error en la petición';
        throw new Error(`HTTP ${response.status}: ${message}`);
    }

    return data;
}

function cerrarEditarModal() {
    document.getElementById('modalEditarInsumo').style.display = 'none';
    document.getElementById('formEditarInsumo').reset();
}

function confirmarEliminar() {
    const insumoId = document.getElementById('editarInsumoId').value;
    const nombreInsumo = document.getElementById('editarNombre').value;
    
    if (confirm(`¿Estás seguro de que deseas eliminar el insumo "${nombreInsumo}"? Esta acción no se puede deshacer.`)) {
        eliminarInsumo(insumoId);
    }
}

function eliminarInsumo(insumoId) {
    requestApi('actualizar_stock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            accion: 'eliminar_insumo',
            insumo_id: insumoId
        })
    })
    .then(data => {
        if (data.success) {
            showToast('Insumo eliminado correctamente', 'success');
            cerrarEditarModal();
            location.reload();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Error de red al eliminar el insumo. ' + error.message, 'error');
    });
}

// Ajustar Ganado
function ajustarGanado(cambio) {
    requestApi('actualizar_ganado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ cambio: cambio })
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Error de red al actualizar el ganado. ' + error.message, 'error');
    });
}

function abrirModalNuevoInsumo() {
    const modal = document.getElementById('modalEditarInsumo');
    const form = document.getElementById('formEditarInsumo');
    
    // Limpiar el formulario
    form.reset();
    document.getElementById('editarInsumoId').value = '';
    document.getElementById('modalEditarTitulo').textContent = 'Nuevo Insumo';
    document.getElementById('btnGuardarInsumo').textContent = 'Crear Insumo';
    document.getElementById('btnEliminarInsumo').style.display = 'none';
    
    modal.style.display = 'flex';
}

function editarInsumo(id) {
    const modal = document.getElementById('modalEditarInsumo');
    const form = document.getElementById('formEditarInsumo');

    requestApi('actualizar_stock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            accion: 'obtener_insumo',
            insumo_id: id
        })
    })
    .then(data => {
        if (!data.success) {
            showToast('Error: ' + data.message, 'error');
            return;
        }

        const insumo = data.insumo;
        document.getElementById('editarInsumoId').value = insumo.id;
        document.getElementById('editarNombre').value = insumo.nombre;
        document.getElementById('editarTipoInsumo').value = insumo.tipo_insumo || '';
        document.getElementById('editarUnidad').value = insumo.unidad;
        document.getElementById('editarCapacidad').value = insumo.capacidad_maxima;
        document.getElementById('editarStock').value = insumo.stock_actual;
        document.getElementById('editarMinimo').value = insumo.stock_minimo;
        document.getElementById('editarConsumo').value = insumo.consumo_promedio_diario;
        document.getElementById('modalEditarTitulo').textContent = 'Editar Silo';
        document.getElementById('btnGuardarInsumo').textContent = 'Guardar';
        document.getElementById('btnEliminarInsumo').style.display = 'block';

        modal.style.display = 'flex';
    })
    .catch(error => {
        showToast('Error de red al obtener datos del insumo. ' + (error.message || ''), 'error');
    });
}

const formEditarInsumo = document.getElementById('formEditarInsumo');
if (formEditarInsumo) {
    formEditarInsumo.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('accion', 'editar_insumo');
        const insumoId = document.getElementById('editarInsumoId').value;

        requestApi('actualizar_stock.php', {
            method: 'POST',
            body: formData
        })
        .then(data => {
            if (data.success) {
                const mensaje = insumoId ? 'Insumo actualizado correctamente' : 'Insumo creado correctamente';
                showToast(mensaje, 'success');
                cerrarEditarModal();
                location.reload();
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Error de red al guardar el insumo. ' + error.message, 'error');
        });
    });
}

// Formulario de Insumo
const formInsumo = document.getElementById('formInsumo');
if (formInsumo) {
    formInsumo.addEventListener('submit', function(e) {
        e.preventDefault();

        requestApi('agregar_insumo.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(data => {
            if (data.success) {
                showToast('Insumo agregado correctamente', 'success');
                this.reset();
                location.reload();
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Error de red al agregar el insumo. ' + error.message, 'error');
        });
    });
}

// Cerrar modal al hacer click fuera
window.onclick = function(event) {
    const modalEditar = document.getElementById('modalEditarInsumo');

    if (event.target === modalEditar) {
        cerrarEditarModal();
    }
};
