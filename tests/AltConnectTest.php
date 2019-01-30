<?php
//----------------------------------------------------------------------------
//
//----------------------------------------------------------------------------


use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;

class AltConnectTest extends TestCase
{
  const tableName = "__TestTable__";
  const tableDef = "CREATE TABLE `".self::tableName."` (`id` int(11) NOT NULL AUTO_INCREMENT,  `name` varchar(255) NOT NULL DEFAULT '',  `age` int(11) DEFAULT NULL,  PRIMARY KEY (`id`)) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;";

  function testBadConnect()
  {
    $this->expectException(\RuntimeException::class);
    $mysqli = new MySQLiExt
    (
      "localhost",
      "nouser",
      "badpassword"
    );
  }

  function testAlternate()
  {
    $mysqli = new MySQLiExt();
    @$this->assertNull($mysqli->ping());

    $mysqli->real_connect
    (
      $GLOBALS["DB_HOST"],
      $GLOBALS["DB_USER"],
      $GLOBALS["DB_PASSWD"],
      $GLOBALS["DB_DBNAME"]
    );    
    $this->assertTrue($mysqli->ping());
    $mysqli->close();
  }

  function testBadAltConnect()
  {
    $this->expectException(\RuntimeException::class);
    $mysqli = new MySQLiExt();
    $mysqli->real_connect
    (
      "localhost",
      "nouser",
      "badpassword"
    );
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2019 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
