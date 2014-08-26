Setup log for drivegal
======================

## Create EC2 instance and LAMP server.

[Launch an EC2 instance](http://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-launch-instance_linux.html),
selecting an Ubuntu long-term-stable image.

Install Apache/MySQL/PHP packages:

    sudo apt-get update
    sudo apt-get install lamp-server^ # Note the caret at the end
    sudo apt-get install php5-curl # Required by OAuth library

## Configure Apache

    vim /etc/apache2/sites-available/drivegal.conf
    a2ensite drivegal
    service apache2 restart

## Set up MySQL database

    mysql -uroot -p
    CREATE DATABASE drivegal;
    CREATE USER 'drivegal'@'localhost' IDENTIFIED BY 'mySuperC0mpl3xP4assPhr4s3';
    GRANT SELECT,INSERT,UPDATE,DELETE ON drivegal.* TO 'drivegal'@'localhost';

## Install composer

    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer

# Install Silex skeleton

    cd
    composer create-project fabpot/silex-skeleton ./drivegal
    sudo mv drivegal /var/www/

Tweak the Silex skeleton.

    cd /var/www/drivegal
    mkdir -p doc/silex-skeleton web/js/lib web/img web/css
    mv LICENSE doc/silex-skeleton/
    sudo chown -R www-data var
