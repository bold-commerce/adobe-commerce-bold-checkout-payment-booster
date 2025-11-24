# Integration Endpoints (Bold-Driven Mode)

## Overview

The Integration API enables **Bold Checkout to drive the entire checkout process** by calling the module's RESTful endpoints to manage Magento quotes and orders.

**Critical Concept**: In this mode, Bold's order stays current throughout. **NO HYDRATION** is ever needed or used.

---

## Bold-Driven Mode Pattern

### Who's In Control
**Bold Checkout** orchestrates everything by calling the module's Integration API.

### Flow
```
External Head (Agent/MCP/Template)
  → Calls Bold Checkout Headless API (v2)
    → Bold calls Module Integration API
      → Module manages Magento quote
      → Returns calculated totals/shipping
    → Bold updates its order (stays current)
  → Order placement
    → Bold calls Module to place order
      → Module creates Magento order
    → Bold processes payment with EPS
```

### Key Pattern: Stateless API Operations
- Each API call is independent
- No session state required
- Bold order updated in real-time
- **No hydration step** (unlike Magento-driven mode)

---

## Authentication Pattern

### Bearer Token (Shared Key)

**All Integration endpoints** use Bearer token authentication:

```
Authorization: Bearer {shared_key}
```

**How It Works**:
1. Bold registers via `/api/v2/checkout/shop/{shop_identifier}/api_config`
2. Module generates shared secret (stored encrypted)
3. Bold includes secret in all Integration API calls
4. Module validates via `Model/Http/SharedSecretAuthorization.php`

**Key File**: `Model/Http/SharedSecretAuthorization.php`
- Method: `isAuthorized()`
- Uses `hash_equals()` for constant-time comparison
- Logs unauthorized attempts (configurable)

**Config Path**: `checkout/bold_checkout_payment_booster/shared_secret`

---

## API Endpoint Structure

All Integration endpoints follow this pattern:

```
/V1/shops/{shopId}/integration/{resource}/{action}
```

Defined in: `etc/webapi.xml` (lines 67-143)

### Endpoint Categories

| Category | Pattern | Purpose |
|----------|---------|---------|
| **Quote Management** | `/integration/carts/*` | Create, get, update quotes |
| **Cart Items** | `/integration/carts/{cartId}/items` | Add/update/remove items |
| **Shipping** | `/integration/carts/{cartId}/shipping` | Get shipping methods |
| **Order Placement** | `/integration/orders` | Place Magento order |

---

## Quote Lifecycle Pattern

### 1. Quote Creation
**Endpoint**: `POST /V1/shops/{shopId}/integration/carts`

**Pattern**:
- Creates guest or customer quote
- Initializes cart with currency/store
- Returns `cart_id` (Magento quote ID)

**Service Contract**: `Api/Integration/CartManagementInterface::createCart()`

**Implementation**: `Model/Integration/CartManagementApi.php`

### 2. Cart Management
**Endpoints**:
- `GET /integration/carts/{cartId}` - Get quote details
- `PATCH /integration/carts/{cartId}` - Update addresses/email

**Pattern**:
- Stateless operations
- Each call validates cart ownership
- Returns current totals after each update

**Service Contracts**: `Api/Integration/CartManagementInterface`

### 3. Item Management
**Endpoints**:
- `POST /integration/carts/{cartId}/items` - Add items
- `PATCH /integration/carts/{cartId}/items/{itemId}` - Update quantity
- `DELETE /integration/carts/{cartId}/items/{itemId}` - Remove item

**Pattern**:
- Item validation via Magento product repository
- Automatic total recalculation
- Returns updated quote with totals

**Service Contracts**: `Api/Integration/CartItemManagementInterface`

### 4. Shipping Selection
**Endpoint**: `GET /integration/carts/{cartId}/shipping-methods`

**Pattern**:
- Requires shipping address first
- Returns available methods with rates
- Calculated by Magento shipping carriers

**Service Contracts**: `Api/Integration/ShippingMethodManagementInterface`

### 5. Order Placement
**Endpoint**: `POST /integration/orders`

**Pattern**:
- Creates Magento order from quote
- Returns order data to Bold
- Bold then processes payment
- Bold updates module via Integration API after payment

**Service Contracts**: `Api/Integration/OrderManagementInterface`

**Implementation**: `Model/Integration/OrderManagementApi.php`

---

## Service Contract Pattern

### Architecture

```
Api/Integration/
├── CartManagementInterface.php          # Quote CRUD
├── CartItemManagementInterface.php      # Items CRUD
├── ShippingMethodManagementInterface.php # Shipping
└── OrderManagementInterface.php         # Order placement

Model/Integration/
├── CartManagementApi.php                # Implements CartManagement
├── CartItemManagementApi.php            # Implements CartItem
├── ShippingMethodManagementApi.php      # Implements Shipping
└── OrderManagementApi.php               # Implements Order
```

### Pattern: Interface → Implementation → webapi.xml

1. **Define interface** in `Api/Integration/`
2. **Implement** in `Model/Integration/`
3. **Map in DI** (`etc/di.xml`)
4. **Expose via API** (`etc/webapi.xml`)

**Example mapping** in `etc/webapi.xml`:
```xml
<route url="/V1/shops/:shopId/integration/carts" method="POST">
    <service class="Bold\CheckoutPaymentBooster\Api\Integration\CartManagementInterface" method="createCart"/>
    <resources>
        <resource ref="anonymous"/>
    </resources>
</route>
```

---

## Data Flow Pattern

### Typical Integration Flow

```
1. Create Cart
   → POST /integration/carts
   → Returns cart_id

2. Add Items
   → POST /integration/carts/{cartId}/items
   → Returns quote with totals

3. Set Shipping Address
   → PATCH /integration/carts/{cartId}
   → Returns updated quote

4. Get Shipping Methods
   → GET /integration/carts/{cartId}/shipping-methods
   → Returns available methods

5. Set Shipping Method
   → PATCH /integration/carts/{cartId}
   → Returns quote with shipping costs

6. Place Order
   → POST /integration/orders
   → Returns order details
   → Bold processes payment
```

### Key Pattern: Always Return Current State
Every mutation endpoint returns the current quote/order state with recalculated totals.

---

## Response Pattern

### Response Interface Pattern

**Critical**: Integration endpoints use **Response Interfaces**, not direct Magento exceptions.

**Pattern**:
1. Each endpoint returns a specific Response Interface
2. Response objects contain `data` and `errors` arrays
3. HTTP status code set explicitly on response
4. Magento framework serializes to JSON

**Example Structure**:
```
Api/Data/Integration/
├── CreateQuoteResponseInterface.php
├── QuoteDataInterface.php
├── ErrorDataInterface.php
└── ...
```

**Key Methods on Response Interface**:
- `getData()` - Success data payload
- `getErrors()` - Array of error objects
- `setResponseHttpStatus(int $code)` - Set HTTP status
- `addError()` / `addErrorWithMessage()` - Add errors

### Implementation Pattern

**Pattern**: Try-Catch with Response Building

```php
try {
    // Call service layer
    $quoteData = $this->service->execute($cartId);
    
    // Success: Build response
    $response->setData($quoteData);
    $response->setResponseHttpStatus(200);
    return $response;
    
} catch (NoSuchEntityException $e) {
    // Not found: Build error response
    $response->addErrorWithMessage($e->getMessage());
    $response->setResponseHttpStatus(404);
    return $response;
    
} catch (LocalizedException $e) {
    // Validation/business logic error
    $response->addErrorWithMessage($e->getMessage());
    $response->setResponseHttpStatus(422);
    return $response;
    
} catch (\Exception $e) {
    // Unexpected error
    $response->addErrorWithMessage('An error occurred');
    $response->setResponseHttpStatus(500);
    return $response;
}
```

**Key Pattern**: 
1. Service layer throws Magento exceptions
2. API layer catches exceptions
3. API builds appropriate response based on exception type
4. Returns Response Interface object

### Common HTTP Status Codes

| Status | Use Case |
|--------|----------|
| 200 | Success |
| 422 | Validation error |
| 401 | Authentication failed |
| 404 | Resource not found |
| 500 | Server error |

**Important**: Always construct and return response interface objects. Magento handles JSON serialization.

---

## Key Files Reference

### Integration API Service Contracts
- `Api/Integration/*Interface.php` - Endpoint interfaces
- `Api/Data/Integration/*ResponseInterface.php` - Response interfaces
- `Api/Data/Integration/*DataInterface.php` - Data transfer objects

### Integration API Implementations
- `Model/Integration/CartManagementApi.php` - Quote CRUD
- `Model/Integration/CartItemManagementApi.php` - Item management
- `Model/Integration/ShippingMethodManagementApi.php` - Shipping
- `Model/Integration/OrderManagementApi.php` - Order placement
- `Model/Integration/Data/*Response.php` - Response implementations

### Configuration
- `etc/webapi.xml` (lines 67-143) - **CRITICAL**: All endpoint definitions
- `etc/di.xml` - Service contract mappings
- `Model/Http/SharedSecretAuthorization.php` - Auth validation
- `Model/Config.php` - Configuration retrieval

### Manual Testing
- `api-calls/integration/*.http` - Example requests for testing
- `api-calls/README.md` - Testing setup instructions

---

## Critical Distinctions

### Integration API vs Hydration

| Aspect | Integration API (This Doc) | Hydration (Magento-Driven) |
|--------|---------------------------|----------------------------|
| **Mode** | Bold-driven | Magento-driven |
| **Checkout Type** | Headless/Custom | Native Magento |
| **Who Calls** | Bold Checkout | Module (outbound) |
| **Direction** | Inbound to module | Outbound to Bold Checkout |
| **Pattern** | API-based | Observer-based |
| **Order State** | Always current | Dry → Hydrated |
| **Hydration** | ❌ Never used | ✅ Required |
| **Auth** | Bearer token (shared key) | API token (configured) |
| **Module Endpoints** | `/integration/*` (inbound) | None (module calls out) |
| **Outbound Calls** | None (Bold calls in) | Hydration API, State API |
| **Trigger** | Bold API calls | Magento events (observers) |
| **Files** | `Model/Integration/` | `Model/Order/Hydrate*.php`, `Observer/` |

**Key Difference**: 
- **Integration (this doc)**: Bold calls module endpoints (inbound) for quote/order management
- **Hydration**: Module calls Bold endpoints (outbound) to fill order with data

### Integration API vs RSA

| Aspect | Integration API | RSA |
|--------|-----------------|-----|
| **Direction** | Bold → Module (inbound) | Bold → Module (inbound) |
| **Purpose** | Quote/order management | Payment status updates |
| **When Used** | During checkout flow | After payment processing |
| **Auth** | Bearer token (shared key) | HMAC signature |
| **Endpoint Pattern** | `/integration/*` | `/orders/{id}/payments` |
| **Mode** | Bold-driven | Magento-driven |
| **Response** | Response Interface objects | Response Interface objects |
| **Trigger** | Bold Headless API calls | Bold payment processing |
| **Files** | `Model/Integration/*Api.php` | `Model/Order/UpdatePayments.php` |

**Key Difference**:
- **Integration API**: Manages checkout (quotes, items, shipping, order placement)
- **RSA**: Updates payment status after Bold processes payments (invoices, refunds)

---

## Testing Pattern

### Manual Testing with .http Files

**Location**: `api-calls/integration/`

**Setup**:
1. Configure variables in `api-calls/http-client.env.json`
2. Run requests in order (cart → items → shipping → order)
3. Each request uses response from previous

**Pattern**: Sequential dependency
- Use `{{cartId}}` from cart creation
- Use `{{itemId}}` from item addition
- Tests full Integration flow

---

## Summary: Key Patterns to Remember

1. **Stateless API**: Each call is independent, no session required
2. **Bearer Token Auth**: All Integration endpoints use shared key
3. **Response Interfaces**: All endpoints return Response Interface objects with data/errors
4. **Real-time Updates**: Bold order stays current, no hydration
5. **Service Contracts**: All operations via interfaces in `Api/Integration/`
6. **Webapi Routing**: `etc/webapi.xml` is critical for endpoint definitions
7. **Quote-Centric**: Everything revolves around Magento quote management
8. **Bold-Driven**: Bold controls the flow, not Magento
9. **Separate from Magento-Driven**: No code overlap with Observer/Hydration pattern

---

## When Working on Integration Code

**Always Ask**:
1. Is this Bold-driven or Magento-driven? (Don't mix!)
2. Which service contract handles this?
3. Does the endpoint have a Response Interface?
4. Is webapi.xml routing correct?
5. Is Bearer token auth configured?
6. Does this affect hydration? (It shouldn't!)

**Key Files to Check**:
- `etc/webapi.xml` - Endpoint definitions
- `Api/Integration/*Interface.php` - Service contracts
- `Api/Data/Integration/*ResponseInterface.php` - Response interfaces
- `Model/Integration/*Api.php` - Implementations
- `Model/Integration/Data/*Response.php` - Response implementations
- `api-calls/integration/*.http` - Test examples

**Response Pattern Checklist**:
- [ ] Response interface exists in `Api/Data/Integration/`
- [ ] Response implements `getData()`, `getErrors()`, `setResponseHttpStatus()`
- [ ] Success case sets data and returns 200
- [ ] Error cases add errors and set appropriate HTTP status
- [ ] DI mapping in `etc/di.xml` for response interface
