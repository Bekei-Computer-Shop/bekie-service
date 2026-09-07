# KHQR API - cURL Examples & Testing

## Quick Testing Guide

All examples assume you have a valid JWT token. Replace `{TOKEN}` with your actual bearer token.

---

## 1. Get KHQR Payment Options

### Request
```bash
curl -X GET "http://localhost:8000/api/v1/payments/khqr/options?platform=web" \
  -H "Content-Type: application/json"
```

### Response
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

## 2. Generate KHQR QR Code

### Request (Web Client)
```bash
curl -X POST "http://localhost:8000/api/v1/payments/khqr/generate" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": 1,
    "platform": "web"
  }'
```

### Request (Mobile Client)
```bash
curl -X POST "http://localhost:8000/api/v1/payments/khqr/generate" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": 1,
    "platform": "mobile"
  }'
```

### Response (Success)
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
    "amount": 100.00,
    "currency": "USD",
    "reference": "ORD1",
    "expires_at": "2026-09-07T14:30:22Z"
  }
}
```

### Response (Order Not Found)
```json
{
  "status": "error",
  "message": "Order not found.",
  "errors": {}
}
```

### Response (Order Already Paid)
```json
{
  "status": "error",
  "message": "Order is already paid.",
  "errors": {}
}
```

---

## 3. Check Payment Status - Pending

### Request
```bash
curl -X GET "http://localhost:8000/api/v1/payments/khqr/KHQR20260906143022AB1CD2EF" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json"
```

### Response (Pending)
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

---

## 4. Check Payment Status - Paid

### Request
```bash
curl -X GET "http://localhost:8000/api/v1/payments/khqr/KHQR20260906143022AB1CD2EF" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json"
```

### Response (Paid)
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

---

## 5. Check Payment Status - Expired

### Request
```bash
curl -X GET "http://localhost:8000/api/v1/payments/khqr/KHQR20260906143022AB1CD2EF" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json"
```

### Response (Expired)
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

---

## 6. Confirm Payment (Manual)

### Request
```bash
curl -X POST "http://localhost:8000/api/v1/payments/khqr/KHQR20260906143022AB1CD2EF/confirm" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_reference": "TXN20260906000001"
  }'
```

### Response
```json
{
  "status": "success",
  "message": "KHQR payment confirmed.",
  "data": {}
}
```

### Response (Transaction Not Found)
```json
{
  "status": "error",
  "message": "Transaction not found.",
  "errors": {}
}
```

---

## 7. Cancel Payment

### Request
```bash
curl -X DELETE "http://localhost:8000/api/v1/payments/khqr/KHQR20260906143022AB1CD2EF" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json"
```

### Response (Success)
```json
{
  "status": "success",
  "message": "KHQR payment cancelled.",
  "data": {}
}
```

### Response (Payment Already Completed)
```json
{
  "status": "error",
  "message": "Payment already completed.",
  "errors": {}
}
```

---

## Postman Collection

### Setup

1. **Create Environment** with variables:
   ```
   {
     "base_url": "http://localhost:8000",
     "token": "your-jwt-token-here",
     "order_id": "1",
     "transaction_id": "KHQR20260906143022AB1CD2EF"
   }
   ```

2. **Import these Requests**:

### Get KHQR Options
```
Method: GET
URL: {{base_url}}/api/v1/payments/khqr/options?platform=web
Headers:
  Content-Type: application/json
```

### Generate QR Code
```
Method: POST
URL: {{base_url}}/api/v1/payments/khqr/generate
Headers:
  Authorization: Bearer {{token}}
  Content-Type: application/json
Body (JSON):
{
  "order_id": {{order_id}},
  "platform": "web"
}
```

### Check Status
```
Method: GET
URL: {{base_url}}/api/v1/payments/khqr/{{transaction_id}}
Headers:
  Authorization: Bearer {{token}}
  Content-Type: application/json
```

### Confirm Payment
```
Method: POST
URL: {{base_url}}/api/v1/payments/khqr/{{transaction_id}}/confirm
Headers:
  Authorization: Bearer {{token}}
  Content-Type: application/json
Body (JSON):
{
  "payment_reference": "TXN20260906000001"
}
```

### Cancel Payment
```
Method: DELETE
URL: {{base_url}}/api/v1/payments/khqr/{{transaction_id}}
Headers:
  Authorization: Bearer {{token}}
  Content-Type: application/json
```

---

## JavaScript / Fetch API Examples

### Generate QR Code
```javascript
const generateQrCode = async (orderId, token) => {
  const response = await fetch('http://localhost:8000/api/v1/payments/khqr/generate', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      order_id: orderId,
      platform: 'web'
    })
  });

  return await response.json();
};

// Usage
const result = await generateQrCode(1, myToken);
if (result.status === 'success') {
  console.log('Transaction ID:', result.data.transaction_id);
  console.log('QR Code URL:', result.data.qr_code_url);
}
```

### Poll for Payment Status
```javascript
const pollPaymentStatus = async (transactionId, token, maxAttempts = 30) => {
  let attempts = 0;
  
  return new Promise((resolve, reject) => {
    const interval = setInterval(async () => {
      attempts++;
      
      try {
        const response = await fetch(
          `http://localhost:8000/api/v1/payments/khqr/${transactionId}`,
          {
            headers: {
              'Authorization': `Bearer ${token}`
            }
          }
        );
        
        const result = await response.json();
        const status = result.data.payment_status;
        
        console.log(`[Attempt ${attempts}] Status: ${status}`);
        
        if (status === 'paid') {
          clearInterval(interval);
          resolve(result.data);
        } else if (status === 'expired') {
          clearInterval(interval);
          reject(new Error('Payment expired'));
        } else if (attempts >= maxAttempts) {
          clearInterval(interval);
          reject(new Error('Max polling attempts reached'));
        }
      } catch (error) {
        clearInterval(interval);
        reject(error);
      }
    }, 5000); // Poll every 5 seconds
  });
};

// Usage
try {
  const paymentData = await pollPaymentStatus(transactionId, token);
  console.log('Payment confirmed:', paymentData);
} catch (error) {
  console.error('Payment failed:', error);
}
```

### Cancel Payment
```javascript
const cancelPayment = async (transactionId, token) => {
  const response = await fetch(
    `http://localhost:8000/api/v1/payments/khqr/${transactionId}`,
    {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    }
  );

  return await response.json();
};

// Usage
const result = await cancelPayment(transactionId, token);
if (result.status === 'success') {
  console.log('Payment cancelled successfully');
}
```

---

## PHP / Laravel Examples

### Generate QR Code
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
  ->post('http://localhost:8000/api/v1/payments/khqr/generate', [
    'order_id' => 1,
    'platform' => 'web'
  ]);

$data = $response->json();

if ($data['status'] === 'success') {
  $transactionId = $data['data']['transaction_id'];
  $qrCodeUrl = $data['data']['qr_code_url'];
}
```

### Check Status
```php
$response = Http::withToken($token)
  ->get("http://localhost:8000/api/v1/payments/khqr/{$transactionId}");

$status = $response->json()['data']['payment_status'];

if ($status === 'paid') {
  // Mark order as paid
}
```

---

## Error Handling Examples

### Handle Missing Order
```bash
curl -X POST "http://localhost:8000/api/v1/payments/khqr/generate" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": 99999,
    "platform": "web"
  }'
```

Response:
```json
{
  "status": "error",
  "message": "Order not found.",
  "errors": {}
}
```

### Handle Invalid Parameters
```bash
curl -X POST "http://localhost:8000/api/v1/payments/khqr/generate" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "invalid-string",
    "platform": "web"
  }'
```

Response:
```json
{
  "status": "error",
  "message": "The order id field must be an integer.",
  "errors": {
    "order_id": ["The order id field must be an integer."]
  }
}
```

### Handle Unauthorized Access
```bash
curl -X POST "http://localhost:8000/api/v1/payments/khqr/generate" \
  -H "Authorization: Bearer invalid-token" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": 1,
    "platform": "web"
  }'
```

Response:
```json
{
  "status": "error",
  "message": "Unauthorized.",
  "errors": {}
}
```

---

## Test Workflow

### Complete End-to-End Test Flow

1. **Generate QR Code**
   ```bash
   TRANSACTION_ID=$(curl -s -X POST "http://localhost:8000/api/v1/payments/khqr/generate" \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"order_id": 1, "platform": "web"}' \
     | jq -r '.data.transaction_id')
   
   echo "Transaction ID: $TRANSACTION_ID"
   ```

2. **Poll Status (10 attempts)**
   ```bash
   for i in {1..10}; do
     echo "Attempt $i..."
     curl -s -X GET "http://localhost:8000/api/v1/payments/khqr/$TRANSACTION_ID" \
       -H "Authorization: Bearer $TOKEN" \
       | jq '.data.payment_status'
     sleep 2
   done
   ```

3. **Manually Confirm (Simulating Webhook)**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/payments/khqr/$TRANSACTION_ID/confirm" \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"payment_reference": "TXN20260906000001"}'
   ```

4. **Verify Status Changed to Paid**
   ```bash
   curl -s -X GET "http://localhost:8000/api/v1/payments/khqr/$TRANSACTION_ID" \
     -H "Authorization: Bearer $TOKEN" \
     | jq '.data'
   ```

---

## Rate Limiting

If you exceed the rate limit:

```json
{
  "status": "error",
  "message": "Too many requests. Please try again later.",
  "errors": {}
}
```

HTTP Status: `429 Too Many Requests`

---

## Troubleshooting

### Issue: 401 Unauthorized
**Cause**: Invalid or expired token  
**Solution**: Verify token is valid and not expired

### Issue: 404 Not Found
**Cause**: Order or transaction doesn't exist  
**Solution**: Verify order ID or transaction ID is correct

### Issue: 422 Validation Error
**Cause**: Invalid parameters  
**Solution**: Check request body matches validation rules

### Issue: 502 Bad Gateway
**Cause**: Service error  
**Solution**: Check service logs and retry

---

## Performance Tips

- **Poll Interval**: Use 5-second interval for best UX
- **Timeout**: Implement 5-minute timeout for polling
- **Retry**: Implement exponential backoff on errors
- **Cache**: Cache payment options response (1 hour)
- **Batch**: Don't create multiple QR codes for same order

---

## Security Checklist

- ✅ Always use HTTPS in production
- ✅ Store token securely (never in localStorage for sensitive operations)
- ✅ Validate order ownership on client side
- ✅ Implement CSRF protection
- ✅ Rate limit polling (don't poll > every 2 seconds)
- ✅ Verify webhook signatures (future phase)

---

Generated: 2026-09-06  
Version: 1.0.0
