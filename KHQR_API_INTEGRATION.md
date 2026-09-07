# ABA KHQR Integration API Documentation

## Overview

KHQR (Khmer Quick Response Code) is a QR-based payment standard for ABA bank transfers in Cambodia. This API provides endpoints for both web and mobile clients to generate and manage KHQR payments.

**Base URL**: `https://api.bekie.local/api/v1`

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Web Client Integration](#web-client-integration)
3. [Mobile Client Integration](#mobile-client-integration)
4. [API Endpoints](#api-endpoints)
5. [Error Handling](#error-handling)
6. [Webhook Integration](#webhook-integration)
7. [Configuration](#configuration)

---

## Getting Started

### Prerequisites

- Valid Bearer token (JWT) for authentication
- Order ID to generate payment QR code
- Customer must have permission `client.orders.manage`

### Authentication

All endpoints (except `/payments/khqr/options`) require Bearer token authentication:

```bash
Authorization: Bearer {jwt_token}
```

---

## Web Client Integration

### Flow Diagram

```
[Web Client]
    ↓
[Generate KHQR] → GET /payments/khqr/generate
    ↓
[Display QR Code Image]
    ↓
[Customer Scans with Banking App]
    ↓
[Poll for Status] → GET /payments/khqr/{transactionId}
    ↓
[Payment Confirmed] → Order marked as paid
```

### Implementation Steps

#### 1. Display Payment Options

```javascript
// Get available payment methods
const response = await fetch('/api/v1/payments/khqr/options', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${authToken}`,
  }
});

const { data } = await response.json();
// data.khqr.enabled = true/false based on configuration
```

#### 2. Generate QR Code

```javascript
// Request KHQR QR code generation
const response = await fetch('/api/v1/payments/khqr/generate', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${authToken}`,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    order_id: 1042,
    platform: 'web'
  })
});

const { data, status } = await response.json();

if (status === 'success') {
  // data.qr_code_url = 'data:image/png;base64,iVBORw0...'
  // data.transaction_id = 'KHQR20260906143022AB1CD2EF'
  // data.expires_at = '2026-09-07T14:30:22Z'
  
  // Display QR code image
  document.getElementById('qr-code').src = data.qr_code_url;
  document.getElementById('expiry-time').textContent = data.expires_at;
}
```

#### 3. Poll for Payment Status

```javascript
// Poll every 5 seconds
const checkPaymentStatus = async (transactionId) => {
  const response = await fetch(`/api/v1/payments/khqr/${transactionId}`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${authToken}`,
    }
  });

  const { data } = await response.json();

  if (data.payment_status === 'paid') {
    // Payment completed!
    console.log('Payment confirmed at', data.paid_at);
    window.location.href = '/orders/success';
  } else if (data.payment_status === 'expired') {
    // QR code expired
    console.log('Payment expired, please generate new QR code');
    showRetryButton();
  } else {
    // Still pending
    setTimeout(() => checkPaymentStatus(transactionId), 5000);
  }
};

checkPaymentStatus(transactionId);
```

#### 4. Cancel Payment (Optional)

```javascript
// Allow customer to cancel and choose different payment method
const cancelPayment = async (transactionId) => {
  const response = await fetch(`/api/v1/payments/khqr/${transactionId}`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${authToken}`,
    }
  });

  if (response.ok) {
    // Return to payment method selection
    window.location.href = '/checkout/payment-methods';
  }
};
```

---

## Mobile Client Integration

### Flow Diagram

```
[Mobile App]
    ↓
[Generate KHQR] → POST /payments/khqr/generate
    ↓
[Display QR Code or Payment Details]
    ↓
[Customer Scans or Manually Enters]
    ↓
[Poll or Webhook Notification]
    ↓
[Payment Confirmed] → Update Order Status
```

### Implementation Steps

#### 1. Generate QR Code

```swift
// iOS Example using URLSession
let url = URL(string: "https://api.bekie.local/api/v1/payments/khqr/generate")!
var request = URLRequest(url: url)
request.httpMethod = "POST"
request.setValue("Bearer \(authToken)", forHTTPHeaderField: "Authorization")
request.setValue("application/json", forHTTPHeaderField: "Content-Type")

let body: [String: Any] = [
  "order_id": 1042,
  "platform": "mobile"
]
request.httpBody = try JSONSerialization.data(withJSONObject: body)

let (data, response) = try await URLSession.shared.data(for: request)
let result = try JSONDecoder().decode(KhqrResponse.self, from: data)

// result.data.transaction_id
// result.data.qr_code_url (base64 image or data URL)
// result.data.expires_at
```

#### 2. Display QR Code or Payment Instructions

```kotlin
// Android Example using Retrofit
interface KhqrService {
  @POST("payments/khqr/generate")
  suspend fun generateQrCode(
    @Body request: KhqrGenerateRequest
  ): ApiResponse<KhqrResponse>
}

// In Activity/Fragment
viewModel.generateQrCode(orderId = 1042, platform = "mobile")
  .observe(this) { result ->
    when (result) {
      is Success -> {
        // Display QR code
        val qrCodeUrl = result.data.qr_code_url
        val imageDecoder = ImageDecoder.Source.createSource(URI(qrCodeUrl).toURL().openStream())
        val bitmap = ImageDecoder.decodeBitmap(imageDecoder)
        qrImageView.setImageBitmap(bitmap)
        
        // Store transaction ID for status checks
        currentTransactionId = result.data.transaction_id
        startPollingForPayment(result.data.transaction_id)
      }
      is Error -> {
        // Handle error
      }
    }
  }
```

#### 3. Poll for Payment Status

```kotlin
// Kotlin coroutine example
private fun startPollingForPayment(transactionId: String) {
  viewModelScope.launch {
    while (isActive) {
      try {
        val status = khqrService.checkStatus(transactionId)
        
        when (status.data.payment_status) {
          "paid" -> {
            // Payment confirmed
            navigateToOrderSuccess()
            return@launch
          }
          "expired" -> {
            // QR code expired
            showRetryDialog()
            return@launch
          }
          "pending" -> {
            // Continue polling
            delay(5000)
          }
        }
      } catch (e: Exception) {
        Log.e("KHQR", "Status check failed", e)
        delay(10000) // Longer delay on error
      }
    }
  }
}
```

---

## API Endpoints

### 1. Get KHQR Payment Options

**Public Endpoint**

```http
GET /api/v1/payments/khqr/options?platform=web
```

**Query Parameters**

| Parameter | Type | Required | Default | Values |
|-----------|------|----------|---------|--------|
| `platform` | string | No | `web` | `web`, `mobile` |

**Response**

```json
{
  "status": "success",
  "message": "KHQR payment options retrieved.",
  "data": {
    "khqr": {
      "enabled": true,
      "name": "ABA KHQR",
      "description": "Scan QR code to pay via ABA bank",
      "supported_currencies": ["USD", "KHR"],
      "web_instructions": "Display QR code for customer to scan with mobile banking app",
      "mobile_instructions": "Customer scans QR code using their mobile banking app"
    }
  }
}
```

---

### 2. Generate KHQR QR Code

**Authenticated Endpoint**

```http
POST /api/v1/payments/khqr/generate
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body**

```json
{
  "order_id": 1042,
  "platform": "web"
}
```

**Parameters**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `order_id` | integer | Yes | Customer's order ID |
| `platform` | string | No | `web` or `mobile` (defaults to `web`) |

**Response (Success)**

```json
{
  "status": "success",
  "message": "KHQR QR code generated successfully.",
  "data": {
    "transaction_id": "KHQR20260906143022AB1CD2EF",
    "qr_code_url": "data:image/png;base64,iVBORw0KGgoAAAANS...",
    "qr_code_data": "000201121129300012D156000700...",
    "merchant_id": "bekie",
    "merchant_name": "Bekie",
    "amount": 50.00,
    "currency": "USD",
    "reference": "ORD1042",
    "expires_at": "2026-09-07T14:30:22Z"
  }
}
```

**Error Responses**

| Status | Message | Meaning |
|--------|---------|---------|
| 404 | Order not found | Order doesn't exist or doesn't belong to customer |
| 422 | Order is already paid | Cannot generate QR for paid order |
| 422 | Order currency not supported | Order currency is not USD or KHR |
| 502 | Unable to generate KHQR QR code | Service error |

---

### 3. Check Payment Status

**Authenticated Endpoint**

```http
GET /api/v1/payments/khqr/{transactionId}
Authorization: Bearer {token}
```

**URL Parameters**

| Parameter | Type | Required | Example |
|-----------|------|----------|---------|
| `transactionId` | string | Yes | `KHQR20260906143022AB1CD2EF` |

**Response - Pending**

```json
{
  "status": "success",
  "message": "Payment status retrieved.",
  "data": {
    "payment_status": "pending",
    "expires_at": "2026-09-07T14:30:22Z"
  }
}
```

**Response - Paid**

```json
{
  "status": "success",
  "message": "Payment status retrieved.",
  "data": {
    "payment_status": "paid",
    "paid_at": "2026-09-06T14:35:10Z",
    "payment_reference": "TXN20260906000001"
  }
}
```

**Response - Expired**

```json
{
  "status": "success",
  "message": "Payment status retrieved.",
  "data": {
    "payment_status": "expired",
    "expired_at": "2026-09-07T14:30:22Z"
  }
}
```

**Error Responses**

| Status | Message | Meaning |
|--------|---------|---------|
| 404 | Transaction not found | Transaction doesn't exist or doesn't belong to customer |

---

### 4. Confirm Payment (Manual/Admin)

**Authenticated Endpoint**

```http
POST /api/v1/payments/khqr/{transactionId}/confirm
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body**

```json
{
  "payment_reference": "TXN20260906000001"
}
```

**Parameters**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `payment_reference` | string | No | External payment reference from bank |

**Response**

```json
{
  "status": "success",
  "message": "KHQR payment confirmed.",
  "data": {}
}
```

---

### 5. Cancel Payment Request

**Authenticated Endpoint**

```http
DELETE /api/v1/payments/khqr/{transactionId}
Authorization: Bearer {token}
```

**URL Parameters**

| Parameter | Type | Required | Example |
|-----------|------|----------|---------|
| `transactionId` | string | Yes | `KHQR20260906143022AB1CD2EF` |

**Response**

```json
{
  "status": "success",
  "message": "KHQR payment cancelled.",
  "data": {}
}
```

**Error Responses**

| Status | Message | Meaning |
|--------|---------|---------|
| 404 | Transaction not found | Transaction doesn't exist or doesn't belong to customer |
| 422 | Payment already completed | Cannot cancel paid transaction |

---

## Error Handling

### Standard Error Response Format

```json
{
  "status": "error",
  "message": "Descriptive error message",
  "errors": {
    "field_name": ["Specific validation error"]
  }
}
```

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Successful request |
| 400 | Bad request (invalid parameters) |
| 401 | Unauthorized (missing/invalid token) |
| 404 | Resource not found |
| 422 | Validation error or business logic error |
| 500 | Server error |
| 502 | Gateway/service error |

### Handling Expired QR Codes

```javascript
if (data.payment_status === 'expired') {
  // Generate new QR code
  const newResult = await generateNewQrCode(orderId);
  updateQrCodeDisplay(newResult.qr_code_url);
}
```

---

## Webhook Integration

For production deployments, implement webhook handling to receive payment notifications:

### Payment Webhook

When a payment is confirmed (via bank webhook), the system will automatically update the order status to `paid`.

**Event**: `payment.confirmed`

```json
{
  "event": "payment.confirmed",
  "timestamp": "2026-09-06T14:35:10Z",
  "data": {
    "transaction_id": "KHQR20260906143022AB1CD2EF",
    "order_id": 1042,
    "amount": 50.00,
    "currency": "USD",
    "payment_reference": "TXN20260906000001",
    "paid_at": "2026-09-06T14:35:10Z"
  }
}
```

### Webhook Verification

All webhooks include an `X-KHQR-Signature` header for verification:

```php
$signature = $_SERVER['HTTP_X_KHQR_SIGNATURE'];
$payload = file_get_contents('php://input');
$hash = hash_hmac('sha256', $payload, config('services.khqr.webhook_secret'));

if (!hash_equals($hash, $signature)) {
  http_response_code(401);
  exit('Unauthorized');
}
```

---

## Configuration

### Environment Variables

```bash
# KHQR Configuration
KHQR_ENABLED=true
KHQR_MERCHANT_ID=bekie
KHQR_MERCHANT_NAME=Bekie

# Optional: Webhook settings
KHQR_WEBHOOK_SECRET=your-secret-key
KHQR_WEBHOOK_URL=https://api.bekie.local/webhooks/khqr/callback
```

### Service Configuration

File: `config/services.php`

```php
'khqr' => [
    'enabled' => (bool) env('KHQR_ENABLED', true),
    'merchant_id' => env('KHQR_MERCHANT_ID', 'bekie'),
    'merchant_name' => env('KHQR_MERCHANT_NAME', 'Bekie'),
],
```

---

## Database Schema

### khqr_transactions Table

```sql
CREATE TABLE khqr_transactions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  order_id BIGINT NOT NULL,
  transaction_id VARCHAR(255) UNIQUE NOT NULL,
  merchant_id VARCHAR(255) NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  currency VARCHAR(3) NOT NULL,
  khqr_data LONGTEXT NOT NULL,
  status ENUM('pending', 'paid', 'expired', 'cancelled') DEFAULT 'pending',
  paid_at TIMESTAMP NULL,
  cancelled_at TIMESTAMP NULL,
  expires_at TIMESTAMP NOT NULL,
  payment_reference VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_user_id_status (user_id, status),
  INDEX idx_order_id (order_id),
  INDEX idx_transaction_id (transaction_id),
  INDEX idx_expires_at (expires_at)
);
```

---

## Rate Limiting

All authenticated KHQR endpoints use the `client-checkout` throttle middleware:

```
Requests: Depends on Laravel throttle configuration
Window: Typically per minute
```

To handle rate limiting:

```http
HTTP/429 Too Many Requests

{
  "status": "error",
  "message": "Too many requests. Please try again later."
}
```

---

## Example: Complete Flow

### Web Client Complete Example

```html
<!DOCTYPE html>
<html>
<head>
  <title>KHQR Payment</title>
</head>
<body>
  <div id="payment-container">
    <h1>Pay with KHQR</h1>
    
    <div id="qr-code-container" style="display:none;">
      <img id="qr-code-image" alt="KHQR QR Code" />
      <p>Expires at: <span id="expires-at"></span></p>
      <button onclick="pollPaymentStatus()">Check Payment Status</button>
      <button onclick="cancelPayment()">Cancel Payment</button>
    </div>

    <div id="loading" style="display:none;">
      <p>Checking payment status...</p>
    </div>

    <div id="success" style="display:none;">
      <h2>✓ Payment Confirmed!</h2>
      <p id="success-message"></p>
    </div>

    <div id="error" style="display:none;">
      <h2>✗ Payment Failed</h2>
      <p id="error-message"></p>
      <button onclick="generateQrCode()">Try Again</button>
    </div>
  </div>

  <script>
    const orderId = new URLSearchParams(window.location.search).get('order_id');
    const authToken = localStorage.getItem('auth_token');
    let transactionId = null;
    let pollInterval = null;

    async function generateQrCode() {
      try {
        const response = await fetch('/api/v1/payments/khqr/generate', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            order_id: orderId,
            platform: 'web'
          })
        });

        const result = await response.json();

        if (result.status === 'success') {
          transactionId = result.data.transaction_id;
          document.getElementById('qr-code-image').src = result.data.qr_code_url;
          document.getElementById('expires-at').textContent = result.data.expires_at;
          document.getElementById('qr-code-container').style.display = 'block';
          startPollingForPayment();
        } else {
          showError(result.message);
        }
      } catch (error) {
        showError('Failed to generate QR code: ' + error.message);
      }
    }

    function startPollingForPayment() {
      pollInterval = setInterval(pollPaymentStatus, 5000);
    }

    async function pollPaymentStatus() {
      try {
        const response = await fetch(`/api/v1/payments/khqr/${transactionId}`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${authToken}`
          }
        });

        const result = await response.json();
        const status = result.data.payment_status;

        if (status === 'paid') {
          clearInterval(pollInterval);
          showSuccess('Payment confirmed! Redirecting...');
          setTimeout(() => {
            window.location.href = `/orders/${orderId}/success`;
          }, 2000);
        } else if (status === 'expired') {
          clearInterval(pollInterval);
          showError('Payment expired. Please generate a new QR code.');
          document.getElementById('qr-code-container').style.display = 'none';
        }
      } catch (error) {
        console.error('Status check failed:', error);
      }
    }

    async function cancelPayment() {
      if (!confirm('Cancel this payment request?')) return;

      try {
        const response = await fetch(`/api/v1/payments/khqr/${transactionId}`, {
          method: 'DELETE',
          headers: {
            'Authorization': `Bearer ${authToken}`
          }
        });

        if (response.ok) {
          clearInterval(pollInterval);
          window.location.href = '/checkout/payment-methods';
        }
      } catch (error) {
        showError('Failed to cancel: ' + error.message);
      }
    }

    function showSuccess(message) {
      document.getElementById('qr-code-container').style.display = 'none';
      document.getElementById('success-message').textContent = message;
      document.getElementById('success').style.display = 'block';
    }

    function showError(message) {
      document.getElementById('error-message').textContent = message;
      document.getElementById('error').style.display = 'block';
    }

    // Initialize
    generateQrCode();
  </script>
</body>
</html>
```

---

## Support

For issues or questions regarding KHQR integration:

1. Check the error messages and HTTP status codes
2. Verify authentication token is valid
3. Ensure order exists and belongs to authenticated customer
4. Check KHQR is enabled in configuration
5. Contact support with transaction ID for debugging

---

## Changelog

### v1.0.0 (2026-09-06)

- Initial KHQR API implementation
- Web and mobile client support
- QR code generation with TLV encoding
- Payment status polling
- Transaction management
- Configuration support
