<?php

/**
 * Task to dump a doctrine mysql database using MySQL.
 *
 * @package    symfony
 * @subpackage doctrine
 * @author     Kevin Dew <kev@dewsolutions.co.uk>
 */
class sfDoctrineMysqlSqlDumpTask extends sfDoctrineDataDumpTask
{
  /**
   * @see sfTask
   */
  protected function configure()
  {
    parent::configure();

    $this->addOptions(array(
      new sfCommandOption(
        'database',
        null,
        sfCommandOption::PARAMETER_OPTIONAL,
        'The database to dump'
      )
    ));

    $this->namespace = 'doctrine';
    $this->name = 'mysql-sql-dump';

    $this->briefDescription = 'Performs a mysql dump of the sql in the database';

    $this->detailedDescription = <<<EOF
The [doctrine:mysql-sql-dump|INFO] performs an sql dump of the data on MySQL databases,
it will not function on non MySQL databases:

  [./symfony doctrine:mysql-sql-dump|INFO]

The task dumps the database sql in [data/sql/%target%|COMMENT].

The sql dump file can be reimported by using the [doctrine:mysql-sql-load|INFO]
task.

  [./symfony doctrine:mysql-sql-load|INFO]
EOF;

  }

  /**
   * @see sfTask
   */
  protected function execute($arguments = array(), $options = array())
  {
    $databaseManager = new sfDatabaseManager($this->configuration);

    $databases = $this->getDoctrineDatabases(
      $databaseManager,
      isset($options['database']) ? array($options['database']) : null
    );

    // get database to dump
    if (count($databases) > 1)
    {
      $this->logSection(
        'doctrine',
        'As you have multiple databases a database must be specified',
        null,
        'ERROR'
      );
      return;
    }

    $databaseName = key($databases);
    $database = current($databases);

    // check connection is mysql
    if (!$database->getDoctrineConnection() instanceof Doctrine_Connection_Mysql)
    {
      $this->logSection(
        'doctrine', 'Database connection must be MySQL', null, 'ERROR'
      );
      return;
    }

    $environment = $this->configuration instanceof sfApplicationConfiguration
      ? $this->configuration->getEnvironment()
      : 'all'
    ;

    // do database backup
    $backupPath = $this->getBackupPath(
      $arguments,
      $options,
      $environment,
      $databaseName,
      count($databases) > 1
    );

    $this->logSection(
      'mysql', 'Dumping ' . $databaseName . ' database to ' . $backupPath
    );

    $this->backupDatabase($database->getDoctrineConnection(), $backupPath);


    $this->logSection('mysql', 'Database dump completed');

  }

  /**
   * Get the path to the file to backup
   *
   * @param   array   $arguments          Arguments for this task
   * @param   array   $options            Options for this task
   * @param   string  $filenameSuffix     (Optional) A suffix for the end of the
   *                                      filename
   * @param   string  $databaseName       (Optional) The database connection name
   * @param   boolean $multipleDatabases  (Optional) Whether there are multiple
   *                                      databases
   * 
   * @return  string
   */
  protected function getBackupPath(
    array $arguments,
    array $options,
    $filenameSuffix = '',
    $databaseName = '',
    $multipleDatabases = false
  )
  {
    $config = $this->getCliConfig();

    $sqlPath = $config['sql_path'];

    if (!is_dir($sqlPath))
    {
      $this->getFilesystem()->mkdirs($sqlPath);
    }

    $path = $sqlPath;

    if ($arguments['target'])
    {
      $filename = $arguments['target'];

      if (!sfToolkit::isPathAbsolute($filename))
      {
        $filename = $sqlPath . '/' . $filename;
      }

      $this->getFilesystem()->mkdirs(dirname($filename));

      $path = $filename;
    }

    if (is_dir($path))
    {
      // create filename for backup
      $path = 
        rtrim($path, '/') . '/' 
        . date(
          sfConfig::get(
            'app_sfDoctrineMysqlSafeMigratePlugin_dump_date_format', 'U'
          )
        )
        . ($filenameSuffix ? '_' . $filenameSuffix : '')
        // append database name to filename in the case of multiple dbs
        . ($multipleDatabases ? '_' . $databaseName : '')
        . '.sql'
      ;
    }

    return $path;

  }

  /**
   * Backup the database
   *
   * @param   Doctrine_Connection_Mysql $connection
   * @param   string                    $backupPath   The path to the file to
   *                                                  backup
   * @return  void
   */
  protected function backupDatabase(
    Doctrine_Connection_Mysql $connection, $backupPath
  )
  {
    $exporter = new sfDoctrineMysqlSafeMigrateMysqlCliExport(
      $connection,
      sfConfig::get('app_sfDoctrineMysqlSafeMigratePlugin_mysql_dump_path'),
      sfConfig::get('app_sfDoctrineMysqlSafeMigratePlugin_mysql_dump_arguments')
    );

    try
    {
      $exporter->exportTo($backupPath);
    }
    catch (Exception $e)
    {
      throw $e;
    }
  }
}
