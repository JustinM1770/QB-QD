$(document).ready(function() {
    // Manejar el envío del formulario de dirección
    $('#guardarDireccion').click(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '../api/guardar_direccion.php',
            type: 'POST',
            data: $('#formDireccion').serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $('#direccionModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'No se pudo guardar la dirección'));
                }
            },
            error: function() {
                alert('Error de conexión con el servidor');
            }
        });
    });
});
