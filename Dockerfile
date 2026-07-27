FROM php:8.2-cli

# Instalar dependencias del sistema necesarias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \ 
    libicu-dev

# Limpiar caché para reducir el tamaño de la imagen
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensiones de PHP que Laravel necesita
RUN docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Obtener la última versión de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar el directorio de trabajo
WORKDIR /var/www/html

# Dar permisos a la carpeta de trabajo (opcional, pero útil)
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto 8000 (el que usa artisan serve)
EXPOSE 8000

# El comando por defecto que se ejecutará cuando inicie el contenedor
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
