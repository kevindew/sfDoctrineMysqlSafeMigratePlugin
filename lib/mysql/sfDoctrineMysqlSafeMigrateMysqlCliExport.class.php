<?php

/**
 * This class is for exporting a MySQL database using mysqldump
 *
 * @package    symfony
 * @subpackage doctrine
 * @author     Kevin Dew <kev@dewsolutions.co.uk>
 */
class sfDoctrineMysqlSafeMigrateMysqlCliExport
  extends sfDoctrineMysqlSafeMigrateMysqlCliBase
{
  /**
   * The path to mysqldump bin
   *
   * @var   string
   */
  protected $_mysqlDumpPath = 'mysqldump';

  /**
   * @param   Doctrine_Connection_Mysql $connection
   * @param   string                    $mysqlDumpPath (Optional) default null
   * @return  void
   */
  public function  __construct(
    Doctrine_Connection_Mysql $connection, $mysqlDumpPath = null
  )
  {
    parent::__construct($connection);

    if ($mysqlDumpPath !== null)
    {
      $this->setMysqlDumpPath($mysqlDumpPath);
    }
  }

  /**
   * @param   string  $mysqlDumpPath
   * @return  self
   */
  public function setMysqlDumpPath($mysqlDumpPath)
  {
    $this->_mysqlDumpPath = $mysqlDumpPath;
    return $this;
  }

  /**
   * @return  string
   */
  public function getMysqlDumpPath()
  {
    return $this->_mysqlDumpPath;
  }

  /**
   * Perform a mysqldump and write the contents to the specified file
   *
   * Note: Not sure what the consequences would be for a very large database
   *
   * @param   string  $path The path to the file that will contain the dump data
   * @throws  Exception
   */
  public function exportTo($path = '')
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
      escapeshellcmd($this->getMysqlDumpPath())
      . $args . ' 2>&1'
      , $output, $return
    );

    if ($return > 0)
    {

      throw new Exception(
        'mysqldump failed. Command Returned: ' . implode(PHP_EOL, $output)
      );
    }

    // write output to file
    $result = file_put_contents($path, implode(PHP_EOL, $output));

    if ($result === false)
    {
      throw new Exception('Writing mysqldump to ' . $path . ' failed');
    }
  }
}