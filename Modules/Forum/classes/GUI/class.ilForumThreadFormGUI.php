<?php declare(strict_types=1);
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilForumThreadFormGUI
 */
class ilForumThreadFormGUI extends ilPropertyFormGUI
{
    public const ALIAS_INPUT = 'alias';
    public const SUBJECT_INPUT = 'subject';
    public const MESSAGE_INPUT = 'message';
    public const FILE_UPLOAD_INPUT = 'file_upload';
    public const ALLOW_NOTIFICATION_INPUT = 'allow_notification';
    public const CAPTCHA_INPUT = 'captcha';
    
    /** @var string[] */
    private $input_items = [];
    private ilObjForumGUI $delegatingGui;
    private ilForumProperties $properties;
    private bool $allowPseudonyms;
    private bool $allowNotification;
    private bool $isDraftContext;
    private int $draftId;

    /**
     * ilForumThreadFormGUI constructor.
     * @param ilObjForumGUI     $delegatingGui
     * @param ilForumProperties $properties
     * @param bool               $allowPseudonyms
     * @param bool               $allowNotification
     * @param bool               $isDraftContext
     * @param int                $draftId
     */
    public function __construct(
        ilObjForumGUI $delegatingGui,
        ilForumProperties $properties,
        bool $allowPseudonyms,
        bool $allowNotification,
        bool $isDraftContext,
        int $draftId
    ) {
        parent::__construct();

        $this->delegatingGui = $delegatingGui;
        $this->properties = $properties;
        $this->allowPseudonyms = $allowPseudonyms;
        $this->allowNotification = $allowNotification;
        $this->isDraftContext = $isDraftContext;
        $this->draftId = $draftId;
    }

    private function addAliasInput() : void
    {
        if ($this->allowPseudonyms) {
            $alias = new ilTextInputGUI($this->lng->txt('forums_your_name'), 'alias');
            $alias->setInfo($this->lng->txt('forums_use_alias'));
            $alias->setMaxLength(255);
            $alias->setSize(50);
        } else {
            $alias = new ilNonEditableValueGUI($this->lng->txt('forums_your_name'), 'alias');
            $alias->setValue($this->user->getLogin());
        }
        $this->addItem($alias);
    }
    
    private function addSubjectInput() : void
    {
        $subject = new ilTextInputGUI($this->lng->txt('forums_thread'), 'subject');
        $subject->setMaxLength(255);
        $subject->setSize(50);
        $subject->setRequired(true);
        $this->addItem($subject);
    }
    
    private function addMessageInput() : void
    {
        $message = new ilTextAreaInputGUI($this->lng->txt('forums_the_post'), 'message');
        $message->setCols(50);
        $message->setRows(15);
        $message->setRequired(true);
        $message->setUseRte(true);
        $message->addPlugin('latex');
        $message->addButton('latex');
        $message->addButton('pastelatex');
        $message->addPlugin('ilfrmquote');
        $message->usePurifier(true);
        $message->setRTERootBlockElement('');
        $message->setRTESupport($this->user->getId(), 'frm~', 'frm_post', 'tpl.tinymce_frm_post.js', false, '5.6.0');
        $message->disableButtons([
            'charmap',
            'undo',
            'redo',
            'alignleft',
            'aligncenter',
            'alignright',
            'alignjustify',
            'anchor',
            'fullscreen',
            'cut',
            'copy',
            'paste',
            'pastetext',
            'formatselect'
        ]);
        $message->setPurifier(ilHtmlPurifierFactory::getInstanceByType('frm_post'));
        $this->addItem($message);
    }
    
    private function addFileUploadInput() : void
    {
        if ($this->properties->isFileUploadAllowed()) {
            $files = new ilFileWizardInputGUI($this->lng->txt('forums_attachments_add'), 'userfile');
            $files->setFilenames([0 => '']);
            $this->addItem($files);
            
            if ($this->isDraftContext) {
                if ($this->draftId > 0) {
                    $threadDraft = ilForumPostDraft::newInstanceByDraftId($this->draftId);
                    if ((int) $threadDraft->getDraftId() > 0) {
                        $draftFileData = new ilFileDataForumDrafts(0, $threadDraft->getDraftId());
                        if (count($draftFileData->getFilesOfPost()) > 0) {
                            $existingFileSelection = new ilCheckboxGroupInputGUI(
                                $this->lng->txt('forums_delete_file'),
                                'del_file'
                            );
                            foreach ($draftFileData->getFilesOfPost() as $file) {
                                $currentAttachment = new ilCheckboxInputGUI($file['name'], 'del_file');
                                $currentAttachment->setValue($file['md5']);
                                $existingFileSelection->addOption($currentAttachment);
                            }
                            $this->addItem($existingFileSelection);
                        }
                    }
                }
            }
        }
    }
    
    private function addAllowNotificationInput() : void
    {
        if ($this->allowNotification) {
            $notifyOnAnswer = new ilCheckboxInputGUI($this->lng->txt('forum_direct_notification'), 'notify');
            $notifyOnAnswer->setInfo($this->lng->txt('forum_notify_me'));
            $notifyOnAnswer->setValue(1);
            $this->addItem($notifyOnAnswer);
        }
    }
    
    private function addCaptchaInput() : void
    {
        if ($this->user->isAnonymous() && !$this->user->isCaptchaVerified() && ilCaptchaUtil::isActiveForForum()) {
            $captcha = new ilCaptchaInputGUI($this->lng->txt('cont_captcha_code'), 'captcha_code');
            $captcha->setRequired(true);
            $this->addItem($captcha);
        }
    }
    
    private function generateInputItems() : void
    {
        $this->setTitleIcon(ilUtil::getImagePath('icon_frm.svg'));
        $this->setTableWidth('100%');
        $this->setTitle($this->lng->txt('forums_new_thread'));
        if ($this->isDraftContext) {
            $this->setTitle($this->lng->txt('edit_thread_draft'));
        }
        
        foreach ($this->input_items as $input_item) {
            switch ($input_item) {
                case self::ALIAS_INPUT:
                    $this->addAliasInput();
                    break;

                case self::SUBJECT_INPUT:
                    $this->addSubjectInput();
                    break;

                case self::MESSAGE_INPUT:
                    $this->addMessageInput();
                    break;

                case self::FILE_UPLOAD_INPUT:
                    $this->addFileUploadInput();
                    break;

                case self::ALLOW_NOTIFICATION_INPUT:
                    $this->addAllowNotificationInput();
                    break;

                case self::CAPTCHA_INPUT:
                    $this->addCaptchaInput();
                    break;
            }
        }
    }

    public function addInputItem(string $input_item) : void
    {
        $this->input_items[] = $input_item;
    }

    public function generateDefaultForm() : void
    {
        $this->generateInputItems();

        if (ilForumPostDraft::isSavePostDraftAllowed() && !$this->user->isAnonymous()) {
            $this->ctrl->setParameter($this->delegatingGui, 'draft_id', $this->draftId);
            if (in_array($this->ctrl->getCmd(), ['publishThreadDraft', 'editThreadDraft', 'updateThreadDraft'])) {
                $this->addCommandButton('publishThreadDraft', $this->lng->txt('publish'));
                $this->addCommandButton('updateThreadDraft', $this->lng->txt('save_message'));
                $this->setFormAction($this->ctrl->getFormAction($this->delegatingGui, 'updateThreadDraft'));
            } else {
                $this->addCommandButton('addThread', $this->lng->txt('create'));
                $this->addCommandButton('saveThreadAsDraft', $this->lng->txt('save_message'));
                $this->setFormAction($this->ctrl->getFormAction($this->delegatingGui, 'saveThreadAsDraft'));
            }
            $this->addCommandButton('cancelDraft', $this->lng->txt('cancel'));
        } else {
            $this->addCommandButton('addThread', $this->lng->txt('create'));
            $this->addCommandButton('showThreads', $this->lng->txt('cancel'));
            $this->setFormAction($this->ctrl->getFormAction($this->delegatingGui, 'addThread'));
        }
    }

    public function generateMinimalForm() : void
    {
        $this->generateInputItems();

        $this->addCommandButton('addEmptyThread', $this->lng->txt('create'));
        $this->addCommandButton('showThreads', $this->lng->txt('cancel'));
        $this->setFormAction($this->ctrl->getFormAction($this->delegatingGui, 'addThread'));
    }
}
