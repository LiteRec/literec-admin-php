# Thin wrapper around the canonical composer scripts so contributors can
# use the conventional `make <target>` workflow. Each target proxies
# directly to the matching composer script — composer.json remains the
# single source of truth.

.PHONY: help db-reset db-reset-test fixtures-dev fixtures-test qa test test-unit test-integration test-functional test-slow

help:
	@echo "make db-reset         - Drop, recreate, migrate, and reseed the dev database"
	@echo "make db-reset-test    - Drop, recreate, migrate, and reseed the test database"
	@echo "make fixtures-dev     - Load fixtures (group dev) into the dev database"
	@echo "make fixtures-test    - Load fixtures (group test) into the test database"
	@echo "make qa               - Run cs + stan + deptrac + full test suite"
	@echo "make test             - Run the full PHPUnit suite"
	@echo "make test-unit        - Run only the Unit testsuite"
	@echo "make test-integration - Run only the Integration testsuite"
	@echo "make test-functional  - Run only the Functional testsuite"
	@echo "make test-slow        - Run only the slow group"

db-reset:
	composer db:reset

db-reset-test:
	composer db:reset-test

fixtures-dev:
	composer fixtures:dev

fixtures-test:
	composer fixtures:test

qa:
	composer qa

test:
	composer test

test-unit:
	composer test:unit

test-integration:
	composer test:integration

test-functional:
	composer test:functional

test-slow:
	composer test:slow
