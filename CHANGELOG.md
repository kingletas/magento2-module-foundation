# Changelog

## Unreleased

Tooling only. The wiring assertions read a field's `config_path` where it has one, so a setting shown on one configuration screen and stored under another section is checked at the path it is stored. Nothing about how the module behaves changed.

Adds `ClockInterface` with `SystemClock`, the current time in UTC, so modules stop carrying their own copy. Adds `LockRunner`, which runs work under a named lock shared by every server, releases it even when the work throws, and says whether the work ran. Tests get `FakeClock` and `ArrayCache` under `Test\Support`.

## 2.1.0

Tooling only. `assertEveryPreferenceResolvesToAnImplementation()` follows a
preference that names a virtual type through to the class it extends, instead of
reporting the virtual type as a class that does not exist. Nothing about how the
module behaves changed.

Tooling only. `DiWiringAssertions` gains
`assertEveryEncryptedFieldIsDeclaredSensitive()`, which reads every encrypted
admin field out of `system.xml` and fails when its config path is not in the
sensitive argument of `TypePool`. Nothing about how the module behaves changed.

Tooling only. Release notes join each changelog paragraph onto one line,
because a release page turns every newline into a line break. Nothing about
how the module behaves changed.

Tooling only. The wiring check that asks for a `config.xml` default now follows
nested `<group>` elements in `system.xml`, building the path from the whole
chain of group ids the way Magento does. A field inside a nested group was
invisible to it, so it reported nothing rather than reporting a missing
default; no module here declares one today. Nothing about how the module
behaves changed.

## 2.0.0

The vendor is now Kingletas: the package is `kingletas/module-foundation`, the namespace
`Kingletas\Foundation` and the module `Kingletas_Foundation`, and every config
section, table, console command and queue name starts with `kingletas`
instead of `commerce`. Nothing about how the module behaves changed; an
existing install moves its `commerce_` config rows and tables to `kingletas_`.

Tooling only. `make test` and `make cs` read Magento and the tools from this
package's own `vendor/`, which `make install` fills, and stop with instructions
when it is missing rather than running whatever `phpcs` or `phpunit` is on the
PATH. Nothing about how the module behaves changed.

## 1.0.4

Tooling only. Every workflow action is pinned to a commit rather than a tag, and
static analysis moved to PHPStan 2. Nothing about how the module behaves changed.

## 1.0.3

Mess detection runs through the module's own composer script, so `composer md`
and the CI gate ask for exactly the same thing.

## Earlier

This module is developed alongside fourteen others and published here from that
tree. The releases before 1.0.3 are in the tags, and the reasoning behind
each one is in the commits.
