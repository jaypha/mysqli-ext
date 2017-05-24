<?php
//----------------------------------------------------------------------------
// Convenience extensions to mysqli
//----------------------------------------------------------------------------

namespace Jaypha;

class MySQLiExt extends \mysqli
{
  function __construct($host, $user, $password = NULL, $database = NULL)
  {
    parent::__construct($host, $user, $password, $database);
    if ($this->connect_error)
      throw new \Exception("MySQLiExt failed to connect to $host as $user: ($this->connect_errno) '$this->connect_error'");
  }

  function query($query)
  {
    $r = parent::query($query);

    if (!$r)
      throw new \Exception("Query failed: ($this->errno) '$this->error'");
    return $r;
  }

  //-------------------------------------------------------------------------

  function quote($value)
  {
    if ($value === NULL)
      return 'null';

    if (is_bool($value))
      return (int) $value;

    // Protect digit sequences beginning with '0' (eg phone numbers)
    if (is_string($value) && substr($value, 0,1) == "0")
     return "'".$this->real_escape_string($value)."'";

    if (is_numeric($value))
      return $value;

    return "'".$this->real_escape_string($value)."'";
  }

  //-------------------------------------------------------------------------
  // Convenience methods.
  //-------------------------------------------------------------------------

  function queryValue($query)
  {
    $res = $this->query($query);
    if (($row = $res->fetch_row()) != NULL)
      $value = $row[0];
    else
      $value = false;
    $res->close();
    return $value;
  }

  //-------------------------------------------------------------------------

  function queryRow($query, $resultType = MYSQLI_ASSOC)
  {
    $res = $this->query($query);
    if ($res->num_rows == 0)
      $row = false;
    else
      $row = $res->fetch_array($resultType);
    $res->close();
    return $row;
  }

  //-------------------------------------------------------------------------

  function queryData($query, $idColumn=NULL, $resultType = MYSQLI_ASSOC)
  {
    $data = [];
    $res = $this->query($query);
    if ($idColumn == NULL)
      $data = $res->fetch_all($resultType);
    else while ($row = $res->fetch_array($resultType))
    {
      if ($idColumn)
        $data[$row[$idColumn]] = $row;
    }
    $res->close();
    return $data;
  }

  //-------------------------------------------------------------------------
  // If two fields are queried, the first is used as an index.

  function queryColumn($query)
  {
    $data = [];
    $res = $this->query($query);
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

  function insert($table, $columns, $values = NULL)
  {
    // Three possibilities
    // string[string] columns, null
    // string[] columns, string[] values
    // string[] columns, string[][] values

    if ($values === NULL)
    {
      $values = array_values($columns);
      $columns = array_keys($columns);
    }

    $query = "insert into $table (".implode(",",$columns).") values (";
    if (!is_array($values[0]))
    {
      $query .= implode(",",array_map([$this,"quote"],$values));
    }
    else
    {
      $query .= implode("),(",array_map(function($a){ return implode(",",array_map([$this,"quote"],$a));},$values));
    }
    $query .= ")";

    $this->query($query);
    return $this->insert_id;
  }

  //-------------------------------------------------------------------------

  function update($table, $values, $wheres)
  {
    assert(is_array($values));
    assert(is_array($wheres));

    $v = $this->_make_clauses($values);
    $w = $this->_make_clauses($wheres);

    $this->query
    (
      "update $table set ".
      implode(",",$v).
      " where ".
      implode(" and ",$w)
    );
  }

  function replace($table, $values)
  {
    assert(is_array($values));
    $v = $this->_make_clauses($values);
    $this->query
    (
      "replace `$table` set ".
      implode(",",$v)
    );
    return $this->insert_id;
  }

  //-------------------------------------------------------------------------

  function insertUpdate($table, $values, $wheres)
  {
    assert(is_array($values));
    assert(is_array($wheres));

    $wClause = implode(" and ", $this->_make_clauses($wheres));

    if ($this->queryValue("select count(*) from $table where $wClause") != 0)
    {
      foreach ($values as $key => &$value)
        $v[] = "`$key`=".$this->quote($value);

      $this->query
      (
        "update $table set ".
        implode(",",$v).
        " where $wClause"
      );
      return 0;
    }
    else
    {
      return $this->insert($table, array_merge($values, $wheres));
    }
  }

  //-------------------------------------------------------------------------

  function delete($table, $wheres)
  {
    if (is_int($wheres))
    {
      // 'wheres' is an ID value
      $this->query("delete from $table where id=$wheres");
    }
    else
    {
      assert(is_array($wheres));
      $wClause = implode(" and ", $this->_make_clauses($wheres));
      $this->query("delete from $table where $wClause");
    }

    return $this->affected_rows;
  }

  //-------------------------------------------------------------------------
  // CRUD style accessors that assume the existance of an "id" column

  function get($table, int $id)
  {
    return $this->queryRow("select * from $table where id=$id");
  }

  function set($table, $values, int $id = 0)
  {
    if ($id)
      $this->update($table, $values, [ "id" => $id ]);
    else
      $id = $this->insert($table, $values);
    return $id;
  }

  private function remove($table, int $id)
  {
    $this->query("delete from $table where id=$id");
    return $this->affected_rows;
  }

  //----------------------------------------------------------------------------

  protected function _make_clause($key, $value)
  {
    return "`$key`=".$this->quote($value);
  }

  protected function _make_clauses(&$wheres)
  {
    $w = [];
    foreach ($wheres as $key => &$value)
      $w[] = $this->_make_clause($key,$value);
    return $w;
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2017 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//

