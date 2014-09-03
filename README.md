## Slim Session-Manager

This is a middleware to integrate [illuminate/session](https://github.com/illuminate/session "illuminate/session") with SlimFramework.

### install

Require this package in your composer.json

    "yusukezzz/slim-session-manager": "0.*"

### important

choose your session driver, filesystem or database and install it.

    "illuminate/filesystem": "4.2.*"
    "illuminate/database": "4.2.*"

if you use database, require sessions table. (for mysql)

```sql
CREATE TABLE `session` (
  `id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `payload` text COLLATE utf8_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  UNIQUE KEY `session_id_unique` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
```

### example

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('UTC');

/**
 * For IDE auto completions
 *
 * @property \Illuminate\Session\Store $session
 */
class MySlim extends \Slim\Slim {}

$app = new MySlim([
    // cookie encryption (strongly recommend)
    'cookies.encrypt' => true,
    'cookies.secret_key' => 'put your secret key',
    // session config
    'sessions.driver' => 'file', // or database
    'sessions.files' => __DIR__ . '/../sessions', // require mkdir
    //'sessions.table' => 'sessions', // require create table
]);

$manager = new \Slim\Middleware\SessionManager($app);
$manager->setFilesystem(new \Illuminate\Filesystem\Filesystem());
// or sessions.driver == 'database'
// ... setup Eloquent ...
// $manager->setDbConnection(Eloquent::getConnection());
$session = new \Slim\Middleware\Session($manager);

$app->add($session);

$app->get('/', function() use ($app)
{
    $current_user = $app->session->get('current_user');
    if (is_null($current_user)) {
        echo <<<HTML
Hello Session.<br>
<form method="POST" action="/session"><input type="submit" value="login"/></form></br>
HTML;
    } else {
        list($id, $name) = $current_user;
        echo <<<HTML
Welcome, {$name} (id={$id})</br>
<form method="POST" action="/session"><input type="submit" value="logout"/>
<input type="hidden" name="_METHOD" value="DELETE"/></form></br>
HTML;
    }
    echo $app->session->get('message').'</br>';
});
$app->post('/session', function() use ($app)
{
    $app->session->put('current_user', [1234, 'hoge_user']);
    $app->session->flash('message', 'logged in.');
    $app->response->redirect('/');
});
$app->delete('/session', function() use ($app)
{
    $app->session->forget('current_user');
    $app->session->flash('message', 'logged out.');
    $app->response->redirect('/');
});

$app->run();
```

### License

MIT
