FROM php:8.2-cli

# Instala dependencias de sistema e habilita a extensao curl do PHP
# (necessaria para o includes/perfil_api.php se comunicar com o app Flask)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80"]
