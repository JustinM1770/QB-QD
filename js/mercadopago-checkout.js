document.addEventListener('DOMContentLoaded', function() {
    // Inicializar MercadoPago con la clave pública de PRODUCCIÓN
    const mp = new MercadoPago('APP_USR-6c8551f2-99b0-466e-9a0e-9e19d53129bf', {
        locale: 'es-MX'
    });

    // Función para mostrar el formulario de MercadoPago
    const showMercadoPagoForm = () => {
        const form = document.getElementById('form-checkout');
        if (!form) return;

        const cardForm = mp.cardForm({
            amount: document.getElementById('total-amount').value,
            iframe: true,
            form: {
                id: "form-checkout",
                cardNumber: {
                    id: "form-checkout__cardNumber",
                    placeholder: "Número de tarjeta",
                },
                expirationDate: {
                    id: "form-checkout__expirationDate",
                    placeholder: "MM/YY",
                },
                securityCode: {
                    id: "form-checkout__securityCode",
                    placeholder: "CVV",
                },
                cardholderName: {
                    id: "form-checkout__cardholderName",
                    placeholder: "Titular de la tarjeta",
                },
                issuer: {
                    id: "form-checkout__issuer",
                    placeholder: "Banco emisor",
                },
                installments: {
                    id: "form-checkout__installments",
                    placeholder: "Cuotas",
                },
                identificationType: {
                    id: "form-checkout__identificationType",
                    placeholder: "Tipo de documento",
                },
                identificationNumber: {
                    id: "form-checkout__identificationNumber",
                    placeholder: "Número de documento",
                },
                cardholderEmail: {
                    id: "form-checkout__cardholderEmail",
                    placeholder: "E-mail",
                },
            },
            callbacks: {
                onFormMounted: error => {
                    if (error) {
                        console.log("Form Mounted error:", error);
                        return;
                    }
                    console.log("Form mounted");
                },
                onSubmit: event => {
                    event.preventDefault();
                    
                    const {
                        paymentMethodId: payment_method_id,
                        issuerId: issuer_id,
                        cardholderEmail: email,
                        amount,
                        token,
                        installments,
                        identificationNumber,
                        identificationType,
                    } = cardForm.getCardFormData();

                    fetch("/process_payment.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({
                            token,
                            issuer_id,
                            payment_method_id,
                            transaction_amount: Number(amount),
                            installments: Number(installments),
                            description: "Pedido QuickBite",
                            payer: {
                                email,
                                identification: {
                                    type: identificationType,
                                    number: identificationNumber,
                                },
                            },
                        }),
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === "approved") {
                            // Actualizar el formulario principal y enviarlo
                            document.getElementById('payment_id').value = result.payment_id;
                            document.getElementById('checkout_form').submit();
                        } else {
                            alert("Pago rechazado: " + result.status_detail);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert("Error al procesar el pago: " + error.message);
                    });
                },
                onFetching: (resource) => {
                    console.log("Fetching resource:", resource);
                    const submitButton = document.getElementById("form-checkout__submit");
                    submitButton.disabled = true;
                    return () => {
                        submitButton.disabled = false;
                    };
                },
            },
        });
    };

    // Mostrar/ocultar formulario de MercadoPago según el método seleccionado
    const paymentMethodSelectors = document.querySelectorAll('input[name="payment_method"]');
    paymentMethodSelectors.forEach(selector => {
        selector.addEventListener('change', function() {
            if (this.value === 'mercadopago') {
                document.getElementById('mercadopago-form-container').style.display = 'block';
                showMercadoPagoForm();
            } else {
                document.getElementById('mercadopago-form-container').style.display = 'none';
            }
        });
    });

    // Si MercadoPago está seleccionado por defecto, mostrar el formulario
    const mercadopagoRadio = document.querySelector('input[name="payment_method"][value="mercadopago"]');
    if (mercadopagoRadio && mercadopagoRadio.checked) {
        document.getElementById('mercadopago-form-container').style.display = 'block';
        showMercadoPagoForm();
    }
});