(function ($) {
    'use strict';

    /**
     * All of the code for your admin-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here, so the
     * $ function reference has been prepared for usage within the scope
     * of this function.
     *
     * This enables you to define handlers, for when the DOM is ready:
     *
     * $(function() {
     *
     * });
     *
     * When the window is loaded:
     *
     * $( window ).load(function() {
     *
     * });
     *
     * ...and/or other possibilities.
     *
     * Ideally, it is not considered best practise to attach more than a
     * single DOM-ready or window-load handler for a particular page.
     * Although scripts in the WordPress core, Plugins and Themes may be
     * practising this, we should strive to set a better example in our own work.
     */


    $(document).on('click', '#validate_coupon_code', function () {
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
})(jQuery);
