#!/usr/bin/env bash

SITEACCESS=$1
ID=$2

php extension/occustomfind/bin/php/opendatadataset_import_pending.php --allow-root-user -s${SITEACCESS} --id=${ID} > /dev/null &