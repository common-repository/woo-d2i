=== WooCommerce D2I ===
Contributors: rolfsd2i
Tags: d2i, woocommerce
Requires at least: 4.0.1
Tested up to: 5.6.2
Stable tag: 4.9
Requires PHP: 5.6
License: GPLv2 or later.
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce D2i, Plugin for woocommerce to allow online card, bank, invoice (and possible including additional options) payments through D2i

== Description ==

Plugin for woocommerce to allow online card, bank, invoice (and possible including additional options) payments through D2i

The plugin adds another payment gateway to the WooCommerce plugin. The payment gateway is powerd by Direct2Internet and only functions 
together with the WooCommerce plugin. The plugin allows online payments using cards, invoice, bank and other methods. More methods are
constantly being added to the payment gateway of Direct2Internet. To use the plugin (and not simple test the plugin) you need to visit
the Direct2Internet webpage at http://www.direct2internet.com an sign an agreement with Direct2Internet. Direct2Internet will send you
setup information (Merchant ID and Secret Key) and you will then have a working plugin to WooCommerce that allows you to make payments.
The plugin is free of charge but actual usage will be charged depending on your service agreement with Direct2Internet.
You cannot test or use the plugin without an agreement with Direct2Internet, if you want to test the plugin without an agreement contact
Direct2Internet for access to the test system.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Use the Settings->WooCommerce->Checkout->D2o to configure the plugin
1. Enter your Merchant ID and Merchant Secret that you have been sent from D2I

== Frequently Asked Questions ==

= I installed the plugin but the plugin does not show up =

You need to install the WooCommerce plugin to use this plugin. Settings are accessible from the WooCommerce->Checkout pages.

= Where do I get a Merchant ID? =

Contact direct2internet at http://www.direct2internet.com.

= Where do I get a Merchant Secret? =

Contact direct2internet at http://www.direct2internet.com.

= How do I know the service is working? =

Activate test mode and make a payment using D2I. You can also create a 1Sek (or minimum possible price) in you store and try to buy that product 
using your credit card or preffered payment method. If the product is billed 

= A customer bought a product but I did not get payed!! =

Always set "Payment Action" to state Capture when using the plugin. The "Authorize" option requires you to "manually" debit the card  through a separate
d2i web interface to receive payments.

= When I try to checkout a product I only see an error message =

This is very likely because you did not correctly setup the plugin. Ensure that Merchant ID, Merchant Secret are correctly filled in and that 
3DSecure payments is enabled. Try removing Card, Bank, Invoice payments and Tiitle/Description text if you have problems.

= Where can I get help? =

Contact Direct2Internet, see http://www.direct2internet.com

== Screenshots ==


== Changelog ==


= 1.33 =
* Revised version info 


= 1.32 =
* Added Swish as payment option, Improved README, 


= 1.31 =
* Added missing logo

= 1.3 =
* Added callback option to support multiple merchants from one instalation. Updated plugin to support latest WooCommerce.
* Added option for spliting payments

= 1.21 =
* Fixed mac calculation of shipping addresses where First Name and Last Name where missing.

= 1.2 =
* Fixed mac calculations for white spaces in code.

== Upgrade Notice ==

= 1.2 =
Improved handling of user input data which fixes some payment errors.



== Additional info ==

The plugin is part of the PSP service delivered by Direct2Internet. The service includes additional features such as a login to check
your payments and credit (payback) payments. 
For complete information on the service contact  see http://www.direct2internet.com.

