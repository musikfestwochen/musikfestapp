.PHONY: precommit test artisan build up shell pest validate

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

artisan:
	$(APP) php artisan $(ARGS)

shell:
	$(APP) sh -lc bash
