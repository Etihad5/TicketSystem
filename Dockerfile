FROM php:8.2-apache
RUN echo '<Directory /var/www/html>' >> /etc/apache2/apache2.conf
RUN echo '    AllowOverride All' >> /etc/apache2/apache2.conf
RUN echo '    Require all granted' >> /etc/apache2/apache2.conf
RUN echo '</Directory>' >> /etc/apache2/apache2.conf


# تفعيل mod_rewrite (مطلوب لمعظم مشاريع PHP)
RUN a2enmod rewrite

# نسخ ملفات المشروع إلى مجلد السيرفر
COPY . /var/www/html/

# ضبط صلاحيات مجلد المشروع
RUN chown -R www-data:www-data /var/www/html

FROM php:8.2-apache

# تثبيت الامتدادات المطلوبة
RUN docker-php-ext-install pdo pdo_mysql
