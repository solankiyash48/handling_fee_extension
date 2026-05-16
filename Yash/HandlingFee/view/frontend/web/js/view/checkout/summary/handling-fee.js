define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Catalog/js/price-utils'
], function (Component, quote, totals, priceUtils) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Yash_HandlingFee/checkout/summary/handling-fee',
            title: 'Handling Fee'
        },

        totals: quote.getTotals(),

        isDisplayed: function () {
            return this.getPureValue() > 0;
        },

        getPureValue: function () {
            var amount = 0;
            var t = this.totals();
            if (t) {
                var segment = totals.getSegment('handling_fee');
                if (segment && segment.value) {
                    amount = parseFloat(segment.value);
                } else if (t['extension_attributes'] && t['extension_attributes']['handling_fee']) {
                    amount = parseFloat(t['extension_attributes']['handling_fee']);
                }
            }
            return amount;
        },

        getValue: function () {
            return this.getFormattedPrice(this.getPureValue());
        },

        getTitle: function () {
            return this.title;
        }
    });
});
