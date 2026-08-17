#!/bin/bash

set -e
set -u

function create_user_and_database() {
	local database=$1
	echo "  Creating database '$database'"
	mysql <<-EOSQL
	    CREATE DATABASE IF NOT EXISTS \`$database\`
	    ;
EOSQL
}

if [ -n "$MYSQL_RANDOM_ROOT_PASSWORD" ]; then
    params=(--silent)
else
    params=()
    if [ "$MYSQL_ROOT_PASSWORD" ]; then
        params+=(--password="$MYSQL_ROOT_PASSWORD")
    else
        params+=(--password="$MYSQL_ROOT_USER")
    fi
fi

echo "Creating test database"

create_user_and_database "$MYSQL_DATABASE"

echo "Database created"