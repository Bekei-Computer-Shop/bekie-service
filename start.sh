#!/bin/bash
set -e

# Create nginx configuration using the PORT environment variable (default to 8080 if not set)
# and set the user to www-data for worker processes
cat > /etc/nginx/nginx.conf <<EOF
user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    # Basic settings
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    # server_tokens off;

    # server_names_hash_bucket_size 64;
    # server_name_in_redirect off;

    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # SSL settings
    ssl_protocols TLSv1 TLSv1.1 TLSv1.2 TLSv1.3; # Dropping SSLv3, ref: POODLE
    ssl_prefer_server_ciphers on;

    # Logging settings
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Gzip settings
    gzip on;
    gzip_disable "msie6";

    # Default server configuration
    server {
        listen ${PORT:-8080};
        index index.php index.html;
        error_log  /var/log/nginx/error.log;
        access_log /var/log/nginx.access.log;
        root /var/www/html/public;

        location ~ \.php$ {
            try_files $uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }

        location / {
            try_files $uri $uri/ /index.php?\$query_string;
            gzip_static on;
        }
    }
}
EOF

# Create nginx log directory if it doesn't exist
mkdir -p /var/log/nginx

# Start PHP-FPM in the background (as root, will drop to www-data for workers)
php-fpm -D

# Start nginx in the foreground (as root, will drop to www-data for workers)
nginx -g 'daemon off;'