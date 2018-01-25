<?php
/**
 * @link http://chenkby.com
 * @copyright Copyright (c) 2018 ChenGuanQun
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace chenkby\applepay;

/**
 * 苹果内购
 * 错误码
 * 21000 App Store不能读取你提供的JSON对象
 * 21002 receipt-data域的数据有问题
 * 21003 receipt无法通过验证
 * 21004 提供的shared secret不匹配你账号中的shared secret
 * 21005 receipt服务器当前不可用
 * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
 * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
 * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
 *
 * @author Chen GuanQun <99912250@qq.com>
 */
class ApplePay
{
    /**
     * @var string 测试时使用
     */
    private $testUrl = 'https://sandbox.itunes.apple.com/verifyReceipt';

    /**
     * @var string 正式时使用
     */
    private $url = 'https://buy.itunes.apple.com/verifyReceipt';

    /**
     * @var string 错误消息
     */
    private $error = '';

    /**
     * @var string 凭证
     */
    private $receipt = '';

    /**
     * @var string 密码
     */
    private $password = '';

    /**
     * @var array 苹果服务器返回的数据
     */
    private $returnData = [];

    /**
     * ApplePay constructor.
     * @param string $receipt 凭证
     * @param string $password 密码
     */
    public function __construct($receipt, $password = '')
    {
        $this->receipt = $receipt;
        $this->password = $password;
    }

    /**
     * 验证凭证
     * @param bool $sendBox 是否使用沙箱环境
     * @return bool
     */
    private function verify($sendBox = false)
    {
        if (strlen($this->receipt) < 10) {
            $this->error = '凭证数据长度太短，请确定数据正确！';
            return false;
        }
        $return = $this->postData($this->receipt, $this->password, $sendBox ? $this->testUrl : $this->url);
        if ($return) {
            $this->returnData = json_decode($return, true);
            if ($this->returnData['status'] != 0) {
                $this->setStatusError($this->returnData['status']);
                return false;
            }
            return $this->returnData;
        } else {
            $this->error = '与苹果服务器通讯失败！';
            return false;
        }
    }

    /**
     * 验证凭证
     * @param bool $verifySendbox 是否验证沙盒环境
     * @return bool
     */
    public function verifyReceipt($verifySendbox = false)
    {
        // 验证正式
        if ($result = $this->verify(false)) {
            return $result;
        }

        // 验证沙盒
        if ($verifySendbox) {
            if ($result = $this->verify(true)) {
                return $result;
            }
        }

        return false;
    }

    /**
     * 设置状态错误消息
     * @param $status
     */
    private function setStatusError($status)
    {
        switch (intval($status)) {
            case 21000:
                $error = 'AppleStore不能读取你提供的JSON对象';
                break;
            case 21002:
                $error = 'receipt-data域的数据有问题';
                break;
            case 21003:
                $error = 'receipt无法通过验证';
                break;
            case 21004:
                $error = '提供的shared secret不匹配你账号中的shared secret';
                break;
            case 21005:
                $error = 'receipt服务器当前不可用';
                break;
            case 21006:
                $error = 'receipt合法，但是订阅已过期';
                break;
            case 21007:
                $error = 'receipt是沙盒凭证，但却发送至生产环境的验证服务';
                break;
            case 21008:
                $error = 'receipt是生产凭证，但却发送至沙盒环境的验证服务';
                break;
            default:
                $error = '未知错误';
        }
        $this->error = $error;
    }

    /**
     * 返回交易id
     * @return mixed
     */
    public function getTransactionId()
    {
        return $this->returnData['receipt']['in_app'][0]['transaction_id'];
    }

    /**
     * 查询数据是否有效
     * @param $productId
     * @param \Closure $successCallBack
     * @return bool
     */
    public function query($productId, \Closure $successCallBack)
    {
        if ($this->returnData) {
            if ($this->returnData['status'] == 0) {
                if ($productId == $this->returnData['receipt']['in_app'][0]['product_id']) {
                    return call_user_func_array($successCallBack, [
                        $this->getTransactionId(),
                        $this->returnData
                    ]);
                } else {
                    $this->error = '非法的苹果商店product_id，这个凭证有可能是伪造的！';
                    return false;
                }
            } else {
                $this->error = '苹果服务器返回订单状态不正确!';
                return false;
            }
        } else {
            $this->error = '无效的苹果服务器返回数据！';
            return false;
        }
    }

    /**
     * 返回错误信息
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * curl提交数据
     * @param $receipt_data
     * @param string $password
     * @param $url
     * @return mixed
     */
    private function postData($receipt_data, $password, $url)
    {
        $postData = ["receipt-data" => $receipt_data, 'password' => $password];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}