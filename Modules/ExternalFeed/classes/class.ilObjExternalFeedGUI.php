<?php

/* Copyright (c) 1998-2021 ILIAS open source, GPLv3, see LICENSE */

/**
 * Class ilObjExternalFeedGUI
 *
 * @author Alex Killing <alex.killing@gmx.de>
 *
 * @ilCtrl_Calls ilObjExternalFeedGUI: ilExternalFeedBlockGUI, ilPermissionGUI, ilExportGUI
 * @ilCtrl_IsCalledBy ilObjExternalFeedGUI: ilRepositoryGUI, ilAdministrationGUI
 */
class ilObjExternalFeedGUI extends ilObjectGUI
{
    /**
     * @var ilTabsGUI
     */
    protected $tabs;

    /**
     * @var ilHelpGUI
     */
    protected $help;

    /**
     * @var
     */
    protected $feed_block;

    /**
    * Constructor
    * @access public
    */
    public function __construct($a_data, $a_id, $a_call_by_reference, $a_prepare_output = true)
    {
        global $DIC;

        $this->tpl = $DIC["tpl"];
        $this->tabs = $DIC->tabs();
        $this->ctrl = $DIC->ctrl();
        $this->tree = $DIC->repositoryTree();
        $this->access = $DIC->access();
        $this->lng = $DIC->language();
        $this->help = $DIC["ilHelp"];
        $this->type = "feed";
        parent::__construct($a_data, $a_id, $a_call_by_reference, $a_prepare_output);
    }
    
    
    public function executeCommand()
    {
        $tpl = $this->tpl;
        $ilTabs = $this->tabs;

        $next_class = $this->ctrl->getNextClass($this);
        
        switch ($next_class) {
            case 'ilpermissiongui':
                $this->prepareOutput();
                $ilTabs->activateTab("id_permissions");
                $perm_gui = new ilPermissionGUI($this);
                $ret = $this->ctrl->forwardCommand($perm_gui);
                break;
                
            case "ilexternalfeedblockgui":
                $this->prepareOutput();
                $ilTabs->activateTab("id_settings");
                $fb_gui = new ilExternalFeedBlockGUI();
                $fb_gui->setGuiObject($this);
                if (is_object($this->object)) {
                    $fb_gui->setRefId($this->object->getRefId());
                }
                $ret = $this->ctrl->forwardCommand($fb_gui);
                $tpl->setContent($ret);
                break;

            case "ilexportgui":
                $this->prepareOutput();
                $ilTabs->activateTab("export");
                $exp_gui = new ilExportGUI($this);
                $exp_gui->addFormat("xml");
                $ret = $this->ctrl->forwardCommand($exp_gui);
                break;

            default:
                $cmd = $this->ctrl->getCmd("view");
                if ($cmd != "create") {
                    $this->prepareOutput();
                }
                $cmd .= "Object";
                $this->$cmd();
                break;
        }
        return true;
    }

    public function createObject()
    {
        $ilCtrl = $this->ctrl;
        $ilCtrl->setCmdClass("ilexternalfeedblockgui");
        $ilCtrl->setCmd("create");
        return $this->executeCommand();
    }
    
    /**
    * save object
    * @access	public
    */
    public function save($a_feed_block)
    {
        // create and insert forum in objecttree
        $_REQUEST["new_type"] = "feed";
        $_POST["title"] = $a_feed_block->getTitle();
        $_POST["desc"] = $a_feed_block->getFeedUrl();
        $this->feed_block = $a_feed_block;
        parent::saveObject();
    }

    public function afterSave(ilObject $a_new_object)
    {
        $a_feed_block = $this->feed_block;
        if ($a_feed_block != null) {
            $a_feed_block->setContextObjId($a_new_object->getId());
            $a_feed_block->setContextObjType("feed");
        }
    }
    
    /**
    * Exit save.
    *
    */
    public function exitSave()
    {
        $ilCtrl = $this->ctrl;

        // always send a message
        ilUtil::sendSuccess($this->lng->txt("object_added"), true);
        $this->ctrl->returnToParent($this);
    }
    
    /**
    * update object
    * @access	public
    */
    public function update($a_feed_block)
    {
        $_POST["title"] = $a_feed_block->getTitle();
        $_POST["desc"] = $a_feed_block->getFeedUrl();
        parent::updateObject();
    }

    /**
    * Cancel update.
    *
    */
    public function cancelUpdate()
    {
        $tree = $this->tree;

        $par = $tree->getParentId($_GET["ref_id"]);
        $_GET["ref_id"] = $par;
        $this->redirectToRefId($par);
    }

    /**
    * After update
    *
    */
    public function afterUpdate()
    {
        $tree = $this->tree;

        $par = $tree->getParentId($_GET["ref_id"]);
        $_GET["ref_id"] = $par;
        $this->redirectToRefId($par);
    }

    /**
    * get tabs
    * @access	public
    */
    public function setTabs()
    {
        $ilAccess = $this->access;
        $ilCtrl = $this->ctrl;
        $ilTabs = $this->tabs;
        $lng = $this->lng;
        $ilHelp = $this->help;
        
        if (in_array($ilCtrl->getCmd(), array("create", "saveFeedBlock"))) {
            return;
        }
        $ilHelp->setScreenIdComponent("feed");
        
        $ilCtrl->setParameterByClass(
            "ilexternalfeedblockgui",
            "external_feed_block_id",
            $_GET["external_feed_block_id"]
        );
        $ilCtrl->saveParameter($this, "external_feed_block_id");

        if ($ilAccess->checkAccess('write', '', $this->object->getRefId())) {
            $ilTabs->addTab(
                "id_settings",
                $lng->txt("settings"),
                $this->ctrl->getLinkTargetByClass("ilexternalfeedblockgui", "editFeedBlock")
            );
        }

        // export
        if ($ilAccess->checkAccess("write", "", $this->object->getRefId()) && DEVMODE == 1) {
            $ilTabs->addTab(
                "export",
                $lng->txt("export"),
                $this->ctrl->getLinkTargetByClass("ilexportgui", "")
            );
        }


        if ($ilAccess->checkAccess('edit_permission', '', $this->object->getRefId())) {
            $ilTabs->addTab(
                "id_permissions",
                $lng->txt("perm_settings"),
                $this->ctrl->getLinkTargetByClass("ilpermissiongui", "perm")
            );
        }
    }
    
    public static function _goto($a_target)
    {
        global $DIC;

        $tree = $DIC->repositoryTree();
        
        $id = explode("_", $a_target);
        $ref_id = $id[0];
        
        // is sideblock: so show parent instead
        $container_id = $tree->getParentId($ref_id);

        // #14870
        ilUtil::redirect(ilLink::_getLink($container_id));
    }
}
