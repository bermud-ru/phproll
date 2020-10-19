# PHPRoll simple PHP backend script for RIA (Rich Internet Application) / SPA (Single-page Application) frontend

**composer.json**
```json
{
    "repositories": [
    {
        "url": "git@github.com:bermud-ru/phproll.git",
        "type": "git"
    },
    {
        "url": "git@github.com:bermud-ru/jsroll.git",
        "type": "git"
    }
    ],
    "require": {
      "bermud-ru/phproll":"*@dev",
      "bermud-ru/jsroll":"*@dev"
    },

    "scripts": {
        "post-install-cmd": [
            "./vendor/bermud-ru/jsroll/post-install",
            "./vendor/bermud-ru/phproll/post-install"
        ],
        "post-update-cmd": [
            "./vendor/bermud-ru/jsroll/post-update",
            "./vendor/bermud-ru/phproll/post-update"
        ]
    },
    "install": {
        "address": "127.0.0.1",
        "domain": "demo.server"
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