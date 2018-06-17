<?php
namespace Latrell\Lock;

use Closure;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class LockManager
{

    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The array of resolved lock stores.
     *
     * @var array
     */
    protected $stores = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * Create a new lock manager instance.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Get a lock store instance by name.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function store($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->stores[$name] = $this->get($name);
    }

    /**
     * Get a lock driver instance.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function driver($driver = null)
    {
        return $this->store($driver);
    }

    /**
     * Attempt to get the store from the local lock.
     *
     * @param  string  $name
     * @return \Latrell\Lock\LockInterface
     */
    protected function get($name)
    {
        return isset($this->stores[$name]) ? $this->stores[$name] : $this->resolve($name);
    }

    /**
     * Resolve the given store.
     *
     * @param  string  $name
     * @return \Latrell\Lock\LockInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Lock store [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        } else {
            $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

            if (method_exists($this, $driverMethod)) {
                return $this->{$driverMethod}($config);
            } else {
                throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
            }
        }
    }

    /**
     * Call a custom driver creator.
     *
     * @param  array  $config
     * @return mixed
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
    }

    /**
     * Create an instance of the file lock driver.
     *
     * @param  array  $config
     * @return \Latrell\Lock\FileStore
     */
    protected function createFileDriver(array $config)
    {
        $timeout = $this->getTimeout($config);
        $max_timeout = $this->getMaxTimeout($config);
        $retry_wait_usec = $this->getRetryWaitUsec($config);

        return new FileStore($this->app['files'], $config['path'], $timeout, $max_timeout, $retry_wait_usec);
    }

    /**
     * Create an instance of the Memcached lock driver.
     *
     * @param  array  $config
     * @return \Latrell\Lock\MemcachedStore
     */
    protected function createMemcachedDriver(array $config)
    {
        $prefix = $this->getPrefix($config);
        $timeout = $this->getTimeout($config);
        $max_timeout = $this->getMaxTimeout($config);
        $retry_wait_usec = $this->getRetryWaitUsec($config);

        $memcached = $this->app['memcached.connector']->connect($config['servers']);

        return new MemcachedStore($memcached, $prefix, $timeout, $max_timeout, $retry_wait_usec);
    }

    /**
     * Create an instance of the Null lock driver.
     *
     * @return \Latrell\Lock\NullStore
     */
    protected function createNullDriver()
    {
        return new NullStore();
    }

    /**
     * Create an instance of the Redis lock driver.
     *
     * @param  array  $config
     * @return \Latrell\Lock\RedisStore
     */
    protected function createRedisDriver(array $config)
    {
        $redis = $this->app['redis'];

        $connection = Arr::get($config, 'connection', 'default');

        $timeout = $this->getTimeout($config);
        $max_timeout = $this->getMaxTimeout($config);
        $retry_wait_usec = $this->getRetryWaitUsec($config);

        return new RedisStore($redis, $this->getPrefix($config), $connection, $timeout, $max_timeout, $retry_wait_usec);
    }

    /**
     * Get the lock prefix.
     *
     * @param  array  $config
     * @return string
     */
    protected function getPrefix(array $config)
    {
        return Arr::get($config, 'prefix') ?: $this->app['config']['lock.prefix'];
    }

    /**
     * Get the lock timeout.
     *
     * @param  array  $config
     * @return string
     */
    protected function getTimeout(array $config)
    {
        return Arr::get($config, 'timeout') ?: $this->app['config']['lock.timeout'];
    }

    /**
     * Get the lock max_timeout.
     *
     * @param  array  $config
     * @return string
     */
    protected function getMaxTimeout(array $config)
    {
        return Arr::get($config, 'max_timeout') ?: $this->app['config']['lock.max_timeout'];
    }

    /**
     * Get the lock retry_wait_usec.
     *
     * @param  array  $config
     * @return string
     */
    protected function getRetryWaitUsec(array $config)
    {
        return Arr::get($config, 'retry_wait_usec') ?: $this->app['config']['lock.retry_wait_usec'];
    }

    /**
     * Get the lock connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["lock.stores.{$name}"];
    }

    /**
     * Get the default lock driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['lock.default'];
    }

    /**
     * Set the default lock driver name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['lock.default'] = $name;
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param  string    $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([
            $this->store(),
            $method
        ], $parameters);
    }
}
