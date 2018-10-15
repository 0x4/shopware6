# FieldResolver

A FieldResolver is a specific implementation for a field, which needs some extra code to be found properly.

Most use-cases are cover by the standard FieldResolver which handle every kind of relation.
In fact, the fields `OneToMany`, `ManyToOne` and `ManyToMany` are handled by their corresponding FieldResolver to
handle the JOINs in relational database systems.

Imagine you have a list of seo urls and one of them is marked as canonical. If you create a field `canonicalUrl` to
return the seo url marked as canonical, your JOIN needs the additional condition to filter on `is_canonical = 1` to
only find the canonical url. With a custom FieldResolver, you are free to design the JOIN yourself.

## Example

A custom FieldResolver must implement the `Shopware\Core\Framework\ORM\Dbal\FieldResolver\FieldResolverInterface`
interface and should be registered and tagged in the service container as `shopware.entity.field_resolver`.

```php
class CanonicalUrlFieldResolver implements FieldResolverInterface
{
    public function resolve(string $definition, string $root, Field $field, QueryBuilder $query, Context $context, EntityDefinitionQueryHelper $queryHelper, bool $raw): bool
    {
        if (!$field instanceof CanonicalUrlField) {
            return false;
        }

        $seoUrlAlias = $root . '.' . $field->getPropertyName();

        $parameters = [
            '#root#' => EntityDefinitionQueryHelper::escape($root),
            '#source_column#' => EntityDefinitionQueryHelper::escape($field->getStorageName()),
            '#alias#' => EntityDefinitionQueryHelper::escape($seoUrlAlias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
        ];

        $condition = str_replace(
            array_keys($parameters),
            array_values($parameters),
            '#alias#.#reference_column# = #root#.#source_column# AND #alias#.is_canonical = 1'
        );

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape(SeoUrlDefinition::getEntityName()),
            EntityDefinitionQueryHelper::escape($seoUrlAlias),
            $condition
        );

        return true;
    }
}
```

The `resolve()` method should return if it was able to handle the field by returning `true` or `false`.

```xml
<service class="SwagCanonicalUrl\ORM\FieldResolver\CanonicalUrlFieldResolver" id="SwagCanonicalUrl\ORM\FieldResolver\CanonicalUrlFieldResolver">
    <tag name="shopware.entity.field_resolver" />
</service>
```
