<?php

/**
 * Performs a migration on a MySQL database by performing a database dump prior
 * to attempting a migration and restoring the backup on a failure
 *
 * Note this class has replication of code written in sfDoctrineMigrateTask
 *
 * @package    symfony
 * @subpackage doctrine
 * @author     Kevin Dew <kev@dewsolutions.co.uk>
 */
class sfDoctrineMysqlSafeMigrateTask extends sfDoctrineMigrateTask
{
  /**
   * @see sfTask
   */
  protected function configure()
  {
    parent::configure();

    $this->namespace = 'doctrine';
    $this->name = 'mysql-safe-migrate';

    $this->addOptions(array(
      new sfCommandOption(
        'target',
        null,
        sfCommandOption::PARAMETER_OPTIONAL,
        'The target filename of the backup file'
      ),
      new sfCommandOption(
        'no-confirmation',
        null,
        sfCommandOption::PARAMETER_NONE,
        'Whether to force dropping of the database'
      ),
      new sfCommandOption(
        'keep-backup',
        null,
        sfCommandOption::PARAMETER_NONE,
        'Keep the backup file that is generated'
      ),
      new sfCommandOption(
        'disable-env',
        null,
        sfCommandOption::PARAMETER_NONE,
        'Disable environment whilst migrating'
      ),
    ));

    $this->briefDescription
      = 'Migrates a mysql database to current/specified version using a backup'
      . ' to get around lack of transaction support'
    ;

    $this->detailedDescription = <<<EOF
The [doctrine:mysql-safe-migrate|INFO] task migrates a MySQL database in a
restorable manner as normal migrations are not able to rollback in the event
of an error:

  [./symfony doctrine:mysql-safe-migrate|INFO]

Provide a version argument to migrate to a specific version:

  [./symfony doctrine:mysql-safe-migrate 10|INFO]

To migrate up or down one migration, use the [--up|COMMENT] or
[--down|COMMENT] options:

  [./symfony doctrine:mysql-safe-migrate --down|INFO]

To test if there are any errors in a migration without applying it you can run
in dry-run mode using the [--dry-run|COMMENT] option, be aware that in this mode
your database will be backed up and then restored so there is possibility for
data loss:

  [./symfony doctrine:mysql-safe-migrate --dry-run|INFO]

To migrate up or down one migration, use the [--up|COMMENT] or
[--down|COMMENT] options:

  [./symfony doctrine:mysql-safe-migrate --down|INFO]

The task dumps the database data in [data/sql/%target%|COMMENT].

You will be prompted for confirmation before any databases are dropped unless
you provide the [--no-confirmation|COMMENT] option:

  [./symfony doctrine:mysql-safe-migrate --no-confirmation|INFO]

If you do not want the backup file to be deleted after the completion of the
task provide the [--keep-backup|COMMENT] option:

  [./symfony doctrine:mysql-safe-migrate --keep-backup|INFO]

Note that because a backup of your database is restored there is the possibility
of data loss from any database changes that happened during the time since the
backup was made. This task is meant primarily for a development environment
to ease in assuring a doctrine migration will work successfully
EOF;

  }

  /**
   * @see sfTask
   */
  protected function execute($arguments = array(), $options = array())
  {
    $databaseManager = new sfDatabaseManager($this->configuration);

    $config = $this->getCliConfig();
    $migration = new Doctrine_Migration($config['migrations_path']);
    $from = $migration->getCurrentVersion();
    $to = $this->getMigrationVersion($migration, $arguments, $options);
    $noConfirmation = $options['no-confirmation'];
    $environment = $this->configuration instanceof sfApplicationConfiguration
      ? $this->configuration->getEnvironment()
      : 'all' // we dont really cater for this condition
    ;

    // check if a migration is needed
    if ($from == $to)
    {
      $this->logSection(
        'doctrine',
        sprintf('Already at migration version %s', $to)
      );
      return;
    }

    // check connection is mysql
    if (!$migration->getConnection() instanceof Doctrine_Connection_Mysql)
    {
      $this->logSection(
        'doctrine', 'Database connection must be MySQL', null, 'ERROR'
      );
      return;
    }

    if ($options['disable-env'])
    {
      $result = $this->enableDisableEnv($options['env'], false);
      if (!$result)
      {
        $this->logSection(
          'disable', 'Disabling failed task aborting', null, 'ERROR'
        );
        return 1;
      }
    }

    // check if it's a dry run
    if ($options['dry-run'] && !$noConfirmation)
    {

      $confirmation = $this->askConfirmation(
        array(
          'As MySQL auto commits on an alter table the database will be backed
          up prior to attempting migration and the backup restored once
          complete',
          'There is the posibility of data loss during this process, It is
          set to run in the ' . $environment . ' environment',
          '',
          'Are you sure you want to proceed? (y/N)'
        ),
        'QUESTION_LARGE',
        false
      );

      // allow user to abort
      if(!$confirmation)
      {
        $this->cleanUp($options);

        $this->logSection('doctrine', 'Task aborted');
        
        return 1;
      }

      $noConfirmation = true;
    }

    // do database backup
    $backupPath = $this->getBackupPath(
      $arguments,
      $options,
      $environment . (!$options['keep-backup'] ? '.tmp' : '' )
    );

    $this->logSection('mysql', 'Dumping database to ' . $backupPath);

    try
    {
      $this->backupDatabase($migration->getConnection(), $backupPath);
    }
    catch (Exception $e)
    {
      $this->cleanUp($options);

      $this->logBlock(
        array(
          'Dumping MySQL database failed:',
          '',
          $e->getMessage()
        ),
        'ERROR_LARGE'
      );

      return 1;
    }

    $this->logSection('mysql', 'Database dump completed');

    $this->logSection('doctrine', sprintf(
      'Migrating from version %s to %s%s', $from, $to,
      $options['dry-run'] ? ' (dry run)' : '')
    );

    // perform migration
    try
    {
      $migration->migrate($to, $options['dry-run']);
    }
    catch (Exception $e) {}

    // do clean up if database needs to be restored
    if ($migration->hasErrors() || $options['dry-run'])
    {

      // check if we need to confirm database drop
      if (!$noConfirmation)
      {
        $confirmation = $this->askConfirmation(
          array(
            'An error has occurred with the migration and the database can be'
            . ' restored to its previous state',
            'To complete the restore the database in the ' . $environment 
            . ' environment will be dropped and the  backup file (located at '
            . $backupPath . ') will be used to restore the database.',
            '',
            'Are you sure you want to proceed? (Y/n)'
          ),
          'QUESTION_LARGE',
          true
        );
        
        if (!$confirmation)
        {
          $this->cleanUp($options);

          $this->logSection('mysql', 'Database restore task aborted');
          $this->logSection(
            'mysql', 'Database backup at ' . $backupPath . 'has not been deleted'
          );
          $this->outputMigrationErrors($migration);
          $this->logSection('doctrine', 'Task aborted');

          return 1;
        }
      }

      // restore database
      try
      {
        $this->restoreBackup($migration->getConnection(), $backupPath);
      }
      catch (Exception $e)
      {
        $this->cleanUp($options);
        $this->outputMigrationErrors($migration);

        $this->logBlock(
          array(
            'Database restore failed:',
            '',
            $e->getMessage()
          ),
          'ERROR_LARGE'
        );

        $this->logSection(
          'mysql', 'Database backup at ' . $backupPath . 'has not been deleted'
        );
        return 1;
      }

      $this->logSection('mysql', 'Database restored successfully');
    }

    // delete backup if needed
    if (!$options['keep-backup'])
    {
      $this->logSection('mysql', 'Deleting backup file');
      $this->getFilesystem()->remove($backupPath);
    }

    if ($migration->hasErrors())
    {
      $this->cleanUp($options);
      $this->outputMigrationErrors($migration);
      return 1;
    }
    
    $this->logSection('doctrine', 'Migration complete');
  }

  /**
   * Get the path to the file to backup
   *
   * @param   array   $arguments      Arguments for this task
   * @param   array   $options        Options for this task
   * @param   string  $filenameSuffix (Optional) A suffix for the end of the
   *                                  filename
   * @return  string
   */
  protected function getBackupPath(
    array $arguments, array $options, $filenameSuffix = ''
  )
  {
    $config = $this->getCliConfig();

    $sqlPath = $config['sql_path'];

    if (!is_dir($sqlPath))
    {
      $this->getFilesystem()->mkdirs($sqlPath);
    }

    $path = $sqlPath;

    if ($options['target'])
    {
      $filename = $options['target'];

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

  /**
   *
   * @param   Doctrine_Connection_Mysql $connection
   * @param   string                    $backupPath The path to the sql file
   *                                                to restore
   * @return  void
   */
  protected function restoreBackup(
    Doctrine_Connection_Mysql $connection, $backupPath
  )
  {
    if (!file_exists($backupPath))
    {
      throw new Exception('Backup to restore database doesn\'t exist');
    }

    $connection->dropDatabase();
    $connection->createDatabase();

    $importer = new sfDoctrineMysqlSafeMigrateMysqlCliImport(
      $connection,
      sfConfig::get('app_sfDoctrineMysqlSafeMigratePlugin_mysql_path'),
      sfConfig::get('app_sfDoctrineMysqlSafeMigratePlugin_mysql_arguments')
    );

    try
    {
      $importer->importFrom($backupPath);
    }
    catch (Exception $e)
    {
      throw $e;
    }
  }

  /**
   *
   * @param   Doctrine_Migration  $migration
   * @param   array               $arguments  Arguments for this task
   * @param   array               $options    Options for this task
   * @return  int
   */
  protected function getMigrationVersion(
    Doctrine_Migration $migration, array $arguments, array $options
  )
  {
    if (is_numeric($arguments['version']))
    {
      $version = $arguments['version'];
    }
    else if ($options['up'])
    {
      $version = $from + 1;
    }
    else if ($options['down'])
    {
      $version = $from - 1;
    }
    else
    {
      $version = $migration->getLatestVersion();
    }

    return $version;
  }

  /**
   * Output any errors caused by the migration
   *
   * @param   Doctrine_Migration  $migration
   * @return  void
   */
  protected function outputMigrationErrors(Doctrine_Migration $migration)
  {
    if ($this->commandApplication && $this->commandApplication->withTrace())
    {
      $this->logSection('doctrine', 'The following errors occurred:');
      foreach ($migration->getErrors() as $error)
      {
        $this->commandApplication->renderException($error);
      }
    }
    else
    {
      $this->logBlock(array_merge(
        array('The following migration errors occurred:', ''),
        array_map(
          create_function('$e', 'return \' - \'.$e->getMessage();'),
          $migration->getErrors()
        )
      ), 'ERROR_LARGE');
    }
  }

  /**
   * Enable / Disable environment
   *
   * @param   string  $env
   * @param   bool    $enable
   * @return  bool    success
   */
  protected function enableDisableEnv($env, $enable = true)
  {
    $task = $enable ? 'enable' : 'disable';

    try
    {
      $this->runTask('project:' . $task, array('env' => $env));
    }
    catch(Exception $e)
    {
      $this->logBlock(
        array(
          'project:' . $task . ' failed for env ' . $env,
          '',
          $e->getMessage()
        ),
        'ERROR_LARGE'
      );

      return false;
    }

    return true;
  }

  /**
   * Method to be called on exiting to clean up task
   *
   * @param   array   $options
   * @return  void
   */
  protected function cleanUp(array $options)
  {
    if (
      isset($options['disable-env'], $options['env']) && $options['disable-env']
    )
    {
      $this->enableDisableEnv($options['env'], true);
    }
  }
}
