<?php
//----------------------------------------------------------------------------
// Key/value store
//----------------------------------------------------------------------------

namespace Jaypha;

//-----------------------------------------------------------------------------
// Values are stored as a seraialised PHP variable. So any PHP data type
// can be stored.
//
// Values are set and retireved via __get and __set. So $o->bead will obtain
// the value of bead, and $o->bead = 'x' will set it.
//-----------------------------------------------------------------------------

class KeyValue
{
  private static $instances;

  //-------------------------------------------------------------------------

  public static function create(\mysqli $connection, $tablename)
  {
    $connection->query
    (
<<<SSSS
CREATE TABLE $tablename (
  name varchar(100) NOT NULL,
  value text NOT NULL,
  PRIMARY KEY (name(100))
) ENGINE=MyISAM CHARACTER SET='utf8mb4';
SSSS
    );
  }
  
  //-------------------------------------------------------------------------

  public static function drop(\mysqli $connection, $tablename)
  {
    $connection->query("drop table $tablename");
    if (!isset(self::$instances[$tablename]))
      unset(self::$instances[$tablename]);
  }

  //-------------------------------------------------------------------------

  /*
   * Obtains an instance if this class to act as a interface to the table.
   * If the table does not exist, create it.
   */

  public static function get(\mysqli $connection, $tablename)
  {
    if (!isset(self::$instances[$tablename]))
      self::$instances[$tablename] = new KeyValue($connection, $tablename);

    return self::$instances[$tablename];
  }

  //-------------------------------------------------------------------------

  private $cache = []; // Use a cache to imporve performance.
  private $tablename;
  private $connection;

  //-------------------------------------------------------------------------

  private function __construct(\mysqli $connection, $tablename)
  {
    $this->connection = $connection;
    $this->tablename = $tablename;
  }

  //-------------------------------------------------------------------------

  function clearCache()
  {
    $this->cache = [];
  }

  //-------------------------------------------------------------------------
  
  function clear()
  {
    $this->connection->query("delete from $this->tablename");
    $this->cache = [];
  }

  //-------------------------------------------------------------------------

  function lockWrite()
  {
    $this->connection->query("lock table $this->tablename write", __FUNCTION__);
  }

  //-------------------------------------------------------------------------

  function unlock()
  {
    $this->connection->query("unlock tables", __FUNCTION__);
  }
  
  //-------------------------------------------------------------------------

  /*
   * get and set interact with the database. $p is the name of the value, $v
   * is the value. If the value is not in the database, NULL is returned.
   */

  public function __get(string $p)
  {
    if (!isset($this->cache[$p]))
    {
      $result = $this->connection->query("select value from $this->tablename where name='$p'");
      $r = $result->fetch_row();
      if ($r == NULL)
        $this->cache[$p] = NULL;
      else
        $this->cache[$p] = unserialize($r[0]);
      $result->close();
    }

    return $this->cache[$p];
  }

  //-------------------------------------------------------------------------

  /* If $v is NULL, the value is removed from the database. */

  public function __set(string $p, $v)
  {
    $this->cache[$p] = $v;

    $p = $this->connection->real_escape_string($p);
    if ($v === NULL)
      $this->connection->query("delete from $this->tablename where name='$p'");
    else
    {
      $v = $this->connection->real_escape_string(serialize($v));
      $this->connection->query("replace $this->tablename set name='$p', value='$v'");
    }
  }

  //-------------------------------------------------------------------------
}

//----------------------------------------------------------------------------
// Copyright (C) 2017 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk

