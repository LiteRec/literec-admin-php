#!/bin/sh
set -e

# In production, refuse to start without a configured APP_SECRET: an empty
# secret allows forged sessions and broken CSRF protection.
if [ "${APP_ENV:-prod}" = 'prod' ] && [ -z "${APP_SECRET:-}" ]; then
	echo 'ERROR: APP_SECRET must be set to a non-empty value in production.' >&2
	exit 1
fi

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		if [ "${APP_ENV:-prod}" = 'prod' ]; then
			composer install --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader
		else
			composer install --prefer-dist --no-progress --no-interaction
		fi
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	# Keep the bind-mounted assets/vendor/ in sync with importmap.php on every
	# dev start. assets/vendor/ is gitignored, so a developer pulling a branch
	# that adds a new importmap entry (e.g. @alpinejs/focus) would otherwise
	# hit "vendor asset missing" at runtime until they ran the command by
	# hand. The command is idempotent and only downloads what is missing. Prod
	# containers do not bind-mount the project directory and have the assets
	# baked into the image by asset-map:compile at build time.
	if [ -f importmap.php ] && [ "${APP_ENV:-prod}" != 'prod' ]; then
		php bin/console importmap:install
	fi

	# Compile the Tailwind CSS so AssetMapper can resolve @import "tailwindcss"
	# when any page is requested. Production images already run tailwind:build
	# --minify during the image build (see Dockerfile), so skip it here to avoid
	# redundant work and longer container start-up.
	if [ -f importmap.php ] && [ "${APP_ENV:-prod}" != 'prod' ]; then
		php bin/console tailwind:build
	fi

	if grep -q ^DATABASE_URL= .env 2>/dev/null || [ -n "${DATABASE_URL:-}" ]; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		DB_EXIT_CODE=1
		while [ "$ATTEMPTS_LEFT_TO_REACH_DATABASE" -gt 0 ]; do
			# Capture the exit code explicitly. The `&& ... || ...` keeps the
			# line itself successful so `set -e` does not abort on a failed
			# connection attempt.
			DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1) && DB_EXIT_CODE=0 || DB_EXIT_CODE=$?
			if [ "$DB_EXIT_CODE" -eq 0 ]; then
				break
			fi
			if [ "$DB_EXIT_CODE" -eq 255 ]; then
				# Exit code 255 means PHP itself terminated (fatal error or
				# uncaught exception), not a transient connection failure —
				# stop retrying and fail below.
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ "$DB_EXIT_CODE" -ne 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		fi
		echo 'The database is now ready and reachable'

		if [ -d ./migrations ] && [ "$(find ./migrations -name '*.php' -print -quit)" ]; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
