# Bold Checkout Integration (Outbound Communication)

## Overview

This document describes how the module **communicates TO Bold Checkout** - the outbound API calls pattern. This is separate from:
- **Integration API** (Bold calls module - inbound)
- **RSA** (Bold calls module - inbound)

---

## Pattern: HTTP Client for Outbound Calls

### Architecture

```
Module → BoldClient → Bold Checkout Service
```

**Purpose**: Module initiates calls to Bold Checkout for:
- Order hydration (Magento-driven mode)
- Shop registration
- Order state updates
- Payment authorization coordination

---

## BoldClient Pattern

### The HTTP Client

**File**: `Model/Http/BoldClient.php`

**Pattern**: Centralized HTTP client for all outbound Bold API calls

**Key Responsibilities**:
- Build HTTP requests
- Add authentication headers
- Send requests to Bold
- Handle responses
- Log errors

### Configuration Pattern

**File**: `Model/Config.php`

**Key Methods**:
- `getApiUrl()` - Bold API base URL
- `getShopId()` - Shop identifier
- `getApiToken()` - Authentication token
- `getSharedSecret()` - Shared secret for HMAC

**Config Paths**:
- `checkout/bold_checkout_payment_booster/api_url`
- `checkout/bold_checkout_payment_booster/shop_identifier`
- `checkout/bold_checkout_payment_booster/api_token`

**Scope**: Per-website configuration (multi-store support)

---

## API URL Construction Pattern

### Base URL

Retrieved from configuration: `Model/Config::getApiUrl()`

**Examples**:
- Production: `https://api.boldcommerce.com`
- Staging: `https://api.staging.boldcommerce.com`
- Local: Custom URL for development
  - **Note**: Local dev uses `bold.ninja` tunnel domains with path-based routing through staging API to redirect to local checkout instances

### Endpoint Patterns

**Hydration** (Magento-driven):
```
PUT checkout_sidekick/{{shopId}}/order/{publicOrderId}
```

**State** (Magento-driven - Capture Queue):
```
POST checkout_sidekick/{{shopId}}/order/{publicOrderId}/state
```

**Shop Registration** (Setup):
```
POST api/v2/checkout/shop/{shop_identifier}/api_config
```

**Pattern**: Relative paths resolved via `BoldClient` with configured base URL

---

## Authentication for Outbound Calls

### API Token Authentication

**Pattern**: Bearer token in Authorization header

**Header**:
```
Authorization: Bearer {api_token}
```

**Token Source**: Module configuration (admin-configured)

**Usage**: All outbound calls from module to Bold

**Note**: This is **different** from Integration API auth (which is inbound with shared key)

---

## Common Outbound Operations

### 1. Order Hydration

**When**: After Magento order placement (Magento-driven mode)

**URL**: `PUT checkout_sidekick/{{shopId}}/order/{publicOrderId}`

**Purpose**: Fill Simple Order with complete cart/customer data

**Implementation**: `Model/Order/HydrateOrderFromQuote.php`

**Pattern**:
1. Observer triggers after order placement
2. Build hydration payload from quote (customer, items, addresses, totals)
3. Use `BoldClient->put()` to call Bold Checkout Sidekick
4. Include API token in headers
5. Handle response (success/failure)
6. Bold order now has complete data for payment authorization

### 2. Order State Update

**When**: After order placement, before capture

**URL**: `POST checkout_sidekick/{{shopId}}/order/{publicOrderId}/state`

**Purpose**: Tell Bold "order is ready, queue the capture"

**Pattern**:
1. Order placed and hydrated
2. Module calls state endpoint via `BoldClient`
3. Bold queues asynchronous payment capture
4. Bold later calls RSA endpoint to update Magento with payment status

### 3. Shop Registration

**When**: Initial module setup, shared secret generation

**Endpoint**: `POST /api/v2/checkout/shop/{shop_identifier}/api_config`

**Purpose**: Register integration, exchange shared secret

**Pattern**:
1. Admin triggers registration
2. Module generates shared secret
3. Sends to Bold via shop registration endpoint
4. Bold stores secret for Integration API auth

---

## Request/Response Pattern

### Standard Request Structure

**Headers**:
```
Authorization: Bearer {api_token}
Content-Type: application/json
```

**Body**: JSON payload (varies by endpoint)

### Standard Response Handling

**Success**: HTTP 200-299
- Parse JSON response
- Extract needed data
- Return success

**Error**: HTTP 400-599
- Log error details
- Parse error message
- Throw appropriate exception
- Handle gracefully

**Pattern**: Used consistently across all BoldClient calls

---

## Key Files Reference

### Outbound Communication
- `Model/Http/BoldClient.php` - HTTP client
- `Model/Config.php` - Configuration access
- `Model/Order/HydrateOrderFromQuote.php` - Hydration calls
- `Model/Order/OrderStateUpdater.php` - State update calls

### Configuration
- `etc/adminhtml/system.xml` - Admin config fields
- `etc/config.xml` - Default configuration
- `Model/Config.php` - Config retrieval

---

## Configuration Access Pattern

### Centralized Configuration

**Pattern**: All config access through `Model/Config.php`

**Example Usage**:
```php
$apiUrl = $this->config->getApiUrl($websiteId);
$shopId = $this->config->getShopId($websiteId);
$apiToken = $this->config->getApiToken($websiteId);
```

**Benefits**:
- Consistent config access
- Website scope handling
- Encrypted value decryption
- Fallback to defaults

---

## Multi-Store Pattern

### Website-Scoped Configuration

**Pattern**: Each website can have different Bold configuration

**Use Case**: Multi-brand stores with separate Bold accounts

**Implementation**:
- Config stored with website scope
- Config retrieval requires website ID
- BoldClient uses website-specific config

---

## Error Handling Pattern

### Outbound Call Failures

**Pattern**: Try → Catch → Log → Handle gracefully

**Common Errors**:
- Network timeout
- Invalid API token
- Bold service unavailable
- Invalid request payload

**Handling**:
- Log full error details
- Don't expose Bold internals to customer
- Graceful degradation where possible
- Inform merchant in admin if critical

---

## When Working with Outbound Integration

### Always Consider

1. **Which endpoint am I calling?**
2. **Which mode uses this?** (Magento-driven usually)
3. **Is authentication correct?** (API token, not shared key)
4. **How do I handle errors?**
5. **Is this website-scoped?**

### Key Files to Check

- `Model/Http/BoldClient.php` - HTTP client logic
- `Model/Config.php` - Configuration
- Implementation file (Hydration, State, etc.)

---

## Critical Distinctions

### Outbound vs Inbound Communication

| Aspect | Outbound (This Doc) | Inbound (Integration/RSA) |
|--------|---------------------|---------------------------|
| **Direction** | Module → Bold | Bold → Module |
| **Initiator** | Module | Bold |
| **Auth** | API token | Shared key or HMAC |
| **Pattern** | HTTP client calls | Web API endpoints |
| **Mode** | Magento-driven | Bold-driven or RSA |
| **Files** | `BoldClient.php` | `Api/`, `Model/Integration/` |

### Different Auth Methods

**Outbound (Module → Bold)**:
- Uses API Token
- `Authorization: Bearer {api_token}`
- Token configured in module
- Used for hydration, state updates

**Inbound Integration (Bold → Module)**:
- Uses Shared Secret
- `Authorization: Bearer {shared_secret}`
- Secret generated by module
- Used for Integration API

**Inbound RSA (Bold → Module)**:
- Uses HMAC Signature
- `Authorization: {hmac_signature}`
- Signed with shared secret
- Used for payment updates

---

## Summary

### Key Concepts

1. **BoldClient** - HTTP client for outbound calls
2. **API Token** - Authentication for module → Bold
3. **Configuration** - Centralized in `Model/Config.php`
4. **Hydration** - Main use case for outbound calls
5. **Multi-Store** - Website-scoped configuration

### Common Operations

- Order hydration (Magento-driven)
- Order state updates (capture queue)
- Shop registration (initial setup)

### Remember

- **Outbound** = Module calls Bold (this doc)
- **Inbound** = Bold calls Module (Integration/RSA)
- **API Token** for outbound, **not** shared secret
- **Magento-driven mode** uses outbound calls most
- **BoldClient** centralizes all HTTP communication
