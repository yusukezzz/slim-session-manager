<?php namespace Slim\Middleware;

use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;

class SessionManager
{
    /** @var  \Slim\Slim */
    protected $app;

    /** @var array[\Illuminate\Session\Store] */
    protected $drivers = [];

    /** @var  \Illuminate\Filesystem\Filesystem */
    protected $filesystem;

    /** @var  \Illuminate\Database\Connection */
    protected $dbConnection;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function setDbConnection($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * @param string $driver
     * @return \Illuminate\Session\Store
     */
    public function driver($driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();
        if ( ! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }
        return $this->drivers[$driver];
    }

    public function getDefaultDriver()
    {
        return $this->app->config('sessions.driver');
    }

    /**
     * @param string $driver
     * @return \Illuminate\Session\Store
     * @throws \InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        $method = 'create' . ucfirst($driver) . 'Driver';
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        throw new \InvalidArgumentException("Driver [$driver] not supported.");
    }

    protected function createArrayDriver()
    {
        return $this->buildSession(new NullSessionHandler());
    }

    protected function createFileDriver()
    {
        $path = $this->app->config('sessions.files');
        if ( ! is_dir($path) OR ! is_writable($path)) {
            throw new \RuntimeException('no such directory or not writable: ' . $path);
        }
        return $this->buildSession(new FileSessionHandler($this->filesystem, $path));
    }

    protected function createDatabaseDriver()
    {
        $table = $this->app->config('sessions.table');
        return $this->buildSession(new DatabaseSessionHandler($this->dbConnection, $table));
    }

    protected function buildSession($handler)
    {
        return new Store($this->app->config('sessions.cookie'), $handler);
    }
}