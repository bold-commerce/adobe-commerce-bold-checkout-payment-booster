# Bold Booster
**Bold Booster** is a free extension that lets you add **Apple Pay®, Google Pay™, PayPal Fastlane, Pay Later, Venmo**, and more without removing your existing checkout customizations.
It's compatible with **Magento (1 & 2)**, **Adobe Commerce**, **OpenCart**, and **Gravity Forms**.
-----
## What is Bold Booster?
Bold Booster connects your store to **Apple Pay®, Google Pay™, PayPal Fastlane, Pay Later, Venmo**, and more through a **single integration**. It works with your current checkout so you can keep your customizations and launch in minutes.
-----
## Benefits for Merchants
  * **Increase conversions** with trusted, frictionless payment methods.
  * **Boost AOV** with Pay Later options like Pay in 4 & Pay Monthly.
  * **Reach more customers** with 700+ payment methods across 200+ markets (via PayPal, Nuvei, etc.).
  * **Go live fast**—install in minutes, not weeks.
  * **Stay flexible**—it works with any Magento version and multiple gateways.
-----
## How It Works
1.  **Install Bold Booster** on your platform.
2.  **Connect your payment gateways** (PayPal, Nuvei, Stripe, Braintree, Authorize.net).
3.  **Select payment methods** (Apple Pay, Google Pay, credit/debit, Pay Later, Venmo).
4.  **Enable Fastlane by PayPal** (optional) for accelerated guest checkout.
5.  **Start selling**—keep customers in the flow from cart to confirmation.
-----
## Installation: Adobe Commerce (Magento 2)
**Bold Booster for PayPal** is free to download and lets you add Apple Pay®, Google Pay™, Venmo, Pay Later, and more without removing customizations.
### Step 1: Install Bold Booster
#### Developer
```bash
composer require bold-commerce/module-checkout-payment-booster
php bin/magento setup:di:compile
php bin/magento setup:upgrade
```
**Magento 2.3.x only:**
`patch < vendor/bold-commerce/module-checkout-payment-booster/patches/CSP-PAYMENT-BOOSTER_2.3.x.patch`
### Step 2: Onboard Bold Booster
#### Developer + Merchant
1.  In the Magento admin, go to **Stores** \> **Configuration**.
2.  Select your website scope.
3.  Navigate to **Sales** \> **Checkout** and click **Connect with Bold**.
4.  Create/connect your Bold account.
5.  Connect payment gateway(s) & configure.
6.  Copy the API access token.
### Step 3: Configure in Magento
#### Merchant
1.  Go to **Stores** \> **Configuration** \> **Sales** \> **Checkout**.
2.  Expand the **Bold Checkout Payment Booster Extension**.
3.  Paste the API Token.
4.  Enable **Bold Booster** & (optional) **Fastlane**.
5.  Configure digital wallet placement.
6.  **Save** & flush cache.
### Step 4: Compatibility Modules
If you use Firecheckout, OneStepCheckout, or Amasty Checkout, you'll need to install the compatibility module (see dev docs).
### Step 5: Security Settings
Add these domains to your allowlist:
  * `https://api.boldcommerce.com`
  * `https://checkout.boldcommerce.com`
Add required IPs (full list in dev docs).
### Step 6: Enable Fastlane (Optional)
US-based stores selling in USD can enable Fastlane by PayPal:
1.  In the Bold Account Center, edit your PayPal/Braintree gateway.
2.  Check **Enable Fastlane**.
3.  Configure in Magento admin.
4.  **Save** & flush cache.
-----
### Support
[Help Center](https://boldcommerce.com)
© 2025 Bold Commerce. All Rights Reserved.
