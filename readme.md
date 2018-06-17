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

使用闭包的方式，可由方法内自动处理异常，并自动解锁，防止死锁。

但需注意，外部变量需要使用 `use` 引入才可在闭包中使用。

```php
// 防止商品超卖。
$key = 'Goods:' . $goods_id;
Lock::granule($key, function() use($goods_id) {
	$goods = Goods::find($goods_id);
	if ( $goods->stock > 0 ) {
		// ...
	}
});
```

### 普通方式

提供手动上锁与解锁方式，方便应用在复杂环境。

```php

// 锁名称。
$key = 'Goods:' . $goods_id;

// **注意：除非特别自信，否则一定要记得捕获异常，保证解锁动作。**
try {

	// 上锁。
	Lock::acquire($key);

	// 逻辑单元。
	$goods = Goods::find($goods_id);
	if ( $goods->stock > 0 ) {
		// ...
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
