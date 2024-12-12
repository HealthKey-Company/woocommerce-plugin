const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

const data = window.wc.wcSettings.getSetting("healthkey_payment_data");

const paymentRequestPaymentMethod = {
    name: 'healthkey_payment',
    label: 'HealthKey',
    ariaLabel: "Label",
    title: 'Healthkey',
    description: "Pay via Healthkey",
    gatewayId: 'healthkey_payment',
    content: <h2>Pay with HealthKey</h2>,
    edit: <h2>Pay with Healthkey</h2>,
    canMakePayment: () => true,
    paymentMethodId: 'healthkey_payment',
    supports: {
        features: data.supports,
    },
};



registerPaymentMethod(paymentRequestPaymentMethod);