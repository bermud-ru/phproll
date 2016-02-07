# PHPRoll simple PHP backend script for RIA (Rich Internet Application) / SPA (Single-page Application) frontend

**composer.json**
```json
{
    "repositories": [
    {
	"url": "git@github.com:bermud-ru/phproll.git",
	"type": "git"
    }
    ],
    "require": {
	"bermud-ru/phproll":"*@dev"
    },

    "scripts": {
	"post-install-cmd": [
	"./vendor/bermud-ru/phproll/post-install"
	],
	"post-update-cmd": [
	"./vendor/bermud-ru/phproll/post-update"
	]
    }
}
```

**php.ini**
```
; Always populate the $HTTP_RAW_POST_DATA variable. PHP's default behavior is
; to disable this feature and it will be removed in a future version.
; If post reading is disabled through enable_post_data_reading,
; $HTTP_RAW_POST_DATA is *NOT* populated.
; http://php.net/always-populate-raw-post-data

always_populate_raw_post_data = -1
```


## Nginx virtual host config
```
server {
    access_log  /var/log/nginx/phproll.access.log combined;
    error_log  /var/log/nginx/phproll.error.log warn;

    server_name phproll www.phproll;
    set $host_path "/srv/phproll";
    root $host_path/public;
    set $app_bootstrap "index.php";
    index $app_bootstrap;

    charset utf-8;

    location / {
        try_files $uri $uri/ /$app_bootstrap?$args;
    }

    location ~ ^/(protected|application|framework|themes/\w+/views) {
        deny  all;
    }

    location ~ \.(js|css|png|jpg|gif|swf|ico|pdf|mov|fla|zip|rar)$ {
        try_files $uri =404;
    }

    location ~ \.php$ {
        set $fsn /$app_bootstrap;
        fastcgi_index $app_botstrap;
        fastcgi_split_path_info  ^(.+\.php)(.*)$;
        fastcgi_pass  unix:/tmp/php-fpm.sock;

        if (-f $document_root$fastcgi_script_name){
            set $fsn $fastcgi_script_name;
        }

        try_files $uri =404;
        fastcgi_param HTTPS on;
        include fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fsn;

        fastcgi_param  PATH_INFO        $fastcgi_path_info;
        fastcgi_param  PATH_TRANSLATED  $document_root$fsn;
    }

    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}

```