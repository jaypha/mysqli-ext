<?php
//----------------------------------------------------------------------------
//  Unit tests for KeyValue class
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\KeyValue;

class KeyValueTest extends TestCase
{
  const tableName = "__TestTable__";

  protected static $mysqli;

  public static function setUpBeforeClass()
  {
    self::$mysqli = new MySQLiExt
    (
      $GLOBALS["DB_HOST"],
      $GLOBALS["DB_USER"],
      $GLOBALS["DB_PASSWD"],
      $GLOBALS["DB_DBNAME"]
    );
  }

  protected $kv;

  function setup()
  {
    KeyValue::drop(self::$mysqli, self::tableName);
    KeyValue::create(self::$mysqli, self::tableName);
    $this->kv = KeyValue::get(self::$mysqli, self::tableName);
  }

  function testNull()
  {
    $this->AssertNull($this->kv->billy);
    $this->kv->billy = "bob";
    $this->AssertEquals(self::$mysqli->queryValue("select count(*) from ".self::tableName),1);
    $this->AssertEquals($this->kv->billy, "bob");
    $this->kv->billy = null;
    $this->AssertEquals(self::$mysqli->queryValue("select count(*) from ".self::tableName),0);
    $this->AssertNull($this->kv->billy);
  }

  function testSet()
  {
    $this->kv->with = "bob";
    $this->AssertEquals(self::$mysqli->queryValue("select count(*) from ".self::tableName),1);
    $v = unserialize(self::$mysqli->queryValue("select value from ".self::tableName." where name='with'"));
    $this->AssertEquals($v, "bob");
    $this->AssertEquals($this->kv->with, "bob");
    $this->kv->with = [1,2,3];
    $v = unserialize(self::$mysqli->queryValue("select value from ".self::tableName." where name='with'"));
    $this->AssertEquals($v, [1,2,3]);
    $this->AssertEquals($this->kv->with, [1,2,3]);
  }

  function testCache()
  {
    $this->kv->with = "bob";
    $this->AssertEquals($this->kv->with, "bob");
    $this->kv->clearCache();
    $this->AssertEquals($this->kv->with, "bob");
    $this->kv->clear();
    $this->AssertNull($this->kv->with);
    $this->AssertEquals(self::$mysqli->queryValue("select count(*) from ".self::tableName),0);
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
