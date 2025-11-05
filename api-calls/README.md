# Integration API HTTP Client

This directory contains HTTP client files for testing the Bold Checkout Payment Booster Integration APIs.

## Setup

1. Copy the example environment file:
   ```bash
   cp http-client.env.json.example http-client.env.json
   ```

2. Edit `http-client.env.json` with your actual values:
   - `baseUrl`: Your Magento store URL (e.g., `https://magento.local`)
   - `shopId`: Your Bold shop identifier (32-character hash from Bold admin)
   - `sharedSecret`: Your integration shared secret (from Bold admin)
   - `productId`: A valid product ID from your Magento catalog
   - `productSku`: A valid product SKU from your Magento catalog

3. Open any `.http` file in VS Code (with REST Client extension) or IntelliJ IDEA

## Usage

### Prerequisites
- Install REST Client extension for VS Code, or use IntelliJ IDEA's built-in HTTP client
- Configure your environment in `http-client.env.json`

### Request Flow

The typical integration flow is:

1. **Validate** (`01-validate.http`) - Verify your integration setup
2. **Create Quote** (`02-quote-create.http`) - Create a new quote with initial items
   - This saves `quoteMaskId` to your environment automatically
3. **Update Customer Info** (`03-quote-update-customer.http`) - Add customer and address information
4. **Manage Items** (optional):
   - **Add Items** (`04-quote-items-add.http`) - Add new products to the quote
   - **Update Items** (`05-quote-items-update.http`) - Update quantities of existing items
   - **Remove Items** (`06-quote-items-remove.http`) - Remove products from the quote
5. **Set Shipping** (`07-quote-set-shipping.http`) - Select a shipping method
6. **Place Order** (`08-quote-place-order.http`) - Submit the order with payment data

### Variables

Variables are automatically managed between requests:
- `quoteMaskId` is saved when you create a quote and used in subsequent requests
- You can manually update variables in `http-client.env.json` as needed

### Multiple Requests

Each `.http` file contains multiple request examples separated by `###`. Click on the "Send Request" link above each request to execute it.

## Files

- `integration/01-validate.http` - Integration validation endpoint
- `integration/02-quote-create.http` - Create quote with items
- `integration/03-quote-update-customer.http` - Update customer info and addresses
- `integration/04-quote-items-add.http` - Add new items to quote
- `integration/05-quote-items-update.http` - Update item quantities
- `integration/06-quote-items-remove.http` - Remove items from quote
- `integration/07-quote-set-shipping.http` - Set shipping method
- `integration/08-quote-place-order.http` - Place order with payment

## Notes

- The `http-client.env.json` file is gitignored to protect your credentials
- Use `http-client.env.json.example` as a template
- The quote creation request automatically saves the `quoteMaskId` for subsequent requests
- Make sure to run requests in order for a complete flow, or adjust as needed for testing specific endpoints

