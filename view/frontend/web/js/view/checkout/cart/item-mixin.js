define([
    'ko',
    'Zinrelo_LoyaltyRewards/js/zinrelo-helper'
], function (ko, zinreloHelper) {
    'use strict';

    return function (CartItemComponent) {
        return CartItemComponent.extend({
            initialize: function () {
                this._super();

                this.potentialPoints = ko.observable(null);

                zinreloHelper.requestPotentialPoints({
                    productId: this.product_id,
                    price: this.row().price,
                    quantity: this.row().qty
                }, (data) => {
                    const points = data?.[0]?.points;
                    if (typeof points !== 'undefined') {
                        this.potentialPoints(points);
                    }
                });

                return this;
            }
        });
    };
});
