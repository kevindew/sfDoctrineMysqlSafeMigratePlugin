<?php

/**
 * This class is for importing a backup of a mysql database from a dump
 *
 * @package    symfony
 * @subpackage doctrine
 * @author     Kevin Dew <kev@dewsolutions.co.uk>
 */
class sfDoctrineMysqlSafeMigrateMysqlCliImport
  extends sfDoctrineMysqlSafeMigrateMysqlCliBase
{
  /**
   * The path to mysql bin
   *
   * @var   string
   */
  protected $_mysqlPath = 'mysql';

  /**
   * Arguments for mysql bin
   *
   * @var   string
   */
  protected $_mysqlArguments = '';

  /**
   * @param   Doctrine_Connection_Mysql $connection
   * @param   string|null               $mysqlPath (Optional) default null
   * @param   string|null               $mysqlArguments (Optional) default
   *                                    null
   * @return  void
   */
  public function  __construct(
    Doctrine_Connection_Mysql $connection, 
    $mysqlPath = null,
    $mysqlArguments = null
  )
  {
    parent::__construct($connection);

    if ($mysqlPath !== null)
    {
      $this->setMysqlPath($mysqlPath);
    }

    if ($mysqlArguments !== null)
    {
      $this->setMysqlArguments($mysqlArguments);
    }
  }

  /**
   * @param   string $mysqlPath
   * @return  self
   */
  public function setMysqlPath($mysqlPath)
  {
    $this->_mysqlPath = $mysqlPath;
    return $this;
  }

  /**
   *
   * @return  string
   */
  public function getMysqlPath()
  {
    return $this->_mysqlPath;
  }

  /**
   * @param   string  $mysqlArguments
   * @return  self
   */
  public function setMysqlArguments($mysqlArguments)
  {
    $this->_mysqlArguments = $mysqlArguments;
    return $this;
  }

  /**
   * @return  string
   */
  public function getMysqlArguments()
  {
    return $this->_mysqlArguments;
  }

  /**
   * Import the data within the path to mysql
   *
   * @param   string  $path The path to the file that will contain the queries
   * @throws  Exception
   */
  public function importFrom($path = '')
  {
    $args = '';

    if ($this->getUsername())
    {
      $args .= ' --user=' . escapeshellarg($this->getUsername());
    }

    if ($this->getPassword())
    {
      $args .= ' --password=' . escapeshellarg($this->getPassword());
    }

    if ($this->getHost())
    {
      $args .= ' --host=' . escapeshellarg($this->getHost());
    }

    if ($this->getPort())
    {
      $args .= ' --port=' . escapeshellarg($this->getPort());
    }

    if ($this->getDbName())
    {
      $args .= ' ' . escapeshellarg($this->getDbName());
    }

    exec(
      escapeshellcmd($this->getMysqlPath())
      . ($this->getMysqlArguments()
        ? ' ' . escapeshellarg($this->getMysqlArguments())
        : ''
      )
      . $args 
      . ' < ' . escapeshellarg($path)
      . ' 2>&1'
      , $output, $return
    );

    if ($return > 0)
    {

      throw new Exception(
        'Mysql import failed. Command Returned: ' . implode(PHP_EOL, $output)
      );
    }
  }
}