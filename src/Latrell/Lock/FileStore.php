<?php
namespace Latrell\Lock;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Carbon\Carbon;
use RuntimeException;

class FileStore extends GranuleStore implements LockInterface
{

	/**
	 * The Illuminate Filesystem instance.
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * The file lock directory.
	 *
	 * @var string
	 */
	protected $directory;

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
	 * 上锁最大超时时间（秒）
	 */
	protected $max_timeout;

	/**
	 * 重试等待时间（微秒）
	 */
	protected $retry_wait_usec;

	/**
	 * 锁识别码
	 */
	protected $identifier;

	/**
	 * 锁的到期时间列表
	 */
	protected $expires_at = [];

	/**
	 * Create a new file lock store instance.
	 *
	 * @param  \Illuminate\Filesystem\Filesystem  $files
	 * @param  string  $directory
	 * @return void
	 */
	public function __construct(Filesystem $files, $directory, $timeout = 30, $max_timeout = 300, $retry_wait_usec = 100000)
	{
		$this->files = $files;
		$this->directory = $directory;
		$this->timeout = $timeout;
		$this->max_timeout = $max_timeout;
		$this->retry_wait_usec = $retry_wait_usec;
		$this->identifier = md5(uniqid(gethostname(), true));
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

		// 取得锁文件路径。
		$file = $this->directory . '/' . $key;

		$time = time();
		while (time() - $time < $this->max_timeout) {

			// 删除超时的锁文件。
			try {
				$current_value = $this->files->get($key);
				if (! is_null($current_value) && $this->hasLockValueExpired($current_value)) {
					$this->files->delete($file);
				}
			} catch (FileNotFoundException $e) {}

			// 检查锁文件是否存在。。
			if (! $this->files->exists($file)) {

				// 创建文件锁目录。
				if (! $this->files->exists($this->directory)) {
					$this->files->makeDirectory($this->directory, 0777, true, true);
				}

				// 创建锁文件。
				$value = $this->getLockExpirationValue();
				if ($this->files->put($file, $value, true)) {
					// 加锁成功。
					$this->acquired($name);
					return true;
				}
			}

			usleep($this->retry_wait_usec);
		}
		return false;
	}

	/**
	 * 记录该锁的到期时间
	 */
	protected function acquired($name)
	{
		$this->expires_at[$name] = Carbon::now()->addSeconds($this->timeout);
	}

	/**
	 * 解锁
	 * @param unknown $key
	 */
	public function release($name)
	{
		$key = $this->getKey($name);

		if (! $this->isLocked($name)) {
			throw new RuntimeException('Attempting to release a lock that is not held');
		}

		// 取得锁文件路径。
		$file = $this->directory . '/' . $key;

		try {
			$value = $this->files->get($file);

			unset($this->expires_at[$name]); // 释放内存占用。
			if (! $this->hasLockValueExpired($value)) {
				$this->files->delete($file); // 释放锁。
			} else {
				trigger_error(sprintf('A FileLock was not released before the timeout. Class: %s Lock Name: %s', get_class($this), $name), E_USER_WARNING);
			}
		} catch (FileNotFoundException $e) {
			trigger_error(sprintf('Attempting to release a lock that is not held. Class: %s Lock Name: %s', get_class($this), $name), E_USER_WARNING);
		}
	}

	/**
	 * 我们有一个锁？
	 */
	protected function isLocked($name)
	{
		return key_exists($name, $this->expires_at);
	}

	/**
	 * 取得用于该锁的Key。
	 */
	protected function getKey($name)
	{
		return $this->prefix . md5($name);
	}

	/**
	 * 取得锁的值。
	 * 添加到期时间与识别码。
	 *
	 * @return string
	 */
	protected function getLockExpirationValue()
	{
		return serialize([
			'identifier' => $this->identifier,
			'expires_at' => Carbon::now()->addSeconds($this->timeout)
		]);
	}

	/**
	 * 确定一个锁已过期。
	 *
	 * @param string 锁的值
	 * @return boolean
	 */
	protected function hasLockValueExpired($value)
	{
		$data = @unserialize($value);
		if (! $data) {
			return true;
		}
		return Carbon::now() > $data['expires_at'];
	}

	/**
	 * 清理过期的死锁
	 *
	 * @return integer 清理的死锁数量
	 */
	public function clear()
	{
		$files = $this->files->files($this->directory);
		$num = 0;
		foreach ($files as $file) {
			if (! starts_with($file, $this->getPrefix())) {
				continue;
			}
			$value = $this->files->get($file);
			if ($this->hasLockValueExpired($value)) {
				$this->files->delete($file);
				$num ++;
			}
		}
		return $num;
	}

	/**
	 * Get the Filesystem instance.
	 *
	 * @return \Illuminate\Filesystem\Filesystem
	 */
	public function getFilesystem()
	{
		return $this->files;
	}

	/**
	 * Get the working directory of the lock.
	 *
	 * @return string
	 */
	public function getDirectory()
	{
		return $this->directory;
	}

	/**
	 * Get the lock key prefix.
	 *
	 * @return string
	 */
	public function getPrefix()
	{
		return '';
	}
}
