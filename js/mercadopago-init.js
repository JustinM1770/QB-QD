// Inicializar MercadoPago
const mp = new MercadoPago('TEST-2b2c5c05-8ac2-42b6-ae94-00e38668cfe4');

// Crear el botón de pago
const bricksBuilder = mp.bricks();

// Renderizar el formulario de pago
const renderCardPaymentBrick = async (bricksBuilder) => {
    const settings = {
        initialization: {
            amount: totalAmount, // Valor definido en PHP
            payer: {
                email: userEmail // Email del usuario definido en PHP
            }
        },
        callbacks: {
            onReady: () => {
                // Callback cuando el Brick está listo
                console.log('Brick listo');
            },
            onSubmit: (cardFormData) => {
                // Callback al hacer submit
                return new Promise((resolve, reject) => {
                    fetch("/process_payment.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify(cardFormData)
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'approved') {
                            resolve();
                            // Actualizar el formulario con el payment_id
                            document.getElementById('payment_id').value = result.payment_id;
                            document.getElementById('checkout_form').submit();
                        } else {
                            reject();
                        }
                    })
                    .catch((error) => {
                        reject();
                    });
                });
            },
            onError: (error) => {
                // Callback en caso de error
                console.error(error);
            }
        },
        locale: 'es-MX',
        customization: {
            visual: {
                style: {
                    theme: 'default',
                    customVariables: {
                        brandColor: '#0165FF',
                        borderRadius: '8px'
                    }
                }
            }
        },
    };

    const cardPaymentBrickController = await bricksBuilder.create(
        'cardPayment',
        'mercadopago-container',
        settings
    );
};

renderCardPaymentBrick(bricksBuilder);