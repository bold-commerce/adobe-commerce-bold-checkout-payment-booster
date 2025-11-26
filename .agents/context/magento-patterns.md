# Magento 2 Patterns

## Overview

This document explains key Magento 2 architectural patterns used throughout the module. Understanding these patterns is essential for working with the codebase.

---

## Service Contract Pattern

### What Are Service Contracts?

**Pattern**: Interface-based API contracts

**Purpose**: Stable, versioned APIs that don't change between minor versions

**Structure**:
```
Api/                    # Interface definitions
Model/                  # Implementations
etc/di.xml              # Interface → Implementation mapping
```

### Pattern Usage

**Define Interface** (in `Api/`):
```php
namespace Bold\CheckoutPaymentBooster\Api\Integration;

interface CartManagementInterface
{
    public function createCart(int $websiteId): int;
}
```

**Implement** (in `Model/`):
```php
namespace Bold\CheckoutPaymentBooster\Model\Integration;

class CartManagementApi implements CartManagementInterface
{
    public function createCart(int $websiteId): int
    {
        // Implementation
    }
}
```

**Map in DI** (`etc/di.xml`):
```xml
<preference for="Bold\...\Api\Integration\CartManagementInterface" 
            type="Bold\...\Model\Integration\CartManagementApi"/>
```

### Benefits

- **Stable APIs**: Interfaces don't change
- **Testability**: Easy to mock
- **Flexibility**: Swap implementations via DI
- **Web API**: Can expose via REST/SOAP

---

## Dependency Injection Pattern

### DI Configuration

**File**: `etc/di.xml`

**Purpose**: Configure object instantiation and dependencies

### Preference Pattern

**Usage**: Map interface to implementation

```xml
<preference for="InterfaceName" type="ImplementationClass"/>
```

**Example**:
```xml
<preference for="Bold\...\Api\ConfigInterface" 
            type="Bold\...\Model\Config"/>
```

### Type Configuration

**Usage**: Configure constructor arguments

```xml
<type name="ClassName">
    <arguments>
        <argument name="argumentName" xsi:type="string">value</argument>
    </arguments>
</type>
```

**Argument Types**:
- `string` - String value
- `boolean` - true/false
- `number` - Numeric value
- `array` - Array of values
- `object` - Object instance

### Virtual Types

**Usage**: Create type variants without new classes

```xml
<virtualType name="VirtualTypeName" type="ActualClass">
    <arguments>
        <argument name="arg" xsi:type="string">value</argument>
    </arguments>
</virtualType>
```

---

## Web API Pattern (Critical for Integration)

### webapi.xml Configuration

**File**: `etc/webapi.xml`

**Purpose**: Expose service contracts as REST/SOAP APIs

**Critical**: This is how Integration endpoints are defined

### Route Definition Pattern

```xml
<route url="/V1/shops/:shopId/integration/carts" method="POST">
    <service class="Bold\...\Api\Integration\CartManagementInterface" 
             method="createCart"/>
    <resources>
        <resource ref="anonymous"/>
    </resources>
</route>
```

### Key Components

**URL Pattern**:
- Starts with `/V1/`
- Parameters: `:paramName`
- RESTful structure

**Service Class**:
- Must be an interface (service contract)
- Method must exist on interface

**Resources**:
- `anonymous` - No authentication required
- `Magento_Backend::admin` - Admin only
- `self` - Current user's resources

### HTTP Methods

- `GET` - Read operations
- `POST` - Create operations
- `PUT` - Update operations
- `DELETE` - Delete operations
- `PATCH` - Partial update

### Parameter Mapping

**URL parameters** (`:paramName`) automatically injected:
```xml
<route url="/V1/carts/:cartId" method="GET">
    <!-- cartId from URL injected as parameter -->
</route>
```

**Body parameters**: JSON body mapped to method parameters

### Example: Integration Endpoint

```xml
<route url="/V1/shops/:shopId/integration/carts/:cartId" method="GET">
    <service class="Bold\...\Api\Integration\CartManagementInterface" 
             method="getCart"/>
    <resources>
        <resource ref="anonymous"/>
    </resources>
</route>
```

Maps to:
```php
public function getCart(string $shopId, int $cartId): CartInterface;
```

---

## Plugin Pattern (Interceptor)

### What Are Plugins?

**Pattern**: Intercept method calls (before, after, around)

**Purpose**: Modify behavior without inheritance

**Configuration**: `etc/di.xml`

### Plugin Types

**Before Plugin**:
```php
public function beforeMethodName($subject, $arg1, $arg2)
{
    // Modify arguments
    return [$newArg1, $newArg2];
}
```

**After Plugin**:
```php
public function afterMethodName($subject, $result)
{
    // Modify return value
    return $modifiedResult;
}
```

**Around Plugin**:
```php
public function aroundMethodName($subject, callable $proceed, $arg1)
{
    // Before original method
    $result = $proceed($arg1);
    // After original method
    return $result;
}
```

### Plugin Configuration

```xml
<type name="TargetClass">
    <plugin name="plugin_name" 
            type="Plugin\Class" 
            sortOrder="10" 
            disabled="false"/>
</type>
```

### Plugin Usage in Module

**Use Cases**:
- Modify quote calculations
- Add data to order
- Intercept payment methods
- Extend third-party functionality

---

## Observer Pattern

### Event-Driven Architecture

**Purpose**: React to Magento events

**Configuration**: `etc/events.xml`

**Implementation**: `Observer/` directory

### Observer Configuration

```xml
<config>
    <event name="event_name">
        <observer name="observer_name" 
                  instance="Observer\Class"/>
    </event>
</config>
```

### Observer Implementation

```php
namespace Bold\...\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class MyObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $data = $observer->getData('data_key');
        // React to event
    }
}
```

### Common Events

- `sales_order_place_after` - After order placement
- `checkout_submit_all_after` - After checkout submission
- `sales_quote_save_after` - After quote save
- `payment_method_assign_data` - Payment data assignment

### Observer Usage in Module

**Pattern**: Used in both modes with conditional logic

**Magento-Driven Behavior**:
- Trigger order hydration
- Update order status
- Handle payment events

**Bold-Driven Behavior**:
- Check integration flag on quote/order
- Skip hydration (already current)
- Allow standard Magento flow

**Location**: `Observer/` directory

**Critical**: Observers must check for integration quotes to avoid processing Bold-driven orders incorrectly

---

## Extension Attributes Pattern

### What Are Extension Attributes?

**Pattern**: Add data to entities without modifying them

**Purpose**: Extend core/third-party models

**Configuration**: `etc/extension_attributes.xml`

### Configuration

```xml
<config>
    <extension_attributes for="Magento\Quote\Api\Data\CartInterface">
        <attribute code="bold_data" type="string"/>
    </extension_attributes>
</config>
```

### Usage

```php
$cart->getExtensionAttributes()->setBoldData($data);
$data = $cart->getExtensionAttributes()->getBoldData();
```

---

## Cron Jobs Pattern

### Cron Configuration

**File**: `etc/crontab.xml`

**Pattern**: Schedule-based background tasks

**Module Usage**:
- Digital wallet quote cleanup (deactivates expired quotes)

**Configuration Pattern**:
```xml
<job name="job_name" 
     instance="Bold\...\Cron\ClassName" 
     method="execute">
    <config_path>path/to/schedule/config</config_path>
</job>
```

**Schedule**: Configured via admin system config or cron expression

### Implementation Pattern

**Location**: `Cron/` directory

**Pattern**: Class with `execute()` method

**Key File**: `Cron/DigitalWallets/DeactivateQuotes.php` - Cleans up expired digital wallet quotes

---

## Configuration System Pattern

### system.xml (Admin Config)

**File**: `etc/adminhtml/system.xml`

**Purpose**: Admin configuration UI

**Pattern**:
```xml
<config>
    <system>
        <section id="checkout">
            <group id="bold_checkout_payment_booster">
                <field id="api_url" type="text">
                    <label>API URL</label>
                    <comment>Bold Checkout API URL</comment>
                </field>
            </group>
        </section>
    </system>
</config>
```

**Field Types**:
- `text` - Text input
- `select` - Dropdown
- `multiselect` - Multiple selection
- `textarea` - Multi-line text
- `obscure` - Encrypted field

### config.xml (Default Values)

**File**: `etc/config.xml`

**Purpose**: Default configuration values

**Pattern**:
```xml
<config>
    <default>
        <checkout>
            <bold_checkout_payment_booster>
                <api_url>https://api.boldcommerce.com</api_url>
            </bold_checkout_payment_booster>
        </checkout>
    </default>
</config>
```

### Accessing Configuration

**Pattern**: Use helper/config class

```php
$value = $this->scopeConfig->getValue(
    'checkout/bold_checkout_payment_booster/api_url',
    \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
    $websiteId
);
```

**Module Pattern**: Centralized in `Model/Config.php`

---

## Database Schema Pattern

### Declarative Schema

**File**: `etc/db_schema.xml`

**Purpose**: Define database tables/columns

**Pattern**:
```xml
<schema>
    <table name="bold_order_mapping">
        <column name="entity_id" type="int" identity="true"/>
        <column name="quote_id" type="int" nullable="false"/>
        <column name="public_order_id" type="varchar" length="255"/>
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="entity_id"/>
        </constraint>
        <constraint xsi:type="unique" referenceId="BOLD_QUOTE_ID">
            <column name="quote_id"/>
        </constraint>
    </table>
</schema>
```

### Schema Updates

**No install scripts**: Magento compares schema to database

**Command**: `bin/magento setup:upgrade`

**Whitelist**: `etc/db_schema_whitelist.json` (auto-generated)

---

## Repository Pattern

### What Are Repositories?

**Pattern**: Data access layer

**Purpose**: CRUD operations on entities

**Usage**: Interact with database through repositories

### Repository Pattern

```php
interface RepositoryInterface
{
    public function save(EntityInterface $entity): EntityInterface;
    public function getById(int $id): EntityInterface;
    public function delete(EntityInterface $entity): bool;
    public function getList(SearchCriteriaInterface $criteria): SearchResultsInterface;
}
```

### Usage in Module

**Magento Repositories**:
- `QuoteRepository` - Quote operations
- `OrderRepository` - Order operations
- `ProductRepository` - Product operations

**Custom Repositories**: If module has custom tables

---

## Payment Method Pattern

### Payment Method Class

**Base**: `Magento\Payment\Model\Method\AbstractMethod`

**Location**: `Model/Payment/`

**Configuration**: `etc/payment.xml`

### Key Methods

```php
isAvailable(CartInterface $quote): bool  // When to show
assignData(DataObject $data): self       // Store payment data
authorize(...): self                     // Authorization
capture(...): self                       // Capture
refund(...): self                        // Refund
```

### Payment Configuration

```xml
<payment>
    <groups>
        <group id="bold">
            <label>Bold Payment</label>
        </group>
    </groups>
</payment>
```

---

## Key Pattern Summary

| Pattern | File | Purpose |
|---------|------|---------|
| **Service Contracts** | `Api/`, `etc/di.xml` | Stable APIs |
| **Web API** | `etc/webapi.xml` | REST/SOAP endpoints |
| **DI** | `etc/di.xml` | Object configuration |
| **Plugins** | `etc/di.xml`, `Plugin/` | Intercept methods |
| **Observers** | `etc/events.xml`, `Observer/` | React to events |
| **Configuration** | `etc/config.xml`, `system.xml` | Settings |
| **Database** | `etc/db_schema.xml` | Schema definition |

---

## When Working with Patterns

### Always Consider

1. **Which pattern applies?**
2. **Where should this be configured?**
3. **Is there a Magento standard for this?**
4. **Do I need DI configuration?**
5. **Should this be a service contract?**

### Critical Files

- `etc/di.xml` - DI, plugins, preferences
- `etc/webapi.xml` - **Critical for Integration APIs**
- `etc/events.xml` - Observers
- `etc/config.xml` - Default config
- `etc/db_schema.xml` - Database

---

## Summary

### Core Patterns

1. **Service Contracts** - Interface-based APIs
2. **Web API (webapi.xml)** - **Critical for Integration endpoints**
3. **Dependency Injection** - Object configuration
4. **Plugins** - Method interception
5. **Observers** - Event reactions
6. **Configuration** - System settings
7. **Database Schema** - Declarative schema

### Remember

- **Service contracts** for all public APIs
- **webapi.xml** exposes service contracts as REST APIs
- **Dependency injection** for all dependencies
- **Plugins** for method interception
- **Observers** for event reactions (both modes with conditional logic)
- **Configuration** centralized in `Model/Config.php`
- Follow **Magento 2 best practices** always
