jQuery(document).on('click', '#doaction, #doaction2', function(e) {
    var action = jQuery(this).closest('form').find('select[name="action"]').val();
    if (action === 'bulk_shipping_label') {
        e.preventDefault();
        
        var post_ids = [];
        jQuery('input[name="post[]"]:checked').each(function() {
            post_ids.push(jQuery(this).val());
        });

        if (post_ids.length > 0) {
            var url = shippingLabelVars.ajaxurl + '?action=bulk_shipping_action&post_ids=' + post_ids.join(',');
            var nonce = shippingLabelVars.nonce;
            url += '&_wpnonce=' + nonce;
            window.open(url, '_blank');
        } else {
            alert('لطفاً حداقل یک سفارش را انتخاب کنید.');
        }
    }
});

jQuery(document).ready(function($) {
    $('a.button.shipping_label').attr('target', '_blank');
});