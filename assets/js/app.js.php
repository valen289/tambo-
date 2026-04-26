<?php
session_start();
header('Content-Type: application/javascript');
$usuarioId = isset($_SESSION['usuario_id']) ? addslashes($_SESSION['usuario_id']) : '';
?>

// Registrar Uso
function registrarUso(id, nombre, stockActual) {
    document.getElementById('insumoId').value = id;
    document.getElementById('insumoNombre').textContent = nombre;
    document.getElementById('cantidad').max = stockActual;
    document.getElementById('modalUso').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalUso').style.display = 'none';
    document.getElementById('formConsumo').reset();
}

function cerrarEditarModal() {
    document.getElementById('modalEditarInsumo').style.display = 'none';
    document.getElementById('formEditarInsumo').reset();
}

// Ajustar Ganado
function ajustarGanado(cambio) {
    fetch('api/actualizar_ganado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ cambio: cambio })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        alert('Error de red al actualizar el ganado.');
    });
}

// Formulario de Consumo
const formConsumo = document.getElementById('formConsumo');
if (formConsumo) {
    formConsumo.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('usuario_id', '<?php echo $usuarioId; ?>');

        fetch('api/registrar_consumo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Consumo registrado correctamente');
                cerrarModal();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => {
            alert('Error de red al registrar el consumo.');
        });
    });
}

function editarInsumo(id) {
    const modal = document.getElementById('modalEditarInsumo');
    const form = document.getElementById('formEditarInsumo');

    fetch('api/actualizar_stock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            accion: 'obtener_insumo',
            insumo_id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Error: ' + data.message);
            return;
        }

        const insumo = data.insumo;
        document.getElementById('editarInsumoId').value = insumo.id;
        document.getElementById('editarNombre').value = insumo.nombre;
        document.getElementById('editarUnidad').value = insumo.unidad;
        document.getElementById('editarCapacidad').value = insumo.capacidad_maxima;
        document.getElementById('editarStock').value = insumo.stock_actual;
        document.getElementById('editarMinimo').value = insumo.stock_minimo;
        document.getElementById('editarConsumo').value = insumo.consumo_promedio_diario;

        modal.style.display = 'flex';
    })
    .catch(() => {
        alert('Error de red al obtener datos del insumo.');
    });
}

const formEditarInsumo = document.getElementById('formEditarInsumo');
if (formEditarInsumo) {
    formEditarInsumo.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('accion', 'editar_insumo');

        fetch('api/actualizar_stock.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Insumo actualizado correctamente');
                cerrarEditarModal();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => {
            alert('Error de red al actualizar el insumo.');
        });
    });
}

// Formulario de Insumo
const formInsumo = document.getElementById('formInsumo');
if (formInsumo) {
    formInsumo.addEventListener('submit', function(e) {
        e.preventDefault();

        fetch('api/agregar_insumo.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Insumo agregado correctamente');
                this.reset();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => {
            alert('Error de red al agregar el insumo.');
        });
    });
}

// Cerrar modal al hacer click fuera
window.onclick = function(event) {
    const modalUso = document.getElementById('modalUso');
    const modalEditar = document.getElementById('modalEditarInsumo');

    if (event.target === modalUso) {
        cerrarModal();
    }

    if (event.target === modalEditar) {
        cerrarEditarModal();
    }
};
