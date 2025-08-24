# RxFlow API Documentation

## Endpoints

### 1. WhatsApp Webhook
**POST** `/api/webhook/whatsapp`

Receives messages from BotConversa.

**Headers:**
- `X-Webhook-Signature`: HMAC signature for verification

**Request Body:**
```json
{
  "from": "5511999999999",
  "message": {
    "type": "image",
    "image": {
      "url": "https://media.url/image.jpg"
    }
  }
}
```

### 2. Process Prescription
**POST** `/api/prescriptions/process`

Process a prescription image directly.

**Request:**
```json
{
  "image_base64": "base64_encoded_image",
  "phone_number": "5511999999999"
}
```

**Response:**
```json
{
  "success": true,
  "prescription_id": "RX123456",
  "quote_id": "Q789012",
  "total": 156.50,
  "payment_link": "https://payment.link/xyz"
}
```

### 3. Manual Validation
**GET** `/api/validations/pending`

Get prescriptions pending manual validation.

**POST** `/api/validations/{id}/approve`

Approve a prescription with corrections.

## Error Codes

- `400` - Invalid request data
- `401` - Authentication failed
- `422` - Validation error
- `500` - Server error

## Webhook Events

### Prescription Processed
```json
{
  "event": "prescription.processed",
  "data": {
    "prescription_id": "RX123456",
    "status": "success"
  }
}
```

### Payment Completed
```json
{
  "event": "payment.completed",
  "data": {
    "quote_id": "Q789012",
    "amount": 156.50
  }
}
```