#!/bin/sh

envsubst < /mnt/efs/www/config/custom.php.template > /mnt/efs/www/config/custom.php

exec apache2-foreground