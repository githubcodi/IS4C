<?php

namespace COREPOS\Fannie\API\data;

/**
  Base class for custom table synchronization
  routines. Intended to replace the old
  symlink based system
*/
class SyncSpecial
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
      Send table to the lanes
      @param $tableName [string]
      @param $dbName [string]
      @return [keyed array]
        - success [boolean]
        - details [string]
    */
    public function push($tableName, $dbName)
    {
        return array('success'=>false, 'details'=>'Just a base class');
    }
}

