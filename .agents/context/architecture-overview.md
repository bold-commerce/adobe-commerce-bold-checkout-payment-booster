# Architecture Overview

## Purpose

This document explains the module's architecture, focusing on **patterns** and **two distinct operational modes**. This is essential for understanding how the module works and avoiding breaking changes.

---

## Critical Concept: Two Operational Modes

The module operates in **two completely separate modes** that must never be mixed.

### Mode 1: Magento-Driven (Observer + Hydration)

**Pattern**: Event-driven, reactive architecture

**Who's In Control**: Magento

**Key Concept**: Order starts "dry", gets **hydrated** when Magento places order

**Architecture**:
- Observer-based (reacts to Magento events)
- Event → Observer → Hydration → RSA Update
- Payment methods integrated into native checkout
- HMAC authentication (RSA only)

**Critical Files**:
- `Observer/` - Event observers
- `Model/Order/HydrateOrderFromQuote.php` - Hydration logic
- `Model/Order/UpdatePayments.php` - RSA handler
- `etc/events.xml` - Observer mapping

### Mode 2: Bold-Driven (API + Integration)

**Pattern**: RESTful API, stateless architecture

**Who's In Control**: Bold Checkout

**Key Concept**: Order stays current throughout, **NO hydration**

**Architecture**:
- API-based (Bold calls module endpoints)
- Service Contracts → webapi.xml routing
- Bearer token authentication
- Real-time quote management

**Critical Files**:
- `Api/Integration/` - Service contracts
- `Model/Integration/` - API implementations
- `etc/webapi.xml` - Endpoint routing (lines 67-143)
- `Model/Http/SharedSecretAuthorization.php` - Auth

### Quick Comparison

| Aspect | Magento-Driven | Bold-Driven |
|--------|----------------|-------------|
| **Pattern** | Observer-based | API-based |
| **Trigger** | Magento events | API calls |
| **Hydration** | Required | Never used |
| **Auth** | HMAC (RSA only) | Bearer token |
| **Files** | `Observer/`, `events.xml` | `Api/Integration/`, `webapi.xml` |

---

## Module Structure Patterns

### Directory Organization by Responsibility

```
Api/                   # Service contracts (interfaces only)
├── Integration/       # Bold-driven APIs
├── Order/             # Magento-driven (Hydration/RSA)
└── Data/              # DTOs

Model/                 # Business logic implementations
├── Integration/       # Bold-driven implementations
├── Order/             # Magento-driven (Hydration/RSA)
├── Payment/           # Both modes: Payment method implementations
└── Http/              # Outbound API client

Observer/              # Both modes (conditional behavior via integration flag)

etc/
├── webapi.xml         # Both modes: API routing
├── events.xml         # Both modes: Observer mapping
├── di.xml             # Both modes: Dependency injection
└── config.xml         # Both modes: Default config
```

### Pattern: Separation by Mode

**Magento-Driven Code**:
- Anything in `Observer/`
- Files with "Hydrate" in name
- `Model/Order/UpdatePayments.php`
- `Model/Payment/*`

**Bold-Driven Code**:
- Anything in `Api/Integration/` or `Model/Integration/`
- `etc/webapi.xml` (Integration endpoints)

**Shared Code**:
- `Model/Http/BoldClient.php` - Outbound API calls
- `Model/Config.php` - Configuration
- `Model/Http/SharedSecretAuthorization.php` - Auth validation (Bearer for Integration, HMAC for RSA)
- `etc/di.xml` - Dependency injection

---

## Magento 2 Architecture Patterns

### Service Contract Pattern

**Pattern**: Interface-based programming

```
1. Define interface in Api/
2. Implement in Model/
3. Map in etc/di.xml
4. Optionally expose via etc/webapi.xml
```

**Example**:
- Interface: `Api/Integration/CartManagementInterface`
- Implementation: `Model/Integration/CartManagementApi`
- DI mapping: `etc/di.xml` (preference)
- Web API: `etc/webapi.xml` (route)

**Key Files**:
- `etc/di.xml` - All preference mappings
- `etc/webapi.xml` - API endpoint exposure

### Dependency Injection Pattern

**Pattern**: Constructor injection, no direct instantiation

All dependencies injected via constructor:
```php
public function __construct(
    ConfigInterface $config,
    BoldClientInterface $client
) {
    // No 'new' keyword anywhere
}
```

**Configuration**: `etc/di.xml`
- `<preference>` - Interface to implementation mapping
- `<type><arguments>` - Constructor argument configuration

### Observer Pattern (Both Modes with Conditional Behavior)

**Pattern**: Event-driven reactions with mode-aware logic

**Flow**:
1. Magento dispatches event
2. Observer registered in `etc/events.xml` is called
3. Observer checks integration flag on quote/order
4. Observer executes mode-appropriate logic:
   - **Magento-driven**: Perform hydration and full flow
   - **Bold-driven**: Skip hydration (integration flag set)

**Key Files**:
- `etc/events.xml` - Event to observer mapping
- `Observer/` - Observer implementations with conditional logic

**Example Events**:
- `sales_order_place_after` - After order placement
- `checkout_submit_all_after` - After checkout submission

**Critical**: Observers must check for integration quotes to avoid hydrating Bold-driven orders

### Plugin Pattern (Interceptor)

**Pattern**: Method interception (before, after, around)

Used to modify behavior of existing classes without inheritance.

**Key Files**:
- `etc/di.xml` - Plugin declarations
- `Plugin/` - Plugin implementations

### Web API Pattern (Bold-Driven Only)

**Pattern**: RESTful API routing

**Flow**:
1. HTTP request arrives
2. `etc/webapi.xml` routes to service contract method
3. Authentication validated
4. Method executed
5. Response returned

**Key File**: `etc/webapi.xml`

**Example Route**:
```xml
<route url="/V1/shops/:shopId/integration/carts" method="POST">
    <service class="..." method="createCart"/>
    <resources><resource ref="anonymous"/></resources>
</route>
```

---

## Authentication Patterns

### Two Authentication Methods

Both methods use the same `Model/Http/SharedSecretAuthorization.php` class with different validation modes:

**1. Bearer Token (Integration API)**
- Used by: Bold-driven mode
- Pattern: `Authorization: Bearer {shared_key}`
- Validation: `SharedSecretAuthorization->isAuthorized($websiteId, true)`
- Storage: Encrypted in `core_config_data`

**2. HMAC Signature (RSA)**
- Used by: RSA payment updates (Magento-driven mode)
- Pattern: `Signature: signature="{hmac}"` + `X-HMAC-Timestamp: {timestamp}`
- Validation: `SharedSecretAuthorization->isAuthorized($websiteId, false)`
- Uses: Same shared secret, different authentication method

---

## API Endpoint Organization

### Endpoint Patterns

**Integration Endpoints** (Bold-driven):
```
/V1/shops/{shopId}/integration/carts
/V1/shops/{shopId}/integration/carts/{cartId}
/V1/shops/{shopId}/integration/carts/{cartId}/items
/V1/shops/{shopId}/integration/orders
```

**Hydration** (Magento-driven - Outbound to Bold):
```
checkout_sidekick/{{shopId}}/order/{publicOrderId}
```
- Direction: Module → Bold Checkout (via BoldClient)
- Triggered by: Observer after order placement

**RSA Endpoints** (Magento-driven):
```
/V1/shops/{shopId}/orders/{publicOrderId}/payments
```

**Pattern**: All use service contracts, all defined in `etc/webapi.xml`

---

## Configuration System Pattern

### Configuration Hierarchy

**System Configuration** (`etc/system.xml`):
- Admin UI configuration fields
- Stored in `core_config_data` table
- Retrieved via `Model/Config.php`

**Default Configuration** (`etc/config.xml`):
- Default values
- Fallback when no admin config exists

**Programmatic Access**:
- All config accessed via `Model/Config.php`
- Scoped by website/store
- Encrypted values (shared secret)

**Key Configuration Paths**:
- `checkout/bold_checkout_payment_booster/api_url`
- `checkout/bold_checkout_payment_booster/shop_identifier`
- `checkout/bold_checkout_payment_booster/shared_secret`

---

## Frontend Integration Pattern

### Technology Stack

- **Module Loader**: RequireJS
- **Data Binding**: Knockout.js
- **Styling**: LESS
- **Layout**: Magento Layout XML

### Integration Points

**Native Checkout** (Magento-driven):
- Payment method renderers
- Knockout components
- EPS SDK injection

**Express Pay** (Can be either mode):
- Product page buttons
- Cart page buttons
- Mini-cart buttons

**Key Directories**:
- `view/frontend/web/js/` - JavaScript components
- `view/frontend/web/template/` - Knockout templates
- `view/frontend/layout/` - Layout XML
- `view/frontend/web/css/` - LESS styles

---

## Database Pattern

### Schema Management

**Pattern**: Declarative schema (no install scripts)

**Key File**: `etc/db_schema.xml`

**Tables**:
- Minimal custom tables
- Mostly uses Magento core tables (quote, order, payment)

---

## Outbound API Communication

### Bold Checkout API Client

**Pattern**: HTTP client for outbound calls to Bold

**Key File**: `Model/Http/BoldClient.php`

**Usage**:
- Hydration API calls
- Order state updates
- Payment authorization
- Shop registration

**Configuration**: Retrieved from `Model/Config.php`

**Base URLs**:
- Determined by configured API URL
- Per-website configuration
- Supports multiple environments

---

## Key Architectural Principles

### 1. Mode Separation
Never mix Magento-driven and Bold-driven code paths.

### 2. Service Contracts
All business logic exposed via interfaces.

### 3. Dependency Injection
No direct instantiation, all dependencies injected.

### 4. Single Responsibility
Each class/file has one clear purpose.

### 5. Magento Standards
Follows all Magento 2 architectural patterns.

### 6. Public API Safety
No exposure of internal Bold architecture.

---

## When Working on the Module

### Always Ask

1. **Which mode am I working on?**
   - Magento-driven (Observer/Hydration/RSA)
   - Bold-driven (Integration API)

2. **What pattern am I using?**
   - Observer pattern
   - Service contract pattern
   - API pattern
   - Plugin pattern

3. **What files need updating?**
   - Service contract in `Api/`
   - Implementation in `Model/`
   - DI mapping in `etc/di.xml`
   - API routing in `etc/webapi.xml` (if API)
   - Observer mapping in `etc/events.xml` (if observer)

### Critical Files Checklist

**For Bold-Driven Work**:
- [ ] Service contract in `Api/Integration/`
- [ ] Implementation in `Model/Integration/`
- [ ] DI preference in `etc/di.xml`
- [ ] Route in `etc/webapi.xml`
- [ ] Bearer token auth configured

**For Magento-Driven Work**:
- [ ] Observer in `Observer/`
- [ ] Event mapping in `etc/events.xml`
- [ ] Hydration logic (if needed)
- [ ] RSA endpoint (if payment update)

---

## Summary

### Key Patterns

1. **Two-Mode Architecture** - Separate code paths
2. **Service Contracts** - Interface-based programming
3. **Dependency Injection** - Constructor injection
4. **Web API** - RESTful endpoints (Bold-driven)
5. **Observers** - Event-driven (Magento-driven)
6. **Configuration** - Centralized in `Model/Config.php`

### Essential Files

- `etc/webapi.xml` - API routing (Bold-driven)
- `etc/events.xml` - Observer mapping (Magento-driven)
- `etc/di.xml` - Dependency injection (both)
- `Model/Config.php` - Configuration access (both)

### Critical Distinctions

Remember: **Magento-driven uses Observers and Hydration**. **Bold-driven uses APIs with no Hydration**.
