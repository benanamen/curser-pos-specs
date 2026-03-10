# Curser POS - Consignment Store Point of Sale

Multi-tenant SaaS POS system for consignment stores. Phase 1 MVP + Phase 2 (Competitive Parity) features.

## Requirements

- PHP 8.4+
- PostgreSQL (with `pdo_pgsql` extension enabled)
- Composer

## Setup

1. **Install dependencies**
   ```bash
   composer install
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

3. **Run migrations**
   ```bash
   # Create database first: createdb curser_pos
   php bin/migrate.php shared
   # For each existing tenant schema (Phase 2): php bin/migrate.php tenant tenant_<uuid>
   ```

4. **Start development server**
   ```bash
   cd public && php -S localhost:8000
   ```
   Or use Laragon: the project will be available at `curser-pos-specs.test`

## API Endpoints

### Auth & Health
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/health` | Global health check (no tenant) |
| GET | `/t/{slug}/api/v1/health` | Tenant-scoped health check |
| POST | `/api/v1/signup` | Self-service signup (creates user + tenant) |
| POST | `/api/v1/auth/login` | Login |
| POST | `/api/v1/auth/logout` | Logout |
| GET | `/t/{slug}/api/v1/me` | Current user + tenant context (auth required) |

### Store Config (Sprint 2)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/store/config` | Get store config (tax, commission, terms) |
| PUT | `/t/{slug}/api/v1/store/config` | Update store config |

### Locations
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/locations` | List locations |
| GET | `/t/{slug}/api/v1/locations/{id}` | Get location |
| POST | `/t/{slug}/api/v1/locations` | Create location |
| PUT | `/t/{slug}/api/v1/locations/{id}` | Update location |
| DELETE | `/t/{slug}/api/v1/locations/{id}` | Delete location |

### Categories
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/categories` | List categories |
| GET | `/t/{slug}/api/v1/categories/{id}` | Get category |
| POST | `/t/{slug}/api/v1/categories` | Create category |
| PUT | `/t/{slug}/api/v1/categories/{id}` | Update category |
| DELETE | `/t/{slug}/api/v1/categories/{id}` | Delete category |

### Consignors
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/consignors` | List consignors (with balance) |
| GET | `/t/{slug}/api/v1/consignors/{id}` | Get consignor (with balance) |
| POST | `/t/{slug}/api/v1/consignors` | Create consignor |
| PUT | `/t/{slug}/api/v1/consignors/{id}` | Update consignor |
| POST | `/t/{slug}/api/v1/consignors/{id}/balance-adjustment` | Manual balance adjustment |
| POST | `/t/{slug}/api/v1/consignors/{id}/portal-token` | Generate consignor portal token (Pro plan) |
| POST | `/t/{slug}/api/v1/consignors/import` | Bulk import consignors from CSV (body = CSV; columns: slug, name, email, phone, address, default_commission_pct) |

### Items (Inventory)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/items` | List/search items |
| GET | `/t/{slug}/api/v1/items/{id}` | Get item |
| GET | `/t/{slug}/api/v1/items/lookup/barcode?barcode=` | Lookup by barcode |
| GET | `/t/{slug}/api/v1/items/lookup/sku?sku=` | Lookup by SKU |
| POST | `/t/{slug}/api/v1/items` | Create item |
| PUT | `/t/{slug}/api/v1/items/{id}` | Update item |
| PUT | `/t/{slug}/api/v1/items/{id}/status` | Update item status |
| GET | `/t/{slug}/api/v1/items/{id}/label` | Get label data |
| POST | `/t/{slug}/api/v1/items/import` | Bulk import items from CSV (body = CSV) |
| PUT/POST | `/t/{slug}/api/v1/items/bulk-status` | Bulk update status (body: `item_ids`, `status`) |
| PUT/POST | `/t/{slug}/api/v1/items/bulk-price` | Bulk update prices (body: `updates`: [{ id, price }]) |

### POS
| Method | Path | Description |
|--------|------|-------------|
| POST | `/t/{slug}/api/v1/pos/checkout` | Complete sale (cart + payments). Body: `cart`, `payments`, optional `held_id`, `tax_exempt`, `discount_amount`, `tax_amount`. Payments may include `method: "store_credit"` with `store_credit_id`, or `method: "gift_card"` with `gift_card_id`. |
| POST | `/t/{slug}/api/v1/pos/hold` | Hold current cart (body: `cart`, `payments`) |
| GET | `/t/{slug}/api/v1/pos/held` | List current user's held sales |
| GET | `/t/{slug}/api/v1/pos/held/{id}` | Get held sale by id |
| GET | `/t/{slug}/api/v1/pos/sales` | List sales (?from=&to=&register_id=&limit=) |
| GET | `/t/{slug}/api/v1/pos/sales/{id}` | Get sale/receipt |
| POST | `/t/{slug}/api/v1/pos/sales/{id}/refund` | Full refund (reverses items and consignor share) |
| POST | `/t/{slug}/api/v1/pos/sales/{id}/receipt/email` | Email receipt (stub; body: `email`) |
| POST | `/t/{slug}/api/v1/pos/sales/{id}/void` | Void sale |
| POST | `/t/{slug}/api/v1/pos/charge-card` | Virtual terminal: keyed card sale (body: `amount`, `payment_method_id`) |

### Store credit & Gift cards
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/store-credits?consignor_id=` | List store credits for consignor |
| GET | `/t/{slug}/api/v1/store-credits/{id}` | Get store credit balance |
| POST | `/t/{slug}/api/v1/store-credits` | Issue store credit (body: `consignor_id?`, `amount`) |
| GET | `/t/{slug}/api/v1/gift-cards/lookup?code=` | Lookup gift card by code (balance) |
| GET | `/t/{slug}/api/v1/gift-cards/{id}` | Get gift card |
| POST | `/t/{slug}/api/v1/gift-cards` | Create/sell gift card (body: `code`, `amount`) |

### Registers
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/registers` | List registers |
| POST | `/t/{slug}/api/v1/registers` | Create register |
| POST | `/t/{slug}/api/v1/registers/{id}/open` | Open drawer (body: `opening_cash?`) |
| POST | `/t/{slug}/api/v1/registers/{id}/close` | Close drawer (body: `closing_cash`) |
| POST | `/t/{slug}/api/v1/registers/{id}/cash-drop` | Record cash drop (body: `amount`) |
| GET | `/t/{slug}/api/v1/registers/{id}/summary` | Register session summary (sales, cash drops) |

### Payouts
| Method | Path | Description |
|--------|------|-------------|
| POST | `/t/{slug}/api/v1/payouts/run` | Run payout batch (deducts booth rent when applicable; response includes `rent_deducted` per payout) |
| GET | `/t/{slug}/api/v1/consignors/{id}/payouts` | List payouts for consignor |

### Vendor Mall (Booths & Rent)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/booths` | List booths (?status=active) |
| GET | `/t/{slug}/api/v1/booths/{id}` | Get booth |
| POST | `/t/{slug}/api/v1/booths` | Create booth (name, location_id?, monthly_rent) |
| PUT | `/t/{slug}/api/v1/booths/{id}` | Update booth |
| POST | `/t/{slug}/api/v1/consignors/{id}/booth-assignment` | Assign consignor to booth (body: booth_id, monthly_rent?, started_at?) |
| POST | `/t/{slug}/api/v1/consignors/{id}/booth-assignment/end` | End booth assignment (body: ended_at?) |
| GET | `/t/{slug}/api/v1/consignors/{id}/rent-due` | Get rent due for consignor (vendor with booth) |
| GET | `/t/{slug}/api/v1/consignors/{id}/rent-deductions` | List rent deductions for consignor |
| GET | `/t/{slug}/api/v1/reports/vendor-mall` | Vendor mall report: sales_by_vendor, rent_collected, vendors_with_booths, active_booths (?from=&to=) |

### Dashboard & Reports
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/dashboard` | Dashboard metrics (sales today/week, inventory count, consignors) |
| GET | `/t/{slug}/api/v1/reports/sales/summary` | Sales summary (optional ?from=&to=) |
| GET | `/t/{slug}/api/v1/reports/sales/by-consignor` | Sales by consignor |
| GET | `/t/{slug}/api/v1/reports/inventory/summary` | Inventory by status |
| GET | `/t/{slug}/api/v1/reports/payouts/summary` | Payouts summary |
| GET | `/t/{slug}/api/v1/reports/sales/export` | Export sales CSV |
| GET | `/t/{slug}/api/v1/reports/quickbooks/sales` | QuickBooks-friendly sales CSV (?from=&to=) |
| GET | `/t/{slug}/api/v1/reports/quickbooks/payouts` | QuickBooks-friendly payouts CSV (?from=&to=) |

### Consignor Portal (Pro plan)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/t/{slug}/api/v1/portal/me?token=` | Consignor portal: balance, recent sales, payouts (auth: query `token` or header `X-Consignor-Portal-Token`) |

## Signup Request Body

```json
{
  "email": "owner@store.com",
  "password": "password123",
  "store_name": "My Consignment Store",
  "store_slug": "mystore"
}
```

## OpenAPI / API Testing

An OpenAPI 3.0 specification is available at `openapi.yaml`. Use it with tools like:

- **Swagger UI**: Load `openapi.yaml` in [Swagger Editor](https://editor.swagger.io/) or run a local Swagger UI container
- **Postman**: Import the OpenAPI file
- **Insomnia**: Import the OpenAPI file
- **curl**: Follow the request/response schemas in the spec

Base URL for local dev: `http://localhost:8000` or `https://curser-pos-specs.test`

## Testing

```bash
php vendor/bin/phpunit
```

With coverage:

```bash
php vendor/bin/phpunit --coverage-text
```

## Project Structure

- `src/` - Application code (PSR-4)
- `config/` - Configuration
- `migrations/shared/` - Shared schema migrations
- `migrations/tenant/` - Per-tenant schema migrations
- `public/` - Web root
- `bin/migrate.php` - Migration runner
