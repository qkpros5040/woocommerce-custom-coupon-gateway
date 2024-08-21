jQuery(document).ready(function ($) {
    $('#validate_coupon_code').on('click', function () {
        var coupon_code = $('#custom_coupon_code').val();

        if (coupon_code === '') {
            alert('Please enter a coupon code.');
            return;
        }

        $.ajax({
            url: customCouponGateway.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'validate_coupon_code',
                coupon_code: coupon_code,
                nonce: customCouponGateway.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('#coupon_validation_message').text(response.data).css('color', 'green');
                } else {
                    $('#coupon_validation_message').text(response.data).css('color', 'red');
                }
            }
        });
    });
});