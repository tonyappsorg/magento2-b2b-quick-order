# Tonyapps_B2bQuickOrder

Optional SKU and CSV quick ordering for Tonyapps_B2b company accounts on Magento Open Source.

## First release behavior

- Adds a **Quick Order** link to the customer account navigation for active B2B company members.
- Buyers can paste SKU/quantity rows or upload a CSV containing `sku,qty` columns. Headerless two-column CSV is also accepted.
- Up to 500 lines and 1 MB are accepted per request. Quantities must be positive and no greater than 10,000.
- Simple and virtual products are supported. Configurable, bundle, grouped, downloadable, and products requiring custom option selection are not supported in this release.
- Every line is added through Magento's cart model, which applies normal salability checks. Invalid lines are reported while valid lines are still added.
- The normal cart and checkout remain in use, including Tonyapps_B2b payment restrictions and the optional B2B order approval module.

## Install

Install `tonyapps/module-b2b-quick-order` with `tonyapps/module-b2b`, then run `bin/magento setup:upgrade`.

## Tests

Run the focused unit suite from the Magento installation root with `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/B2bQuickOrder/Test/Unit`.
