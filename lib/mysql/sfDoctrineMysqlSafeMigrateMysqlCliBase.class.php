<?php

/**
 * This class is a base class for using MySQL CLI bins
 *
 * @package    symfony
 * @subpackage doctrine
 * @author     Kevin Dew <kev@dewsolutions.co.uk>
 */
abstract class sfDoctrineMysqlSafeMigrateMysqlCliBase
{
  /**
   * @var   Doctrine_Connection_Mysql
   */
  protected $_connection;

  /**
   * @var   string
   */
  protected $_username;

  /**
   * @var   string
   */
  protected $_password;

  /**
   * @var   string
   */
  protected $_host;

  /**
   * @var   string
   */
  protected $_port;

  /**
   * @var   string
   */
  protected $_dbName;

  /**
   * This builds up the variables for this class based off the doctrine
   * connection object
   *
   * @param Doctrine_Connection_Mysql $connection
   */
  public function  __construct(Doctrine_Connection_Mysql $connection)
  {
    $this->setConnection($connection);
  }

  /**
   * As well as setting connection this also populates the other variables
   * of this class from data in the connection object
   *
   * @param   Doctrine_Connection_Mysql $connection
   * @return  self
   */
  public function setConnection(Doctrine_Connection_Mysql $connection)
  {
    $this->_connection = $connection;

    $dsnParts = $this->_parseDsn($connection->getOption('dsn'));

    $this
      ->setUsername($connection->getOption('username'))
      ->setPassword($connection->getOption('password'))
      ->setHost($dsnParts['host'])
      ->setPort($dsnParts['port'])
      ->setDbName($dsnParts['dbname'])
    ;
    
    return $this;
  }

  /**
   * @return  Doctrine_Connection_Mysql
   */
  public function getConnection()
  {
    return $this->_connection;
  }

  /**
   * @param   string  $host
   * @return  self
   */
  public function setHost($host)
  {
    $this->_host = $host;

    return $this;
  }

  /**
   * @return  string
   */
  public function getHost()
  {
    return $this->_host;
  }

  /**
   * @param   string  $port
   * @return  self
   */
  public function setPort($port)
  {
    $this->_port = $port;

    return $this;
  }

  /**
   * @return  string
   */
  public function getPort()
  {
    return $this->_port;
  }

  /**
   * @param   string  $username
   * @return  self
   */
  public function setUsername($username)
  {
    $this->_username = $username;

    return $this;
  }

  /**
   * @return  string
   */
  public function getUsername()
  {
    return $this->_username;
  }

  /**
   * @param   string  $password
   * @return  self
   */
  public function setPassword($password)
  {
    $this->_password = $password;

    return $this;
  }

  /**
   * @return  string
   */
  public function getPassword()
  {
    return $this->_password;
  }

  /**
   * @param   string  $dbName
   * @return  self
   */
  public function setDbName($dbName)
  {
    $this->_dbName = $dbName;

    return $this;
  }

  /**
   * @return  string
   */
  public function getDbName()
  {
    return $this->_dbName;
  }

  /**
   * Takes a valid dsn string and parses it into an array
   *
   * @param   string    $dsnString
   * @return  array     array keys are host, port, dbname, unix_socket, charset
   *                    these are null if data is present in dsn string
   */
  protected static function _parseDsn($dsnString)
  {
    $dsn = array(
      'host'        => null,
      'port'        => null,
      'dbname'      => null,
      'unix_socket' => null,
      'charset'     => null
    );

    // get the bit after the colon
    $colonPosition = strpos($dsnString, ':');

    if ($colonPosition === false)
    {
      return $dsn;
    }

    $dsnString = substr($dsnString, $colonPosition + 1);

    $dsnParts = explode(';', $dsnString);

    foreach ($dsnParts as $value)
    {
      $split = explode('=', $value, 2);
      
      if (isset($split[1]) && array_key_exists($split[0], $dsn))
      {
        $dsn[$split[0]] = $split[1];
      }
    }

    return $dsn;
  }

}