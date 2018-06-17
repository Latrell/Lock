<?php

namespace Latrell\Lock;

use Closure;
use Latrell\Lock\Exceptions\AcquireFailedException;

abstract class GranuleStore
{
    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;

    /**
     * 锁超时时间（秒）
     */
    protected $timeout;

    /**
     * 默认上锁最大超时时间（秒）
     */
    protected $max_timeout;

    /**
     * 重试等待时间（微秒）
     */
    protected $retry_wait_usec;

    public function granule($key, Closure $callback, $acquire_timeout = -1)
    {
        try {
            if ($this->acquire($key, $acquire_timeout)) {
                return $callback(); // 闭包返回值作为方法返回值
            }

            throw new AcquireFailedException("Acquire lock key {$key} timeout!");
        } finally {
            $this->release($key);
        }
    }

    /**
     * synchronized is a alias function of granule
     *
     * @param $key
     * @param Closure $callback
     * @param int $acquire_timeout 上锁最大超时时间，未指定此参数时，取默认配置
     * @return bool
     */
    public function synchronized($key, Closure $callback, $acquire_timeout = -1)
    {
        return $this->granule($key, $callback, $acquire_timeout);
    }
}
