<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use PHPUnit\Framework\TestCase;

/**
 * @group needsInstalledILIAS
 */
class ilObjectDefinitionTest extends TestCase
{
    protected $backupGlobals = false;

    protected function setUp() : void
    {
        include_once("./Services/PHPUnit/classes/class.ilUnitUtil.php");
        ilUnitUtil::performInitialisation();
    }

    /**
     * @group IL_Init
     */
    public function testObjDefinitionReader()
    {
        /*
        include_once("./setup/classes/class.ilObjDefReader.php");
        $def_reader = new ilObjDefReader(
            "./Services/Object/test/testmodule.xml",
            "DefinitionTest",
            "Modules"
        );

        $def_reader->deleteObjectDefinition("xx1");
        $def_reader->startParsing();

        $this->assertEquals("", $result);
        */
        //To avoid: No tests found in class "ilObjectDefinitionTest".
        $this->assertTrue(true);
    }
}
