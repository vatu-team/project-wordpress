#!/usr/bin/env bash

# Update plugin language files.
wp language plugin update --all

# Run WordPress database update if available.
wp core update-db

# Delete expired transients
wp transient delete --expired

# Cache purge
wp cache flush
