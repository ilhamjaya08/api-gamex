# Transaction API Documentation

API untuk melakukan transaksi topup game menggunakan saldo balance user.

---

## Base URL
```
http://localhost:8000/api
```

---

## Authentication
Semua endpoint transaction memerlukan authentication Bearer token dari login.

**Header:**
```
Authorization: Bearer {your_token}
```

---

## Endpoints

### 1. Get Transaction List

Menampilkan daftar transaksi user (paginated).

**Endpoint:**
```
GET /api/transactions
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "product_id": 860,
      "target_id": "081234567890",
      "server_id": null,
      "payment_method": "balance",
      "amount": "1038.00",
      "provider_trx_id": "210286229",
      "status": "success",
      "created_at": "2025-11-16T10:30:00.000000Z",
      "updated_at": "2025-11-16T10:31:00.000000Z",
      "product": {
        "id": 860,
        "kode": "D1",
        "nama": "Top Up Saldo DANA",
        "keterangan": "Dana 1.000",
        "harga": "1038.00"
      }
    }
  ],
  "per_page": 20,
  "total": 5
}
```

---

### 2. Create New Transaction

Membuat transaksi baru untuk topup game. Saldo user akan langsung dipotong.

**Endpoint:**
```
POST /api/transactions
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "product_id": 860,
  "target_id": "081234567890",
  "server_id": null
}
```

**Field Descriptions:**
- `product_id` (required, integer): ID produk yang akan dibeli (dari `/api/categories/{id}/products`)
- `target_id` (required, string): Nomor tujuan / Game ID / Account ID
- `server_id` (optional, string): Server ID untuk game yang memerlukan (e.g., Mobile Legends)

**Response Success (201):**
```json
{
  "message": "Transaksi berhasil dibuat",
  "transaction": {
    "id": 1,
    "user_id": 1,
    "product_id": 860,
    "target_id": "081234567890",
    "server_id": null,
    "payment_method": "balance",
    "amount": "1038.00",
    "provider_trx_id": "210286229",
    "status": "process",
    "created_at": "2025-11-16T10:30:00.000000Z",
    "updated_at": "2025-11-16T10:30:00.000000Z",
    "product": {
      "id": 860,
      "kode": "D1",
      "nama": "Top Up Saldo DANA",
      "keterangan": "Dana 1.000",
      "harga": "1038.00"
    }
  },
  "new_balance": "98962.00"
}
```

**Response Error - Saldo Tidak Cukup (422):**
```json
{
  "message": "Saldo tidak mencukupi",
  "required": "1038.00",
  "current_balance": "500.00",
  "shortage": "538.00"
}
```

**Response Error - Produk Tidak Tersedia (422):**
```json
{
  "message": "Produk sedang tidak tersedia"
}
```

**Response Error - Transaksi Gagal (422):**
```json
{
  "message": "Transaksi gagal: Pin Salah",
  "details": "R#0 D1.081234567890 GAGAL. Pin Salah"
}
```

---

### 3. Get Transaction Detail

Melihat detail transaksi tertentu.

**Endpoint:**
```
GET /api/transactions/{transaction_id}
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "transaction": {
    "id": 1,
    "user_id": 1,
    "product_id": 860,
    "target_id": "081234567890",
    "server_id": null,
    "payment_method": "balance",
    "amount": "1038.00",
    "provider_trx_id": "R230512.1908.2000FE",
    "status": "success",
    "created_at": "2025-11-16T10:30:00.000000Z",
    "updated_at": "2025-11-16T10:31:00.000000Z",
    "product": {
      "id": 860,
      "kode": "D1",
      "nama": "Top Up Saldo DANA",
      "keterangan": "Dana 1.000",
      "harga": "1038.00"
    }
  }
}
```

**Response Error - Unauthorized (403):**
```json
{
  "message": "Unauthorized"
}
```

---

### 4. Refresh Transaction Status

Melakukan pengecekan manual status transaksi ke OkeConnect. Jika transaksi gagal, saldo akan otomatis dikembalikan.

**Endpoint:**
```
GET /api/transactions/{transaction_id}/refresh-status
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "message": "Status berhasil diperbarui",
  "transaction": {
    "id": 1,
    "user_id": 1,
    "product_id": 860,
    "target_id": "081234567890",
    "server_id": null,
    "payment_method": "balance",
    "amount": "1038.00",
    "provider_trx_id": "R230512.1908.2000FE",
    "status": "success",
    "created_at": "2025-11-16T10:30:00.000000Z",
    "updated_at": "2025-11-16T10:31:00.000000Z",
    "product": {
      "id": 860,
      "kode": "D1",
      "nama": "Top Up Saldo DANA"
    }
  },
  "raw_response": "R#1 Three 1.000 T1.081234567890 sudah pernah jam 19:08, status Sukses..."
}
```

**Response - Already Finalized (200):**
```json
{
  "message": "Transaksi sudah selesai",
  "transaction": {
    "id": 1,
    "status": "success"
  }
}
```

---

## Transaction Status

Status yang mungkin ada:

- `pending` - Transaksi baru dibuat, belum diproses
- `process` - Sedang diproses oleh provider
- `success` - Transaksi berhasil
- `failed` - Transaksi gagal (saldo dikembalikan)
- `refund` - Transaksi di-refund

---

## Transaction Flow

1. **User memilih produk** dari endpoint `/api/categories/{id}/products`
2. **User membuat transaksi** dengan `POST /api/transactions`
   - Sistem cek saldo user
   - Sistem potong saldo user
   - Sistem kirim request ke OkeConnect
   - Response `status: process` atau langsung `failed`
3. **OkeConnect memproses transaksi**
   - Jika sukses/gagal, OkeConnect akan kirim callback ke `/api/webhook/transaction-callback`
   - Callback otomatis update status transaksi
   - Jika gagal, saldo otomatis dikembalikan
4. **User bisa cek status manual** dengan `GET /api/transactions/{id}/refresh-status`
   - Akan query langsung ke OkeConnect
   - Update status sesuai response
   - Jika gagal, refund saldo

---

## Webhook Callback (Internal - dari OkeConnect)

**Endpoint:**
```
GET /api/webhook/transaction-callback
```

**Parameters:**
- `refid` - Transaction ID
- `message` - Status message (URL encoded)

**Example Request:**
```
GET /api/webhook/transaction-callback?refid=114&message=T%23210288912%20R%23114%20Three%201.000%20T1.089660522887%20SUKSES.%20SN%3A%20R230512.1911.2100F1
```

**Response:**
```
OK
```

**Note:** Endpoint ini untuk internal use oleh OkeConnect. User tidak perlu memanggil endpoint ini.

---

## Error Codes

- `200` - Success
- `201` - Created (transaction berhasil dibuat)
- `401` - Unauthorized (token invalid/expired)
- `403` - Forbidden (bukan transaksi milik user)
- `404` - Not Found (transaksi tidak ditemukan)
- `422` - Unprocessable Entity (validation error, saldo tidak cukup, dll)
- `500` - Internal Server Error

---

## Example Usage (cURL)

### Create Transaction
```bash
curl -X POST http://localhost:8000/api/transactions \
  -H "Authorization: Bearer {your_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 860,
    "target_id": "081234567890"
  }'
```

### Get Transaction List
```bash
curl -X GET http://localhost:8000/api/transactions \
  -H "Authorization: Bearer {your_token}"
```

### Refresh Status
```bash
curl -X GET http://localhost:8000/api/transactions/1/refresh-status \
  -H "Authorization: Bearer {your_token}"
```

---

## Notes

1. **Saldo akan langsung dipotong** saat transaksi dibuat
2. **Jika transaksi gagal**, saldo akan otomatis dikembalikan (via callback atau refresh status)
3. **Status `process`** artinya masih dalam proses di provider, tunggu callback atau refresh manual
4. **Callback URL** harus di-setting di dashboard OkeConnect ke: `https://yourdomain.com/api/webhook/transaction-callback`
5. **Provider Transaction ID** (`provider_trx_id`) akan terisi setelah transaksi diproses, berisi serial number jika sukses
