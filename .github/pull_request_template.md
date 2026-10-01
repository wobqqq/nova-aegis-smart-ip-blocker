## What changes

<!-- What the change does and why. -->

## Upgrading applications

<!-- A changed setting or default? A changed cache key or console command? A newer Aegis core needed? Anything a developer has to do after `composer update`? Write "none" if nothing. -->

## Checklist

- [ ] `make ready` passes (fixers, static analysis, tests with coverage)
- [ ] Every new setting is validated in `rules()` and again where it is read
- [ ] The administrator saving the settings cannot rate-limit themselves out
- [ ] CHANGELOG.md and README updated if the behaviour or the settings changed
