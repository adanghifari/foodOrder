# Chatbot Response Contract

Dokumen ini mendefinisikan kontrak response `POST /api/v1/chatbot/message` agar render Flutter konsisten.

## Top-Level JSON

```json
{
  "status": "success",
  "message": "Chatbot response generated",
  "data": {
    "response_version": "1.1",
    "reply": "string",
    "intent": "string",
    "data": {},
    "actions": [],
    "cards": []
  }
}
```

## Field Wajib

- `response_version`: versi kontrak response chatbot.
- `reply`: teks balasan chatbot untuk chat bubble.
- `intent`: intent final yang dipakai backend.
- `data`: payload domain sesuai intent, bisa `null`.
- `actions`: daftar interaksi cepat. Selalu array.
- `cards`: daftar blok visual. Selalu array.

## Action Object

Minimal shape:

```json
{
  "ui_block_type": "quick_reply",
  "type": "quick_reply",
  "label": "Lihat Keranjang",
  "value": "greeting_view_cart"
}
```

Catatan:
- `ui_block_type` dan `type` disamakan oleh backend.
- Untuk saat ini action utama: `quick_reply`.

## Card Object

Semua card memiliki:

```json
{
  "ui_block_type": "menu_card",
  "type": "menu_card"
}
```

Tipe card aktif:
- `menu_card`
- `order_summary_card`
- `tracking_status_card`

### `menu_card`

```json
{
  "ui_block_type": "menu_card",
  "type": "menu_card",
  "menu": {
    "menu_id": "string",
    "menu_name": "string",
    "description": "string",
    "price": 18000,
    "stock": 10,
    "category": "makanan utama",
    "image_url": "/storage/menu/ayam-geprek.jpg"
  }
}
```

### `order_summary_card`

```json
{
  "ui_block_type": "order_summary_card",
  "type": "order_summary_card",
  "items": [],
  "total": 54000
}
```

### `tracking_status_card`

```json
{
  "ui_block_type": "tracking_status_card",
  "type": "tracking_status_card",
  "order_id": "string",
  "status": "in_progress",
  "status_label": "Sedang Diproses",
  "payment_status": "PENDING",
  "queue_number": 7,
  "total_price": 54000,
  "created_at": "2026-05-23 21:10:00"
}
```

## Status Label Tracking (Indonesia)

Backend mapping:
- `PENDING_PAYMENT` -> `Menunggu Pembayaran`
- `CONFIRMED` -> `Terkonfirmasi`
- `IN_QUEUE` -> `Dalam Antrean`
- `IN_PROGRESS` -> `Sedang Diproses`
- `DELIVERED` -> `Disajikan`

## Catatan Integrasi Flutter

- Render chat bubble dari `data.reply`.
- Render tombol cepat dari `data.actions`.
- Render blok visual dari `data.cards`.
- Jangan hardcode harga/stok/status dari client; selalu pakai payload backend.
