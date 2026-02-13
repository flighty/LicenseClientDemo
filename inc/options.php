<?php
/**
 * 轻狂授权管理系统 - WordPress 后台设置页面
 *
 * 本文件提供授权管理的后台界面：
 * - 管理菜单：在 WordPress 后台添加"轻狂授权"菜单
 * - 设置页面：显示授权状态、绑定授权、刷新授权等功能
 * - 插件链接：在插件列表中添加"设置"链接
 */

/**
 * ============================================
 * 添加管理菜单
 * ============================================
 *
 * 在 WordPress 后台添加一个顶级菜单"轻狂授权"
 * 菜单图标使用 WordPress 内置的 shield-alt 图标
 * 优先级为 80，显示在"设置"和"工具"之间
 */
add_action('admin_menu', function() {
    add_menu_page(
        '轻狂授权设置',        // 页面标题
        '轻狂授权',             // 菜单标题
        'manage_options',      // 权限要求
        'qk-license',          // 菜单 slug
        'qk_license_render_settings_page',  // 回调函数
        'dashicons-shield-alt', // 图标
        80                     // 位置
    );
});

/**
 * ============================================
 * 添加插件操作链接
 * ============================================
 *
 * 在 WordPress 插件列表页面，为插件添加"设置"链接
 * 点击后直接跳转到授权设置页面
 */
add_filter('plugin_action_links_' . plugin_basename(dirname(__FILE__) . '/../wordpress-license-integration-example.php'), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=qk-license') . '">设置</a>';

    // 将设置链接添加到"禁用"按钮的左边
    array_splice($links, 1, 0, $settings_link);

    return $links;
});

/**
 * ============================================
 * 渲染设置页面
 * ============================================
 *
 * 显示授权状态和授权管理界面
 */
function qk_license_render_settings_page()
{
    $storage = qk_license_system()->get_storage();

    // 获取授权数据
    $licenseData = $storage->get();

    // 获取当前授权码（用于回填）
    $licenseKey = $licenseData ? $licenseData['license_key'] : '';

    ?>
    <div class="wrap">
        <h1>轻狂授权设置</h1>

        <!-- 成功提示 -->
        <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
            <div class="notice notice-success is-dismissible">
                <p>授权设置已保存！</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['refreshed']) && $_GET['refreshed'] === 'true'): ?>
            <div class="notice notice-success is-dismissible">
                <p>授权状态已刷新！</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['unbound']) && $_GET['unbound'] === 'true'): ?>
            <div class="notice notice-success is-dismissible">
                <p>已解除授权绑定！</p>
            </div>
        <?php endif; ?>

        <!-- 授权状态卡片 -->
        <div id="qk-license-status-card" style="max-width: 800px; margin: 20px 0;">
            <?php if ($licenseData): ?>
                <!-- 已设置授权 -->
                <?php if ($licenseData['is_valid']): ?>
                    <div class="notice notice-success" style="padding: 20px;">
                        <h2 style="margin-top: 0;">
                            <span class="dashicons dashicons-yes-alt" style="color: green; font-size: 24px;"></span>
                            已授权
                        </h2>

                        <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                            <!-- 产品名称 -->
                            <?php if (!empty($licenseData['product_name'])): ?>
                                <tr>
                                    <td><strong>产品名称：</strong></td>
                                    <td><?php echo esc_html($licenseData['product_name']); ?></td>
                                </tr>
                            <?php endif; ?>

                            <!-- 绑定域名 -->
                            <?php if (!empty($licenseData['bound_domains'])): ?>
                                <tr>
                                    <td><strong>绑定域名：</strong></td>
                                    <td>
                                        <?php foreach ($licenseData['bound_domains'] as $domain): ?>
                                            <code><?php echo esc_html($domain); ?></code>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <!-- 过期时间 -->
                            <?php if (!empty($licenseData['expires_at'])): ?>
                                <tr>
                                    <td><strong>过期时间：</strong></td>
                                    <td><?php echo esc_html($licenseData['expires_at']); ?></td>
                                </tr>
                            <?php endif; ?>

                            <!-- 最后检查时间 -->
                            <tr>
                                <td><strong>最后检查时间：</strong></td>
                                <td><?php echo esc_html($licenseData['last_check']); ?></td>
                            </tr>

                            <!-- 检查消息 -->
                            <?php if (!empty($licenseData['last_check_message'])): ?>
                                <tr>
                                    <td><strong>检查消息：</strong></td>
                                    <td><?php echo esc_html($licenseData['last_check_message']); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>

                        <!-- 操作按钮 -->
                        <div style="margin-top: 20px;">
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=qk_unbind_license'), 'qk_unbind_license'); ?>"
                               class="button button-secondary"
                               onclick="return confirm('确定要解除授权绑定吗？');">
                                <span class="dashicons dashicons-no"></span> 解除授权
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=qk_refresh_license'), 'qk_refresh_license'); ?>"
                               class="button button-primary">
                                <span class="dashicons dashicons-update"></span> 刷新授权状态
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- 授权无效 -->
                    <div class="notice notice-error" style="padding: 20px;">
                        <h2 style="margin-top: 0;">
                            <span class="dashicons dashicons-dismiss" style="color: red; font-size: 24px;"></span>
                            未授权
                        </h2>

                        <p><strong>最后检查时间：</strong> <?php echo esc_html($licenseData['last_check']); ?></p>
                        <p><strong>检查消息：</strong> <?php echo esc_html($licenseData['last_check_message']); ?></p>

                        <div style="margin-top: 20px;">
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=qk_unbind_license'), 'qk_unbind_license'); ?>"
                               class="button button-secondary">
                                <span class="dashicons dashicons-trash"></span> 清除授权信息
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- 未设置授权 -->
                <div class="notice notice-info" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-info" style="font-size: 24px;"></span>
                        未设置授权
                    </h2>
                    <p>请输入授权码进行绑定</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 授权码输入表单（仅在未授权或授权无效时显示） -->
        <?php if (!$licenseData || !$licenseData['is_valid']): ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="max-width: 800px;">
                <input type="hidden" name="action" value="qk_save_license">
                <?php wp_nonce_field('qk_save_license', 'qk_license_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">授权码</th>
                        <td>
                            <input type="text"
                                   id="license_key"
                                   name="license_key"
                                   value="<?php echo esc_attr($licenseKey); ?>"
                                   class="regular-text"
                                   placeholder="请输入授权码"
                                   required
                                   style="width: 100%; max-width: 400px;">
                            <p class="description">
                                请在轻狂授权管理系统后台获取您的授权码
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('绑定授权', 'primary'); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
