# 苹果内购

## 安装

composer

```bash
$ php composer.phar require chenkby/apple-pay:1.0
```

或者添加以下代码到`composer.json`文件的`require`块中：

```
"chenkby/apple-pay": "1.0"
```

## 使用

```php
    // $orderId 本地订单号
    // $storeProductId 苹果商店产品ID
    // $tradeNo 苹果交易号
    
    $order = findOrder($orderId);

    $order->updateReceipt($data['receipt']);
    $applePay = new ApplePay($_POST['receipt'], $orderId);
    if ($applePay->verifyReceipt()) {
        $result = $applePay->query($storeProductId, function ($tradeNo, $returnData) use ($order) {
            // 检查此交易号是否被使用
            if (!$order->checkTradeNoIsUsed($tradeNo)) {
                // 更新本地订单状态等...
                return $this->notify($order, $returnData, $tradeNo);
            } else {
                echo '此笔交易号已经被使用，这笔交易有可能是伪造的！';
                return false;
            }
        });
        if ($result) {
            echo 'success';
        } else {
            echo $applePay->getError();
        }
    } else {
        echo $applePay->getError();
    }

```