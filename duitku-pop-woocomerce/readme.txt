=== Duitku Pop Payment Gateway ===

Plugin Name:  Duitku Pop Payment Gateway
Plugin URI:   https://github.com/duitkupg/duitku-pop-plugin/
Version:      1.0.4
Author:       Duitku
Contributors: anggiyawanduitku, heripurnama, rayhanduitku, febriana, dhanisuhendra10
Author URI:   http://duitku.com
Tags:         Payment Gateway, Duitku, Woocommerce, Virtual Account, QRIS
Requires at least: 4.7
Tested up to: 6.6.2
Stable tag: 1.0.4
Requires PHP: 7.4
Author URI:   http://duitku.com
License:      GPLv3 or Later
License URI:  https://www.gnu.org/licenses/gpl-3.0.html#license-text


== Description ==

Do you want the best solution to accept Credit Cards, e-wallet, and Various Bank Transfers on your website? Our Payment Gateway for WooCommerce plugin integrates with your WooCommerce store and lets you accept those payments through our payment gateway.
Securely accept major credit cards, View and manage transactions from one convenient place – your Duitku dashboard.

Supported Payment Channels, can be seen [here](https://www.duitku.com/harga/).

== Installation ==

Guide to installing the Duitku plugin for Woocommerce

1. Download the Duitku plugin for Woocommerce here .

2. Open your Wordpress Admin menu (generally in / wp-admin).

3. Open the Plugins menu -> Add New Page.

4. Upload the Duitku plugin file (Make sure Woocommerce is installed before adding the Duitku plugin).

5. After the plugin is installed, Duitku will appear in the list of installed plugins. Open the Plugin -> Installled Plugins page, then activate the Duitku plugin.

6. At the Installed Plugins page you may see Duitku plugin on the list.

7. Open Woocommerce -> Settings page.

8. Then select the 'Payment' tab.

9. Select "Duitku Payment" and click Manage.

10. Enter the merchant code and API Key. These parameters are created on the Duitku merchant page in the My Projects menu section. Click Save after finished.
    Information:
        Title: Set up displayed payment name on checkout page.
        Customer Message: Help you to give a massage or small description on checkout page.
        Plugin Status: select 'Sandbox' as development usage or 'Production' for live usage. You need to set Plugin Status as like as URL Endpoint that you want to use.
        Language: Payment page default language. Support for Indonesia and English.
        Duitku Merchant Code: enter your merchant code that you get from project on the Duitku merchant page.
        Duitku API Key: enter the project API key that you got from Project on the Duitku merchant page.
        Duitku Prefix: Give a prefix for order id.
        Expiry Period: The validity period of the transaction before it expires. Value input is an integer between 1 - 1440 counted as minutes.

== Frequently Asked Questions ==

= What is Duitku? =

Duitku is a Payment Solution service with the best MDR (Merchant Discount Rate) fees from many Payment Channels in Indonesia. As your payment service provider, Duitku can serve payments via credit cards, bank transfers and internet banking directly to your online shop.

= How do I integrate Duitku with my website? =

Integrating online payments with Duitku is very easy, web integration using our API. (API doc: http://docs.duitku.com/docs-api.html) or using plugins for e-commerce.

== Screenshots ==

1. Checkout Page

2. Payment Page

3. Payment Page Detail Request

4. Payment Page Payment Channel list

5. Payment Page Detail Transaction

6. Payment Page Pending

7. Payment Page Success

8. Duitku Pop Configuration

9. Duitku Business Flow

10. Duitku System Flow

== Changelog ==

= 1.0.4 =

Add parameter customerVaName to Support Tokopedia Payment

= 1.0.3 =

fix missing block files

= 1.0.2 =

Support Block Checkout
Support HPOS
Remove endpoint Configuration
Remove Duitku JS Library (woo require a redirection)

= 1.0.1 =

Configuration estimation rate and default language

= 1.0.0 =

Initial Public Release