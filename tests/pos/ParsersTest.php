<?php
include(dirname(__FILE__).'/../../pos/is4c-nf/parser-class-lib/PreParser.php');
include(dirname(__FILE__).'/../../pos/is4c-nf/parser-class-lib/Parser.php');
if (!class_exists('lttLib')) {
    include(dirname(__FILE__) . '/lttLib.php');
}

/**
 * @backupGlobals disabled
 */
class ParsersTest extends PHPUnit_Framework_TestCase
{
    /**
      Check methods for getting available PreParser and Parser modules
    */
    public function testStatics()
    {
        $chain = PreParser::get_preparse_chain();
        $this->assertInternalType('array',$chain);
        $this->assertNotEmpty($chain);
        foreach($chain as $class){
            $instance = new $class();
            $this->assertInstanceOf('PreParser',$instance);
            // just for coverage; not vital functionality
            $this->assertNotEquals(0, strlen($instance->doc()));
        }

        $chain = Parser::get_parse_chain();
        $this->assertInternalType('array',$chain);
        $this->assertNotEmpty($chain);
        foreach($chain as $class){
            $instance = new $class();
            $this->assertInstanceOf('Parser',$instance);
            // just for coverage; not vital functionality
            $this->assertNotEquals(0, strlen($instance->doc()));
        }
    }

    public function testPreParsers()
    {
        /* set any needed session variables */
        CoreLocal::set('runningTotal',1.99);
        CoreLocal::set('mfcoupon',0);
        CoreLocal::set('itemPD',0);
        CoreLocal::set('multiple',0);
        CoreLocal::set('quantity',0);
        CoreLocal::set('refund',0);
        CoreLocal::set('toggletax',0);
        CoreLocal::set('togglefoodstamp',0);
        CoreLocal::set('toggleDiscountable',0);
        CoreLocal::set('nd',0);
    
        /* inputs and expected outputs */
        $input_output = array(
            'MANUALCC'    => '199CC',
            '5DI123'    => '123',
            '7PD123'    => '123',
            '2*4011'    => '4011',
            '3*100DP10'    => '100DP10',
            'text*blah'    => 'text*blah',
            'RF123'        => '123',
            '123RF'        => '123',
            'RF3*100DP10'    => '100DP10',
            'FN123'        => '123',
            'DN123'        => '123',
            '1TN123'    => '123',
            'invalid'    => 'invalid',
            '123NR'     => '123',
            'NR123'     => '123',
            'CMWHARF'   => 'CMWHARF',
            'RF123VD'   => 'RF123VD',
        );

        $chain = PreParser::get_preparse_chain();
        foreach($input_output as $input => $output){
            foreach($chain as $class){
                $obj = new $class();
                $chk = $obj->check($input);
                $this->assertInternalType('boolean',$chk);
                if ($chk){
                    $input = $obj->parse($input);
                }
            }
            $this->assertEquals($output, $input);
        }

        /* verify correct session values */
        $this->assertEquals(7, CoreLocal::get('itemPD'));
        $this->assertEquals(1, CoreLocal::get('multiple'));
        $this->assertEquals(3, CoreLocal::get('quantity'));
        $this->assertEquals(1, CoreLocal::get('refund'));
        $this->assertEquals(1, CoreLocal::get('toggletax'));
        $this->assertEquals(1, CoreLocal::get('togglefoodstamp'));
        $this->assertEquals(1, CoreLocal::get('toggleDiscountable'));

        CoreLocal::set('itemPD', 0);
        CoreLocal::set('multiple', 0);
        CoreLocal::set('quantity', 0);
        CoreLocal::set('refund', 0);
        CoreLocal::set('toggletax', 0);
        CoreLocal::set('togglefoodstamp', 0);
        CoreLocal::set('toggleDiscountable', 0);
    }

    function testCcMenu()
    {
        $plugins = CoreLocal::get('PluginList');
        CoreLocal::set('PluginList', array('Paycards'));
        $obj = new CCMenu();
        $this->assertEquals(true, $obj->check('CC'));
        $this->assertEquals('QM1', $obj->parse('CC'));
        CoreLocal::set('PluginList', $plugins);
    }

    function testCaseDiscount()
    {
        $obj = new CaseDiscount();
        $this->assertEquals(true, $obj->check('10CT4011'));

        $out = $obj->parse('11CT4011');
        $this->assertEquals('cdinvalid', $out);

        CoreLocal::set('isStaff', 0);
        CoreLocal::set('SSI', 0);
        CoreLocal::set('isMember', 0);
        $out = $obj->parse('10CT4011');
        $this->assertEquals('4011', $out);
        $this->assertEquals(5, CoreLocal::get('casediscount'));

        CoreLocal::set('isMember', 1);
        $out = $obj->parse('10CT4011');
        $this->assertEquals('4011', $out);
        $this->assertEquals(10, CoreLocal::get('casediscount'));

        CoreLocal::set('SSI', 1);
        $out = $obj->parse('10CT4011');
        $this->assertEquals('cdSSINA', $out);

        CoreLocal::set('isStaff', 1);
        $out = $obj->parse('10CT4011');
        $this->assertEquals('cdStaffNA', $out);

        CoreLocal::set('isStaff', 0);
        CoreLocal::set('SSI', 0);
        CoreLocal::set('isMember', 0);
        CoreLocal::set('casediscount', 0);
    }

    function testMemStatusToggle()
    {
        $obj = new MemStatusToggle();
        $this->assertEquals(false, $obj->check('foo'));
        $this->assertEquals('foo', $obj->parse('foo'));
    }

    function testParsers()
    {
        /* inputs and expected outputs */
        $input_output = array(
        'WillNotMatchAnythingEver' => array(),
        );

        $chain = Parser::get_parse_chain();
        foreach($input_output as $input => $output){
            $actual = $output;
            foreach($chain as $class){
                $obj = new $class();
                $chk = $obj->check($input);
                $this->assertInternalType('boolean',$chk, $class . ' returns non-boolean');
                if ($chk){
                    $actual = $obj->parse($input);
                    break;
                }
                $this->assertInternalType('string', $obj->doc());
            }
            $this->assertEquals($output, $actual);
        }
    }

    function testWakeup()
    {
        $obj = new Wakeup();
        $this->assertEquals(true, $obj->check('WAKEUP'));

        $out = $obj->parse('WAKEUP');
        $this->assertEquals('rePoll', $out['udpmsg']);
    }

    function testToggleReceipt()
    {
        $obj = new ToggleReceipt();
        CoreLocal::set('receiptToggle', 0);
        $obj->parse('NR');
        $this->assertEquals(1, CoreLocal::get('receiptToggle'));
        $out = $obj->parse('NR');
        $this->assertEquals(0, CoreLocal::get('receiptToggle'));
        $this->assertEquals('/pos2.php', substr($out['main_frame'], -9));
    }

    function testTotals()
    {
        $t = new Totals();

        $out = $t->parse('FNTL');
        $this->assertEquals('/fsTotalConfirm.php', substr($out['main_frame'], -19));
        $out = $t->parse('TETL');
        $this->assertEquals('=Totals', substr($out['main_frame'], -7));
        lttLib::clear();
        $out = $t->parse('FTTL');
        $this->assertNotEquals(0, strlen($out['output']));
        $this->assertEquals(true, $out['redraw_footer']);
        lttLib::clear();
        $out = $t->parse('TL');
        $this->assertEquals('/memlist.php', substr($out['main_frame'], -12));
        lttLib::clear();
        PrehLib::setMember(1, 1);
        $out = $t->parse('TL');
        $this->assertNotEquals(0, strlen($out['output']));
        $this->assertEquals(true, $out['redraw_footer']);
        lttLib::clear();
        $out = $t->parse('WICTL');
        $this->assertNotEquals(0, strlen($out['output']));
    }

    function testTenderOut()
    {
        $to = new TenderOut();
        $this->assertEquals(true, $to->check('TO'));

        CoreLocal::set('LastID', 0);
        $out = $to->parse('TO');
        $this->assertNotEquals(0, strlen($out['output']));
    }

    function testTenderKey()
    {
        $tk = new TenderKey();
        $this->assertEquals(true, $tk->check('TT'));
        $out = $tk->parse('TT');
        $this->assertEquals('/tenderlist.php', substr($out['main_frame'], -15));

        $this->assertEquals(true, $tk->check('100TT'));
        $out = $tk->parse('100TT');
        $this->assertEquals('/tenderlist.php', substr($out['main_frame'], -15));
        $this->assertEquals(100, CoreLocal::get('tenderTotal'));
    }

    function testSteering()
    {
        $obj = new Steering();

        CoreLocal::set('LastID', 1);
        $obj->check('CAB');
        $out = $obj->parse('CAB');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('LastID', 0);
        $obj->check('CAB');
        $out = $obj->parse('CAB');
        $this->assertEquals('/cablist.php', substr($out['main_frame'], -12));

        $obj->check('PVASDF');
        $out = $obj->parse('PVASDF');
        $this->assertEquals('/productlist.php', substr($out['main_frame'], -16));
        $this->assertEquals('ASDF', CoreLocal::get('pvsearch'));
        $obj->check('TESTPV');
        $out = $obj->parse('TESTPV');
        $this->assertEquals('/productlist.php', substr($out['main_frame'], -16));
        $this->assertEquals('TEST', CoreLocal::get('pvsearch'));

        CoreLocal::set('LastID', 1);
        $obj->check('UNDO');
        $out = $obj->parse('UNDO');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('LastID', 0);
        $obj->check('UNDO');
        $out = $obj->parse('UNDO');
        $this->assertEquals('=UndoAdminLogin', substr($out['main_frame'], -15));

        $obj->check('SK');
        $out = $obj->parse('SK');
        $this->assertEquals('/DDDReason.php', substr($out['main_frame'], -14));
        $obj->check('DDD');
        $out = $obj->parse('DDD');
        $this->assertEquals('/DDDReason.php', substr($out['main_frame'], -14));

        CoreLocal::set('SecuritySR', 21);
        $obj->check('MG');
        $out = $obj->parse('MG');
        $this->assertEquals('=SusResAdminLogin', substr($out['main_frame'], -17));
        CoreLocal::set('SecuritySR', 0);
        $obj->check('MG');
        $out = $obj->parse('MG');
        $this->assertEquals('/adminlist.php', substr($out['main_frame'], -14));

        CoreLocal::set('LastID', 1);
        CoreLocal::set('receiptToggle', 0);
        $obj->check('RP');
        $out = $obj->parse('RP');
        $this->assertEquals(1, CoreLocal::get('receiptToggle'));
        $this->assertEquals('/pos2.php', substr($out['main_frame'], -9));
        $obj->check('RP');
        $out = $obj->parse('RP');
        $this->assertEquals(0, CoreLocal::get('receiptToggle'));
        $this->assertEquals('/pos2.php', substr($out['main_frame'], -9));
        CoreLocal::set('LastID', 0);
        $obj->check('RP');
        $out = $obj->parse('RP');
        $this->assertNotEquals(0, strlen($out['output']));

        $obj->check('ID');
        $out = $obj->parse('ID');
        $this->assertEquals('/memlist.php', substr($out['main_frame'], -12));

        $obj->check('DDM');
        $out = $obj->parse('DDM');
        $this->assertEquals('/drawerPage.php', substr($out['main_frame'], -15));

        CoreLocal::set('LastID', 1);
        $obj->check('NS');
        $out = $obj->parse('NS');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('LastID', 0);
        $obj->check('NS');
        $out = $obj->parse('NS');
        $this->assertEquals('/nslogin.php', substr($out['main_frame'], -12));

        $obj->check('GD');
        $out = $obj->parse('GD');
        $this->assertEquals('/giftcardlist.php', substr($out['main_frame'], -17));

        $obj->check('IC');
        $out = $obj->parse('IC');
        $this->assertEquals('/HouseCouponList.php', substr($out['main_frame'], -20));

        $obj->check('CN');
        $out = $obj->parse('CN');
        $this->assertEquals('/mgrlogin.php', substr($out['main_frame'], -13));

        $obj->check('PO');
        $out = $obj->parse('PO');
        $this->assertEquals('=PriceOverrideAdminLogin', substr($out['main_frame'], -24));

        CoreLocal::set('memType', 1);
        $obj->check('MSTG');
        $out = $obj->parse('MSTG');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('memType', 0);
        CoreLocal::set('SecuritySR', 21);
        $obj->check('MSTG');
        $out = $obj->parse('MSTG');
        $this->assertEquals('=MemStatusAdminLogin', substr($out['main_frame'], -20));
        CoreLocal::set('SecuritySR', 0);
        $obj->check('MSTG');
        $out = $obj->parse('MSTG');
        $this->assertNotEquals(0, strlen($out['output']));

        CoreLocal::set('LastID', 1);
        $obj->check('SS');
        $out = $obj->parse('SS');
        $this->assertNotEquals(0, strlen($out['output']));
        $obj->check('SO');
        $out = $obj->parse('SO');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('LastID', 0);
        CoreLocal::set('LoggedIn', 1);
        $obj->check('SS');
        $out = $obj->parse('SS');
        $this->assertEquals('login.php', substr($out['main_frame'], -9));
        $this->assertEquals(0, CoreLocal::get('LoggedIn'));
        CoreLocal::set('LastID', 0);
        CoreLocal::set('LoggedIn', 1);
        $obj->check('SO');
        $out = $obj->parse('SO');
        $this->assertEquals('login.php', substr($out['main_frame'], -9));
        $this->assertEquals(0, CoreLocal::get('LoggedIn'));
        // may have created log records when signing out
        lttLib::clear();
    }

    function testStackableDiscount()
    {
        $sd = new StackableDiscount();
        $this->assertEquals(false, $sd->check('ZSD'));
        CoreLocal::set('tenderTotal', 1);
        $this->assertEquals(true, $sd->check('100SD'));
        $out = $sd->parse('100SD');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('tenderTotal', 0);
        $this->assertEquals(true, $sd->check('100SD'));
        $out = $sd->parse('100SD');
        $this->assertNotEquals(0, strlen($out['output']));
        $this->assertEquals(true, $sd->check('-1SD'));
        $out = $sd->parse('-1SD');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('percentDiscount', 5);
        $this->assertEquals(true, $sd->check('5SD'));
        $out = $sd->parse('5SD');
        $this->assertEquals(10, CoreLocal::get('percentDiscount'));
        CoreLocal::set('percentDiscount', 0);
        // may have subtotal'd
        lttLib::clear();
    }

    function testScrollItems()
    {
        $inputs = array('D', 'U', 'D5', 'U5');
        $obj = new ScrollItems();
        foreach ($inputs as $input) {
            $this->assertEquals(true, $obj->check($input));
            $out = $obj->parse($input);
            $this->assertNotEquals(0, strlen($out['output']));
        }
    }

    function testScaleInput()
    {
        $obj = new ScaleInput();
        $out = $obj->parse('S111234');
        $this->assertEquals(1, CoreLocal::get('scale'));
        $this->assertEquals(12.34, CoreLocal::get('weight'));
        $this->assertEquals('S111234', $out['scale']);

        $out = $obj->parse('S143');
        $this->assertEquals(0, CoreLocal::get('scale'));
        $this->assertEquals('S143', $out['scale']);
    }

    function testRepeatKey()
    {
        $rk = new RepeatKey();
        $this->assertEquals(true, $rk->check('*'));
        $this->assertEquals(true, $rk->check('*2'));
        $dbc = Database::tDataConnect();
        $upc = new UPC();
        lttLib::clear();
        $out = $rk->parse('*');
        $this->assertNotEquals(0, strlen($out['output']));
        $upc->parse('666');
        lttLib::console('added UPC');
        $out = $rk->parse('*');
        lttLib::console('repeated UPC');
        $this->assertNotEquals(0, strlen($out['output']));
        $query = 'SELECT * FROM localtemptrans WHERE upc=\'0000000000666\'';
        $res = $dbc->query($query);
        $this->assertEquals(2, $dbc->numRows($res));
        lttLib::clear();
        $upc->parse('666');
        $out = $rk->parse('*2');
        $this->assertNotEquals(0, strlen($out['output']));
        $query = 'SELECT * FROM localtemptrans WHERE upc=\'0000000000666\'';
        $res = $dbc->query($query);
        $this->assertEquals(2, $dbc->numRows($res));
        $prep = $dbc->prepare('SELECT SUM(quantity) FROM localtemptrans WHERE upc=\'0000000000666\'');
        $this->assertEquals(3, $dbc->getValue($prep));
        lttLib::clear();
    }

    function testRRR()
    {
        $r = new RRR();
        $this->assertEquals(true, $r->check('RRR'));
        $this->assertEquals(true, $r->check('2*RRR'));
        CoreLocal::set('LastID', 0);
        $out = $r->parse('RRR');
        $this->assertNotEquals(0, strlen($out['output']));
        $out = $r->parse('2*RRR');
        $this->assertNotEquals(0, strlen($out['output']));
        lttLib::clear();
    }

    function testPartialReceipt()
    {
        $obj = new PartialReceipt();
        $out = $obj->parse('PP');
        $this->assertEquals('partial', $out['receipt']);
        $this->assertNotEquals(0, strlen($out['output']));
    }

    function testMemberID()
    {
        $m = new MemberID();
        $this->assertEquals(true, $m->check('1ID'));
        $out = $m->parse('0ID');
        $this->assertNotEquals(0, strlen($out['output']));
        $this->assertEquals(true, $out['redraw_footer']);
        $this->assertEquals('0', CoreLocal::get('memberID'));
        CoreLocal::set('verifyName', 1);
        $out = $m->parse('1ID');
        $this->assertEquals('/memlist.php?idSearch=1', substr($out['main_frame'], -23));
        CoreLocal::set('memberID', 1);
        CoreLocal::set('defaultNonMem', 99999);
        CoreLocal::set('RestrictDefaultNonMem', 1);
        $out = $m->parse('99999ID');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreState::memberReset();
        $out = $m->parse('99999ID');
        $this->assertEquals('/memlist.php?idSearch=99999', substr($out['main_frame'], -27));
        CoreLocal::set('RestrictDefaultNonMem', 0);
        CoreState::memberReset();
    }

    function testLock()
    {
        $obj = new Lock();
        $out = $obj->parse('LOCK');
        $this->assertEquals('/login3.php', substr($out['main_frame'], -11));
    }

    function testDonationKey()
    {
        $d = new DonationKey();
        CoreLocal::set('roundUpDept', 1);
        $this->assertEquals(true, $d->check('RU'));
        $this->assertEquals(true, $d->check('2RU'));
        $dbc = Database::tDataConnect();
        lttLib::clear();
        $out = $d->parse('RU');
        lttLib::console('donated');
        $prep = $dbc->prepare('SELECT SUM(total) FROM localtemptrans');
        $this->assertEquals(1, $dbc->getValue($prep));
        lttLib::clear();
        $out = $d->parse('200RU');
        $prep = $dbc->prepare('SELECT SUM(total) FROM localtemptrans');
        $this->assertEquals(2, $dbc->getValue($prep));
        lttLib::clear();
    }

    function testDiscountApplied()
    {
        $sd = new DiscountApplied();
        $this->assertEquals(false, $sd->check('ZDA'));
        CoreLocal::set('tenderTotal', 1);
        $this->assertEquals(true, $sd->check('100DA'));
        $out = $sd->parse('100DA');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('tenderTotal', 0);
        $this->assertEquals(true, $sd->check('100DA'));
        $out = $sd->parse('100DA');
        $this->assertNotEquals(0, strlen($out['output']));
        $this->assertEquals(true, $sd->check('-1DA'));
        $out = $sd->parse('-1DA');
        $this->assertNotEquals(0, strlen($out['output']));
        CoreLocal::set('percentDiscount', 4);
        $this->assertEquals(true, $sd->check('5DA'));
        $out = $sd->parse('5DA');
        $this->assertEquals(5, CoreLocal::get('percentDiscount'));
        CoreLocal::set('percentDiscount', 0);
        // may have subtotal'd
        lttLib::clear();
    }

    function testDeptKey()
    {
        $d = new DeptKey();
        CoreLocal::set('refund', 1);
        CoreLocal::set('SpecialDeptMap', false);
        $out = $d->parse('1.00DP');
        $this->assertEquals('/deptlist.php', substr($out['main_frame'], -13));
        $this->assertEquals('RF100', CoreLocal::get('departmentAmount'));
        $this->assertInternalType('array', CoreLocal::get('SpecialDeptMap'));
        CoreLocal::set('refundComment', '');
        CoreLocal::set('SecurityRefund', 21);
        $out = $d->parse('100DP10');
        $this->assertEquals('=RefundAdminLogin', substr($out['main_frame'], -17));
        CoreLocal::set('SecurityRefund', 0);
        $out = $d->parse('100DP10');
        $this->assertEquals('/refundComment.php', substr($out['main_frame'], -18));
        CoreLocal::set('refund', 0);
    }

    function testBalanceCheck()
    {
        $obj = new BalanceCheck();
        $this->assertEquals(true, $obj->check('BQ'));
        $out = $obj->parse('BQ');
        $this->assertNotEquals(0, strlen($out['output']));
    }

    function testComment()
    {
        $cm = new Comment();
        lttLib::clear();
        $out = $cm->parse('CMTEST');
        $dbc = Database::tDataConnect();
        $prep = $dbc->prepare('SELECT description FROM localtemptrans');
        $this->assertEquals('TEST', $dbc->getValue($prep));
        $out = $cm->parse('CM');
        $this->assertEquals('/bigComment.php', substr($out['main_frame'], -15));
        lttLib::clear();
    }

    function testClear()
    {
        $obj = new Clear();
        $out = $obj->parse('CL');
        $this->assertEquals(0, CoreLocal::get('msgrepeat'));
        $this->assertEquals('', CoreLocal::get('strRemembered'));
        $this->assertEquals(0, CoreLocal::get('SNR'));
        $this->assertEquals(0, CoreLocal::get('refund'));
        $this->assertEquals('/pos2.php', substr($out['main_frame'], -9));
    }

    function testCheckKey()
    {
        $obj = new CheckKey();
        $out = $obj->parse('100CQ');
        $this->assertEquals(100, CoreLocal::get('tenderTotal'));
        $this->assertEquals('/checklist.php', substr($out['main_frame'], -14));
    }

    function testCaseDiscMsgs()
    {
        $obj = new CaseDiscMsgs();
        $inputs = array('cdinvalid', 'cdStaffNA', 'cdSSINA');
        foreach ($inputs as $input) {
            $this->assertEquals(true, $obj->check($input));
            $out = $obj->parse($input);
            $this->assertNotEquals(0, strlen($out['output']));
        }
    }

    function testItemsEntry()
    {
        CoreLocal::set('mfcoupon',0);
        CoreLocal::set('itemPD',0);
        CoreLocal::set('multiple',0);
        CoreLocal::set('quantity',0);
        CoreLocal::set('refund',0);
        CoreLocal::set('toggletax',0);
        CoreLocal::set('togglefoodstamp',0);
        CoreLocal::set('toggleDiscountable',0);
        CoreLocal::set('nd',0);
        CoreLocal::set('memType', 0);

        // test regular price item
        lttLib::clear();
        $u = new UPC();
        $this->assertEquals(true, $u->check('666'));
        $json = $u->parse('666');
        $this->assertInternalType('array', $json);
        $record = lttLib::genericRecord();
        $record['upc'] = '0000000000666';
        $record['description'] = 'EXTRA BAG';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'NA';
        $record['department'] = 13;
        $record['tax'] = 1;
        $record['quantity'] = 1;
        $record['unitPrice'] = 0.05;
        $record['total'] = 0.05;
        $record['regPrice'] = 0.05;
        $record['ItemQtty'] = 1;
        lttLib::verifyRecord(1, $record, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['quantity'] *= -1;
        $record['ItemQtty'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        lttLib::verifyRecord(2, $record, $this);

        // test quantity multiplier
        lttLib::clear();
        CoreLocal::set('quantity', 2);
        CoreLocal::set('multiple', 1);
        $u = new UPC();
        $this->assertEquals(true, $u->check('666'));
        $json = $u->parse('666');
        $this->assertInternalType('array', $json);
        $record = lttLib::genericRecord();
        $record['upc'] = '0000000000666';
        $record['description'] = 'EXTRA BAG';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'NA';
        $record['department'] = 13;
        $record['tax'] = 1;
        $record['quantity'] = 2;
        $record['unitPrice'] = 0.05;
        $record['total'] = 0.10;
        $record['regPrice'] = 0.05;
        $record['ItemQtty'] = 2;
        lttLib::verifyRecord(1, $record, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['quantity'] *= -1;
        $record['ItemQtty'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        lttLib::verifyRecord(2, $record, $this);

        // test refund
        lttLib::clear();
        CoreLocal::set('quantity', 0);
        CoreLocal::set('multiple', 0);
        CoreLocal::set('refund', 1);
        CoreLocal::set('refundComment', 'TEST REFUND');
        $u = new UPC();
        $this->assertEquals(true, $u->check('666'));
        $json = $u->parse('666');
        $this->assertInternalType('array', $json);
        $record = lttLib::genericRecord();
        $record['upc'] = '0000000000666';
        $record['description'] = 'EXTRA BAG';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'NA';
        $record['trans_status'] = 'R';
        $record['department'] = 13;
        $record['tax'] = 1;
        $record['quantity'] = -1;
        $record['unitPrice'] = 0.05;
        $record['total'] = -0.05;
        $record['regPrice'] = 0.05;
        $record['ItemQtty'] = 1;
        lttLib::verifyRecord(1, $record, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['quantity'] *= -1;
        $record['ItemQtty'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        lttLib::verifyRecord(2, $record, $this);

        // test sale item
        lttLib::clear();
        CoreLocal::set('refund', 0);
        CoreLocal::set('refundComment', '');
        $u = new UPC();
        $this->assertEquals(true, $u->check('4627'));
        $json = $u->parse('4627');
        $this->assertInternalType('array', $json);
        $record = lttLib::genericRecord();
        $record['upc'] = '0000000004627';
        $record['description'] = 'PKALE';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'NA';
        $record['department'] = 513;
        $record['foodstamp'] = 1;
        $record['discounttype'] = 1;
        $record['discountable'] = 1;
        $record['quantity'] = 1;
        $record['cost'] = 1.30;
        $record['unitPrice'] = 1.99;
        $record['total'] = 1.99;
        $record['regPrice'] = 2.29;
        $record['discount'] = 0.30;
        $record['ItemQtty'] = 1;
        $record['mixMatch'] = '943';
        lttLib::verifyRecord(1, $record, $this);
        $drecord = lttLib::genericRecord();
        $drecord['description'] = '** YOU SAVED $0.30 **';
        $drecord['trans_type'] = 'I';
        $drecord['department'] = 513;
        $drecord['trans_status'] = 'D';
        $drecord['voided'] = 2;
        lttLib::verifyRecord(2, $drecord, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['quantity'] *= -1;
        $record['ItemQtty'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        $record['discount'] *= -1;
        lttLib::verifyRecord(3, $record, $this);

        // test member sale
        lttLib::clear();
        CoreLocal::set('isMember', 1);
        $u = new UPC();
        $this->assertEquals(true, $u->check('0003049488122'));
        $json = $u->parse('0003049488122');
        $this->assertInternalType('array', $json);
        $record = lttLib::genericRecord();
        $record['upc'] = '0003049488122';
        $record['description'] = 'MINERAL WATER';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'NA';
        $record['department'] = 188;
        $record['foodstamp'] = 1;
        $record['discounttype'] = 2;
        $record['discountable'] = 1;
        $record['quantity'] = 1;
        $record['cost'] = 2.06;
        $record['unitPrice'] = 2.49;
        $record['total'] = 2.49;
        $record['regPrice'] = 3.15;
        $record['memDiscount'] = 0.66;
        $record['ItemQtty'] = 1;
        lttLib::verifyRecord(1, $record, $this);
        $drecord = lttLib::genericRecord();
        $drecord['description'] = '** YOU SAVED $0.66 **';
        $drecord['trans_type'] = 'I';
        $drecord['department'] = 188;
        $drecord['trans_status'] = 'D';
        $drecord['voided'] = 2;
        lttLib::verifyRecord(2, $drecord, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['quantity'] *= -1;
        $record['ItemQtty'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        $record['memDiscount'] *= -1;
        lttLib::verifyRecord(3, $record, $this);

        // test member sale as non-member
        lttLib::clear();
        CoreLocal::set('isMember', 0);
        $u = new UPC();
        $this->assertEquals(true, $u->check('0003049488122'));
        $json = $u->parse('0003049488122');
        $this->assertInternalType('array', $json);
        $record = lttLib::genericRecord();
        $record['upc'] = '0003049488122';
        $record['description'] = 'MINERAL WATER';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'NA';
        $record['department'] = 188;
        $record['foodstamp'] = 1;
        $record['discounttype'] = 2;
        $record['discountable'] = 1;
        $record['quantity'] = 1;
        $record['cost'] = 2.06;
        $record['unitPrice'] = 3.15;
        $record['total'] = 3.15;
        $record['regPrice'] = 3.15;
        $record['memDiscount'] = 0.66;
        $record['ItemQtty'] = 1;
        lttLib::verifyRecord(1, $record, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['quantity'] *= -1;
        $record['ItemQtty'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        $record['memDiscount'] *= -1;
        lttLib::verifyRecord(2, $record, $this);
    }

    function testOpenRings()
    {
        lttLib::clear();
        $d = new DeptKey();
        $this->assertEquals(true, $d->check('100DP10'));
        $json = $d->parse('100DP10');
        $this->assertInternalType('array', $json);
        $record = lttLib::genericRecord();
        $record['upc'] = '1DP1';
        $record['description'] = 'BBAKING';
        $record['trans_type'] = 'D';
        $record['department'] = 1;
        $record['quantity'] = 1;
        $record['foodstamp'] = 1;
        $record['unitPrice'] = 1.00;
        $record['total'] = 1.00;
        $record['regPrice'] = 1.00;
        $record['ItemQtty'] = 1;
        lttLib::verifyRecord(1, $record, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['quantity'] *= -1;
        $record['ItemQtty'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        lttLib::verifyRecord(2, $record, $this);

        lttLib::clear();
        CoreLocal::set('refund', 1);
        CoreLocal::set('refundComment', 'TEST REFUND');
        $d = new DeptKey();
        $this->assertEquals(true, $d->check('100DP10'));
        $json = $d->parse('100DP10');
        $this->assertInternalType('array', $json);
        $record = lttLib::genericRecord();
        $record['upc'] = '1DP1';
        $record['description'] = 'BBAKING';
        $record['trans_type'] = 'D';
        $record['trans_status'] = 'R';
        $record['department'] = 1;
        $record['quantity'] = -1;
        $record['foodstamp'] = 1;
        $record['unitPrice'] = 1.00;
        $record['total'] = -1.00;
        $record['regPrice'] = 1.00;
        $record['ItemQtty'] = 1;
        lttLib::verifyRecord(1, $record, $this);
        CoreLocal::set('currentid', 1);
        $v = new Void();
        $this->assertEquals(true, $v->check('VD'));
        $json = $v->parse('VD');
        $this->assertInternalType('array', $json);
        $record['total'] *= -1;
        $record['quantity'] *= -1;
        $record['ItemQtty'] *= -1;
        $record['voided'] = 1;
        $record['trans_status'] = 'V';
        lttLib::verifyRecord(2, $record, $this);
    }

    /**
      Add a taxable, non-foodstampable item record,
      shift it, and verify
    */
    function testTaxFoodShift()
    {
        if (!class_exists('lttLib')) {
            include (dirname(__FILE__) . '/lttLib.php');
        }
        lttLib::clear();
        $upc = new UPC();
        $upc->parse('0000000000111');
        $record = lttLib::genericRecord();
        $record['upc'] = '0000000000111';
        $record['description'] = 'WYNDMERE 5-8 DRAM BOTTLE';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'NA';
        $record['trans_status'] = '';
        $record['department'] = 103;
        $record['quantity'] = 1;
        $record['ItemQtty'] = 1;
        $record['unitPrice'] = 1.65;
        $record['total'] = 1.65;
        $record['regPrice'] = 1.65;
        $record['tax'] = 1;
        $record['discountable'] = 1;
        $record['mixMatch'] = '499';
        lttLib::verifyRecord(1, $record, $this);
        $tfs = new TaxFoodShift();
        $tfs->parse('TFS');
        $record['tax'] = 0;
        $record['foodstamp'] = 1;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();
    }

    // mostly for coverage's sake
    function testBaseClasses()
    {
        $parser = new Parser();
        $pre = new PreParser();
        $post = new PostParser();

        $pre->check('');
        $pre->parse('');
        $pre->doc();

        $this->assertEquals(array(), $post->parse(array()));
        $this->assertInternalType('array', PostParser::getPostParseChain());

        $parser->check('');
        $parser->parse('');
    }
}