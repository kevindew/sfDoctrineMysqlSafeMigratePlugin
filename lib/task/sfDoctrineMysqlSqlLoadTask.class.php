<?php

/**
 * Task to load a MySQL dump into a Doctrine database
 *
 * @package    symfony
 * @subpackage doctrine
 * @author     Kevin Dew <kev@dewsolutions.co.uk>
 */
class sfDoctrineMysqlSqlLoadTask extends sfDoctrineBaseTask
{
  /**
   * @see sfTask
   */
  protected function configure()
  {
    parent::configure();

    $this->addArguments(array(
      new sfCommandArgument(
        'dir_or_file',
        sfCommandArgument::REQUIRED | sfCommandArgument::IS_ARRAY,
        'Directory or file to load'
      )
    ));


    $this->addOptions(array(
      new sfCommandOption(
        'application',
        null,
        sfCommandOption::PARAMETER_OPTIONAL,
        'The application name',
        true
      ),
      new sfCommandOption(
        'env',
        null,
        sfCommandOption::PARAMETER_REQUIRED,
        'The environment',
        'dev'
      ),
      new sfCommandOption(
        'append',
        null,
        sfCommandOption::PARAMETER_NONE,
        'Don\'t delete current data in the database'
      ),
      new sfCommandOption(
        'database',
        null,
        sfCommandOption::PARAMETER_OPTIONAL,
        'The database to use'
      ),
      new sfCommandOption(
        'no-confirmation',
        null,
        sfCommandOption::PARAMETER_NONE,
        'Whether to force dropping of the database'
      )
    ));

    $this->namespace = 'doctrine';
    $this->name = 'mysql-sql-load';

    $this->briefDescription 
      = 'Loads MySQL queries from files/dir into a MySQL database'
    ;

    $this->detailedDescription = <<<EOF
The [doctrine:mysql-sql-load|INFO] is used to load SQL into a MySQL database,
it will not function on non MySQL databases:

  [./symfony doctrine:mysql-sql-load|INFO]

The intended function of this task is to load a MySQL dump backup of a database.

An argument of a target must be specified, mulitple files or a directory of .sql
can be specified.

If you don't want the task to drop and recreate the database and instead want to
just add to the existing database, use the
[--append|COMMENT] option:

  [./symfony doctrine:mysql-sql-load --append target|INFO]
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

    $files = array();

    // get the files
    foreach ($arguments['dir_or_file'] as $target)
    {
      if (!file_exists($target))
      {
        $this->logSection(
          'mysql', 'The path ' . $target . ' does not exist', null, 'ERROR'
        );
        return;
      }

      // add files to array
      if (is_dir($target))
      {
        $this->logSection('finder', 'loading .sql files from ' . $target);
        $files = array_merge(
          $files, sfFinder::type('file')->name('*.sql')->in($target)
        );
      }
      else
      {
        $files[] = $target;
      }
    }

    // no files
    if (!$files)
    {
      $this->logSection(
        'mysql', 'There are no files to load data from', null, 'ERROR'
      );
      return;
    }


    if (!$options['append'] && !$options['no-confirmation'])
    {
      // check if we need to confirm database drop
        $confirmation = $this->askConfirmation(
          array(
            'This task will replace data in the database with whats specified ',
            'in the SQL files. This will drop all data currently stored',
            '',
            'Are you sure you want to proceed? (y/N)'
          ),
          'QUESTION_LARGE',
          false
        );

        if (!$confirmation)
        {
          $this->logSection('mysql', 'SQL load task aborted');
          return 1;
        }
    }

    if (!$options['append'])
    {

      $this->logSection('doctrine', 'Dropping database');
      $database->getDoctrineConnection()->dropDatabase();
      $this->logSection('doctrine', 'Creating empty database');
      $database->getDoctrineConnection()->createDatabase();
    }

    $importer = new sfDoctrineMysqlSafeMigrateMysqlCliImport(
      $database->getDoctrineConnection(),
      sfConfig::get('app_sfDoctrineMysqlSafeMigratePlugin_mysql_path'),
      sfConfig::get('app_sfDoctrineMysqlSafeMigratePlugin_mysql_arguments')
    );

    foreach ($files as $file)
    {
      $this->logSection('mysql', 'Importing ' . $file);
      $importer->importFrom($file);
      $this->logSection(
        'mysql', 'Completed importing ' . $file . ' successfully'
      );
    }
    
    $this->logSection('mysql', 'SQL load completed');

  }

}
