# Patches

### How to apply patch
From [webroot] folder run command:

#### On-prem
```
patch < vendor/bold-commerce/module-checkout/patches/[file].patch
```

#### Cloud
```
cp vendor/bold-commerce/module-checkout/patches/[file].patch m2-hotfixes
```

### List of available patches

| File                          | Magento version | Description                                     |
|-------------------------------|-----------------|-------------------------------------------------|
| MAGETWO-PAYMENT-BOOSTER_2.3.1 | <= 2.3.1        | Compatability fix for Payment Booster           |
| MAGETWO-PAYMENT-BOOSTER_2.3.4 | <= 2.3.4        | Compatability fix for Payment Booster           |
| MAGETWO-PAYMENT-BOOSTER_2.3.x | <= 2.3.x        | Compatability fix for Payment Booster           |
