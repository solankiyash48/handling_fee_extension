define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'Magento_Catalog/js/price-utils'
], function (Component, customerData, priceUtils) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Yash_HandlingFee/minicart/handling-fee'
        },

        initialize: function () {
            this._super();
            this.cart = customerData.get('cart');
            return this;
        },

        isVisible: function () {
            var c = this.cart();
            return c && c.handling_fee_applied && parseFloat(c.handling_fee) > 0;
        },

        getLabel: function () {
            var c = this.cart();
            return (c && c.handling_fee_label) || 'Handling Fee';
        },

        getFormatted: function () {
            var c = this.cart();
            var amount = c ? parseFloat(c.handling_fee || 0) : 0;
            var format = c && c.priceFormat ? c.priceFormat : { pattern: '%s', decimalSymbol: '.', groupSymbol: ',', precision: 2, integerRequired: 1 };
            return priceUtils.formatPrice(amount, format);
        }
    });
});
