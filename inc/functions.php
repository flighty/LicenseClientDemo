<?php
/**
 * 轻狂授权管理系统 - WordPress 插件核心功能
 *
 * 本文件包含授权系统的核心功能：
 * - QK_License_Storage: 授权数据存储类（使用 WordPress Options）
 * - QK_License_System: 授权验证系统主类
 * - 辅助函数：方便快捷地检查授权状态
 */

/**
 * ============================================
 * QK_License_Storage 授权数据存储类
 * ============================================
 *
 * 使用 WordPress Options 存储授权数据，无需创建数据表
 *
 * 存储的 Options：
 * - qk_license_settings: 授权设置（如授权码）
 * - qk_license_status: 授权状态缓存（包含验证结果、过期时间等）
 */
class QK_License_Storage
{
    /** @var string 设置选项名称 */
    private $settings_option_name = 'qk_license_settings';

    /** @var string 状态选项名称 */
    private $status_option_name = 'qk_license_status';

    /** @var int 缓存时间（秒），默认 24 小时 */
    private $cache_time = 86400;

    /**
     * 保存授权数据
     *
     * @param array $data 授权数据，包含以下字段：
     *               - license_key: string 授权码
     *               - is_valid: bool 授权是否有效
     *               - product_name: string 产品名称
     *               - bound_domains: array 绑定的域名列表
     *               - expires_at: string 过期时间
     *               - message: string 提示信息
     *               - response_data: array 完整的 API 响应数据
     * @return bool 是否保存成功
     */
    public function save($data)
    {
        // 保存授权码到设置选项
        $settings = get_option($this->settings_option_name, []);
        if (!empty($data['license_key'])) {
            $settings['license_key'] = $data['license_key'];
        }

        // 保存授权状态到缓存选项
        $cache_data = [
            'valid' => $data['is_valid'],
            'message' => $data['message'] ?? '',
            'data' => [],
            'expires_at' => time() + $this->cache_time,
            'last_check' => time()
        ];

        // 如果授权有效，保存详细信息
        if ($data['is_valid']) {
            $cache_data['data'] = [
                'product' => $data['product_name'] ?? '',
                'bound_domains' => $data['bound_domains'] ?? [],
                'expires_at' => $data['expires_at'] ?? null
            ];
        }

        update_option($this->status_option_name, $cache_data);
        update_option($this->settings_option_name, $settings);

        return true;
    }

    /**
     * 获取授权数据
     *
     * @param string $licenseKey 授权码（可选）
     * @return array|null 授权数据，包含以下字段：
     *                - license_key: string 授权码
     *                - is_valid: bool 授权是否有效
     *                - product_name: string 产品名称
     *                - bound_domains: array 绑定的域名列表
     *                - expires_at: string 过期时间
     *                - last_check: string 最后检查时间
     *                - last_check_message: string 检查提示信息
     */
    public function get($licenseKey = null)
    {
        $status = get_option($this->status_option_name, []);

        if (empty($status) || !isset($status['valid'])) {
            return null;
        }

        // 转换为兼容的格式
        $row = [
            'license_key' => get_option($this->settings_option_name)['license_key'] ?? '',
            'is_valid' => $status['valid'],
            'product_name' => $status['data']['product'] ?? '',
            'bound_domains' => $status['data']['bound_domains'] ?? [],
            'expires_at' => $status['data']['expires_at'] ?? null,
            'last_check' => isset($status['last_check']) ? date('Y-m-d H:i:s', $status['last_check']) : null,
            'last_check_message' => $status['message'] ?? '',
            'response_data' => $status['data'] ?? []
        ];

        return $row;
    }

    /**
     * 检查是否需要重新验证
     *
     * @param string $licenseKey 授权码（可选）
     * @return bool 是否需要重新验证
     */
    public function should_recheck($licenseKey = null)
    {
        $status = get_option($this->status_option_name);

        if (!$status) {
            return true;
        }

        if (!isset($status['expires_at']) || $status['expires_at'] <= time()) {
            return true;
        }

        return false;
    }

    /**
     * 删除授权数据
     *
     * @param string $licenseKey 授权码（可选）
     * @return bool 是否删除成功
     */
    public function delete($licenseKey = null)
    {
        delete_option($this->status_option_name);

        $settings = get_option($this->settings_option_name, []);
        if (isset($settings['license_key'])) {
            unset($settings['license_key']);
            update_option($this->settings_option_name, $settings);
        }

        return true;
    }

    /**
     * 清除授权缓存
     */
    public function clear_cache()
    {
        delete_option($this->status_option_name);
    }
}

/**
 * ============================================
 * QK_License_System 授权验证系统主类
 * ============================================
 *
 * 协调授权验证的各个组件：
 * - LicenseClient: 向授权服务器发送验证请求
 * - QK_License_Storage: 存储和管理授权数据
 * - WordPress 钩子: 处理表单提交、定时任务等
 */
class QK_License_System
{
    /** @var LicenseClient 授权验证客户端 */
    private $client;

    /** @var QK_License_Storage 授权数据存储 */
    private $storage;

    /**
     * 构造函数
     *
     * 注册所有的 WordPress 钩子
     */
    public function __construct()
    {
        $this->client = new LicenseClient(
            QK_LICENSE_API_URL,
            QK_LICENSE_SECRET_KEY
        );
        $this->storage = new QK_License_Storage();

        // 注册 admin_post_ 钩子（必须在 plugins_loaded 前注册）
        add_action('admin_post_qk_save_license', [$this, 'save_license']);
        add_action('admin_post_qk_unbind_license', [$this, 'unbind_license']);
        add_action('admin_post_qk_refresh_license', [$this, 'refresh_license']);

        // 注册定时任务
        add_action('qk_license_check_event', [$this, 'scheduled_check']);

        // 注册自动检查钩子
        add_action('plugins_loaded', [$this, 'auto_check_license'], 10);

        // 注册管理通知
        add_action('admin_notices', [$this, 'admin_notice']);
    }

    /**
     * 插件激活时设置定时任务
     *
     * 根据检查间隔设置合适的定时任务频率：
     * - <= 1 小时: 每小时检查
     * - <= 12 小时: 每 12 小时检查
     * - > 12 小时: 每天检查
     */
    public function activate()
    {
        if (!wp_next_scheduled('qk_license_check_event')) {
            $checkInterval = $this->client->getCheckInterval();

            if ($checkInterval <= 3600) {
                $schedule = 'hourly';
            } elseif ($checkInterval <= 43200) {
                $schedule = 'twicedaily';
            } else {
                $schedule = 'daily';
            }

            wp_schedule_event(time(), $schedule, 'qk_license_check_event');
        }
    }

    /**
     * 处理授权绑定表单提交
     *
     * 验证用户输入的授权码，并保存验证结果
     */
    public function save_license()
    {
        // 验证 nonce
        if (!isset($_POST['qk_license_nonce']) ||
            !wp_verify_nonce($_POST['qk_license_nonce'], 'qk_save_license')) {
            wp_die('安全验证失败');
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_die('无权限执行此操作');
        }

        // 获取授权码
        $licenseKey = sanitize_text_field($_POST['license_key'] ?? '');
        if (empty($licenseKey)) {
            wp_die('授权码不能为空');
        }

        // 验证授权
        $result = $this->client->verify($licenseKey, false, '', LicenseClient::getProductSlug());

        // 解析授权数据
        $data = [
            'license_key' => $licenseKey,
            'is_valid' => $result['valid'],
            'message' => $result['message'] ?? '验证失败',
            'response_data' => $result
        ];

        if ($result['valid'] && isset($result['data'])) {
            $data['product_name'] = $result['data']['product'] ?? null;
            $data['bound_domains'] = $result['data']['current_domains'] ?? [];
            $data['expires_at'] = $result['data']['expires_at'] ?? null;
        }

        // 保存到数据库
        $this->storage->save($data);

        // 重定向回设置页面
        wp_redirect(admin_url('admin.php?page=qk-license&updated=true'));
        exit;
    }

    /**
     * 处理解除授权
     *
     * 清除本地存储的授权数据
     */
    public function unbind_license()
    {
        // 验证 nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'qk_unbind_license')) {
            wp_die('安全验证失败');
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_die('无权限执行此操作');
        }

        // 删除授权数据
        $licenseData = $this->storage->get();
        if ($licenseData) {
            $this->storage->delete($licenseData['license_key']);
        }

        wp_redirect(admin_url('admin.php?page=qk-license&unbound=true'));
        exit;
    }

    /**
     * 处理手动刷新授权
     *
     * 强制重新验证授权，更新授权状态
     */
    public function refresh_license()
    {
        // 验证 nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'qk_refresh_license')) {
            wp_die('安全验证失败');
        }

        // 验证权限
        if (!current_user_can('manage_options')) {
            wp_die('无权限执行此操作');
        }

        // 获取授权码
        $settings = get_option('qk_license_settings', []);
        $licenseKey = $settings['license_key'] ?? '';

        if (empty($licenseKey)) {
            wp_redirect(admin_url('admin.php?page=qk-license'));
            exit;
        }

        // 重新验证授权
        $result = $this->client->verify($licenseKey, false, '', LicenseClient::getProductSlug());

        // 更新授权数据
        $data = [
            'license_key' => $licenseKey,
            'is_valid' => $result['valid'],
            'message' => $result['message'] ?? '验证失败',
            'response_data' => $result
        ];

        if ($result['valid'] && isset($result['data'])) {
            $data['product_name'] = $result['data']['product'] ?? $result['data']['product_name'] ?? null;
            $data['bound_domains'] = $result['data']['bound_domains'] ?? [];
            $data['expires_at'] = $result['data']['expires_at'] ?? null;
        }

        $this->storage->save($data);

        wp_redirect(admin_url('admin.php?page=qk-license&refreshed=true'));
        exit;
    }

    /**
     * 自动检查授权（前台访问时）
     *
     * 只在前台访问且缓存过期时才检查，避免影响性能
     */
    public function auto_check_license()
    {
        // 管理后台不自动检查
        if (is_admin()) {
            return;
        }

        // 检查是否需要重新验证
        if (!$this->storage->should_recheck()) {
            return;
        }

        // 获取授权码
        $settings = get_option('qk_license_settings', []);
        $licenseKey = $settings['license_key'] ?? '';

        if (empty($licenseKey)) {
            return;
        }

        // 重新验证授权
        $result = $this->client->verify($licenseKey, false, '', LicenseClient::getProductSlug());

        // 更新授权数据
        $data = [
            'license_key' => $licenseKey,
            'is_valid' => $result['valid'],
            'message' => $result['message'] ?? '验证失败',
            'response_data' => $result
        ];

        if ($result['valid'] && isset($result['data'])) {
            $data['product_name'] = $result['data']['product'] ?? $result['data']['product_name'] ?? null;
            $data['bound_domains'] = $result['data']['bound_domains'] ?? [];
            $data['expires_at'] = $result['data']['expires_at'] ?? null;
        }

        $this->storage->save($data);
    }

    /**
     * 定时任务：检查授权
     *
     * 通过 WordPress 定时任务定期检查授权状态
     */
    public function scheduled_check()
    {
        // 获取授权码
        $settings = get_option('qk_license_settings', []);
        $licenseKey = $settings['license_key'] ?? '';

        if (empty($licenseKey)) {
            return;
        }

        // 重新验证授权
        $result = $this->client->verify($licenseKey, false, '', LicenseClient::getProductSlug());

        // 更新授权数据
        $data = [
            'license_key' => $licenseKey,
            'is_valid' => $result['valid'],
            'message' => $result['message'] ?? '验证失败',
            'response_data' => $result
        ];

        if ($result['valid'] && isset($result['data'])) {
            $data['product_name'] = $result['data']['product'] ?? $result['data']['product_name'] ?? null;
            $data['bound_domains'] = $result['data']['bound_domains'] ?? [];
            $data['expires_at'] = $result['data']['expires_at'] ?? null;
        }

        $this->storage->save($data);
    }

    /**
     * 管理通知：授权即将过期或已失效
     *
     * 在 WordPress 后台显示授权状态通知
     */
    public function admin_notice()
    {
        // 设置页面不显示
        if (isset($_GET['page']) && $_GET['page'] === 'qk-license') {
            return;
        }

        // 获取授权数据
        $licenseData = $this->storage->get();
        if (!$licenseData) {
            return;
        }

        // 授权已失效
        if (!$licenseData['is_valid']) {
            echo '<div class="notice notice-error is-dismissible">
                    <p><strong>授权已失效：</strong> ' . esc_html($licenseData['last_check_message']) . '</p>
                    <p><a href="' . admin_url('admin.php?page=qk-license') . '">前往授权设置页面</a></p>
                  </div>';
        }
        // 授权即将过期（7天内）
        elseif (!empty($licenseData['expires_at'])) {
            $expiresAt = strtotime($licenseData['expires_at']);
            $now = current_time('timestamp');
            $daysLeft = ceil(($expiresAt - $now) / (60 * 60 * 24));

            if ($daysLeft <= 7 && $daysLeft > 0) {
                echo '<div class="notice notice-warning is-dismissible">
                        <p><strong>授权即将过期：</strong> 还有 ' . $daysLeft . ' 天</p>
                        <p><a href="' . admin_url('admin.php?page=qk-license') . '">查看详情</a></p>
                      </div>';
            }
        }
    }

    /**
     * 获取存储实例
     *
     * @return QK_License_Storage
     */
    public function get_storage()
    {
        return $this->storage;
    }
}

/**
 * ============================================
 * 辅助函数
 * ============================================
 */

/**
 * 检查授权是否有效
 *
 * @param bool $autoCheck 是否自动检查（默认 true）
 * @return bool 授权是否有效
 */
function qk_license_is_valid($autoCheck = true)
{
    static $system = null;
    if ($system === null) {
        $system = new QK_License_System();
    }

    $licenseData = $system->get_storage()->get();

    if (!$licenseData) {
        return false;
    }

    // 不需要自动检查，直接返回缓存状态
    if (!$autoCheck) {
        return (bool) $licenseData['is_valid'];
    }

    // 需要自动检查
    if ($system->get_storage()->should_recheck()) {
        $system->auto_check_license();
        $licenseData = $system->get_storage()->get();
    }

    return (bool) $licenseData['is_valid'];
}

/**
 * 获取授权数据
 *
 * @return array|null 授权数据
 */
function qk_license_get_data()
{
    static $system = null;
    if ($system === null) {
        $system = new QK_License_System();
    }

    return $system->get_storage()->get();
}

/**
 * 在授权有效时执行某个功能
 *
 * @param callable $callback 回调函数
 * @param bool $autoCheck 是否自动检查（默认 true）
 */
function qk_license_require_valid($callback, $autoCheck = true)
{
    if (qk_license_is_valid($autoCheck)) {
        call_user_func($callback);
    }
}

/**
 * 获取授权系统全局实例
 *
 * @return QK_License_System
 */
function qk_license_system() {
    static $instance = null;
    if ($instance === null) {
        $instance = new QK_License_System();
    }
    return $instance;
}
