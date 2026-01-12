## Platformz_DealerLocator

GraphQL query to geocode an address into latitude/longitude.

### Install (as app/code module)

- Copy module to `app/code/Platformz/DealerLocator`
- Run:

```bash
bin/magento module:enable Platformz_DealerLocator
bin/magento setup:upgrade
bin/magento cache:flush
```

### GraphQL

Query name: `dealerLocatorCoordinates`

Input:
- `address_1: String!`
- `address_2: String`
- `city: String!`
- `state: String!`
- `country: String!`
- `postal_code: String!`

Example:

```graphql
query {
  dealerLocatorCoordinates(
    input: {
      address_1: "1600 Amphitheatre Parkway"
      address_2: ""
      city: "Mountain View"
      state: "CA"
      country: "US"
      postal_code: "94043"
    }
  ) {
    latitude
    longitude
    display_name
  }
}
```

### Notes

- Default implementation uses OpenStreetMap Nominatim (`Platformz\DealerLocator\Model\Geocoding\NominatimGeocoder`).
- Nominatim requires a valid **User-Agent**. Update it in `Model/Geocoding/NominatimGeocoder.php` to match your project/contact.

