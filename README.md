# WBR Stripe Payment

**Contributors:** Wayback Revive  
**Tags:** stripe, payment, checkout, wbr  
**Requires at least:** 5.8  
**Tested up to:** 6.4  
**Stable tag:** 1.0.0  
**License:** GPLv2 or later  

A custom Stripe payment portal for Wayback Revive, enabling easy payment link generation and secure checkout processing.

## Description

WBR Stripe Payment is a lightweight, custom-built solution for accepting payments via Stripe. It allows administrators to generate unique, secure payment links for clients directly from the WordPress dashboard.

The plugin provides a seamless checkout experience hosted on your own domain, leveraging Stripe's secure Checkout API.

### Key Features
*   **Simple Order Generation:** Create payment orders with Client Name, Email, Amount, Currency, and Service Type.
*   **Secure Payment Links:** Generates unique, tokenized URLs for each order (e.g., `yourdomain.com/pay/order/{token}`).
*   **Stripe Integration:** Uses Stripe Checkout for secure, PCI-compliant payment processing.
*   **Test & Live Modes:** Toggle between Sandbox (Test) and Production (Live) environments easily.
*   **Custom Branding:** Configure your brand color, logo, and page titles to match your identity.
*   **Order Management:** Track payment status (Pending, Paid, Cancelled) from the admin dashboard.
*   **AJAX-Powered:** Smooth user experience for creating orders and saving settings without page reloads.

## Installation

1.  **Upload Plugin:**
    *   Upload the `wbr-stripe-payment` folder to the `/wp-content/plugins/` directory.
2.  **Install Dependencies:**
    *   Ensure the `vendor` directory is present. If not, run `composer install` inside the plugin directory to install the Stripe PHP SDK.
3.  **Activate:**
    *   Activate the plugin through the 'Plugins' menu in WordPress.
4.  **Database Setup:**
    *   Upon activation, the plugin automatically creates the necessary database table (`wp_wbr_orders`) and a "Secure Payment" page.

## Configuration

1.  Navigate to **WBR Payments > Settings** in the WordPress admin menu.
2.  **General Tab:**
    *   **Payment Mode:** Select "Test Mode" for development or "Live Mode" for real transactions.
    *   **API Keys:** Enter your Stripe Publishable and Secret keys for the selected mode.
3.  **Checkout Options:**
    *   Configure billing address collection (Required/Auto).
    *   Enable/Disable phone number collection.
4.  **Appearance:**
    *   **Brand Color:** Choose a primary color for buttons and accents.
    *   **Logo URL:** Upload a custom logo or leave empty to use the site's default logo.
    *   **Custom Title/Description:** Customize the text displayed on the payment page.

## Usage

### Creating a Payment Link
1.  Go to **WBR Payments**.
2.  Click **Add New**.
3.  Fill in the client details, amount, and service description.
4.  Click **Generate Payment Link**.
5.  The new order will appear in the table. Click the **Copy** button to grab the link and send it to your client.

### Client Experience
1.  The client clicks the link and lands on a branded payment summary page.
2.  They review the order details and click **Pay Securely via Stripe**.
3.  They are redirected to Stripe's secure checkout page to complete the transaction.
4.  Upon success, they are redirected back to a "Payment Successful" confirmation page on your site.

## Requirements

*   WordPress 5.8 or higher
*   PHP 7.4 or higher
*   Stripe Account

## Changelog

### 1.0.0
*   Initial release.
*   Added Admin Order management.
*   Added Stripe Checkout integration.
*   Added Settings page with Test/Live mode support.
*   Added "Copy Link" functionality.
