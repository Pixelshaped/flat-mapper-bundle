![CI](https://github.com/pixelshaped/flat-mapper-bundle/actions/workflows/ci.yml/badge.svg)

# Flat Mapper Bundle

This bundle aims to solve the problem of building DTOs with non-scalar properties from database queries results.

## Introduction

Doctrine [provides a solution](https://www.doctrine-project.org/projects/doctrine-orm/en/2.11/reference/dql-doctrine-query-language.html#new-operator-syntax) to build DTOs directly from a QueryBuilder:

Given a DTO class such as `CustomerDTO`:

```php
<?php
class CustomerDTO
{
    public function __construct($name, $email, $city, $value = null){ /* ... */ }
}
```

Doctrine can execute a query that produces an array `array<CustomerDTO>`:

```php
<?php
$query = $em->createQuery('SELECT NEW CustomerDTO(c.name, e.email, a.city) FROM Customer c JOIN c.email e JOIN c.address a');
$users = $query->getResult(); // array<CustomerDTO>
```

Unfortunately, if you need to retrieve DTOs with non-scalar properties, such as:

- an array of IDs
- an array of nested DTOs

then, the solution provided by Doctrine doesn't work. The creation of this bundle arose from that situation. With it, you can do:

```php
$flatMapper->map(NonScalarCustomerDTO::class, $query->getArrayResult());
```

## How to use?

### Configuration

This bundle can work without any Symfony configuration, but will display better performance if mapping validation is disabled and some cache service is autowired.

```yaml
# config/pixelshaped_flat_mapper.yaml
pixelshaped_flat_mapper:
    validate_mapping: '%kernel.debug%' # disable on prod environment
    cache_service: cache.app
```

### Mapping pre-caching

The mapping for a DTO is created the first time the function is called. Subsequent calls during the same script execution won't recreate the mapping. If a cache service is configured, mapping will be loaded from the cache for next script executions.

If you want to cache all your DTOs in advance to avoid doing it on your hotpaths, you can do:

```php
$dtoClassNames = [CustomerDTO::class, ...];
foreach($dtoClassNames as $className) {
    $flatMapper->createMapping($className);
}
```

This should be regarded as optional. Mapping information be created in any case when calling:

```php
$flatMapper->map(CustomerDTO::class, $results);
```

### Add mapping to your DTOs

This bundle comes with several attributes that you can use to add mapping to your DTOs:

- `#[Identifier]`: Any DTO has to have exactly one identifier. This identifier is used to create the DTO only once.
- `#[InboundProperty("property_name")]`: The name of the key on the associative arrays contained by your result set. This is optional if your DTO's property names are already matching the result set.
- `#[ReferencesArray(NestedDTO::class)]`: An array of `NestedDTO` will be created using the mapping information contained in `NestedDTO`
- `#[ColumnArray("property_name")]` the column `property_name` of your result set will be mapped as an array of scalar properties (such as IDs).

### Hydrating nested DTOs

Given:

- [RootDTO](tests/Examples/Valid/RootDTO.php)
- [LeafDTO](tests/Examples/Valid/LeafDTO.php)

Calling FlatMapper with the following result set:

```php
$results = [
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 1, 'object2_name' => 'Leaf 1', 'object2_value' => 'Value 1'],
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 2, 'object2_name' => 'Leaf 2', 'object2_value' => 'Value 2'],
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 3, 'object2_name' => 'Leaf 3', 'object2_value' => 'Value 3'],
    ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 1, 'object2_name' => 'Leaf 1', 'object2_value' => 'Value 1'],
    ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 4, 'object2_name' => 'Leaf 4', 'object2_value' => 'Value 4'],
];

$flatMapper->map(RootDTO::class, $results);
```

Will output:

```php
Array
(
    [1] => RootDTO Object
        (
            [id] => 1
            [name] => Root 1
            [leafs] => Array
                (
                    [1] => LeafDTO Object
                        (
                            [id] => 1
                            [name] => Leaf 1
                            [value] => Value 1
                        )

                    [2] => LeafDTO Object
                        (
                            [id] => 2
                            [name] => Leaf 2
                            [value] => Value 2
                        )

                    [3] => LeafDTO Object
                        (
                            [id] => 3
                            [name] => Leaf 3
                            [value] => Value 3
                        )
                )
        )
    [2] => RootDTO Object
        (
            [id] => 2
            [name] => Root 2
            [leafs] => Array
                (
                    [1] => LeafDTO Object
                        (
                            [id] => 1
                            [name] => Leaf 1
                            [value] => Value 1
                        )
                    [4] => LeafDTO Object
                        (
                            [id] => 4
                            [name] => Leaf 4
                            [value] => Value 4
                        )
                )
        )
)
```

### Hydrating Column Arrays

Given [ColumnArrayDTO](tests/Examples/Valid/ColumnArrayDTO.php)

Calling FlatMapper with the following result set:
```php
$results = [
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 1],
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 2],
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 3],
    ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 1],
    ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 4],
];
```

Will output:

```php
Array
(
    [1] => ColumnArrayDTO Object
        (
            [id] => 1
            [name] => Root 1
            [object2s] => Array
                (
                    [0] => 1
                    [1] => 2
                    [2] => 3
                )
        )
    [2] => ColumnArrayDTO Object
        (
            [id] => 2
            [name] => Root 2
            [object2s] => Array
                (
                    [0] => 1
                    [1] => 4
                )
        )
)
```

### Working with Doctrine Queries

Given the following DTO class:

```php
<?php
class CustomerDTO
{
    public function __construct(
        #[Identifier]
        #[InboundPropertyName('customer_id')]
        public int $id,
        #[InboundPropertyName('customer_name')]
        public string $name,
        #[ColumnArray('shopping_list_id')]
        public array $shoppingListIds
    )
}
```

The query:

```php
<?php
$result = $this->getOrCreateQueryBuilder()
    ->select('customer.id AS customer_id, customer.name AS customer_name, shopping_list.id AS shopping_list_id')
    ->leftJoin('customer.shopping_list', 'shopping_list')
    ->getQuery()->getResult()
    ;

$flatMapper = new \Pixelshaped\FlatMapperBundle\FlatMapper()

$flatMapper->map(CustomerDTO::class, $result);
```

Will give you an array of `CustomerDTO`, with the `$shoppingListIds` property populated with an array of corresponding ShoppingList IDs.

### Working with pagination

You can still use [Doctrine](https://www.doctrine-project.org/projects/doctrine-orm/en/3.2/tutorials/pagination.html) to paginate your DQL query:

```php
$qb = $customerRepository->createQueryBuilder('customer');
$qb
    ->leftJoin('customer.addresses', 'customer_addresses')
    ->select('customer.id AS customer_id, customer.ref AS customer_ref, customer_addresses.id AS address_id')
    ->setFirstResult(0)
    ->setMaxResults(10)
    ;

$paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);
$paginator->setUseOutputWalkers(false);

$result = $flatMapper->map(CustomerWithAddressesDTO::class, $paginator);
```

Will get you an array of 10 `CustomerWithAddressesDTO` (granted you do have 10 in your db).

### Usage without Symfony

You can use this package without Symfony. Just instantiate the `FlatMapper` class and use its methods.

## Alternatives

When I started coding this, I looked for alternatives but found only partial ones:

- [mark-gerarts/automapper-plus](https://github.com/mark-gerarts/automapper-plus) is great at mapping objects to other objects (namely, entities to DTOs and vice versa), but doesn't solve the problem of mapping denormalized data (i.e. an array with the information for several objects on each row and a lot of redundancy between rows) to objects.
- [jolicode/automapper](https://github.com/jolicode/automapper) is a great alternative to the previous bundle with the same limitations.
- [sunrise-php/hydrator](https://github.com/sunrise-php/hydrator) can map arrays to objects, but not denormalized arrays 
- Several other bundles can map JSON info to objects.
- [doctrine/orm](https://github.com/doctrine/orm) solves this problem internally using [ResultSetMapping](https://www.doctrine-project.org/projects/doctrine-orm/en/2.14/reference/native-sql.html#the-resultsetmapping). It can join on Entities but can't for DTOs because there's no way do declare a mapping for a DTO. That's actually why Doctrine only handles DTOs with scalar properties.
- You can technically build `PARTIAL` objects with Doctrine, but I consider this to be a bad practice as the next developer has no idea if the object at hand is a complete one or not. You could then map it to a DTO and discard it to avoid this situation, but the algorithmic complexity will likely be higher than the mapping we do with our bundle (O(n)).

Do not hesitate to suggest alternatives or to contribute.
