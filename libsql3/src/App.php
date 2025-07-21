<?php

namespace Libsql3;

use Libsql3\Cmd\AuthLogin;
use Libsql3\Cmd\AuthLogout;
use Libsql3\Cmd\ConfigDelete;
use Libsql3\Cmd\ConfigGet;
use Libsql3\Cmd\ConfigList;
use Libsql3\Cmd\ConfigSet;
use Libsql3\Cmd\DbArchive;
use Libsql3\Cmd\DbCreate;
use Libsql3\Cmd\DbDelete;
use Libsql3\Cmd\DbList;
use Libsql3\Cmd\DbRestore;
use Libsql3\Cmd\DbShell;
use Libsql3\Cmd\GroupCreate;
use Libsql3\Cmd\GroupDelete;
use Libsql3\Cmd\GroupList;
use Libsql3\Cmd\TeamCreate;
use Libsql3\Cmd\TeamDelete;
use Libsql3\Cmd\TeamEdit;
use Libsql3\Cmd\TeamList;
use Libsql3\Cmd\UserList;
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
        parent::__construct('Libsql3', '1.0.0');

        // Check if MylibSQLAdmin API Endpoint is set
        if (empty(config_get('LIBSQL_API_ENDPOINT')) && config('api_endpoint') === false) {
            throw new \Exception('LIBSQL_API_ENDPOINT environment variable is not set. Please set it and try again. For example: export LIBSQL_API_ENDPOINT="https://your-libsql-admin.io"');
        }

        $this->config = new Configuration();
        $this->readline = $this->config->getReadline();
        $this->app_config = include __DIR__ . '/config.php';
    }

    public function getLibsqlCommands(): array
    {
        $authLogin = new AuthLogin();
        $authLogin->setAppConfig($this->app_config);

        $whoAmI = new WhoAmI();
        $whoAmI->setAppConfig($this->app_config);

        $authLogout = new AuthLogout();
        $authLogout->setAppConfig($this->app_config);

        $dbCreate = new DbCreate();
        $dbCreate->setAppConfig($this->app_config);

        $dbList = new DbList();
        $dbList->setAppConfig($this->app_config);

        $dbDelete = new DbDelete();
        $dbDelete->setAppConfig($this->app_config);

        $dbArchive = new DbArchive();
        $dbArchive->setAppConfig($this->app_config);

        $dbRestore = new DbRestore();
        $dbRestore->setAppConfig($this->app_config);

        $groupCreate = new GroupCreate();
        $groupCreate->setAppConfig($this->app_config);

        $groupDelete = new GroupDelete();
        $groupDelete->setAppConfig($this->app_config);

        $userList = new UserList();
        $userList->setAppConfig($this->app_config);

        $teamCreate = new TeamCreate();
        $teamCreate->setAppConfig($this->app_config);

        $teamEdit = new TeamEdit();
        $teamEdit->setAppConfig($this->app_config);

        $teamDelete = new TeamDelete();
        $teamDelete->setAppConfig($this->app_config);

        return [
            $authLogin,
            $authLogout,
            new ConfigSet(),
            new ConfigGet(),
            new ConfigList(),
            new ConfigDelete(),
            new DbShell(),
            $dbCreate,
            $dbList,
            $dbDelete,
            $dbArchive,
            $dbRestore,
            new GroupList(),
            $groupCreate,
            $groupDelete,
            new TeamList(),
            $teamCreate,
            $teamEdit,
            $teamDelete,
            $userList,
            $whoAmI,
        ];
    }
}
