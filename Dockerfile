# Use official PHP with Apache
FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Configure Apache to use the 'public' folder as the root (if you have one)
# If all your files are in the root folder, change 'public' to '.' below
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy your code into the container
COPY . /var/www/html

# Expose port 80
EXPOSE 80
