<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

use \ILIAS\Survey\Participants;

/**
* Class ilSurveyParticipantsGUI
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @version  $Id: class.ilObjSurveyGUI.php 43670 2013-07-26 08:41:31Z jluetzen $
*
* @ilCtrl_Calls ilSurveyParticipantsGUI: ilRepositorySearchGUI
*
* @ingroup ModulesSurvey
*/
class ilSurveyParticipantsGUI
{
    /**
     * @var ilCtrl
     */
    protected $ctrl;

    /**
     * @var ilLanguage
     */
    protected $lng;

    /**
     * @var ilTemplate
     */
    protected $tpl;

    /**
     * @var ilTabsGUI
     */
    protected $tabs;

    /**
     * @var ilToolbarGUI
     */
    protected $toolbar;

    /**
     * @var ilAccessHandler
     */
    protected $access;

    /**
     * @var ilRbacSystem
     */
    protected $rbacsystem;

    /**
     * @var ilObjUser
     */
    protected $user;

    /**
     * @var Logger
     */
    protected $log;

    protected $parent_gui; // [ilObjSurveyGUI]
    protected $object; // [ilObjSurvey]
    protected $ref_id; // [int]
    protected $has_write; // [bool]

    /**
     * @var Participants\InvitationsManager
     */
    protected $invitation_manager;
    
    public function __construct(ilObjSurveyGUI $a_parent_gui, $a_has_write_access)
    {
        global $DIC;

        $this->tabs = $DIC->tabs();
        $this->toolbar = $DIC->toolbar();
        $this->access = $DIC->access();
        $this->rbacsystem = $DIC->rbac()->system();
        $this->user = $DIC->user();
        $this->log = $DIC["ilLog"];
        $ilCtrl = $DIC->ctrl();
        $lng = $DIC->language();
        $tpl = $DIC["tpl"];
        
        $this->parent_gui = $a_parent_gui;
        $this->object = $this->parent_gui->object;
        $this->ref_id = $this->object->getRefId();
        $this->has_write = (bool) $a_has_write_access;
        
        $this->ctrl = $ilCtrl;
        $this->lng = $lng;
        $this->tpl = $tpl;
        $this->invitation_manager = new Participants\InvitationsManager();
    }
    
    protected function handleWriteAccess()
    {
        if (!$this->has_write) {
            throw new ilSurveyException("Permission denied");
        }
    }
    
    public function executeCommand()
    {
        $ilCtrl = $this->ctrl;
        $ilTabs = $this->tabs;
        $lng = $this->lng;
        
        $cmd = $ilCtrl->getCmd("maintenance");
        $next_class = $this->ctrl->getNextClass($this);
        
        switch ($next_class) {
            case 'ilrepositorysearchgui':
                $rep_search = new ilRepositorySearchGUI();
                
                if (!$_REQUEST["appr360"] && !$_REQUEST["rate360"]) {
                    $ilTabs->clearTargets();
                    $ilTabs->setBackTarget(
                        $this->lng->txt("btn_back"),
                        $this->ctrl->getLinkTarget($this, "maintenance")
                    );

                    $rep_search->setCallback(
                        $this,
                        'inviteUsers',
                        array(
                            )
                    );
                    $rep_search->setTitle($lng->txt("svy_invite_participants"));
                    // Set tabs
                    $this->ctrl->setReturn($this, 'maintenance');
                    $this->ctrl->forwardCommand($rep_search);
                    $ilTabs->setTabActive('maintenance');
                } elseif ($_REQUEST["rate360"]) {
                    $ilTabs->clearTargets();
                    $ilTabs->setBackTarget(
                        $this->lng->txt("btn_back"),
                        $this->ctrl->getLinkTarget($this, "listAppraisees")
                    );
                    
                    $this->ctrl->setParameter($this, "rate360", 1);
                    $this->ctrl->saveParameter($this, "appr_id");
                    
                    $rep_search->setCallback(
                        $this,
                        'addRater',
                        array(
                            )
                    );

                    // Set tabs
                    $this->ctrl->setReturn($this, 'editRaters');
                    $this->ctrl->forwardCommand($rep_search);
                } else {
                    $ilTabs->activateTab("survey_360_appraisees");
                    $this->ctrl->setParameter($this, "appr360", 1);
                    
                    $rep_search->setCallback(
                        $this,
                        'addAppraisee',
                        array(
                            )
                    );

                    // Set tabs
                    $this->ctrl->setReturn($this, 'listAppraisees');
                    $this->ctrl->forwardCommand($rep_search);
                }
                break;
                
            default:
                $cmd .= "Object";
                $this->$cmd();
                break;
        }
    }
    
    protected function filterSurveyParticipantsByAccess($a_finished_ids = null)
    {
        $all_participants = $this->object->getSurveyParticipants($a_finished_ids, false, true);
        $participant_ids = [];
        foreach ($all_participants as $participant) {
            $participant_ids[] = $participant['usr_id'];
        }

        $filtered_participant_ids = $this->access->filterUserIdsByRbacOrPositionOfCurrentUser(
            'read_results',
            'access_results',
            $this->object->getRefId(),
            $participant_ids
        );

        $participants = [];
        foreach ($all_participants as $username => $user_data) {
            if (!$user_data['usr_id']) {
                $participants[$username] = $user_data;
            }
            if (in_array($user_data['usr_id'], $filtered_participant_ids)) {
                $participants[$username] = $user_data;
            }
        }

        return $participants;
    }
    
    
    /**
    * Participants maintenance
    */
    public function maintenanceObject()
    {
        $ilToolbar = $this->toolbar;
        $lng = $this->lng;
        
        if ($this->object->get360Mode()) {
            return $this->listAppraiseesObject();
        }
        
        //Btn Determine Competence Levels
        if ($this->object->getMode() == ilObjSurvey::MODE_SELF_EVAL) {
            $skmg_set = new ilSkillManagementSettings();
            if ($this->object->getSkillService() && $skmg_set->isActivated()) {
                $ilToolbar->addButton(
                    $this->lng->txt("survey_calc_skills"),
                    $this->ctrl->getLinkTargetByClass("ilsurveyskilldeterminationgui"),
                    ""
                );
            }
        }

        $this->handleWriteAccess();
        $this->setParticipantSubTabs("overview");

        $ilToolbar->addButton(
            $this->lng->txt('svy_remove_all_participants'),
            $this->ctrl->getLinkTarget($this, 'deleteAllUserData')
        );

        $ilToolbar->addSeparator();

        if ($this->object->isAccessibleWithoutCode()) {
            $ilToolbar->addButton(
                $this->lng->txt("svy_invite_participants"),
                $this->ctrl->getLinkTargetByClass('ilRepositorySearchGUI', '')
            );
        }

        $table_gui = new ilSurveyMaintenanceTableGUI($this, 'maintenance');

        $total = $this->filterSurveyParticipantsByAccess();
        $data = array();
        foreach ($total as $user_data) {
            $finished = false;
            if ((bool) $user_data["finished"]) {
                $finished = $user_data["finished_tstamp"];
            }
            $wt = $this->object->getWorkingtimeForParticipant($user_data["active_id"]);
            $last_access = $this->object->getLastAccess($user_data["active_id"]);
            array_push($data, array(
                'id' => $user_data["active_id"],
                'name' => $user_data["sortname"],
                'usr_id' => $user_data["usr_id"],
                'login' => $user_data["login"],
                'last_access' => $last_access,
                'workingtime' => $wt,
                'finished' => $finished,
                'invited' => $user_data["invited"]
            ));
        }
        $table_gui->setData($data);
        $this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());
    }
    
    protected function isAnonymousListActive()
    {
        $surveySetting = new ilSetting("survey");

        if ($surveySetting->get("anonymous_participants", false)) {
            if ($this->object->hasAnonymizedResults() &&
                $this->object->hasAnonymousUserList()) {
                $end = $this->object->getEndDate();
                if ($end && $end < date("YmdHis")) {
                    $min = $surveySetting->get("anonymous_participants_min", 0);
                    $total = $this->object->getSurveyParticipants();
                    if (!$min || sizeof($total) >= $min) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    /**
     * Set the tabs for the access codes section
     *
     * @param string $active
     */
    protected function setParticipantSubTabs(string $active)
    {
        $ilTabs = $this->tabs;
        
        // not used in 360° mode
    
        // overview
        $ilTabs->addSubTab(
            "overview",
            $this->lng->txt("svy_part_overview"),
            $this->ctrl->getLinkTarget($this, 'maintenance')
        );
        
        if ($this->isAnonymousListActive()) {
            $ilTabs->addSubTab(
                "anon_participants",
                $this->lng->txt("svy_anonymous_participants_svy"),
                $this->ctrl->getLinkTarget($this, 'listParticipants')
            );
        }

        if (!$this->object->isAccessibleWithoutCode()) {
            $ilTabs->addSubTab(
                "codes",
                $this->lng->txt("svy_codes"),
                $this->ctrl->getLinkTarget($this, 'codes')
            );
        }
        

        $data = $this->object->getExternalCodeRecipients();
        if (count($data)) {
            $ilTabs->addSubTab(
                "mail_survey_codes",
                $this->lng->txt("mail_survey_codes"),
                $this->ctrl->getLinkTarget($this, "mailCodes")
            );
        }

        $ilTabs->activateSubTab($active);
    }
    


    /**
    * Creates a confirmation form for delete all user data
    */
    public function deleteAllUserDataObject()
    {
        $cgui = new ilConfirmationGUI();
        $cgui->setHeaderText($this->lng->txt("confirm_delete_all_user_data"));
        $cgui->setFormAction($this->ctrl->getFormAction($this, "deleteAllUserData"));
        $cgui->setCancel($this->lng->txt("cancel"), "cancelDeleteAllUserData");
        $cgui->setConfirm($this->lng->txt("confirm"), "confirmDeleteAllUserData");
        $this->tpl->setContent($cgui->getHTML());
    }
    
    /**
    * Deletes all user data of the survey after confirmation
    */
    public function confirmDeleteAllUserDataObject()
    {
        if ($this->access->checkAccess('write', '', $this->object->getRefId())) {
            $this->object->deleteAllUserData();
        } else {
            $participants = $this->filterSurveyParticipantsByAccess();
            foreach ($participants as $something => $participant_data) {
                $this->object->removeSelectedSurveyResults([$participant_data['active_id']]);
            }
        }
        
        
        
        // #11558 - re-open closed appraisees
        if ($this->object->get360Mode()) {
            $this->object->openAllAppraisees();
        }
        
        ilUtil::sendSuccess($this->lng->txt("svy_all_user_data_deleted"), true);
        $this->ctrl->redirect($this, "maintenance");
    }
    
    /**
    * Cancels delete of all user data in maintenance
    */
    public function cancelDeleteAllUserDataObject()
    {
        $this->ctrl->redirect($this, "maintenance");
    }
    
    /**
    * Deletes all user data for the test object
    */
    public function confirmDeleteSelectedUserDataObject()
    {
        if (is_array($_POST["chbUser"])) {
            $this->object->removeSelectedSurveyResults(array_filter($_POST["chbUser"], function ($i) {
                return is_numeric($i);
            }));

            $invitations = array_filter($_POST["chbUser"], function ($i) {
                return (substr($i, 0, 3) == "inv");
            });
            foreach ($invitations as $i) {
                $this->invitation_manager->remove($this->object->getSurveyId(), (int) substr($i, 3));
            }

            ilUtil::sendSuccess($this->lng->txt("svy_selected_user_data_deleted"), true);
        }
        $this->ctrl->redirect($this, "maintenance");
    }
    
    /**
    * Cancels the deletion of all user data for the test object
    */
    public function cancelDeleteSelectedUserDataObject()
    {
        ilUtil::sendInfo($this->lng->txt('msg_cancel'), true);
        $this->ctrl->redirect($this, "maintenance");
    }
    
    /**
    * Asks for a confirmation to delete selected user data of the test object
    */
    public function deleteSingleUserResultsObject()
    {
        $this->handleWriteAccess();
        
        if (!is_array($_POST["chbUser"]) || count($_POST["chbUser"]) == 0) {
            ilUtil::sendInfo($this->lng->txt('no_checkbox'), true);
            $this->ctrl->redirect($this, "maintenance");
        }

        ilUtil::sendQuestion($this->lng->txt("confirm_delete_single_user_data"));
        $table_gui = new ilSurveyMaintenanceTableGUI($this, 'maintenance', true);
        $total = $this->object->getSurveyParticipants(null, false, true);
        $data = array();
        foreach ($total as $user_data) {
            if (in_array($user_data['active_id'], $_POST['chbUser'])
                || ($user_data['invited'] && in_array("inv" . $user_data['usr_id'], $_POST['chbUser']))) {
                $last_access = $this->object->getLastAccess($user_data["active_id"]);
                array_push($data, array(
                    'id' => $user_data["active_id"],
                    'name' => $user_data["sortname"],
                    'login' => $user_data["login"],
                    'last_access' => $last_access,
                    'usr_id' => $user_data["usr_id"],
                    'invited' => $user_data["invited"]
                ));
            }
        }
        $table_gui->setData($data);
        $this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());
    }
    
    /**
    * Change survey language for direct access URL's
    */
    public function setCodeLanguageObject()
    {
        if (strcmp($_POST["lang"], "-1") != 0) {
            $ilUser = $this->user;
            $ilUser->writePref("survey_code_language", $_POST["lang"]);
        }
        ilUtil::sendSuccess($this->lng->txt('language_changed'), true);
        $this->ctrl->redirect($this, 'codes');
    }
    
    /**
    * Display the survey access codes tab
    */
    public function codesObject()
    {
        $ilUser = $this->user;
        $ilToolbar = $this->toolbar;
        
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("codes");
        
        if ($this->object->isAccessibleWithoutCode()) {
            return ilUtil::sendInfo($this->lng->txt("survey_codes_no_anonymization"));
        }

        $default_lang = $ilUser->getPref("survey_code_language");

        // creation buttons
        $ilToolbar->setFormAction($this->ctrl->getFormAction($this));
        
        $si = new ilTextInputGUI($this->lng->txt("new_survey_codes"), "nrOfCodes");
        $si->setValue(1);
        $si->setSize(3);
        $ilToolbar->addInputItem($si, true);
        
        $button = ilSubmitButton::getInstance();
        $button->setCaption("create");
        $button->setCommand("createSurveyCodes");
        $ilToolbar->addButtonInstance($button);
    
        $ilToolbar->addSeparator();
        
        $button = ilSubmitButton::getInstance();
        $button->setCaption("import_from_file");
        $button->setCommand("importExternalMailRecipientsFromFileForm");
        $ilToolbar->addButtonInstance($button);
        
        $button = ilSubmitButton::getInstance();
        $button->setCaption("import_from_text");
        $button->setCommand("importExternalMailRecipientsFromTextForm");
        $ilToolbar->addButtonInstance($button);
        
        $ilToolbar->addSeparator();
                
        $button = ilSubmitButton::getInstance();
        $button->setCaption("svy_import_codes");
        $button->setCommand("importAccessCodes");
        $ilToolbar->addButtonInstance($button);
            
        $ilToolbar->addSeparator();
        
        $languages = $this->lng->getInstalledLanguages();
        $options = array();
        $this->lng->loadLanguageModule("meta");
        foreach ($languages as $lang) {
            $options[$lang] = $this->lng->txt("meta_l_" . $lang);
        }
        $si = new ilSelectInputGUI($this->lng->txt("survey_codes_lang"), "lang");
        $si->setOptions($options);
        $si->setValue($default_lang);
        $ilToolbar->addInputItem($si, true);
        
        $button = ilSubmitButton::getInstance();
        $button->setCaption("set");
        $button->setCommand("setCodeLanguage");
        $ilToolbar->addButtonInstance($button);
    
        $table_gui = new ilSurveyCodesTableGUI($this, 'codes');
        $survey_codes = $this->object->getSurveyCodesTableData(null, $default_lang);
        $table_gui->setData($survey_codes);
        $this->tpl->setContent($table_gui->getHTML());
    }
    
    public function editCodesObject()
    {
        if (isset($_GET["new_ids"])) {
            $ids = explode(";", $_GET["new_ids"]);
        } else {
            $ids = (array) $_POST["chb_code"];
        }
        if (!$ids) {
            ilUtil::sendFailure($this->lng->txt('no_checkbox'), true);
            $this->ctrl->redirect($this, 'codes');
        }
    
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("codes");
        
        $table_gui = new ilSurveyCodesEditTableGUI($this, 'editCodes');
        $table_gui->setData($this->object->getSurveyCodesTableData($ids));
        $this->tpl->setContent($table_gui->getHTML());
    }
    
    public function updateCodesObject()
    {
        if (!is_array($_POST["chb_code"])) {
            $this->ctrl->redirect($this, 'codes');
        }

        $errors = array();
        $error_message = "";
        foreach ($_POST["chb_code"] as $id) {
            if (!$this->object->updateCode(
                $id,
                $_POST["chb_mail"][$id],
                $_POST["chb_lname"][$id],
                $_POST["chb_fname"][$id],
                $_POST["chb_sent"][$id]
            )) {
                array_push($errors, array($_POST["chb_mail"][$id], $_POST["chb_lname"][$id], $_POST["chb_fname"][$id]));
            };
        }
        if (empty($errors)) {
            ilUtil::sendSuccess($this->lng->txt('settings_saved'), true);
        } else {
            foreach ($errors as $error) {
                $error_message .= sprintf($this->lng->txt("error_save_code"), $error[0], $error[1], $error[2]);
            }
            ilUtil::sendFailure($error_message, true);
        }

        $this->ctrl->redirect($this, 'codes');
    }
    
    public function deleteCodesConfirmObject()
    {
        if (is_array($_POST["chb_code"]) && (count($_POST["chb_code"]) > 0)) {
            $cgui = new ilConfirmationGUI();
            $cgui->setHeaderText($this->lng->txt("survey_code_delete_sure"));

            $cgui->setFormAction($this->ctrl->getFormAction($this));
            $cgui->setCancel($this->lng->txt("cancel"), "codes");
            $cgui->setConfirm($this->lng->txt("confirm"), "deleteCodes");
            
            $data = $this->object->getSurveyCodesTableData($_POST["chb_code"]);

            foreach ($data as $item) {
                if ($item["used"]) {
                    continue;
                }
                
                $title = array($item["code"]);
                $item["email"] ? $title[] = $item["email"] : null;
                $item["last_name"] ? $title[] = $item["last_name"] : null;
                $item["first_name"] ? $title[] = $item["first_name"] : null;
                $title = implode(", ", $title);
                                
                $cgui->addItem("chb_code[]", $item["code"], $title);
            }

            $this->tpl->setContent($cgui->getHTML());
        } else {
            ilUtil::sendFailure($this->lng->txt('no_checkbox'), true);
            $this->ctrl->redirect($this, 'codes');
        }
    }
    
    /**
    * Delete a list of survey codes
    */
    public function deleteCodesObject()
    {
        if (is_array($_POST["chb_code"]) && (count($_POST["chb_code"]) > 0)) {
            foreach ($_POST["chb_code"] as $survey_code) {
                $this->object->deleteSurveyCode($survey_code);
            }
            ilUtil::sendSuccess($this->lng->txt('codes_deleted'), true);
        } else {
            ilUtil::sendInfo($this->lng->txt('no_checkbox'), true);
        }
        $this->ctrl->redirect($this, 'codes');
    }
    
    /**
    * Exports a list of survey codes
    */
    public function exportCodesObject()
    {
        if (is_array($_POST["chb_code"]) && (count($_POST["chb_code"]) > 0)) {
            $export = $this->object->getSurveyCodesForExport(null, $_POST["chb_code"]);
            ilUtil::deliverData($export, ilUtil::getASCIIFilename($this->object->getTitle() . ".csv"));
        } else {
            ilUtil::sendFailure($this->lng->txt("no_checkbox"), true);
            $this->ctrl->redirect($this, 'codes');
        }
    }
    
    /**
    * Exports all survey codes
    */
    public function exportAllCodesObject()
    {
        $export = $this->object->getSurveyCodesForExport();
        ilUtil::deliverData($export, ilUtil::getASCIIFilename($this->object->getTitle() . ".csv"));
    }
    
    /**
     * Import codes from export codes file (upload form)
     */
    protected function importAccessCodesObject()
    {
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("codes");
        
        $form_import_file = new ilPropertyFormGUI();
        $form_import_file->setFormAction($this->ctrl->getFormAction($this));
        $form_import_file->setTableWidth("100%");
        $form_import_file->setId("codes_import_file");

        $headerfile = new ilFormSectionHeaderGUI();
        $headerfile->setTitle($this->lng->txt("svy_import_codes"));
        $form_import_file->addItem($headerfile);
        
        $export_file = new ilFileInputGUI($this->lng->txt("codes"), "codes");
        $export_file->setInfo(sprintf(
            $this->lng->txt('svy_import_codes_info'),
            $this->lng->txt("export_all_survey_codes")
        ));
        $export_file->setSuffixes(array("csv"));
        $export_file->setRequired(true);
        $form_import_file->addItem($export_file);
        
        $form_import_file->addCommandButton("importAccessCodesAction", $this->lng->txt("import"));
        $form_import_file->addCommandButton("codes", $this->lng->txt("cancel"));

        $this->tpl->setContent($form_import_file->getHTML());
    }
    
    /**
     * Import codes from export codes file
     */
    protected function importAccessCodesActionObject()
    {
        if (trim($_FILES['codes']['tmp_name'])) {
            $existing = array();
            foreach ($this->object->getSurveyCodesTableData() as $item) {
                $existing[$item["code"]] = $item["id"];
            }
            
            $reader = new ilCSVReader();
            $reader->open($_FILES['codes']['tmp_name']);
            foreach ($reader->getDataArrayFromCSVFile() as $row) {
                // numeric check of used column due to #26176
                if (sizeof($row) == 8 && is_numeric($row[5])) {
                    // used/sent/url are not relevant when importing
                    list($code, $email, $last_name, $first_name, $created, $used, $sent, $url) = $row;
                    
                    // unique code?
                    if (!array_key_exists($code, $existing)) {
                        // could be date or datetime
                        if (strlen($created) == 10) {
                            $created = new ilDate($created, IL_CAL_DATE);
                        } else {
                            $created = new ilDateTime($created, IL_CAL_DATETIME);
                        }
                        $created = $created->get(IL_CAL_UNIX);
                        
                        $user_data = array(
                            "email" => $email
                            ,"lastname" => $last_name
                            ,"firstname" => $first_name
                        );
                        $this->object->importSurveyCode($code, $created, $user_data);
                    }
                }
            }
            
            ilUtil::sendSuccess($this->lng->txt('codes_created'), true);
        }
        
        $this->ctrl->redirect($this, 'codes');
    }
    
    /**
    * Create access codes for the survey
    */
    public function createSurveyCodesObject()
    {
        if (is_numeric($_POST["nrOfCodes"])) {
            $ids = $this->object->createSurveyCodes($_POST["nrOfCodes"]);
            ilUtil::sendSuccess($this->lng->txt('codes_created'), true);
            $this->ctrl->setParameter($this, "new_ids", implode(";", $ids));
            $this->ctrl->redirect($this, 'editCodes');
        } else {
            ilUtil::sendFailure($this->lng->txt("enter_valid_number_of_codes"), true);
            $this->ctrl->redirect($this, 'codes');
        }
    }

    public function insertSavedMessageObject()
    {
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("codes");

        $form_gui = new FormMailCodesGUI($this);
        $form_gui->setValuesByPost();
        try {
            if ($form_gui->getSavedMessages()->getValue() > 0) {
                $ilUser = $this->user;
                $settings = $this->object->getUserSettings($ilUser->getId(), 'savemessage');
                $form_gui->getMailMessage()->setValue($settings[$form_gui->getSavedMessages()->getValue()]['value']);
                ilUtil::sendSuccess($this->lng->txt('msg_message_inserted'));
            } else {
                ilUtil::sendFailure($this->lng->txt('msg_no_message_inserted'));
            }
        } catch (Exception $e) {
            $ilLog = $this->log;
            $ilLog->write('Error: ' + $e->getMessage());
        }
        $this->tpl->setVariable("ADM_CONTENT", $form_gui->getHTML());
    }

    public function deleteSavedMessageObject()
    {
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("codes");

        $form_gui = new FormMailCodesGUI($this);
        $form_gui->setValuesByPost();
        try {
            if ($form_gui->getSavedMessages()->getValue() > 0) {
                $this->object->deleteUserSettings($form_gui->getSavedMessages()->getValue());
                $form_gui = new FormMailCodesGUI($this);
                $form_gui->setValuesByPost();
                ilUtil::sendSuccess($this->lng->txt('msg_message_deleted'));
            } else {
                ilUtil::sendFailure($this->lng->txt('msg_no_message_deleted'));
            }
        } catch (Exception $e) {
            $ilLog = $this->log;
            $ilLog->write('Error: ' + $e->getMessage());
        }
        $this->tpl->setVariable("ADM_CONTENT", $form_gui->getHTML());
    }
    
    public function mailCodesObject()
    {
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("codes");

        $mailData['m_subject'] = (array_key_exists('m_subject', $_POST)) ? $_POST['m_subject'] : sprintf($this->lng->txt('default_codes_mail_subject'), $this->object->getTitle());
        $mailData['m_message'] = (array_key_exists('m_message', $_POST)) ? $_POST['m_message'] : $this->lng->txt('default_codes_mail_message');
        $mailData['m_notsent'] = (array_key_exists('m_notsent', $_POST)) ? $_POST['m_notsent'] : '1';

        $form_gui = new FormMailCodesGUI($this);
        $form_gui->setValuesByArray($mailData);
        $this->tpl->setVariable("ADM_CONTENT", $form_gui->getHTML());
    }
    
    public function sendCodesMailObject()
    {
        $ilUser = $this->user;
        
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("mail_survey_codes");

        $form_gui = new FormMailCodesGUI($this);
        if ($form_gui->checkInput()) {
            $url_exists = strpos($_POST['m_message'], '[url]') !== false;
            if (!$url_exists) {
                ilUtil::sendFailure($this->lng->txt('please_enter_mail_url'));
                $form_gui->setValuesByPost();
            } else {
                if ($_POST['savemessage'] == 1) {
                    $ilUser = $this->user;
                    $title = (strlen($_POST['savemessagetitle'])) ? $_POST['savemessagetitle'] : ilStr::substr($_POST['m_message'], 0, 40) . '...';
                    $this->object->saveUserSettings($ilUser->getId(), 'savemessage', $title, $_POST['m_message']);
                }
                
                $lang = $ilUser->getPref("survey_code_language");
                if (!$lang) {
                    $lang = $this->lng->getDefaultLanguage();
                }
                $this->object->sendCodes($_POST['m_notsent'], $_POST['m_subject'], nl2br($_POST['m_message']), $lang);
                ilUtil::sendSuccess($this->lng->txt('mail_sent'), true);
                $this->ctrl->redirect($this, 'mailCodes');
            }
        } else {
            $form_gui->setValuesByPost();
        }
        $this->tpl->setVariable("ADM_CONTENT", $form_gui->getHTML());
    }
    
    public function importExternalRecipientsFromTextObject()
    {
        if (trim($_POST['externaltext'])) {
            $data = preg_split("/[\n\r]/", $_POST['externaltext']);
            $fields = preg_split("/;/", array_shift($data));
            if (!in_array('email', $fields)) {
                $_SESSION['externaltext'] = $_POST['externaltext'];
                ilUtil::sendFailure($this->lng->txt('err_external_rcp_no_email_column'), true);
                $this->ctrl->redirect($this, 'importExternalMailRecipientsFromTextForm');
            }
            $existingdata = $this->object->getExternalCodeRecipients();
            $existingcolumns = array();
            if (count($existingdata)) {
                $first = array_shift($existingdata);
                foreach ($first as $key => $value) {
                    array_push($existingcolumns, $key);
                }
            }
            $founddata = array();
            foreach ($data as $datarow) {
                $row = preg_split("/;/", $datarow);
                if (count($row) == count($fields)) {
                    $dataset = array();
                    foreach ($fields as $idx => $fieldname) {
                        if (count($existingcolumns)) {
                            if (array_key_exists($idx, $existingcolumns)) {
                                $dataset[$fieldname] = $row[$idx];
                            }
                        } else {
                            $dataset[$fieldname] = $row[$idx];
                        }
                    }
                    if (strlen($dataset['email'])) {
                        array_push($founddata, $dataset);
                    }
                }
            }
            $this->object->createSurveyCodesForExternalData($founddata);
            ilUtil::sendSuccess($this->lng->txt('external_recipients_imported'), true);
            $this->ctrl->redirect($this, 'codes');
        }
        
        $this->ctrl->redirect($this, 'importExternalMailRecipientsFromTextForm');
    }
    
    // see ilBookmarkImportExport
    protected function _convertCharset($a_string, $a_from_charset = "", $a_to_charset = "UTF-8")
    {
        if (extension_loaded("mbstring")) {
            if (!$a_from_charset) {
                mb_detect_order("UTF-8, ISO-8859-1, Windows-1252, ASCII");
                $a_from_charset = mb_detect_encoding($a_string);
            }
            if (strtoupper($a_from_charset) != $a_to_charset) {
                return @mb_convert_encoding($a_string, $a_to_charset, $a_from_charset);
            }
        }
        return $a_string;
    }
    
    protected function removeUTF8Bom($a_text)
    {
        $bom = pack('H*', 'EFBBBF');
        return preg_replace('/^' . $bom . '/', '', $a_text);
    }

    public function importExternalRecipientsFromFileObject()
    {
        if (trim($_FILES['externalmails']['tmp_name'])) {
            $reader = new ilCSVReader();
            $reader->open($_FILES['externalmails']['tmp_name']);
            $data = $reader->getDataArrayFromCSVFile();
            $fields = array_shift($data);
            foreach ($fields as $idx => $field) {
                $fields[$idx] = $this->removeUTF8Bom($field);
            }
            if (!in_array('email', $fields)) {
                $reader->close();
                ilUtil::sendFailure($this->lng->txt('err_external_rcp_no_email'), true);
                $this->ctrl->redirect($this, 'codes');
            }
            $existingdata = $this->object->getExternalCodeRecipients();
            $existingcolumns = array();
            if (count($existingdata)) {
                $first = array_shift($existingdata);
                foreach ($first as $key => $value) {
                    array_push($existingcolumns, $key);
                }
            }
            
            $founddata = array();
            foreach ($data as $row) {
                if (count($row) == count($fields)) {
                    $dataset = array();
                    foreach ($fields as $idx => $fieldname) {
                        // #14811
                        $row[$idx] = $this->_convertCharset($row[$idx]);
                        
                        if (count($existingcolumns)) {
                            if (array_key_exists($idx, $existingcolumns)) {
                                $dataset[$fieldname] = $row[$idx];
                            }
                        } else {
                            $dataset[$fieldname] = $row[$idx];
                        }
                    }
                    if (strlen($dataset['email'])) {
                        array_push($founddata, $dataset);
                    }
                }
            }
            $reader->close();
            
            if (sizeof($founddata)) {
                $this->object->createSurveyCodesForExternalData($founddata);
                ilUtil::sendSuccess($this->lng->txt('external_recipients_imported'), true);
            }
        }
        
        $this->ctrl->redirect($this, 'codes');
    }
    
    public function importExternalMailRecipientsFromFileFormObject()
    {
        $ilAccess = $this->access;
        
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("mail_survey_codes");
        
        $form_import_file = new ilPropertyFormGUI();
        $form_import_file->setFormAction($this->ctrl->getFormAction($this));
        $form_import_file->setTableWidth("100%");
        $form_import_file->setId("codes_import_file");

        $headerfile = new ilFormSectionHeaderGUI();
        $headerfile->setTitle($this->lng->txt("import_from_file"));
        $form_import_file->addItem($headerfile);
        
        $externalmails = new ilFileInputGUI($this->lng->txt("externalmails"), "externalmails");
        $externalmails->setInfo($this->lng->txt('externalmails_info'));
        $externalmails->setRequired(true);
        $form_import_file->addItem($externalmails);
        if ($ilAccess->checkAccess("write", "", $_GET["ref_id"])) {
            $form_import_file->addCommandButton("importExternalRecipientsFromFile", $this->lng->txt("import"));
        }
        if ($ilAccess->checkAccess("write", "", $_GET["ref_id"])) {
            $form_import_file->addCommandButton("codes", $this->lng->txt("cancel"));
        }

        $this->tpl->setContent($form_import_file->getHTML());
    }

    public function importExternalMailRecipientsFromTextFormObject()
    {
        $ilAccess = $this->access;
        
        $this->handleWriteAccess();
        $this->setParticipantSubTabs("mail_survey_codes");
        
        $form_import_text = new ilPropertyFormGUI();
        $form_import_text->setFormAction($this->ctrl->getFormAction($this));
        $form_import_text->setTableWidth("100%");
        $form_import_text->setId("codes_import_text");

        $headertext = new ilFormSectionHeaderGUI();
        $headertext->setTitle($this->lng->txt("import_from_text"));
        $form_import_text->addItem($headertext);

        $inp = new ilTextAreaInputGUI($this->lng->txt('externaltext'), 'externaltext');
        if (array_key_exists('externaltext', $_SESSION) && strlen($_SESSION['externaltext'])) {
            $inp->setValue($_SESSION['externaltext']);
        } else {
            // $this->lng->txt('mail_import_example1') #14897
            $inp->setValue("email;firstname;lastname\n" . $this->lng->txt('mail_import_example2') . "\n" . $this->lng->txt('mail_import_example3') . "\n");
        }
        $inp->setRequired(true);
        $inp->setCols(80);
        $inp->setRows(10);
        $inp->setInfo($this->lng->txt('externaltext_info'));
        $form_import_text->addItem($inp);
        unset($_SESSION['externaltext']);

        if ($ilAccess->checkAccess("write", "", $_GET["ref_id"])) {
            $form_import_text->addCommandButton("importExternalRecipientsFromText", $this->lng->txt("import"));
        }
        if ($ilAccess->checkAccess("write", "", $_GET["ref_id"])) {
            $form_import_text->addCommandButton("codes", $this->lng->txt("cancel"));
        }

        $this->tpl->setContent($form_import_text->getHTML());
    }
    
    
    
    
    
    
    
    
    
    
    //
    // 360°
    //
    
    
    
    
    public function listAppraiseesObject()
    {
        $ilToolbar = $this->toolbar;
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;
        
        $this->handleWriteAccess();
        
        $this->ctrl->setParameter($this, "appr360", 1);
        
        ilRepositorySearchGUI::fillAutoCompleteToolbar(
            $this,
            $ilToolbar,
            array(
                'auto_complete_name' => $this->lng->txt('user'),
                'submit_name' => $this->lng->txt('add'),
                'add_search' => true,
                'add_from_container' => $this->ref_id
            )
        );
        
        // competence calculations
        $skmg_set = new ilSkillManagementSettings();
        if ($this->object->getSkillService() && $skmg_set->isActivated()) {
            $ilToolbar->addSeparator();
            $ilToolbar->addButton(
                $lng->txt("survey_calc_skills"),
                $ilCtrl->getLinkTargetByClass("ilsurveyskilldeterminationgui"),
                ""
            );
        }
        
        $ilToolbar->addSeparator();
        $ilToolbar->addButton(
            $this->lng->txt('svy_delete_all_user_data'),
            $this->ctrl->getLinkTarget($this, 'deleteAllUserData')
        );
        
        $this->ctrl->setParameter($this, "appr360", "");
        
        $tbl = new ilSurveyAppraiseesTableGUI($this, "listAppraisees");
        $tbl->setData($this->object->getAppraiseesData());
        $this->tpl->setContent($tbl->getHTML());
    }
    
    public function addAppraisee($a_user_ids)
    {
        if (sizeof($a_user_ids)) {
            // #13319
            foreach (array_unique($a_user_ids) as $user_id) {
                $this->object->addAppraisee($user_id);
            }

            ilUtil::sendSuccess($this->lng->txt("settings_saved"), true);
        }
        $this->ctrl->redirect($this, "listAppraisees");
    }
    
    public function confirmDeleteAppraiseesObject()
    {
        $ilTabs = $this->tabs;
        
        if (!sizeof($_POST["appr_id"])) {
            ilUtil::sendFailure($this->lng->txt("select_one"), true);
            $this->ctrl->redirect($this, "listAppraisees");
        }
        
        $ilTabs->clearTargets();
        $ilTabs->setBackTarget(
            $this->lng->txt("btn_back"),
            $this->ctrl->getLinkTarget($this, "listAppraisees")
        );
        
        $cgui = new ilConfirmationGUI();
        $cgui->setHeaderText($this->lng->txt("survey_360_sure_delete_appraises"));

        $cgui->setFormAction($this->ctrl->getFormAction($this, "deleteAppraisees"));
        $cgui->setCancel($this->lng->txt("cancel"), "listAppraisees");
        $cgui->setConfirm($this->lng->txt("confirm"), "deleteAppraisees");

        $data = $this->object->getAppraiseesData();
        
        $count = 0;
        foreach ($_POST["appr_id"] as $id) {
            if (isset($data[$id]) && !$data[$id]["closed"]) {
                $cgui->addItem("appr_id[]", $id, ilUserUtil::getNamePresentation($id));
                $count++;
            }
        }
        
        if (!$count) {
            ilUtil::sendFailure($this->lng->txt("select_one"), true);
            $this->ctrl->redirect($this, "listAppraisees");
        }

        $this->tpl->setContent($cgui->getHTML());
    }
    
    public function deleteAppraiseesObject()
    {
        if (sizeof($_POST["appr_id"])) {
            $data = $this->object->getAppraiseesData();

            foreach ($_POST["appr_id"] as $id) {
                // #11285
                if (isset($data[$id]) && !$data[$id]["closed"]) {
                    $this->object->deleteAppraisee($id);
                }
            }
            
            ilUtil::sendSuccess($this->lng->txt("settings_saved"), true);
        }
        
        $this->ctrl->redirect($this, "listAppraisees");
    }
    
    public function handleRatersAccess()
    {
        $ilAccess = $this->access;
        $ilUser = $this->user;
        
        if ($ilAccess->checkAccess("write", "", $this->ref_id)) {
            $appr_id = $_REQUEST["appr_id"];
            if (!$appr_id) {
                $this->ctrl->redirect($this, "listAppraisees");
            }
            return $appr_id;
        } elseif ($this->object->get360Mode() &&
            $this->object->get360SelfRaters() &&
            $this->object->isAppraisee($ilUser->getId()) &&
            !$this->object->isAppraiseeClosed($ilUser->getId())) {
            return $ilUser->getId();
        }
        $this->ctrl->redirect($this->parent_gui, "infoScreen");
    }
    
    public function editRatersObject()
    {
        $ilTabs = $this->tabs;
        $ilToolbar = $this->toolbar;
        $ilAccess = $this->access;
        
        $appr_id = $_REQUEST["appr_id"] = $this->handleRatersAccess();
                
        $has_write = $ilAccess->checkAccess("write", "", $this->ref_id);
        if ($has_write) {
            $ilTabs->clearTargets();
            $ilTabs->setBackTarget(
                $this->lng->txt("btn_back"),
                $this->ctrl->getLinkTarget($this, "listAppraisees")
            );
        }
        
        $this->ctrl->setParameter($this, "appr_id", $appr_id);
        $this->ctrl->setParameter($this, "rate360", 1);
        
        ilRepositorySearchGUI::fillAutoCompleteToolbar(
            $this,
            $ilToolbar,
            array(
                'auto_complete_name' => $this->lng->txt('user'),
                'submit_name' => $this->lng->txt('add'),
                'add_search' => true,
                'add_from_container' => $this->ref_id
            )
        );
        
        $this->ctrl->setParameter($this, "rate360", "");
        
        $ilToolbar->addSeparator();
        
        $ilToolbar->addButton(
            $this->lng->txt("survey_360_add_external_rater"),
            $this->ctrl->getLinkTarget($this, "addExternalRaterForm")
        );
        
        // #13320
        $url = ilLink::_getStaticLink($this->object->getRefId());
        
        $tbl = new ilSurveyAppraiseesTableGUI($this, "editRaters", true, !$this->object->isAppraiseeClosed($appr_id), $url); // #11285
        $tbl->setData($this->object->getRatersData($appr_id));
        $this->tpl->setContent($tbl->getHTML());
    }
    
    public function addExternalRaterFormObject(ilPropertyFormGUI $a_form = null)
    {
        $ilTabs = $this->tabs;
        $ilAccess = $this->access;
        
        $appr_id = $this->handleRatersAccess();
        $this->ctrl->setParameter($this, "appr_id", $appr_id);
        
        $has_write = $ilAccess->checkAccess("write", "", $this->ref_id);
        if ($has_write) {
            $ilTabs->clearTargets();
            $ilTabs->setBackTarget(
                $this->lng->txt("btn_back"),
                $this->ctrl->getLinkTarget($this, "editRaters")
            );
        }
        
        if (!$a_form) {
            $a_form = $this->initExternalRaterForm($appr_id);
        }
        
        $this->tpl->setContent($a_form->getHTML());
    }
    
    protected function initExternalRaterForm($appr_id)
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, "addExternalRater"));
        $form->setTitle($this->lng->txt("survey_360_add_external_rater") .
            ": " . ilUserUtil::getNamePresentation($appr_id));

        $email = new ilEmailInputGUI($this->lng->txt("email"), "email");
        $email->setRequired(true);
        $form->addItem($email);
        
        $lname = new ilTextInputGUI($this->lng->txt("lastname"), "lname");
        $lname->setSize(30);
        $form->addItem($lname);
        
        $fname = new ilTextInputGUI($this->lng->txt("firstname"), "fname");
        $fname->setSize(30);
        $form->addItem($fname);

        $form->addCommandButton("addExternalRater", $this->lng->txt("save"));
        $form->addCommandButton("editRaters", $this->lng->txt("cancel"));
        
        return $form;
    }
    
    public function addExternalRaterObject()
    {
        $appr_id = $_REQUEST["appr_id"];
        if (!$appr_id) {
            $this->ctrl->redirect($this, "listAppraisees");
        }
        
        $this->ctrl->setParameter($this, "appr_id", $appr_id);
        
        $form = $this->initExternalRaterForm($appr_id);
        if ($form->checkInput()) {
            $data = array(
                "email" => $form->getInput("email"),
                "lastname" => $form->getInput("lname"),
                "firstname" => $form->getInput("fname")
            );
            $anonymous_id = $this->object->createSurveyCodesForExternalData(array($data));
            $anonymous_id = array_pop($anonymous_id);
            
            $this->object->addRater($appr_id, 0, $anonymous_id);
            
            ilUtil::sendSuccess($this->lng->txt("settings_saved"), true);
            $this->ctrl->setParameter($this, "appr_id", $appr_id);
            $this->ctrl->redirect($this, "editRaters");
        }
        
        $form->setValuesByPost();
        $this->addExternalRaterFormObject($form);
    }
    
    public function addRater($a_user_ids)
    {
        $ilAccess = $this->access;
        $ilUser = $this->user;
        
        $appr_id = $this->handleRatersAccess();
        
        if (sizeof($a_user_ids)) {
            // #13319
            foreach (array_unique($a_user_ids) as $user_id) {
                if ($ilAccess->checkAccess("write", "", $this->ref_id) ||
                    $this->object->get360SelfEvaluation() ||
                    $user_id != $ilUser->getId()) {
                    if ($appr_id != $user_id) {
                        $this->object->addRater($appr_id, $user_id);
                        ilUtil::sendSuccess($this->lng->txt("settings_saved"), true);
                    } else {
                        ilUtil::sendFailure($this->lng->txt("svy_appraisses_cannot_be_raters"), true);
                    }
                }
            }
        }
        
        $this->ctrl->setParameter($this, "appr_id", $appr_id);
        $this->ctrl->redirect($this, "editRaters");
    }
    
    public function confirmDeleteRatersObject()
    {
        $ilTabs = $this->tabs;
        
        $appr_id = $this->handleRatersAccess();
        $this->ctrl->setParameter($this, "appr_id", $appr_id);
        if (!sizeof($_POST["rtr_id"])) {
            ilUtil::sendFailure($this->lng->txt("select_one"), true);
            $this->ctrl->redirect($this, "editRaters");
        }
        
        $ilTabs->clearTargets();
        $ilTabs->setBackTarget(
            $this->lng->txt("btn_back"),
            $this->ctrl->getLinkTarget($this, "editRaters")
        );
                
        $cgui = new ilConfirmationGUI();
        $cgui->setHeaderText(sprintf(
            $this->lng->txt("survey_360_sure_delete_raters"),
            ilUserUtil::getNamePresentation($appr_id)
        ));

        $cgui->setFormAction($this->ctrl->getFormAction($this, "deleteRaters"));
        $cgui->setCancel($this->lng->txt("cancel"), "editRaters");
        $cgui->setConfirm($this->lng->txt("confirm"), "deleteRaters");

        $data = $this->object->getRatersData($appr_id);
            
        foreach ($_POST["rtr_id"] as $id) {
            if (isset($data[$id])) {
                $cgui->addItem("rtr_id[]", $id, $data[$id]["lastname"] . ", " .
                    $data[$id]["firstname"] . " (" . $data[$id]["email"] . ")");
            }
        }

        $this->tpl->setContent($cgui->getHTML());
    }
    
    public function deleteRatersObject()
    {
        $appr_id = $this->handleRatersAccess();
        $this->ctrl->setParameter($this, "appr_id", $appr_id);
        
        if (sizeof($_POST["rtr_id"])) {
            $data = $this->object->getRatersData($appr_id);

            foreach ($_POST["rtr_id"] as $id) {
                if (isset($data[$id])) {
                    if (substr($id, 0, 1) == "u") {
                        $this->object->deleteRater($appr_id, substr($id, 1));
                    } else {
                        $this->object->deleteRater($appr_id, 0, substr($id, 1));
                    }
                }
            }
            
            ilUtil::sendSuccess($this->lng->txt("settings_saved"), true);
        }

        $this->ctrl->redirect($this, "editRaters");
    }
    
    public function addSelfAppraiseeObject()
    {
        $ilUser = $this->user;
        
        if ($this->object->get360SelfAppraisee() &&
            !$this->object->isAppraisee($ilUser->getId())) {
            $this->object->addAppraisee($ilUser->getId());
        }
        
        $this->ctrl->redirect($this->parent_gui, "infoScreen");
    }

    public function initMailRatersForm($appr_id, array $rec_ids)
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, "mailRatersAction"));
        $form->setTitle($this->lng->txt('compose'));
        
        $all_data = $this->object->getRatersData($appr_id);
        $rec_data = array();
        foreach ($rec_ids as $rec_id) {
            if (isset($all_data[$rec_id])) {
                $rec_data[] = $all_data[$rec_id]["lastname"] . ", " .
                    $all_data[$rec_id]["firstname"] .
                    " (" . $all_data[$rec_id]["email"] . ")";
            }
        }
        sort($rec_data);
        $rec = new ilCustomInputGUI($this->lng->txt('recipients'));
        $rec->setHTML(implode("<br />", $rec_data));
        $form->addItem($rec);

        $subject = new ilTextInputGUI($this->lng->txt('subject'), 'subject');
        $subject->setSize(50);
        $subject->setRequired(true);
        $form->addItem($subject);
        
        $existingdata = $this->object->getExternalCodeRecipients();
        $existingcolumns = array();
        if (count($existingdata)) {
            $first = array_shift($existingdata);
            foreach ($first as $key => $value) {
                if (strcmp($key, 'code') != 0 && strcmp($key, 'email') != 0 && strcmp($key, 'sent') != 0) {
                    array_push($existingcolumns, '[' . $key . ']');
                }
            }
        }

        $mailmessage_u = new ilTextAreaInputGUI($this->lng->txt('survey_360_rater_message_content_registered'), 'message_u');
        $mailmessage_u->setRequired(true);
        $mailmessage_u->setCols(80);
        $mailmessage_u->setRows(10);
        $form->addItem($mailmessage_u);
        
        $mailmessage_a = new ilTextAreaInputGUI($this->lng->txt('survey_360_rater_message_content_anonymous'), 'message_a');
        $mailmessage_a->setRequired(true);
        $mailmessage_a->setCols(80);
        $mailmessage_a->setRows(10);
        $mailmessage_a->setInfo(sprintf($this->lng->txt('message_content_info'), join(', ', $existingcolumns)));
        $form->addItem($mailmessage_a);
        
        $recf = new ilHiddenInputGUI("rtr_id");
        $recf->setValue(implode(";", $rec_ids));
        $form->addItem($recf);

        $form->addCommandButton("mailRatersAction", $this->lng->txt("send"));
        $form->addCommandButton("editRaters", $this->lng->txt("cancel"));
        
        $subject->setValue(sprintf($this->lng->txt('survey_360_rater_subject_default'), $this->object->getTitle()));
        $mailmessage_u->setValue($this->lng->txt('survey_360_rater_message_content_registered_default'));
        $mailmessage_a->setValue($this->lng->txt('survey_360_rater_message_content_anonymous_default'));
        
        return $form;
    }
    
    public function mailRatersObject(ilPropertyFormGUI $a_form = null)
    {
        $ilTabs = $this->tabs;
        
        if (!$a_form) {
            $appr_id = $this->handleRatersAccess();
            $this->ctrl->setParameter($this, "appr_id", $appr_id);
        
            if (!sizeof($_POST["rtr_id"])) {
                ilUtil::sendFailure($this->lng->txt("select_one"), true);
                $this->ctrl->redirect($this, "editRaters");
            }
        
            $a_form = $this->initMailRatersForm($appr_id, $_POST["rtr_id"]);
        }
                
        $ilTabs->clearTargets();
        $ilTabs->setBackTarget(
            $this->lng->txt("btn_back"),
            $this->ctrl->getLinkTarget($this, "editRaters")
        );
        
        $this->tpl->setContent($a_form->getHTML());
    }
    
    public function mailRatersActionObject()
    {
        $ilUser = $this->user;
        
        $appr_id = $this->handleRatersAccess();
        $this->ctrl->setParameter($this, "appr_id", $appr_id);
        
        $rec_ids = explode(";", $_POST["rtr_id"]);
        if (!sizeof($rec_ids)) {
            $this->ctrl->redirect($this, "editRaters");
        }
        
        $form = $this->initMailRatersForm($appr_id, $rec_ids);
        if ($form->checkInput()) {
            $txt_u = $form->getInput("message_u");
            $txt_a = $form->getInput("message_a");
            $subj = $form->getInput("subject");
                    
            // #12743
            $sender_id = (trim($ilUser->getEmail()))
                ? $ilUser->getId()
                : ANONYMOUS_USER_ID;
                
            $all_data = $this->object->getRatersData($appr_id);
            foreach ($rec_ids as $rec_id) {
                if (isset($all_data[$rec_id])) {
                    $user = $all_data[$rec_id];
                    
                    // anonymous
                    if (substr($rec_id, 0, 1) == "a") {
                        $mytxt = $txt_a;
                        $url = $user["href"];
                        $rcp = $user["email"];
                    }
                    // reg
                    else {
                        $mytxt = $txt_u;
                        $user["code"] = $this->lng->txt("survey_code_mail_on_demand");
                        $url = ilLink::_getStaticLink($this->object->getRefId());
                        $rcp = $user["login"]; // #15141
                    }
                    
                    $mytxt = str_replace("[lastname]", $user["lastname"], $mytxt);
                    $mytxt = str_replace("[firstname]", $user["firstname"], $mytxt);
                    $mytxt = str_replace("[url]", $url, $mytxt);
                    $mytxt = str_replace("[code]", $user["code"], $mytxt);
                    
                    $mail = new ilMail($sender_id);
                    $mail->enqueue(
                        $rcp, // to
                        "", // cc
                        "", // bcc
                        $subj, // subject
                        $mytxt, // message
                        array() // attachments
                    );
                    
                    $this->object->set360RaterSent(
                        $appr_id,
                        (substr($rec_id, 0, 1) == "a") ? 0 : (int) substr($rec_id, 1),
                        (substr($rec_id, 0, 1) == "u") ? 0 : (int) substr($rec_id, 1)
                    );
                }
            }
            
            ilUtil::sendSuccess($this->lng->txt("mail_sent"), true);
            $this->ctrl->redirect($this, "editRaters");
        }
        
        $form->setValuesByPost();
        $this->mailRatersObject($form);
    }
   
    public function confirmAppraiseeCloseObject()
    {
        $ilUser = $this->user;
        $tpl = $this->tpl;
        $ilTabs = $this->tabs;
        
        $ilTabs->clearTargets();
        $ilTabs->setBackTarget(
            $this->lng->txt("menuback"),
            $this->ctrl->getLinkTarget($this->parent_gui, "infoScreen")
        );

        if (!$this->object->isAppraisee($ilUser->getId())) {
            $this->ctrl->redirect($this->parent_gui, "infoScreen");
        }
       
        $cgui = new ilConfirmationGUI();
        $cgui->setHeaderText($this->lng->txt("survey_360_sure_appraisee_close"));

        $cgui->setFormAction($this->ctrl->getFormAction($this, "appraiseeClose"));
        $cgui->setCancel($this->lng->txt("cancel"), "confirmAppraiseeCloseCancel");
        $cgui->setConfirm($this->lng->txt("confirm"), "appraiseeClose");

        $tpl->setContent($cgui->getHTML());
    }
   
    public function confirmAppraiseeCloseCancelObject()
    {
        $this->ctrl->redirect($this->parent_gui, "infoScreen");
    }
   
    public function appraiseeCloseObject()
    {
        $ilUser = $this->user;

        if (!$this->object->isAppraisee($ilUser->getId())) {
            $this->ctrl->redirect($this->parent_gui, "infoScreen");
        }
        
        $this->object->closeAppraisee($ilUser->getId());
        ilUtil::sendSuccess($this->lng->txt("survey_360_appraisee_close_action_success"), true);
        $this->ctrl->redirect($this->parent_gui, "infoScreen");
    }
   
    public function confirmAdminAppraiseesCloseObject()
    {
        $tpl = $this->tpl;
       
        $this->handleWriteAccess();
       
        $appr_ids = $_POST["appr_id"];

        if (!sizeof($appr_ids)) {
            ilUtil::sendFailure($this->lng->txt("select_one"), true);
            $this->ctrl->redirect($this, "listAppraisees");
        }

        $cgui = new ilConfirmationGUI();
        $cgui->setHeaderText($this->lng->txt("survey_360_sure_appraisee_close_admin"));

        $cgui->setFormAction($this->ctrl->getFormAction($this, "adminAppraiseesClose"));
        $cgui->setCancel($this->lng->txt("cancel"), "listAppraisees");
        $cgui->setConfirm($this->lng->txt("confirm"), "adminAppraiseesClose");

        foreach ($appr_ids as $appr_id) {
            $cgui->addItem("appr_id[]", $appr_id, ilUserUtil::getNamePresentation($appr_id));
        }

        $tpl->setContent($cgui->getHTML());
    }
   
    public function adminAppraiseesCloseObject()
    {
        $this->handleWriteAccess();
        
        $appr_ids = $_POST["appr_id"];
        
        if (!sizeof($appr_ids)) {
            ilUtil::sendFailure($this->lng->txt("select_one"), true);
            $this->ctrl->redirect($this, "listAppraisees");
        }
        
        $appr_data = $this->object->getAppraiseesData();
        foreach ($appr_ids as $appr_id) {
            if (isset($appr_data[$appr_id]) && !$appr_data[$appr_id]["closed"]) {
                $this->object->closeAppraisee($appr_id);
            }
        }
        
        ilUtil::sendSuccess($this->lng->txt("survey_360_appraisee_close_action_success_admin"), true);
        $this->ctrl->redirect($this, "listAppraisees");
    }
   
    protected function listParticipantsObject()
    {
        $ilToolbar = $this->toolbar;

        if (!$this->isAnonymousListActive()) {
            $this->ctrl->redirect($this, "maintenance");
        }

        $this->handleWriteAccess();
        $this->setParticipantSubTabs("anon_participants");

        $button = ilLinkButton::getInstance();
        $button->setCaption("print");
        $button->setOnClick("window.print(); return false;");
        $button->setOmitPreventDoubleSubmission(true);
        $ilToolbar->addButtonInstance($button);

        $tbl = new ilSurveyParticipantsTableGUI($this, "listParticipants", $this->object);
        $this->tpl->setContent($tbl->getHTML());
    }

    public function getObject()
    {
        return $this->object;
    }

    /**
     * Invite users
     *
     * @param int[]
     */
    public function inviteUsers($user_ids)
    {
        $lng = $this->lng;
        $ctrl = $this->ctrl;

        if (is_array($user_ids)) {
            foreach ($user_ids as $user_id) {
                $this->invitation_manager->add($this->object->getSurveyId(), (int) $user_id);
            }
        }
        ilUtil::sendSuccess($lng->txt("svy_users_invited"), true);
        $ctrl->redirect($this, "maintenance");
    }
}
