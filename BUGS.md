# BUGS / KNOWN ISSUES

## 1. Sally email search anomaly

- **Summary**: Searching for the consignor with email `sally5@example.com` does not return a match in the web UI, while other similar addresses (for example `sally3@example.com`) work as expected.
- **Scope**:
  - Backend API (`GET /t/{slug}/api/v1/consignors`) correctly returns this consignor, and unit tests for `ConsignorController::list()` confirm that both `calvin@example.com` and `sally5@example.com` are present in the JSON response.
  - The anomaly is specific to the **web-layer search filter** in `curser-pos-web/public/index.php` and/or to the stored data for this record.
- **Suspected root cause**:
  - The persisted email value likely contains a non‑ASCII character where a `5` is expected (for example a full‑width digit or similar Unicode look‑alike). This would make the email visually appear as `sally5@example.com` in HTML, but string search using `str_contains('sally５@example.com', 'sally5@example.com')` will not match.
  - Alternatively, invisible Unicode whitespace or normalization differences could be present despite trimming.
- **Impact**:
  - User cannot reliably find this specific consignor using the email search box in the web app, even though the API returns the record.
  - Other email addresses without such characters work normally.
- **Proposed fix (later cleanup)**:
  1. **Data normalization**:
     - Add a one‑time migration or maintenance script to normalize existing email values to a strict ASCII subset (e.g. reject or convert full‑width digits and other non‑standard characters).
  2. **Input validation**:
     - Tighten email validation on create/update so that only valid ASCII email characters are accepted (or normalize on write), preventing new malformed values from being stored.
  3. **Optional defensive search**:
     - As an extra safeguard, consider normalizing both the stored value and the search query through a common normalization function (e.g. Unicode NFKC + digit mapping) before comparison, while still keeping stored emails canonicalized.

## 2. POS quantity model vs stock-style items

- **Summary**: The current data model for `items` assumes one row per physical item (unique `sku`, no quantity column). This works well for consignment pieces (each is unique) but does not naturally support stock‑style retail items (e.g. 5 identical sodas) on a single cart line with editable quantity.
- **Scope**:
  - Database: `items` table has no `quantity` field and enforces a unique `sku`.
  - POS cart: each cart line currently represents a single `item_id` + quantity, and we constrain quantity to 1 to avoid selling more units than exist for that item.
- **Impact**:
  - Retail scenarios (multiple identical units in one sale) either:
    - Require one cart line per physical unit (visually noisy), or
    - Need a more sophisticated “stock item” concept that is not yet implemented.
- **Proposed future enhancement**:
  - Introduce a **stock/inventory item model** that supports:
    - A quantity field (on‑hand stock),
    - Relaxed SKU uniqueness for stock items or a separate stock table keyed by SKU,
    - POS cart lines that reference a stock item with a multi‑unit quantity, and
    - Correct decrementation of stock on checkout and reporting.
  - Keep the existing one‑row‑per‑consignment‑item model for traditional consignment pieces, and make clear in the UI which items are stock vs one‑off consignment.

