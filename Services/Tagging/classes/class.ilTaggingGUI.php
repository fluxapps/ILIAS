<?php

/* Copyright (c) 1998-2021 ILIAS open source, GPLv3, see LICENSE */

/**
 * Class ilTaggingGUI. User interface class for tagging engine.
 *
 * @author Alex Killing <alex.killing@gmx.de>
 */
class ilTaggingGUI
{
    /**
     * @var ilCtrl
     */
    protected $ctrl;

    /**
     * @var ilObjUser
     */
    protected $user;

    /**
     * @var ilLanguage
     */
    protected $lng;

    /**
     * @var \ILIAS\DI\UIServices
     */
    protected $ui;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->user = $DIC->user();
        $this->lng = $DIC->language();
        $this->ui = $DIC->ui();
    }

    
    /**
     * Execute command
     *
     * @param
     * @return
     */
    public function executeCommand()
    {
        $ilCtrl = $this->ctrl;
        
        $next_class = $ilCtrl->getNextClass();
        switch ($next_class) {
            default:
                $cmd = $ilCtrl->getCmd();
                $this->$cmd();
                break;
        }
    }
    
    
    /**
    * Set Object.
    *
    * @param	int			$a_obj_id			Object ID
    * @param	string		$a_obj_type			Object Type
    * @param	int			$a_sub_obj_id		Subobject ID
    * @param	string		$a_sub_obj_type		Subobject Type
    */
    public function setObject($a_obj_id, $a_obj_type, $a_sub_obj_id = 0, $a_sub_obj_type = "")
    {
        $ilUser = $this->user;

        $this->obj_id = $a_obj_id;
        $this->obj_type = $a_obj_type;
        $this->sub_obj_id = $a_sub_obj_id;
        $this->sub_obj_type = $a_sub_obj_type;
        
        $this->setSaveCmd("saveTags");
        $this->setUserId($ilUser->getId());
        $this->setInputFieldName("il_tags");
        
        $tags_set = new ilSetting("tags");
        $forbidden = $tags_set->get("forbidden_tags");
        if ($forbidden != "") {
            $this->forbidden = unserialize($forbidden);
        } else {
            $this->forbidden = array();
        }
    }
    
    /**
    * Set User ID.
    *
    * @param	int	$a_userid	User ID
    */
    public function setUserId($a_userid)
    {
        $this->userid = $a_userid;
    }

    /**
    * Get User ID.
    *
    * @return	int	User ID
    */
    public function getUserId()
    {
        return $this->userid;
    }

    /**
    * Set Save Command.
    *
    * @param	string	$a_savecmd	Save Command
    */
    public function setSaveCmd($a_savecmd)
    {
        $this->savecmd = $a_savecmd;
    }

    /**
    * Get Save Command.
    *
    * @return	string	Save Command
    */
    public function getSaveCmd()
    {
        return $this->savecmd;
    }

    /**
    * Set Input Field Name.
    *
    * @param	string	$a_inputfieldname	Input Field Name
    */
    public function setInputFieldName($a_inputfieldname)
    {
        $this->inputfieldname = $a_inputfieldname;
    }

    /**
    * Get Input Field Name.
    *
    * @return	string	Input Field Name
    */
    public function getInputFieldName()
    {
        return $this->inputfieldname;
    }

    /**
    * Get Input HTML for Tagging of an object (and a user)
    */
    public function getTaggingInputHTML()
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        
        $ttpl = new ilTemplate("tpl.tags_input.html", true, true, "Services/Tagging");
        $tags = ilTagging::getTagsForUserAndObject(
            $this->obj_id,
            $this->obj_type,
            $this->sub_obj_id,
            $this->sub_obj_type,
            $this->getUserId()
        );
        $ttpl->setVariable(
            "VAL_TAGS",
            ilUtil::prepareFormOutput(implode(", ", $tags))
        );
        $ttpl->setVariable("TXT_SAVE", $lng->txt("save"));
        $ttpl->setVariable("TXT_COMMA_SEPARATED", $lng->txt("comma_separated"));
        $ttpl->setVariable("CMD_SAVE", $this->savecmd);
        $ttpl->setVariable("NAME_TAGS", $this->getInputFieldName());
        
        return $ttpl->get();
    }
    
    /**
    * Save Input
    */
    public function saveInput()
    {
        $lng = $this->lng;
        
        $input = ilUtil::stripSlashes($_POST[$this->getInputFieldName()]);
        $input = str_replace("\r", "\n", $input);
        $input = str_replace("\n\n", "\n", $input);
        $input = str_replace("\n", ",", $input);
        $itags = explode(",", $input);
        $tags = array();
        foreach ($itags as $itag) {
            $itag = trim($itag);
            if (!in_array($itag, $tags) && $itag != "") {
                if (!$this->isForbidden($itag)) {
                    $tags[] = $itag;
                }
            }
        }

        ilTagging::writeTagsForUserAndObject(
            $this->obj_id,
            $this->obj_type,
            $this->sub_obj_id,
            $this->sub_obj_type,
            $this->getUserId(),
            $tags
        );
        ilUtil::sendSuccess($lng->txt('msg_obj_modified'));
    }
    
    /**
    * Check whether a tag is forbiddens
    */
    public function isForbidden($a_tag)
    {
        foreach ($this->forbidden as $f) {
            if (is_int(strpos(strtolower(
                str_replace(array("+", "§", '"', "'", "*", "%", "&", "/", "\\", "(", ")", "=", ":", ";", ":", "-", "_", "\$",
                    "£" . "!" . "¨", "^", "`", "@", "<", ">"), "", $a_tag)
            ), $f))) {
                return true;
            }
        }
        return false;
    }
    
    /**
    * Get Input HTML for Tagging of an object (and a user)
    */
    public function getAllUserTagsForObjectHTML()
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        
        $ttpl = new ilTemplate("tpl.tag_cloud.html", true, true, "Services/Tagging");
        $tags = ilTagging::getTagsForObject(
            $this->obj_id,
            $this->obj_type,
            $this->sub_obj_id,
            $this->sub_obj_type
        );

        $max = 1;
        foreach ($tags as $tag) {
            $max = max($max, $tag["cnt"]);
        }
        reset($tags);
        foreach ($tags as $tag) {
            if (!$this->isForbidden($tag["tag"])) {
                $ttpl->setCurrentBlock("unlinked_tag");
                $ttpl->setVariable(
                    "REL_CLASS",
                    ilTagging::getRelevanceClass($tag["cnt"], $max)
                );
                $ttpl->setVariable("TAG_TITLE", $tag["tag"]);
                $ttpl->parseCurrentBlock();
            }
        }
        
        return $ttpl->get();
    }

    
    ////
    //// Ajax related methods
    ////
    
    /**
     * Init javascript
     */
    public static function initJavascript($a_ajax_url, ilGlobalTemplateInterface $a_main_tpl = null)
    {
        global $DIC;

        if ($a_main_tpl != null) {
            $tpl = $a_main_tpl;
        } else {
            $tpl = $DIC["tpl"];
        }

        $lng = $DIC->language();

        $lng->loadLanguageModule("tagging");
        $lng->toJs("tagging_tags", $tpl);

        iljQueryUtil::initjQuery($tpl);
        $tpl->addJavascript("./Services/Tagging/js/ilTagging.js");
        
        $tpl->addOnLoadCode("ilTagging.setAjaxUrl('" . $a_ajax_url . "');");
    }
    
    /**
     * Get tagging js call
     *
     * @param string $a_hash
     * @param string $a_update_code
     * @return string
     */
    public static function getListTagsJSCall($a_hash, $a_update_code = null)
    {
        global $DIC;

        $tpl = $DIC["tpl"];
        
        if ($a_update_code === null) {
            $a_update_code = "null";
        } else {
            $a_update_code = "'" . $a_update_code . "'";
        }

        return "ilTagging.listTags(event, '" . $a_hash . "', " . $a_update_code . ");";
    }

    /**
     * Get HTML
     *
     * @param
     * @return
     */
    public function getHTML()
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        $ui = $this->ui;
        
        $lng->loadLanguageModule("tagging");
        $tpl = new ilTemplate("tpl.edit_tags.html", true, true, "Services/Tagging");
        $tpl->setVariable("TXT_TAGS", $lng->txt("tagging_tags"));
    
        switch ($_GET["mess"] != "" ? $_GET["mess"] : $this->mess) {
            case "mod":
                $mtype = "success";
                $mtxt = $lng->txt("msg_obj_modified");
                break;
        }
        if ($mtxt != "") {
            $tpl->setVariable("MESS", ilUtil::getSystemMessageHTML($mtxt, $mtype));
        } else {
            $tpl->setVariable("MESS", "");
        }

        $title = ilObject::_lookupTitle($this->obj_id);
        $icon = $ui->factory()->symbol()->icon()->custom(
            ilObject::_getIcon($this->obj_id),
            $title,
            "medium"
        );
        $tpl->setVariable("ICON", $ui->renderer()->render($icon));
        $tpl->setVariable("TXT_OBJ_TITLE", ilObject::_lookupTitle($this->obj_id));
        $tags = ilTagging::getTagsForUserAndObject(
            $this->obj_id,
            $this->obj_type,
            $this->sub_obj_id,
            $this->sub_obj_type,
            $this->getUserId()
        );
        $tpl->setVariable(
            "VAL_TAGS",
            ilUtil::prepareFormOutput(implode(", ", $tags))
        );
        $tpl->setVariable("TXT_SAVE", $lng->txt("save"));
        $tpl->setVariable("TXT_COMMA_SEPARATED", $lng->txt("comma_separated"));
        $tpl->setVariable("CMD_SAVE", "saveJS");
        
        $os = "ilTagging.cmdAjaxForm(event, '" .
            $ilCtrl->getFormActionByClass("iltagginggui", "", "", true) .
            "');";
        $tpl->setVariable("ON_SUBMIT", $os);
        
        $tags_set = new ilSetting("tags");
        if ($tags_set->get("enable_all_users")) {
            $tpl->setVariable("TAGS_TITLE", $lng->txt("tagging_my_tags"));
            
            $all_obj_tags = ilTagging::_getListTagsForObjects(array($this->obj_id));
            $all_obj_tags = $all_obj_tags[$this->obj_id];
            if (is_array($all_obj_tags) &&
                sizeof($all_obj_tags) != sizeof($tags)) {
                $tpl->setVariable("TITLE_OTHER", $lng->txt("tagging_other_users"));
                $tpl->setCurrentBlock("tag_other_bl");
                foreach ($all_obj_tags as $tag => $is_owner) {
                    if (!$is_owner) {
                        $tpl->setVariable("OTHER_TAG", $tag);
                        $tpl->parseCurrentBlock();
                    }
                }
            }
        }
        
        echo $tpl->get();
        exit;
    }
    
    /**
     * Save JS
     */
    public function saveJS()
    {
        $input = ilUtil::stripSlashes($_POST["tags"]);
        $input = str_replace("\r", "\n", $input);
        $input = str_replace("\n\n", "\n", $input);
        $input = str_replace("\n", ",", $input);
        $itags = explode(",", $input);
        $tags = array();
        foreach ($itags as $itag) {
            $itag = trim($itag);
            if (!in_array($itag, $tags) && $itag != "") {
                if (!$this->isForbidden($itag)) {
                    $tags[] = $itag;
                }
            }
        }

        ilTagging::writeTagsForUserAndObject(
            $this->obj_id,
            $this->obj_type,
            $this->sub_obj_id,
            $this->sub_obj_type,
            $this->getUserId(),
            $tags
        );
        
        $this->mess = "mod";
        
        $this->getHTML();
    }
}
