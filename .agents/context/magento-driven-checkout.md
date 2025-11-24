# Magento-Driven Checkout (Hydration Mode)

## Overview

This document describes the **Magento-driven operational mode** where customers use native Magento checkout with Bold payment methods. The critical pattern here is **hydration** - converting a "dry" Simple Order into a complete order with customer data.

---

## Magento-Driven Pattern

### Who's In Control
**Magento** drives the entire checkout experience.

### Key Concept: Hydration
Order is created "dry" (minimal data) at Bold early in checkout, then **hydrated** with full customer/cart data when Magento places the order.

### Flow
```
Customer browsing Magento
  → Adds items to cart
  → Goes to Magento native checkout
  → Fills shipping/billing addresses
  → Selects Bold payment method
  → EPS SDK renders payment fields
  → Customer enters payment details
  → Magento places order (event dispatched)
    → Observer triggered
    → Module HYDRATES Bold order (outbound call)
    → Bold authorizes payment with EPS
    → Bold processes payment
    → Bold calls RSA to update Magento (inbound)
    → Magento creates invoice/credit memo
```

---

## Why Hydration is Required

### The Problem
Bold needs customer data to authorize payment, but in native Magento checkout:
1. Customer fills data in **Magento's checkout forms**
2. Bold doesn't see this data in real-time
3. Simple Order created early (for payment form) has no customer data
4. Payment authorization requires customer/cart/shipping details

### The Solution: Hydration
When Magento places order:
1. Observer intercepts order placement event
2. Module sends complete quote data to Bold
3. Bold "hydrates" its Simple Order
4. Now Bold has everything needed to authorize payment

---

## Observer Pattern

### Event-Driven Architecture

**Pattern**: Magento dispatches events → Module observers react

**Key Events**:
- `sales_order_place_after` - After order placement
- `checkout_submit_all_after` - After checkout submission

**Configuration**: `etc/events.xml`

**Example**:
```xml
<event name="sales_order_place_after">
    <observer name="bold_hydrate_order" instance="Bold\...\Observer\Order\AfterSubmitObserver"/>
</event>
```

### Observer Responsibilities

**Order Placement Observer**:
- Detects Bold payment methods
- Triggers hydration
- Handles authorization
- Updates order state

**Key File**: `Observer/Order/AfterSubmitObserver.php`

**Pattern**:
1. Check if order uses Bold payment
2. Validate order state
3. Call hydration service
4. Handle success/failure

---

## Hydration Process

### What Gets Hydrated

**Customer Data**:
- Name, email, phone
- Billing address
- Shipping address

**Cart Data**:
- Line items (products, quantities, prices)
- Discounts
- Taxes
- Totals

**Shipping Data**:
- Selected shipping method
- Shipping cost
- Carrier information

**Payment Data**:
- Payment method
- Payment nonce (from EPS)

### Hydration API

**URL** (Module → Bold Checkout Sidekick):
```
PUT checkout_sidekick/{{shopId}}/order/{publicOrderId}
```

**Pattern**: Outbound API call via `BoldClient`

**Implementation**: `Model/Order/HydrateOrderFromQuote.php`

**Key Method**: `hydrate(Quote $quote, string $publicOrderId)`

**Flow**:
1. Observer triggers after order placement
2. Build hydration payload from quote (customer, items, totals, shipping)
3. Call Bold Checkout Sidekick API via `BoldClient->put()`
4. Bold updates its order with complete data
5. Bold can now authorize payment with EPS

**Authentication**: Module's API token (configured in admin)

---

## Payment Methods Pattern

### Three Payment Methods

**1. `bold`** - Standard Bold payment
- Renders EPS payment form
- Full payment method selection
- Used in standard checkout flow

**2. `bold_wallet`** - Digital wallets (Apple Pay, Google Pay, PayPal)
- Express pay buttons
- One-click payment
- Can be on product/cart pages

**3. `bold_fastlane`** - Fastlane by PayPal
- Guest checkout acceleration
- Returning customer recognition
- Simplified address entry

### Payment Method Architecture

**Base Class**: `Model/Payment/AbstractBoldPaymentMethod.php`

**Implementations**:
- `Model/Payment/Bold.php` - Standard payment
- `Model/Payment/Wallet.php` - Digital wallets
- `Model/Payment/Fastlane.php` - Fastlane

**Pattern**: Extends `Magento\Payment\Model\Method\AbstractMethod`

**Key Methods**:
- `isAvailable()` - When to show payment method
- `assignData()` - Store payment data
- `authorize()` - Authorization logic
- `capture()` - Capture logic (via RSA)

### Configuration

**Files**:
- `etc/payment.xml` - Payment method definitions
- `etc/config.xml` - Default payment config
- `view/frontend/layout/checkout_index_index.xml` - Checkout layout

---

## Simple Order Pattern

### What is a Simple Order?

A **lightweight order structure** at Bold used before full order data is available.

**Created When**:
- EPS SDK needs to render payment form
- Before customer completes checkout
- Before Magento places order

**Contains**:
- Minimal required data for payment form
- Currency
- Total amount
- Order ID reference

**Why "Dry"**: Missing customer/shipping/items details

### Simple Order Lifecycle

```
1. Customer reaches checkout payment step
2. Module creates Simple Order at Bold (dry)
3. Simple Order ID returned
4. EPS SDK uses Simple Order ID to render payment
5. Customer completes payment entry
6. Magento places order
7. Observer triggers
8. Module HYDRATES Simple Order (fills with data)
9. Bold authorizes payment
10. Bold captures payment
11. RSA updates Magento
```

---

## Authorization and Capture Flow

### Two-Phase Payment

**Phase 1: Authorization**
- Happens immediately after hydration
- Reserves funds on customer's payment method
- Does not charge yet
- Bold validates with EPS

**Phase 2: Capture**
- Happens later (minutes to days)
- Actually charges the customer
- Triggered by Bold's state endpoint call
- Captured asynchronously

### Module's Role

**Authorization**:
- Module hydrates order
- Bold handles authorization
- Module waits for confirmation

**Capture**:
- Module calls Bold state endpoint (queues capture)
- Bold processes capture asynchronously
- Bold calls RSA endpoint when captured
- Module creates invoice

**State Endpoint Call**:
- Triggered after order placement
- Tells Bold "order is ready for capture"
- Bold queues capture process

---

## RSA Pattern (Payment Updates)

### What is RSA?

**Remote Store Adapter** - Bold's webhook system to update Magento after payment processing.

### Flow

```
Bold processes payment (authorize/capture/refund)
  → Bold calls Module RSA endpoint
  → Module validates HMAC signature
  → Module updates Magento order
  → Creates invoice (if paid)
  → Creates credit memo (if refunded)
  → Cancels order (if voided)
```

### RSA Endpoint

**Path**: `PUT /V1/shops/{shopId}/orders/{publicOrderId}/payments`

**Authentication**: HMAC signature (NOT Bearer token)

**Implementation**: `Model/Order/UpdatePayments.php`

**Actions**:
- `paid` → Create invoice
- `refunded` → Create credit memo
- `voided` → Cancel order

**Details**: See `rsa-endpoints.md` for full RSA documentation

---

## Key Files Reference

### Hydration
- `Observer/Order/AfterSubmitObserver.php` - Triggers hydration
- `Model/Order/HydrateOrderFromQuote.php` - Hydration logic
- `Model/Quote/QuoteDataBuilder.php` - Builds hydration payload

### Payment Methods
- `Model/Payment/Bold.php` - Standard payment
- `Model/Payment/Wallet.php` - Digital wallets
- `Model/Payment/Fastlane.php` - Fastlane
- `etc/payment.xml` - Payment method definitions

### RSA
- `Model/Order/UpdatePayments.php` - RSA payment updates
- `Model/Order/InvoiceOrder.php` - Invoice creation
- `Model/Order/RefundOrder.php` - Refund handling

### Configuration
- `etc/events.xml` - Observer mappings
- `etc/config.xml` - Payment configuration
- `Model/Config.php` - Config access

---

## Critical Distinctions

### Magento-Driven vs Bold-Driven

| Aspect | Magento-Driven (This Doc) | Bold-Driven (Integration) |
|--------|---------------------------|---------------------------|
| **Checkout** | Native Magento | Headless/Custom |
| **Driver** | Magento | Bold Checkout |
| **Pattern** | Observer-based | API-based |
| **Hydration** | ✅ Required | ❌ Never used |
| **Order Type** | Simple Order (dry) | Order (current) |
| **Endpoints** | Hydration, RSA | Integration API |
| **Code** | `Observer/`, `Model/Payment/` | `Model/Integration/` |

### Hydration vs Integration

**Hydration** (this mode):
- Outbound call from module TO Bold
- Fills existing Simple Order with data
- Triggered by Magento event
- Part of Magento-driven flow

**Integration** (other mode):
- Inbound calls from Bold TO module
- No hydration, order stays current
- Triggered by Bold API calls
- Part of Bold-driven flow

**⚠️ They are completely separate patterns - never mix them!**

---

## Common Patterns

### Checking if Order is Bold

```php
$method = $order->getPayment()->getMethod();
$isBold = in_array($method, ['bold', 'bold_wallet', 'bold_fastlane']);
```

### Getting Public Order ID

```php
$publicOrderId = $order->getPayment()->getAdditionalInformation('public_order_id');
```

### Triggering Hydration

**Pattern**: Event observer → Hydration service

**File**: `Observer/Order/AfterSubmitObserver.php`

**Service**: `Model/Order/HydrateOrderFromQuote.php`

---

## Testing Magento-Driven Flow

### Manual Testing Steps

1. Add product to cart in Magento frontend
2. Go to checkout
3. Fill shipping address
4. Select shipping method
5. Select Bold payment method
6. Enter payment details in EPS form
7. Place order
8. **Observe**: Hydration triggered by observer
9. **Check**: Order status updated
10. **Check**: Invoice created (after capture)

### Key Logs to Monitor

- Hydration API calls
- RSA endpoint calls
- Observer execution
- Payment authorization
- Invoice creation

---

## When Working on Magento-Driven Code

### Always Consider

1. **Is hydration affected?** - Most critical
2. **Which observer is involved?**
3. **Which payment method?**
4. **Is this RSA related?**
5. **Does this affect Bold-driven mode?** (It shouldn't!)

### Files to Check

- `Observer/` - Event observers
- `etc/events.xml` - Observer configuration
- `Model/Order/HydrateOrderFromQuote.php` - Hydration
- `Model/Order/UpdatePayments.php` - RSA
- `Model/Payment/` - Payment methods

---

## Summary

### Key Concepts

1. **Magento-Driven** - Magento controls checkout
2. **Observer Pattern** - React to Magento events
3. **Hydration** - Fill dry Simple Order with data
4. **RSA** - Bold updates Magento after payment
5. **Payment Methods** - `bold`, `bold_wallet`, `bold_fastlane`

### Critical Flow

```
Checkout → Order Placed → Observer → Hydration → Authorization → Capture → RSA → Invoice
```

### Remember

- **Hydration is REQUIRED** in this mode
- **Observers drive everything**
- **Simple Orders start dry**
- **Separate from Bold-driven mode**
- **RSA uses HMAC, not Bearer token**
