<?php

/* Copyright (c) 1998-2021 ILIAS open source, GPLv3, see LICENSE */

/**
 * This class represents a property form user interface
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * @ilCtrl_Calls ilPropertyFormGUI: ilFormPropertyDispatchGUI
 */
class ilPropertyFormGUI extends ilFormGUI
{
    /**
     * @var ilLanguage
     */
    protected $lng;

    /**
     * @var ilCtrl
     */
    protected $ctrl;

    /**
     * @var ilTemplate
     */
    protected $tpl;

    /**
     * @var ilObjUser
     */
    protected $user;

    /**
     * @var ilSetting
     */
    protected $settings;

    private $buttons = array();
    private $items = array();
    protected $mode = "std";
    protected $check_input_called = false;
    protected $disable_standard_message = false;
    protected $top_anchor = "il_form_top";
    protected $title = '';
    protected $titleicon = false;
    protected $description = "";
    protected $tbl_width = false;
    protected $show_top_buttons = true;
    protected $hide_labels = false;

    protected $force_top_buttons = false;

    /**
    * Constructor
    *
    * @param
    */
    public function __construct()
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();

        $this->user = null;
        if (isset($DIC["ilUser"])) {
            $this->user = $DIC["ilUser"];
        }

        $this->settings = null;
        if (isset($DIC["ilSetting"])) {
            $this->settings = $DIC["ilSetting"];
        }

        $lng = $DIC->language();
        
        $lng->loadLanguageModule("form");

        // avoid double submission
        $this->setPreventDoubleSubmission(true);

        // do it as early as possible
        $this->rebuildUploadedFiles();
    }

    /**
    * Execute command.
    */
    public function executeCommand()
    {
        $ilCtrl = $this->ctrl;
        
        $next_class = $ilCtrl->getNextClass($this);

        switch ($next_class) {
            case 'ilformpropertydispatchgui':
                $ilCtrl->saveParameter($this, 'postvar');
                $form_prop_dispatch = new ilFormPropertyDispatchGUI();
                $item = $this->getItemByPostVar($_REQUEST["postvar"]);
                $form_prop_dispatch->setItem($item);
                return $ilCtrl->forwardCommand($form_prop_dispatch);
                break;

        }
        return false;
    }

    /**
     * Set table width
     *
     * @access public
     * @param string table width
     *
     */
    final public function setTableWidth($a_width)
    {
        $this->tbl_width = $a_width;
    }
    
    /**
     * get table width
     *
     * @access public
     *
     */
    final public function getTableWidth()
    {
        return $this->tbl_width;
    }

    /**
    * Set Mode ('std', 'subform').
    *
    * @param	string	$a_mode	Mode ('std', 'subform')
    */
    public function setMode($a_mode)
    {
        $this->mode = $a_mode;
    }

    /**
    * Get Mode ('std', 'subform').
    *
    * @return	string	Mode ('std', 'subform')
    */
    public function getMode()
    {
        return $this->mode;
    }

    /**
    * Set Title.
    *
    * @param	string	$a_title	Title
    */
    public function setTitle($a_title)
    {
        $this->title = $a_title;
    }

    /**
    * Get Title.
    *
    * @return	string	Title
    */
    public function getTitle()
    {
        return $this->title;
    }

    /**
    * Set TitleIcon.
    *
    * @param	string	$a_titleicon	TitleIcon
    */
    public function setTitleIcon($a_titleicon)
    {
        $this->titleicon = $a_titleicon;
    }

    /**
    * Get TitleIcon.
    *
    * @return	string	TitleIcon
    */
    public function getTitleIcon()
    {
        return $this->titleicon;
    }

    /**
    * Set description
    *
    * @param	string	description
    */
    public function setDescription($a_val)
    {
        $this->description = $a_val;
    }
    
    /**
    * Get description
    *
    * @return	string	description
    */
    public function getDescription()
    {
        return $this->description;
    }
    
    /**
     * Set top anchor
     *
     * @param	string	top anchor
     * @deprecated
     */
    public function setTopAnchor($a_val)
    {
        $this->top_anchor = $a_val;
    }
    
    /**
    * Get top anchor
    *
    * @return	string	top anchor
    */
    public function getTopAnchor()
    {
        return $this->top_anchor;
    }

    /**
     * Get show top buttons
     */
    public function setShowTopButtons($a_val)
    {
        $this->show_top_buttons = $a_val;
    }

    /**
     * Set show top buttons
     */
    public function getShowTopButtons()
    {
        return $this->show_top_buttons;
    }
    
    /**
     * Set force top buttons
     *
     * @param bool $a_val force top buttons
     */
    public function setForceTopButtons($a_val)
    {
        $this->force_top_buttons = $a_val;
    }
    
    /**
     * Get force top buttons
     *
     * @return bool force top buttons
     */
    public function getForceTopButtons()
    {
        return $this->force_top_buttons;
    }
    
    
    /**
    * Add Item (Property, SectionHeader).
    *
    * @param	object	$a_property		Item object
    */
    public function addItem($a_item)
    {
        $a_item->setParentForm($this);
        return $this->items[] = $a_item;
    }

    /**
    * Remove Item.
    *
    * @param	string	$a_postvar		Post Var
    */
    public function removeItemByPostVar($a_post_var, $a_remove_unused_headers = false)
    {
        foreach ($this->items as $key => $item) {
            if (method_exists($item, "getPostVar") && $item->getPostVar() == $a_post_var) {
                unset($this->items[$key]);
            }
        }

        // remove section headers if they do not contain any items anymore
        if ($a_remove_unused_headers) {
            $unset_keys = array();
            $last_item = null;
            $last_key = null;
            foreach ($this->items as $key => $item) {
                if ($item instanceof ilFormSectionHeaderGUI && $last_item instanceof ilFormSectionHeaderGUI) {
                    $unset_keys[] = $last_key;
                }
                $last_item = $item;
                $last_key = $key;
            }
            if ($last_item instanceof ilFormSectionHeaderGUI) {
                $unset_keys[] = $last_key;
            }
            foreach ($unset_keys as $key) {
                unset($this->items[$key]);
            }
        }
    }

    /**
     * Get Item by POST variable.
     * @param string $a_postvar Post Var
     * @return false|ilFormPropertyGUI
     */
    public function getItemByPostVar($a_post_var)
    {
        foreach ($this->items as $key => $item) {
            if ($item->getType() != "section_header") {
                //if ($item->getPostVar() == $a_post_var)
                $ret = $item->getItemByPostVar($a_post_var);
                if (is_object($ret)) {
                    return $ret;
                }
            }
        }
        
        return false;
    }

    /**
    * Set Items
    *
    * @param	array	$a_items	array of item objects
    */
    public function setItems($a_items)
    {
        $this->items = $a_items;
    }

    /**
    * Get Items
    *
    * @return	array	array of item objects
    */
    public function getItems()
    {
        return $this->items;
    }
    
    /**
     * returns a flat array of all input items including
     * the possibly existing subitems recursively
     *
     * @return array
     */
    public function getInputItemsRecursive()
    {
        $inputItems = array();
        
        foreach ($this->items as $item) {
            if ($item->getType() == 'section_header') {
                continue;
            }
            
            $inputItems[] = $item;
            
            if ($item instanceof ilSubEnabledFormPropertyGUI) {
                $inputItems = array_merge($inputItems, $item->getSubInputItemsRecursive());
            }
        }
        
        return $inputItems;
    }

    /**
    * Set disable standard message
    *
    * @param	boolean		disable standard message
    */
    public function setDisableStandardMessage($a_val)
    {
        $this->disable_standard_message = $a_val;
    }
    
    /**
    * Get disable standard message
    *
    * @return	boolean		disable standard message
    */
    public function getDisableStandardMessage()
    {
        return $this->disable_standard_message;
    }
    
    /**
    * Get a value indicating whether the labels should be hidden or not.
    *
    * @return	boolean		true, to hide the labels; otherwise, false.
    */
    public function getHideLabels()
    {
        return $this->hide_labels;
    }
    
    /**
    * Set a value indicating whether the labels should be hidden or not.
    *
    * @param	boolean	$a_value	Indicates whether the labels should be hidden.
    */
    public function setHideLabels($a_value = true)
    {
        $this->hide_labels = $a_value;
    }
    
    /**
    * Set form values from an array
    *
    * @param	array	$a_values	Value array (key is post variable name, value is value)
    */
    public function setValuesByArray($a_values, $a_restrict_to_value_keys = false)
    {
        foreach ($this->items as $item) {
            if (!($a_restrict_to_value_keys) ||
                in_array($item->getPostVar(), array_keys($a_values))) {
                $item->setValueByArray($a_values);
            }
        }
    }

    /**
    * Set form values from POST values
    *
    */
    public function setValuesByPost()
    {
        foreach ($this->items as $item) {
            $item->setValueByArray($_POST);
        }
    }
    
    /**
    * Check Post Input. This method also strips slashes and html from
    * input and sets the alert texts for the items, if the input was not ok.
    *
    * @return	boolean		ok true/false
    */
    public function checkInput()
    {
        global $DIC;
        
        if ($this->check_input_called) {
            die("Error: ilPropertyFormGUI->checkInput() called twice.");
        }
        
        $ok = true;
        foreach ($this->items as $item) {
            $item_ok = $item->checkInput();
            if (!$item_ok) {
                $ok = false;
            }
        }
        
        // check if POST is missint completely (if post_max_size exceeded)
        if (count($this->items) > 0 && !is_array($_POST)) {
            $ok = false;
        }
        
        $this->check_input_called = true;
        
        
        
        // try to keep uploads for another try
        if (!$ok && isset($_POST["ilfilehash"]) && $_POST["ilfilehash"] && count($_FILES)) {
            $hash = $_POST["ilfilehash"];

            foreach ($_FILES as $field => $data) {
                // only try to keep files that are ok
                // see 25484: Wrong error handling when uploading icon instead of tile
                $item = $this->getItemByPostVar($field);
                if (is_bool($item) || !$item->checkInput()) {
                    continue;
                }
                // we support up to 2 nesting levels (see test/assesment)
                if (is_array($data["tmp_name"])) {
                    foreach ($data["tmp_name"] as $idx => $upload) {
                        if (is_array($upload)) {
                            foreach ($upload as $idx2 => $file) {
                                if ($file && is_uploaded_file($file)) {
                                    $file_name = $data["name"][$idx][$idx2];
                                    $file_type = $data["type"][$idx][$idx2];
                                    $this->keepFileUpload($hash, $field, $file, $file_name, $file_type, $idx, $idx2);
                                }
                            }
                        } elseif ($upload && is_uploaded_file($upload)) {
                            $file_name = $data["name"][$idx];
                            $file_type = $data["type"][$idx];
                            $this->keepFileUpload($hash, $field, $upload, $file_name, $file_type, $idx);
                        }
                    }
                } else {
                    $this->keepFileUpload($hash, $field, $data["tmp_name"], $data["name"], $data["type"]);
                }
            }
        }
        $http = $DIC->http();
        $txt = $DIC->language()->txt("form_input_not_valid");
        switch ($http->request()->getHeaderLine('Accept')) {
            // When JS asks for a valid JSON-Response, we send the success and message as JSON
            case 'application/json':
                $stream = \ILIAS\Filesystem\Stream\Streams::ofString(json_encode([
                    'success' => $ok,
                    'message' => $txt,
                ]));
                $http->saveResponse($http->response()->withBody($stream));

                return $ok;

            // Otherwise we send it using ilUtil and it will be rendered in the Template
            default:

                if (!$ok && !$this->getDisableStandardMessage()) {
                    ilUtil::sendFailure($txt);
                }

                return $ok;
        }
    }
    
    /**
     *
     * Returns the value of a HTTP-POST variable, identified by the passed id
     *
     * @param	string	The key used for value determination
     * @param	boolean	A flag whether the form input has to be validated before calling this method
     * @return	string	The value of a HTTP-POST variable, identified by the passed id
     * @access	public
     *
     */
    public function getInput($a_post_var, $ensureValidation = true)
    {
        // this check ensures, that checkInput has been called (incl. stripSlashes())
        if (!$this->check_input_called && $ensureValidation) {
            die("Error: ilPropertyFormGUI->getInput() called without calling checkInput() first.");
        }
        
        return $_POST[$a_post_var] ?? '';
    }
    
    /**
    * Add a custom property.
    *
    * @param	string		Title
    * @param	string		HTML.
    * @param	string		Info text.
    * @param	string		Alert text.
    * @param	boolean		Required field. (Default false)
    */
    public function addCustomProperty(
        $a_title,
        $a_html,
        $a_info = "",
        $a_alert = "",
        $a_required = false
    ) {
        $this->properties[] = array("type" => "custom",
            "title" => $a_title,
            "html" => $a_html,
            "info" => $a_info);
    }

    /**
    * Add Command button
    *
    * @param	string	Command
    * @param	string	Text
    */
    public function addCommandButton($a_cmd, $a_text, $a_id = "")
    {
        $this->buttons[] = array("cmd" => $a_cmd, "text" => $a_text, "id" => $a_id);
    }


    /**
     * Return all Command buttons
     *
     * @return array
     */
    public function getCommandButtons()
    {
        return $this->buttons;
    }

    /**
    * Remove all command buttons
    */
    public function clearCommandButtons()
    {
        $this->buttons = array();
    }

    /**
    * Get Content.
    */
    public function getContent()
    {
        global $DIC;
        $lng = $this->lng;
        $tpl = $DIC["tpl"];
        $ilSetting = $this->settings;
    
        ilYuiUtil::initEvent();
        ilYuiUtil::initDom();

        $tpl->addJavaScript("./Services/JavaScript/js/Basic.js");
        $tpl->addJavaScript("Services/Form/js/Form.js");

        $this->tpl = new ilTemplate("tpl.property_form.html", true, true, "Services/Form");

        // check if form has not title and first item is a section header
        // -> use section header for title and remove section header
        // -> command buttons are presented on top
        $fi = $this->items[0] ?? null;
        if ($this->getMode() == "std" &&
            $this->getTitle() == "" &&
            is_object($fi) && $fi->getType() == "section_header"
            ) {
            $this->setTitle($fi->getTitle());
            unset($this->items[0]);
        }
        
        
        // title icon
        if ($this->getTitleIcon() != "" && @is_file($this->getTitleIcon())) {
            $this->tpl->setCurrentBlock("title_icon");
            $this->tpl->setVariable("IMG_ICON", $this->getTitleIcon());
            $this->tpl->parseCurrentBlock();
        }

        // title
        if ($this->getTitle() != "") {
            // commands on top
            if (count($this->buttons) > 0 && $this->getShowTopButtons() && (count($this->items) > 2 || $this->force_top_buttons)) {
                // command buttons
                foreach ($this->buttons as $button) {
                    $this->tpl->setCurrentBlock("cmd2");
                    $this->tpl->setVariable("CMD", $button["cmd"]);
                    $this->tpl->setVariable("CMD_TXT", $button["text"]);
                    if ($button["id"] != "") {
                        $this->tpl->setVariable("CMD2_ID", " id='" . $button["id"] . "_top'");
                    }
                    $this->tpl->parseCurrentBlock();
                }
                $this->tpl->setCurrentBlock("commands2");
                $this->tpl->parseCurrentBlock();
            }

            if (is_object($ilSetting)) {
                if ($ilSetting->get('char_selector_availability') > 0) {
                    if (ilCharSelectorGUI::_isAllowed()) {
                        $char_selector = ilCharSelectorGUI::_getCurrentGUI();
                        if ($char_selector->getConfig()->getAvailability() == ilCharSelectorConfig::ENABLED) {
                            $char_selector->addToPage();
                            $this->tpl->TouchBlock('char_selector');
                        }
                    }
                }
            }
            
            $this->tpl->setCurrentBlock("header");
            $this->tpl->setVariable("TXT_TITLE", $this->getTitle());
            //$this->tpl->setVariable("LABEL", $this->getTopAnchor());
            $this->tpl->setVariable("TXT_DESCRIPTION", $this->getDescription());
            $this->tpl->parseCurrentBlock();
        }
        $this->tpl->touchBlock("item");
        
        // properties
        $this->required_text = false;
        foreach ($this->items as $item) {
            if ($item->getType() != "hidden") {
                $this->insertItem($item);
            }
        }

        // required
        if ($this->required_text && $this->getMode() == "std") {
            $this->tpl->setCurrentBlock("required_text");
            $this->tpl->setVariable("TXT_REQUIRED", $lng->txt("required_field"));
            $this->tpl->parseCurrentBlock();
        }
        
        // command buttons
        foreach ($this->buttons as $button) {
            $this->tpl->setCurrentBlock("cmd");
            $this->tpl->setVariable("CMD", $button["cmd"]);
            $this->tpl->setVariable("CMD_TXT", $button["text"]);

            if ($button["id"] != "") {
                $this->tpl->setVariable("CMD_ID", " id='" . $button["id"] . "'");
            }

            $this->tpl->parseCurrentBlock();
        }
        
        // #18808
        if ($this->getMode() != "subform") {
            // try to keep uploads even if checking input fails
            if ($this->getMultipart()) {
                $hash = $_POST["ilfilehash"] ?? null;
                if (!$hash) {
                    $hash = md5(uniqid(mt_rand(), true));
                }
                $fhash = new ilHiddenInputGUI("ilfilehash");
                $fhash->setValue($hash);
                $this->addItem($fhash);
            }
        }
        
        // hidden properties
        $hidden_fields = false;
        foreach ($this->items as $item) {
            if ($item->getType() == "hidden") {
                $item->insert($this->tpl);
                $hidden_fields = true;
            }
        }
        
        if ($this->required_text || count($this->buttons) > 0 || $hidden_fields) {
            $this->tpl->setCurrentBlock("commands");
            $this->tpl->parseCurrentBlock();
        }

        
        if ($this->getMode() == "subform") {
            $this->tpl->touchBlock("sub_table");
        } else {
            $this->tpl->touchBlock("std_table");
            $this->tpl->setVariable('STD_TABLE_WIDTH', $this->getTableWidth());
        }
        
        return $this->tpl->get();
    }
    
    protected function hideRequired($a_type)
    {
        // #15818
        return in_array($a_type, array("non_editable_value"));
    }

    public function insertItem($item, $a_sub_item = false)
    {
        global $DIC;
        $tpl = $DIC["tpl"];
        ;
        $lng = $this->lng;
        
        
        $cfg = array();
        
        //if(method_exists($item, "getMulti") && $item->getMulti())
        if ($item instanceof ilMultiValuesItem && $item->getMulti()) {
            $tpl->addJavascript("./Services/Form/js/ServiceFormMulti.js");
            
            $this->tpl->setCurrentBlock("multi_in");
            $this->tpl->setVariable("ID", $item->getFieldId());
            $this->tpl->parseCurrentBlock();

            $this->tpl->touchBlock("multi_out");

            
            // add hidden item to enable preset multi items
            // not used yet, should replace hidden field stuff
            $multi_values = $item->getMultiValues();
            if (is_array($multi_values) && sizeof($multi_values) > 1) {
                $multi_value = new ilHiddenInputGUI("ilMultiValues~" . $item->getPostVar());
                $multi_value->setValue(implode("~", $multi_values));
                $this->addItem($multi_value);
            }
            $cfg["multi_values"] = $multi_values;
        }
        
        $item->insert($this->tpl);

        if ($item->getType() == "file" || $item->getType() == "image_file") {
            $this->setMultipart(true);
        }

        if ($item->getType() != "section_header") {
            $cfg["id"] = $item->getFieldId();
            
            // info text
            if ($item->getInfo() != "") {
                $this->tpl->setCurrentBlock("description");
                $this->tpl->setVariable(
                    "PROPERTY_DESCRIPTION",
                    $item->getInfo()
                );
                $this->tpl->parseCurrentBlock();
            }

            if ($this->getMode() == "subform") {
                // required
                if (!$this->hideRequired($item->getType())) {
                    if ($item->getRequired()) {
                        $this->tpl->touchBlock("sub_required");
                        $this->required_text = true;
                    }
                }
                
                // hidden title (for accessibility, e.g. file upload)
                if ($item->getHiddenTitle() != "") {
                    $this->tpl->setCurrentBlock("sub_hid_title");
                    $this->tpl->setVariable(
                        "SPHID_TITLE",
                        $item->getHiddenTitle()
                    );
                    $this->tpl->parseCurrentBlock();
                }
                
                $this->tpl->setCurrentBlock("sub_prop_start");
                $this->tpl->setVariable("PROPERTY_TITLE", $item->getTitle());
                $this->tpl->setVariable("PROPERTY_CLASS", "il_" . $item->getType());
                if ($item->getType() != "non_editable_value" && $item->getFormLabelFor() != "") {
                    $this->tpl->setVariable("FOR_ID", ' for="' . $item->getFormLabelFor() . '" ');
                }
                $this->tpl->setVariable("LAB_ID", $item->getFieldId());
                $this->tpl->parseCurrentBlock();
            } else {
                // required
                if (!$this->hideRequired($item->getType())) {
                    if ($item->getRequired()) {
                        $this->tpl->touchBlock("required");
                        $this->required_text = true;
                    }
                }
                
                // hidden title (for accessibility, e.g. file upload)
                if ($item->getHiddenTitle() != "") {
                    $this->tpl->setCurrentBlock("std_hid_title");
                    $this->tpl->setVariable(
                        "PHID_TITLE",
                        $item->getHiddenTitle()
                    );
                    $this->tpl->parseCurrentBlock();
                }
                
                $this->tpl->setCurrentBlock("std_prop_start");
                $this->tpl->setVariable("PROPERTY_TITLE", $item->getTitle());
                if ($item->getType() != "non_editable_value" && $item->getFormLabelFor() != "") {
                    $this->tpl->setVariable("FOR_ID", ' for="' . $item->getFormLabelFor() . '" ');
                }
                $this->tpl->setVariable("LAB_ID", $item->getFieldId());
                if ($this->getHideLabels()) {
                    $this->tpl->setVariable("HIDE_LABELS_STYLE", " ilFormOptionHidden");
                }
                $this->tpl->parseCurrentBlock();
            }
            
            // alert
            if ($item->getType() != "non_editable_value" && $item->getAlert() != "") {
                $this->tpl->setCurrentBlock("alert");
                $this->tpl->setVariable(
                    "IMG_ALERT",
                    ilUtil::getImagePath("icon_alert.svg")
                );
                $this->tpl->setVariable(
                    "ALT_ALERT",
                    $lng->txt("alert")
                );
                $this->tpl->setVariable(
                    "TXT_ALERT",
                    $item->getAlert()
                );
                $this->tpl->parseCurrentBlock();
            }
            
            // subitems
            $sf = null;
            if ($item->getType() != "non_editable_value" or 1) {
                $sf = $item->getSubForm();
                if ($item->hideSubForm() && is_object($sf)) {
                    $this->tpl->setCurrentBlock("sub_form_hide");
                    $this->tpl->setVariable("DSFID", $item->getFieldId());
                    $this->tpl->parseCurrentBlock();
                }
            }
            

            $sf_content = "";
            if (is_object($sf)) {
                $sf_content = $sf->getContent();
                if ($sf->getMultipart()) {
                    $this->setMultipart(true);
                }
                $this->tpl->setCurrentBlock("sub_form");
                $this->tpl->setVariable("PROP_SUB_FORM", $sf_content);
                $this->tpl->setVariable("SFID", $item->getFieldId());
                $this->tpl->parseCurrentBlock();
            }

            $this->tpl->setCurrentBlock("prop");
            /* not used yet
            $this->tpl->setVariable("ID", $item->getFieldId());
            $this->tpl->setVariable("CFG", ilJsonUtil::encode($cfg));*/
            $this->tpl->parseCurrentBlock();
        }
        
        
        $this->tpl->touchBlock("item");
    }
    
    public function getHTML()
    {
        $html = parent::getHTML();
        
        // #13531 - get content that has to reside outside of the parent form tag, e.g. panels/layers
        foreach ($this->items as $item) {
            // #13536 - ilFormSectionHeaderGUI does NOT extend ilFormPropertyGUI ?!
            if (method_exists($item, "getContentOutsideFormTag")) {
                $outside = $item->getContentOutsideFormTag();
                if ($outside) {
                    $html .= $outside;
                }
            }
        }
        
        return $html;
    }
    
    
    //
    // UPLOAD HANDLING
    //
    
    /**
     * Import upload into temp directory
     *
     * @param string $a_hash unique form hash
     * @param string $a_field form field
     * @param string $a_tmp_name temp file name
     * @param string $a_name original file name
     * @param string $a_type file mime type
     * @param mixed $a_index form field index (if array)
     * @param mixed $a_sub_index form field subindex (if array)
     * @return bool
     */
    protected function keepFileUpload($a_hash, $a_field, $a_tmp_name, $a_name, $a_type, $a_index = null, $a_sub_index = null)
    {
        if (trim($a_tmp_name) == "") {
            return;
        }

        $a_name = ilUtil::getAsciiFileName($a_name);
        
        $tmp_file_name = implode("~~", array(session_id(),
            $a_hash,
            $a_field,
            $a_index,
            $a_sub_index,
            str_replace("/", "~~", $a_type),
            str_replace("~~", "_", $a_name)));
        
        // make sure temp directory exists
        $temp_path = ilUtil::getDataDir() . "/temp";
        if (!is_dir($temp_path)) {
            ilUtil::createDirectory($temp_path);
        }

        ilUtil::moveUploadedFile($a_tmp_name, $tmp_file_name, $temp_path . "/" . $tmp_file_name);

        /** @var ilFileInputGUI $file_input */
        $file_input = $this->getItemByPostVar($a_field);
        $file_input->setPending($a_name);
    }
    
    /**
     * Get file upload data
     *
     * @param string $a_field form field
     * @param mixed $a_index form field index (if array)
     * @param mixed $a_sub_index form field subindex (if array)
     * @return array (tmp_name, name, type, error, size, is_upload)
     */
    public function getFileUpload($a_field, $a_index = null, $a_sub_index = null)
    {
        $res = array();
        if ($a_index) {
            if ($_FILES[$a_field]["tmp_name"][$a_index][$a_sub_index]) {
                $res = array(
                    "tmp_name" => $_FILES[$a_field]["tmp_name"][$a_index][$a_sub_index],
                    "name" => $_FILES[$a_field]["name"][$a_index][$a_sub_index],
                    "type" => $_FILES[$a_field]["type"][$a_index][$a_sub_index],
                    "error" => $_FILES[$a_field]["error"][$a_index][$a_sub_index],
                    "size" => $_FILES[$a_field]["size"][$a_index][$a_sub_index],
                    "is_upload" => true
                );
            }
        } elseif ($a_sub_index) {
            if ($_FILES[$a_field]["tmp_name"][$a_index]) {
                $res = array(
                    "tmp_name" => $_FILES[$a_field]["tmp_name"][$a_index],
                    "name" => $_FILES[$a_field]["name"][$a_index],
                    "type" => $_FILES[$a_field]["type"][$a_index],
                    "error" => $_FILES[$a_field]["error"][$a_index],
                    "size" => $_FILES[$a_field]["size"][$a_index],
                    "is_upload" => true
                );
            }
        } else {
            if ($_FILES[$a_field]["tmp_name"]) {
                $res = array(
                    "tmp_name" => $_FILES[$a_field]["tmp_name"],
                    "name" => $_FILES[$a_field]["name"],
                    "type" => $_FILES[$a_field]["type"],
                    "error" => $_FILES[$a_field]["error"],
                    "size" => $_FILES[$a_field]["size"],
                    "is_upload" => true
                );
            }
        }
        return $res;
    }
    
    /**
     * Was any file uploaded?
     *
     * @param string $a_field form field
     * @param mixed $a_index form field index (if array)
     * @param mixed $a_sub_index form field subindex (if array)
     * @return bool
     */
    public function hasFileUpload($a_field, $a_index = null, $a_sub_index = null)
    {
        $data = $this->getFileUpload($a_field, $a_index, $a_sub_index);
        return (bool) $data["tmp_name"];
    }
    
    /**
     * Move upload to target directory
     *
     * @param string $a_target_directory target directory (without filename!)
     * @param string $a_field form field
     * @param string $a_target_name target file name (if different from uploaded file)
     * @param mixed $a_index form field index (if array)
     * @param mixed $a_sub_index form field subindex (if array)
     * @return string target file name incl. path
     */
    public function moveFileUpload($a_target_directory, $a_field, $a_target_name = null, $a_index = null, $a_sub_index = null)
    {
        if (!is_dir($a_target_directory)) {
            return;
        }
        
        $data = $this->getFileUpload($a_field, $a_index, $a_sub_index);
        if ($data["tmp_name"] && file_exists($data["tmp_name"])) {
            if ($a_target_name) {
                $data["name"] = $a_target_name;
            }
            
            $target_file = $a_target_directory . "/" . $data["name"];
            $target_file = str_replace("//", "/", $target_file);
            
            if ($data["is_upload"]) {
                if (!ilUtil::moveUploadedFile($data["tmp_name"], $data["name"], $target_file)) {
                    return;
                }
            } else {
                if (!rename($data["tmp_name"], $target_file)) {
                    return;
                }
            }
            
            return $target_file;
        }
    }
    
    /**
     * try to rebuild files
     */
    protected function rebuildUploadedFiles()
    {
        if (isset($_POST["ilfilehash"]) && $_POST["ilfilehash"]) {
            $temp_path = ilUtil::getDataDir() . "/temp";
            if (is_dir($temp_path)) {
                $reload = array();
                
                $temp_files = glob($temp_path . "/" . session_id() . "~~" . $_POST["ilfilehash"] . "~~*");
                if (is_array($temp_files)) {
                    foreach ($temp_files as $full_file) {
                        $file = explode("~~", basename($full_file));
                        $field = $file[2];
                        $idx = $file[3];
                        $idx2 = $file[4];
                        $type = $file[5] . "/" . $file[6];
                        $name = $file[7];

                        if ($idx2 != "") {
                            if (!$_FILES[$field]["tmp_name"][$idx][$idx2]) {
                                $_FILES[$field]["tmp_name"][$idx][$idx2] = $full_file;
                                $_FILES[$field]["name"][$idx][$idx2] = $name;
                                $_FILES[$field]["type"][$idx][$idx2] = $type;
                                $_FILES[$field]["error"][$idx][$idx2] = 0;
                                $_FILES[$field]["size"][$idx][$idx2] = filesize($full_file);
                            }
                        } elseif ($idx != "") {
                            if (!$_FILES[$field]["tmp_name"][$idx]) {
                                $_FILES[$field]["tmp_name"][$idx] = $full_file;
                                $_FILES[$field]["name"][$idx] = $name;
                                $_FILES[$field]["type"][$idx] = $type;
                                $_FILES[$field]["error"][$idx] = 0;
                                $_FILES[$field]["size"][$idx] = filesize($full_file);
                            }
                        } else {
                            if (!$_FILES[$field]["tmp_name"]) {
                                $_FILES[$field]["tmp_name"] = $full_file;
                                $_FILES[$field]["name"] = $name;
                                $_FILES[$field]["type"] = $type;
                                $_FILES[$field]["error"] = 0;
                                $_FILES[$field]["size"] = filesize($full_file);
                            }
                        }
                    }
                }
            }
        }
    }
}
