<?php

/* Copyright (c) 1998-2021 ILIAS open source, GPLv3, see LICENSE */

/**
 * This class represents a file property in a property form.
 *
 * @author Alex Killing <alex.killing@gmx.de>
 */
class ilFileInputGUI extends ilSubEnabledFormPropertyGUI implements ilToolbarItem
{
    /**
     * @var ilLanguage
     */
    protected $lng;

    private $filename;
    private $filename_post;
    protected $size = 40;
    protected $pending;
    protected $allow_deletion;
    protected $filename_selection;
    
    protected static $check_wsp_quota;

    /**
     * @var array
     */
    protected $forbidden_suffixes = [];
    protected $suffixes = [];
    protected $value;
    
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
        $lng = $DIC->language();
        
        parent::__construct($a_title, $a_postvar);
        $this->setType("file");
        $this->setHiddenTitle("(" . $lng->txt("form_file_input") . ")");
    }
    
    /**
    * Set value by array
    *
    * @param	array	$a_values	value array
    */
    public function setValueByArray($a_values)
    {
        $value = $a_values[$this->getPostVar()] ?? null;
        if (!is_array($value)) {
            $this->setValue($value);
        }
        $filenam = $a_values[$this->getFileNamePostVar()] ?? '';
        $this->setFilename($filenam);
    }

    /**
    * Set Value. (used for displaying file title of existing file below input field)
    *
    * @param	string	$a_value	Value
    */
    public function setValue($a_value)
    {
        $this->value = $a_value;
    }

    /**
    * Get Value.
    *
    * @return	string	Value
    */
    public function getValue()
    {
        return $this->value;
    }
    
    /**
    * Set Size.
    *
    * @param	int	$a_size	Size
    */
    public function setSize($a_size)
    {
        $this->size = $a_size;
    }

    /**
    * Get Size.
    *
    * @return	int	Size
    */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Set filename value (if filename selection is enabled)
     *
     * @param string $a_val
     */
    public function setFilename($a_val)
    {
        $this->filename = $a_val;
    }
    
    /**
    * Get Value.
    *
    * @return	string	Value
    */
    public function getFilename()
    {
        return $this->filename;
    }
    
    

    /**
    * Set Accepted Suffixes.
    *
    * @param	array	$a_suffixes	Accepted Suffixes
    */
    public function setSuffixes($a_suffixes)
    {
        $this->suffixes = $a_suffixes;
    }

    /**
    * Get Accepted Suffixes.
    *
    * @return	array	Accepted Suffixes
    */
    public function getSuffixes()
    {
        return $this->suffixes;
    }

    /**
     * Set forbidden Suffixes.
     *
     * @param	array	$a_suffixes	forbidden Suffixes
     */
    public function setForbiddenSuffixes($a_suffixes)
    {
        $this->forbidden_suffixes = $a_suffixes;
    }

    /**
     * Get Accepted Suffixes.
     *
     * @return	array	forbidden Suffixes
     */
    public function getForbiddenSuffixes()
    {
        return $this->forbidden_suffixes;
    }

    /**
     * Set pending filename value
     *
     * @param string $a_val
     */
    public function setPending($a_val)
    {
        $this->pending = $a_val;
    }
    
    /**
    * Get pending filename
    *
    * @return	string	Value
    */
    public function getPending()
    {
        return $this->pending;
    }
    
    /**
     * If enabled, users get the possibility to enter a filename for the uploaded file
     *
     * @access public
     * @param string post variable
     *
     */
    public function enableFileNameSelection($a_post_var)
    {
        $this->filename_selection = true;
        $this->filename_post = $a_post_var;
    }
    
    /**
     * Check if filename selection is enabled
     *
     * @access public
     * @return bool enabled/disabled
     */
    public function isFileNameSelectionEnabled()
    {
        return $this->filename_selection ? true : false;
    }
    
    /**
     * Get file name post var
     *
     * @access public
     * @param string file name post var
     *
     */
    public function getFileNamePostVar()
    {
        return $this->filename_post;
    }
    
    /**
     * Set allow deletion
     *
     * @param boolean $a_val allow deletion
     */
    public function setALlowDeletion($a_val)
    {
        $this->allow_deletion = $a_val;
    }
    
    /**
     * Get allow deletion
     *
     * @return boolean allow deletion
     */
    public function getALlowDeletion()
    {
        return $this->allow_deletion;
    }

    /**
    * Check input, strip slashes etc. set alert, if input is not ok.
    *
    * @return	boolean		Input ok, true/false
    */
    public function checkInput()
    {
        $lng = $this->lng;
        
        // #18756
        if ($this->getDisabled()) {
            return true;
        }

        // if no information is received, something went wrong
        // this is e.g. the case, if the post_max_size has been exceeded
        if (!is_array($_FILES[$this->getPostVar()])) {
            $this->setAlert($lng->txt("form_msg_file_size_exceeds"));
            return false;
        }

        $_FILES[$this->getPostVar()]["name"] = ilUtil::stripSlashes($_FILES[$this->getPostVar()]["name"]);

        $_FILES[$this->getPostVar()]["name"] = ilStr::normalizeUtf8String($_FILES[$this->getPostVar()]["name"]);

        // remove trailing '/'
        $_FILES[$this->getPostVar()]["name"] = rtrim($_FILES[$this->getPostVar()]["name"], '/');

        $filename = $_FILES[$this->getPostVar()]["name"];
        $filename_arr = pathinfo($_FILES[$this->getPostVar()]["name"]);
        $suffix = $filename_arr["extension"] ?? '';
        $temp_name = $_FILES[$this->getPostVar()]["tmp_name"];
        $error = $_FILES[$this->getPostVar()]["error"];
        $_POST[$this->getPostVar()] = $_FILES[$this->getPostVar()];
        
        // error handling
        if ($error > 0) {
            switch ($error) {
                case UPLOAD_ERR_INI_SIZE:
                    $this->setAlert($lng->txt("form_msg_file_size_exceeds"));
                    return false;
                    break;
                    
                case UPLOAD_ERR_FORM_SIZE:
                    $this->setAlert($lng->txt("form_msg_file_size_exceeds"));
                    return false;
                    break;
    
                case UPLOAD_ERR_PARTIAL:
                    $this->setAlert($lng->txt("form_msg_file_partially_uploaded"));
                    return false;
                    break;
    
                case UPLOAD_ERR_NO_FILE:
                    if ($this->getRequired()) {
                        if (!strlen($this->getValue())) {
                            $this->setAlert($lng->txt("form_msg_file_no_upload"));
                            return false;
                        }
                    }
                    break;
     
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->setAlert($lng->txt("form_msg_file_missing_tmp_dir"));
                    return false;
                    break;
                    
                case UPLOAD_ERR_CANT_WRITE:
                    $this->setAlert($lng->txt("form_msg_file_cannot_write_to_disk"));
                    return false;
                    break;
     
                case UPLOAD_ERR_EXTENSION:
                    $this->setAlert($lng->txt("form_msg_file_upload_stopped_ext"));
                    return false;
                    break;
            }
        }
        
        // check suffixes
        if ($_FILES[$this->getPostVar()]["tmp_name"] != "") {
            if (is_array($this->forbidden_suffixes) && in_array(strtolower($suffix), $this->forbidden_suffixes)) {
                $this->setAlert($lng->txt("form_msg_file_type_is_not_allowed") . " (" . $suffix . ")");
                return false;
            }
            if (is_array($this->getSuffixes()) && count($this->getSuffixes()) > 0) {
                if (!in_array(strtolower($suffix), $this->getSuffixes())) {
                    $this->setAlert($lng->txt("form_msg_file_wrong_file_type"));
                    return false;
                }
            }
        }
        
        // virus handling
        if ($_FILES[$this->getPostVar()]["tmp_name"] != "") {
            $vir = ilUtil::virusHandling($temp_name, $filename);
            if ($vir[0] == false) {
                $this->setAlert($lng->txt("form_msg_file_virus_found") . "<br />" . $vir[1]);
                return false;
            }
        }
        
        return true;
    }

    /**
    * Render html
    */
    public function render($a_mode = "")
    {
        $lng = $this->lng;
        
        $quota_exceeded = $quota_legend = false;

        $f_tpl = new ilTemplate("tpl.prop_file.html", true, true, "Services/Form");
        
        
        // show filename selection if enabled
        if ($this->isFileNameSelectionEnabled()) {
            $f_tpl->setCurrentBlock('filename');
            $f_tpl->setVariable('POST_FILENAME', $this->getFileNamePostVar());
            $f_tpl->setVariable('VAL_FILENAME', $this->getFilename());
            $f_tpl->setVariable('FILENAME_ID', $this->getFieldId());
            $f_tpl->setVAriable('TXT_FILENAME_HINT', $lng->txt('if_no_title_then_filename'));
            $f_tpl->parseCurrentBlock();
        } else {
            if (trim($this->getValue() != "")) {
                if (!$this->getDisabled() && $this->getALlowDeletion()) {
                    $f_tpl->setCurrentBlock("delete_bl");
                    $f_tpl->setVariable("POST_VAR_D", $this->getPostVar());
                    $f_tpl->setVariable(
                        "TXT_DELETE_EXISTING",
                        $lng->txt("delete_existing_file")
                    );
                    $f_tpl->parseCurrentBlock();
                }
                
                $f_tpl->setCurrentBlock('prop_file_propval');
                $f_tpl->setVariable('FILE_VAL', $this->getValue());
                $f_tpl->parseCurrentBlock();
            }
        }

        if ($a_mode != "toolbar") {
            if (!$quota_exceeded) {
                $this->outputSuffixes($f_tpl);

                $f_tpl->setCurrentBlock("max_size");
                $f_tpl->setVariable("TXT_MAX_SIZE", $lng->txt("file_notice") . " " .
                    $this->getMaxFileSizeString());
                $f_tpl->parseCurrentBlock();
                
                if ($quota_legend) {
                    $f_tpl->setVariable("TXT_MAX_SIZE", $quota_legend);
                    $f_tpl->parseCurrentBlock();
                }
            } else {
                $f_tpl->setCurrentBlock("max_size");
                $f_tpl->setVariable("TXT_MAX_SIZE", $quota_exceeded);
                $f_tpl->parseCurrentBlock();
            }
        } elseif ($quota_exceeded) {
            return $quota_exceeded;
        }

        $pending = $this->getPending();
        if ($pending) {
            $f_tpl->setCurrentBlock("pending");
            $f_tpl->setVariable("TXT_PENDING", $lng->txt("file_upload_pending") .
                ": " . $pending);
            $f_tpl->parseCurrentBlock();
        }
        
        if ($this->getDisabled() || $quota_exceeded) {
            $f_tpl->setVariable(
                "DISABLED",
                " disabled=\"disabled\""
            );
        }
        
        $f_tpl->setVariable("POST_VAR", $this->getPostVar());
        $f_tpl->setVariable("ID", $this->getFieldId());
        $f_tpl->setVariable("SIZE", $this->getSize());
        
        
        /* experimental: bootstrap'ed file upload */
        $f_tpl->setVariable("TXT_BROWSE", $lng->txt("select_file"));
        
        
        return $f_tpl->get();
    }
    
    /**
    * Insert property html
    *
    * @return	int	Size
    */
    public function insert($a_tpl)
    {
        $html = $this->render();

        $a_tpl->setCurrentBlock("prop_generic");
        $a_tpl->setVariable("PROP_GENERIC", $html);
        $a_tpl->parseCurrentBlock();
    }


    protected function outputSuffixes($a_tpl, $a_block = "allowed_suffixes")
    {
        $lng = $this->lng;
        
        if (is_array($this->getSuffixes()) && count($this->getSuffixes()) > 0) {
            $suff_str = $delim = "";
            foreach ($this->getSuffixes() as $suffix) {
                $suff_str .= $delim . "." . $suffix;
                $delim = ", ";
            }
            $a_tpl->setCurrentBlock($a_block);
            $a_tpl->setVariable(
                "TXT_ALLOWED_SUFFIXES",
                $lng->txt("file_allowed_suffixes") . " " . $suff_str
            );
            $a_tpl->parseCurrentBlock();
        }
    }
    
    protected function getMaxFileSizeString()
    {
        // get the value for the maximal uploadable filesize from the php.ini (if available)
        $umf = ini_get("upload_max_filesize");
        // get the value for the maximal post data from the php.ini (if available)
        $pms = ini_get("post_max_size");
        
        //convert from short-string representation to "real" bytes
        $multiplier_a = array("K" => 1024, "M" => 1024 * 1024, "G" => 1024 * 1024 * 1024);
        
        $umf_parts = preg_split("/(\d+)([K|G|M])/", $umf, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $pms_parts = preg_split("/(\d+)([K|G|M])/", $pms, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        if (count($umf_parts) == 2) {
            $umf = $umf_parts[0] * $multiplier_a[$umf_parts[1]];
        }
        if (count($pms_parts) == 2) {
            $pms = $pms_parts[0] * $multiplier_a[$pms_parts[1]];
        }
        
        // use the smaller one as limit
        $max_filesize = min($umf, $pms);

        if (!$max_filesize) {
            $max_filesize = max($umf, $pms);
        }
    
        //format for display in mega-bytes
        $max_filesize = sprintf("%.1f MB", $max_filesize / 1024 / 1024);
        
        return $max_filesize;
    }
    
    /**
     * Get number of maximum file uploads as declared in php.ini
     *
     * @return int
     */
    protected function getMaxFileUploads()
    {
        return (int) ini_get("max_file_uploads");
    }
    
    /**
    * Get deletion flag
    */
    public function getDeletionFlag()
    {
        if ($_POST[$this->getPostVar() . "_delete"]) {
            return true;
        }
        return false;
    }
    
    /**
    * Get HTML for toolbar
    */
    public function getToolbarHTML()
    {
        $html = $this->render("toolbar");
        return $html;
    }
}
