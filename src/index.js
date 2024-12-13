const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

const data = window.wc.wcSettings.getSetting("healthkey_payment_data");

const Label = (
    <>

        <span style={{ width: "100%" }}>
            <div
                style={{
                    display: "flex",
                    justifyContent: "space-between",
                }}
            >
                <span>Pay with HealthKey</span> <img src={data.icon} />
            </div>
        </span>
    </>
);

const Content = (
    <>
        <div style={{
            display: "flex",
            flexDirection: "column",
            gap: "0.25rem"
        }}>
            <span className="content" style={{ fontSize: "14px", fontWeight: "400" }}>You’ll be asked to sign in or create a free account to authorise the payment</span>
            <span className="content" style={{ fontSize: "14px", fontWeight: "700" }}>Get discounts on vetted health care with HealthKey</span>
        </div>
    </>
);

const paymentRequestPaymentMethod = {
    name: "healthkey_payment",
    label: Label,
    ariaLabel:
        "Pay with HealthKey. You’ll be asked to sign in or create a free account to authorise the payment. Get discounts on vetted health care with HealthKey ",
    title: "Pay with HealthKey",
    description: "Pay via Healthkey",
    gatewayId: "healthkey_payment",
    content: Content,
    edit: Content,
    canMakePayment: () => true,
    paymentMethodId: "healthkey_payment",
    supports: {
        features: data.supports,
    },
};

registerPaymentMethod(paymentRequestPaymentMethod);