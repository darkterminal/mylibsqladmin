<?php

namespace Libsql3;

use Libsql3\Cmd\AuthLogin;
use Libsql3\Cmd\AuthLogout;
use Libsql3\Cmd\DbList;
use Libsql3\Cmd\DbShell;
use Libsql3\Cmd\WhoAmI;
use Psy\Configuration;
use Psy\Readline\Readline;
use Symfony\Component\Console\Application;

class App extends Application
{
    private Configuration $config;
    private Readline $readline;
    private array $app_config;

    public function __construct()
    {
        $this->config = new Configuration();
        $this->readline = $this->config->getReadline();
        $this->app_config = require_once __DIR__ . '/config.php';
        parent::__construct('Libsql3', '1.0.0');
    }

    public function getLibsqlCommands(): array
    {
        $authLogin = new AuthLogin();
        $authLogin->setAppConfig($this->app_config);

        $whoAmI = new WhoAmI();
        $whoAmI->setAppConfig($this->app_config);

        $authLogout = new AuthLogout();
        $authLogout->setAppConfig($this->app_config);

        $dbList = new DbList();
        $dbList->setAppConfig($this->app_config);

        return [
            $authLogin,
            $authLogout,
            $whoAmI,
            new DbShell(),
            $dbList
        ];
    }
}
