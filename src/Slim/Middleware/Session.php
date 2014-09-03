<?php namespace Slim\Middleware;

use Carbon\Carbon;
use Slim\Middleware;

class Session extends Middleware
{
    const DEFAULT_COOKIE_NAME = 'slim_session';
    const DEFAULT_TABLE_NAME = 'sessions';

    /** @var  SessionManager */
    protected $manager;

    public function __construct($manager)
    {
        $this->manager = $manager;
    }

    public function call()
    {
        $this->mergeDefaultConfig();

        if ($this->sessionConfigured()) {
            $session = $this->startSession();
            $this->app->container->singleton('session', function() use ($session)
            {
                return $session;
            });
        }

        $this->next->call();

        if ($this->sessionConfigured()) {
            $this->closeSession($session);
            $this->addCookieToResponse($session);
        }
    }

    protected function sessionConfigured()
    {
        return ( ! is_null($this->app->config('sessions.driver')));
    }

    protected function mergeDefaultConfig()
    {
        $config = [
            'sessions.driver' => null,
            'sessions.files' => null,
            'sessions.table' => self::DEFAULT_TABLE_NAME,
            'sessions.lifetime' => 120, // minutes
            'sessions.expire_on_close' => false,
            'sessions.cookie' => self::DEFAULT_COOKIE_NAME,
            'sessions.lottery' => [2, 100], // session sweep probability (2% default)
        ];
        $settings = $this->app->container['settings'];
        $this->app->container['settings'] = array_merge($config, $settings);
    }

    protected function startSession()
    {
        $session = $this->manager->driver();
        $session->setId($this->app->getCookie($session->getName()));
        $session->start();
        return $session;
    }

    /**
     * @param $session \Illuminate\Session\Store
     */
    protected function closeSession($session)
    {
        $session->save();
        $this->collectGarbage($session);
    }

    /**
     * @param $session \Illuminate\Session\Store
     */
    protected function collectGarbage($session)
    {
        if ($this->configHitsLottery()) {
            $session->getHandler()->gc($this->getLifeTimeSeconds());
        }
    }

    protected function configHitsLottery()
    {
        $lottery = $this->app->config('sessions.lottery');
        return (mt_rand(1, $lottery[1]) <= $lottery[0]);
    }

    protected function getLifeTimeSeconds()
    {
        return $this->app->config('sessions.lifetime') * 60;
    }

    protected function getCookieLifeTime()
    {
        $expire_on_close = $this->app->config('sessions.expire_on_close');
        $lifetime = $this->app->config('sessions.lifetime');
        return ($expire_on_close ? 0 : intval(Carbon::now()->addMinutes($lifetime)->format('U')));
    }

    /**
     * @param $session \Illuminate\Session\Store
     */
    protected function addCookieToResponse($session)
    {
        $s = $session;
        $this->app->setCookie($s->getName(), $s->getId(), $this->getCookieLifeTime());
    }
}