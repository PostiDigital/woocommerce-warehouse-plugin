# Posti warehouse plug-in for WooCommerce

## General 

Posti warehouse plug-in provides integration to Glue platform to enable **warehouse** and **dropshipping** services offered by Posti. Your company need service agreement with Posti to use the services. 

If you have questions about the Posti warehouse service or dropshipping service, please contact asiakaspalvelu@transval.fi.

## Features

Main features of the plug-in are:

- You can select which Posti delivery methods are available in a shopping cart.
- You can select if product is shipped by Posti warehouse, dropshipping supplier, or yourself.
- When you create a new product in WooCommerce it is also created to warehouse. Simple product and Variable product types are supported. Grouped product type is not supported.
- Product quantities are automatically updated from warehouse and/or dropshipping supplier.
- Send order to warehouse and/or dropshipping supplier. 
- Order status is updated from Glue to WooCommerce. This includes a tracking ID of the delivery. 

More information about warehouse service is available at [Posti.fi / verkkokaupan varasto](https://www.posti.fi/fi/yrityksille/paketit-ja-logistiikka/verkkokaupoille/verkkokaupan-varasto) 

More information about Posti dropshipping service is available at [Posti.fi / Glue palvelun käyttäminen ](https://www.posti.fi/fi/asiakastuki/yrityksen-tiedot/yritysasiakkaan-asiointikanavat/glue-palvelun-kayttaminen) 

When using the warehouse plug-in please note that there can be a 10 minute delay between dashboard information and real warehouse inventory quantity and order fulfillment status.

## Installation

This plug-in has been tested with WooCommerce version 7.7.0/WordPress version 6.2.2/PHP 7&8. You should always test the plug-in in your environment to ensure compatibility with other plug-ins also.

1. Download the plug-in software as ZIP file from this Github.
1. Remove previous version of the plug-in if you are updating the plugin.
1. Install the plug-in via admin UI of the Wordpress > Plugins > Add plugin.
1. Activate the plugin.
1. Configure the plugin using the following instructions. If you are updating the plugin, make sure you map shipping options to Posti delivery servies again. All other settings are ready if you had previous version of the plug-in installed. Use test mode for new installations first - please do not make tests in production environment as all orders are processed by warehouse.
1. Update product information. 
1. Test the plug-in to ensure compatibility with your existing environment.
1. Switch off the test mode. Now you are ready to use the service.

## Configuration

**WooCommerce > Settings > General**

Fill the Store address. It is used as sender’s address for parcel deliveries.

**WooCommerce > Settings > Shipping**

Create a new shipping zone, for example “Suomi” and add shipping methods (for example, “Nouto Postista”, “Postin kotiintoimitus”, and “Express paketti perille”). These are just names that are shown to end-customer – actual delivery methods are mapped in the following Posti warehouse settings. 

**Plugins > Posti Warehouse > Settings**

Add information to configure the warehouse settings:

- **Username** – this is API key (account name) for the production environment of the Glue, which is provided by Posti. 
- **Password** – this is API password (secret) for the production environment of the Glue, which is provided by Posti.
- **TEST Username** -  this is API key for the test environment of the Glue, which is provided by Posti. 
- **TEST Pasword**  – this is API password for the test environment of the Glue, which is provided by Posti.
- **Delivery service** - Select either Posti Warehouse or Dropshipping, this determines which delivery methods are available when you map shipping options..
- **Default stock type** - select service you are mainly using (warehouse or dropshipping). You can change the value when you add new products.
  - **Posti Warehouse** - product is stocked by Posti warehouse
  - **Dropshipping** - product is stocked and order is fulfilled by supplier. 
  - **Store** - product is stocked by yourself. You can use the Glue to print address label for the delivery. This feature requires separate activation in Glue. 
  - **Not in stock** - product is stocked by yourself. Use some other plugin or service for address label printing to fulfill orders. 
- **Auto ordering** – if selected then new order is automatically sent to warehouse, which speed up the order processing. 
- **Add tracking to email** – tracking ID of the delivery is added to the delivery confirmation.
- **Test mode** – if selected then TEST username and TEST password is used to interface test environment of the Glue.
- **Debug** – if selected then event log is available at “Settings” > “Posti Warehouse debug”. 

**WooCommerce > Settings > Shipping > Posti warehouse**

Map shipping methods to actual Posti’s delivery products. Please note that delivery services may include services that are not available in the service you signed with Posti.

**Woocommerce > Products**

Select your existing product or create a new, and update the product information. Note the use of the following fields in the product information:
- **Product data** - Simple product and Variable product are supported.
- **General > Wholesales price** - Glue is able to show total value of your stock if wholesales price is available.
- **Inventory > SKU** - product ID
  - **Warehouse service**: this is product ID, which is used by warehouse also. Product is creted to the warehouse with this ID. The plug-in adds your business ID as a prefix to the front of the product ID. For example WooCommerce SKU "2001" will be sent as "01234567-8-2001" to warehouse.
  - **Dropshipping service** - this is supplier's product ID. You need to find out this value from Glue and input it manually or use CSV upload to create products in WooCommerce. Note that WooCommerce does not accept dublicate SKUs. If two suppliers have the same SKU, then workaround is to create a new SKU by Supplier with Grouped product feature.
- **Manage stock?** - if enabled then the plug-in is polling stock quantities from the Glue.
- **Stock quantity** - leave this 0 and let the plug-in to update stock quantities from the Glue (if "Manage stock" is enabled).
- **Inventory > EAN** - additional product ID. In case of warehouse service this is updated to warehouse also.
- **Shipping > Weight** - weight is mandatory information.
- **Shipping > Dimensions** - dimensions are mandatory information.
- **Attributes (for Variable products)** - Add Custom product attibutes for your product, for example "color" or "size".
- **Variations (for Variable products)** - Add SKU and enable stock management ("Manage stock?"-option), add weight and dimensions. Product name for the Glue is the name of the main product with name of the variation, for example "T-Shirt Red", where "T-shirt" is name of the product and "Red" is name of the variation.
- **Posti > Stock type**
  - **Posti Warehouse** - product is stocked and fulfilled by Posti warehouse
  - **Dropshipping** - product is stocked and order is fulfilled by supplier. 
  - **Store** - product is stocked and fulfilled by yourself. You can use the Glue to print address label for the delivery. This feature requires separate activation in Glue. 
  - **Not in stock** - product is stocked by yourself. Use some other plugin or service for address label printing to fulfill orders. 
- **Posti > Warehouse** - This shows list of available warehouses and suppliers. Information is extracted from the Glue.
- **Posti > Distributor ID** - This is optional value used by the warehouse service. You can input here your supplier's business ID (or your own reference for the supplier) and Glue is using it when you place Purchse Order in Glue. This ensures that the Purchase Order does not have products from multiple suppliers, which would be error.  
- **Posti > LQ Process permission** - if enabled then LQ addtional service is added to order/delivery.
- **Posti > Large** - if enable then Large addtional servie is added to order/delivery.
- **Posti > Fragile** - if enabled then Fragile additional service is added to order/delivery. 

## Version history
- 2.4.2: Added "Verbose logging" setting. 
- 2.4.1: internal: Changed how plugin gets order status (WC_Order get_status)
- 2.4.0:
    - Added "Reject partial order" setting.
    - Changed products quantity sync to allow products with duplicate SKUs.
    - Changed number of log entries 50 -> 100.
- 2.3.6: Support email update. WP banner update.
- 2.3.4: Remove contract number field from settings page.
- 2.3.3: Bug fix: Settings link not shown when plugin is installed from shop.
- 2.3.2: Limit "Hide outdoor pickup points" option to Posti pickup points.
- 2.3.1: Updated pickup point translations.
- 2.3.0: Added "Hide outdoor pickup points" option to Shipping tab.
- 2.2.3: internal: json_encode replaced with wp_json_encode function.
- 2.2.2: internal: curl dependency replaced with wp_remote_* functions.
- 2.2.1: Update product status to EOS ("End Of Sale") when removing from warehouse.
- 2.2.0:
    - Changed Warehouse column to show warehouse name instead of code.
    - Added product main image url to product upload sent to Posti.
    - Changed pickup stores lookup to show all results when shipping city does not match available store locations.
    - Bug fix: fixed warehouse selection with 1 result.

- 2.1.1: Do not allow user to submit order without email and phone.
- 2.1.0: Added "Store pickup" option to Shipping configuration.
- 2.0.2: EAN field renamed to EAN / ISBN / Barcode.
- 2.0.1: Fix products quantity sync for dropshipping use case.
- 2.0.0:
    - Expanded pickup points support.
    - Deprecated business ID prefix in orders and products.
    - Added separate plugin settings page.
    - Added bulk products list action - Publish to warehouse / Remove from warehouse
    - Updated orders and products sync process to use timestamp.
    - Authentication APIs have been changed.

- 1.0.8 Fix email and telephone when pickup point is used for delivery address.
- 1.0.7 Prefer shipping email and telephone to billing information for delivery address.
- 1.0.6 "PickUp Parcel" and "Home delivery SE/DK" introduced as new shipping options for Sweden and Denmark. If you are updating the old version of the Warehouse plug-in please ensure update mapping of shipping options in Posti warehouse settings. Some bug fixes included also.
- 1.0.5 Bug fix: fixed error in error message that appeared when saving variabl product.
- 1.0.4 Added support for the variable products.