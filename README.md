# TeamSpeak3-Online-Checker

A small script that runs every minute and compares the current online clients with the previous ones. If someone has come online or gone offline a message with the info is sent via Telegram.

## Setup

1. Copy the config file
```shell
cp ./config.php.dist ./config.php
```

2. Fill out the needed values in `config.php`

3. Create a cronjob which will execute `index.php` every minute
```bash
* * * * * /usr/bin/php /var/www/teamspeak/index.php
```