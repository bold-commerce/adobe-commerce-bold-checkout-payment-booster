# Agent Context Documentation

## Purpose

This directory contains architectural and pattern documentation for AI agents and developers working on the Bold Checkout Payment Booster module.

**Focus**: Patterns, concepts, and architecture - not implementation details.

---

## Critical Concept: Two Modes

Before reading anything else, understand this:

The module operates in **two completely separate modes**:

1. **Magento-Driven** - Native checkout, requires hydration, uses observers
2. **Bold-Driven** - Headless checkout, no hydration, uses Integration API

**Always know which mode you're working with.**

---

## Document Index

### Core Architecture

**[architecture-overview.md](architecture-overview.md)**
- Module architecture patterns
- Two-mode distinction
- Directory structure
- Code organization by mode

**[magento-patterns.md](magento-patterns.md)**
- Magento 2 patterns (Service Contracts, DI, Plugins, Observers)
- **webapi.xml** - Critical for Integration endpoints
- Configuration system
- Database schema

**[coding-standards.md](coding-standards.md)**
- PSR-12 and Magento 2 standards
- Type hints and strict types
- PHPStan level 7
- Code quality requirements

### Operational Modes

**[magento-driven-checkout.md](magento-driven-checkout.md)**
- Native Magento checkout with Bold payments
- Hydration process (critical!)
- Observer pattern
- Payment methods
- Simple Order lifecycle

**[integration-endpoints.md](integration-endpoints.md)**
- Bold-driven headless checkout
- Integration API pattern
- Bearer token authentication
- Quote management flow
- **NO hydration** in this mode

### Communication Patterns

**[bold-checkout-integration.md](bold-checkout-integration.md)**
- Outbound API calls (Module → Bold)
- BoldClient HTTP client
- Configuration management
- API token authentication

**[rsa-endpoints.md](rsa-endpoints.md)**
- Inbound payment updates (Bold → Module)
- HMAC authentication
- Financial status handling
- Invoice/refund/cancellation

### Frontend & Features

**[express-pay.md](express-pay.md)**
- Digital wallets (Apple Pay, Google Pay, etc.)
- Express Pay buttons
- Product/cart/mini-cart integration
- EPS SDK integration

**[frontend-components.md](frontend-components.md)**
- RequireJS modules
- Knockout.js components
- LESS styling
- Layout XML

---

## Quick Reference

### By Task Type

| Task | Read These |
|------|-----------|
| **Integration API work** | integration-endpoints.md, architecture-overview.md |
| **Magento checkout work** | magento-driven-checkout.md, architecture-overview.md |
| **Payment updates** | rsa-endpoints.md, magento-driven-checkout.md |
| **Bold API calls** | bold-checkout-integration.md |
| **Frontend work** | frontend-components.md, express-pay.md |
| **New feature** | architecture-overview.md, magento-patterns.md |

### By Mode

| Mode | Documents |
|------|-----------|
| **Magento-Driven** | magento-driven-checkout.md, rsa-endpoints.md |
| **Bold-Driven** | integration-endpoints.md |
| **Both** | architecture-overview.md, bold-checkout-integration.md |

---

## How to Use These Docs

### For AI Agents

1. **Start with**: architecture-overview.md (understand two modes)
2. **Identify mode**: Magento-driven or Bold-driven?
3. **Read mode docs**: integration-endpoints.md OR magento-driven-checkout.md
4. **Check patterns**: magento-patterns.md for Magento specifics
5. **Review standards**: coding-standards.md before writing code

### For Developers

1. **Onboarding**: Read architecture-overview.md first
2. **Deep dive**: Read docs related to your feature area
3. **Reference**: Use as quick reference during development
4. **Standards**: Consult coding-standards.md for code quality

### For Code Reviews

1. **Check mode**: Is correct mode being modified?
2. **Review patterns**: Are Magento patterns followed?
3. **Verify standards**: PSR-12, type hints, PHPStan passing?
4. **Cross-check**: Does change affect other mode? (shouldn't!)

---

## Document Philosophy

### What These Docs ARE

✅ **Pattern documentation** - How things work conceptually
✅ **Architecture guides** - Structure and organization
✅ **Concept explanations** - Why things are designed this way
✅ **File pointers** - Where to find implementations
✅ **Quick references** - Tables and summaries

### What These Docs are NOT

❌ **Implementation specs** - That's in engineering specs
❌ **Complete code examples** - Just patterns
❌ **API documentation** - That's in inline DocBlocks
❌ **User manuals** - That's in public docs
❌ **Installation guides** - That's in README.md

---

## Keeping Docs Updated

### When to Update

- New operational pattern added
- Architecture changes
- New Integration endpoint added
- Payment flow changes
- Frontend patterns change

### What to Update

- Keep focused on **patterns**, not implementations
- Update **file pointers** if files move/rename
- Maintain **two-mode distinction** clarity
- Keep **quick reference tables** current

### What NOT to Do

- Don't add every code snippet
- Don't document every method
- Don't replace engineering specs
- Don't duplicate README.md content

---

## Key Files Reference

For manual API testing: `../api-calls/`

For public documentation: `../README.md`

For git workflow: `../AGENTS.md`

For code: Browse the module directories per architecture-overview.md

---

## Summary

These documents explain **HOW the module is architectured** and **WHAT patterns to follow**, not every implementation detail.

**Most Critical Document**: architecture-overview.md (especially two-mode distinction)

**Most Referend For**: Bold-Driven work = integration-endpoints.md, Magento-Driven work = magento-driven-checkout.md

**Remember**: Always know which mode you're working with!
