[wikiUrl]: <>(../using-the-storefront-api/product-endpoint?category=shopware-platform-en/using-the-storefront-api)
[titleEn]: <>(['Product endpoint'])
The product endpoint of the storefront API is used to get product
information e.g. for a storefront listing.

## Listing of products

**GET /storefront-api/v1/product**

Description: Returns a list of products assigned to the sales channel.
All filter, sorting, limit, and search operations are supported. You
find more information about these operations
[here](../30-api/50-filter-search-limit.md).

## Detailed product information

**GET /storefront-api/v1/product/{productId}**

Description: Returns detailed information about a specific product.
