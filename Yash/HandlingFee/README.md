# Yash_HandlingFee

A self-contained Magento 2 module that applies a configurable **handling fee** to the cart and order, with exemption rules, full storefront/admin display, and REST API exposure.

---

## Compatibility

| Item | Version |
|---|---|
| Magento | 2.4.x (developed against 2.4.7 CE) |
| PHP | 8.1 / 8.2 / 8.3 |
| Database | MariaDB 10.4+ / MySQL 8.0+ |
| Dependencies | `Magento_Quote`, `Magento_Sales`, `Magento_Checkout`, `Magento_Customer`, `Magento_SalesRule` |

No third-party Composer packages are required.

---

## Installation

Drop the module under `app/code/Yash/HandlingFee/`, then from the Magento root:

```bash
bin/magento module:enable Yash_HandlingFee
bin/magento setup:upgrade
bin/magento setup:di:compile         # production / default modes only
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Then configure under **Stores → Configuration → Yash Modules → Handling Fee → General Settings**:

| Setting | Default | Notes |
|---|---|---|
| Enable Handling Fee | Yes | Master switch |
| Rate (%) | `7` | Percentage applied to base subtotal |
| Subtotal Exemption Threshold | `50000` | Orders with base subtotal strictly greater than this value are exempt |
| Exempt Customer Groups | `3` (Wholesale) | Multi-select — groups exempt from the fee |
| Display Label | `Handling Fee` | Text shown on cart, checkout, order pages |

> Defaults exactly match the brief: 7%, ₹50,000 threshold, Wholesale group ID 3.

---

## Business Logic

**Fee** = `7%` of cart base subtotal

**Exemptions** (any one triggers full exemption — no zero line, no hidden text):

1. Base subtotal **strictly greater than** ₹50,000, **or**
2. Customer belongs to an exempt group (default: Wholesale)

Guest customers are treated as regular retail customers.

---

## Where It Appears

| Touchpoint | Implementation |
|---|---|
| Mini-cart | `customer-data` section enriched via `Magento\Checkout\CustomerData\Cart::afterGetSectionData` plugin; KO view + template inside the minicart subtotal container. |
| Cart page | KO component registered into `checkout.cart.totals` via `checkout_cart_index.xml`. |
| Checkout order summary | KO component registered under `checkout.sidebar.summary.totals` via `checkout_index_index.xml`. |
| Order success page | PHTML block (`Yash\HandlingFee\Block\Success\HandlingFee`) reading the last placed order from `CheckoutSession`. |
| Customer order view / print / email | `Yash\HandlingFee\Block\Order\Totals` injected into the `order_totals` parent — uses `addTotalBefore('grand_total')`. |
| Admin order view | Same totals block registered via `view/adminhtml/layout/sales_order_view.xml`. |
| Admin invoice view | Same totals block registered via `view/adminhtml/layout/sales_order_invoice_new.xml`. |
| Admin Create New Order | Dedicated block `Yash\HandlingFee\Block\Adminhtml\Order\Create\Totals\HandlingFee` registered in `sales_order_create_index.xml` and `sales_order_create_load_block_totals.xml` (AJAX reload). |

If the fee is zero or exempt, **no line item is rendered anywhere** — not even a zero-value row.

---

## REST API

All three endpoints in the brief expose the fee via **extension attributes** (no contract changes):

### `GET /V1/carts/mine/totals`

```json
{
  "grand_total": 1070.00,
  "subtotal": 1000.00,
  "extension_attributes": {
    "handling_fee": 70.00,
    "base_handling_fee": 70.00
  },
  "total_segments": [
    {
      "code": "handling_fee",
      "title": "Handling Fee",
      "value": 70.00,
      "extension_attributes": {
        "handling_fee": 70.00,
        "base_handling_fee": 70.00
      }
    },
    ...
  ]
}
```

### `POST /V1/carts/mine/payment-information`

The response includes the same totals envelope (Magento returns full totals after placing the order); the order ID is in `Location`/body, and a subsequent `GET /V1/orders/{id}` exposes:

### `GET /V1/orders/{id}`

```json
{
  "entity_id": 42,
  "grand_total": 1070.00,
  "extension_attributes": {
    "handling_fee": 70.00,
    "base_handling_fee": 70.00
  }
}
```

A Postman collection is provided at `postman/Yash_HandlingFee.postman_collection.json` covering all three endpoints, plus prerequisite steps (guest cart create, add product, set addresses, set shipping/payment method) so it runs end-to-end against a fresh install.

---

## Data Model

| Table | Columns added |
|---|---|
| `quote` | `handling_fee` (decimal 20,4), `base_handling_fee` (decimal 20,4) |
| `quote_address` | `handling_fee`, `base_handling_fee` |
| `sales_order` | `handling_fee`, `base_handling_fee` |
| `sales_invoice` | `handling_fee`, `base_handling_fee` |
| `sales_creditmemo` | `handling_fee`, `base_handling_fee` |

All columns are added via declarative schema (`etc/db_schema.xml`) — no install/upgrade scripts. The corresponding `db_schema_whitelist.json` is committed.

Quote → order conversion is wired through `etc/fieldset.xml` (`sales_convert_quote` + `sales_convert_quote_address` → aspect `to_order`) **plus** an observer on `sales_model_service_quote_submit_before` (`Observer/QuoteSubmitBefore.php`). The observer is required because `Magento\Quote\Model\Quote\Address\ToOrder::convert()` populates the order via `DataObjectHelper::populateWithArray($order, $data, OrderInterface::class)` — that helper filters the incoming array via `array_intersect_key($data, $setMethods)` against the public `OrderInterface` contract, silently dropping any field (like `handling_fee`) that isn't a declared getter. The observer fires after ToOrder runs but before the order is saved, and copies `handling_fee` / `base_handling_fee` from `quote_address` onto the order via raw `setData()`, bypassing the interface filter and matching the column names declared in `db_schema.xml`.

---

## Design

### Totals Collector

`Yash\HandlingFee\Model\Quote\Total\HandlingFee` extends `Magento\Quote\Model\Quote\Address\Total\AbstractTotal`.

- Registered in `etc/sales.xml` under `quote/totals` with sort order `710` — runs after subtotal (`100`), discount (`275`), shipping (`350`), tax (`450`), weee (`460`), grand_total registration (`700`), but before final grand-total persistence in the address.
- `collect()` resets prior values, reads the calculated fee from `Yash\HandlingFee\Model\Calculator`, and writes:
  - The fee onto `Total` (via `setTotalAmount` / `setBaseTotalAmount`) so Magento includes it in the grand total automatically.
  - The fee onto the **quote** and **quote address** so it persists on save.
- `fetch()` returns the segment (`code`, `title`, `value`) only when the fee is positive — never a zero segment. This is what makes the checkout/cart UI omit the line on exemption rather than rendering "Handling Fee: ₹0".

### Calculator

`Yash\HandlingFee\Model\Calculator` is a small, side-effect-free class that returns both base and display-currency amounts. It pulls every parameter (enabled flag, rate, threshold, exempt groups, label) from `Yash\HandlingFee\Model\Config`, which itself wraps `ScopeConfigInterface`. This keeps the collector trivially testable: stub the calculator and the collector becomes a pure data-shuffler.

### Extension Attributes Wiring

Three extension-attribute entries (`etc/extension_attributes.xml`):

- `Magento\Quote\Api\Data\TotalsInterface` — exposed via `CartTotalRepositoryPlugin::afterGet`.
- `Magento\Quote\Api\Data\TotalSegmentInterface` — populated via `TotalsConverterPlugin::afterProcess` so each segment in `total_segments` carries the value too.
- `Magento\Sales\Api\Data\OrderInterface` — populated via `OrderRepositoryPlugin::afterGet`, `::afterGetList`, and `::afterSave`.

Because the underlying columns exist on `sales_order` / `quote_address`, the framework's normal getData/setData reads work — the plugins only translate that into the extension-attribute surface area of the API contract.

### Mini-cart

The mini-cart in Luma does not consume the cart totals API; it reads `customer-data` sections. A small plugin on `Magento\Checkout\CustomerData\Cart::afterGetSectionData` adds `handling_fee`, `handling_fee_label`, and `handling_fee_applied` to the cart section payload, and a KO view-model renders it inside the minicart subtotal container.

### Recalculation

The brief calls out four recalculation triggers (cart update, qty change, coupon application/removal, customer-group change). All four are handled implicitly: any one of them invokes `Quote::collectTotals()`, which iterates the registered collectors, including ours. No additional event observers are needed. The customer-group-change case in particular is covered because Magento marks the quote as dirty when the group changes and reruns totals on the next interaction.

---

## Trade-offs and Assumptions

- **Wholesale group identifier is a config setting, not a hard-coded ID.** The brief names "Wholesale" but doesn't pin a group ID. I made this configurable (multiselect) and defaulted to group ID `3` because that is the default Wholesale group ID on a Luma sample-data install. Document this assumption with reviewers — if your Wholesale group has a different ID, update the config.
- **Currency.** The brief uses ₹50,000 for the threshold. I store the threshold in base currency (no per-currency configuration), since the brief says nothing about multi-currency stores. If the store base currency is not INR, the threshold needs to be converted by the operator.
- **Discountable.** The fee is not part of the subtotal, so Magento's coupon rules never see it — this satisfies "not discountable via standard Magento coupon rules" without any additional code.
- **Tax.** The fee is added after tax collection and is not flagged tax-applicable, so it is not tax-inclusive. If finance needs the fee to be taxable in the future, the cleanest extension is to register a tax class on the fee and add a tax-collector hook; that is out of scope here.
- **Invoices and credit memos.** Schema columns are added so future-you can extend totals collection to invoices/credit memos without another migration. For now, the fee is captured in full on the first invoice — there is no proration. If the order is partially refunded, the refund flow currently does not refund the handling fee.
- **No payment-method gating.** The brief lists exemptions for subtotal and customer group only. The fee is not skipped for any specific payment method.
- **REST `payment-information` envelope.** Magento's response to `payment-information` does not include the totals body by default in older versions; reviewers should rely on `GET /V1/carts/mine/totals` immediately before the POST, and on `GET /V1/orders/{id}` immediately after. The Postman collection follows that pattern.
- **Admin Create New Order.** The block uses `Magento\Backend\Model\Session\Quote` to read the live admin-side quote. The fee recalculates whenever the admin recollects totals (item add, qty change, etc.), via the same totals collector — no separate admin code path.

---

## Constraints Checklist

| Constraint | How it's satisfied |
|---|---|
| Custom totals collector extending `AbstractTotal` | `Model/Quote/Total/HandlingFee.php` |
| Extension attributes on API data interfaces | `etc/extension_attributes.xml` + three plugins |
| Magento service contracts, no preferences | No `<preference>` entries anywhere in `etc/di.xml` |
| Constructor injection, no ObjectManager | Verified: zero `ObjectManager` references in PHP sources |
| No files modified outside the module | Entire deliverable lives under `app/code/Yash/HandlingFee/` |
| No direct SQL | All data reads/writes via models and quote/order APIs |
| Clean install via `setup:upgrade` | Declarative schema only |

---

## Directory Layout

```
app/code/Yash/HandlingFee/
├── Block/
│   ├── Adminhtml/Order/Create/Totals/HandlingFee.php
│   ├── Order/Totals.php
│   └── Success/HandlingFee.php
├── CustomerData/CartPlugin.php
├── Model/
│   ├── Calculator.php
│   ├── Config.php
│   └── Quote/Total/HandlingFee.php
├── Observer/
│   └── QuoteSubmitBefore.php
├── Plugin/
│   ├── CartTotalRepositoryPlugin.php
│   ├── OrderRepositoryPlugin.php
│   └── TotalsConverterPlugin.php
├── etc/
│   ├── acl.xml
│   ├── adminhtml/system.xml
│   ├── config.xml
│   ├── db_schema.xml
│   ├── db_schema_whitelist.json
│   ├── di.xml
│   ├── events.xml
│   ├── extension_attributes.xml
│   ├── fieldset.xml
│   ├── module.xml
│   └── sales.xml
├── i18n/en_US.csv
├── postman/Yash_HandlingFee.postman_collection.json
├── view/
│   ├── adminhtml/...
│   └── frontend/...
├── README.md
├── composer.json
└── registration.php
```

---

## Verifying the Install

```bash
# 1. Module is registered
bin/magento module:status Yash_HandlingFee     # → Module is enabled

# 2. Schema applied
mysql -e "DESCRIBE sales_order" magento | grep handling_fee

# 3. End-to-end via API (replace BASE_URL and TOKEN)
curl -X GET "$BASE_URL/rest/V1/carts/mine/totals" \
     -H "Authorization: Bearer $TOKEN" | jq '.extension_attributes'
```

Expected output on a ₹1,000 retail cart:

```json
{ "handling_fee": 70, "base_handling_fee": 70 }
```

Expected on a ₹60,000 cart, or any cart for a Wholesale customer:

```json
null
```
