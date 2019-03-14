[titleEn]: <>(Creating a custom entity)
[wikiUrl]: <>(../how-to/creating-a-custom-entity?category=platform-en/how-to)

## Overview

Quite often, your plugin has to save data into a custom database table.
The Shopware platform's [data abstraction layer](./data-abstraction-layer.md) fully supports custom entities,
so you don't have to take care about the data handling at all.

## Plugin base class

So let's start with the plugin base class.

All it has to do, is to register your `services.xml` file again.

```php
<?php declare(strict_types=1);

namespace CustomEntity;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class CustomEntity extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }
}
```

*Note: The namespace does **not** have to include `Entity` in order to work. This is just the plugin's namespace,
since this example plugin is named `CustomEntity`.*

## The EntityDefinition class

The main entry point for custom entities is an `EntityDefinition` class.
For more information about what the `EntityDefinition` class does, have a look at the guide about the [Shopware platform
data abstraction layer](./data-abstraction-layer.md).

Your custom entity, as well as your `EntityDefinition` and the `EntityCollection` classes, should be placed inside a folder
named after the domain it handles, e.g. "Checkout" if you were to include a Checkout entity.

In this example, they will be put into a folder called `Custom` inside of the plugin root directory.

```php
<?php declare(strict_types=1);

namespace CustomEntity\Custom;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomEntityDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'custom_entity';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('technical_name', 'technicalName'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CustomEntityCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CustomEntity::class;
    }
}
```

As you can see, the `EntityDefinition` lists all available fields of your custom entity, as well as it's name, it's `EntityCollection` 
class and it's actual entity class.
Keep in mind, that the return of your `getEntityName` method will be used for two cases:
- The database table name
- The repository name in the DI container (`<the-name>.repository`)

The two missing classes, the `Entity` itself and the `EntityCollection`, will be created in the next steps.

## The entity class

The entity class itself is a simple value object, like a struct, which contains as much properties as fields in the definition.

```php
<?php declare(strict_types=1);

namespace CustomEntity\Custom;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
```

As you can see, it only holds the properties and it's respective getters and setters, for the fields mentioned in the
`EntityDefinition` class.

## CustomEntityCollection

An `EntityCollection` class is a class, whose main purpose it is to hold one or more of your entities, when they are being read / searched.
It will be automatically returned by the DAL when dealing with the custom entity repository.

```php
<?php declare(strict_types=1);

namespace CustomEntity\Custom;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(CustomEntity $entity)
 * @method void              set(string $key, CustomEntity $entity)
 * @method CustomEntity[]    getIterator()
 * @method CustomEntity[]    getElements()
 * @method CustomEntity|null get(string $key)
 * @method CustomEntity|null first()
 * @method CustomEntity|null last()
 */
class CustomEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CustomEntity::class;
    }
}
```

You should also add the annotation above the class to make sure your IDE knows how to properly handle your custom collection.
Make sure to replace every occurrence of `CustomEntity` in there with your actual entity class.

## Creating the table

Basically that's it for your custom entity itself.
Yet, there's a very important part missing: Creating the database table.
As already mentioned earlier, the database table **has to** be named after your chosen entity name.

You should create the database table using the plugin migration system.
Read more about it [here](./080-plugin-migrations.md).

In short:
Create a new folder named `Migration` in your plugin root and add a migration class like this in there:

```php
<?php declare(strict_types=1);

namespace CustomEntity\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552484872Custom extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552484872;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `custom_entity` (
    `id` BINARY(16) NOT NULL,
    `technical_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3),
    PRIMARY KEY (`id`)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
```

## Dealing with your custom entity

Since the DAL automatically creates a repository for your custom entities, you can now ask the DAL to return some of your
custom data.

```php
/** @var EntityRepositoryInterface $customRepository */
$customRepository = $this->container->get('custom_entity.repository');
$customId = $customRepository->searchIds(
    (new Criteria())->addFilter(new EqualsFilter('technicalName', 'Foo')),
    Context::createDefaultContext()
)->getIds()[0];
```

In this example, the ID of your custom entity, whose technical name equals to 'FOO', is requested.

As a follow up, you might want to have a look at the documentation on [How to translate custom entities](./060-translating-custom-entites.md).