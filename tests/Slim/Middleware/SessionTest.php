<?php

class SessionTest extends PHPUnit_Framework_TestCase
{
    /** @var  \Illuminate\Database\Capsule\Manager */
    protected $capsule;

    public function setUp()
    {
        $this->capsule = new \Illuminate\Database\Capsule\Manager();
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        $ddl = <<<SQL
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL UNIQUE,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL
)
SQL;
        $this->capsule->getConnection()->getPdo()->exec($ddl);
    }

    public function tearDown()
    {
        $this->capsule = null;
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Driver [hoge] not supported.
     */
    public function test_throw_exception_when_unknown_driver()
    {
        $config = [
            'sessions.driver' => 'hoge',
        ];
        $this->dispatch($config);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function test_throw_exception_when_session_dir_not_exists()
    {
        $config = [
            'sessions.driver' => 'file',
            'sessions.files' => __DIR__ . '/hoge',
        ];
        $this->dispatch($config);
    }

    public function test_empty_cookie_when_session_not_started()
    {
        $config = [
            'sessions.driver' => null,
            'sessions.cookie' => 'test_session',
        ];
        $slim = $this->dispatch($config);
        $this->assertEmpty($slim->response->cookies[$config['sessions.cookie']]);
    }

    public function test_got_session_cookie_when_session_close_success()
    {
        $config = [
            'sessions.driver' => 'database',
            'sessions.cookie' => 'test_session',
        ];
        $slim = $this->dispatch($config);
        $this->assertNotEmpty($slim->response->cookies[$config['sessions.cookie']]);
    }

    public function test_sessions_sweep_when_hits_lottery()
    {
        $config = [
            'sessions.driver' => 'database',
        ];
        $this->dispatch($config);
        $rec = $this->capsule->getConnection()->table('sessions')->get();
        $this->assertSame(1, count($rec));
        $config['sessions.lifetime'] = 0;
        $config['sessions.lottery'] = [1, 1]; // 100% hits
        $this->dispatch($config);
        $rec = $this->capsule->table('sessions')->get();
        $this->assertEmpty($rec);
    }

    /**
     * @param array $config
     * @return \Slim\Slim
     */
    public function dispatch($config)
    {
        \Slim\Environment::mock(array(
            'REQUEST_METHOD' => 'HEAD', // ignore console output
            'PATH_INFO' => '/',
        ));
        $config['debug'] = false; // ignore pretty exceptions
        $slim = new \Slim\Slim($config);
        $manager = new \Slim\Middleware\SessionManager($slim);
        $manager->setDbConnection($this->capsule->getConnection());
        $session = new \Slim\Middleware\Session($manager);
        $slim->add($session);
        $slim->run();
        return $slim;
    }
}
