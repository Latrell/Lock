<?php
namespace Latrell\Lock;

use Closure;
use RuntimeException;

abstract class GranuleStore
{

	public function granule($key, Closure $callback)
	{
		try {
			if ($this->acquire($key)) {
				$callback();
			} else {
				throw new RuntimeException("Acquire lock key {$key} timeout!");
			}
		} finally {
			$this->release($key);
		}
	}

	/**
	 * [synchronized alias of function granule]
	 */
	public function synchronized($key, Closure $callback)
	{
		return $this->granule($key, $callback);
	}
}
