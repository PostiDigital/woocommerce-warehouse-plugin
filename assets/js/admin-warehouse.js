// phpcs:disable PEAR.Functions.FunctionCallSignature
/* admin-warehouse js */
jQuery(function ($) {
    window.posti_order_change = function (obj) {
        $('#posti-order-metabox').addClass('loading');

        var data = {
            action: 'posti_order_meta_box',
            post_id: woocommerce_admin_meta_boxes.post_id,
            security: $('#posti_order_metabox_nonce').val(),
            order_action: $(obj).val()
        };


        $.post(woocommerce_admin_meta_boxes.ajax_url, data, function (response) {
            $('#posti-order-metabox').replaceWith(response);
        }).fail(function () {
            $('#posti-order-metabox').removeClass('loading');
        });
    };

    $('#_posti_wh_stock_type').on('change', function () {

        var data = {
            action: 'posti_warehouses',
            catalog_type: $(this).val(),
        };


        $("#_posti_wh_warehouse").html('');
        $.post(woocommerce_admin_meta_boxes.ajax_url, data, function (response) {
            var data = JSON.parse(response);
            $.each(data, function () {
                $("#_posti_wh_warehouse").append('<option value="' + this.value + '">' + this.name + '</option>')
            });
            //$('#_posti_wh_warehouse');
        }).fail(function () {
        });
    });


});
