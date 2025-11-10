#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	 if [ "$APP_ENV" != 'prod' ]; then
		ln -sf ${PHP_INI_DIR}/php.ini-development ${PHP_INI_DIR}/php.ini
	else
		ln -sf ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini
	fi

	if [ $APP_ENV = "dev" ]; then
		composer install --prefer-dist --no-progress --optimize-autoloader
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	php bin/console cache:clear

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
