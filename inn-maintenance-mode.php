<?php

// Plugin Name: INN Maintenance Mode | INN 维护模式
// Plugin URI: https://inn-studio.com/maintenance-mode
// Description: The site maintenance-mode plugin | 开启站点维护模式插件，内置两种自定义功能，请参见官网说明。
// Author: Km.Van
// Version: 5.0.0
// Author URI: https://inn-studio.com
// PHP Required: 7.3.0

declare(strict_types=1);

namespace InnStudio\Plugins\MaintenanceMode;

\defined('AUTH_KEY') || http_response_code(500) && exit;

final class MaintenanceMode
{
    public const TOKEN_KEY = 'innMaintenanceModeToken';

    public const DEFAULT_LANG = 'en-US';

    public const LANGS = [
        'Maintaining...' => [
            'zh-CN' => '维护中……',
        ],
        '%1$s in maintenance, we will come back soon! <small>(Auto-refresh in %2$d minutes)</small>' => [
            'zh-CN' => '%1$s 正在更新维护中，请稍后再来吧！（页面 %2$d 分钟自动刷新）',
        ],
        'Logged as administrator.' => [
            'zh-CN' => '已作为管理员登陆。',
        ],
        'Administrator token URL' => [
            'zh-CN' => '管理员令牌地址',
        ],
        'URL copied.' => [
            'zh-CN' => '已复制到粘贴版。',
        ],
        'Please copy URL manually.' => [
            'zh-CN' => '请手动复制 URL 地址。',
        ],
        'Unable to locate admin user.' => [
            'zh-CN' => '无法定位管理员用户。',
        ],
    ];

    private $remoteUrl = '';

    private $refreshMinutes = 5;

    private $localPagePath = ABSPATH . '/maintenance';

    public function __construct()
    {
        if (\defined('\\INN_MAINTENANCE_MODE_REMOTE_URL') && filter_var(INN_MAINTENANCE_MODE_REMOTE_URL, \FILTER_VALIDATE_URL)) {
            $this->remoteUrl = INN_MAINTENANCE_MODE_REMOTE_URL;
        }

        if (\defined('\\INN_MAINTENANCE_MODE_REFRESH_MINUTES') && filter_var(INN_MAINTENANCE_MODE_REFRESH_MINUTES, \FILTER_VALIDATE_INT)) {
            $this->refreshMinutes = (int) INN_MAINTENANCE_MODE_REFRESH_MINUTES;
        }

        add_action('plugins_loaded', [$this, 'filterPluginsLoaded']);
        add_filter('plugin_action_links', [$this, 'filterActionLink'], 10, 2);
    }

    public function filterPluginsLoaded(): void
    {
        $this->loginWithAdmin();

        if (\defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if ($this->isWpRestful()) {
            return;
        }

        if (current_user_can('manage_options')) {
            return;
        }

        $this->dieWithRemotePage();
        $this->dieWithLocalPage();
        $this->dieWithWpDie();
    }

    public function filterActionLink($actions, string $pluginFile): array
    {
        if (false !== mb_stripos($pluginFile, basename(__DIR__))) {
            $adminUrl = get_admin_url();
            $tokenKey = self::TOKEN_KEY;
            $url      = "{$adminUrl}?{$tokenKey}={$this->genToken()}";
            $opts     = <<<HTML
<a id="inn-maintenance__copy" href="{$url}" class="button button-primary" style="line-height: 1.5; min-height: auto;">{$this->gettext('Administrator token URL')}</a>
<script>
;(function(){
    var a = document.getElementById('inn-maintenance__copy');
    a.addEventListener('click', function(e){
        e.preventDefault();
        try{
            var input = document.createElement('input');
            input.value = '{$url}';
            document.body.append(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            alert('{$this->gettext('URL copied.')}');
        } catch (e){
            alert('{$this->gettext('Please copy URL manually.')}');
        }
    })
})();
</script>
HTML;

            if ( ! \is_array($actions)) {
                $actions = [];
            }

            array_unshift($actions, $opts);
        }

        return $actions;
    }

    private function isWpRestful(): bool
    {
        return false !== mb_strpos($this->getCurrentUrl(), 'wp-json');
    }

    private function getCurrentUrl(): string
    {
        $scheme = is_ssl() ? 'https' : 'http';

        return "{$scheme}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }

    private function gettext(string $text): string
    {
        static $lang = null;

        if (null === $lang) {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $lang = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0] ?? self::DEFAULT_LANG;
            } else {
                $lang = self::DEFAULT_LANG;
            }
        }

        return self::LANGS[$text][$lang] ?? $text;
    }

    private function genToken(): string
    {
        return hash('sha512', AUTH_KEY);
    }

    private function getAdminRoleId(): string
    {
        global $wpdb;

        $roles = get_option("{$wpdb->prefix}user_roles") ?: get_option('wp_user_roles') ?: [];

        if ( ! $roles) {
            return '';
        }

        foreach ($roles as $roleId => $role) {
            $caps = $role['capabilities'] ?? [];

            if ( ! $caps) {
                continue;
            }

            if ((bool) ($caps['manage_options'] ?? false)) {
                return $roleId;
            }
        }

        return '';
    }

    private function loginWithAdmin(): void
    {
        $token = (string) filter_input(\INPUT_GET, self::TOKEN_KEY, \FILTER_DEFAULT);

        if ( ! $token || $token !== $this->genToken()) {
            return;
        }

        global $wpdb;

        $sql = <<<SQL
SELECT `user_id` FROM `{$wpdb->prefix}usermeta`
WHERE (
    `meta_key` = '{$wpdb->prefix}capabilities'
    OR
    `meta_key` = 'wp_capabilities'
)
AND `meta_value` LIKE %s
LIMIT 0, 1
SQL;
        $meta = $wpdb->get_row($wpdb->prepare(
            $sql,
            "%{$this->getAdminRoleId()}%"
        ));

        if ( ! $meta) {
            exit(gettext('Unable to locate admin user'));
        }

        wp_set_current_user((int) $meta->user_id);
        wp_set_auth_cookie((int) $meta->user_id, true);

        $adminUrl = get_admin_url();

        echo <<<HTML
<a href="{$adminUrl}">✔️ {$this->gettext('Logged as administrator.')}</a>
HTML;

        exit;
    }

    private function dieWithRemotePage(): void
    {
        if ($this->remoteUrl) {
            $content = file_get_contents($this->remoteUrl);

            if ($content) {
                exit($content);
            }
        }
    }

    private function dieWithLocalPage(): void
    {
        foreach (['html', 'php', 'htm'] as $ext) {
            $filePath = "{$this->localPagePath}.{$ext}";

            if ( ! is_file($filePath)) {
                continue;
            }

            include $filePath;

            exit;
        }
    }

    private function dieWithWpDie(): void
    {
        http_response_code(503);
        $lang    = str_replace('_', '-', (string) get_option('WPLANG'));
        $url     = get_bloginfo('url');
        $name    = get_bloginfo('name');
        $content = sprintf(
            $this->gettext('%1$s in maintenance, we will come back soon! <small>(Auto-refresh in %2$d minutes)</small>'),
            "<a href=\"{$url}\">{$name}</a>",
            $this->refreshMinutes
        ) . $this->getRetryJs();
        echo <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
<title>{$name} - {$this->gettext('Maintaining...')}</title>
<style>
body{background:#fafafa;margin:0;}
main{
    text-align:center;
    position:absolute;
    top:40%;
    color:#333;
    width:100vw;
}
</style>
</head>
<body>
    <main>{$content}</main>
</body>
</html>
HTML;

        exit;
    }

    private function getRetryJs(): string
    {
        $seconds = $this->refreshMinutes * 60 * 1000;

        return <<<HTML
<script>
;(() => {
  setInterval(() => {
    location.reload(true)
  }, {$seconds})
})();</script>
HTML;
    }
}

new MaintenanceMode();
