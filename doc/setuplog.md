Setup log for gdrivegallery
===========================

## Create EC2 instance and LAMP server.

## Configure Apache

    vim /etc/apache2/sites-available/gdrivegallery.conf
    a2ensite gdrivegallery
    service apache2 restart

## Install composer

    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer

# Install silex skeleton

    cd
    composer create-project fabpot/silex-skeleton ./gdrivegallery
    sudo mv gdrivegallery /var/www/

Tweak the silex skeleton.

    cd /var/www/gdrivegallery
    mkdir -p doc/silex-skeleton web/js/lib web/img web/css
    mv LICENSE doc/silex-skeleton/
    sudo chown -R www-data var
