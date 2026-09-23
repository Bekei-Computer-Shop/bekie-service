# Serial Numbers Feature Documentation

Professional e-commerce serial number tracking system for hardware products with warranty management.

## Overview

The serial number feature provides comprehensive tracking of individual product units throughout their lifecycle, from receiving inventory through warranty management and support.

## Core Features

### 1. Serial Number Inventory Management

- **Receive serials**: Bulk import serial numbers for tracked products
- **Status tracking**: Follow serials through states (available, reserved, sold, returned, damaged, etc.)
- **Warehouse assignment**: Track physical location of serialized stock
- **Reference tracking**: Link serials to purchase orders and receiving documents

### 2. Warranty Management

- **Automatic warranty activation**: Set at point of sale (1-year default)
- **Custom warranty periods**: Assign variable warranty durations
- **Warranty validation**: Check warranty status for customer support
- **Expiration tracking**: Monitor warranty expiration dates
- **Warranty analytics**: View warranty statistics and expiring items

### 3. Lifecycle Status Flow

```
available → reserved → sold → returned/in_service
                    ↓
                 cancelled/damaged/lost

in_service → repaired → sold/in_service
          → damaged/replaced

returned → available/replaced/damaged
```

### 4. Audit Trail

Complete history tracking for all serial status changes:
- Event type (received, reserved, sold, damaged, etc.)
- Previous and new status
- Actor (admin user who made change)
- Associated order
- Notes and references
- Timestamp

## API Endpoints

### Admin API (requires authentication and `product-serials` permission)

#### Listing & Search
```
GET /admin/product-serials
  Query: q, status, product_id, product_variant_id, warehouse, 
         customer_id, order_id, received_from, received_to
  Returns: paginated items with summary data
```

#### Summary & Stats
```
GET /admin/product-serials/summary
  Returns: by_status, total, available, sold, reserved, warranty_expiring_soon

GET /admin/product-serials/warranty/stats
  Returns: warranty statistics across inventory
```

#### Lookup & Details
```
GET /admin/product-serials/lookup/{serialNumber}
  Returns: single serial details

GET /admin/product-serials/{id}
  Returns: full serial with history

GET /admin/product-serials/{id}/history
  Returns: audit trail
```

#### Warranty Operations
```
GET /admin/product-serials/warranty/validate/{serialNumber}
  Returns: warranty validity status

POST /admin/product-serials/{id}/warranty
  Body: { days: number }
  Sets warranty period from now
```

#### Receive Stock
```
POST /admin/product-serials
  Body: {
    product_id: uuid,
    product_variant_id: number,
    serial_numbers: [string],
    receiving_reference: string,
    warehouse: string,
    notes: string
  }
  Returns: created serials
```

#### Update Serial
```
PATCH /admin/product-serials/{id}
  Body: {
    status: string,
    warehouse: string,
    notes: string
  }
  Supports state transitions via service validation
```

#### Bulk Operations
```
POST /admin/product-serials/bulk-update-status
  Body: {
    serial_ids: [number],
    status: string
  }
  Returns: count of updated items

GET /admin/product-serials/export
  Query: same as index
  Returns: all matching serials as JSON for download
```

### Client API (customer-facing)

#### My Products
```
GET /my-products
  Query: status, warranty, per_page
  Returns: customer's purchased serials

GET /my-products/summary
  Returns: by_status, by_warranty, total, active_warranty

GET /my-products/{id}
  Returns: serial details with order and history
```

#### Warranty Lookup
```
GET /warranty/summary
  Returns: warranty statistics for customer

GET /warranty/{serialNumber}
  Returns: warranty validity and days remaining
```

## Data Models

### ProductSerial
- `id` (int): Primary key
- `product_id` (uuid): Associated product
- `product_variant_id` (int): Optional variant
- `serial_number` (string): Unique identifier
- `status` (enum): Current state
- `warehouse` (string): Physical location
- `receiving_reference` (string): PO/receiving doc
- `order_id` (int): Associated sales order
- `customer_id` (int): Current owner
- `purchased_at` (timestamp): Sale date
- `warranty_start_at` (timestamp): Warranty begins
- `warranty_end_at` (timestamp): Warranty expires
- `notes` (text): Internal notes
- `created_at`, `updated_at` (timestamps)

### ProductSerialHistory
- `id` (int): Primary key
- `product_serial_id` (int): Parent serial
- `event` (string): Event type (received, sold, damaged, etc.)
- `previous_status`, `new_status` (string): State change
- `actor_id` (int): User who made change
- `order_id` (int): Associated order
- `reference` (string): External reference
- `note` (text): Event note
- `created_at` (timestamp)

## Status Enum

```php
AVAILABLE   = 'available'   // In stock, available for sale
RESERVED    = 'reserved'    // Reserved for order pending payment
SOLD        = 'sold'        // Sold and active
RETURNED    = 'returned'    // Returned by customer
IN_SERVICE  = 'in_service'  // In warranty/support service
REPAIRED    = 'repaired'    // Repair completed, ready to use
REPLACED    = 'replaced'    // Replaced as warranty claim
DAMAGED     = 'damaged'     // Damaged, not usable
LOST        = 'lost'        // Lost in transit
CANCELLED   = 'cancelled'   // Cancelled order
```

## Usage Examples

### Admin: Receive Stock
```javascript
await receiveSerials({
  product_id: 'abc-123-uuid',
  product_variant_id: 42,
  serial_numbers: ['SN001', 'SN002', 'SN003'],
  receiving_reference: 'PO-2024-001',
  warehouse: 'Main Store',
  notes: 'Dell Laptops batch 001'
}, token)
```

### Admin: Update Serial Status
```javascript
await updateSerial(serialId, {
  status: 'damaged',
  notes: 'Device failed drop test'
}, token)
```

### Admin: Bulk Status Update
```javascript
await bulkUpdateStatus([id1, id2, id3], 'damaged', token)
```

### Admin: Set Warranty Period
```javascript
await setWarranty(serialId, 365, token)  // 1 year warranty
```

### Customer: Check Warranty
```javascript
const result = await validateMyWarranty('SN12345', token)
// Returns: { valid: true, reason: 'active', days_remaining: 120, ... }
```

### Customer: View Products
```javascript
const products = await fetchMyProducts(token)
// Optional filtering: { status: 'sold', warranty: 'active', per_page: 20 }
```

## Warranty Status Validation

The warranty validation endpoint returns one of these status combinations:

```javascript
{
  valid: true,
  reason: 'active',
  message: 'Warranty active for 120 more days.',
  days_remaining: 120,
  expires_at: '2025-01-23T00:00:00Z'
}

{
  valid: false,
  reason: 'expired',
  message: 'Warranty expired on 2024-09-23.',
  expired_at: '2024-09-23T00:00:00Z'
}

{
  valid: false,
  reason: 'no_warranty',
  message: 'This product does not have warranty coverage.'
}
```

## Response Format

All endpoints return structured JSON responses:

```javascript
{
  success: true,
  data: { /* response data */ },
  message: "Optional message"
}

{
  success: false,
  error: "Error message",
  code: 422
}
```

## Permissions

Serial number management requires one of:
- `product-serials.view` - Read access
- `product-serials.manage` - Read + Write access

Set via role-based permission system.

## Performance Considerations

### Indexes
- `(product_id, status)` - Status filtering
- `(product_variant_id, status)` - Variant status queries
- `(customer_id, status)` - Customer inventory
- `(warranty_end_at)` - Warranty expiration queries
- `(customer_id, status, warranty_end_at)` - Customer warranty queries
- `(status, warranty_end_at)` - Warranty expiring soon
- `(purchased_at)` - Historical lookups
- `(product_serial_id, created_at)` - History timeline

### Query Optimization
- Use `per_page` parameter to limit result sets
- Filter by status when querying large inventories
- Use date ranges for historical queries
- Cache warranty summary statistics

## Integration Points

### Order System
- Serials reserved at checkout (for tracked products)
- Serials marked as SOLD when order is fulfilled
- Serials linked to order record for reference
- Warranty initialized from sale date

### Customer Portal
- Customers see their purchased serials
- Warranty status visible in account
- Support can look up warranty validity
- Customer can register serials

### Support System
- Link support tickets to serial numbers
- Track service history in audit trail
- Manage warranty status during service
- Generate warranty proof of purchase

## Future Enhancements

- [ ] Serial number format validation (regex patterns per product)
- [ ] QR code generation and printing
- [ ] Batch import from CSV/Excel
- [ ] Warranty claim workflow
- [ ] Service history notes
- [ ] Replacement serial linking
- [ ] Warranty transfer on resale
- [ ] Customer self-registration portal
- [ ] Scheduled warranty expiration notifications
- [ ] Integration with shipping carriers
