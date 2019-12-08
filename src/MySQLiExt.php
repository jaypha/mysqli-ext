<?php
//----------------------------------------------------------------------------
// Convenience extensions to mysqli
//----------------------------------------------------------------------------

namespace Jaypha;

const MYSQL_DATETIME_FORMAT = "Y-m-d H:i:s";

trait MySQLiExtTrait
{
  function q($query)
  {
    $r = $this->query($query);

    if ($r === false)
      throw new \LogicException("Query failed: ($this->errno) '$this->error'\nQuery: $query");
    return $r;
  }

  //-------------------------------------------------------------------------

  function mq($query)
  {
    $r = $this->multi_query($query);

    if (!$r)
      throw new \LogicException("Query failed: ($this->errno) '$this->error'\nQuery: $query");
  }

  //-------------------------------------------------------------------------

  function quote($value)
  {
    if ($value === null)
      return 'null';

    // Note that this does not take into account timezone differences.
    if ($value instanceof \DateTimeInterface)
      return "'".$value->format(MYSQL_DATETIME_FORMAT)."'";

    assert (!is_object($value));

    if (is_bool($value))
      return (int) $value;

    // Protect digit sequences beginning with '0' (eg phone numbers)
    if (is_string($value) && substr($value, 0,1) == "0")
     return "'".$this->real_escape_string($value)."'";

    if (is_numeric($value))
      return $value;

    // Create comma separated lists for arrays.
    if (is_array($value))
    {
      $res = [];
      foreach ($value as $v)
      {
        if (is_array($v))
          $res[] = "(".$this->quote($v).")";
        else
          $res[] = $this->quote($v);
      }
      return implode(",",$res);
    }

    return "'".$this->real_escape_string($value)."'";
  }

  //-------------------------------------------------------------------------
  // Convenience methods.
  //-------------------------------------------------------------------------

  function queryValue($query)
  {
    $res = $this->q($query);
    if (($row = $res->fetch_row()) != null)
      $value = $row[0];
    else
      $value = false;
    $res->close();
    return $value;
  }

  //-------------------------------------------------------------------------

  function queryRow($query, $resultType = MYSQLI_ASSOC)
  {
    $res = $this->q($query);
    if ($res->num_rows == 0)
      $row = false;
    else
      $row = $res->fetch_array($resultType);
    $res->close();
    return $row;
  }

  //-------------------------------------------------------------------------

  function queryData($query, $keyField=null, $resultType = MYSQLI_ASSOC)
  {
    $data = [];
    $res = $this->q($query);
    if ($keyField == null)
      $data = $res->fetch_all($resultType);
    else while ($row = $res->fetch_array($resultType))
    {
      if ($keyField)
        $data[$row[$keyField]] = $row;
    }
    $res->close();
    return $data;
  }

  //-------------------------------------------------------------------------

  function queryChunkedData($query, $limit = 1000, $resultType = MYSQLI_ASSOC)
  {
    return new MySQLiChunkedResult($this, $query, $limit, $resultType);
  }

  function queryChunkedColumn($query, $limit = 1000)
  {
    $r = new MySQLiChunkedResult($this, $query, $limit);
    $r->asColumn = true;
    return $r;
  }

  //-------------------------------------------------------------------------
  // If two fields are queried, the first is used as an index.

  function queryColumn($query)
  {
    $data = [];
    $res = $this->q($query);
    while ($row = $res->fetch_row())
    {
      if ($res->field_count == 1)
        $data[] = $row[0];
      else
        $data[$row[0]] = $row[1];
    }
    $res->close();
    return $data;
  }

  //-------------------------------------------------------------------------

  function insert($tableName, $columns, $values = null)
  {
    // Three possibilities
    // string[string] columns, null
    // string[] columns, string[] values
    // string[] columns, string[][] values

    if ($values === null)
    {
      $values = array_values($columns);
      $columns = array_keys($columns);
    }

    $query = "insert into $tableName (`".implode("`,`",$columns)."`) values ";
    if (!is_array($values[0]))
      $query .= "(".$this->quote($values).")";
    else
      $query .= $this->quote($values);
//    {
//      $query .= implode(",",array_map([$this,"quote"],$values));
//    }
//    else
//    {
//      $query .= implode("),(",array_map(function($a){ return implode(",",array_map([$this,"quote"],$a));},$values));
//    }

    $this->q($query);
    return $this->insert_id;
  }

  //-------------------------------------------------------------------------

  function update($tableName, $values, $wheres)
  {
    assert(is_array($values));
    assert(is_array($wheres));

    $v = [];
    foreach ($values as $key => &$value)
      $v[] = "`$key`=".$this->quote($value);

    $w = $this->makeClauses($wheres);

    $this->q
    (
      "update $tableName set ".
      implode(",",$v).
      " where ".
      implode(" and ",$w)
    );
  }

  //-------------------------------------------------------------------------

  function replace(string $tableName, array $values)
  {
    $v = $this->makeClauses($values);
    $this->q
    (
      "replace `$tableName` set ".
      implode(",",$v)
    );
    return $this->insert_id;
  }

  //-------------------------------------------------------------------------

  function insertUpdate(string $tableName, array $values, array $wheres)
  {
    $wClause = implode(" and ", $this->makeClauses($wheres));

    if ($this->queryValue("select count(*) from $tableName where $wClause") != 0)
    {
      if (count($values))
      {
        $v = [];
        foreach ($values as $key => &$value)
          $v[] = "`$key`=".$this->quote($value);

        $this->q
        (
          "update $tableName set ".
          implode(",",$v).
          " where $wClause"
        );
      }
      return null;
    }
    else
    {
      return $this->insert($tableName, array_merge($values, $wheres));
    }
  }

  //-------------------------------------------------------------------------

  function delete(string $tableName, $wheres = null)
  {
    if ($wheres === null)
    {
      $this->q("truncate $tableName");
    }
    else if (is_int($wheres) || (is_string($wheres) && ctype_digit($wheres)))
    {
      // 'wheres' is an ID value
      $this->q("delete from $tableName where id=$wheres");
    }
    else
    {
      assert(is_array($wheres));
      $wClause = implode(" and ", $this->makeClauses($wheres));
      $this->q("delete from $tableName where $wClause");
    }

    return $this->affected_rows;
  }

  //-------------------------------------------------------------------------
  // CRUD style accessors that assume the existance of an "id" column

  function get(string $tableName, int $id)
  {
    return $this->queryRow("select * from $tableName where id=$id");
  }

  function set(string $tableName, array $values, int $id = 0)
  {
    if ($id)
    {
      if ($this->queryValue("select count(*) from $tableName where id=$id")==0)
      {
        $values["id"] = $id;
        $this->insert($tableName, $values);
      }
      else
        $this->update($tableName, $values, [ "id" => $id ]);
    }
    else
      $id = $this->insert($tableName, $values);
    return $id;
  }

  //----------------------------------------------------------------------------

  public function tableExists($tablename)
  {
    $fromdb = "";

    if ($pos = strpos($tablename, "."))
    {
      $fromdb = "from `".substr($tablename, 0, $pos)."`";
      $tablename = substr($tablename, $pos+1);
    }

    $query = "show tables $fromdb like ".$this->quote($tablename);
    $result = $this->q($query);

    $exists = mysqli_num_rows($result) != 0;
    mysqli_free_result($result);
    return $exists;
  }

  //----------------------------------------------------------------------------

  function lockWrite($tablename)
  {
    $this->q("lock table $tablename write", __FUNCTION__);
  }

  //-------------------------------------------------------------------------

  function unlock()
  {
    $this->q("unlock tables", __FUNCTION__);
  }

  //----------------------------------------------------------------------------

  function makeClause($key, $value)
  {
    if ($value === null)
      return "`$key` is NULL";
    else if (is_array($value))
    {
      if (count($value))
        return "`$key` in (".$this->quote($value).")";
      else
        return "false";
    }
    else
      return "`$key`=".$this->quote($value);
  }

  //----------------------------------------------------------------------------

  function makeClauses(array &$wheres)
  {
    $w = [];
    foreach ($wheres as $key => &$value)
      $w[] = $this->makeClause($key,$value);
    return $w;
  }
}

//----------------------------------------------------------------------------

class MySQLiExt extends \mysqli
{
  use MySQLiExtTrait;

  function __construct($host = null, $username = null, $password = null, $database = null)
  {
    if ($host != null)
    {
      @parent::__construct($host, $username, $password, $database);
      if ($this->connect_error)
        throw new \RuntimeException("MySQLiExt failed to connect to $host as $username: ($this->connect_errno) '$this->connect_error'");
    }
    else
      parent::init();
  }

  function real_connect($host = null, $username = null, $password = null, $database = null, $port = null, $socket = null, $flag = null)
  {
    @parent::real_connect($host,$username,$password,$database,$port,$socket,$flag);
    if ($this->connect_error)
      throw new \RuntimeException("MySQLiExt failed to connect to $host as $username: ($this->connect_errno) '$this->connect_error'");
  }
}


//----------------------------------------------------------------------------
// Copyright (C) 2017 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//

