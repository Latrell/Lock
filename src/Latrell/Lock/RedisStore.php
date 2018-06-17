<?php
namespace Latrell\Lock;

use Illuminate\Redis\RedisManager;

class RedisStore extends GranuleStore implements LockInterface
{

	/**
	 * The Redis database connection.
	 *
	 * @var \Illuminate\Redis\Database
	 */
	protected $redis;

	/**
	 * A string that should be prepended to keys.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * The Redis connection that should be used.
	 *
	 * @var string
	 */
	protected $connection;

	/**
	 * 锁超时时间（秒）
	 */
	protected $timeout;

	/**
	 * 上锁最大超时时间（秒）
	 */
	protected $max_timeout;

	/**
	 * 重试等待时间（微秒）
	 */
	protected $retry_wait_usec;

	/**
	 * Create a new Redis store.
	 *
	 * @param  \Illuminate\Redis\Database  $redis
	 * @param  string  $prefix
	 * @param  string  $connection
	 * @return void
	 */
	public function __construct(RedisManager $redis, $prefix = '', $connection = 'default', $timeout = 30, $max_timeout = 300, $retry_wait_usec = 100000)
	{
		$this->redis = $redis;
		$this->setPrefix($prefix);
		$this->connection = $connection;
		$this->timeout = $timeout;
		$this->max_timeout = $max_timeout;
		$this->retry_wait_usec = $retry_wait_usec;
	}

	/**
	 * 上锁
	 *
	 * @param string $key
	 * @return boolean
	 */
	public function acquire($name)
	{
		$key = $this->getKey($name);

		$time = time();
		while (time() - $time < $this->max_timeout) {
			$lockValue = time() + $this->timeout;
			if ($this->connection()->set($key, $lockValue, "EX", $this->timeout, "NX")) {
				// 加锁成功。
				return true;
			}

			// 未能加锁成功。
			// 检查当前锁是否已过期，并重新锁定。
			if ($this->connection()->get($key) < time() && $this->connection()->getset($key, $lockValue) < time()) {
				$this->connection()->expire($key, $this->timeout);
				return true;
			}
			usleep($this->retry_wait_usec);
		}
		return false;
	}

	/**
	 * 解锁
	 *
	 * @param string $key
	 */
	public function release($name)
	{
		$key = $this->getKey($name);

		if($this->connection()->ttl($key)) {
			$this->connection()->del($key);
		}
	}

	/**
	 * 取得用于该锁的Key。
	 */
	protected function getKey($name)
	{
		return $this->prefix . $name;
	}

	/**
	 * 清理过期的死锁
	 *
	 * @return integer 清理的死锁数量
	 */
	public function clear()
	{
		return 0;
	}

	/**
	 * Get the Redis connection instance.
	 *
	 * @return \Predis\ClientInterface
	 */
	public function connection()
	{
		return $this->redis->connection($this->connection);
	}

	/**
	 * Set the connection name to be used.
	 *
	 * @param  string  $connection
	 * @return void
	 */
	public function setConnection($connection)
	{
		$this->connection = $connection;
	}

	/**
	 * Get the Redis database instance.
	 *
	 * @return \Illuminate\Redis\Database
	 */
	public function getRedis()
	{
		return $this->redis;
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
