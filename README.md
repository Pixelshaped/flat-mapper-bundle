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