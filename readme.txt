=== Plugin Name ===
Contributors: kmvan
Tags: maintenance
Requires at least: 4.9.9
Tested up to: 5.4.99
Requires PHP: 7.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

> 开启站点维护模式。
> Enable site maintenance-mode.

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

== Description ==
> 开启站点维护模式。
> Enable site maintenance-mode.

== Installation ==
> 上传插件到 `/wp-content/plugins/` 目录后并在 WP 后台启用即可。
> Upload to the `/wp-content/plugins/` directory and ENABLE it.

== Changelog ==
- 2020-04-16 4.0.3 增强管理员令牌登录兼容性
