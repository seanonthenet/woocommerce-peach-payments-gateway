=== WC Peach Payments Gateway ===

Tags: woocommerce, payments, credit card, payment request
Requires at least: 4.7
Tested up to: 5.6
Requires PHP: 7.0
Stable tag: 2.0.2
Version: 2.0.2
License: GPLv3


A payment gateway integration between WooCommerce and Peach Payments.

== Description ==

The Peach Payments (https://www.peachpayments.com/) extension for WooCommerce is an African payment gateway that allows merchants to access various payment methods, including credit/debit cards, bank transfers, mobile and electronic wallets.

= Features =
* Secure card storage
* Card payments, EFT Secure, Ozow, Masterpass, Mobicred
* Fully supports WooCommerce Subscriptions (separate purchase)
* 3DSecure ready
* PCI Compliant

= Requirements =
* A Peach Payments Merchant Account
* A WooCommerce store

= Countries Supported =
* South Africa
* Kenya
* Mauritius

= Sign up with Peach Payments =
Contact Peach Payments at [sales@peachpayments.com](mailto:sales@peachpayments.com) to set up a merchant account for your company/website.
Peach Payments will assist you in the application process with the respective banks. Please note that the merchant account application process may take up to 4 weeks depending on the bank. Get in touch as soon as possible to avoid delays going live.

= It's Free, and always will be =
We are firm believers in open source and that is why we are releasing the WC Peach Payments Gateway plugin for free, forever.

= Actively Developed =
The WC Peach Payments Gateway plugin is actively developed. New features and enhancements are added based on feedback from you.

== Installation ==
1. Log in to your WordPress website (www.yourwebsiteurl.com/wp-admin).
2. Navigate to Plugins and select Add New.
3. Search for ”Peach Payments” in the plugin search bar to find [WooCommerce Peach Payments Gateway](https://wordpress.org/plugins/wc-peach-payments-gateway/).
4. When the installation is complete, Activate your plugin.

= Setup and Configuration =
Upon setting up your merchant account with Peach Payments you will receive TEST and LIVE access credentials. You will need to insert these details on the Peach Payments gateway settings page under WooCommerce settings. Use your TEST credentials for testing prior to going live.

== Testing the payment gateway ==
1. Go to WooCommerce > Settings > Payments > Peach Payments
2. Enable Peach Payments
3. Set the Transaction Mode to Integrator Test
4. Add the payment methods that you want to enable. Make sure that have requested Peach Payments to set you up for these
5. Enter Peach Payments TEST access credentials. For Card payments: Access Token and 3DSecure Channel ID (and Recurring Channel ID for Card Storage.)** For other payment methods also add the Secret Token. You have received these credentials from Peach Payments
6. Optional if you want to enable webhooks. Ask Peach Payments to set up your webhook, by providing them your domain url
7. Save changes

**You would have received these credentials after signing up with Peach Payments. If you only received one 3D-Secure channel ID, please repeat this value in the Channel ID field

== Sandbox Testing ==
Now test the payment gateway by purchasing a product on your website using the Peach Payment Test Cards (the Test Card numbers provided in this system can be used to test the various components of your integration). View our testing guidelines here, https://support.peachpayments.com/hc/en-us/sections/200504676-Plugins-and-Integrations.

== Peach Payment Test Cards ==
Note: Card associations that are available to you depends on the country you do business in, please contact Peach Payments if you need assistance.

VISA
    Number: 4111111111111111
    Expiry: Any future date (MM/YY)
    Verification: 123

MASTER
    Number: 5105105105105100
    Expiry: Any future date (MM/YY)
    Verification / CVV: 123

AMEX
    Number: 311111111111117
    Expiry: Any future date (MM/YY)
Verification / CVV: 123

VISA
    Number: 4242424242424242
    Expiry: Any future date (MM/YY)
    Verification / CVV: 123


MASTER
    Number: 5454545454545454
    Expiry: Any future date (MM/YY)
    Verification / CVV: 123

= Live Mode =

1. After testing the gateway with the Peach Payment test cards, go back to Go to WooCommerce > Settings > Payments > Peach Payments > Setup/manage
2. Set the Transaction Mode to Live
3. Replace your TEST access credentials with your LIVE access credentials**
4. Click Save changes

**You would have received these credentials after signing up with Peach Payments. If you only received one 3D-Secure channel ID, please repeat this value in the Channel ID field

== Frequently Asked Questions ==

= What does this plugin do? =
A payment gateway integration between WooCommerce and Peach Payments. It enables you as an online merchant or business to collect card payments from your customers, securely on your website. Your customers can pay on their internet connected smartphone, laptop, tablet or computer and you receive the funds.


= I am getting a message about SSL not being enabled. =
Peach Payments does not require an SSL certificate to be installed on your website for payments to be accepted. The Peach Payments card acceptance widget is secured independently of your website. However, we do strongly recommend that you secure your site with an SSL certificate to ensure that your customer has trust in submitting a transaction.


= Do I need to obtain PCI compliance in order to accept payments with Peach Payments? =
Peach Payments is PCI compliant and adheres to the latest security requirements to securely process payments worldwide. When using the WC Peach Payments plugin you do not need to obtain your own PCI compliance certificate.


= I am unable to process credit or debit card payments with Visa, Mastercard, American Express , Diners or another card type. =
If a particular card brand is not working make sure that you have enabled it using the Supported Cards field in the WooCommerce Checkout Settings for Peach Payments. Some card brands require additional setup and applications (eg. AMEX, DINERS), and are therefore not available by default when setting up your Peach Payments account. Contact our support team to enable these cards for your website.


= Where can I report bugs or contribute to the project? =
Bugs can be reported either by sending an email to support@peachpayments.com.


= What are the server requirements for running the WC Peach Payments Gateway plugin? =
Your WordPress website needs to be running PHP version 7.0 or higher in order to make use of the Peach Payments plugin.


= How do I differentiate recurring payments from one-time payments? =
In the WooCommerce backend, there is a separate tab for subscriptions orders (Woocommerce --> subscriptions). A recurring order will be attached to this subscription order, as a renewal order. When looking at the Peach merchant dashboard, one-time payments will only have a DB (debit) line item per order. Click on the DB (debit) transaction and go to the "Actions" tab. If you see an RG (Registration) label, then it is a subscription or stored card payment.

Additionally, you can click on the RG to see all payments made on this specific stored card.


= How do we make sure people get payment confirmations? =
WooCommerce order emails are configured to be sent to the site administrator and the customer. For every successful transaction, Peach Payments delivers a confirmation to WooCommerce which triggers the necessary notification emails by WooCommerce. To check your Wordpress settings, go to Woocommerce> Settings > Emails.

For more payment confirmation email support, visit: https://docs.woocommerce.com/document/email-faq/


= If I refund or reverse a transaction through the Wordpress dashboard, will it successfully reimburse the customer? =
At this moment, refunds and reversals through the Wordpress dashboard is not supported by the Peach Payment plugin. To initiate a refund or reversal you would need to complete this thought the Peach payments merchant dashboard. Once refunded you can also click on refund in your WooCommerce backend so that your order totals in WooCommerce match the payments fully. We are actively working to support this feature.


= How do I see how much I have collected from sales with Peach Payments? =
Your sales and revenue figures are available in your WooCommerce order reports. Refunds and reversals may not be accounted for unless they are manually synced. To sync a refund transaction, you would need to process a refund on the Peach payments merchant dashboard, and update the refund on the order in the WooCommerce backend.

\You can also view your payments in the Peach Payments merchant dashboard, https://peachpayments.ctpe.info/. This balance will reflect your revenue net of refunds.

= What should the status be in WooCommerce when I receive a payment? =
More info on different order statuses can be found here on WooCommerce,  https://docs.woocommerce.com/document/managing-orders/.

For physical products, order status will stay in "Processing"
For digital / virtual or downloadable products, order status will be "Completed"
For subscriptions, a subscription order will be created and will be set to “Active” if the customer is up to date on payments and renewal orders. Please always rely on Woocommerce documentation for order status information.

== Screenshots ==

1. Woocommerce Peach Payments plugin card widget page
2. Woocommerce Peach Payments plugin my account stored cards
3. Woocommerce Peach Payments plugin checkout page card option
4. Woocommerce Peach Payments plugin configuration page
5. Woocommerce Peach Payments plugin Checkout page

