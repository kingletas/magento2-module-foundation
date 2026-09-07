# Commerce_Foundation

The shared base the other modules in this repository build on. It exists so that each module can be installed on its own without dragging in a 400-class "stdlib" grab-bag, and so that the pieces every module genuinely shares — tokens, cache keys, scoped config — have exactly one implementation.

Everything here is either an interface with a swappable default, or a class whose behaviour is driven entirely from `di.xml`. There's nothing store-specific in it.

---

## What's inside

| Contract | Default | Use it for |
| --- | --- | --- |
| `TokenGeneratorInterface` | `Model\Security\TokenGenerator` | Unguessable share/ack tokens, with constant-time verification |
| `RegistryInterface` | `Model\Registry` | Request-scoped state between layers that can't see each other |
| `CacheKeyBuilderInterface` | `Model\Cache\CacheKeyBuilder` | Namespaced cache keys, tags and TTL in one object |
| `ProductImageUrlResolverInterface` | `Model\Catalog\ProductImageUrlResolver` | Product image URLs, CDN-swappable |
| `ConfigurableParentSkuResolverInterface` | `Model\Catalog\ConfigurableParentSkuResolver` | Simple SKU → configurable parent SKU, batched |
| `ModuleFilePathResolverInterface` | `Model\Filesystem\ModuleFilePathResolver` | Paths inside your own module |
| `MessageQueueEnvelopeInterface` | `Model\MessageQueue\Envelope` | Array-payload queue topics without a Data interface per topic |
| — | `Model\Repository\SearchResultBuilder` | `getList()` in any repository, using core's CollectionProcessor |
| — | `Model\Config\ModuleConfig` | Typed, scope-aware reads of one config section |
| — | `Ui\Component\...` | Grid action columns and admin buttons, declared not coded |

---

## Main classes

### `ModuleConfig` — rebrandable config access

The section id is a constructor argument, not a constant. That's what lets the same code serve `acme_embroidery/...` and `contoso_embroidery/...`:

```xml
<virtualType name="Acme\Embroidery\Model\Config" type="Commerce\Foundation\Model\Config\ModuleConfig">
    <arguments>
        <argument name="section" xsi:type="string">acme_embroidery</argument>
    </arguments>
</virtualType>
```

```php
$this->config->isSetFlag('general/enabled', $storeId);
$this->config->getPositiveInt('warmer/batch_size', 100);
$this->config->getList('general/recipients');
```

`getPositiveInt` matters more than it looks: a misconfigured `0` batch size turns a chunked loop into an infinite one, so zero falls back to the default rather than being honoured.

### `SearchResultBuilder` — `getList()` without the boilerplate

```php
public function getList(SearchCriteriaInterface $criteria): SearchResultsInterface
{
    return $this->searchResultBuilder->build($criteria, $this->collectionFactory->create());
}
```

That's the whole implementation. It delegates filter, sort and paging translation to core's `CollectionProcessorInterface`, which handles `like` escaping, `in`/`nin` arrays and null comparisons correctly.

**If your `getList()` declares its own `*SearchResultsInterface`, pass the result in.**

```php
public function getList(SearchCriteriaInterface $criteria): SharedCartSearchResultsInterface
{
    return $this->searchResultBuilder->build(
        $criteria,
        $this->collectionFactory->create(),
        $this->searchResultsFactory->create()      // your typed factory
    );
}
```

Without the third argument the builder creates the result through
`SearchResultsInterfaceFactory`, which produces whatever `SearchResultsInterface`
is preferred to globally — Magento's generic `SearchResults`. That doesn't
implement your module's sub-interface, so the return is a `TypeError` on every
call.

Magento's own repositories have the same wiring and get away with it, because
they declare the narrow type only in a `@return` annotation and PHP never checks
it. Declare it for real and the mismatch stops being a documentation
inaccuracy. Ship a six-line `class FooSearchResults extends SearchResults
implements FooSearchResultsInterface {}`, prefer your interface to it, and pass
its factory's output in here.

---

## Gotchas

- **`CacheKeyBuilderInterface` deliberately has no global `<preference>`.** Every consumer declares its own `virtualType` with its own prefix. Binding one implementation globally gives two unrelated features the same key namespace, and they will collide.
- **Typing a constant on an interface is a breaking change for implementors.** Constant types are invariant, and an untyped constant in a class implementing a typed interface constant is a fatal. Before typing one here, grep every implementor — including ones in other repositories that this repo's diff won't show you.
- **`AbstractColumnMigrator` chunks on a numeric primary key.** A table keyed on a string will loop wrongly. It also copies values in the database and never through PHP, which is deliberate: marshalling them through a cast is how a migration zeroes every varchar and decimal column it touches.
- **`Registry` is request-scoped and deliberately unglamorous.** It exists because Magento's own registry is deprecated and modules still need the seam, not because global mutable state is a good idea.

---

## Tests

```bash
make check
```

The coding standard and all four suites — 156 tests, no database and no Magento bootstrap. Narrow it to one suite with `SUITE`:

```bash
make test SUITE=behaviour
```

The suites run against a real Magento installation without being installed into it. `M2_VENDOR` names that installation's `vendor` directory, and `Test/bootstrap.php` builds an autoloader from its composer map — which is also why they work where the host's own `vendor/autoload.php` is broken.

---

## Rebranding

```bash
php ../bin/rebrand Acme
```
