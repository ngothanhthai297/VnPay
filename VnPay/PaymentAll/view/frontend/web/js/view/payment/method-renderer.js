define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'vnpay_payment',
                component: 'VnPay_PaymentAll/js/view/payment/method-renderer/vnpay_payment'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
