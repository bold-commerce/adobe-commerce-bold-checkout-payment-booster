# Bold Checkout Payment Booster - AI Agent & Developer Guide

> **Magento 2 module** providing Bold Checkout payment integration for Adobe Commerce stores, enabling digital wallets, Pay Later options, and headless checkout capabilities.

## Quick Start

### For AI Agents

**Essential Reading (in order)**:
1. This file (overview & quick reference)
2. [Architecture Overview](.agents/context/architecture-overview.md) - Module structure & two operational modes
3. [Integration Endpoints](.agents/context/integration-endpoints.md) - Bold-driven headless checkout
4. [Magento-Driven Checkout](.agents/context/magento-driven-checkout.md) - Native Magento checkout with hydration

**When Working on Specific Tasks**:
- **Integration API endpoints**: [Integration Endpoints](.agents/context/integration-endpoints.md)
- **Payment methods & hydration**: [Magento-Driven Checkout](.agents/context/magento-driven-checkout.md)
- **RSA payment updates**: [RSA Endpoints](.agents/context/rsa-endpoints.md)
- **Outbound Bold API calls**: [Bold Checkout Integration](.agents/context/bold-checkout-integration.md)
- **Express Pay / Digital Wallets**: [Express Pay](.agents/context/express-pay.md)
- **Frontend JavaScript**: [Frontend Components](.agents/context/frontend-components.md)
- **Magento patterns**: [Magento Patterns](.agents/context/magento-patterns.md)
- **Code style**: [Coding Standards](.agents/context/coding-standards.md)

### For Developers

**Installation & Setup**: See [README.md](README.md), Confluence documentation, and [Bold Booster Documentation](https://developer.boldcommerce.com/guides/checkout/bold-boosters/bold-booster-for-paypal-overview)

**Documentation Structure**:
- This file: Overview, quick reference, operational modes
- `.agents/context/`: Detailed architectural context
- `api-calls/`: HTTP request examples for manual testing

---

## Module Overview

**Bold Checkout Payment Booster** is a Magento 2 module that integrates Bold's payment processing capabilities into Adobe Commerce stores.

### Core Features

- **Digital Wallets**: Apple Pay, Google Pay, PayPal, Venmo
- **Pay Later Options**: Pay in 4, Pay Monthly
- **Fastlane by PayPal**: Accelerated guest checkout
- **Headless Checkout**: Integration API for Bold Checkout v2 (Headless APIs)
- **Native Checkout**: Enhanced Magento checkout with Bold payments
- **Multiple Payment Gateways**: 700+ payment methods via PayPal, Nuvei, Stripe, Braintree, etc.

### Technology Stack

- **Platform**: Magento 2.3.x - 2.4.x / Adobe Commerce
- **Language**: PHP 7.2 - 8.5 (code must be PHP 7.2 compatible)
- **Standards**: PSR-12, Magento 2 coding standards
- **Static Analysis**: PHPStan level 7
- **Frontend**: RequireJS, Knockout.js, LESS
- **API**: RESTful Web APIs (webapi.xml)

**Critical**: Code must maintain backward compatibility with PHP 7.2 to support all Magento 2 versions.

---

## Critical: Two Operational Modes

**This is the MOST IMPORTANT concept to understand when working with this module.**

The module operates in **two completely separate, incompatible modes**. Understanding which mode you're working with is **CRITICAL** to avoid breaking functionality. These modes have different patterns, different flows, and different architectural approaches.

### Mode 1: Magento-Driven (Native Checkout + Hydration)

**Who's In Control**: Magento drives the checkout experience

**Flow**: Customer → Magento Checkout → Bold Payment → Hydration → Payment Processing → RSA Update

**Key Concept**: Order starts "dry" (minimal data), gets **hydrated** when Magento places order

**Architecture Pattern**:
- **Observer-based**: Magento events trigger operations
- **Reactive**: Module reacts to Magento checkout flow
- **Hydration**: Critical step to fill Bold order with quote data
- **Simple Order**: Lightweight order structure

**When Used**: Native Magento checkout with Bold payment methods

**Key Files to Understand**:
- `Observer/Order/AfterSubmitObserver.php` - Triggers hydration
- `Model/Order/HydrateOrderFromQuote.php` - Hydration logic
- `Model/Order/UpdatePayments.php` - RSA payment updates
- `etc/events.xml` - Observer mappings

### Mode 2: Bold-Driven (Headless + Integration API)

**Who's In Control**: Bold Checkout orchestrates everything

**Flow**: External Head → Bold API → Module Integration API → Quote Management → Order Placement

**Key Concept**: Order stays current throughout, **NO hydration ever needed**

**Architecture Pattern**:
- **API-based**: RESTful Integration endpoints
- **Stateless**: Each call is independent
- **Real-time**: Bold order updated as operations happen
- **Service Contract**: All operations via webapi.xml endpoints

**When Used**: Headless checkout, AI agents, custom checkout experiences

**Key Files to Understand**:
- `Model/Integration/*Api.php` - Integration API implementations
- `Api/Integration/*Interface.php` - Service contracts
- `etc/webapi.xml` (lines 67-143) - Endpoint definitions
- `Model/Http/SharedSecretAuthorization.php` - Auth validation (Bearer & HMAC)

### Critical Differences

| Aspect | Magento-Driven | Bold-Driven |
|--------|----------------|-------------|
| **Control** | Magento | Bold Checkout |
| **Pattern** | Observer-based | API-based |
| **Order State** | Dry → Hydrated | Always current |
| **Hydration** | ✅ Required | ❌ Never used |
| **Auth** | HMAC (RSA only) | Bearer token |
| **Module Endpoints** | RSA: `PUT /orders/{id}/payments` (inbound) | Integration: `/integration/*` (inbound) |
| **Calls to Bold** | Hydration API, state, authorize (outbound) | None (Bold calls module) |
| **Trigger** | Magento events | API calls from Bold |

### How to Determine Which Mode

**Magento-Driven** if:
- Customer using native Magento checkout
- Payment methods: `bold`, `bold_wallet`, `bold_fastlane`
- Observer code is involved
- Hydration is mentioned
- RSA payment updates

**Bold-Driven** if:
- External system calling Integration API
- Endpoint path contains `/integration/`
- Observers check integration flag and skip Magento-driven behavior
- No hydration performed
- Bearer token authentication

**⚠️ WARNING**: When working on a task, **always confirm which mode** before making changes. Code for one mode should NOT affect the other mode.

---

## Architecture Overview

### Key Architectural Patterns

**1. Two-Mode Architecture**
- Separate code paths for Magento-driven vs Bold-driven
- No code sharing between modes (prevents conflicts)
- Each mode has its own entry points and flow

**2. Service Contract Pattern** (Magento 2 Standard)
- Interfaces in `Api/` directory
- Implementations in `Model/` directory  
- Mapped via `etc/di.xml`
- Enables web API exposure via `etc/webapi.xml`

**3. Observer Pattern** (Magento-Driven Behavior)
- Reacts to Magento events
- Defined in `etc/events.xml`
- Located in `Observer/` directory
- **Observers execute in both modes** but check integration flag
- Skip Magento-driven behavior (hydration) for integration quotes

**4. RESTful API Pattern** (Bold-Driven Mode Only)
- Service contracts exposed via `etc/webapi.xml`
- Bearer token authentication
- Stateless request/response
- **NOT used in Magento-driven mode**

### Critical Directory Structure

```
Api/
├── Integration/          # Bold-driven: Service contracts
├── Order/                # Magento-driven: Hydration & RSA contracts
└── Data/                 # DTOs

Model/
├── Integration/          # Bold-driven: Implementations
├── Order/                # Magento-driven: Hydration & RSA implementations
├── Payment/              # Both modes: Payment method implementations
└── Http/                 # Shared: Outbound client

Observer/                 # Both modes (conditional behavior)
etc/
├── webapi.xml            # Both modes: API routing for all endpoints
├── events.xml            # Both modes: Observer configuration
└── di.xml                # Both modes: Dependency injection
```

### Understanding Which Code Does What

**Magento-Driven Only**:
- `Model/Order/Hydrate*.php` - Hydration logic
- `Model/Order/UpdatePayments.php` - RSA handler
- `Api/Order/` - Hydration & RSA service contracts

**Bold-Driven Only**:
- `Model/Integration/` - Integration API implementations
- `Api/Integration/` - Integration service contracts

**Both Modes (Conditional)**:
- `Observer/` - Check integration flag, skip hydration if Bold-driven
- `Model/Payment/` - Payment method implementations
- `etc/events.xml` - Observer configuration
- `etc/webapi.xml` - API routing (Integration endpoints ~lines 67-143, Hydration/RSA in other sections)

**Shared**:
- `Model/Http/BoldClient.php` - Outbound API client
- `Model/Config.php` - Configuration
- `etc/di.xml` - Dependency injection

### API Endpoint Organization

**Integration Endpoints** (Bold-driven):
- Module endpoints: `/V1/shops/:shopId/integration/*`
- Authentication: `Authorization: Bearer {shared_key}`
- Direction: Bold → Module (inbound)
- See [Integration Endpoints](.agents/context/integration-endpoints.md)

**Hydration** (Magento-driven):
- Outbound API call: Module → Bold Checkout Sidekick
- URL: `checkout_sidekick/{{shopId}}/order/{publicOrderId}`
- Triggered by: Observer after order placement
- See [Magento-Driven Checkout](.agents/context/magento-driven-checkout.md)

**RSA Endpoint** (Magento-driven):
- RSA = Remote State Authority (Bold Checkout pattern, limited implementation here)
- Module endpoint: `/V1/shops/:shopId/orders/:publicOrderId/payments`
- Authentication: HMAC signature
- Direction: Bold → Module (inbound)
- Purpose: Payment status updates only
- See [RSA Endpoints](.agents/context/rsa-endpoints.md)

**Express Pay Endpoints**:
- Module endpoints: `/V1/express_pay/order/*`
- See [Express Pay](.agents/context/express-pay.md)

---

## Agent Context Documentation

Detailed context documentation is available in `.agents/context/`. These documents are organized by topic for efficient navigation.

### Architecture & Code Style

1. **[Architecture Overview](.agents/context/architecture-overview.md)** - Module structure, DI, two operational modes
2. **[Coding Standards](.agents/context/coding-standards.md)** - PSR-12, Magento 2 standards, PHPStan

### Operational Modes

3. **[Integration Endpoints](.agents/context/integration-endpoints.md)** - Bold-driven mode, Integration API
4. **[Magento-Driven Checkout](.agents/context/magento-driven-checkout.md)** - Magento-driven mode, hydration
5. **[RSA Endpoints](.agents/context/rsa-endpoints.md)** - Payment status updates (HMAC)

### Integration & Communication

6. **[Bold Checkout Integration](.agents/context/bold-checkout-integration.md)** - Outbound API calls to Bold
7. **[Express Pay](.agents/context/express-pay.md)** - Digital wallets, product/cart/mini-cart

### Frontend & Patterns

8. **[Frontend Components](.agents/context/frontend-components.md)** - JavaScript, Knockout.js, RequireJS
9. **[Magento Patterns](.agents/context/magento-patterns.md)** - Service contracts, plugins, observers, webapi.xml

### How to Use These Docs

**For AI Agents**:
- **Start with Architecture Overview** to understand the two modes
- **Reference specific docs** based on your task
- **Always confirm which mode** you're working with

**For Human Developers**:
- Use as onboarding material
- Reference when learning specific areas
- Understand architectural decisions

---

## Manual Testing with api-calls/

The `api-calls/` directory contains `.http` files for manual endpoint testing using REST Client (VS Code) or IntelliJ IDEA.

### Setup

1. Copy `api-calls/http-client.env.json.example` to `api-calls/http-client.env.json`
2. Fill in your values:
   - `baseUrl`: Your Magento store URL
   - `shopId`: Bold shop identifier (32-character hash)
   - `sharedSecret`: Integration shared secret
   - `productId`, `productSku`: Valid product from your catalog

### Usage

See `api-calls/README.md` for complete usage instructions.

**Integration Endpoint Flow**:
1. Validate integration → `01-validate.http`
2. Create quote → `02-quote-create.http`
3. Update customer info → `03-quote-update-customer.http`
4. Manage items → `04-quote-items-add.http`, `05-quote-items-update.http`, `06-quote-items-remove.http`
5. Set shipping → `07-quote-set-shipping.http`
6. Place order → `08-quote-place-order.http`

**When Adding New Endpoints**: Create corresponding `.http` files in `api-calls/integration/` to help developers test locally.

---

## Git Workflow

### Branch Naming

```
{TICKET}-{description}
```

Examples:
- `CHK-9267-add-agents-context`
- `CHK-1234-fix-hydration-bug`
- `PS-567-add-payment-method`

### Commit Messages

```
{TICKET}: {Short description}

- Optional bullet point details
- Keep it concise (TLDR style)
```

Examples:
```
CHK-9267: Add agents and dev context about the module

- Create AGENTS.md with two operational modes
- Add context files for architecture and patterns
```

```
CHK-1234: Fix hydration endpoint quote data mapping

- Correctly map line item discounts
- Handle tax-included pricing
```

### Development Process

1. Create branch from `main`:
   ```bash
   git checkout main
   git pull
   git checkout -b CHK-1234-feature-description
   ```

2. Make changes, commit as you work

3. Developer reviews changes and decides next steps (manual push, agent push, or PR creation)

4. Address review feedback

5. Merge when approved

---

## Configuration

### Admin Configuration Path

**Stores → Configuration → Sales → Checkout → Bold Checkout Payment Booster Extension**

### Key Configuration Options

- **Enabled**: Enable/disable module
- **API Token**: Bold API access token (from Bold Account Center)
- **Shop ID**: Bold shop identifier
- **Shared Secret**: For Integration API authentication
- **Fastlane**: Enable Fastlane by PayPal
- **Express Pay**: Enable digital wallets on product/cart/mini-cart
- **Payment Methods**: Configure bold, bold_wallet, bold_fastlane

### Configuration Storage

Configuration is stored in Magento's `core_config_data` table under the scope `checkout/bold_checkout_payment_booster/`.

See `Model/Config.php` for configuration retrieval methods.

---

## Bold Checkout Integration

### Outbound Communication (Module → Bold)

The module communicates with Bold Checkout service via the `BoldClient` HTTP client.

**Key Points**:
- Client: `Model/Http/BoldClient.php`
- Configuration: `Model/Config.php`
- Endpoints: Bold Checkout API, EPS API
- Authentication: API token, Shop ID

See [Bold Checkout Integration](.agents/context/bold-checkout-integration.md) for details.

### Inbound Communication (Bold → Module)

**Integration Endpoints** (Bold-driven):
- Authentication: `Authorization: Bearer {shared_key}`
- Purpose: Bold manages checkout, calls module for Magento operations
- See [Integration Endpoints](.agents/context/integration-endpoints.md)

**RSA Endpoint** (Magento-driven):
- RSA = Remote State Authority (limited implementation)
- Authentication: HMAC signature
- Purpose: Bold Checkout updates Magento order with payment status
- See [RSA Endpoints](.agents/context/rsa-endpoints.md)

---

## Payment Methods

The module provides three payment methods:

### 1. `bold`

Standard Bold payment method for credit/debit cards and alternative payment methods.

### 2. `bold_wallet`

Digital wallet payment method (Apple Pay, Google Pay, PayPal, Venmo).

### 3. `bold_fastlane`

Fastlane by PayPal - accelerated guest checkout for US merchants.

### Payment Flow

1. **Payment SDK** injected in checkout page
2. Customer enters payment (via SPI iframe for cards)
3. **EPS tokenizes** payment data
4. Magento places order (Observer triggered)
5. Module **hydrates Bold order** (Magento-driven mode)
6. Module calls Bold to **authorize** payment
7. Module calls Bold to **queue capture**
8. Bold processes capture
9. Bold calls **RSA endpoint** to update Magento
10. Module creates **invoice** or **credit memo**

See [Magento-Driven Checkout](.agents/context/magento-driven-checkout.md) for complete flow.

---

## Getting Help

### For AI Agents

1. Read the context documentation in `.agents/context/`
2. Search the codebase for similar implementations
3. **If needed for the task**, ask the task creator about:
   - Internal Bold Checkout architecture
   - EPS internal implementation details
   - Sidekick integration specifics
   - Production environment configurations

### For Developers

1. **Installation & Setup**: See README.md, Confluence, and [Bold Booster Documentation](https://developer.boldcommerce.com/guides/checkout/bold-boosters/bold-booster-for-paypal-overview)
2. **Code Questions**: Review context docs, check existing implementations
3. **Architecture Questions**: See `.agents/context/architecture-overview.md`

---

## Important Conventions

### Code Style

- **PHP 7.2+ compatibility**: Code must work on PHP 7.2 (no typed properties, no `mixed`, no union types)
- **PSR-12** coding standard
- **Magento 2** coding standards (see `.phpcs.xml`)
- **Type hints** on all parameters and return types (but NOT on properties)
- **Strict types**: `declare(strict_types=1);` at top of PHP files
- **PHPStan level 7**: Static analysis (see `phpstan.neon`)
- **DocBlocks**: Include `@param`, `@return`, `@throws` (required for properties)
- **Self-documenting code**: Minimal comments, clear variable names

See [Coding Standards](.agents/context/coding-standards.md) for details and PHP version compatibility rules.

### Magento Patterns

- **Service Contracts**: Define interfaces in `Api/`, implementations in `Model/`
- **Dependency Injection**: Use constructor injection, define in `etc/di.xml`
- **Plugins**: Use for extending core Magento functionality
- **Observers**: Use for reacting to Magento events
- **Web APIs**: Define in `etc/webapi.xml`, route to service contracts

See [Magento Patterns](.agents/context/magento-patterns.md) for details.

### Database

- **Schema**: Define in `etc/db_schema.xml`
- **No direct SQL**: Use Magento resource models
- **Indexes**: Add for performance on frequently queried columns

### Security

- **Input Validation**: Validate all inputs
- **Output Escaping**: Escape output in templates
- **Authentication**: 
  - Integration endpoints: Bearer token
  - RSA endpoint: HMAC signature validation
- **Authorization**: Check permissions in service classes
- **PII**: Handle customer data securely
