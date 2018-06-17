<?php
namespace Latrell\Lock;

use Memcached;

class MemcachedStore extends GranuleStore implements LockInterface
{
    /**
     * The Memcached instance.
     *
     * @var \Memcached
     */
    protected $memcached;

    /**
     * 锁识别码
     */
    protected $Identifier;

    /**
     * Create a new Memcached store.
     *
     * @param Memcached $memcached
     * @param string $prefix
     * @param int $timeout
     * @param int $max_timeout
     * @param int $retry_wait_usec
     */
    public function __construct($memcached, $prefix = '', $timeout = 30, $max_timeout = 300, $retry_wait_usec = 100000)
    {
        $this->setPrefix($prefix);
        $this->memcached = $memcached;
        $this->timeout = $timeout;
        $this->max_timeout = $max_timeout;
        $this->retry_wait_usec = $retry_wait_usec;
        $this->Identifier = md5(uniqid(gethostname(), true));
    }

    /**
     * 上锁
     *
     * @param string $key
     * @param int $acquire_timeout
     * @return bool
     */
    public function acquire($key, $acquire_timeout = -1)
    {
        if ($acquire_timeout == -1) {
            // 指定第二个参数，则当次加锁使用该参数指定的最大上锁超时时间；否则使用默认配置
            $acquire_timeout = $this->max_timeout;
        }

        $key = $this->prefix . $key;
        $time = time();
        do {
            if ($this->memcached->add($key, $this->Identifier, $this->timeout)) {
                return true;
            }

            usleep($this->retry_wait_usec);
        } while (time() - $time < $acquire_timeout);

        return false;
    }

    /**
     * 解锁
     * @param unknown $key
     */
    public function release($key)
    {
        $key = $this->prefix . $key;
        if ($this->memcached->get($key) === $this->Identifier) {
            $this->memcached->delete($key);
        }
    }

    /**
     * 清理过期的死锁
     *
     * @return integer 清理的死锁数量
     */
    public function clear()
    {
        // 由Memcache自动管理。
        return 0;
    }

    /**
     * Get the underlying Memcached connection.
     *
     * @return \Memcached
     */
    public function getMemcached()
    {
        return $this->memcached;
    }

    /**
     * Get the lock key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set the lock key prefix.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setPrefix($prefix)
    {
        $this->prefix = ! empty($prefix) ? $prefix . ':' : '';
    }
}
