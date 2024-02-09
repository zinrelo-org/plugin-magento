define(
    [
        'jquery',
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/totals',
        'Magento_Catalog/js/price-utils'
    ],
    function ($,Component,quote,totals,priceUtils) {
        "use strict";
        return Component.extend({
            defaults: {
                template: 'Zinrelo_LoyaltyRewards/checkout/summary/zinrelo-discount'
            },
            totals: quote.getTotals(),
            isDisplayedZinrelodiscountTotal : function () {
                if(totals.getSegment('zinrelo_discount')) {
                    return true;
                }else {
                    return false;
                }
            },
            getZinrelodiscountTotal : function () {
                if(totals.getSegment('zinrelo_discount')){
                    var price =  totals.getSegment('zinrelo_discount').value;
                    return this.getFormattedPrice(price);
                }
            },
            getZinrelodiscountLabel : function () {
                if(totals.getSegment('zinrelo_discount')){
                    return totals.getSegment('zinrelo_discount').title;
                }
            }
        });
    }
);
