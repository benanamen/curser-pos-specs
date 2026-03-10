# Consignment Store POS System — Complete Feature List

**Document Version:** 1.4  
**Date:** March 9, 2025  
**Status:** Draft for Review  
**Competitors Analyzed:** [SimpleConsign](https://www.simpleconsign.com), [ConsignCloud](https://consigncloud.com), [Ricochet](https://www.ricoconsign.com)

---

## Executive Summary

This document defines the complete feature set for a full-featured Consignment Store Point of Sale System. Features are organized by module and prioritized for development. The system must support consignment stores, vendor malls, buy-outright stores, antique malls, estate sales, and mixed-retail environments.

**Architecture:** Multi-tenant SaaS application on PostgreSQL with schema-per-tenant isolation.

---

## Architecture Overview

### Deployment Model
| Aspect | Decision |
|--------|----------|
| **Application Type** | Multi-tenant SaaS (Software as a Service) |
| **Database** | PostgreSQL |
| **Tenancy Model** | Schema-per-tenant |
| **Hosting** | Self-hosted on own Linux VM |

### Schema-per-Tenant Architecture

Each tenant (store/organization) receives a dedicated PostgreSQL schema. All tenant-specific tables exist within that schema, providing:

- **Data isolation:** Tenant data is physically separated; no cross-tenant data leakage
- **Performance:** Queries are scoped to a single schema; easier indexing and tuning per tenant
- **Compliance:** Simpler to export, migrate, or delete a single tenant's data
- **Scaling:** Can move high-volume tenants to dedicated DB instances later by schema extraction
- **Backup/restore:** Tenant-level backup and point-in-time recovery possible

**Shared (non-tenant) data** lives in a common schema, e.g.:
- Tenant registry (tenant ID, slug, status, plan)
- Global user accounts (for login/SSO)
- Tenant-user associations
- Billing/subscription metadata
- System-wide configuration

**Tenant schema** contains all business data:
- Items, consignors, sales, payouts, registers, etc.
- Each request resolves tenant from path (or JWT after login) and sets `search_path` or connection context to that schema

### Architecture Decisions (Finalized)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Tenant identification** | Path-based (MVP) | Simpler SSL, works everywhere; format: `/t/{tenant_slug}` or `/{tenant_slug}`. Subdomain optional for premium/custom domains later. |
| **Canonical tenant ID** | UUID in DB + JWT | Path/slug resolves to UUID; all queries use `tenant_id`. |
| **Signup flow** | Both self-service and admin-provisioned | Self-service for SMB; admin provisioning for enterprise, migrations, support. Shared provisioning logic. |
| **API design** | API-first | API is primary interface; web UI consumes it. Enables mobile, integrations, webhooks. Same services for API and any server-rendered views. |
| **API versioning** | From day one | e.g. `/api/v1/` to allow future changes without breaking clients. |
| **Target store size** | Multi-location from start | Support multiple store locations from MVP. |
| **Vendor mall** | Later phase | Not in MVP; Phase 2 or 3. |
| **E-commerce** | Later phase | Native in-house webstore + Shopify/Square integrations; Phase 3. |
| **Payment processor** | Stripe default, extensible | Stripe as primary; support other processors via abstraction. |
| **Pricing model** | Tiered | Lite (starter, limited consignor items) and Pro (Consignor Access, full features). |
| **Offline capability** | Later phase | Not in MVP; Phase 3 or 4. |

### Implications for Features

| Requirement | Description |
|-------------|-------------|
| Tenant provisioning | Automated schema creation and baseline data on signup (self-service or admin) |
| Tenant resolution | Resolve tenant from URL path (`/t/{slug}`) or `tenant_id` in JWT after auth |
| Schema migrations | Migrations run against all tenant schemas; new tenants get latest schema |
| Connection pooling | Dynamic schema switching via `search_path`; consider PgBouncer |
| Super-admin | Platform admin can access any tenant for support (audit required) |
| Tenant deletion | Drop schema + cleanup shared references for GDPR/data deletion |

### Local Development Environment

| Component | Choice |
|----------|--------|
| **Stack** | Laragon with Apache, MySQL, PostgreSQL, PHP 8.4 |
| **Virtual hosts** | Auto-generated; format `project-foldername.test` |
| **Example URL** | `curser-pos-specs.test` (for this project) |
| **Database** | PostgreSQL for app; MySQL available if needed for tooling |

### Testing Strategy

| Aspect | Requirement |
|--------|--------------|
| **Unit testing** | Required for all business logic |
| **Coverage target** | 100% code coverage |
| **Scope** | Services, domain logic, repositories (mocked), value objects |
| **CI** | Tests must pass before merge; coverage gate blocks merge if below 100% |
| **Framework** | PHPUnit (PSR-compliant, native PHP) |

**What to test at 100%:**
- Service layer (POS, inventory, payouts, consignor logic)
- Domain models and value objects
- Repositories (with mocked PDO/DB)
- Tenant resolution, schema context
- Commission/split calculations, tax logic, discount logic

**Exclusions (optional, document if excluded):**
- Controllers (thin; covered by integration/API tests)
- Framework bootstrap, config files
- Third-party library code

**Note:** 100% coverage ensures no untested code paths. Use mutation testing (e.g. Infection) periodically to verify tests are meaningful.

---

## 1. Point of Sale (POS)

### 1.1 Checkout & Transactions
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 1.1.1 | Barcode Scanning | Scan item barcodes for fast lookup and add-to-cart | P0 |
| 1.1.2 | Manual Item Lookup | Search items by SKU, description, consignor name, or tag number | P0 |
| 1.1.3 | Quick Add / Create on Fly | Add new items during checkout without leaving POS | P1 |
| 1.1.4 | Cart Management | Add, remove, edit quantity, clear cart | P0 |
| 1.1.5 | Split Transactions | Ring up multiple consignors/vendors in single sale with automatic split calculation | P0 |
| 1.1.6 | Hold / Suspend Sales | Hold current cart and retrieve later | P1 |
| 1.1.7 | Layaway / Payment Plans | Support partial payments and layaway tracking | P2 |
| 1.1.8 | Tax-Exempt Sales | Apply tax exemption for qualifying customers | P1 |

### 1.2 Discounts & Pricing
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 1.2.1 | Item-Level Discounts | Apply percentage or fixed-amount discount to individual items | P0 |
| 1.2.2 | Transaction-Level Discounts | Apply store-wide or sale-wide discounts | P0 |
| 1.2.3 | Consignor Approval for Discounts | Optional: require consignor approval below certain discount threshold | P2 |
| 1.2.4 | Automatic Markdown Rules | Scheduled price reductions (e.g., 30/60/90 day markdowns) | P1 |
| 1.2.5 | Promo Codes / Coupons | Apply coupon codes at checkout | P1 |
| 1.2.6 | Manager Override | Manager approval for discounts above threshold | P1 |

### 1.3 Taxes & Surcharges
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 1.3.1 | Tax by Location | Configure tax rates per store/location | P0 |
| 1.3.2 | Tax-Exempt Categories | Mark categories or items as tax-exempt | P1 |
| 1.3.3 | Surcharges | Apply surcharges (e.g., credit card fee pass-through) | P2 |
| 1.3.4 | Multi-Tax Support | Support multiple tax types (state, county, city) | P1 |

### 1.4 Payments
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 1.4.1 | Cash | Cash payments with change calculation | P0 |
| 1.4.2 | Credit/Debit Cards | Integrated card processing (Stripe, Square, or merchant provider) | P0 |
| 1.4.3 | Split Payments | Accept multiple payment methods per transaction | P0 |
| 1.4.4 | Store Credit | Apply and redeem store credit at checkout | P0 |
| 1.4.5 | Gift Cards | Sell, redeem, and check gift card balance | P0 |
| 1.4.6 | Check | Accept check payments with optional check imaging | P1 |
| 1.4.7 | External Payment Terminal | Support for external card readers (tap, chip, swipe) | P0 |
| 1.4.8 | Refunds | Full and partial refunds with original payment method preference | P0 |
| 1.4.9 | Store Credit Issuance | Issue store credit for returns | P0 |
| 1.4.10 | Void Sales | Void transactions with manager approval and audit trail | P0 |

### 1.5 Receipts & Output
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 1.5.1 | Print Receipt | Thermal or standard receipt printing | P0 |
| 1.5.2 | Email Receipt | Email receipt to customer | P0 |
| 1.5.3 | SMS/Text Receipt | Send receipt via text message | P1 |
| 1.5.4 | Custom Receipt Templates | Branded receipts with logo, store info, optional marketing | P1 |
| 1.5.5 | Receipt Options | Customer choice: print, email, text, or none | P1 |

### 1.6 Registers & Multi-Location
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 1.6.1 | Register Management | Multiple registers per location with unique IDs | P0 |
| 1.6.2 | Cash Drawer | Open/close drawer, cash drops, till reconciliation | P0 |
| 1.6.3 | End-of-Day Reconciliation | Reconcile till to expected amount, variance tracking | P0 |
| 1.6.4 | Multi-Location POS | Sell from multiple stores with synced inventory | P1 |
| 1.6.5 | User/Employee Assignment | Track which employee is on each register | P0 |
| 1.6.6 | Shift Management | Clock in/out, shift reports | P2 |

### 1.7 Virtual Terminal (VPOS)
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 1.7.1 | Phone/Remote Sales | Process sales without physical presence (manual entry) | P1 |
| 1.7.2 | Key-Enter Card | Manually enter card number for phone orders | P1 |

---

## 2. Inventory Management

### 2.1 Item Intake
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 2.1.1 | Manual Item Entry | Add items one at a time with full details | P0 |
| 2.1.2 | Batch Intake | Add multiple items in a single session (e.g., consignor drop-off) | P0 |
| 2.1.3 | CSV/Excel Import | Bulk import items from spreadsheet | P1 |
| 2.1.4 | Barcode Generation | Auto-generate or assign barcodes (UPC, Code 128, etc.) | P0 |
| 2.1.5 | Quick Intake Form | Streamlined intake for high-volume stores | P0 |
| 2.1.6 | AI-Assisted Item Entry | Optional: AI for description, category, pricing suggestions (future) | P3 |
| 2.1.7 | Photo Capture | Attach photos to items (camera or upload) | P0 |

### 2.2 Item Data & Attributes
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 2.2.1 | SKU / Tag Number | Unique identifier per item | P0 |
| 2.2.2 | Description | Item description, condition notes | P0 |
| 2.2.3 | Category | Assign to category (configurable hierarchy) | P0 |
| 2.2.4 | Brand | Brand/manufacturer field | P1 |
| 2.2.5 | Size / Variants | Size, color, or other variants | P0 |
| 2.2.6 | Condition | Condition grade (e.g., like new, good, fair) | P1 |
| 2.2.7 | Custom Tags | User-defined tags for filtering and reporting | P1 |
| 2.2.8 | Consignment Period | Track intake date, expiration/return date | P0 |
| 2.2.9 | Location / Shelf | Physical location within store | P1 |
| 2.2.10 | Cost / Commission Split | Store vs consignor split (%, fixed, or hybrid) | P0 |

### 2.3 Pricing
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 2.3.1 | Consignment Pricing | Set price with consignor split (e.g., 50/50, 60/40) | P0 |
| 2.3.2 | Buy-Outright Pricing | Cost + markup for purchased inventory | P0 |
| 2.3.3 | Retail Pricing | Standard retail (no split) | P0 |
| 2.3.4 | Flexible Splits | Per-item or per-consignor commission rules | P0 |
| 2.3.5 | Surcharges | Add surcharges (e.g., processing fee) to items | P2 |
| 2.3.6 | Price Book / Resale Merchandising | Reference pricing for similar items (e.g., eBay, Poshmark comps) | P2 |
| 2.3.7 | Bulk Price Edit | Change prices for multiple items at once | P1 |

### 2.4 Labels & Barcodes
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 2.4.1 | Label Printing | Print price tags/labels (thermal or standard) | P0 |
| 2.4.2 | Bulk Label Print | Print labels for multiple items (batch, category, consignor) | P0 |
| 2.4.3 | Custom Label Layouts | Configurable label templates (size, layout, branding) | P1 |
| 2.4.4 | Barcode on Label | Barcode printed on label for scanning at POS | P0 |
| 2.4.5 | Reprint Labels | Reprint lost or damaged labels | P0 |

### 2.5 Inventory Status & Lifecycle
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 2.5.1 | Status Workflow | Available, Sold, Returned, Expired, Picked Up, Donated, etc. | P0 |
| 2.5.2 | Expiration / Return Reminders | Notify when items expire or need pickup | P1 |
| 2.5.3 | Markdown Automation | Auto-reduce price at set intervals | P1 |
| 2.5.4 | Donate / Write-Off | Mark items as donated or written off | P1 |
| 2.5.5 | Transfer Between Locations | Move inventory between stores | P1 |
| 2.5.6 | Inventory Count / Cycle Count | Physical count reconciliation | P2 |

### 2.6 Multi-Location Inventory
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 2.6.1 | Location-Based Inventory | Track which items are at which store | P1 |
| 2.6.2 | Cross-Location Visibility | View and search inventory across all locations | P1 |
| 2.6.3 | Transfer Orders | Create and track inter-store transfers | P1 |

### 2.7 Bulk Operations
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 2.7.1 | Bulk Edit | Edit multiple items (price, category, status, etc.) | P1 |
| 2.7.2 | Bulk Status Change | Change status for multiple items | P1 |
| 2.7.3 | Bulk Delete / Archive | Remove or archive items in bulk | P2 |

---

## 3. Consignor & Vendor Management

### 3.1 Account Management
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 3.1.1 | Consignor Profiles | Name, contact info, address, phone, email | P0 |
| 3.1.2 | Vendor Profiles | Same as consignor; support vendor mall model | P0 |
| 3.1.3 | Customer Profiles | Optional customer accounts for loyalty, history | P1 |
| 3.1.4 | Account Overview | Dashboard: balance, active items, sales history | P0 |
| 3.1.5 | Balance Tracking | Running balance (sales, payouts, adjustments) | P0 |
| 3.1.6 | Manual Balance Adjustments | Add credits, debits, corrections with reason | P0 |
| 3.1.7 | Notes & Attachments | Notes, contracts, documents per account | P1 |
| 3.1.8 | Consignment Agreement | Store agreement terms, commission %, expiration policy | P0 |
| 3.1.9 | Inactive / Blacklist | Mark consignors as inactive or blocked | P1 |

### 3.2 Consignor Communication
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 3.2.1 | Email Notifications | Sale notifications, payout ready, pickup reminders | P0 |
| 3.2.2 | Automated Emails | Configurable triggers (item sold, payout processed, expiration) | P1 |
| 3.2.3 | Pickup Reminders | Remind consignors to pick up unsold/expired items | P1 |
| 3.2.4 | SMS Notifications | Optional SMS for critical alerts | P2 |
| 3.2.5 | Consignor Portal (Consignor Access) | Consignors log in to view balance, sales, payouts — Pro plan | P1 |
| 3.2.6 | Vendor Portal | Vendors log in to add inventory, set prices, view sales | P1 |

### 3.3 Consignor Scheduling (Vendor Malls)
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 3.3.1 | Intake Appointments | Schedule consignor drop-off times | P2 |
| 3.3.2 | Booth/Vendor Scheduling | Assign booth times or spaces (vendor malls) | P2 |
| 3.3.3 | Rent Collection | Track and collect booth/space rent (vendor malls) | P2 |

### 3.4 Consignor Booth Rental
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 3.4.1 | Booth Rental Tracking | Track booth/space rental fees per consignor (monthly, weekly) | P2 |
| 3.4.2 | Rental Deduction from Payout | Deduct booth rent from consignor payouts automatically | P2 |
| 3.4.3 | Booth Assignment | Assign consignors to physical booth or space | P2 |
| 3.4.4 | Rental Billing | Bill or collect booth rent separately or via payout deduction | P2 |

---

## 4. Payouts

### 4.1 Payout Methods
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 4.1.1 | Check Printing | Print checks for consignor payouts | P0 |
| 4.1.2 | ACH / Direct Deposit | Electronic payouts via ACH (e.g., Checkbook.io) | P0 |
| 4.1.3 | Cash Payouts | Record cash payouts | P0 |
| 4.1.4 | Store Credit | Issue store credit as payout option | P0 |
| 4.1.5 | Split Payout Methods | Allow consignor to choose method per payout | P1 |
| 4.1.6 | Minimum Payout Threshold | Only process when balance exceeds minimum | P1 |
| 4.1.7 | Payout Hold | Hold payouts for new consignors (e.g., 30 days) | P2 |

### 4.2 Payout Processing
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 4.2.1 | Payout Run | Generate payout batch for multiple consignors | P0 |
| 4.2.2 | Payout Report | Itemized list of sales included in payout | P0 |
| 4.2.3 | Payout History | Full history of all payouts per consignor | P0 |
| 4.2.4 | Payout Notifications | Notify consignor when payout is ready/processed | P0 |
| 4.2.5 | Void/Reverse Payout | Reverse payout with audit trail | P1 |
| 4.2.6 | Deductions | Deduct fees, rent, or other amounts from payout | P1 |
| 4.2.7 | Tax Forms (1099) | Generate or export 1099 data for consignors | P2 |

### 4.3 Accounting Integration
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 4.3.1 | QuickBooks Export | Export sales, payouts, and journal entries to QuickBooks | P1 |
| 4.3.2 | Accountant Access | Read-only or limited access for accountant | P1 |
| 4.3.3 | General Ledger Export | Export data for other accounting systems | P2 |

---

## 5. Reporting & Analytics

### 5.1 Sales Reports
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 5.1.1 | Sales Summary | Daily, weekly, monthly sales totals | P0 |
| 5.1.2 | Sales by Category | Sales breakdown by category | P0 |
| 5.1.3 | Sales by Consignor/Vendor | Revenue per consignor/vendor | P0 |
| 5.1.4 | Sales by Location | Multi-location sales comparison | P1 |
| 5.1.5 | Sales by Register/Employee | Performance by register or employee | P1 |
| 5.1.6 | Sales Trends | Time-series charts (daily, weekly, YoY) | P1 |
| 5.1.7 | Discount Report | Track discounts given | P1 |
| 5.1.8 | Refund Report | Refunds and voids | P0 |
| 5.1.9 | Tax Report | Tax collected by period | P1 |

### 5.2 Inventory Reports
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 5.2.1 | Inventory Valuation | Total inventory value (cost or retail) | P1 |
| 5.2.2 | Aging Report | Items by age (days in stock) | P1 |
| 5.2.3 | Slow-Moving Items | Items not selling | P1 |
| 5.2.4 | Top Sellers | Best-selling items, categories, brands | P1 |
| 5.2.5 | Consignor Inventory Report | Items per consignor, status | P0 |
| 5.2.6 | Expiring Soon | Items approaching expiration | P1 |
| 5.2.7 | Category Performance | Sales and turnover by category | P1 |

### 5.3 Payout Reports
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 5.3.1 | Payout Summary | Total payouts by period | P0 |
| 5.3.2 | Payout by Consignor | Payout history per consignor | P0 |
| 5.3.3 | Outstanding Balances | Consignors with balance due | P0 |
| 5.3.4 | Payout Method Report | Breakdown by check, ACH, store credit | P2 |

### 5.4 Accounting & Export
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 5.4.1 | Accounting Report | Balance entries, sales, payouts for accountant | P1 |
| 5.4.2 | Data Export | Export to CSV, Excel for backup or analysis | P0 |
| 5.4.3 | Custom Date Ranges | Run reports for any date range | P0 |
| 5.4.4 | Scheduled Reports | Email reports on schedule | P2 |
| 5.4.5 | Dashboard | Executive dashboard with key metrics | P1 |
| 5.4.6 | Profit/Margin Report | Gross profit, margin by category/consignor | P1 |

### 5.5 Audit & Compliance
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 5.5.1 | Audit Log | Log of all actions (who, what, when) | P1 |
| 5.5.2 | Void/Refund Log | Track all voids and refunds | P0 |
| 5.5.3 | User Activity Log | Actions per user | P1 |

---

## 6. E-Commerce & Integrations

### 6.1 Online Sales (Later Phase)
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 6.1.1 | Native Webstore | In-house e-commerce storefront | P2 |
| 6.1.2 | Shopify Integration | Sync inventory, orders; update on sale | P2 |
| 6.1.3 | Square Integration | Sync with Square for online/in-person | P2 |
| 6.1.4 | Online Sale Sync | When item sells online, update POS inventory and consignor balance | P2 |
| 6.1.5 | Multi-Channel Inventory | Single inventory source for POS + online | P2 |
| 6.1.6 | Product Sync | Sync item photos, descriptions, prices to online store | P2 |

### 6.2 Payment Processing
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 6.2.1 | Stripe Integration | Default card processor; primary integration | P0 |
| 6.2.2 | Processor Abstraction | Pluggable payment processor interface for alternatives | P0 |
| 6.2.3 | Square Payments | Alternative processor | P2 |
| 6.2.4 | Other Processors | Support for store's existing merchant account (Gravity, etc.) | P2 |

### 6.3 Accounting
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 6.3.1 | QuickBooks Integration | Export sales, expenses, payouts | P1 |
| 6.3.2 | Xero Integration | Alternative accounting export | P2 |
| 6.3.3 | Generic Export | CSV/Excel for any accounting system | P0 |

### 6.4 Data & Migration
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 6.4.1 | Data Import | Import consignors, items, history from CSV/Excel | P1 |
| 6.4.2 | Store Transfer / Migration | Import from competitor systems (SimpleConsign, ConsignCloud, Ricochet, etc.) | P2 |
| 6.4.3 | API / Dev Tools | REST API for custom integrations | P2 |
| 6.4.4 | Webhooks | Notify external systems on events (sale, payout) | P3 |

---

## 7. Vendor Mall Features

| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 7.1 | Centralized Checkout | Single checkout for all vendors; automatic split to vendor accounts | P1 |
| 7.2 | Vendor Payments | Payouts to vendors (same as consignor payouts) | P1 |
| 7.3 | Vendor Management | Vendor accounts, balances, inventory | P1 |
| 7.4 | Rent Collection | Monthly/weekly booth rent; deduct from payouts or separate billing | P2 |
| 7.5 | Vendor Portal | Vendors add and price their own inventory | P1 |
| 7.6 | Vendor Mall Reporting | Sales by vendor, rent collected, vendor performance | P1 |
| 7.7 | Booth/Space Assignment | Assign inventory to physical booth or space | P2 |

---

## 8. Platform, Security & Admin

### 8.1 User & Access
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 8.1.1 | User Accounts | Create staff/employee accounts | P0 |
| 8.1.2 | Role-Based Permissions | Admin, Manager, Cashier, etc. with configurable permissions | P0 |
| 8.1.3 | Login / Logout | Secure authentication | P0 |
| 8.1.4 | Password Policy | Password requirements, reset | P0 |
| 8.1.5 | Two-Factor Authentication (2FA) | Optional 2FA for sensitive roles | P2 |
| 8.1.6 | Session Management | Timeout, concurrent session limits | P1 |
| 8.1.7 | PIN for Quick Actions | PIN for manager override, drawer open | P1 |

### 8.2 Store Configuration
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 8.2.1 | Store Profile | Store name, address, phone, logo | P0 |
| 8.2.2 | Tax Configuration | Tax rates, rules per location | P0 |
| 8.2.3 | Category Management | Create, edit, reorder categories | P0 |
| 8.2.4 | Commission Defaults | Default split %, rules | P0 |
| 8.2.5 | Receipt Branding | Logo, footer text, custom receipt layout | P1 |
| 8.2.6 | Label Templates | Configure label size and layout | P1 |
| 8.2.7 | Email Templates | Customize notification emails | P1 |
| 8.2.8 | Consignment Terms | Default agreement, expiration policy | P0 |

### 8.3 Security & Compliance
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 8.3.1 | PCI Compliance | Secure card handling (tokenization, no storage of full card numbers) | P0 |
| 8.3.2 | Data Encryption | Encryption at rest and in transit | P0 |
| 8.3.3 | Backup & Recovery | Automated backups, point-in-time recovery | P0 |
| 8.3.4 | Data Retention | Configurable retention policies | P2 |
| 8.3.5 | GDPR/Privacy | Data export, deletion for EU/privacy compliance | P2 |

### 8.4 Platform
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 8.4.1 | Cloud-Based | Web app, accessible from any device | P0 |
| 8.4.2 | Responsive Design | Works on desktop, tablet, mobile | P0 |
| 8.4.3 | Offline Mode | Optional: limited POS when offline, sync when online | P2 |
| 8.4.4 | Multi-Platform | Windows, Mac, iPad, Android support | P0 |
| 8.4.5 | Browser Support | Chrome, Safari, Firefox, Edge | P0 |
| 8.4.6 | Mobile App | Optional native or PWA for mobile intake, reports | P2 |

### 8.5 Multi-Tenancy (SaaS)
| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 8.5.1 | Tenant Provisioning | Create tenant + PostgreSQL schema on signup; seed baseline data | P0 |
| 8.5.2 | Tenant Resolution | Resolve tenant from path (`/t/{slug}`) or `tenant_id` in JWT | P0 |
| 8.5.3 | Schema Migrations | Run migrations across all tenant schemas; new tenants get latest schema | P0 |
| 8.5.4 | Connection/Schema Context | Set `search_path` or equivalent per request so all queries hit correct tenant schema | P0 |
| 8.5.5 | Tenant Isolation | No cross-tenant data access; all queries scoped to tenant schema | P0 |
| 8.5.6 | Tenant Deletion | Drop tenant schema + cleanup shared references (GDPR, churn) | P1 |
| 8.5.7 | Super-Admin / Support Access | Platform admin can impersonate/access any tenant for support (audit logged) | P1 |
| 8.5.8 | Tenant Backup | Per-tenant backup and restore capability | P1 |
| 8.5.9 | Tenant Billing Context | Link tenant to subscription/plan in shared billing schema | P1 |
| 8.5.10 | Custom Subdomain | Optional: `storename.yourpos.com` or CNAME to custom domain | P2 |

---

## 9. Hardware Support

| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 9.1 | Receipt Printers | Thermal receipt printers (USB, network) | P0 |
| 9.2 | Label Printers | Thermal label printers for tags | P0 |
| 9.3 | Barcode Scanners | USB or Bluetooth barcode scanners | P0 |
| 9.4 | Cash Drawers | Cash drawer with kick-out | P0 |
| 9.5 | Card Readers | Integrated or external (Stripe, Square) | P0 |
| 9.6 | Customer Display | Optional second screen for customer | P2 |
| 9.7 | Scale | Optional for weighted items | P2 |
| 9.8 | Hardware Recommendations | Document recommended hardware (printers, scanners) | P1 |

---

## 10. Support & Onboarding

| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 10.1 | Help Center / Knowledge Base | In-app or external documentation | P1 |
| 10.2 | In-App Help | Contextual help, tooltips | P1 |
| 10.3 | Onboarding / Setup Wizard | Guided setup for new stores | P1 |
| 10.4 | Data Migration Support | Assistance importing from other systems | P2 |
| 10.5 | Training Resources | Videos, tutorials | P1 |
| 10.6 | Support Ticket System | Submit and track support requests | P1 |
| 10.7 | Status Page | System status, uptime | P2 |

---

## 11. Store Types Supported

| Store Type | Description | Core Support |
|------------|-------------|-------------|
| Consignment Store | Items on consignment; pay consignor on sale | Yes |
| Vendor Mall | Multi-vendor, centralized checkout, vendor payouts | Yes |
| Buy-Outright Store | Purchase inventory upfront; no consignor split | Yes |
| Mixed-Retail | Mix of consignment and retail in same store | Yes |
| Antique Mall | Vendor booths, collective space | Yes |
| Estate Sale | Short-term, high-volume sales | Partial |
| High-End Resale | Luxury focus, premium service | Yes |
| Online-Only | Primarily e-commerce with POS for events | Yes |
| Pop-Up / Events | Temporary locations, quick setup | Partial |
| Furniture / Large Item | Simple inventory, large items | Yes |
| Gift Shop | Local makers, consignment mix | Yes |

---

## 12. Priority Legend

| Priority | Meaning |
|----------|---------|
| **P0** | Must-have for MVP; core to consignment POS |
| **P1** | High priority; needed for competitive parity |
| **P2** | Important; differentiator or common request |
| **P3** | Nice-to-have; future enhancement |

---

## 13. Suggested Development Phases

### Phase 1 — MVP (P0 Features)
- **Testing:** Unit tests with 100% coverage for all business logic (PHPUnit)
- **Multi-tenancy:** Tenant provisioning, schema-per-tenant, tenant resolution, schema migrations
- **Multi-location:** Support multiple store locations from start
- **Payment:** Stripe as default; processor abstraction for alternatives
- Core POS: checkout, payments, receipts, discounts, taxes
- Inventory: intake, pricing, labels, status
- Consignor management: profiles, balance, agreements
- Payouts: check, cash, store credit
- Basic reporting: sales, payouts, inventory
- User accounts, roles, store config
- Hardware: printers, scanners, drawer
- **Pricing tiers:** Lite (limited items) and Pro (full features) plan logic

### Phase 2 — Competitive Parity (P1 Features)
- ACH payouts
- Consignor portal (Consignor Access) — Pro plan
- Advanced reporting, dashboard
- QuickBooks export
- Bulk operations, CSV import
- Virtual terminal

### Phase 3 — E-Commerce & Vendor Mall
- E-commerce integrations (Shopify, Square)
- Vendor mall features
- Vendor portal
- Rent collection
- Consignor booth rental
- API, webhooks
- Store migration tools
- Advanced automation (markdowns, emails)
- 2FA, audit enhancements

### Phase 4 — Future (P3 Features)
- AI-assisted item entry
- Native mobile app
- Offline mode
- Additional integrations

---

## 14. User Stories

User stories follow the format: **As a** [role], **I want** [goal] **so that** [benefit].

### Authentication & Tenancy

| ID | Story | Priority |
|----|-------|----------|
| US-1 | As a **store owner**, I want to sign up and create my store (self-service) so that I can start using the POS without a sales call |
| US-2 | As a **platform admin**, I want to provision a tenant for a new customer so that I can onboard enterprise or migrated stores |
| US-3 | As a **store employee**, I want to log in and be taken to my store's dashboard so that I can start working |
| US-4 | As a **store owner**, I want my store URL to be predictable (e.g. `app.com/t/mystore`) so that I can bookmark or share it |
| US-5 | As a **consignor**, I want to log in to a portal to see my balance and sales so that I don't need to call the store |

### Point of Sale

| ID | Story | Priority |
|----|-------|----------|
| US-6 | As a **cashier**, I want to scan a barcode or search by tag to add items to cart so that checkout is fast |
| US-7 | As a **cashier**, I want to apply discounts and accept split payments (cash + card) so that I can complete any sale |
| US-8 | As a **cashier**, I want to print or email a receipt so that the customer has proof of purchase |
| US-9 | As a **manager**, I want to void a sale with my PIN so that I can correct mistakes |
| US-10 | As a **cashier**, I want to redeem store credit and gift cards so that I can complete returns or loyalty redemptions |
| US-11 | As a **cashier**, I want to hold a sale and retrieve it later so that I can serve another customer |
| US-12 | As a **manager**, I want to reconcile my till at end of day so that I know cash and variance |

### Inventory

| ID | Story | Priority |
|----|-------|----------|
| US-13 | As a **store employee**, I want to add items for a consignor with price and split so that I can intake new inventory |
| US-14 | As a **store employee**, I want to print labels with barcodes for items so that I can tag and scan at checkout |
| US-15 | As a **store owner**, I want to see items by status (available, sold, expired) so that I can manage inventory |
| US-16 | As a **store employee**, I want to bulk import items from CSV so that I can onboard large consignor drops |
| US-17 | As a **store owner**, I want automatic markdowns (e.g. 30/60/90 days) so that slow-moving items move |
| US-18 | As a **store employee**, I want to search items by consignor, category, or SKU so that I can find anything quickly |

### Consignors & Payouts

| ID | Story | Priority |
|----|-------|----------|
| US-19 | As a **store owner**, I want to create consignor profiles with contact info and agreement so that I can track who owns what |
| US-20 | As a **store owner**, I want to run a payout batch and print checks or send ACH so that consignors get paid |
| US-21 | As a **store owner**, I want to see consignor balances and payout history so that I can answer questions |
| US-22 | As a **consignor**, I want to receive an email when my item sells so that I know my balance |
| US-23 | As a **consignor**, I want to receive an email when my payout is ready so that I can pick it up or expect ACH |
| US-24 | As a **store owner**, I want to set a minimum payout threshold so that I don't write tiny checks |

### Reporting

| ID | Story | Priority |
|----|-------|----------|
| US-25 | As a **store owner**, I want a daily sales summary so that I know how the day went |
| US-26 | As a **store owner**, I want to see sales by category and consignor so that I can spot trends |
| US-27 | As a **store owner**, I want to export data to CSV/Excel so that I can send to my accountant |
| US-28 | As a **store owner**, I want to see an audit log of voids and refunds so that I can monitor for abuse |
| US-29 | As an **accountant**, I want read-only access to sales and payout data so that I can reconcile without store owner |

### Integrations & API

| ID | Story | Priority |
|----|-------|----------|
| US-30 | As a **store owner**, I want to sync inventory with Shopify so that online sales update my POS |
| US-31 | As a **developer**, I want a REST API so that I can build custom integrations or mobile apps |
| US-32 | As a **store owner**, I want to export to QuickBooks so that I don't double-enter data |
| US-33 | As a **webhook consumer**, I want to receive events when a sale occurs so that I can update external systems |

### Vendor Mall (Phase 2+)

| ID | Story | Priority |
|----|-------|----------|
| US-34 | As a **vendor mall operator**, I want centralized checkout with automatic vendor splits so that I can ring up any vendor's item |
| US-35 | As a **vendor**, I want to log in and add my own inventory so that I can manage my booth |
| US-36 | As a **vendor mall operator**, I want to collect booth rent and deduct from payouts so that I can bill vendors |
| US-42 | As a **store owner**, I want to charge consignors for booth rental and deduct it from payouts so that I can monetize booth space |

### Platform & Admin

| ID | Story | Priority |
|----|-------|----------|
| US-37 | As a **store owner**, I want to assign roles (admin, manager, cashier) so that I can limit what employees can do |
| US-38 | As a **platform admin**, I want to impersonate a tenant for support so that I can debug issues |
| US-39 | As a **store owner**, I want to configure tax rates and receipt branding so that my store matches my brand |
| US-40 | As a **store owner**, I want to delete my data so that I can comply with GDPR or cancel |
| US-41 | As a **developer**, I want unit tests with 100% coverage so that I can refactor confidently and catch regressions |

---

## 15. Pricing Tiers (Planned)

| Tier | Target | Key Limits | Pro Features |
|------|--------|------------|--------------|
| **Lite** | Starter stores | 50 active consignor items max | Core POS, inventory, payouts, basic reporting |
| **Pro** | Growing stores | Unlimited items | Consignor Access (portal), multi-location, advanced reporting, booth rental, vendor mall |

Tier enforced at tenant level; feature flags or plan checks gate Pro features.

---

## 16. Production Environment (Linux VM)

| Component | Choice |
|----------|--------|
| **Deployment** | Bare metal |
| **Server management** | Plesk |
| **Web server** | Apache |
| **Database** | PostgreSQL |
| **PHP** | PHP 8.4 (FPM or mod_php) |
| **SSL** | Let's Encrypt with Certbot auto-renewal |
| **Access** | Root access; full control over stack |
| **E-commerce (later phase)** | Both native in-house webstore and Shopify/Square integrations |

---

## 17. Open Questions for Review

1. **Domain:** Single domain with path-based tenancy, or wildcard subdomain for future?

---

## Appendix A: Competitor Feature Comparison (Summary)

| Feature Area | SimpleConsign | ConsignCloud | Ricochet |
|--------------|---------------|--------------|----------|
| POS | ✓ | ✓ | ✓ |
| Multi-location | ✓ | ✓ | ✓ |
| Consignor Portal | ✓ (ConsignorAccess) | ✓ | ✓ (Ricochet GO) |
| Vendor Mall | ✓ | ✓ | ✓ |
| Shopify | ✓ | ✓ | ✓ |
| Square | — | ✓ | — |
| QuickBooks | ✓ | — | ✓ |
| ACH Payouts | ✓ | ✓ (Checkbook) | ✓ |
| Virtual Terminal | ✓ | — | — |
| AI Item Entry | ✓ | — | — |
| Built-in Webstore | — | — | ✓ (Ricochet Web) |
| API/Dev Tools | — | ✓ | — |
| Pricing | ~$99/mo+ | $139–189/mo | $199/mo flat |

---

*End of Feature List. Ready for review and refinement into formal requirements document.*
