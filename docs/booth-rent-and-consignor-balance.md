# Booth Rent, Consignor Share, and Payout Policy

This document explains how Perfect Consign currently models consignor money flows (sales, balances, payouts, and booth rent), and outlines the key business policy choices you can make around booth rent for low‑performing consignors.

It is intended as a discussion aid for store owners/tenants and implementers.

---

## 1. Concepts and Data Model

At a high level, the system tracks three related but distinct money concepts:

- **Consignor share (per sale item)** – how much of a sale belongs to a consignor for that item.
- **Consignor balance** – what the store currently owes (or, if allowed, is owed by) a consignor overall.
- **Booth rent** – a periodic charge for vendors who rent physical booth space.

### 1.1 Consignor share and “Total consignor share”

Each sale item row stores:

- `store_share` – the store’s portion of that sale line.
- `consignor_share` – the consignor’s portion of that sale line.

For the Vendor‑mall report, the backend aggregates consignor share across completed sales:

- **Total consignor share** (per consignor, over a date range) = sum of `consignor_share` for that consignor’s sale items where the sale status is `completed`.

This answers the question:

> “How much did this consignor earn from sales during this period?”

Important: **Total consignor share is a period performance metric.** It does not, by itself, say whether that money has already been paid out or is still owed.

### 1.2 Consignor balances: owed vs paid

The system maintains a separate **consignor balance** record, modeled by `ConsignorBalance` and accessed via `ConsignorService::getBalance()`:

- `balance` – current net amount the store **owes to the consignor**.
- `pending_sales` – sales that are not yet fully settled into the balance (if used).
-,paid_out` – total amount that has been **paid out historically** to the consignor.

In the API and web UI, a consignor’s balance fields appear as:

- **Balance** – “what the store currently owes this consignor.”
- **Pending sales** – sales still in a pending or unsettled state.
- **Paid out** – cumulative payouts already made to this consignor.

So, conceptually:

- **What is owed *now*?** → `balance`
- **What has been *already paid*?** → `paid_out`
- **What did they *earn in this period*?** → `Total consignor share` on the Vendor‑mall report

These numbers are related, but each serves a different purpose.

### 1.3 Booth rent

For consignors who rent booths, the system tracks:

- **Rent due** for a given period (e.g., a monthly booth charge).
- **Rent deductions** – amounts of rent that have been satisfied (typically via payout runs).

`BoothRentalService` and `RentDeductionRepository` encapsulate this logic. At a high level:

- `BoothRentalService::getRentDue(consignor_id)` returns:
  - `amount` – rent owed for the relevant period.
  - `period_start`, `period_end` – the period covered.
- `RentDeductionRepository` stores which portions of rent have already been deducted (collected) from consignor balances or payouts.

The Vendor‑mall report uses this to compute:

- **Rent collected** – sum of rent deductions that have been recorded during the chosen date range.

---

## 2. How Payouts and Rent Interact Today

When you run a payout run for consignors, the flow for booth renters looks like this:

1. Fetch the consignor’s **current balance** via `ConsignorService::getBalance(consignor_id)`.
2. Ask `BoothRentalService::getRentDue(consignor_id)` for the current booth rent due.
3. Compute:

   \[
   \text{payout\_amount} = \text{balance} - \text{rent\_amount}
   \]

4. Compare `payout_amount` to a configured **minimum payout amount**:

   - If `payout_amount` is **below** the minimum:
     - The consignor is **skipped** for this run:
       - No payout is created.
       - No rent deduction is recorded.
       - Their `balance` remains unchanged.
   - If `payout_amount` is **greater than or equal** to the minimum:
     - A payout record is created for `payout_amount` (what the consignor will receive).
     - A rent deduction is recorded for `rent_amount`.
     - The consignor’s balance is updated by `ConsignorService`:
       - `deductForPayout()` if no rent is due.
       - `deductForPayoutAndRent()` if rent is due.

In the rent‑due case, `deductForPayoutAndRent()` effectively does:

- New balance = old balance − (payout_amount + rent_amount)
- New paid_out = old paid_out + payout_amount

So, when balance is high enough:

- The consignor’s **rent is paid** (via a rent deduction).
- The consignor receives **net payout** equal to `payout_amount`.
- The store’s liability to that consignor (balance) is reduced accordingly.

When balance is not high enough (and/or the minimum payout is not met), the system **does not automatically charge rent** in that run and leaves the balance and rent state unchanged.

---

## 3. Business Policy Choices Around Booth Rent

The main policy decision is how strictly you want to enforce rent, particularly when vendors have poor sales.

### 3.1 Option A – Rent is effectively conditional on sales

**Concept:**

- Rent is collected **from consignor balance** as part of payout runs, but only when there is enough balance to cover it and the payout threshold is met.
- If a consignor has low or no sales, and therefore an insufficient balance, their rent is not automatically deducted in the system.

**Pros:**

- Consignor‑friendly in slow periods – you are less likely to chase vendors for cash when they have no sales.
- Avoids large negative balances for consignors in the ledger.
- Keeps the focus of the system on “money flowing out to consignors,” not on collections.

**Cons:**

- The store may effectively **subsidize booth rent** when sales are low.
- The fact that rent is “owed in principle” but not collected must be tracked **outside** the system (manual notes, invoices, etc.).
- It may be unclear which tenants are chronically under‑performing and not actually covering their rent.

This option largely matches the bias of the current behavior: rent is deducted as part of successful payout runs, but not forced when balances are low.

### 3.2 Option B – Rent is due regardless of sales

**Concept:**

- Booth rent is treated as a **fixed obligation** for each period, independent of consignor sales.
- For each rent period (e.g. monthly), the system should:
  - Post the **full rent amount** as due.
  - Adjust the consignor’s position, even if that means they now owe the store money.

**Operational behavior (conceptual):**

- For a given period:
  - Calculate rent due, e.g., \$200 for the month.
  - Apply this rent to the consignor’s account:
    - If the consignor’s balance is \$300:
      - Rent is deducted, balance becomes \$100.
      - A payout run may then pay some or all of that \$100.
    - If the consignor’s balance is \$50:
      - Rent is still posted at \$200.
      - Net position becomes **−\$150** (the consignor now owes the store).

- For future periods:
  - New consignor share from sales will first reduce the **negative balance / rent arrears**.
  - Only after the balance returns to zero (or positive) does the consignor become eligible for payouts again.

**Pros:**

- Closely matches real‑world rent expectations: “the booth costs money every month, whether you sell or not.”
- The system can explicitly show:
  - Who owes rent.
  - How much rent is outstanding.
- You can produce clear reports for:
  - Vendors in good standing.
  - Vendors behind on rent.

**Cons:**

- Requires comfort with **negative balances** for consignors, meaning “this consignor owes the store.”
- May require explicit collection processes for rent arrears (invoicing, off‑system payments, etc.).
- Can feel harsh for vendors with extended slow periods unless mitigated by discounts, caps, or grace rules.

This option matches a “real estate first” mindset: the booth is a fixed cost that the consignor is expected to cover regardless of sales performance.

---

## 4. Questions to Clarify with Clients

Before changing behavior or UI, it is important to clarify the following with store owners/tenants:

1. **Rent enforcement philosophy**
   - If a vendor’s sales for a month are less than the booth rent, do you expect to:
     - Still charge the full rent every time?
     - Only charge up to the amount of their sales?
     - Sometimes forgive or adjust rent manually?

2. **Negative balances and arrears**
   - Are you comfortable with the system showing **negative consignor balances** (vendor owes the store), or should balances only ever represent what the store owes the consignor?
   - If negative balances are allowed, should there be a dedicated UI/report that highlights “vendors owing rent”?

3. **Operational handling of rent arrears**
   - If the system records that a consignor owes \$X in unpaid rent:
     - Will you invoice them separately?
     - Expect them to pay in cash/card?
     - Allow it to roll forward and be repaid from future sales automatically?

4. **Reporting and communication needs**
   - For each consignor, what do you want them (and you) to see?
     - Earnings for a period (Total consignor share).
     - Rent charged for that period.
     - Payouts made.
     - Net balance (positive = we owe them; negative = they owe us).
   - Do you want periodic statements that summarize these items clearly?

---

## 5. Suggested Direction and Next Steps

From a product perspective, both policies (A and B) are viable; the choice depends on what is fair and practical for your particular stores and vendors.

If stores lean toward **“rent is due regardless of sales”**:

- The system should:
  - Explicitly support **negative consignor balances**, or maintain a parallel “rent receivable” ledger.
  - Treat booth rent as a **first‑class, periodic charge** that is always posted.
  - Ensure payout logic:
    - Applies rent charges for the period.
    - Only pays cash out when the resulting balance and minimum payout rules allow it.
  - Provide clear UI and reports for:
    - Current balance (positive or negative).
    - Rent due/charged by period.
    - Payouts executed.
    - Total consignor share (earnings) over selected ranges.

If stores prefer **“rent only from sales”**:

- The existing pattern (rent deducted only when there is sufficient balance in a payout run) is closer to their expectations, but should be:
  - Documented clearly.
  - Supported with UI hints, so it is obvious when rent was not collected because of insufficient balance.
  - Potentially complemented with off‑system or custom workflows (e.g. manual invoicing) for when they do want to pursue rent separately.

This document is meant as a starting point for those conversations. Once clients have chosen the desired policy, implementation details (schema, API, and UI behavior) can be updated to align with that decision.

