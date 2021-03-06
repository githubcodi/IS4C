<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op

    This file is part of IT CORE.

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

namespace COREPOS\pos\lib\Scanning\PriceMethods;
use COREPOS\pos\lib\Scanning\PriceMethod;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\TransRecord;

/**
  @class BigGroupPM
   
  Elaborate set matching; can require up to
  11 separate items
  Qualifying item(s) have a mixmatch code
  with a matching 'stem' plus '_qX'
  (e.g., mmitemstem_q0, mmitemstem_q1, etc)
  Discount item has the same stem plus '_d'
  (e.g, mmitemstem_d)

  Customer has to buy one item from each
  qualifying group as well as an item from
  the discount group. 
*/

class BigGroupPM extends PriceMethod 
{

    public function addItem(array $row, $quantity, $priceObj)
    {
        if ($quantity == 0) return false;

        $pricing = $priceObj->priceInfo($row,$quantity);

        // enforce limit on discounting sale items
        $dsi = $this->session->get('DiscountableSaleItems');
        if ($dsi == 0 && $dsi !== '' && $priceObj->isSale()) {
            $row['discount'] = 0;
        }

        $sets = 99;
        $volume = $priceObj->isSale() ? $row['specialquantity'] : $row['quantity'];
        $mixMatch = $row['mixmatchcode'];
        $stem = substr($mixMatch,0,10);    
        $dbt = Database::tDataConnect();
        // count up total sets
        for($i=0; $i<=($volume-1); $i++){
            $tmp = $stem."_q".$i;
            if ($volume-1 == $i) $tmp = $stem.'_d';

            $chkQ = "SELECT sum(CASE WHEN scale=0 THEN ItemQtty ELSE 1 END) 
                FROM localtemptrans WHERE mixmatch='$tmp' 
                and trans_status<>'R'";
            $chkR = $dbt->query($chkQ);
            $tsets = 0;
            if ($dbt->num_rows($chkR) > 0){
                $chkW = $dbt->fetchRow($chkR);
                $tsets = $chkW[0];
            }
            if ($tmp == $mixMatch){
                $tsets += is_int($quantity)?$quantity:1;
            }

            if ($tsets < $sets)
                $sets = $tsets;

            // item not found, no point continuing
            if ($sets == 0) break;
        }

        // count existing sets
        $matches = 0;
        $matchQ = "SELECT sum(matched) FROM localtemptrans WHERE
            left(mixmatch,11)='{$stem}_'";
        $matchR = $dbt->query($matchQ);
        if ($dbt->num_rows($matchR) > 0) {
            $matchW = $dbt->fetchRow($matchR);
            $matches = $matchW[0];
        }
        $sets -= $matches;

        // this means the current item
        // completes a new set
        if ($sets > 0){
            if ($priceObj->isSale()){
                if ($priceObj->isMemberSale() || $priceObj->isStaffSale())
                    $pricing['memDiscount'] = MiscLib::truncate2($row['specialgroupprice'] * $quantity);
                else
                    $pricing['discount'] = MiscLib::truncate2($row['specialgroupprice'] * $quantity);
            }

            TransRecord::addRecord(array(
                'upc' => $row['upc'],
                'description' => $row['description'],
                'trans_type' => 'I',
                'trans_subtype' => (isset($row['trans_subtype'])) ? $row['trans_subtype'] : '',
                'department' => $row['department'],
                'quantity' => $quantity,
                'unitPrice' => $pricing['unitPrice'],
                'total' => MiscLib::truncate2($pricing['unitPrice'] * $quantity),
                'regPrice' => $pricing['regPrice'],
                'scale' => $row['scale'],
                'tax' => $row['tax'],
                'foodstamp' => $row['foodstamp'],
                'discount' => $pricing['discount'],
                'memDiscount' => $pricing['memDiscount'],
                'discountable' => $row['discount'],
                'discounttype' => $row['discounttype'],
                'ItemQtty' => $quantity,
                'volDiscType' => ($priceObj->isSale() ? $row['specialpricemethod'] : $row['pricemethod']),
                'volume' => ($priceObj->isSale() ? $row['specialquantity'] : $row['quantity']),
                'VolSpecial' => ($priceObj->isSale() ? $row['specialgroupprice'] : $row['groupprice']),
                'mixMatch' => $row['mixmatchcode'],
                'matched' => $sets,
                'cost' => (isset($row['cost'])?$row['cost']*$quantity:0.00),
                'numflag' => (isset($row['numflag'])?$row['numflag']:0),
                'charflag' => (isset($row['charflag'])?$row['charflag']:'')
            ));

            if (!$priceObj->isSale()){
                TransRecord::addhousecoupon('0', 0, MiscLib::truncate2(-1*$sets*$row['groupprice']), 'SET DISCOUNT');
            }
        }
        else {
            // not a new set, treat as a regular item
            TransRecord::addRecord(array(
                'upc' => $row['upc'],
                'description' => $row['description'],
                'trans_type' => 'I',
                'trans_subtype' => (isset($row['trans_subtype'])) ? $row['trans_subtype'] : '',
                'department' => $row['department'],
                'quantity' => $quantity,
                'unitPrice' => $pricing['unitPrice'],
                'total' => MiscLib::truncate2($pricing['unitPrice'] * $quantity),
                'regPrice' => $pricing['regPrice'],
                'scale' => $row['scale'],
                'tax' => $row['tax'],
                'foodstamp' => $row['foodstamp'],
                'discount' => $pricing['discount'],
                'memDiscount' => $pricing['memDiscount'],
                'discountable' => $row['discount'],
                'discounttype' => $row['discounttype'],
                'ItemQtty' => $quantity,
                'volDiscType' => $row['pricemethod'],
                'volume' => $row['quantity'],
                'VolSpecial' => $row['groupprice'],
                'mixMatch' => $row['mixmatchcode'],
                'cost' => (isset($row['cost'])?$row['cost']*$quantity:0.00),
                'numflag' => (isset($row['numflag'])?$row['numflag']:0),
                'charflag' => (isset($row['charflag'])?$row['charflag']:'')
            ));
        }

        return True;
    }
}

