var config = {
    map: {
        "*": {
            'Magento_Checkout/template/minicart/item/default.html': 'Zinrelo_LoyaltyRewards/template/minicart/item/default.html',
            priceBox:'Zinrelo_LoyaltyRewards/js/price-box-zinrelo'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'Zinrelo_LoyaltyRewards/js/model/checkout-data-resolver': true
            }
        }
    }
};
