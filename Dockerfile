FROM php:8.2-cli

# Dependências do sistema e extensões do PHP:
#   curl        -> Gmail, app do Perfil, Adzuna, API do TOTVS
#   pdo_mysql   -> banco MySQL (dados da Central, TOTVS, Painéis BI)
#   pdo_pgsql   -> banco PostgreSQL
#   zip         -> ler planilhas .xlsx nos Painéis BI e gerar Excel nos relatórios
#   (pdo_sqlite já vem no PHP)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libpq-dev libzip-dev unzip git \
    && docker-php-ext-install curl pdo_mysql pdo_pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# SQL Server (banco do TOTVS RM): precisa do driver da Microsoft (msodbcsql18 + pdo_sqlsrv).
# É um passo maior; está explicado em docs/BANCO-DE-DADOS.md. Só ative quando a TI liberar o banco:
# RUN apt-get update && apt-get install -y gnupg curl apt-transport-https \
#  && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft.gpg \
#  && echo "deb [signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql.list \
#  && apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev \
#  && pecl install sqlsrv pdo_sqlsrv && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Instala o Composer copiando o binario direto da imagem oficial dele
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

# Instala as dependencias do PHP definidas no composer.json/composer.lock
RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 80

# router.php protege data/, .env, docs/ etc. (sem ele, os JSON poderiam ser baixados)
CMD ["php", "-S", "0.0.0.0:80", "router.php"]
