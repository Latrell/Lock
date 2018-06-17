Lock
======

这是一个支持 Laravel 5 的并发锁拓展包。

该模块在 Redis 与 Memcache 上实现了锁机制。

注意：集群环境下，必须使用 Redis 驱动，否则由于 Memcache 的特性，锁可能出现上锁不准确的情况。

## 安装

```
composer require latrell/lock dev-master
```

使用 ```composer update``` 更新包列表，或使用 ```composer install``` 安装。

找到 `config/app.php` 配置文件中的 `providers` 键，注册服务提供者。

（Laravel 5.5 以上版本可跳过该步骤）

```php
    'providers' => [
        // ...
        Latrell\Lock\LockServiceProvider::class,
    ]
```

找到 `config/app.php` 配置文件中的 `aliases` 键，注册别名。

```php
    'aliases' => [
        // ...
        'Lock' => Latrell\Lock\Facades\Lock::class,
    ]
```

运行 `php artisan vendor:publish` 命令，发布配置文件到你的项目中。

## 使用

### 闭包方式

使用闭包的方式，自动解锁，防止死锁；上锁失败时抛出 AcquireFailedException 异常

需注意，外部变量需要使用 `use` 引入才可在闭包中使用。

```php
// 防止商品超卖。
$key = 'Goods:' . $goods_id;
Lock::granule($key, function() use($goods_id) {
    $goods = Goods::find($goods_id);
    if ($goods->stock > 0) {
        // ...
    }
});

// synchronized 是 granule的别名
try {
    $key = md5($user_id . ':' . $udid); // 用户ID+设备ID，防止重复下单
    Lock::synchronized($key, function() use($data) {
        $orderService->createOrder($data)
        // ... 
    }, 0);
} catch (AcquireFailedException $e) {
    // 请求过于频繁，请稍后再试
}
```

### 普通方式

提供手动上锁与解锁方式，方便应用在复杂环境。

```php

// 锁名称。
$key = 'Goods:' . $goods_id;

// **注意：除非特别自信，否则一定要记得捕获异常，保证解锁动作。**
try {
    // 上锁成功（max_timeout 为0时不会等待当前持有锁的任务运行完成）
    if (Lock::acquire($key, 0)) { 
        // acquire 为true表示上锁成功，false表示锁被占用或上锁等待超时 
        // 第二个参数指定上锁最大超时时间（秒），不指定则默认使用配置文件设置
        // 逻辑单元。
        $goods = Goods::find($goods_id);
        if ( $goods->stock > 0 ) {
            // ...
        }
    }
} finally {
    // 解锁。
    Lock::release($key);
}
```

### 中间件

使用中间件的方式，让两个相同指纹的请求同步执行。

找到 `app/Http/Kernel.php` 中的 `$routeMiddleware` 配置，添加中间件配置。

```
    protected $routeMiddleware = [
        // ...
        'synchronized' => \Latrell\Lock\Middleware\SynchronizationRequests::class,
    ];
```
