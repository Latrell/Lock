<?php
namespace Latrell\Lock;

use Closure;

/**
 * 分布式并发锁
 *
 * @author Latrell Chan
 */
interface LockInterface
{

    /**
     * 上锁
     *
     * @param string $key
     * @return boolean
     */
    public function acquire($key);

    /**
     * 解锁
     * @param unknown $key
     */
    public function release($key);

    /**
     * 隔离
     */
    public function granule($key, Closure $callback);

    /**
     * granule的别名
     *
     * @param $key
     * @param Closure $callback
     * @return mixed
     */
    public function synchronized($key, Closure $callback);

    /**
     * 清理过期的死锁
     *
     * @return integer 清理的死锁数量
     */
    public function clear();
}
