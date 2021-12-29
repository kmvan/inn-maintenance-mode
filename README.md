# INN Maintenance Mode

## Requires 环境

* Requires WordPress: 4.9.9
* Tested up to WordPress: 5.9.99
* Requires PHP: 7.3.0

## Usage 使用

> 开启站点维护模式。
> Enable site maintenance-mode.
>
> More tips: https://inn-studio.com/inn-maintenance-mode

## Description 描述

> 开启站点维护模式。
> Enable site maintenance-mode.

## Installation 安装

> 上传插件到 `/wp-content/plugins/` 目录后并在 WP 后台启用即可。
> Upload to the `/wp-content/plugins/` directory and ENABLE it.

## Custom remote page 自定义远程页面

> Add code into `wp-config.php` file:

> 添加以下代码到 `wp-config.php` 文件：

```php
\define('INN_MAINTENANCE_MODE_REMOTE_URL', 'https://YOUR_DOMAIN/PAGE.html');
```

## Custom server location page 自定义服务器本地页面

> Create a new file and name `maintenance` in WordPress root dir.

> 在 `WordPress` 根目录里创建一个新文件并且命名为 `maintenance`。

## Custom refresh minutes 自定义刷新分钟数

> Add code into `wp-config.php` file:

> 添加以下代码到 `wp-config.php` 文件：

```php
\define('INN_MAINTENANCE_MODE_REFRESH_MINUTES', 5);
```
