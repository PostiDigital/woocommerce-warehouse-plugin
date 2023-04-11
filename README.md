# Posti warehouse plug-in for WooCommerce

## General 

Posti warehouse plug-in provides integration to Glue platform to enable **warehouse** and **dropshipping** service offered by Posti. Your company need service agreement with Posti to use the services. 

If you have questions about the Posti warehouse service or dropshipping service, please contact kari.nykanen@posti.com. 

## Features

Main features of the plug-in are:

- You can select which Posti delivery methods are available in a shopping cart.
- You can select if product is shipped by Posti warehouse, dropshipping supplier, or yourself.
- When you create a new product it is also created to warehouse. Simple product and Variable product types are supported. Grouped product type is not supported.
- Product quantities are automatically updated from warehouse and/or dropshipping supplier.
- Send order to warehouse and/or dropshipping supplier. Your business ID is added as a prefix to WooCommerce order ID.
- Order status is updated from Glue to WooCommerce. This includes a tracking ID of the delivery. 

More information about warehouse service is available at [Posti.fi / verkkokaupan varasto](https://www.posti.fi/fi/yrityksille/paketit-ja-logistiikka/verkkokaupoille/verkkokaupan-varasto) 

More information about Posti dropshipping service is available at [Posti.fi / Glue palvelun käyttäminen ](https://www.posti.fi/fi/asiakastuki/yrityksen-tiedot/yritysasiakkaan-asiointikanavat/glue-palvelun-kayttaminen) 

## Installation

This plug-in has been tested with WooCommerce version 7.5.1/WordPress version 6.2.0. You should always test the plug-in in your environment to ensure compatibility also with other plug-ins.

1. Download the plug-in software as ZIP file from this Github.
1. Remove previous version of the plug-in if you are updating the plugin.
1. Install the plug-in via admin UI of the Wordpress > Plugins > Add plugin.
1. Activate the plugin.
1. Configure the plugin using the following instructions. If you are updating the plugin, make sure you map shipping options to Posti't delivery servies again. All other settings are ready if you had previous version of the plug-in installed. Use test mode for new installations first.
1. Update product information. 
1. Test the plug-in to ensure compatibility with your existing environment.
1. Switch off the test mode. Now you are ready to use the service.

## Configuration

**WooCommerce > Settings > General**

Fill the Store address. It is used as sender’s address for parcel deliveries.

**WooCommerce > Settings > Shipping**

Create a new shipping zone, for example “Suomi” and add shipping methods (for example, “Nouto Postista”, “Postin kotiintoimitus”, and “Express paketti perille”). These are just names that are shown to end-customer – actual delivery methods are mapped in the following Posti warehouse settings. 

**Plugins > Posti Warehouse Plugin > Settings**

Add information to configure the warehouse settings:

- **Username** – this is API key for the production environment of the Glue, which is provided by Posti. 
- **Password** – this is API password for the production environment of the Glue, which is provided by Posti.
- **TEST Username** -  this is API key for the test environment of the Glue, which is provided by Posti. 
- **TEST Pasword**  – this is API password for the test environment of the Glue, which is provided by Posti.
- **Delivery service - Select either Posti Warehouse or Dropshipping, this determines which delivery methods are available when you map shipping options..
- **Contract number** – your contract number for Posti parcel services (6-digit long number which starts with number 6 )
- **Default stock type** - select service you are mainly using (warehouse or dropshipping). You can change the value when you add new products.
  - **Posti Warehouse** - product is stocked by Posti warehouse
  - **Dropshipping** - product is stocked and order is fulfilled by supplier. 
  - **Store** - product is stocked by yourself. You can use the Glue to print address label for the delivery. This feature requires separate activation in Glue. 
  - **Not in stock** - product is stocked by yourself. Use some other plugin or service for address label printing to fulfill orders. 
- **Auto ordering** – if selected then new order is automatically sent to warehouse, which speed up the order processing. 
- **Add tracking to email** – tracking ID of the delivery is added to the delivery confirmation.
- **Delay between stock and order checks in seconds** – Recommended value is “7200” (2 hours). WooCommerce is polling stock and quantities and order statuses and this defines polling frequency.
- **Test mode** – if selected then TEST username and password is used to interface test environment of the Glue.
- **Debug** – if selected then event log is available at “Settings” > “Posti Warehouse debug”. 
- **Datetime of last stock update** - Timestamp of last stock change
- **Datetime of last order update** - Timestamp of last order change

**WooCommerce > Settings > Shipping > Posti warehouse**

Map shipping methods to actual Posti’s delivery products.


**Woocommerce > Products**

Select your existing product or create a new, and update the product information. Note the use of the following fields in the product information:
- **Product data** - Simple product and Variable product are supported.
- **General > Wholesales price** - Glue is able to show total value of your stock if wholesales price is available.
- **Inventory > SKU** - product ID
  - **Warehouse service**: this is product ID, which is used by warehouse also. Product is creted to the warehouse with this ID. The plug-in adds your business ID as a prefix to the front of the product ID. For example WooCommerce SKU "2001" will be sent as "01234567-8-2001" to warehouse.
  - **Dropshipping service** - this is supplier's product ID. You need to find out this value from Glue and input it manually or use CSV upload to create products in WooCommerce
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
- **Posti > Fragile** - if enabled then Fragile addtional service is added to order/delivery. 

## Version history

- 2.0.0 Expanded pickup points support.
        Deprecated business ID prefix in orders and products.
        Added separate plugin settings page.
        Updated orders and products sync process to use timestamp.
- 1.0.8 Fix email and telephone when pickup point is used for delivery address.
- 1.0.7 Prefer shipping email and telephone to billing information for delivery address.
- 1.0.6 "PickUp Parcel" and "Home delivery SE/DK" introduced as new shipping options for Sweden and Denmark. If you are updating the old version of the Warehouse plug-in please ensure update mapping of shipping options in Posti warehouse settings. Some bug fixes included also.
- 1.0.5 Bug fix: fixed error in error message that appeared when saving variabl product.
- 1.0.4 Added support for the variable products.
