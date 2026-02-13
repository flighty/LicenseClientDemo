<?php
/**
 * 轻狂授权管理系统 - PHP 客户端 SDK
 *
 * 这是一个轻量级的授权验证客户端，可以轻松集成到任何 PHP 项目中
 * 支持独立 PHP 项目、WordPress、以及其他 PHP 框架
 *
 * 使用示例：
 * ```php
 * $client = new LicenseClient();
 * $result = $client->verify('LICENSE-KEY', $_SERVER['HTTP_HOST']);
 * if ($result['valid']) {
 *     // 授权有效，继续执行
 * } else {
 *     // 授权无效，显示错误信息
 *     echo $result['message'];
 * }
 * ```
 */

/**
 * ============================================
 * 配置区域
 * ============================================
 *
 * 在这里修改授权系统的配置参数
 * 这些常量可以在引入此文件前定义，以覆盖默认值
 */

/**
 * 授权验证接口地址
 *
 * 这是授权服务器的完整 URL，必须指向有效的 verify.php 文件
 */
defined('QK_LICENSE_API_URL') || define('QK_LICENSE_API_URL', 'http://你的授权站域名/api/verify.php');

/**
 * API 密钥
 *
 * 用于生成签名，增强安全性
 * 如果授权服务器启用了签名验证，必须提供正确的密钥
 */
defined('QK_LICENSE_SECRET_KEY') || define('QK_LICENSE_SECRET_KEY', 'lrIBaveYHMyv3F30');

/**
 * 产品标识符
 *
 * 用于区分不同的产品，必须在授权管理系统中创建对应的产品
 * 每个产品有独立的授权码和授权规则
 */
defined('QK_LICENSE_PRODUCT_SLUG') || define('QK_LICENSE_PRODUCT_SLUG', '对应的产品标识符');

/**
 * 自动检查间隔（秒）
 *
 * 定义授权缓存的有效时间，减少对授权服务器的请求次数
 * 默认 86400 秒 = 24 小时
 */
defined('QK_LICENSE_CHECK_INTERVAL') || define('QK_LICENSE_CHECK_INTERVAL', 86400);

/**
 * ============================================
 * LicenseClient 类
 * ============================================
 *
 * 授权验证客户端类，提供授权验证相关的所有功能
 */
class LicenseClient
{
    /** @var string 授权服务器地址 */
    private $apiUrl;

    /** @var string API 密钥 */
    private $secretKey;

    /** @var string 最后一次错误信息 */
    private $lastError = '';

    /**
     * 构造函数
     *
     * @param string $apiUrl 授权验证接口地址（可选，默认使用配置常量）
     * @param string $secretKey API 密钥（可选，默认使用配置常量）
     */
    public function __construct($apiUrl = null, $secretKey = null)
    {
        $this->apiUrl = $apiUrl ? rtrim($apiUrl, '/') : QK_LICENSE_API_URL;
        $this->secretKey = $secretKey !== null ? $secretKey : QK_LICENSE_SECRET_KEY;
    }

    /**
     * 验证授权
     *
     * 向授权服务器发送验证请求，检查授权码是否有效
     *
     * @param string $licenseKey 授权码
     * @param string $domain 域名（可选，默认当前域名）
     * @param string $version 插件/系统版本（可选）
     * @param string $product 产品标识（可选）
     * @return array 验证结果，包含以下字段：
     *               - valid: bool 授权是否有效
     *               - message: string 提示信息
     *               - code: string 错误代码（如果失败）
     *               - data: array 授权详细信息（如果成功）
     */
    public function verify($licenseKey, $domain = '', $version = '', $product = '')
    {
        // 默认域名
        if (empty($domain)) {
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        }

        // 构建请求数据
        $data = [
            'license_key' => $licenseKey,
            'domain' => $domain
        ];

        // 可选参数
        if (!empty($version)) {
            $data['version'] = $version;
        }

        if (!empty($product)) {
            $data['product'] = $product;
        }

        // 添加签名（如果提供了密钥）
        if (!empty($this->secretKey)) {
            $data['signature'] = hash_hmac('sha256', $licenseKey . $domain, $this->secretKey);
        }

        // 发送验证请求
        $result = $this->sendRequest($data);

        return $result;
    }

    /**
     * 发送 HTTP 请求到授权服务器
     *
     * @param array $data 请求数据（JSON 格式）
     * @return array 响应结果
     */
    private function sendRequest($data)
    {
        $ch = curl_init($this->apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        // 处理错误
        if ($error) {
            $this->lastError = $error;
            return [
                'valid' => false,
                'message' => '请求失败: ' . $error,
                'code' => 'REQUEST_ERROR'
            ];
        }

        // 解析响应
        $result = json_decode($response, true);
        if ($result === null) {
            $this->lastError = 'JSON解析失败';
            return [
                'valid' => false,
                'message' => '服务器返回无效数据',
                'code' => 'INVALID_RESPONSE'
            ];
        }

        return $result;
    }

    /**
     * 获取最后一次错误信息
     *
     * @return string 错误信息
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * 检查授权是否有效（便捷方法）
     *
     * @param string $licenseKey 授权码
     * @param string $domain 域名
     * @param string $version 版本
     * @param string $product 产品标识
     * @return bool 授权是否有效
     */
    public function isValid($licenseKey, $domain = '', $version = '', $product = '')
    {
        $result = $this->verify($licenseKey, $domain, $version, $product);
        return (bool) $result['valid'];
    }

    /**
     * 获取配置的检查间隔
     *
     * @return int 检查间隔（秒）
     */
    public static function getCheckInterval()
    {
        return QK_LICENSE_CHECK_INTERVAL;
    }

    /**
     * 获取配置的 API 地址
     *
     * @return string API 地址
     */
    public static function getApiUrl()
    {
        return QK_LICENSE_API_URL;
    }

    /**
     * 获取配置的 API 密钥
     *
     * @return string API 密钥
     */
    public static function getSecretKey()
    {
        return QK_LICENSE_SECRET_KEY;
    }

    /**
     * 获取配置的产品标识符
     *
     * @return string 产品标识符
     */
    public static function getProductSlug()
    {
        return QK_LICENSE_PRODUCT_SLUG;
    }
}
