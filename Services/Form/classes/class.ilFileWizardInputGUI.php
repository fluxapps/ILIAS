<?php

/* Copyright (c) 1998-2021 ILIAS open source, GPLv3, see LICENSE */

/**
 * This class represents a file wizard property in a property form.
 *
 * @author Helmut Schottmüller <ilias@aurealis.de>
 */
class ilFileWizardInputGUI extends ilFileInputGUI
{
    /**
     * @var ilLanguage
     */
    protected $lng;

    /**
     * @var ilTemplate
     */
    protected $tpl;

    protected $filenames = array();
    protected $allowMove = false;
    protected $imagepath_web = "";
    
    /**
    * Constructor
    *
    * @param	string	$a_title	Title
    * @param	string	$a_postvar	Post Variable
    */
    public function __construct($a_title = "", $a_postvar = "")
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->lng->loadLanguageModule("form");
        $this->tpl = $DIC["tpl"];
        parent::__construct($a_title, $a_postvar);
    }

    /**
    * Set the web image path
    *
    * @param string $a_path Path
    */
    public function setImagePathWeb($a_path)
    {
        $this->imagepath_web = $a_path;
    }
    
    /**
    * Get the web image path
    *
    * @return string Path
    */
    public function getImagePathWeb()
    {
        return $this->imagepath_web;
    }

    /**
    * Set filenames
    *
    * @param	array	$a_value	Value
    */
    public function setFilenames($a_filenames)
    {
        $this->filenames = $a_filenames;
    }

    /**
    * Get filenames
    *
    * @return	array	filenames
    */
    public function getFilenames()
    {
        return $this->filenames;
    }

    /**
    * Set allow move
    *
    * @param	boolean	$a_allow_move Allow move
    */
    public function setAllowMove($a_allow_move)
    {
        $this->allowMove = $a_allow_move;
    }

    /**
    * Get allow move
    *
    * @return	boolean	Allow move
    */
    public function getAllowMove()
    {
        return $this->allowMove;
    }

    /**
    * Check input, strip slashes etc. set alert, if input is not ok.
    *
    * @return	boolean		Input ok, true/false
    */
    public function checkInput()
    {
        $lng = $this->lng;
        
        // see ilFileInputGUI
        // if no information is received, something went wrong
        // this is e.g. the case, if the post_max_size has been exceeded
        if (!is_array($_FILES[$this->getPostVar()])) {
            $this->setAlert($lng->txt("form_msg_file_size_exceeds"));
            return false;
        }
        
        $pictures = $_FILES[$this->getPostVar()];
        $uploadcheck = true;
        if (is_array($pictures)) {
            foreach ($pictures['name'] as $index => $name) {
                // remove trailing '/'
                $name = rtrim($name, '/');

                $filename = $name;
                $filename_arr = pathinfo($name);
                $suffix = $filename_arr["extension"];
                $temp_name = $pictures["tmp_name"][$index];
                $error = $pictures["error"][$index];

                $_FILES[$this->getPostVar()]["name"][$index] = ilStr::normalizeUtf8String($_FILES[$this->getPostVar()]["name"][$index]);


                // error handling
                if ($error > 0) {
                    switch ($error) {
                        case UPLOAD_ERR_INI_SIZE:
                            $this->setAlert($lng->txt("form_msg_file_size_exceeds"));
                            $uploadcheck = false;
                            break;

                        case UPLOAD_ERR_FORM_SIZE:
                            $this->setAlert($lng->txt("form_msg_file_size_exceeds"));
                            $uploadcheck = false;
                            break;

                        case UPLOAD_ERR_PARTIAL:
                            $this->setAlert($lng->txt("form_msg_file_partially_uploaded"));
                            $uploadcheck = false;
                            break;

                        case UPLOAD_ERR_NO_FILE:
                            if ($this->getRequired()) {
                                $filename = $this->filenames[$index];
                                if (!strlen($filename)) {
                                    $this->setAlert($lng->txt("form_msg_file_no_upload"));
                                    $uploadcheck = false;
                                }
                            }
                            break;

                        case UPLOAD_ERR_NO_TMP_DIR:
                            $this->setAlert($lng->txt("form_msg_file_missing_tmp_dir"));
                            $uploadcheck = false;
                            break;

                        case UPLOAD_ERR_CANT_WRITE:
                            $this->setAlert($lng->txt("form_msg_file_cannot_write_to_disk"));
                            $uploadcheck = false;
                            break;

                        case UPLOAD_ERR_EXTENSION:
                            $this->setAlert($lng->txt("form_msg_file_upload_stopped_ext"));
                            $uploadcheck = false;
                            break;
                    }
                }

                // check suffixes
                if ($pictures["tmp_name"][$index] != "" && is_array($this->getSuffixes())) {
                    if (!in_array(strtolower($suffix), $this->getSuffixes())) {
                        $this->setAlert($lng->txt("form_msg_file_wrong_file_type"));
                        $uploadcheck = false;
                    }
                }

                // virus handling
                if ($pictures["tmp_name"][$index] != "") {
                    $vir = ilUtil::virusHandling($temp_name, $filename);
                    if ($vir[0] == false) {
                        $this->setAlert($lng->txt("form_msg_file_virus_found") . "<br />" . $vir[1]);
                        $uploadcheck = false;
                    }
                }
            }
        }

        if (!$uploadcheck) {
            return false;
        }
        
        return $this->checkSubItemsInput();
    }

    /**
    * Insert property html
    *
    * @return	int	Size
    */
    public function insert($a_tpl)
    {
        $lng = $this->lng;
        
        $tpl = new ilTemplate("tpl.prop_filewizardinput.html", true, true, "Services/Form");

        $i = 0;
        foreach ($this->filenames as $value) {
            if (strlen($value)) {
                $tpl->setCurrentBlock("image");
                $tpl->setVariable("SRC_IMAGE", $this->getImagePathWeb() . ilUtil::prepareFormOutput($value));
                $tpl->setVariable("PICTURE_FILE", ilUtil::prepareFormOutput($value));
                $tpl->setVariable("ID", $this->getFieldId() . "[$i]");
                $tpl->setVariable("ALT_IMAGE", ilUtil::prepareFormOutput($value));
                $tpl->parseCurrentBlock();
            }
            if ($this->getAllowMove()) {
                $tpl->setCurrentBlock("move");
                $tpl->setVariable("CMD_UP", "cmd[up" . $this->getFieldId() . "][$i]");
                $tpl->setVariable("CMD_DOWN", "cmd[down" . $this->getFieldId() . "][$i]");
                $tpl->setVariable("ID", $this->getFieldId() . "[$i]");
                $tpl->setVariable("UP_BUTTON", ilGlyphGUI::get(ilGlyphGUI::UP));
                $tpl->setVariable("DOWN_BUTTON", ilGlyphGUI::get(ilGlyphGUI::DOWN));
                $tpl->parseCurrentBlock();
            }

            $this->outputSuffixes($tpl, "allowed_image_suffixes");

            $tpl->setCurrentBlock("row");
            $tpl->setVariable("POST_VAR", $this->getPostVar() . "[$i]");
            $tpl->setVariable("ID", $this->getFieldId() . "[$i]");
            $tpl->setVariable("CMD_ADD", "cmd[add" . $this->getFieldId() . "][$i]");
            $tpl->setVariable("CMD_REMOVE", "cmd[remove" . $this->getFieldId() . "][$i]");
            $tpl->setVariable("ALT_ADD", $lng->txt("add"));
            $tpl->setVariable("ALT_REMOVE", $lng->txt("remove"));
            if ($this->getDisabled()) {
                $tpl->setVariable(
                    "DISABLED",
                    " disabled=\"disabled\""
                );
            }
            
            $tpl->setVariable("ADD_BUTTON", ilGlyphGUI::get(ilGlyphGUI::ADD));
            $tpl->setVariable("REMOVE_BUTTON", ilGlyphGUI::get(ilGlyphGUI::REMOVE));
            $tpl->setVariable("TXT_MAX_SIZE", $lng->txt("file_notice") . " " . $this->getMaxFileSizeString());
            $tpl->setVariable("MAX_UPLOAD_VALUE", $this->getMaxFileUploads());
            $tpl->setVariable("TXT_MAX_UPLOADS", $lng->txt("form_msg_max_upload") . " " . $this->getMaxFileUploads());
            $tpl->parseCurrentBlock();
            $i++;
        }
        $tpl->setVariable("ELEMENT_ID", $this->getFieldId());

        $a_tpl->setCurrentBlock("prop_generic");
        $a_tpl->setVariable("PROP_GENERIC", $tpl->get());
        $a_tpl->parseCurrentBlock();
        
        $main_tpl = $this->tpl;
        $main_tpl->addJavascript("./Services/Form/js/ServiceFormWizardInput.js");
        $main_tpl->addJavascript("./Services/Form/templates/default/filewizard.js");
    }
}
