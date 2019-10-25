#!/usr/bin/env bash
SERVER=$1
SSH="ssh root@$SERVER"
#$SSH 'wp plugin deactivate --allow-root --path=/var/www/morrisfed/ "PLI Certificate Generation"'
#$SSH 'wp plugin delete --allow-root --path=/var/www/morrisfed/ "PLI Certificate Generation"'

$SSH 'mkdir /var/www/morrisfed/wp-content/plugins/pli_gen'
scp pli_gen.php root@$SERVER:/var/www/morrisfed/wp-content/plugins/pli_gen
$SSH 'chown -R www-data:www-data /var/www/morrisfed/wp-content/plugins/pli_gen'
