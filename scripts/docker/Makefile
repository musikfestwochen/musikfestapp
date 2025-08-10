.PHONY: precommit test mutate artisan build up shell pest validate

COMPOSE=docker compose
APP=$(COMPOSE) run --rm app

build:
	$(COMPOSE) build

validate:
	$(APP) composer validate --strict

precommit:
	$(APP) composer precommit

pest:
	$(APP) ./vendor/bin/pest --parallel

mutate:
	$(APP) ./vendor/bin/pest --mutate --parallel --covered-only --min=100 --ignore-min-score-on-zero-mutations

artisan:
	$(APP) php artisan $(ARGS)

shell:
	$(APP) sh -lc bash
