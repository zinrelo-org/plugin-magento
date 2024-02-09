require(['jquery'], function ($) {
    $(document).ready(function () {
        // Your AJAX request code here
        var url = window.BASE_URL + "loyaltyRewards/loyaltyrewards/index";
        $.ajax({
            url: url, // Adjust the URL as needed
            type: 'POST',
            dataType: 'json',
            cache: false,
            success: function (response) {
                let init_data = {
                    "partner_id": response.partnerId,
                    "jwt_token": response.tokenData,
                    "version": "v2",
                    "server": "https://app.zinrelo.com"
                };
                _zrl.push(['init', init_data]);
            },
            error: function () {
                console.error('AJAX request failed.');
            }
        });
    });
});
