<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of CORE-POS.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

namespace COREPOS\Fannie\API\data\hooks {

/**
  @class BasicModelHook

  Base class to attach actions to models
*/
class BasicModelHook
{
    /**
      List of [string] table names the hook class
      needs access to 
    */
    protected $registered_tables = array();

    /**
      Does hook class operate on the given table
      @param $table_name [string]
      @return [boolean]
    */
    public function operatesOnTable($table_name)
    {
        return in_array($table_name, $this->registered_tables);
    }

    /**
      Save callback 
      This method is called *before* a model is saved
      to the database.
      @param $table_name [string] is the name of the
        table represented by the model. Only useful
        if a hook interacts with multiple tables
      @param $model_obj [object] instance of the model
      @return [none]
    */
    public function onSave($table_name, $model_obj)
    {

    }
}

}

namespace {
    class BasicModelHook extends \COREPOS\Fannie\API\data\hooks\BasicModelHook {}
}

