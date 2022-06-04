#!/bin/sh

set -e

download_wp() {
	VERSION="$1"
	if [ "${VERSION}" = "nightly" ] || [ "${VERSION}" = "trunk" ]; then
		TESTS_TAG="trunk"
	elif [ "${VERSION}" = "latest" ]; then
		VERSIONS=$(wget https://api.wordpress.org/core/version-check/1.7/ -q -O - )
		LATEST=$(echo "${VERSIONS}" | jq -r '.offers | map(select( .response == "upgrade")) | .[0].version')
		if [ -z "${LATEST}" ]; then
			echo "Unable to detect the latest WP version"
			exit 1
		fi

		download_wp "${LATEST}"
		ln -sf "/tmp/wordpress-${LATEST}" /tmp/wordpress-latest
		ln -sf "/tmp/wordpress-tests-lib-${LATEST}" /tmp/wordpress-tests-lib-latest
		return
	elif [ "${VERSION%.x}" != "${VERSION}" ]; then
		VER="${VERSION}"
		LATEST=$(wget https://api.wordpress.org/core/version-check/1.7/ -q -O - | jq --arg version "${VERSION%.x}" -r '.offers | map(select(.version | startswith($version))) | sort_by(.version) | reverse | .[0].version')
		download_wp "${LATEST}"
		ln -sf "/tmp/wordpress-${LATEST}" "/tmp/wordpress-${VER}"
		ln -sf "/tmp/wordpress-tests-lib-${LATEST}" "/tmp/wordpress-tests-lib-${VER}"
		return
	else
		TESTS_TAG="tags/${VERSION}"
	fi

	if [ ! -d "/tmp/wordpress-${VERSION}" ]; then
		if [ "${VERSION}" = "nightly" ]; then
			cd /tmp
			wget -q https://wordpress.org/nightly-builds/wordpress-latest.zip
			unzip -q wordpress-latest.zip
			mv /tmp/wordpress /tmp/wordpress-nightly
			rm -f wordpress-latest.zip
			cd -
		else
			mkdir -p "/tmp/wordpress-${VERSION}"
			wget -q "https://wordpress.org/wordpress-${VERSION}.tar.gz" -O - | tar --strip-components=1 -zxm -f - -C "/tmp/wordpress-${VERSION}"
		fi
		wget -q https://raw.github.com/markoheijnen/wp-mysqli/master/db.php -O "/tmp/wordpress-${VERSION}/wp-content/db.php"
	else
		echo "Skipping WordPress download"
	fi

	if [ ! -d "/tmp/wordpress-tests-lib-${VERSION}" ]; then
		mkdir -p "/tmp/wordpress-tests-lib-${VERSION}"
		svn co --quiet --ignore-externals "https://develop.svn.wordpress.org/${TESTS_TAG}/tests/phpunit/includes/" "/tmp/wordpress-tests-lib-${VERSION}/includes"
		svn co --quiet --ignore-externals "https://develop.svn.wordpress.org/${TESTS_TAG}/tests/phpunit/data/" "/tmp/wordpress-tests-lib-${VERSION}/data"
		rm -f "/tmp/wordpress-tests-lib-${VERSION}/wp-tests-config-sample.php"
		wget -q "https://develop.svn.wordpress.org/${TESTS_TAG}/wp-tests-config-sample.php" -O "/tmp/wordpress-tests-lib-${VERSION}/wp-tests-config-sample.php"
	else
		echo "Skipping WordPress test library download"
	fi
}

export WP_VERSION="${WP_VERSION:-"latest"}"
export DB_USER="${DB_USER:-"root"}"
export DB_PASSWORD="${DB_PASSWORD:-""}"
export DB_NAME="${DB_NAME:-"wordpress_test"}"
export DB_HOST="${DB_HOST:-"127.0.0.1"}"

download_wp "${WP_VERSION}"

echo "Waiting for MySQL..."
while ! mysqladmin ping -h "${DB_HOST}" --silent; do
	sleep 1
done

mysqladmin create "${DB_NAME}" --user="${DB_USER}" --password="${DB_PASSWORD}" --host="${DB_HOST}" || true

(
	cd "/tmp/wordpress-tests-lib-${WP_VERSION}" && \
	cp -f wp-tests-config-sample.php wp-tests-config.php && \
	sed -i "s/youremptytestdbnamehere/${DB_NAME}/; s/yourusernamehere/${DB_USER}/; s/yourpasswordhere/${DB_PASSWORD}/; s|localhost|${DB_HOST}|" wp-tests-config.php && \
	sed -i "s:dirname( __FILE__ ) . '/src/':'/tmp/wordpress/':" wp-tests-config.php
)

rm -rf /tmp/wordpress /tmp/wordpress-tests-lib
ln -sf "/tmp/wordpress-${WP_VERSION}" /tmp/wordpress
ln -sf "/tmp/wordpress-tests-lib-${WP_VERSION}" /tmp/wordpress-tests-lib
