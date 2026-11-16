# B2B Quick Order for Magento 2

Let business buyers fill a cart by SKU — typed in or uploaded as a CSV — instead of browsing product by product.

Trade customers usually know exactly what they want and have the part numbers at hand. Magento Open Source has no
bulk ordering by SKU (Quick Order is an Adobe Commerce B2B feature). This add-on for B2B
Company Accounts (`tonyapps/module-b2b`) gives company members a Quick Order page that drops a whole
order into the normal cart in one step.

## Features

- **Order by SKU** — paste one `SKU, quantity` pair per line.
- **CSV upload** — upload a spreadsheet export with SKU and quantity columns; a header row (`sku,qty` or
  `SKU,Quantity`, any column order) is optional.
- **Large orders in one go** — up to 500 lines per submission.
- **Nothing lost on a typo** — valid lines go into the cart even when other lines fail, and each failed line is
  reported by line number (for example `Line 3: SKU "NOPE-123" was not found.`).
- **Your normal checkout** — products land in the regular Magento cart, so prices, stock checks, company payment
  rules and B2B Order Approval all apply as usual.
- **Company members only** — the Quick Order link appears in the account menu only for members of an active company.

## How it works

A company member opens **Quick Order** in their customer account menu, types or pastes SKUs and quantities (or picks a
CSV file) and clicks **Add to Cart** — or **Add and View Cart** to go straight to the cart. A message confirms how many
lines were added and lists the lines that could not be.

## Good to know

- Only **simple and virtual products** can be ordered this way. Configurable, bundle, grouped and downloadable
  products, and products that need custom options chosen, are not supported.
- Limits per submission: 500 lines, 1 MB CSV file, quantities from above 0 up to 10,000 per line.
- There is no admin configuration; the page is available whenever B2B Company Accounts is enabled.

## Requirements

- Magento Open Source 2.4.8
- PHP 8.3 or 8.4
- `tonyapps/module-b2b` (B2B Company Accounts)

## Installation

```sh
composer require tonyapps/module-b2b-quick-order
bin/magento setup:upgrade
```

## Configuration

No settings of its own. It follows **Stores › Configuration › Tonyapps › B2B Company Account › Enable B2B Company
Accounts**.

## For developers

Architecture, decisions, data model and tests are in [AGENTS.md](AGENTS.md).

## License

Free and open source under the [GNU General Public License v3.0 or later](LICENSE).
