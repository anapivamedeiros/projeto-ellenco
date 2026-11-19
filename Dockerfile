FROM php:8.3-apache

# mod_rewrite (páginas amigáveis: /painel.html -> backend/painel.php) e headers.
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers

# AllowOverride por um arquivo em conf-enabled, que é lido DEPOIS do apache2.conf
# e do site. O `sed` sozinho no apache2.conf não bastava: os <Directory> já
# definidos vencem, e sem AllowOverride o Apache ignora o .htaccess — a página
# era entregue como arquivo estático (sem CSRF) e /backend/*.php não resolvia.
RUN printf '<Directory /var/www/>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-enabled/zz-allowoverride.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Porta vinda do Render, e o log de erro no stdout para aparecer no painel.
CMD ["sh","-c","sed -i \"s/Listen 80/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-10000}>/\" /etc/apache2/sites-enabled/000-default.conf && ln -sf /proc/self/fd/2 /var/log/apache2/error.log && apache2-foreground"]
