FROM php:8.2-cli

# Instala dependencias de sistema e habilita a extensao curl do PHP
# (necessaria para o includes/perfil_api.php se comunicar com o app Flask)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev unzip git \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instala o Composer copiando o binario direto da imagem oficial dele
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

# Instala as dependencias do PHP definidas no composer.json/composer.lock
# (gera a pasta vendor/ com o autoload.php dentro da imagem)
RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80"]
