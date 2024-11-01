const settings = window.wc.wcSettings.getSetting( 'veritaspay-hosted-payment_data', {} );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};
const VeritasPay_Hosted_Checkout_Block = {
    name: 'veritaspay-hosted-payment',
    label: settings.title,
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: settings.title,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( VeritasPay_Hosted_Checkout_Block );