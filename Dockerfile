FROM php:cli
MAINTAINER Jason Rivers <docker@jasonrivers.co.uk>

RUN docker-php-ext-install -j4 sockets

ADD ./server.php /

EXPOSE 9000

CMD [ "php", "/server.php" ]
