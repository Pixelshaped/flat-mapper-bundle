[![Latest Stable Version](https://img.shields.io/packagist/v/pixelshaped/flat-mapper-bundle.svg)](https://packagist.org/packages/pixelshaped/flat-mapper-bundle)
![CI](https://github.com/pixelshaped/flat-mapper-bundle/actions/workflows/ci.yml/badge.svg)
[![codecov](https://codecov.io/github/Pixelshaped/flat-mapper-bundle/graph/badge.svg?token=TGPLKP7W2B)](https://codecov.io/github/Pixelshaped/flat-mapper-bundle)

# Flat Mapper Bundle

**Object mapper for denormalized data.** Transform flat arrays (like database JOIN results) into nested, typed DTOs without the overhead of a full ORM.

## The Problem

When you write efficient SQL JOINs, you get back **flat, denormalized rows** where parent data repeats across child records:

```php
// Result from: SELECT author.*, book.* FROM authors LEFT JOIN books ON books.author_id = authors.id
$queryResults = [
    ['author_id' => 1, 'author_name' => 'Alice Brian', 'book_id' => 1, 'book_name' => 'Travelling as a group'],
    ['author_id' => 1, 'author_name' => 'Alice Brian', 'book_id' => 2, 'book_name' => 'My journeys'],
    ['author_id' => 1, 'author_name' => 'Alice Brian', 'book_id' => 3, 'book_name' => 'Coding on the road'],
    ['author_id' => 2, 'author_name' => 'Bob Schmo',   'book_id' => 4, 'book_name' => 'My best recipes'],
];
```

But you want **clean, nested DTOs** for your application:

```php
[
    AuthorDTO(
        id: 1,
        name: 'Alice Brian',
        books: [
            BookDTO(id: 1, name: 'Travelling as a group'),
            BookDTO(id: 2, name: 'My journeys'),
            BookDTO(id: 3, name: 'Coding on the road'),
        ]
    ),
    AuthorDTO(
        id: 2,
        name: 'Bob Schmo',
        books: [
            BookDTO(id: 4, name: 'My best recipes'),
        ]
    ),
]
```

**FlatMapper does this transformation automatically**, handling:
- Deduplication (one AuthorDTO per unique author despite repeated rows)
- Relationship reconstruction (grouping books under their authors)
- Nested object hierarchies (DTOs containing arrays of other DTOs)
- Type safety (strongly-typed DTOs with PHP attributes)

**And it's fast.** FlatMapper outperforms Doctrine entity hydration for read operations—even without N+1 queries. See [benchmarks](https://github.com/Pixelshaped/flat-mapper-benchmark) comparing FlatMapper to Doctrine entities, partial objects, and manual mapping.

## Quick Start

### Installation

```bash
composer require pixelshaped/flat-mapper-bundle
```

### Basic Usage

**1. Define your DTOs with attributes:**

```php
use Pixelshaped\FlatMapperBundle\Mapping\{Identifier, Scalar, ReferenceArray};

class AuthorDTO
{
    public function __construct(
        #[Identifier]
        #[Scalar('author_id')]
        public int $id,

        #[Scalar('author_name')]
        public string $name,

        #[ReferenceArray(BookDTO::class)]
        public array $books,
    ) {}
}

class BookDTO
{
    public function __construct(
        #[Identifier('book_id')]
        public int $id,

        #[Scalar('book_name')]
        public string $name,
    ) {}
}
```

**2. Map your flat results:**

```php
use Pixelshaped\FlatMapperBundle\FlatMapper;

$flatMapper = new FlatMapper();
$authors = $flatMapper->map(AuthorDTO::class, $queryResults);
```

That's it! You now have properly structured `AuthorDTO` objects with nested `BookDTO` arrays.

## How It Works

### Mapping Attributes

FlatMapper uses PHP attributes to define how flat data maps to your DTOs:

#### `#[Identifier]` - Required

Every DTO needs exactly one identifier to track unique instances:

```php
// As a property attribute (when you need the ID in your DTO)
class AuthorDTO {
    public function __construct(
        #[Identifier]
        #[Scalar('author_id')]
        public int $id,
        // ...
    ) {}
}

// As a class attribute (when you only need it for internal tracking)
#[Identifier('product_id')]
class ProductDTO {
    public function __construct(
        #[Scalar('product_sku')]
        public string $sku,
        // ...
    ) {}
}
```

#### `#[Scalar("column_name")]` - Optional

Maps a column from your result set to a scalar property. Omit if property names match column names:

```php
class BookDTO {
    public function __construct(
        public int $id,              // Looks for 'id' column
        #[Scalar('book_name')]
        public string $name,         // Looks for 'book_name' column
    ) {}
}
```

#### `#[ReferenceArray(NestedDTO::class)]` - For nested objects

Creates an array of nested DTOs from the denormalized data:

```php
class AuthorDTO {
    public function __construct(
        #[Identifier('author_id')]
        public int $id,

        #[ReferenceArray(BookDTO::class)]
        public array $books,  // Will contain BookDTO instances
    ) {}
}
```

#### `#[ScalarArray("column_name")]` - For arrays of scalars

Collects scalar values (like IDs) into an array:

```php
class CustomerDTO {
    public function __construct(
        #[Identifier('customer_id')]
        public int $id,

        #[ScalarArray('shopping_list_id')]
        public array $shoppingListIds,  // [1, 2, 3, ...]
    ) {}
}
```

#### `#[NameTransformation]` - Class-level attribute

Apply consistent naming rules to avoid repeating `#[Scalar]` on every property:

```php
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

// Add a prefix to all column lookups
#[NameTransformation(columnPrefix: 'author_')]
class AuthorDTO {
    public function __construct(
        #[Identifier]
        public int $id,        // Looks for 'author_id'
        public string $name,   // Looks for 'author_name'
    ) {}
}

// Convert camelCase to snake_case
#[NameTransformation(snakeCaseColumns: true)]
class ProductDTO {
    public function __construct(
        #[Identifier]
        public int $productId,      // Looks for 'product_id'
        public string $productName, // Looks for 'product_name'
    ) {}
}

// Combine both
#[NameTransformation(columnPrefix: 'usr_', snakeCaseColumns: true)]
class UserDTO {
    public function __construct(
        #[Identifier]
        public int $userId,      // Looks for 'usr_user_id'
        public string $fullName, // Looks for 'usr_full_name'
    ) {}
}
```

Individual `#[Scalar]` or `#[Identifier]` attributes override class-level transformations.

## Complete Examples

### Nested DTOs Example

**DTOs:**
- [AuthorDTO](tests/Examples/Valid/ReferenceArray/AuthorDTO.php)
- [BookDTO](tests/Examples/Valid/ReferenceArray/BookDTO.php)

**Input (denormalized):**

```php
$results = [
    ['author_id' => 1, 'author_name' => 'Alice Brian', 'book_id' => 1, 'book_name' => 'Travelling as a group', 'book_publisher_name' => 'TravelBooks'],
    ['author_id' => 1, 'author_name' => 'Alice Brian', 'book_id' => 2, 'book_name' => 'My journeys', 'book_publisher_name' => 'Lorem Press'],
    ['author_id' => 1, 'author_name' => 'Alice Brian', 'book_id' => 3, 'book_name' => 'Coding on the road', 'book_publisher_name' => 'Ipsum Books'],
    ['author_id' => 2, 'author_name' => 'Bob Schmo', 'book_id' => 1, 'book_name' => 'Travelling as a group', 'book_publisher_name' => 'TravelBooks'],
    ['author_id' => 2, 'author_name' => 'Bob Schmo', 'book_id' => 4, 'book_name' => 'My best recipes', 'book_publisher_name' => 'Cooking and Stuff'],
];

$authors = $flatMapper->map(AuthorDTO::class, $results);
```

**Output (nested objects):**

```php
Array
(
    [1] => AuthorDTO Object
        (
            [id] => 1
            [name] => Alice Brian
            [books] => Array
                (
                    [1] => BookDTO Object
                        (
                            [id] => 1
                            [name] => Travelling as a group
                            [publisherName] => TravelBooks
                        )
                    [2] => BookDTO Object
                        (
                            [id] => 2
                            [name] => My journeys
                            [publisherName] => Lorem Press
                        )
                    [3] => BookDTO Object
                        (
                            [id] => 3
                            [name] => Coding on the road
                            [publisherName] => Ipsum Books
                        )
                )
        )
    [2] => AuthorDTO Object
        (
            [id] => 2
            [name] => Bob Schmo
            [books] => Array
                (
                    [1] => BookDTO Object
                        (
                            [id] => 1
                            [name] => Travelling as a group
                            [publisherName] => TravelBooks
                        )
                    [4] => BookDTO Object
                        (
                            [id] => 4
                            [name] => My best recipes
                            [publisherName] => Cooking and Stuff
                        )
                )
        )
)
```

### Scalar Arrays Example

**DTO:** [ScalarArrayDTO](tests/Examples/Valid/ScalarArray/ScalarArrayDTO.php)

**Input:**

```php
$results = [
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 1],
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 2],
    ['object1_id' => 1, 'object1_name' => 'Root 1', 'object2_id' => 3],
    ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 1],
    ['object1_id' => 2, 'object1_name' => 'Root 2', 'object2_id' => 4],
];
```

**Output:**

```php
Array
(
    [1] => ScalarArrayDTO Object
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
    [2] => ScalarArrayDTO Object
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

## Framework Integration

### Symfony

FlatMapper works out of the box with Symfony. Optionally configure for better performance:

```yaml
# config/packages/pixelshaped_flat_mapper.yaml
pixelshaped_flat_mapper:
    validate_mapping: '%kernel.debug%'  # Disable validation in production
    cache_service: cache.app            # Cache mapping metadata
```

### Doctrine

Use with DQL queries:

```php
$result = $entityManager->createQueryBuilder()
    ->select('customer.id AS customer_id, customer.name AS customer_name, shopping_list.id AS shopping_list_id')
    ->from(Customer::class, 'customer')
    ->leftJoin('customer.shoppingLists', 'shopping_list')
    ->getQuery()
    ->getResult();

$customers = $flatMapper->map(CustomerDTO::class, $result);
```

### Pagination

FlatMapper works with Doctrine's Paginator:

```php
$qb = $customerRepository->createQueryBuilder('customer')
    ->leftJoin('customer.addresses', 'address')
    ->select('customer.id AS customer_id, customer.ref AS customer_ref, address.id AS address_id')
    ->setFirstResult(0)
    ->setMaxResults(10);

$paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);
$paginator->setUseOutputWalkers(false);

$customers = $flatMapper->map(CustomerWithAddressesDTO::class, $paginator);
```

### Standalone (No Framework)

```php
use Pixelshaped\FlatMapperBundle\FlatMapper;

$flatMapper = new FlatMapper();

// Optional: configure for production
$flatMapper
    ->setCacheService($psr6CachePool)  // Any PSR-6 cache
    ->setValidateMapping(false);       // Skip validation checks

$result = $flatMapper->map(AuthorDTO::class, $queryResults);
```

## Performance Optimization

### Mapping Cache

Mapping metadata is created once per DTO and cached across requests when a cache service is configured. The first call analyzes your DTO attributes; subsequent calls use the cached mapping.

### Pre-cache Mappings

Avoid creating mappings on hot paths by pre-caching during deployment:

```php
$dtoClasses = [CustomerDTO::class, OrderDTO::class, ProductDTO::class];

foreach ($dtoClasses as $class) {
    $flatMapper->createMapping($class);
}
```

This is optional. Mappings are created automatically when calling `map()` if not already cached.

### Disable Validation in Production

Validation checks ensure your DTOs are configured correctly but add a little overhead. Disable in production:

```php
$flatMapper->setValidateMapping(false);
```

Or in Symfony:
```yaml
pixelshaped_flat_mapper:
    validate_mapping: '%kernel.debug%'  # true in dev, false in prod
```

## Why Not Just Use...?

### Doctrine Entities

FlatMapper is significantly faster for read operations ([see benchmarks](https://github.com/Pixelshaped/flat-mapper-benchmark)):
- ~2x faster execution time
- 40-60% less memory usage
- No lazy-loading surprises

Using full Doctrine entities for reads also:
- Risks coupling your templates/views to your domain model
- Loads entity metadata and change tracking overhead
- Can trigger lazy-loading and N+1 queries (even with proper JOINs, proxies add overhead)

FlatMapper gives you lightweight, read-only DTOs optimized for queries.

### Doctrine's `NEW` Operator

Doctrine can create DTOs directly in DQL:

```php
$query = $em->createQuery('SELECT NEW CustomerDTO(c.name, e.email, a.city) FROM Customer c JOIN c.email e JOIN c.address a');
$customers = $query->getResult(); // array<CustomerDTO>
```

**Limitation:** Only supports scalar properties. You can't have:
- Arrays of nested DTOs (`#[ReferenceArray]`)
- Arrays of IDs or other scalar arrays (`#[ScalarArray]`)
- Complex object graphs

FlatMapper solves this by handling denormalized data at any nesting level.

### Other Object Mappers

Most object mappers transform **nested arrays** (like JSON) to objects:

- [mark-gerarts/automapper-plus](https://github.com/mark-gerarts/automapper-plus) - Maps entities to DTOs (normalized data)
- [jolicode/automapper](https://github.com/jolicode/automapper) - Maps normalized objects/arrays
- [sunrise-php/hydrator](https://github.com/sunrise-php/hydrator) - Maps normalized arrays to objects

**These don't handle denormalized data** where:
- Parent information repeats across multiple rows
- Relationships need to be reconstructed from flat results
- One row doesn't equal one object

### `PARTIAL` Objects + Manual Mapping

You could use Doctrine's `PARTIAL` objects then map to DTOs, but:
- No indication whether an object is fully loaded
- Two-step process (entity hydration + DTO mapping)
- Higher complexity than direct flat-to-DTO mapping

## Contributing

Found a bug or have a suggestion? Please [open an issue](https://github.com/pixelshaped/flat-mapper-bundle/issues) or submit a pull request.

Know of an alternative that solves similar problems? Let us know—we'd love to reference it here!

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.
