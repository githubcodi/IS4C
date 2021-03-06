<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

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

/**
  @class CustReceiptMessageModel
*/
class CustReceiptMessageModel extends BasicModel
{

    protected $name = "custReceiptMessage";
    protected $preferred_db = 'op';

    protected $columns = array(
    'card_no' => array('type'=>'INT','index'=>true),
    'msg_text' => array('type'=>'VARCHAR(255)'),
    'modifier_module' => array('type'=>'VARCHAR(50)'),
    );

    public function doc()
    {
        return '
Depends on:
* custdata (table)

Use:
Create member-specific messages for
receipts.

* card_no is the member number
* msg_text is the message itself
* modifier_module is [optionally] the name
  of a class that should be invoked
  to potentially modify the message.
  An equity message, for example, might
  use a modifier module to check and see
  if payment was made in the current 
  transaction
        ';
    }
}

