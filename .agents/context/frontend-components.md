# Frontend Components

## Overview

The module's frontend uses Magento 2's standard stack: **RequireJS** for module loading, **Knockout.js** for data binding, **LESS** for styling, and **Layout XML** for structure.

---

## Technology Stack

### RequireJS

**Purpose**: JavaScript module loader

**Pattern**: AMD (Asynchronous Module Definition)

**Config**: `view/frontend/requirejs-config.js`

**Usage**:
```javascript
define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    return Component.extend({...});
});
```

### Knockout.js

**Purpose**: MVVM data binding

**Version**: Magento's bundled version

**Pattern**: Observable properties, computed properties, bindings

**Usage**: All UI components use Knockout for reactive updates

### LESS

**Purpose**: CSS preprocessing

**Pattern**: Variables, mixins, nesting

**Files**: `view/frontend/web/css/source/*.less`

**Compilation**: Magento compiles to CSS

### Layout XML

**Purpose**: Page structure, component placement

**Pattern**: Declarative XML configuration

**Files**: `view/frontend/layout/*.xml`

---

## JavaScript Architecture

### Module Pattern

**Location**: `view/frontend/web/js/`

**Pattern**: RequireJS modules

**Types**:
- UI Components (Knockout)
- Mixins
- Utilities
- Widgets

### UI Component Pattern

**Pattern**: Extend `uiComponent` or `Magento_Ui/js/form/element/abstract`

**Structure**:
```javascript
define(['uiComponent', 'ko'], function (Component, ko) {
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/component'
        },
        
        initialize: function () {
            this._super();
            this.observable('property');
            return this;
        },
        
        someMethod: function () {
            // Logic
        }
    });
});
```

**Key Directories**:
- `view/frontend/web/js/view/` - View components (UI)
- `view/frontend/web/js/model/` - Models (data)
- `view/frontend/web/js/action/` - Actions (operations)

---

## Component Examples

### Express Pay Component

**File**: `view/frontend/web/js/view/express-pay-pdp.js`

**Pattern**: Knockout component for Express Pay buttons

**Responsibilities**:
- Load EPS SDK
- Render wallet buttons
- Handle wallet events
- Create quote
- Place order

### Payment Renderer

**File**: `view/frontend/web/js/view/payment/method-renderer/bold-method.js`

**Pattern**: Payment method renderer for checkout

**Responsibilities**:
- Render payment form
- Validate payment data
- Submit payment
- Handle EPS SDK

---

## Template Pattern

### Knockout Templates

**Location**: `view/frontend/web/template/`

**Extension**: `.html`

**Pattern**: HTML with Knockout bindings

**Example**:
```html
<div class="bold-express-pay">
    <button data-bind="click: placeOrder, visible: isAvailable">
        <span data-bind="text: buttonLabel"></span>
    </button>
</div>
```

**Bindings**:
- `text:` - Text content
- `visible:` - Show/hide
- `click:` - Event handler
- `if:` - Conditional rendering
- `foreach:` - Loop

### PHTML Templates

**Location**: `view/frontend/templates/`

**Extension**: `.phtml`

**Pattern**: PHP + HTML (server-side)

**Usage**: Block templates (not Knockout)

**Example Use Cases**:
- Admin configuration output
- Static content injection
- Server-side rendering

---

## Layout XML Pattern

### Structure

**Location**: `view/frontend/layout/`

**Pattern**: XML configuration

**Example**:
```xml
<page>
    <body>
        <referenceContainer name="content">
            <block class="Bold\...\Block\ExpressPay" 
                   name="bold.express.pay"
                   template="Bold_CheckoutPaymentBooster::express-pay.phtml"/>
        </referenceContainer>
    </body>
</page>
```

### Common Operations

**Add Block**:
```xml
<block class="..." name="..." template="..."/>
```

**Add UI Component**:
```xml
<block class="Magento\Framework\View\Element\Template">
    <arguments>
        <argument name="jsLayout" xsi:type="array">
            <item name="components" xsi:type="array">
                <item name="bold-component" xsi:type="array">
                    <item name="component" xsi:type="string">Bold_CheckoutPaymentBooster/js/view/component</item>
                </item>
            </item>
        </argument>
    </arguments>
</block>
```

**Remove Block**:
```xml
<referenceBlock name="block.name" remove="true"/>
```

---

## Styling Pattern

### LESS Organization

**Location**: `view/frontend/web/css/source/`

**Structure**:
```
_module.less          # Main imports
_variables.less       # Variables
_mixins.less          # Mixins
components/
  _express-pay.less   # Component styles
  _payment.less       # Payment styles
```

### Pattern: BEM-like Classes

```less
.bold-express-pay {
    &__button {
        // Button styles
    }
    
    &__label {
        // Label styles
    }
    
    &--loading {
        // Loading state
    }
}
```

### Responsive Pattern

**Breakpoints**: Use Magento's variables

```less
.media-width(@extremum, @break) when (@extremum = 'min') and (@break = @screen__m) {
    .bold-express-pay {
        // Tablet+ styles
    }
}
```

---

## RequireJS Configuration

### Config File

**Location**: `view/frontend/requirejs-config.js`

**Purpose**: Module mappings, shims, paths

**Pattern**:
```javascript
var config = {
    map: {
        '*': {
            'boldExpressPay': 'Bold_CheckoutPaymentBooster/js/view/express-pay-pdp'
        }
    },
    paths: {
        'boldEps': 'https://bold.cdn.com/eps'
    },
    shim: {
        'boldEps': {
            exports: 'BoldEPS'
        }
    }
};
```

---

## Checkout Integration Pattern

### Payment Method Renderer

**Pattern**: Register custom payment renderer

**File**: `view/frontend/layout/checkout_index_index.xml`

**Structure**:
```xml
<item name="children" xsi:type="array">
    <item name="bold-payments" xsi:type="array">
        <item name="component" xsi:type="string">Bold_CheckoutPaymentBooster/js/view/payment/bold</item>
        <item name="methods" xsi:type="array">
            <item name="bold" xsi:type="array">
                <item name="component" xsi:type="string">Bold_CheckoutPaymentBooster/js/view/payment/method-renderer/bold-method</item>
            </item>
        </item>
    </item>
</item>
```

### Checkout Step Modification

**Pattern**: Add steps or modify existing

**Use Cases**:
- Additional validation
- Custom checkout steps
- EPS SDK injection

---

## Key Files Reference

### JavaScript
- `view/frontend/web/js/view/` - UI components
- `view/frontend/web/js/view/payment/` - Payment renderers
- `view/frontend/web/js/model/` - Data models
- `view/frontend/requirejs-config.js` - RequireJS config

### Templates
- `view/frontend/web/template/` - Knockout templates
- `view/frontend/templates/` - PHTML templates

### Styles
- `view/frontend/web/css/source/` - LESS source
- `view/frontend/web/css/` - Compiled CSS (don't edit)

### Layout
- `view/frontend/layout/` - Layout XML
- `view/frontend/layout/checkout_index_index.xml` - Checkout layout
- `view/frontend/layout/catalog_product_view.xml` - Product page

---

## Common Patterns

### Observable Property

```javascript
this.property = ko.observable('initial value');
this.property('new value'); // Set
var value = this.property(); // Get
```

### Computed Property

```javascript
this.displayText = ko.computed(function () {
    return this.firstName() + ' ' + this.lastName();
}, this);
```

### Event Binding

```html
<button data-bind="click: handleClick">Click Me</button>
```

```javascript
handleClick: function () {
    // Handle click
}
```

---

## Best Practices

### JavaScript

✅ Use RequireJS modules
✅ Extend UI components
✅ Use Knockout observables
✅ Minimize global scope
✅ Handle errors gracefully

❌ Don't use jQuery excessively
❌ Don't modify Magento core JS
❌ Don't use global variables

### Templates

✅ Keep logic in JS, not template
✅ Use semantic HTML
✅ Follow accessibility standards
✅ Use Knockout bindings

❌ Don't put business logic in templates
❌ Don't use inline styles
❌ Don't use inline JavaScript

### Styles

✅ Use LESS variables
✅ Follow BEM-like naming
✅ Mobile-first approach
✅ Use Magento mixins

❌ Don't use !important
❌ Don't use IDs for styling
❌ Don't hardcode colors/sizes

---

## Testing Frontend

### Manual Testing

**Browser DevTools**:
- Check console for errors
- Inspect network requests
- Debug Knockout bindings

**Knockout Context Debugger**:
```javascript
// In browser console
ko.dataFor($0) // Get component
```

---

## Summary

### Key Technologies

1. **RequireJS** - Module loading
2. **Knockout.js** - Data binding
3. **LESS** - CSS preprocessing
4. **Layout XML** - Structure

### Directory Structure

```
view/frontend/
├── web/
│   ├── js/           # JavaScript
│   ├── template/     # Knockout templates
│   └── css/          # LESS/CSS
├── layout/           # Layout XML
└── templates/        # PHTML templates
```

### Key Patterns

- **UI Components** - Knockout-based
- **Templates** - HTML + data bindings
- **Layout XML** - Declarative structure
- **LESS** - Structured stylesheets
- **RequireJS** - Module system

### Remember

- **Knockout** for reactive UI
- **Layout XML** for structure
- **LESS** for styling
- **RequireJS** for modules
- Follow **Magento 2 frontend standards**
