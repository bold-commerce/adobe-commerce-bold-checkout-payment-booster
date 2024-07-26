# Adobe Commerce Bold Payment Booster

This extension adds Bold payment gateways to your existing Adobe Commere checkout experience.

## Table of Contents

- [Table of Contents](#table-of-contents)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Usage and configuration](#usage-and-configuration)
- [Support](#support)

## Prerequisites

- **PHP**: 7.1 or higher.
- **Magento Open Source/Adobe Commerce**: 2.3.2 or higher.

## Installation

1. Open your terminal and navigate to your project directory.
2. Run the following Composer commands to install Bold Checkout and Bold Payment Booster
    ```bash
    composer require bold-commerce/module-checkout-payment-booster
    ```
3. Enable the extension using the following commands
    ```bash
    php bin/magento setup:di:compile
    php bin/magento setup:upgrade
    ```

## Usage and configuration

Refer to the developer documentation for [Payment Booster installation instructions](https://developer.boldcommerce.com/guides/platform-integration/adobe-commerce/installation?config=payment-booster).


## Support

If you have any questions, reach out to the [Bold Customer Success team](https://support.boldcommerce.com/hc/en-us/requests/new?ticket_form_id=132106).
