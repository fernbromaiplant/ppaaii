FROM php:8.2-apache
# 開啟 Apache 的 rewrite 模組
RUN a2enmod rewrite
# 將你的程式碼複製到容器內
COPY . /var/www/html/
# 設定權限
RUN chown -R www-data:www-data /var/www/html
# 暴露 80 埠（Render 會自動對齊）
EXPOSE 80
