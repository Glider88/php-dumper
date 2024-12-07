#!/bin/sh

su - "$USER" -c "cd /usr/src/app; composer install"

tail -f /dev/null
