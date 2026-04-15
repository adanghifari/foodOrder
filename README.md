# ♨️ KedaiKlik Food Order System

KedaiKlik adalah aplikasi pemesanan makanan berbasis Laravel untuk skenario dine-in (makan di tempat) yang mencakup:
- Frontliner web flow (scan meja, pilih menu, keranjang, checkout, struk)
- Backoffice admin (dashboard, kelola menu, pesanan, pembayaran, pengguna, meja)
- REST API untuk integrasi mobile/client
- Integrasi Midtrans untuk pembayaran
- MongoDB sebagai database utama

## 📌 Project Overview

KedaiKlik menggunakan struktur domain-based agar logika bisnis lebih terorganisir. Domain utama di proyek ini:
- Auth
- Cart
- Menu
- Order
- Payment
- Table

Aplikasi menyediakan 3 jalur utama:
- Web Backoffice: `/backoffice`
- Web Frontliner: `/kedai`, `/menu`, `/keranjang`, `/kedai/pembayaran/*`
- REST API v1: `/api/v1/*`

## ✨ Main Features

- Login backoffice berbasis session untuk role ADMIN
- CRUD menu termasuk upload/hapus gambar menu
- Manajemen pesanan dan update status order
- Manajemen pembayaran dan webhook Midtrans
- Role-based API auth (ADMIN, CUSTOMER) menggunakan JWT
- Table session handling untuk flow dine-in
- UI backoffice dan frontliner berbasis Blade + Tailwind

## 🛠️ Tech Stack

- Backend Framework: Laravel 12
- Bahasa: PHP 8.2+
- Database: MongoDB
- API Auth: JWT (`php-open-source-saver/jwt-auth`)
- MongoDB Driver: `mongodb/laravel-mongodb`
- Payment Gateway: Midtrans (`midtrans/midtrans-php`)
- Frontend: Blade-laravel
- Styling: Tailwind CSS v4
- HTTP Client: Axios
- Testing: PHPUnit

## ✅ Requirements

Pastikan environment lokal memiliki:
- PHP `>= 8.2`
- Composer `>= 2.x`
- Node.js `>= 18` (disarankan LTS)
- npm `>= 9`
- MongoDB
- Git

## ⚡ Quick Start

### 5.1 Clone Repository and Install Dependencies

```bash
git clone https://github.com/adanghifari/foodOrder.git
cd foodOrder
composer install
```

### 5.2 Setup Environment File

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

### 5.3 Set MongoDB Database in `.env`

Minimal konfigurasi:

```env
DB_CONNECTION=mongodb
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=foodOrder_db
DB_USERNAME=
DB_PASSWORD=
```

### 5.4 Configure Midtrans (Sandbox)

```env
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx
MIDTRANS_MERCHANT_ID=Gxxxx
MIDTRANS_CALLBACK_URL=http://127.0.0.1:8000/api/v1/payments/webhook
MIDTRANS_FINISH_REDIRECT_URL=http://127.0.0.1:8000/kedai/pembayaran/selesai
```

### 5.5 Create Symbolic Link for Upload Files

```bash
php artisan storage:link
```

### 5.6 Run Application (Quick Option)

```bash
composer run dev
```

Perintah di atas menjalankan:
- Laravel server
- Queue listener
- Log watcher
- Vite dev server

### 5.7 Run Application 

```bash
php artisan serve
```

Akses aplikasi:
- Frontliner: `http://127.0.0.1:8000/kedai`
- Backoffice Login: `http://127.0.0.1:8000/backoffice/login`
- API base: `http://127.0.0.1:8000/api/v1`

## 🔐 Important ENV Configuration

Selain DB, beberapa variabel penting di `.env`:

```env
APP_NAME=Laravel
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

JWT_SECRET=
JWT_TTL=60

MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_MERCHANT_ID=
MIDTRANS_CALLBACK_URL=
MIDTRANS_FINISH_REDIRECT_URL=
```

## 🧭 Main Route Structure

### Web Routes
- `/backoffice/*` untuk panel admin
- `/menu` untuk halaman menu customer
- `/keranjang` untuk keranjang customer
- `/kedai/pembayaran/struk` untuk struk pembayaran

### API Routes (v1)
- `/api/v1/auth/*`
- `/api/v1/menus/*`
- `/api/v1/cart/*`
- `/api/v1/orders/*`
- `/api/v1/payments/*`
- `/api/v1/overview/*`

## 🗄️ Database and Schema Notes

- Proyek menggunakan MongoDB Eloquent model dari package `mongodb/laravel-mongodb`
- Koleksi penting mencakup `users`, `menu_item`, dan koleksi terkait order/payment
- Tersedia command custom untuk validator schema menu:

```bash
php artisan mongo:ensure-menu-schema
```

## 🔑 Authentication and Roles

Role yang digunakan:
- ADMIN
- CUSTOMER

Backoffice auth:
- Login admin melalui `/backoffice/login`
- Proteksi panel menggunakan session (`backoffice_is_admin`)

API auth:
- Register customer: `POST /api/v1/auth/register`
- Login: `POST /api/v1/auth/login`
- Protected route menggunakan middleware `auth:api`
- Otorisasi role menggunakan middleware role (`ADMIN`/`CUSTOMER`)

## 🧪 Testing

Jalankan test:

```bash
php artisan test
```

atau:

```bash
composer test
```
