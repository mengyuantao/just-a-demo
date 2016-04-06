# 环境
## php7
* compile php7 --with-curl=/usr/local/lib/curl/
* install pecl extension yaf and enable it in php.ini

## nginx
<code>
server {
        listen 8081;

        server_name localhost;

        root /project_directory/public/;

        rewrite ^/(.*)  /index.php/$1;

        location / {
                fastcgi_pass   127.0.0.1:9000;
                include        fastcgi.conf;
        }

        location ^~ /static/ {

        }
}
</code>

# 部署
## start php-cgi or use php-fpm
php-cgi -b localhost:9000

## start nginx
nginx 

## start browser
http://locahost:8081/
