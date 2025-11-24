# Express Pay & Digital Wallets

## Overview

Express Pay enables one-click checkout using digital wallets (Apple Pay, Google Pay, PayPal, Venmo, Fastlane) on product pages, cart, and mini-cart.

---

## Express Pay Pattern

### What is Express Pay?

**Pattern**: One-click purchase buttons before traditional checkout

**Wallets Supported**:
- Apple Pay
- Google Pay
- PayPal
- Venmo
- Fastlane (PayPal)

**Placement**:
- Product Detail Page (PDP)
- Shopping Cart
- Mini-Cart

### User Flow

```
Customer on product/cart page
  → Sees Express Pay buttons
  → Clicks wallet button (e.g., Apple Pay)
  → Wallet authenticates customer
  → Customer approves payment
  → Module creates order
  → Bold processes payment
  → Order complete
```

---

## Integration Points

### Product Page

**Location**: Below "Add to Cart" button

**Pattern**: JavaScript component renders wallet buttons

**Requirements**:
- Product must be in stock
- Product must be purchasable
- Express Pay must be enabled

**Files**:
- `view/frontend/layout/catalog_product_view.xml` - Layout
- `view/frontend/web/js/view/express-pay-pdp.js` - Component
- `view/frontend/web/template/express-pay-pdp.html` - Template

### Cart Page

**Location**: Above cart totals or summary

**Pattern**: Same component pattern as PDP

**Requirements**:
- Cart must have items
- Express Pay must be enabled

**Files**:
- `view/frontend/layout/checkout_cart_index.xml` - Layout
- `view/frontend/web/js/view/express-pay-cart.js` - Component
- `view/frontend/web/template/express-pay-cart.html` - Template

### Mini-Cart

**Location**: Inside mini-cart dropdown

**Pattern**: Same component pattern

**Files**:
- `view/frontend/layout/default.xml` - Layout
- `view/frontend/web/js/view/express-pay-minicart.js` - Component

---

## Technical Architecture

### Frontend Pattern

**Technology**: Knockout.js component

**Pattern**:
1. JavaScript component initializes
2. Loads EPS SDK
3. Renders wallet buttons
4. Handles wallet events
5. Creates quote via API
6. Places order

### Backend API Pattern

**Endpoints Used**:
- `POST /bold/express-pay/quote/create` - Initialize quote
- `POST /bold/express-pay/order/place` - Place order

**Service Contracts**:
- `Api/ExpressPay/QuoteManagementInterface`
- `Api/ExpressPay/OrderManagementInterface`

**Implementations**:
- `Model/ExpressPay/QuoteManagement.php`
- `Model/ExpressPay/OrderManagement.php`

---

## Quote Creation Pattern

### Express Pay Quote

**Pattern**: Server-side quote creation with customer data from wallet

**Flow**:
1. Customer authorizes wallet
2. Wallet returns customer data (name, address, email)
3. JavaScript calls quote creation endpoint
4. Module creates quote with wallet data
5. Returns quote ID

**Key Differences from Normal Quote**:
- Pre-filled customer data from wallet
- Immediate shipping address
- Payment nonce from wallet

---

## Order Placement Pattern

### Express Pay Order

**Pattern**: Direct order placement (bypass traditional checkout)

**Flow**:
1. Quote created and populated
2. Shipping method selected
3. Payment nonce obtained from wallet
4. Order placement endpoint called
5. Module creates order
6. Bold processes payment

**Which Mode?**

Express Pay uses **Magento-driven mode**:
- Creates Simple Order
- Requires hydration
- Uses RSA for payment updates
- Observer-driven

**Why**: Customer interaction happens in Magento context (product/cart pages)

---

## EPS SDK Integration

### SDK Loading

**Pattern**: JavaScript dynamically loads EPS SDK

**File**: `view/frontend/web/js/eps-loader.js`

**When**: On pages with Express Pay buttons

**Configuration**: SDK URL from Bold config

### Wallet Initialization

**Pattern**: EPS SDK initializes supported wallets

**Availability Check**:
- Browser support (e.g., Apple Pay on Safari)
- Wallet configured at Bold
- Device capability

**Rendering**: Only available wallets shown

---

## Customer Data Synchronization

### Wallet → Magento

**Pattern**: Map wallet data to Magento format

**Data Mapped**:
- Name → Customer name
- Email → Customer email
- Address → Shipping/billing address
- Phone → Customer phone

**Implementation**: `Model/ExpressPay/QuoteManagement.php`

---

## Key Files Reference

### Frontend
- `view/frontend/web/js/view/express-pay-*.js` - Components
- `view/frontend/web/template/express-pay-*.html` - Templates
- `view/frontend/web/js/eps-loader.js` - SDK loader
- `view/frontend/layout/*.xml` - Layout configuration

### Backend
- `Model/ExpressPay/QuoteManagement.php` - Quote creation
- `Model/ExpressPay/OrderManagement.php` - Order placement
- `Api/ExpressPay/*.php` - Service contracts
- `Controller/ExpressPay/*.php` - Endpoints

### Configuration
- `etc/webapi.xml` - API endpoints (Express Pay section)
- `etc/di.xml` - Service contract mappings

---

## Configuration

### Admin Settings

**Path**: Stores → Configuration → Sales → Bold Checkout Payment Booster

**Settings**:
- Enable Express Pay
- Button styling
- Placement locations (PDP, cart, mini-cart)
- Wallet types enabled

**Config Paths**:
- `checkout/bold_checkout_payment_booster/express_pay_enabled`
- `checkout/bold_checkout_payment_booster/express_pay_pdp`
- `checkout/bold_checkout_payment_booster/express_pay_cart`

---

## Common Patterns

### Button Visibility Logic

**Pattern**: Show buttons only when appropriate

```javascript
canShow: ko.computed(() => {
    return config.enabled && 
           product.isSaleable() && 
           wallet.isAvailable();
})
```

### Payment Nonce Handling

**Pattern**: Wallet provides temporary payment token

**Flow**:
1. Customer approves payment in wallet
2. Wallet returns nonce
3. Nonce sent to module
4. Module creates order with nonce
5. Bold processes payment using nonce

---

## Error Handling

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Buttons not showing | Express Pay disabled | Check config |
| Wallet unavailable | Browser/device not supported | Normal for some devices |
| Order fails | Quote issue | Check quote state |
| Payment declined | Wallet/payment issue | Customer must resolve |

---

## Testing Express Pay

### Manual Testing

**Product Page**:
1. Navigate to product
2. Verify Express Pay buttons visible
3. Click wallet button
4. Complete wallet flow
5. Verify order created

**Requirements**:
- Bold configured correctly
- Express Pay enabled
- Supported browser/device (for some wallets)

---

## Critical Distinctions

### Express Pay vs Normal Checkout

| Aspect | Express Pay | Normal Checkout |
|--------|-------------|-----------------|
| **Entry Point** | Product/Cart page | Checkout page |
| **Data Source** | Wallet | Customer entry |
| **Flow** | One-click | Multi-step |
| **Quote** | Server-created | Session-based |
| **Mode** | Magento-driven | Magento-driven |
| **Hydration** | Required | Required |

### Express Pay Mode

**Mode**: Magento-driven (like normal checkout)

**Why**:
- Customer on Magento pages
- Module creates order
- Requires hydration
- Uses RSA for updates

**Not Bold-driven** because:
- Not using Integration API
- Not Bold Headless API
- Magento pages, not external head

---

## When Working on Express Pay

### Always Consider

1. **Which placement?** (PDP, cart, mini-cart)
2. **Frontend or backend?**
3. **Which wallet?** (behavior may differ)
4. **Is quote creation correct?**
5. **Is this Magento-driven mode?** (yes)

### Key Files to Check

- JavaScript component for placement
- Quote management for backend
- Order placement for order creation
- Layout XML for placement

---

## Summary

### Key Concepts

1. **One-Click Checkout** - Wallet-powered
2. **Multiple Placements** - PDP, cart, mini-cart
3. **EPS SDK** - Wallet integration
4. **Magento-Driven** - Requires hydration
5. **Server-Side Quote** - Pre-filled from wallet

### Technology

- **Frontend**: Knockout.js, EPS SDK
- **Backend**: Service contracts, webapi
- **Wallets**: Apple Pay, Google Pay, PayPal, Venmo, Fastlane

### Remember

- **Magento-driven mode** (not Bold-driven)
- **Requires hydration** (like normal checkout)
- **EPS SDK** handles wallet interactions
- **Multiple placements** (PDP, cart, mini-cart)
- **Customer data from wallet** (pre-filled)
