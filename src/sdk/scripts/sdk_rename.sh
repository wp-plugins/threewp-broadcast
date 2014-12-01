#!/bin/bash

# Renames the Plainview SDK to something unique so that each plugin can use its own version.
# Script must be run from plugin base directory (one dir up).

SLUG=$1
shift

if [ "$SLUG" == "" ]; then
	echo "Syntax: sdk_rename.sh SLUG"
	echo ""
	echo "The SLUG is the new namespace suffix. \\plainview\\sdk_broadcast_SLUG\\"
	exit 1
fi

cd ..
if [ ! -f "base.php" ]; then
	echo "Run from the scripts directory."
	exit 1
fi

# Single backslash
perl -pi -e "s/plainview\\\\sdk/plainview\\\\sdk_$SLUG/" `find ./ -type f`
# Double backslash
perl -pi -e "s/plainview\\\\\\\\sdk/plainview\\\\\\\\sdk_$SLUG/" `find ./ -type f`
