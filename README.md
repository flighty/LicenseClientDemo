

本文档说明如何将轻狂授权管理系统的授权验证功能快速集成到 WordPress 插件中。
本示例需要与轻狂授权管理系统配合使用：https://www.suujee.com/2604622.html

## 快速开始

### 1. 准备工作

1. 复制整个 `LicenseClientDemo` 文件夹到你的 WordPress 插件目录
2. 将 `wordpress-license-integration-example.php` 重命名为你的插件名称
3. 修改插件配置（在 `inc/LicenseClient.php` 中）：
   - `QK_LICENSE_API_URL`：授权验证接口地址
   - `QK_LICENSE_SECRET_KEY`：API 密钥（重要！必须与授权管理系统config.php里设置的API密钥一致）
   - `QK_LICENSE_PRODUCT_SLUG`：产品标识符（重要！必须与后台创建的产品标识符一致）

### 2. 配置参数

在 inc/LicenseClient.php 中修改以下配置：

```PHP
// 授权验证接口地址
defined('QK_LICENSE_API_URL') || define('QK_LICENSE_API_URL', 'http://你的授权站域名/api/verify.php');

// API 密钥（用于签名验证，必须与授权服务器一致）
defined('QK_LICENSE_SECRET_KEY') || define('QK_LICENSE_SECRET_KEY', 'lrIBaveYHMyv3F30');

// 产品标识符（重要！必须与授权管理系统中创建的产品标识一致）
defined('QK_LICENSE_PRODUCT_SLUG') || define('QK_LICENSE_PRODUCT_SLUG', '对应的产品标识符');

// 自动检查间隔（秒），默认 24 小时
defined('QK_LICENSE_CHECK_INTERVAL') || define('QK_LICENSE_CHECK_INTERVAL', 86400);
```

### 3. 产品标识配置说明

**重要**：`QK_LICENSE_PRODUCT_SLUG` 必须与授权管理后台创建的产品标识符一致，否则验证时会返回"授权产品不匹配"错误。

例如：
- 如果在后台创建的产品标识是 `zibll`，则配置为：`define('QK_LICENSE_PRODUCT_SLUG', 'zibll');`

### 3. 激活插件

1. 将插件上传到 WordPress 的 `wp-content/plugins/` 目录
2. 在 WordPress 后台启用插件
3. 进入「轻狂授权」菜单
4. 输入授权码并点击「绑定授权」按钮

## 功能特性

### 1. 授权设置页面

插件会在 WordPress 管理后台创建一个顶级菜单「轻狂授权」，包含以下功能：

#### 授权状态显示

- **已授权状态**：显示以下信息
  - 产品名称
  - 绑定的域名列表
  - 过期时间
  - 最后检查时间
  - 检查消息
  - 解除授权按钮
  - 刷新授权状态按钮

- **未授权状态**：显示以下信息
  - 最后检查时间
  - 检查消息
  - 清除授权信息按钮

- **未设置授权**：提示输入授权码

#### 授权码绑定

提供一个文本框输入授权码，点击「绑定授权」按钮后：
1. 调用 LicenseClient.php 进行授权验证
2. 将验证结果存储到数据库
3. 显示授权状态

### 2. 数据存储

插件使用 WordPress Options API 存储授权数据，无需创建数据库表：

存储的 Options：
- `qk_license_settings`: 授权设置（授权码等）选项名请自行更改，对应你的插件名称即可。
- `qk_license_status`: 授权状态缓存（验证结果、过期时间等）选项名请自行更改，对应你的插件名称即可。

这种方式的优势：
- 无需创建数据表，减少数据库操作
- 自动利用 WordPress 的缓存机制
- 更容易迁移和备份

### 3. 自动授权检查

插件支持两种自动检查机制：

#### 方式一：页面加载时检查

在前台访问时，如果缓存过期会自动检查授权：
- 如果距离上次检查超过配置的间隔时间（默认24小时）
- 会自动调用授权接口进行验证
- 更新 WordPress Options 中的授权状态
- 注意：管理后台不会自动检查，避免影响性能

#### 方式二：定时任务检查

插件会注册一个 WordPress 定时任务，根据检查间隔自动执行：
- 检查间隔 <= 1 小时：每小时检查
- 检查间隔 <= 12 小时：每 12 小时检查
- 检查间隔 > 12 小时：每天检查

### 4. 代码结构

插件采用模块化设计，代码分离清晰：

```
LicenseClientDemo/
├── wordpress-license-integration-example.php  # 插件入口文件
├── inc/
│   ├── LicenseClient.php                      # SDK（含配置常量）
│   ├── functions.php                          # 核心功能类和函数
│   └── options.php                            # 后台设置页面
└── README.md
```

- `LicenseClient.php`: 授权验证 SDK，包含配置常量（API地址、密钥、产品标识）
- `functions.php`: 授权存储类、授权系统主类、辅助函数
- `options.php`: 后台管理界面和菜单

## API 接口说明

授权验证接口：`POST /api/verify.php`

### 请求参数

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| license_key | string | 是 | 授权码 |
| domain | string | 是 | 授权域名 |
| product | string | 是 | 产品标识 |
| version | string | 否 | 插件版本号 |
| signature | string | 否 | HMAC 签名（可选） |

### 响应格式

```json
{
  "valid": true,
  "message": "授权有效",
  "code": "SUCCESS",
  "data": {
    "expires_at": "2025-12-31 23:59:59",
    "max_domains": 5,
    "bound_domains": ["example.com", "www.example.com"],
    "product": "My Plugin",
    "product_name": "My Plugin"
  }
}
```

### 响应字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| valid | boolean | 授权是否有效 |
| message | string | 提示信息 |
| code | string | 响应代码（SUCCESS, INVALID_LICENSE, EXPIRED 等） |
| data | object | 授权详情数据 |

## 使用示例

### 示例 1：基本验证

```php
if (qk_license_is_valid()) {
    // 授权有效，显示内容
    echo '这是一个授权功能的内容';
} else {
    echo '请先激活授权';
}
```

### 示例 2：使用回调函数

```php
qk_license_require_valid(function() {
    // 仅在授权有效时执行
    echo '高级功能已启用';
});
```

### 示例 3：在类方法中使用

```php
class My_Plugin_Feature {
    public function init() {
        if (!qk_license_is_valid()) {
            return; // 授权无效，不执行
        }

        // 初始化功能
        $this->setup_hooks();
    }
}
```

### 示例 4：获取详细授权信息

```php
$data = qk_license_get_data();

if ($data && $data['is_valid']) {
    echo '产品名称: ' . $data['product_name'];
    echo '过期时间: ' . $data['expires_at'];
    echo '绑定域名: ' . implode(', ', $data['bound_domains']);
}
```

### 示例 5：不自动检查，直接使用缓存状态

```php
// 不会触发远程验证，直接使用数据库缓存状态
if (qk_license_is_valid(false)) {
    echo '授权有效（缓存状态）';
}
```

## 数据存储类

插件提供了一个 `QK_License_Storage` 类用于管理授权数据（使用 WordPress Options）：

### 保存授权数据

```php
$storage = new QK_License_Storage();
$storage->save([
    'license_key' => 'LICENSE-KEY',
    'is_valid' => true,
    'product_name' => 'My Plugin',
    'bound_domains' => ['example.com'],
    'expires_at' => '2025-12-31 23:59:59',
    'message' => '授权有效',
    'response_data' => $result
]);
```

### 获取授权数据

```php
$storage = new QK_License_Storage();

// 获取最新授权数据
$data = $storage->get();
```

### 检查是否需要重新验证

```php
$storage = new QK_License_Storage();

if ($storage->should_recheck()) {
    // 需要重新验证
}
```

### 删除授权数据

```php
$storage->delete();
```

### 清除授权缓存

```php
$storage->clear_cache();
```

## 自定义配置

### 修改自动检查间隔

```php
// 在插件文件顶部修改
define('QK_LICENSE_CHECK_INTERVAL', 7200); // 改为 2 小时
```

### 修改定时任务频率

修改 `activate()` 方法中的定时任务：

```php
public function activate() {
    // 注册定时任务
    if (!wp_next_scheduled('qk_license_check_event')) {
        // 改为每 6 小时检查一次
        wp_schedule_event(time(), 'twicedaily', 'qk_license_check_event');
    }
}
```

### 自定义授权失效处理

修改 `admin_notice()` 方法中的显示逻辑：

```php
public function admin_notice() {
    $licenseData = $this->storage->get();

    if (!$licenseData || !$licenseData['is_valid']) {
        // 自定义失效处理
        // 例如：停用插件功能、重定向等
    }
}
```

## 后台自动授权检查

插件通过以下机制实现后台自动检查：

### 1. 页面加载检查（实时）

```php
public function auto_check_license() {
    // 获取授权数据
    $licenseData = $this->storage->get();

    if (!$licenseData) {
        return;
    }

    // 检查是否需要重新验证
    if (!$this->storage->should_recheck($licenseData['license_key'])) {
        return;
    }

    // 重新验证授权
    $result = $this->client->verify($licenseData['license_key'], false);

    // 更新授权数据
    // ...
}
```

### 2. 定时任务检查（计划）

```php
public function scheduled_check() {
    // 每小时执行一次
    $result = $this->client->verify($licenseKey, false);

    // 更新授权数据
    // ...
}
```

两种机制可以同时启用，也可以只启用其中一种：

- **只使用页面加载检查**：注释掉定时任务相关代码
- **只使用定时任务检查**：注释掉 `auto_check_license()` 的调用

## 安全建议

1. **保护 API 密钥**：不要将 `QK_LICENSE_SECRET_KEY` 硬编码在插件中，建议从配置文件读取，除非你在发布时将LicenseClient.php加密
2. **使用 HTTPS**：授权验证接口应使用 HTTPS 协议
3. **设置合理的检查间隔**：根据授权的重要程度调整检查频率
4. **错误处理**：捕获验证失败的情况，提供友好的错误提示
**数据验证**：对用户输入的授权码进行验证和清理

## 常见问题

### Q: 授权验证失败怎么办？

A: 检查以下几点：
1. 授权码是否正确
2. 授权域名是否与绑定的域名一致
3. 授权是否已过期
4. API 接口地址是否正确
5. 服务器是否可以访问授权接口

### Q: 如何在多个站点使用同一个授权？

A: 需要在授权管理后台为授权码添加多个域名绑定，修改最多绑定域名数量即可。

### Q: 自动检查会影响性能吗？

A: 使用了时间间隔控制（默认24小时），不会频繁请求，对性能影响很小。

### Q: 如何停用自动检查？

A: 两种方式：
1. 将 `QK_LICENSE_CHECK_INTERVAL` 设置为一个很大的值
2. 注释掉 `auto_check_license()` 和定时任务相关代码

### Q: 授权数据存储在哪里？

A: 存储在 WordPress 的 Options 表中（`wp_options`），选项名为 `qk_license_settings` 和 `qk_license_status`。选项名请自行更改，对应你的插件名称即可。

### Q: 如何清理授权数据？

A: 在授权设置页面点击「解除授权」或「清除授权信息」按钮。

### Q: 如何手动刷新授权状态？

A: 在授权设置页面点击「刷新授权状态」按钮。

## 常见问题

### Q: 绑定授权时提示"授权产品不匹配"怎么办？

A: 检查 `inc/LicenseClient.php` 中的 `QK_LICENSE_PRODUCT_SLUG` 配置是否与授权管理后台创建的产品标识一致。例如：
- 后台产品标识为 `zibll`，则配置为：`define('QK_LICENSE_PRODUCT_SLUG', 'zibll');`

## 版本历史

### 3.0.0 (2026-02-13) 
- 重构为模块化代码结构
- LicenseClient.php 放到 inc/ 目录
- 核心功能分离到 inc/functions.php
- UI 代码分离到 inc/options.php
- 产品标识配置移到 LicenseClient.php
- 改用 WordPress Options API 存储授权数据（不再使用数据表）
- 修复白屏错误（Hook 注册时机问题）
- 清理调试代码，添加详细注释

### 2.0.0 (2026-01-12)
- 实现完整的授权管理页面
- 支持授权状态显示（产品名称、绑定域名、过期时间等）
- 支持后台自动授权检查（页面加载 + 定时任务）
- 数据库存储授权数据
- 支持手动刷新授权状态
- 支持解除授权绑定
- 管理后台授权通知

### 1.0.0
- 初始版本发布
- 基础授权验证功能

## 技术支持

如有问题，请联系作者少轻狂或查看授权管理系统文档。
