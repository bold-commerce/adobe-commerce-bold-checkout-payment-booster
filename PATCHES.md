# Patches

### How to apply patch
From [webroot] folder run command:

### Git Version CSP Patch:
```
patch < app/code/Bold/CheckoutPaymentBooster/patches/CSP-PAYMENT-BOOSTER_2.3.x.patch
```

### Composer Version CSP Patch:
```
patch < vendor/bold-commerce/module-checkout-payment-booster/patches/CSP-PAYMENT-BOOSTER_COMPOSER_2.3.x.patch
```

#### On-prem
```
patch < vendor/bold-commerce/module-checkout-payment-booster/patches/[file].patch
```

#### Cloud
```
cp vendor/bold-commerce/module-checkout-payment-booster/patches/[file].patch m2-hotfixes
```

### List of available patches

| File                          | Magento version | Description                               |
|-------------------------------|-----------------|-------------------------------------------|
| MAGETWO-PAYMENT-BOOSTER_2.3.1 | <= 2.3.1        | Compatability fix for Payment Booster     |
| MAGETWO-PAYMENT-BOOSTER_2.3.4 | <= 2.3.4        | Compatability fix for Payment Booster     |
| MAGETWO-PAYMENT-BOOSTER_2.3.x | <= 2.3.x        | CSP Compatability fix for Payment Booster |
