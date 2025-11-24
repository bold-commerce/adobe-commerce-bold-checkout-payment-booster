# RSA Endpoints (Payment Status Updates)

## Overview

**RSA (Remote State Authority)** is Bold Checkout's pattern where an external system (the platform) is the authoritative source for order state. The module implements a **limited RSA pattern** for receiving payment status updates from Bold Checkout.

---

## What is RSA?

### RSA Concept

**Remote State Authority (RSA)** is a Bold Checkout mechanism where:
- The **platform (Magento) is the authoritative source** for order state
- Bold Checkout synchronizes with the platform to get/update order state
- The platform provides authoritative data (totals, items, customer info, payment status)

### RSA in Magento 2 Booster

**Implementation**: **Limited/Unidirectional** (payment updates only)

The Magento 2 Booster implements **only ONE RSA endpoint**:
- **Endpoint**: `/orders/{publicOrderId}/payments` (payment status updates)
- **Direction**: Bold Checkout → Module (inbound)
- **Purpose**: Receive payment status updates after Bold processes payments

**What M2 Booster does NOT implement**:
- Full RSA order state synchronization
- RSA for order hydration (uses Sidekick hydration instead)
- Other RSA event types (customer, address, items, shipping)

**Pattern**:
```
Bold Checkout processes payment (authorize/capture/refund)
  → Bold calls Module RSA endpoint
  → Module validates HMAC signature
  → Module updates Magento order
  → Creates invoice/credit memo/cancellation
```

**Mode**: Used in **Magento-driven mode** (after hydration)

---

## RSA Endpoint

### Endpoint Details

**Method**: `PUT`

**Path**: `/V1/shops/{shopId}/orders/{publicOrderId}/payments`

**Parameters**:
- `shopId` - Shop identifier (from config)
- `publicOrderId` - Bold's order ID

**Authentication**: HMAC signature (NOT Bearer token)

**Definition**: `etc/webapi.xml`

**Service Contract**: `Api/Order/UpdatePaymentsInterface`

**Implementation**: `Model/Order/UpdatePayments.php`

---

## HMAC Authentication Pattern

### Why HMAC?

**Security**: Verifies the call is actually from Bold, not spoofed

**Pattern**: HMAC-SHA256 signature of request

### How HMAC Works

1. Bold creates signature using shared secret
2. Bold includes signature in Authorization header
3. Module recreates signature from request
4. Module compares signatures (constant-time)
5. If match → valid, if not → rejected

### Signature Validation

**Implementation**:
- **Endpoint**: `Model/Order/UpdatePayments.php`
- **Validation**: `Model/Http/SharedSecretAuthorization.php`
- **Method**: `SharedSecretAuthorization->isAuthorized($websiteId, false)`

**Pattern**:
- Extracts `Signature` header and `X-HMAC-Timestamp` header
- Validates timestamp (must be within 3 seconds)
- Computes HMAC-SHA256 signature using shared secret
- Compares signatures using constant-time comparison (`hash_equals`)

**Secret Source**: `checkout/bold_checkout_payment_booster/shared_secret`

**Note**: Same `SharedSecretAuthorization` class and shared secret used for Integration API, but different auth method (HMAC vs Bearer)

---

## Financial Status Actions

### Status Types

RSA calls indicate what happened with the payment at Bold/EPS:

| Status | Meaning | Module Action |
|--------|---------|---------------|
| **paid** | Payment captured | Create invoice |
| **refunded** | Payment refunded | Create credit memo |
| **voided** | Payment voided | Cancel order |
| **authorized** | Payment authorized | Update order status |

### Payment Status Flow

**Typical Flow**:
```
1. authorized → Order authorized, waiting
2. paid → Capture completed, create invoice
3. refunded (optional) → Partial/full refund, create credit memo
```

---

## Invoice Creation Pattern

### When Invoked

RSA call with `financial_status: paid`

### Pattern

1. Validate HMAC signature
2. Load Magento order
3. Check if can invoice (not already invoiced)
4. Create invoice
5. Capture payment (offline, already done at Bold)
6. Save invoice
7. Update order status

**Implementation**: `Model/Order/InvoiceOrder.php`

**Key Methods**:
- `execute()` - Main invoice creation
- `canInvoice()` - Validation
- `createInvoice()` - Invoice generation

---

## Credit Memo Pattern

### When Invoked

RSA call with `financial_status: refunded`

### Pattern

1. Validate HMAC signature
2. Load Magento order
3. Check if can refund (invoiced, not fully refunded)
4. Create credit memo
5. Offline refund (already done at Bold)
6. Save credit memo
7. Update order status

**Implementation**: `Model/Order/RefundOrder.php`

**Refund Types**:
- **Full refund** - Entire order amount
- **Partial refund** - Specific amount or items

---

## Order Cancellation Pattern

### When Invoked

RSA call with `financial_status: voided`

### Pattern

1. Validate HMAC signature
2. Load Magento order
3. Check if can cancel (not already completed/canceled)
4. Cancel order
5. Update order status
6. Restore inventory (if configured)

**Implementation**: `Model/Order/CancelOrder.php`

---

## Request/Response Pattern

### Request Structure

**Headers**:
```
Authorization: {hmac_signature}
Content-Type: application/json
```

**Body** (example):
```json
{
    "financial_status": "paid",
    "payment_details": {...},
    "amount": 12345,
    "currency": "USD"
}
```

### Response Structure

**Success**: HTTP 200
```json
{
    "success": true,
    "message": "Payment updated successfully"
}
```

**Error**: HTTP 400/401/500
```json
{
    "message": "Error message",
    "parameters": []
}
```

---

## Key Files Reference

### RSA Implementation
- `Model/Order/UpdatePayments.php` - Main RSA handler
- `Model/Order/InvoiceOrder.php` - Invoice creation
- `Model/Order/RefundOrder.php` - Credit memo creation
- `Model/Order/CancelOrder.php` - Order cancellation

### Configuration
- `etc/webapi.xml` - RSA endpoint definition
- `Model/Config.php` - Shared secret retrieval
- `Api/Order/UpdatePaymentsInterface.php` - Service contract

---

## RSA vs Integration API

### Critical Differences

| Aspect | RSA | Integration API |
|--------|-----|-----------------|
| **Purpose** | Payment updates | Quote/order management |
| **Direction** | Bold → Module | Bold → Module |
| **Mode** | Magento-driven | Bold-driven |
| **Auth** | HMAC signature | Bearer token |
| **Endpoint** | `/orders/.../payments` | `/integration/*` |
| **When** | After payment processing | During checkout |

### RSA is NOT Integration

**RSA (Limited Implementation)**:
- Bold Checkout pattern for platform as authority
- M2 implements payment status updates only
- Creates invoices/credit memos
- Part of Magento-driven flow
- After hydration and capture
- Unidirectional: Bold Checkout → M2

**Integration**:
- Direct module API for quote/order management
- Part of Bold-driven flow
- No hydration involved
- Before payment processing
- Bidirectional: Bold Checkout ↔ M2

**Note**: Full RSA implementations in other platforms may include order state synchronization, customer updates, and cart management. M2 Booster uses Sidekick hydration instead of full RSA.

### Architectural Direction

**Moving Away from RSA**:
- Integration endpoints are designed to **replace RSA requirements** for checkout orchestration
- RSA adds extra network hops between Bold Checkout and the platform
- Direct Integration API calls are more efficient and reduce latency

**Platform Connector Integration**:
- Bold has a platform connector integration between Bold Checkout and M2
- Used for headless checkout with hosted templates
- That integration implements the complete RSA flow (all event types)
- The Booster module's Integration API approach is the preferred pattern going forward

**Why Integration Over RSA**:
- Fewer network hops (Bold → M2 directly)
- Simpler request/response pattern
- Better error handling and debugging
- More efficient for headless checkout scenarios

---

## Error Handling Pattern

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| 401 Unauthorized | Invalid HMAC | Check shared secret config |
| 404 Not Found | Invalid order ID | Verify publicOrderId mapping |
| 400 Bad Request | Invalid status | Check financial_status value |
| 500 Server Error | Invoice creation failed | Check Magento order state |

### Validation Pattern

**Pre-checks before action**:
1. HMAC signature valid?
2. Order exists?
3. Order uses Bold payment?
4. Action is valid for current state?
5. Can perform action (invoice/refund/cancel)?

---

## When Working with RSA

### Always Consider

1. **Is HMAC signature validated?**
2. **What financial status is being handled?**
3. **Can Magento order perform this action?**
4. **Is this Magento-driven mode?** (should be)
5. **Does this affect Integration API?** (shouldn't)

### Key Files to Check

- `Model/Order/UpdatePayments.php` - Main handler
- `Model/Order/InvoiceOrder.php` - Invoice logic
- `Model/Order/RefundOrder.php` - Refund logic
- `etc/webapi.xml` - Endpoint definition

---

## Testing RSA Flow

### Manual Testing

**Setup**: Use `api-calls/*.http` files (if available)

**Pattern**:
1. Place order in Magento (Magento-driven mode)
2. Order gets hydrated
3. Bold captures payment
4. Simulate RSA call with HMAC
5. Verify invoice created in Magento

### HMAC Generation for Testing

**Pattern**:
```
signature = HMAC-SHA256(request_body, shared_secret)
```

**Header**:
```
Authorization: {signature}
```

---

## Common Patterns

### Loading Order by Public ID

**Pattern**:
```php
// Public Order ID stored in payment additional_information
$order->getPayment()->getAdditionalInformation('public_order_id');
```

### Checking if Already Invoiced

**Pattern**:
```php
if ($order->hasInvoices()) {
    // Already invoiced
}
```

### Offline Capture/Refund

**Pattern**: Payment already processed at Bold, just record in Magento
```php
$invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
```

---

## Summary

### Key Concepts

1. **RSA** - Remote State Authority (platform as authoritative source)
2. **Limited RSA Implementation** - M2 Booster only implements payment updates
3. **HMAC Authentication** - Signature validation
4. **Payment Updates** - Invoice/refund/cancel
5. **Magento-Driven** - Part of hydration flow
6. **Inbound** - Bold Checkout calls module

### Critical Flow

```
Bold processes payment → Calls RSA endpoint → HMAC validation → Update Magento order
```

### Remember

- **RSA = Remote State Authority** (not "Remote Store Adapter")
- **Limited RSA**: M2 Booster only implements payment update endpoint
- **HMAC auth**, not Bearer token
- **Only RSA** uses HMAC, Integration uses Bearer
- **Magento-driven mode** only
- **After payment processing** at Bold Checkout
- **Creates financial documents** (invoice/credit memo)
- **Separate from Integration API** (different purpose, auth, flow)
