
[titleEn]: <>(Sales channel endpoint)
[titleDe]: <>(Sales channel endpoint)
[wikiUrl]: <>(../using-the-storefront-api/sales-channel-endpoint?category=shopware-platform-en/using-the-storefront-api)

The sales channel endpoint is used to obtain information about various
entities like currency, language or country which are assigned to a
sales channel.

## Get currencies

**GET  /storefront-api/v1/sales-channel/currencies**

**Header:** x-sw-context-token is required  
**Response:** Returns a list of currencies assigned to the sales
channel. All filter, sorting, limit, and search operations are
supported. You find more information about these operations
[here](../30-api/50-filter-search-limit.md).

## Get languages

**GET  /storefront-api/v1/sales-channel/languages**

**Header:** x-sw-context-token is required  
**Response:** Returns a list of languages assigned to the sales channel.
All filter, sorting, limit, and search operations are supported. You
find more information about these operations
[here](../30-api/50-filter-search-limit.md).

## Get countries

**GET  /storefront-api/v1/sales-channel/countries**

**Header:** x-sw-context-token is required  
**Response:** Returns a list of countries assigned to the sales channel.
All filter, sorting, limit, and search operations are supported. You
find more information about these operations here
[here](../30-api/50-filter-search-limit.md).

## Get country states

**GET  /storefront-api/v1/sales-channel/country/states**

**Header:** x-sw-context-token is required  
**Response:** Returns a list of country states assigned to the sales
channel. All filter, sorting, limit, and search operations are
supported. You find more information about these operations
[here](../30-api/50-filter-search-limit.md).

## Get payment methods

**GET  /storefront-api/v1/sales-channel/payment-methods**

**Header:** x-sw-context-token is required  
**Response:** Returns a list of payment methods assigned to the sales
channel. All filter, sorting, limit, and search operations are
supported. You find more information about these operations
[here](../30-api/50-filter-search-limit.md).

## Get shipping methods

**GET  /storefront-api/v1/sales-channel/shipping-methods**

**Header:** x-sw-context-token is required  
**Response:** Returns a list of shipping methods assigned to the sales
channel. All filter, sorting, limit, and search operations are
supported. You find more information about these operations
[here](../30-api/50-filter-search-limit.md).
