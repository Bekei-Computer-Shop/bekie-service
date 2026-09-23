# Serial Numbers - Implementation Examples

## Receiving Inventory

### Example 1: Receive serialized laptops
```php
// Request
POST /admin/product-serials
{
  "product_id": "550e8400-e29b-41d4-a716-446655440000",
  "serial_numbers": [
    "DELL-LT-001",
    "DELL-LT-002",
    "DELL-LT-003",
    "DELL-LT-004",
    "DELL-LT-005"
  ],
  "receiving_reference": "PO-2024-08-15-001",
  "warehouse": "Main Warehouse A",
  "notes": "Dell XPS 13 - Q3 2024 shipment"
}

// Response
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "serial_number": "DELL-LT-001",
        "status": "available",
        "product": {
          "id": "550e8400-e29b-41d4-a716-446655440000",
          "name": "Dell XPS 13",
          "sku": "DELL-XPS-13"
        },
        "warehouse": "Main Warehouse A",
        "created_at": "2024-09-23T10:30:00Z"
      }
      // ... more items
    ]
  },
  "message": "Serial numbers received."
}
```

## Order Processing with Serials

### Example 2: Customer buys serialized product
```php
// 1. Product must have is_serialized = true
$product = Product::find('...');
// is_serialized: true

// 2. When adding to cart
POST /cart/items
{
  "product_id": "...",
  "quantity": 2  // Requires 2 serial numbers
}

// 3. During checkout, customer selects serials
POST /cart/checkout
{
  "serial_numbers": ["DELL-LT-001", "DELL-LT-002"],  // One per quantity
  ...
}

// 4. Backend reserves serials
$serialService->reserveForOrder(
  ["DELL-LT-001", "DELL-LT-002"],  // Serial numbers
  $customerId,
  $orderId
);

// 5. When order is confirmed/paid
$serialService->finalizeOrder($orderId);  // Moves from RESERVED to SOLD
```

## Warranty Management

### Example 3: Check warranty validity
```php
// Admin checking warranty
GET /admin/product-serials/warranty/validate/DELL-LT-001

{
  "success": true,
  "data": {
    "valid": true,
    "reason": "active",
    "message": "Warranty active for 180 more days.",
    "days_remaining": 180,
    "expires_at": "2025-03-23T00:00:00Z"
  }
}

// Customer checking their warranty
GET /warranty/DELL-LT-001

{
  "success": true,
  "data": {
    "valid": true,
    "reason": "active",
    "message": "Warranty active for 180 more days.",
    "days_remaining": 180,
    "expires_at": "2025-03-23T00:00:00Z"
  }
}
```

### Example 4: Set custom warranty period
```php
// Admin extends warranty to 2 years
POST /admin/product-serials/42/warranty
{
  "days": 730
}

{
  "success": true,
  "data": {
    "id": 42,
    "serial_number": "DELL-LT-001",
    "warranty": {
      "status": "active",
      "start_at": "2024-09-23T00:00:00Z",
      "end_at": "2026-09-23T00:00:00Z"
    }
  },
  "message": "Warranty period set."
}
```

## Status Transitions

### Example 5: Device in warranty service
```php
// Customer reports issue
// Admin creates service ticket and updates serial status

// Mark as in service
PATCH /admin/product-serials/42
{
  "status": "in_service",
  "notes": "Sent to service center - screen malfunction"
}

// Later: Mark as repaired
PATCH /admin/product-serials/42
{
  "status": "repaired",
  "notes": "Screen replacement completed, tested working"
}

// Device can go back to sold or in_service again
// If customer returns, mark as sold (if they keep it)
// or returned (if replacing)
```

### Example 6: Warranty claim - device replacement
```php
// Customer has issue, device replaced under warranty

PATCH /admin/product-serials/42
{
  "status": "replaced",
  "notes": "Warranty claim approved - unit replaced with SN: DELL-LT-100"
}

// New serial assigned same customer and warranty
// Old serial now shows as "replaced"
```

## Customer Portal

### Example 7: Customer views their products
```php
// Get summary
GET /my-products/summary

{
  "success": true,
  "data": {
    "by_status": {
      "sold": 3,
      "returned": 1
    },
    "by_warranty": {
      "active": 2,
      "expired": 1,
      "not_covered": 1
    },
    "total": 4,
    "active_warranty": 2
  }
}

// List products with filters
GET /my-products?warranty=active&per_page=20

{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "serial_number": "DELL-LT-001",
        "status": "sold",
        "product": {
          "id": "...",
          "name": "Dell XPS 13",
          "sku": "DELL-XPS-13",
          "thumbnail": "..."
        },
        "order": {
          "id": 123,
          "order_number": "ORD-2024-001",
          "created_at": "2024-06-15T..."
        },
        "purchased_at": "2024-06-15T10:30:00Z",
        "warranty": {
          "status": "active",
          "start_at": "2024-06-15T00:00:00Z",
          "end_at": "2025-06-15T00:00:00Z",
          "days_remaining": 265,
          "is_active": true
        }
      }
    ],
    "pagination": {
      "total": 3,
      "per_page": 20,
      "current_page": 1,
      "last_page": 1
    }
  }
}

// View single product details
GET /my-products/1

{
  "success": true,
  "data": {
    "id": 1,
    "serial_number": "DELL-LT-001",
    "status": "sold",
    "product": { ... },
    "order": { ... },
    "warranty": { ... },
    "history": [
      {
        "event": "sold",
        "previous_status": "reserved",
        "new_status": "sold",
        "note": null,
        "created_at": "2024-06-15T10:35:00Z"
      },
      {
        "event": "received",
        "previous_status": null,
        "new_status": "available",
        "reference": "PO-2024-06-001",
        "created_at": "2024-06-01T08:00:00Z"
      }
    ]
  }
}
```

## Admin Operations

### Example 8: Monitor warranty expirations
```php
// Get warranty stats
GET /admin/product-serials/warranty/stats

{
  "success": true,
  "data": {
    "total_sold": 250,
    "active_warranty": 180,
    "expired_warranty": 45,
    "no_warranty": 25,
    "expiring_soon": 12
  }
}

// View summary
GET /admin/product-serials/summary

{
  "success": true,
  "data": {
    "by_status": {
      "available": 50,
      "reserved": 5,
      "sold": 250,
      "damaged": 3,
      "returned": 8
    },
    "total": 316,
    "available": 50,
    "sold": 250,
    "reserved": 5,
    "warranty_expiring_soon": 12
  }
}
```

### Example 9: Bulk operations
```php
// Mark multiple units as damaged (batch inspection)
POST /admin/product-serials/bulk-update-status
{
  "serial_ids": [42, 43, 44],
  "status": "damaged"
}

{
  "success": true,
  "data": {
    "updated_count": 3
  },
  "message": "Updated 3 serial(s)."
}
```

### Example 10: Export for reporting
```php
// Export all sold units with active warranty
GET /admin/product-serials/export?status=sold&warranty=active

{
  "success": true,
  "data": {
    "total": 180,
    "data": [
      {
        "serial_number": "DELL-LT-001",
        "product": { "name": "Dell XPS 13", "sku": "..." },
        "customer": { "name": "John Doe", "email": "..." },
        "status": "sold",
        "warranty": {
          "status": "active",
          "days_remaining": 265,
          "expires_at": "2025-06-15T..."
        }
      }
      // ... 179 more
    ]
  }
}
```

## Common Workflows

### Workflow 1: Receiving → Sale → Service → Warranty Claim

```
1. Receive stock
   POST /admin/product-serials
   Status: AVAILABLE

2. Customer purchases
   PUT /cart → POST /cart/checkout → POST /orders
   Status: RESERVED (at checkout) → SOLD (at fulfillment)

3. Customer reports issue
   PATCH /admin/product-serials/X { status: "in_service" }
   Status: IN_SERVICE

4. Service completed
   PATCH /admin/product-serials/X { status: "repaired" }
   Status: REPAIRED → back to SOLD

5. Or: Warranty replacement
   PATCH /admin/product-serials/X { status: "replaced" }
   Status: REPLACED
   New serial assigned to customer with fresh warranty
```

### Workflow 2: Inventory Return

```
1. Purchase received
   Status: SOLD

2. Customer returns item
   Status: RETURNED

3. Options:
   a) Resell as new
      PATCH { status: "available" }
   b) Mark as damaged
      PATCH { status: "damaged" }
   c) Replace customer's unit
      PATCH { status: "replaced" }
      Create new serial for customer
```

## Error Handling Examples

### Example 11: Serial not found
```json
{
  "success": false,
  "error": "Serial number not found",
  "code": 404
}
```

### Example 12: Invalid status transition
```json
{
  "success": false,
  "error": "Cannot change serial from sold to available.",
  "code": 422
}
```

### Example 13: Duplicate serial number
```json
{
  "success": false,
  "error": "Serial number DELL-LT-001 already exists.",
  "code": 422
}
```

### Example 14: Missing product serialization
```json
{
  "success": false,
  "error": "Serialized tracking is not enabled for this product.",
  "code": 422
}
```

## API Response Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden (permission denied) |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

## Permissions Required

| Operation | Permission |
|-----------|-----------|
| View serials | `product-serials.view` |
| Create/edit serials | `product-serials.manage` |
| Receive stock | `product-serials.manage` |
| Change status | `product-serials.manage` |
| Delete/destroy | Not implemented (archive via status) |
