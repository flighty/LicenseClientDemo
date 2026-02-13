<?php
/**
 * Plugin Name: 轻狂授权管理系统 - WordPress 插件集成示例
 * Plugin URI: https://your-website.com/
 * Description: 演示如何将轻狂授权系统集成到 WordPress 插件中
 * Version: 2.1.0
 * Author: Your Name
 * License: GPL v2 or later
 *
 * 功能说明：
 * - 授权验证：向授权服务器验证授权码有效性
 * - 自动检查：后台自动定期检查授权状态
 * - 授权绑定：用户可以输入授权码进行绑定
 * - 授权管理：查看授权状态、刷新授权、解除授权
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================
 * 引入 SDK 和功能文件
 * ============================================
 */

// 引入 LicenseClient SDK - 授权验证客户端
require_once plugin_dir_path(__FILE__) . 'inc/LicenseClient.php';

// 引入核心功能文件 - 存储类和授权系统类
require_once plugin_dir_path(__FILE__) . 'inc/functions.php';

// 引入后台设置页面 - 管理菜单和设置界面
require_once plugin_dir_path(__FILE__) . 'inc/options.php';

/**
 * ============================================
 * 初始化授权系统
 * ============================================
 *
 * 在 plugins_loaded 钩子中实例化授权系统，确保：
 * 1. admin_post_ 钩子在 admin-post.php 加载前注册
 * 2. 其他 WordPress 功能已加载完成
 */
add_action('plugins_loaded', function() {
    qk_license_system();
}, 5);

/**
 * ============================================
 * 插件激活/停用钩子
 * ============================================
 */

/**
 * 插件激活时的初始化操作
 * - 设置定时任务，用于定期检查授权状态
 */
register_activation_hook(__FILE__, function() {
    $system = new QK_License_System();
    $system->activate();
});

/**
 * 插件停用时的清理操作
 * - 清除授权检查的定时任务
 */
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('qk_license_check_event');
});
