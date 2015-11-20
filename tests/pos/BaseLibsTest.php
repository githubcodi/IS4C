<?php
/**
 * @backupGlobals disabled
 */
class BaseLibsTest extends PHPUnit_Framework_TestCase
{

    public function testMiscLib()
    {
        chdir(dirname(__FILE__).'/../../pos/is4c-nf/gui-modules/');
        $rel = MiscLib::baseURL();
        $this->assertEquals('../',$rel);

        $this->assertEquals(1, MiscLib::nullwrap(1));
        $this->assertEquals(1.5, MiscLib::nullwrap(1.5));
        $this->assertEquals('test', MiscLib::nullwrap('test'));
        $this->assertEquals(0, MiscLib::nullwrap(False));

        $this->assertEquals(1, MiscLib::truncate2(1));
        $this->assertEquals(1.99, MiscLib::truncate2(1.99));
        $this->assertEquals(1.99, MiscLib::truncate2("1.99"));
        $this->assertEquals(1.35, MiscLib::truncate2("1.345"));

        $hostCheck = MiscLib::pingport(CoreLocal::get('localhost'),CoreLocal::get('DBMS'));
        $this->assertInternalType('integer', $hostCheck);

        $hostCheck = MiscLib::win32();
        $this->assertInternalType('integer', $hostCheck);

        $scale = MiscLib::scaleObject();
        if ($scale !== 0){
            $this->assertInstanceOf('ScaleDriverWrapper', $scale);
        }
    }

    public function testDatabase()
    {
        $db = Database::tDataConnect();
        $this->assertInstanceOf('\\COREPOS\\pos\\lib\\SQLManager', $db);
        $this->assertEquals(CoreLocal::get('tDatabase'), $db->default_db);
        $db = Database::pDataConnect();
        $this->assertInstanceOf('\\COREPOS\\pos\\lib\\SQLManager', $db);
        $this->assertEquals(CoreLocal::get('pDatabase'), $db->default_db);

        $this->assertEquals(1, Database::gettransno(-1)); // not a real emp_no

        $db = Database::tDataConnect();
        $matches = Database::localMatchingColumns($db, 'localtrans', 'localtemptrans');
        $this->assertInternalType('string', $matches);
        $this->assertRegExp('/(.+)/',$matches);

        $globals = array(
            'CashierNo' => 9999,
            'cashier' => 'TRAINING',
            'LoggedIn' => 0,
            'TransNo' => 1,
            'TTLFlag' => 0,
            'FntlFlag' => 0,
            'TaxExempt' => 0
        );
        Database::setglobalvalues($globals);
        $this->assertEquals(9999, CoreLocal::get('CashierNo'));
        $this->assertEquals('TRAINING', CoreLocal::get('cashier'));
        $this->assertEquals(0, CoreLocal::get('LoggedIn'));
        $this->assertEquals(1, CoreLocal::get('transno'));
        $this->assertEquals(0, CoreLocal::get('ttlflag'));
        $this->assertEquals(0, CoreLocal::get('fntlflag'));
        $this->assertEquals(0, CoreLocal::get('TaxExempt'));
        Database::loadglobalvalues(); // reload session from db. shouldn't change.
        $this->assertEquals(9999, CoreLocal::get('CashierNo'));
        $this->assertEquals('TRAINING', CoreLocal::get('cashier'));
        $this->assertEquals(0, CoreLocal::get('LoggedIn'));
        $this->assertEquals(1, CoreLocal::get('transno'));
        $this->assertEquals(0, CoreLocal::get('ttlflag'));
        $this->assertEquals(0, CoreLocal::get('fntlflag'));
        $this->assertEquals(0, CoreLocal::get('TaxExempt'));
        Database::setglobalvalue('TTLFlag',1);
        Database::loadglobalvalues();
        $this->assertEquals(1, CoreLocal::get('ttlflag'));
        Database::setglobalflags(0);
        Database::loadglobalvalues();
        $this->assertEquals(0, CoreLocal::get('ttlflag'));
        $this->assertEquals(0, CoreLocal::get('fntlflag'));

        if (!class_exists('lttLib')) {
            include(dirname(__FILE__) . '/lttLib.php');
        }
        $db = Database::mDataConnect();
        $db->query('truncate table suspended');
        lttLib::clear();
        $record = lttLib::genericRecord(); 
        $record['upc'] = '0000000000000';
        $record['description'] = uniqid('TEST-');
        TransRecord::addRecord($record);
        SuspendLib::suspendorder();
        $this->assertEquals(1, SuspendLib::checksuspended());

        $query = "
            SELECT *
            FROM suspended
            WHERE upc='{$record['upc']}'
                AND description='{$record['description']}'
                AND datetime >= " . $db->curdate();
        $result = $db->query($query);
        $this->assertNotEquals(false, $result, 'Could not query suspended record');
        $this->assertEquals(1, $db->num_rows($result), 'Could not find suspended record');
        $row = $db->fetch_row($result);
        $this->assertInternalType('array', $row, 'Invalid suspended record');
        foreach ($record as $column => $value) {
            $this->assertArrayHasKey($column, $row, 'Suspended missing ' . $column);
            $this->assertEquals($value, $row[$column], 'Suspended mismatch on column ' . $column);
        }
    }

    public function testAuthenticate()
    {
        CoreLocal::set('scaleDriver',''); // don't interact w/ scale

        Database::setglobalvalue('LoggedIn',1);
        Database::setglobalvalue('CashierNo',1);
        $fail = Authenticate::checkPassword('9999');
        $this->assertEquals(False, $fail);

        Database::setglobalvalue('CashierNo',9999);
        $pass = Authenticate::checkPassword('9999');
        $this->assertEquals(True, $pass);

        Database::setglobalvalue('LoggedIn',0);
        Database::setglobalvalue('CashierNo',1);
        $pass = Authenticate::checkPassword('9999');
        $this->assertEquals(True, $pass);

        Database::setglobalvalue('LoggedIn',0);
        Database::setglobalvalue('CashierNo',1);
        $pass = Authenticate::checkPassword('56');
        $this->assertEquals(True, $pass);

        Database::setglobalvalue('LoggedIn',0);
        Database::setglobalvalue('CashierNo',1);
        $fail = Authenticate::checkPassword('invalid password');
        $this->assertEquals(false, $fail);

        $this->assertEquals(false, Authenticate::checkPermission('56', 50));
        $this->assertEquals(false, Authenticate::checkPermission('56', 21));
        $this->assertEquals(true, Authenticate::checkPermission('56', 20));
        $this->assertEquals(true, Authenticate::checkPermission('56', 10));

        $this->assertEquals(false, Authenticate::getEmployeeByPassword('asdf'));
        $this->assertInternalType('array', Authenticate::getEmployeeByPassword('56'));
        $this->assertEquals(false, Authenticate::getEmployeeByNumber(75));
        $this->assertInternalType('array', Authenticate::getEmployeeByNumber(56));

        $this->assertEquals(0, Authenticate::getPermission(55));
        $this->assertEquals(20, Authenticate::getPermission(56));
    }

    public function testAutoLoader()
    {
        // get codepath where session var is not array
        CoreLocal::set('ClassLookup', false);
        AutoLoader::loadClass('LocalStorage');
        $this->assertEquals(true, class_exists('LocalStorage', false));

        AutoLoader::loadMap();
        $class_map = CoreLocal::get('ClassLookup');
        $this->assertInternalType('array', $class_map);
        $this->assertNotEmpty($class_map);
        
        /**
          Verify base classes and required libraries
          were properly discovered
        */
        $required_classes = array(
            'AutoLoader',
            'Authenticate',
            'PreParser',
            'Parser',
            'BasicCorePage',
            'TenderModule',
            'DisplayLib',
            'ReceiptLib',
            'Database',
            'Kicker',
            'SpecialUPC',
            'SpecialDept',
            'DiscountType',
            'PriceMethod',
            'LocalStorage',
            'FooterBox',
            'Plugin',
            'PrintHandler',
        );

        foreach($required_classes as $class){
            $this->assertArrayHasKey($class, $class_map);
            $this->assertFileExists($class_map[$class]);
        }

        $mods = AutoLoader::listModules('Parser');
        $this->assertInternalType('array',$mods);
        $this->assertNotEmpty($mods);
        foreach($mods as $m){
            $obj = new $m();
            $this->assertInstanceOf('Parser',$obj);
        }

        $listable = array(
            'DiscountType',
            'FooterBox',
            'Kicker',
            'Parser',
            'PreParser',
            'PriceMethod',
            'SpecialUPC',
            'SpecialDept',
            'TenderModule',
            'TenderReport',
            'DefaultReceiptDataFetch',
            'DefaultReceiptFilter',
            'DefaultReceiptSort',
            'DefaultReceiptTag',
            'DefaultReceiptSavings',
            'ReceiptMessage',
            'CustomerReceiptMessage',
            'ProductSearch',
            'DiscountModule',
            'PrintHandler',
            'TotalAction',
            'VariableWeightReWrite',
            'ItemNotFound',
        );
        foreach ($listable as $base_class) {
            $mods = AutoLoader::listModules($base_class);
            $this->assertInternalType('array',$mods);
        }
    }

    public function testBitmap()
    {
        /**
          Using PrintHandler::RenderBitmapFromFile
          will call all the methods of the Bitmap class
          that actually get used
        */

        $ph = new PrintHandler();
        $file = dirname(__FILE__).'/../../pos/is4c-nf/graphics/WfcLogo2014.bmp';

        $this->assertFileExists($file);
        $bitmap = $ph->RenderBitmapFromFile($file);
        $this->assertInternalType('string',$bitmap);
        $this->assertNotEmpty($bitmap);
    }

    public function testCoreState()
    {
        // normal session init attempts to recover state
        // transaction info - e.g., after a browser crash
        // or reboot. Clear the table so that doesn't
        // happen
        $db = Database::tDataConnect();
        $db->query('TRUNCATE TABLE localtemptrans');

        /**
          This will trigger any syntax or run-time errors
          Testing all the invidual values of session
          might be worthwhile is anyone wants to write
          all those tests out. They're mostly static values
          so the test would only catch changes to the
          defaults.
        */
        CoreState::initiate_session();

        $str = CoreState::getCustomerPref('asdf');
        $this->assertInternalType('string',$str);
        $this->assertEquals('',$str);

        // non-numeric age converts to zero
        CoreState::cashierLogin(false, 'z');
        $this->assertEquals(0, CoreLocal::get('cashierAge'));
    }

    public function testDisplayLib()
    {
        $footer = DisplayLib::printfooter();
        $this->assertInternalType('string',$footer);
        $this->assertNotEmpty($footer);

        $pmsg = DisplayLib::plainmsg('test message');
        $this->assertInternalType('string',$pmsg);
        $this->assertNotEmpty($pmsg);
        $this->assertContains('test message',$pmsg);

        $mbox = DisplayLib::msgbox('test msgbox','',True);
        $this->assertInternalType('string',$mbox);
        $this->assertNotEmpty($mbox);
        $this->assertContains('test msgbox',$mbox);

        $xbox = DisplayLib::xboxMsg('test xboxMsg');
        $this->assertInternalType('string',$xbox);
        $this->assertNotEmpty($xbox);
        $this->assertContains('test xboxMsg',$xbox);

        $bmsg = DisplayLib::boxMsg('test boxMsg','',True);
        $this->assertInternalType('string',$bmsg);
        $this->assertNotEmpty($bmsg);
        $this->assertContains('test boxMsg',$bmsg);

        $unk = DisplayLib::inputUnknown();
        $this->assertInternalType('string',$unk);
        $this->assertNotEmpty($unk);

        $headerb = DisplayLib::printheaderb();
        $this->assertInternalType('string',$headerb);
        $this->assertNotEmpty($headerb);

        $item = DisplayLib::printItem('name','weight','1.99','T',1);
        $this->assertInternalType('string',$item);
        $this->assertNotEmpty($item);

        $itemC = DisplayLib::printItemColor('004080','name','weight','1.99','T',2);
        $this->assertInternalType('string',$itemC);
        $this->assertNotEmpty($itemC);

        $itemH = DisplayLib::printItemColorHilite('004080','name','weight','1.99','T');
        $this->assertInternalType('string',$itemH);
        $this->assertNotEmpty($itemH);

        CoreLocal::set('weight',0);
        CoreLocal::set('scale',0);
        CoreLocal::set('SNR',0);

        $basic = DisplayLib::scaledisplaymsg();
        $this->assertInternalType('string',$basic);
        $this->assertEquals('0.00 lb',$basic);

        $scale_in_out = array(
            'S11000' => '0.00 lb',
            'S11001' => '0.01 lb',
            'S11' => '_ _ _ _',
            'S141' => '_ _ _ _',
            'S143' => '0.00 lb',
            'S145' => 'err -0',
            'S142' => 'error',
            'ASDF' => '? ? ? ?',
            'S144000' => '0.00 lb',
            'S144002' => '0.02 lb'
        );

        foreach($scale_in_out as $input => $output){
            $test = DisplayLib::scaledisplaymsg($input);
            $this->assertInternalType('array',$test);
            $this->assertArrayHasKey('display',$test);
            $this->assertEquals($output, $test['display']);
        }

        $this->assertEquals(1, CoreLocal::get('scale'));
        $this->assertEquals(0.02, CoreLocal::get('weight'));

        CoreLocal::set('SNR','4011');
        $both = DisplayLib::scaledisplaymsg('S11050');
        $this->assertInternalType('array',$both);
        $this->assertArrayHasKey('display',$both);
        $this->assertArrayHasKey('upc',$both);
        $this->assertEquals('0.50 lb',$both['display']);
        $this->assertEquals('4011',$both['upc']);

        $list = DisplayLib::listItems(0,0);
        $this->assertInternalType('string',$list);

        $rf = DisplayLib::printReceiptFooter();
        $this->assertInternalType('string',$rf);

        $draw = DisplayLib::drawItems(0,11,0);
        $this->assertInternalType('string',$draw);

        $lp = DisplayLib::lastpage();
        $this->assertInternalType('string',$lp);

        $this->assertEquals($lp,$list);
    }

    public function testJsonLib()
    {
        $test = array(
            0 => array(1, 2, 3),
            1 => 'test string',
            2 => 5,
            3 => '0006',
            4 => 9.7,
            5 => True,
            6 => "bad\\char\tacters"
        );

        $json = JsonLib::array_to_json($test);
        $good = "[[1,2,3],\"test string\",5,6,9.7,true,\"bad\\\\char\\tacters\"]";
        $this->assertInternalType('string',$json);
        $this->assertEquals($good,$json);
    }

    public function testUdpComm()
    {
        UdpComm::udpSend('most likely no one is listening...');
    }

    public function testTransRecord()
    {
        if (!class_exists('lttLib')) include ('lttLib.php');
        lttLib::clear();

        CoreLocal::set('infoRecordQueue','not-array');
        TransRecord::addQueued('1234567890123','UNIT TEST',1,'UT',1.99);
        $queue = CoreLocal::get('infoRecordQueue');
        $this->assertInternalType('array',$queue);
        $this->assertEquals(1,count($queue));
        $this->assertArrayHasKey(0,$queue);
        $this->assertInternalType('array',$queue[0]);
        $this->assertArrayHasKey('upc',$queue[0]);
        $this->assertEquals('1234567890123',$queue[0]['upc']);
        $this->assertArrayHasKey('description',$queue[0]);
        $this->assertEquals('UNIT TEST',$queue[0]['description']);
        $this->assertArrayHasKey('numflag',$queue[0]);
        $this->assertEquals(1,$queue[0]['numflag']);
        $this->assertArrayHasKey('charflag',$queue[0]);
        $this->assertEquals('UT',$queue[0]['charflag']);
        $this->assertArrayHasKey('regPrice',$queue[0]);
        $this->assertEquals(1.99,$queue[0]['regPrice']);

        TransRecord::emptyQueue();
        $queue = CoreLocal::get('infoRecordQueue');
        $this->assertInternalType('array',$queue);
        $this->assertEquals(0,count($queue));
        $record = lttLib::genericRecord();
        $record['upc'] = '1234567890123';
        $record['description'] = 'UNIT TEST';
        $record['numflag'] = 1;
        $record['charflag'] = 'UT';
        $record['regPrice'] = 1.99;
        $record['trans_type'] = 'C';
        $record['trans_status'] = 'D';
        lttLib::verifyRecord(1, $record, $this);

        CoreLocal::set('infoRecordQueue','not-array');
        TransRecord::emptyQueue();
        $this->assertInternalType('array', CoreLocal::get('infoRecordQueue'));

        lttLib::clear();

        CoreLocal::set('taxTotal',1.23);
        TransRecord::addtax();
        $record = lttLib::genericRecord();
        $record['upc'] = 'TAX';
        $record['description'] = 'Tax';
        $record['trans_type'] = 'A';
        $record['total'] = 1.23;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addtender('UT TENDER','UT',2.34);
        $record = lttLib::genericRecord();
        $record['description'] = 'UT TENDER';
        $record['trans_type'] = 'T';
        $record['trans_subtype'] = 'UT';
        $record['total'] = 2.34;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addFlaggedTender('UT TENDER','UT',2.34,7,'TF');
        $record = lttLib::genericRecord();
        $record['description'] = 'UT TENDER';
        $record['trans_type'] = 'T';
        $record['trans_subtype'] = 'UT';
        $record['total'] = 2.34;
        $record['numflag'] = 7;
        $record['charflag'] = 'TF';
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addcomment('UNIT TEST COMMENT');
        $record = lttLib::genericRecord();
        $record['description'] = 'UNIT TEST COMMENT';
        $record['trans_type'] = 'C';
        $record['trans_subtype'] = 'CM';
        $record['trans_status'] = 'D';
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        // trim comment to 30 characters
        TransRecord::addcomment('1234567890123456789012345678901');
        $record = lttLib::genericRecord();
        $record['description'] = '123456789012345678901234567890';
        $record['trans_type'] = 'C';
        $record['trans_subtype'] = 'CM';
        $record['trans_status'] = 'D';
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addchange(3.14,'UT','MoneyBack');
        $record = lttLib::genericRecord();
        $record['description'] = 'MoneyBack';
        $record['trans_type'] = 'T';
        $record['trans_subtype'] = 'UT';
        $record['total'] = 3.14;
        $record['voided'] = 8;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addchange(3.14,'','');
        $record = lttLib::genericRecord();
        $record['description'] = 'Change';
        $record['trans_type'] = 'T';
        $record['trans_subtype'] = 'CA';
        $record['total'] = 3.14;
        $record['voided'] = 8;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addfsones(3);
        $record = lttLib::genericRecord();
        $record['description'] = 'FS Change';
        $record['trans_type'] = 'T';
        $record['trans_subtype'] = 'FS';
        $record['total'] = 3;
        $record['voided'] = 8;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        CoreLocal::set('itemPD', 5);
        TransRecord::adddiscount(5.45,25);
        CoreLocal::set('itemPD', 0);
        $record = lttLib::genericRecord();
        $record['description'] = '** YOU SAVED $5.45 (5%) **';
        $record['trans_type'] = 'I';
        $record['trans_status'] = 'D';
        $record['department'] = 25;
        $record['voided'] = 2;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addfsTaxExempt();
        $record = lttLib::genericRecord();
        $record['upc'] = 'FS Tax Exempt';
        $record['description'] = ' Fs Tax Exempt ';
        $record['trans_type'] = 'C';
        $record['trans_status'] = 'D';
        $record['voided'] = 17;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::discountnotify(5);
        $record = lttLib::genericRecord();
        $record['description'] = '** 5% Discount Applied **';
        $record['trans_status'] = 'D';
        $record['voided'] = 4;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addTaxExempt();
        $record = lttLib::genericRecord();
        $record['description'] = '** Order is Tax Exempt **';
        $record['trans_status'] = 'D';
        $record['voided'] = 10;
        $record['tax'] = 9;
        lttLib::verifyRecord(1, $record, $this);
        $this->assertEquals(1, CoreLocal::get('TaxExempt'));

        lttLib::clear();

        TransRecord::reverseTaxExempt();
        $record = lttLib::genericRecord();
        $record['description'] = '** Tax Exemption Reversed **';
        $record['trans_status'] = 'D';
        $record['voided'] = 10;
        $record['tax'] = 9;
        lttLib::verifyRecord(1, $record, $this);
        $this->assertEquals(0, CoreLocal::get('TaxExempt'));

        lttLib::clear();

        CoreLocal::set('casediscount',7);
        TransRecord::addcdnotify();
        $record = lttLib::genericRecord();
        $record['description'] = '** 7% Case Discount Applied';
        $record['trans_status'] = 'D';
        $record['voided'] = 6;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addCoupon('0051234512345',123,-1.23,1);
        $record = lttLib::genericRecord();
        $record['upc'] = '0051234512345';
        $record['description'] = ' * Manufacturers Coupon';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'CP';
        $record['trans_status'] = 'C';
        $record['department'] = 123;
        $record['unitPrice'] = -1.23;
        $record['total'] = -1.23;
        $record['regPrice'] = -1.23;
        $record['foodstamp'] = 1;
        $record['quantity'] = 1;
        $record['ItemQtty'] = 1;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addhousecoupon('0049999912345',122,-1.22);
        $record = lttLib::genericRecord();
        $record['upc'] = '0049999912345';
        $record['description'] = ' * Store Coupon';
        $record['trans_type'] = 'I';
        $record['trans_subtype'] = 'IC';
        $record['trans_status'] = 'C';
        $record['department'] = 122;
        $record['unitPrice'] = -1.22;
        $record['total'] = -1.22;
        $record['regPrice'] = -1.22;
        $record['quantity'] = 1;
        $record['ItemQtty'] = 1;
        $record['discountable'] = 1;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::additemdiscount(345,3.45);
        $record = lttLib::genericRecord();
        $record['upc'] = 'ITEMDISCOUNT';
        $record['description'] = ' * Item Discount';
        $record['trans_type'] = 'I';
        $record['department'] = 345;
        $record['unitPrice'] = -3.45;
        $record['total'] = -3.45;
        $record['regPrice'] = -3.45;
        $record['quantity'] = 1;
        $record['ItemQtty'] = 1;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addtare(5);
        $record = lttLib::genericRecord();
        $record['description'] = '** Tare Weight 0.05 **';
        $record['trans_status'] = 'D';
        $record['voided'] = 6;
        lttLib::verifyRecord(1, $record, $this);
        $this->assertEquals(0.05, CoreLocal::get('tare'));

        lttLib::clear();

        CoreLocal::set('transDiscount',3.24);
        TransRecord::addTransDiscount();
        $record = lttLib::genericRecord();
        $record['upc'] = 'DISCOUNT';
        $record['description'] = 'Discount';
        $record['trans_type'] = 'S';
        $record['quantity'] = 1;
        $record['ItemQtty'] = 1;
        $record['unitPrice'] = -3.24;
        $record['total'] = -3.24;
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        TransRecord::addCashDrop('90.78');
        $record = lttLib::genericRecord();
        $record['upc'] = 'DROP';
        $record['description'] = 'Cash Drop';
        $record['trans_type'] = 'I';
        $record['trans_status'] = 'X';
        $record['quantity'] = 1;
        $record['ItemQtty'] = 1;
        $record['unitPrice'] = -90.78;
        $record['total'] = -90.78;
        $record['charflag'] = 'CD';
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();

        $record = lttLib::genericRecord();
        $record['upc'] = 'UNITTEST';
        $record['description'] = 'Unit Test';
        $record['department'] = 5;
        $record['numflag'] = 4;
        $record['charflag'] = 'UT';
        $record['amount1'] = 1.23;
        $record['total'] = 1.23;
        $record['amount2'] = 1.24;
        $record['regPrice'] = 1.24;
        TransRecord::add_log_record($record);
        unset($record['amount1']); // not real column
        unset($record['amount2']); // not real column
        $record['trans_type'] = 'L';
        $record['trans_subtype'] = 'OG';
        $record['trans_status'] = 'D';
        lttLib::verifyRecord(1, $record, $this);

        lttLib::clear();
    }

    public function testPrehLib()
    {
        if (!class_exists('lttLib')) include ('lttLib.php');
        lttLib::clear();
        
        TransRecord::addcomment('peek');
        $peek = PrehLib::peekItem();
        $this->assertEquals('peek',$peek);

        lttLib::clear();

        CoreLocal::set('percentDiscount',5);
        CoreLocal::set('transDiscount',0.51);
        CoreLocal::set('taxTotal',1.45);
        CoreLocal::set('fsTaxExempt',1.11);
        CoreLocal::set('amtdue',9.55);
        // should add four records
        PrehLib::finalttl();

        // verify discount record
        $record = lttLib::genericRecord();
        $record['description'] = 'Discount';
        $record['trans_type'] = 'C';
        $record['trans_status'] = 'D';
        $record['unitPrice'] = -0.51;
        $record['voided'] = 5;
        lttLib::verifyRecord(1, $record, $this);

        // verify subtotal record
        $record = lttLib::genericRecord();
        $record['upc'] = 'Subtotal';
        $record['description'] = 'Subtotal';
        $record['trans_type'] = 'C';
        $record['trans_status'] = 'D';
        $record['unitPrice'] = 0.34;
        $record['voided'] = 11;
        lttLib::verifyRecord(2, $record, $this);

        // verify fs tax exempt record
        $record = lttLib::genericRecord();
        $record['upc'] = 'Tax';
        $record['description'] = 'FS Taxable';
        $record['trans_type'] = 'C';
        $record['trans_status'] = 'D';
        $record['unitPrice'] = 1.11;
        $record['voided'] = 7;
        lttLib::verifyRecord(3, $record, $this);

        // verify total record
        $record = lttLib::genericRecord();
        $record['upc'] = 'Total';
        $record['description'] = 'Total';
        $record['trans_type'] = 'C';
        $record['trans_status'] = 'D';
        $record['unitPrice'] = 9.55;
        $record['voided'] = 11;
        lttLib::verifyRecord(4, $record, $this);

        lttLib::clear();

        CoreLocal::set('cashierAge', 17);
        CoreLocal::set('cashierAgeOverride', 0);
        list($age_required, $json) = PrehLib::ageCheck(21, array());
        $this->assertEquals(true, $age_required);
        $this->assertInternalType('array', $json);
        CoreLocal::set('cashierAgeOverride', 1);
        list($age_required, $json) = PrehLib::ageCheck(21, array());
        $this->assertEquals(true, $age_required);
        $this->assertInternalType('array', $json);
        CoreLocal::set('memAge', date('Ymd', strtotime('21 years ago')));
        list($age_required, $json) = PrehLib::ageCheck(21, array());
        $this->assertEquals(false, $age_required);
        $this->assertInternalType('array', $json);
        CoreLocal::set('memAge', date('Ymd', strtotime('20 years ago')));
        list($age_required, $json) = PrehLib::ageCheck(21, array());
        $this->assertEquals(true, $age_required);
        $this->assertInternalType('array', $json);
    }

    public function testDiscountModules()
    {
        $ten = new DiscountModule(10, 'ten');
        $fifteen = new DiscountModule(15, 'fifteen');

        // verify stacking discounts
        CoreLocal::set('percentDiscount', 0);
        CoreLocal::set('NonStackingDiscounts', 0);
        DiscountModule::updateDiscount($ten, false);
        $this->assertEquals(10, CoreLocal::get('percentDiscount'));
        DiscountModule::updateDiscount($fifteen, false);
        $this->assertEquals(25, CoreLocal::get('percentDiscount'));

        DiscountModule::transReset();

        // verify non-stacking discounts
        CoreLocal::set('percentDiscount', 0);
        CoreLocal::set('NonStackingDiscounts', 1);
        DiscountModule::updateDiscount($ten, false);
        $this->assertEquals(10, CoreLocal::get('percentDiscount'));
        DiscountModule::updateDiscount($fifteen, false);
        $this->assertEquals(15, CoreLocal::get('percentDiscount'));

        DiscountModule::transReset();

        // verify best non-stacking discount wins
        CoreLocal::set('percentDiscount', 0);
        DiscountModule::updateDiscount($fifteen, false);
        $this->assertEquals(15, CoreLocal::get('percentDiscount'));
        DiscountModule::updateDiscount($ten, false);
        $this->assertEquals(15, CoreLocal::get('percentDiscount'));

        DiscountModule::transReset();

        // verify same-name discounts overwrite
        $one = new DiscountModule(1, 'custdata');
        $two = new DiscountModule(2, 'custdata');
        CoreLocal::set('percentDiscount', 0);
        CoreLocal::set('NonStackingDiscounts', 0);
        DiscountModule::updateDiscount($one, false);
        $this->assertEquals(1, CoreLocal::get('percentDiscount'));
        DiscountModule::updateDiscount($two, false);
        $this->assertEquals(2, CoreLocal::get('percentDiscount'));

        DiscountModule::transReset();

        // same-name should overwrite in the order called
        CoreLocal::set('percentDiscount', 0);
        DiscountModule::updateDiscount($two, false);
        $this->assertEquals(2, CoreLocal::get('percentDiscount'));
        DiscountModule::updateDiscount($one, false);
        $this->assertEquals(1, CoreLocal::get('percentDiscount'));
    }

}

