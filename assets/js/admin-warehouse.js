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
    
    var extra_services = $('.form-field._posti_lq_field, .form-field._posti_large_field, .form-field._posti_fragile_field');
    
    var check_stock_type = function(){
        if ($('#_posti_wh_stock_type').val() === 'Catalog'){
            //$('.form-field._posti_wh_product_field').slideDown();
            extra_services.slideUp();
        } else {
            //$('.form-field._posti_wh_product_field').slideUp();
            extra_services.slideDown();
        }
    };

    $('#_posti_wh_stock_type').on('change', function () {
        check_stock_type();
        var data = {
            action: 'posti_warehouses',
            catalog_type: $(this).val()
        };

        $('#posti_wh_tab').addClass('loading');

        $("#_posti_wh_warehouse").html('');
        $.post(woocommerce_admin_meta_boxes.ajax_url, data, function (response) {
            $("#_posti_wh_warehouse").append('<option value="">Select warehouse</option>');
            var data = JSON.parse(response);
            $.each(data, function () {
                $("#_posti_wh_warehouse").append('<option value="' + this.value + '">' + this.name + '</option>');
            });
        }).fail(function () {
        }).always(function () {
            $('#posti_wh_tab').removeClass('loading');
        });
    });

    check_stock_type();
    
    $('.posti-wh-select2').select2();
    
    var attach_action_bulk_publish = function(){
        $(document).ready(function() {
            $("#bulk-action-selector-top, #bulk-action-selector-bottom").on('change', function(e) {
                var $this = $(this);

                if ( $this.val() === '_posti_wh_bulk_actions_publish_products' ) {                    
                    //$('#posti_wh_tab').addClass('loading');
                    var opts_warehouses = $("<select>", { name: "_posti_wh_warehouse_bulk_publish" });
                    opts_warehouses.attr("id", "_posti_wh_warehouse_bulk_publish");

                    var data = {
                        action: 'posti_warehouses',
                        catalog_type: 'Posti'
                    };
                    $.post(woocommerce_admin_meta_boxes.ajax_url, data, function (response) {
                        var options = $("#_posti_wh_warehouse_bulk_publish");
                        var whs = JSON.parse(response);
                        if (whs.length !== 1) {
                            options.append('<option value="">Select warehouse</option>');
                        }
                        
                        $.each(whs, function () {
                            options.append('<option value="' + this.value + '">' + this.name + '</option>');
                        });
                        
                        if (whs.length === 1) {
                            options.hide();
                        }
                    }).fail(function () {
                    }).always(function () {
                        //$('#posti_wh_tab').removeClass('loading');
                    });

                    $this.after(opts_warehouses);

                } else {
                    $("#_posti_wh_warehouse_bulk_publish").remove();
                    
                }
            }); 
        });
    };
    attach_action_bulk_publish();

    var attach_action_warehouse_products_migrate = function(){
        $(document).ready(function() {
            $("#posti_wh_migration_submit").on('click', function(e) {
                var $this = $(this);
                $this.prop("disabled", true);
                var data = {
                    action: 'warehouse_products_migrate',
                    security: $('#posti_migration_metabox_nonce').val(),
                };
                $.post($('#posti_migration_url').val(), data, function (response) {
                $this.hide();
                $('#posti_wh_migration_required').hide();
                $('#posti_wh_migration_completed').show();
                }).fail(function () {
					$this.prop("disabled", false);
                }).always(function () {
                });
            }); 
        });
    };
    attach_action_warehouse_products_migrate();
});
