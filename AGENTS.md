# Tonyapps_B2bQuickOrder — notes for AI agents and developers

Read this before changing the module. Workspace-wide rules are in ../AGENTS.md. B2B core context is in
../B2b/AGENTS.md.

## Status

- First release built (`tonyapps/module-b2b-quick-order` v0.1.0, proprietary). Optional addon of `Tonyapps_B2b`.
- 2026-10-30 review fixes applied. E2E: `b2b-quick-order.spec.js` (5 tests, added 2026-11-09) in the workspace
  `../e2e/tests/` Playwright suite. WORKPLAN manual QA asks to re-check Quick Order prices/options with the new
  collection loading.

## Architecture

| Piece | What |
|---|---|
| Route | `b2bquickorder` (frontend, standard router) |
| `Controller\Order\Index` (GET) | Quick Order page; extends `Tonyapps\B2b\Controller\AbstractCompanyAction` with the default `profile_view` permission (any member of an active company; B2b master switch applies) |
| `Controller\Order\Add` (POST, form key) | Reads uploaded `import_file` if present, else the `items` textarea; parses with `CsvImporter`, adds with `QuickOrderService`; success "Added %1 SKU line(s) to your cart."; shows at most 10 errors (`MAX_SHOWN_ERRORS`) plus "%1 additional line error(s) were not shown."; redirects to `checkout/cart` when `return_to_cart` (button *Add and View Cart*), else back to the page |
| `Model\CsvImporter` | `MAX_ROWS` 500, `MAX_QTY` 10000.0, `MAX_BYTES` 1048576; strips UTF-8 BOM; header optional, accepts `qty` or `quantity` (case-insensitive, any column order); headerless two-column input accepted |
| `Model\QuickOrderService::addRows()` | Validates rows (SKU non-empty, ≤ 64 chars, finite qty > 0 and ≤ `MAX_QTY`), loads all SKUs with **one product collection** (store scope, custom options, `has_stock_status_filter` so out-of-stock lines get Magento's stock error rather than "not found"), allows only `simple`/`virtual`, adds each via the checkout cart model (`addProduct`), saves the cart once. Returns `['added' => int, 'errors' => string[]]`. Reused by `Tonyapps_B2bRequisitionLists` |
| `Block\Account\QuickOrderLink` | `SortLink` in `customer_account_navigation` (sortOrder 236), rendered only if `CompanyContext::isAllowed(profile_view)` |
| Layout | `b2bquickorder_order_index.xml` (form block `cacheable="false"`, account menu), `customer_account.xml` (nav link); template `order/form.phtml` |

Error messages: `Line N: invalid SKU or quantity.`, `Line N: SKU "X" was not found.`,
`Line N: SKU "X" is not a supported simple or virtual product.`, `Line N: could not add SKU "X": <reason>`,
`The cart could not be saved: <reason>`; upload errors for failed upload, > 1 MB, non-`.csv` extension.

`module.xml` sequence: `Magento_Catalog`, `Magento_Checkout`, `Magento_Customer`, `Magento_Store`, `Tonyapps_B2b`.

## Data model

None (no schema, no config, no ACL).

## Configuration and ACL

- No `system.xml`, `config.xml` or `acl.xml`. Gated by `tonyapps_b2b/general/enabled` and B2b membership/permissions
  through `AbstractCompanyAction`.

## Decisions and constraints

- Every line goes through Magento's cart model, so normal salability checks apply. Invalid lines are reported while
  valid lines are still added.
- The normal cart and checkout stay in use, including Tonyapps_B2b payment restrictions and the optional
  B2bOrderApproval module.
- Supported product types: simple and virtual only. Configurable, bundle, grouped, downloadable and products
  requiring custom option selection are not supported in this release.
- Limits per request: 500 lines, 1 MB, quantity > 0 and ≤ 10,000.

## Known limitations and open points

- No configurable/bundle option selection (would need a product-options UI per line).
- No saved lists here — that is `Tonyapps_B2bRequisitionLists`.

## Tests

```sh
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/B2bQuickOrder/Test/Unit
```

Unit tests: `Block/Account/QuickOrderLinkTest`, `Model/CsvImporterTest`, `Model/QuickOrderServiceTest`,
`View/AccountPageLayoutTest`. No integration tests. E2E: `../e2e/tests/b2b-quick-order.spec.js`.

## Change history

### 2026-10-30 review fixes
- Quick Order page excluded from the full page cache and shows the customer account menu.
- All SKUs of a request loaded with one product collection instead of one repository load per line. Unknown SKUs
  report `Line N: SKU "X" was not found.`
- CSV header accepts `quantity` as well as `qty` (case-insensitive, any column order), e.g. `SKU,Quantity`.

### First release
- **Quick Order** link in the account navigation for active B2B company members; paste SKU/qty rows or upload a CSV
  with `sku,qty` columns (headerless two-column CSV accepted); limits and product types as above.
