var config = {
    map: {
        "*": {
            'Magento_Checkout/template/minicart/item/default.html': 'Zinrelo_LoyaltyRewards/template/minicart/item/default.html',
            priceBox:'Zinrelo_LoyaltyRewards/js/price-box-zinrelo',
            cartPoints: 'Zinrelo_LoyaltyRewards/js/cart-points'
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
