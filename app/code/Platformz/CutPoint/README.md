## Platformz_CutPoint (Magento 2.4.7-p8)

Per-product **Manufacturer → Model → Size** selection that derives fixed **Cut Point** and **Additional Info** from a
mapping table, stores the selection on the quote item, and exposes everything via GraphQL.

### What this module adds

- **DB table**: `platformz_cutpoint_matrix` keyed by `(product_id, manufacturer, model, size)`
- **Admin import**: Catalog → CutPoint Matrix → Import (upload CSV/XLSX per product SKU)
- **Product GraphQL**: `ProductInterface.cutpoint_options` returns dependent options for UI dropdowns
- **Cart add GraphQL**: extends `CartItemInput` with `manufacture_selection`
- **Persistence**: saves selection as quote item `additional_options` (flows to order/invoice/email automatically)
- **Cart GraphQL**: `CartItemInterface.cutpoint_info` reads the saved selection

### GraphQL usage

#### 1) Fetch options for a product

Query `cutpoint_options` from a product:

```graphql
query($sku: String!) {
  products(filter: { sku: { eq: $sku } }) {
    items {
      sku
      cutpoint_options {
        manufacturer
        models {
          model
          sizes {
            size
            cut_point
            additional_info
          }
        }
      }
    }
  }
}
```

#### 2) Add to cart with manufacture selection (default mutation extended)

```graphql
mutation($cartId: String!) {
  addProductsToCart(
    cartId: $cartId
    cartItems: [
      {
        sku: "SKU-123"
        quantity: 1
        manufacture_selection: { manufacturer: "Eclipse", model: "Astra", size: "7 1/4" }
      }
    ]
  ) {
    cart {
      itemsV2 {
        items {
          uid
          product {
            sku
          }
          cutpoint_info {
            manufacturer
            model
            size
            cut_point
            additional_info
          }
        }
      }
    }
    user_errors {
      code
      message
    }
  }
}
```

### How it works (high level)

- GraphQL plugin converts `manufacture_selection` into `entered_options` (request-scoped carrier).
- A GraphQL-only buyRequest provider injects `platformz_cutpoint` into the buy request.
- A quote `addProduct` plugin validates against `platformz_cutpoint_matrix`, derives cut point & info, and writes it to
  the quote item as `additional_options` (JSON).

### Admin import notes

- **CSV is recommended** (works out of the box).
- **XLSX** requires `phpoffice/phpspreadsheet` to be installed in the Magento application.
- The importer expects headers matching your sheet (case-insensitive):
  - `Manufacturer`, `Model`, `Size`, `CutPoint` (or `Cut Point`)
  - Optional: `Additional Info`

