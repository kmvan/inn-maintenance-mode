<?php

declare(strict_types = 1);

// Plugin Name: INN Maintenance Mode | INN 维护模式
// Plugin URI: https://inn-studio.com/maintenance-mode
// Description: The site maintenance-mode plugin | 开启站点维护模式插件，内置两种自定义功能，请参见官网说明。
// Author: Km.Van
// Version: 3.2.0
// Author URI: https://inn-studio.com
// PHP Required: 7.3

namespace InnStudio\Plugins\MaintenanceMode;

\defined('AUTH_KEY') || \http_response_code(500) && die;

class MaintenanceMode
{
    const RETRY_MINUTES = 5;

    const DEFAULT_LANG = 'en-US';

    const LANGS = [
        'Maintaining...' => [
            'zh-CN' => '维护中……',
        ],
        '%1$s in maintenance, we will come back soon! <small>(Auto-refresh in %2$d minutes)</small>' => [
            'zh-CN' => '%1$s 正在更新维护中，请稍后再来吧！（页面 %2$d 分钟自动刷新）',
        ],
        'Logged as administrator' => [
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
    ];

    private $removePageUrl = '';

    private $localPagePath = \ABSPATH . '/maintenance';

    public function __construct(string $removePageUrl = '')
    {
        $removePageUrl = (string) \filter_var($removePageUrl, \FILTER_VALIDATE_URL);

        if ($removePageUrl) {
            $this->removePageUrl = $removePageUrl;
        }

        \add_action('plugins_loaded', [$this, 'filterPluginsLoaded']);
        \add_filter('plugin_action_links', [$this, 'filterActionLink'], 10, 2);
    }

    public function filterPluginsLoaded(): void
    {
        if (\defined('DOING_AJAX') && \DOING_AJAX) {
            return;
        }

        if ($this->isWpRest()) {
            return;
        }

        if (\current_user_can('manage_options')) {
            return;
        }

        $this->loginWithAdmin();

        \header('Retry-After: ' . self::RETRY_MINUTES * 60);

        $this->dieWithRemotePage();
        $this->dieWithLocalPage();
        $this->dieWithWpDie();
    }

    public function filterActionLink($actions, string $pluginFile): array
    {
        if (false !== \stripos($pluginFile, \basename(__DIR__))) {
            $adminUrl = get_admin_url();
            $url      = "{$adminUrl}?token={$this->genToken()}";
            $opts     = <<<HTML
<a id="inn-maintenance__copy" href="{$url}" class="button button-primary" style="line-height: 1.5; height: auto;">{$this->_('Administrator token URL')}</a>
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
            alert('{$this->_('URL copied.')}');
        } catch (e){
            alert('{$this->_('Please copy URL manually.')}');
        }
    })
})();
</script>
HTML;

            if ( ! \is_array($actions)) {
                $actions = [];
            }

            \array_unshift($actions, $opts);
        }

        return $actions;
    }

    private function isWpRest(): bool
    {
        return false !== \strpos($this->getCurrentUrl(), 'wp-json');
    }

    private function getCurrentUrl(): string
    {
        $scheme = \is_ssl() ? 'https' : 'http';

        return "{$scheme}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }

    private function _(string $text): string
    {
        static $lang = null;

        if (null === $lang) {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $lang = \explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0] ?? self::DEFAULT_LANG;
            } else {
                $lang = self::DEFAULT_LANG;
            }
        }

        return self::LANGS[$text][$lang] ?? $text;
    }

    private function genToken(): string
    {
        return \hash('sha512', \AUTH_KEY);
    }

    private function loginWithAdmin(): void
    {
        $token = (string) \filter_input(\INPUT_GET, 'token', \FILTER_SANITIZE_STRING);

        if ( ! $token || $token !== $this->genToken()) {
            return;
        }

        global $wpdb;

        $metaValue = 'a:1:{s:13:"administrator";b:1;}';
        $sql       = <<<SQL
SELECT `user_id` FROM `{$wpdb->prefix}usermeta`
WHERE `meta_key` = 'wp_capabilities'
AND `meta_value` = %s
SQL;
        $meta = $wpdb->get_row($wpdb->prepare(
            $sql,
            $metaValue
        ));

        if ( ! $meta) {
            return;
        }

        \wp_set_current_user($meta->user_id);
        \wp_set_auth_cookie($meta->user_id, true);

        die($this->_('Logged as administrator.'));
    }

    private function dieWithRemotePage(): void
    {
        if ($this->removePageUrl) {
            $content = \file_get_contents($this->removePageUrl);

            echo $content;

            die;
        }
    }

    private function dieWithLocalPage(): void
    {
        foreach (['html', 'php', 'htm'] as $ext) {
            $filePath = "{$this->localPagePath}.{$ext}";

            if ( ! \is_file($filePath)) {
                continue;
            }

            include $filePath;

            die;
        }
    }

    private function dieWithWpDie(): void
    {
        $url  = \get_bloginfo('url');
        $name = \get_bloginfo('name');

        \wp_die(
            \sprintf(
                $this->_('%1$s in maintenance, we will come back soon! <small>(Auto-refresh in %2$d minutes)</small>'),
                "<a href=\"{$url}\">{$name}</a>",
                self::RETRY_MINUTES
            ) . $this->getRetryJs(),
            $this->_('Maintaining...'),
            [
                'response' => 503,
            ]
        );
    }

    private function getRetryJs(): string
    {
        $seconds = self::RETRY_MINUTES * 60 * 1000;

        return <<<HTML
<script>setInterval(function(){location.reload(true)}, {$seconds});</script>
HTML;
    }
}

new MaintenanceMode();
