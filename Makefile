init:
	@if [ ! -f .env ]; then \
		echo "The .env file does not exist. Create it as a copy of .env.template and fill in your information if necessary."; \
		exit 1; \
	fi
	mkdir -p src temp log
	docker-compose up -d

docker:
	docker-compose exec -it php /bin/bash

composer:
	docker-compose exec -it php sh -c "composer install"

cs:
	docker-compose exec -it php sh -c "composer run cs"

cs-fix:
	docker-compose exec -it php sh -c "composer run cs-fix"

phpstan:
	docker-compose exec -it php sh -c "composer run phpstan"

phpunit:
	docker-compose exec -it php sh -c "composer run tests"