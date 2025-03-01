<?php

/* Copyright (c) 1998-2021 ILIAS open source, GPLv3, see LICENSE */

/**
 * Class ilObjWikiAccess
 *
 * @author 		Alex Killing <alex.killing@gmx.de>
 */
class ilObjWikiAccess extends ilObjectAccess
{
    /**
     * @var ilObjUser
     */
    protected $user;

    /**
     * @var ilLanguage
     */
    protected $lng;

    /**
     * @var ilRbacSystem
     */
    protected $rbacsystem;

    /**
     * @var ilAccessHandler
     */
    protected $access;


    /**
     * Constructor
     */
    public function __construct()
    {
        global $DIC;

        $this->user = $DIC->user();
        $this->lng = $DIC->language();
        $this->rbacsystem = $DIC->rbac()->system();
        $this->access = $DIC->access();
    }


    /**
     * get commands
     *
     * this method returns an array of all possible commands/permission combinations
     *
     * example:
     * $commands = array
     *	(
     *		array("permission" => "read", "cmd" => "view", "lang_var" => "show"),
     *		array("permission" => "write", "cmd" => "edit", "lang_var" => "edit"),
     *	);
     */
    public static function _getCommands()
    {
        $commands = array(
            array("permission" => "read", "cmd" => "view", "lang_var" => "show",
                "default" => true),
            array("permission" => "write", "cmd" => "editSettings", "lang_var" => "settings")
        );
        
        return $commands;
    }
    
    /**
    * checks wether a user may invoke a command or not
    * (this method is called by ilAccessHandler::checkAccess)
    *
    * @param	string		$a_cmd		command (not permission!)
    * @param	string		$a_permission	permission
    * @param	int			$a_ref_id	reference id
    * @param	int			$a_obj_id	object id
    * @param	int			$a_user_id	user id (if not provided, current user is taken)
    *
    * @return	boolean		true, if everything is ok
    */
    public function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = "")
    {
        $ilUser = $this->user;
        $lng = $this->lng;
        $rbacsystem = $this->rbacsystem;
        $ilAccess = $this->access;

        if ($a_user_id == "") {
            $a_user_id = $ilUser->getId();
        }

        switch ($a_cmd) {
            case "view":

                if (!ilObjWikiAccess::_lookupOnline($a_obj_id)
                    && !$rbacsystem->checkAccessOfUser($a_user_id, 'write', $a_ref_id)) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
                    return false;
                }
                break;
                
            // for permission query feature
            case "infoScreen":
                if (!ilObjWikiAccess::_lookupOnline($a_obj_id)) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
                } else {
                    $ilAccess->addInfoItem(IL_STATUS_MESSAGE, $lng->txt("online"));
                }
                break;

        }
        switch ($a_permission) {
            case "read":
            case "visible":
                if (!ilObjWikiAccess::_lookupOnline($a_obj_id) &&
                    (!$rbacsystem->checkAccessOfUser($a_user_id, 'write', $a_ref_id))) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("offline"));
                    return false;
                }

                $info = ilExcRepoObjAssignment::getInstance()->getAccessInfo($a_ref_id, $a_user_id);
                if (!$info->isGranted()) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, implode(" / ", $info->getNotGrantedReasons()));
                    return false;
                }
                break;
        }

        return true;
    }

    /**
    * check whether goto script will succeed
    */
    public static function _checkGoto($a_target)
    {
        global $DIC;

        $ilAccess = $DIC->access();
        //	echo "-".$a_target."-"; exit;
        $t_arr = explode("_", $a_target);

        if ($t_arr[0] != "wiki" || (((int) $t_arr[1]) <= 0) && $t_arr[1] != "wpage") {
            return false;
        }
        
        if ($t_arr[1] == "wpage") {
            $wpg_id = (int) $t_arr[2];
            $w_id = ilWikiPage::lookupWikiId($wpg_id);
            if ((int) $t_arr[3] > 0) {
                $refs = array((int) $t_arr[3]);
            } else {
                $refs = ilObject::_getAllReferences($w_id);
            }
            foreach ($refs as $r) {
                if ($ilAccess->checkAccess("read", "", $r)) {
                    return true;
                }
            }
        } elseif ($ilAccess->checkAccess("read", "", $t_arr[1])) {
            return true;
        }
        return false;
    }
    
    /**
    * Check wether wiki cast is online
    *
    * @param	int		$a_id	wiki id
    */
    public static function _lookupOnline($a_id)
    {
        global $DIC;

        $ilDB = $DIC->database();

        $q = "SELECT * FROM il_wiki_data WHERE id = " .
            $ilDB->quote($a_id, "integer");
        $wk_set = $ilDB->query($q);
        $wk_rec = $ilDB->fetchAssoc($wk_set);

        return $wk_rec["is_online"];
    }

    /**
     * Check wether learning module is online (legacy version)
     *
     * @deprecated
     */
    public static function _lookupOnlineStatus($a_ids)
    {
        global $DIC;

        $ilDB = $DIC->database();

        $q = "SELECT id, is_online FROM il_wiki_data WHERE " .
            $ilDB->in("id", $a_ids, false, "integer");
        $lm_set = $ilDB->query($q);
        $status = [];
        while ($r = $ilDB->fetchAssoc($lm_set)) {
            $status[$r["id"]] = $r["is_online"];
        }
        return $status;
    }


    /**
    * Check wether files should be public
    *
    * @param	int		$a_id	wiki id
    */
    public static function _lookupPublicFiles($a_id)
    {
        global $DIC;

        $ilDB = $DIC->database();

        $q = "SELECT * FROM il_wiki_data WHERE id = " .
            $ilDB->quote($a_id, "integer");
        $wk_set = $ilDB->query($q);
        $wk_rec = $ilDB->fetchAssoc($wk_set);

        return $wk_rec["public_files"];
    }
}
