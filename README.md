# Flat Mapper Bundle

This bundle aims to solve the problem of building DTOs from database queries results.

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

The solution provided by Doctrine doesn't work. The creation of this bundle arose from that situation.

## How to use?

### Add mapping to your DTOs

This bundle comes with several attributes that you can use to add mapping to your DTOs:

- `#[Identifier]`: Any DTO has to have at least one identifier. This identifier is used to create the DTO only once.
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