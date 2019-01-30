<?php
//----------------------------------------------------------------------------
// Class for handling large data sets returned by queries.
//----------------------------------------------------------------------------
// If, for whetever reason, you cannot have an open query, but you also
// cannot load the whole query result into memory, then this class works
// as a compromise. The result is stored into a temporary table, and then
// read in chunks as separate queries. Implements Iterator so it can be
// used with foreach.
//----------------------------------------------------------------------------

namespace Jaypha;

//----------------------------------------------------------------------------

class MySQLiChunkedResult implements \Iterator, \Countable
{
  private $mysql, $query, $count = null;
  private $offset, $idx, $limit;
  private $tableName, $data, $resultType;

  public $asColumn = false;
  
  //-------------------------------------------------------

  function __construct($mysql, string $query, int $limit = 1000, $resultType = MYSQLI_ASSOC)
  {
    assert($limit > 0);
    assert(method_exists($mysql,"queryData"));
    assert(method_exists($mysql,"queryValue"));
    $this->query = $query;
    $this->mysql = $mysql;
    $this->limit = $limit;
    $this->resultType = $resultType;
  }

  //-------------------------------------------------------

  function count()
  {
    if ($this->count !== null) return $this->count;
    else return $this->mysql->queryValue(
      "select count(*) from ($this->query) as q"
    );
  }

  //-------------------------------------------------------

  function current ()
  { return $this->data[$this->idx]; }

  //-------------------------------------------------------

  function key ()
  { return $this->offset+$this->idx; }

  //-------------------------------------------------------

  function next ()
  {
    ++$this->idx;
    if ($this->idx == count($this->data))
    {
      $this->offset += $this->limit;
      $this->idx = 0;
      $sql = "select * from $this->tableName limit $this->limit offset $this->offset";
      if ($this->asColumn)
        $this->data = $this->mysql->queryColumn($sql);
      else
        $this->data = $this->mysql->queryData($sql, null, $this->resultType);
      assert(count($this->data) <= $this->limit);
    }      
  }

  //-------------------------------------------------------

  function rewind ()
  {
    $this->offset = 0;
    $this->idx = 0;
    $this->tableName = "_".\bin2hex(\random_bytes(6));
    $this->mysql->q(
      "create temporary table $this->tableName as ($this->query)"
    );
    $this->count = $this->mysql->queryValue(
      "select count(*) from $this->tableName"
    );
    $sql = "select * from $this->tableName limit $this->limit";
    if ($this->asColumn)
      $this->data = $this->mysql->queryColumn($sql);
    else
      $this->data = $this->mysql->queryData($sql,null, $this->resultType);
  }

  //-------------------------------------------------------

  function valid ()
  {
    return ($this->offset + $this->idx < $this->count);
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2017 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
