# Task A — Bulk Import & Chunked Upload

Laravel 12 application that demonstrates a CSV-driven product catalog with chunked, resumable image uploads. The project ships with a Bootstrap 5.3 dashboard, an upload browser, and a product browser so you can exercise the full workflow end to end.

## Features
- Chunked image uploads (init → chunk streaming → checksum validation → completion) with automatic 256/512/1024px variants via Intervention Image.
- CSV import that upserts products by SKU, links optional uploads, and reports totals, invalid rows, and duplicates.
- Upload browser (`/uploads`) listing uploads, serving images through signed routes, and generating sample CSVs with League CSV.
- Product browser (`/products`) showing catalog details, metadata, and variant downloads.
- Artisan utility `mock:assets` for generating placeholder images plus large CSV files.
- PHPUnit coverage for importer behaviour (duplicates, invalid rows, optional uploads).

## Requirements
- PHP 8.2+
- Composer, Node.js + npm
- SQLite/MySQL/PostgreSQL (defaults to SQLite)

## Setup
```bash
# install dependencies
composer install
npm install && npm run build

# environment
tcp .env.example .env
php artisan key:generate

# database
php artisan migrate

# expose storage/public disk
php artisan storage:link
```

Update `.env` with any database credentials and set the default filesystem disk to `public` so thumbnails are web-accessible:
```
FILESYSTEM_DISK=public
```
Then clear cached config if necessary:
```
php artisan config:clear
```

## Running Locally
```bash
php artisan serve
npm run dev # optional for Vite hot reload
```
Visit http://localhost:8000/ in your browser.

## User Flows

### 1. Chunked Uploads (`/`)
1. Drag & drop or browse for one or many images.
2. The UI calculates SHA-256 hashes (Web Crypto with a pure JS fallback) and calls the API to initialise the upload.
3. Chunks (`2MB` default) stream to `POST /api/uploads/{upload}/chunk`. Re-sent chunks are ignored.
4. After all chunks finish, `POST /api/uploads/{upload}/complete` assembles the file, verifies the checksum, and generates 256/512/1024 variants plus the original image.
5. Completion returns image metadata. The upload badge in the UI shows the ULID you can reuse in CSV rows.

APIs in play:
```
POST /api/uploads/init
POST /api/uploads/{upload}/chunk
GET  /api/uploads/{upload}
POST /api/uploads/{upload}/complete
```

### 2. CSV Import (`/`)
- Upload a CSV with headers at least `sku,name,price`. `upload_id` is optional—leave it blank to omit images or populate with a completed Upload ULID.
- Extra columns become JSON metadata on the product.
- Missing required values mark the row invalid but do not abort the import.
- Duplicated SKUs within the same CSV count as duplicates and only the first instance is processed.
- Summary shows totals, created, updated, invalid, and duplicates.

API endpoint used by the form:
```
POST /api/products/import
```

### 3. Upload Browser (`/uploads`)
- Paginated table of uploads, status badges, variant thumbnails, and raw storage paths.
- Thumbnails are served via `GET /uploads/{upload}/{image}` which proxies through Laravel’s filesystem, so it works for any configured disk (public/local/S3).
- CSV Generator form lets you download ready-made data. Choose how many rows and which completed uploads to reference.

### 4. Product Browser (`/products`)
- Cards showing SKU, pricing, description, metadata badges, and variant links.
- Uses the same proxy route (`uploads.image`) for primary images and variants.

## Artisan Utilities
```
# Generate placeholder images and a 10k+ row CSV into storage/app/mock
php artisan mock:assets --images=250 --rows=12000
```

## Tests
The importer logic is covered with PHPUnit:
```
php vendor/phpunit/phpunit/phpunit tests/Unit/ProductCsvImporterTest.php
```

## Troubleshooting
- **Thumbnails show as broken**: confirm `FILESYSTEM_DISK=public`, run `php artisan storage:link`, and ensure existing `uploads` rows have `storage_disk = 'public'` with files under `storage/app/public`.
- **Checksum mismatch on completion**: ensure the frontend is not modifying files (e.g. by image editors) between chunks; the UI calculates the hash before uploading.
- **CSV rows flagged “Upload missing or incomplete”**: the `upload_id` must reference a completed upload ULID or be left blank.

## API Quick Reference
| Method | Endpoint | Purpose |
| ------ | -------- | ------- |
| POST   | `/api/uploads/init` | Create a resumable upload session |
| POST   | `/api/uploads/{upload}/chunk` | Store chunk `index` (1-based) |
| GET    | `/api/uploads/{upload}` | Check received chunk indexes |
| POST   | `/api/uploads/{upload}/complete` | Assemble file, validate checksum, generate variants |
| POST   | `/api/products/import` | Stream CSV, upsert products |

## Directory Highlights
- `app/Services/Uploads/ChunkedUploadService.php` — orchestrates init/chunk/assemble/variant workflow.
- `app/Services/Images/ImageVariantGenerator.php` — Intervention Image power for 256/512/1024 variants.
- `app/Services/Products/ProductCsvImporter.php` — League CSV importer with SKU upsert logic.
- `resources/views/dashboard.blade.php` — uploader + CSV UI.
- `resources/views/uploads/index.blade.php` — upload browser + CSV generator.
- `resources/views/products/index.blade.php` — product catalog view.

## Deployment Notes
- Ensure queues/workers are configured if you offload image processing (currently synchronous).
- Configure your preferred filesystem disk (local/public/S3) and update `env('APP_URL')` so generated URLs are correct.
- Run automated tests (`phpunit`) as part of your pipeline.

Enjoy building with Task A! If you have any issues, check the Troubleshooting section or inspect the services and controllers listed above.