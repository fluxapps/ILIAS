<?php

/* Copyright (c) 1998-2021 ILIAS open source, GPLv3, see LICENSE */

/**
 * BlockGUI class for Selected Items on Personal Desktop
 *
 * @author Alexander Killing <killing@leifos.de>
 *
 * @ilCtrl_IsCalledBy ilPDSelectedItemsBlockGUI: ilColumnGUI
 * @ilCtrl_Calls ilPDSelectedItemsBlockGUI: ilCommonActionDispatcherGUI
 */
class ilPDSelectedItemsBlockGUI extends ilBlockGUI implements ilDesktopItemHandling
{
    /** @var ilRbacSystem */
    protected $rbacsystem;

    /** @var ilSetting */
    protected $settings;

    /** @var ilObjectDefinition */
    protected $obj_definition;

    /** @var string */
    public static $block_type = 'pditems';

    /** @var ilPDSelectedItemsBlockViewSettings */
    protected $viewSettings;

    /** @var ilPDSelectedItemsBlockViewGUI */
    protected $blockView;

    /** @var bool */
    protected $manage = false;

    /** @var string */
    protected $content = '';

    /** @var ilLanguage */
    protected $lng;

    /** @var ilCtrl */
    protected $ctrl;

    /** @var ilObjUser */
    protected $user;

    /** @var \ILIAS\DI\UIServices */
    protected $ui;
    
    /** @var \ILIAS\HTTP\GlobalHttpState */
    protected $http;

    /** @var \ilObjectService */
    protected $objectService;

    /**
     * @var ilFavouritesManager
     */
    protected $favourites;

    /**
     * @var ilTree
     */
    protected $tree;

    /**
     * @var ilPDSelectedItemsBlockListGUIFactory
     */
    protected $list_factory;

    /**
     * ilPDSelectedItemsBlockGUI constructor.
     */
    public function __construct()
    {
        /** @var ILIAS\DI\Container $DIC */
        global $DIC;

        $this->rbacsystem = $DIC->rbac()->system();
        $this->settings = $DIC->settings();
        $this->obj_definition = $DIC["objDefinition"];
        $this->access = $DIC->access();
        $this->ui = $DIC->ui();
        $this->http = $DIC->http();
        $this->objectService = $DIC->object();
        $this->favourites = new ilFavouritesManager();
        $this->tree = $DIC->repositoryTree();

        parent::__construct();

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->user = $DIC->user();

        $this->lng->loadLanguageModule('pd');
        $this->lng->loadLanguageModule('cntr'); // #14158
        $this->lng->loadLanguageModule('rep'); // #14158

        $this->setEnableNumInfo(false);
        $this->setLimit(99999);
        $this->allow_moving = false;

        $this->initViewSettings();

        if ($this->viewSettings->isTilePresentation()) {
            $this->setPresentation(self::PRES_MAIN_LEG);
        } else {
            $this->setPresentation(self::PRES_MAIN_LIST);
        }

        $params = $DIC->http()->request()->getQueryParams();
        $this->requested_item_ref_id = (int) ($params["item_ref_id"] ?? 0);
    }

    /**
     * Evaluates the view settings of this block
     */
    protected function initViewSettings()
    {
        $this->viewSettings = new ilPDSelectedItemsBlockViewSettings(
            $this->user,
            ilPDSelectedItemsBlockViewSettings::VIEW_SELECTED_ITEMS
        );

        $this->viewSettings->parse();

        $this->blockView = ilPDSelectedItemsBlockViewGUI::bySettings($this->viewSettings);

        $this->ctrl->setParameter($this, 'view', $this->viewSettings->getCurrentView());
    }

    /**
     * @return ilPDSelectedItemsBlockViewSettings
     */
    public function getViewSettings() : ilPDSelectedItemsBlockViewSettings
    {
        return $this->viewSettings;
    }

    /**
     * @inheritdoc
     */
    /*
    public function fillDetailRow()
    {
        parent::fillDetailRow();
    }*/

    /**
     * @inheritdoc
     */
    public function addToDeskObject()
    {
        $this->favourites->add($this->user->getId(), $this->requested_item_ref_id);
        ilUtil::sendSuccess($this->lng->txt("rep_added_to_favourites"), true);
        $this->returnToContext();
    }

    /**
     * Return to context
     */
    protected function returnToContext()
    {
        $this->ctrl->setParameterByClass('ildashboardgui', 'view', $this->viewSettings->getCurrentView());
        $this->ctrl->redirectByClass('ildashboardgui', 'show');
    }

    /**
     * @inheritdoc
     */
    public function removeFromDeskObject()
    {
        $this->lng->loadLanguageModule("rep");
        $this->favourites->remove($this->user->getId(), $this->requested_item_ref_id);
        ilUtil::sendSuccess($this->lng->txt("rep_removed_from_favourites"), true);
        $this->returnToContext();
    }

    /**
     * @inheritdoc
     */
    public function getBlockType() : string
    {
        return static::$block_type;
    }

    /**
     * @inheritdoc
     */
    protected function isRepositoryObject() : bool
    {
        return false;
    }

    /**
     * Get view title
     * @return string
     */
    protected function getViewTitle()
    {
        return $this->blockView->getTitle();
    }

    /**
     * @inheritdoc
     */
    public function getHTML()
    {
        global $DIC;

        $this->setTitle($this->getViewTitle());

        $DIC->database()->useSlave(true);

        // workaround to show details row
        $this->setData([['dummy']]);

        $DIC['ilHelp']->setDefaultScreenId(ilHelpGUI::ID_PART_SCREEN, $this->blockView->getScreenId());

        $this->ctrl->clearParameters($this);

        $DIC->database()->useSlave(false);

        return parent::getHTML();
    }

    /**
     *
     */
    public function executeCommand() : string
    {
        $next_class = $this->ctrl->getNextClass();
        $cmd = $this->ctrl->getCmd('getHTML');

        switch ($next_class) {
            case 'ilcommonactiondispatchergui':
                $gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
                $this->ctrl->forwardCommand($gui);
                break;
                
            default:
                if (method_exists($this, $cmd)) {
                    return $this->$cmd();
                } else {
                    return $this->{$cmd . 'Object'}();
                }
        }
        return "";
    }

    /**
     * @return string
     */
    protected function getContent() : string
    {
        return $this->content;
    }

    /**
     * @param string $a_content
     */
    protected function setContent(string $a_content)
    {
        $this->content = $a_content;
    }

    /**
     * @inheritdoc
     */
    public function fillDataSection()
    {
        if ($this->getContent() == '') {
            $this->setDataSection($this->blockView->getIntroductionHtml());
        } else {
            $this->tpl->setVariable('BLOCK_ROW', $this->getContent());
        }
    }

    /**
     * @return array
     */
    protected function getGroupedCommandsForView($manage = false) : array
    {
        $commandGroups = [];

        $sortingCommands = [];
        $sortings = $this->viewSettings->getSelectableSortingModes();
        $effectiveSorting = $this->viewSettings->getEffectiveSortingMode();
        // @todo: set checked on $sorting === $effectiveSorting
        foreach ($sortings as $sorting) {
            $this->ctrl->setParameter($this, 'sorting', $sorting);
            $sortingCommands[] = [
                'txt' => $this->lng->txt('dash_sort_by_' . $sorting),
                'url' => $this->ctrl->getLinkTarget($this, 'changePDItemSorting'),
                'xxxasyncUrl' => $this->ctrl->getLinkTarget($this, 'changePDItemSorting', '', true),
                'active' => $sorting === $effectiveSorting,
            ];
            $this->ctrl->setParameter($this, 'sorting', null);
        }

        if (count($sortingCommands) > 1) {
            $commandGroups[] = $sortingCommands;
        }

        if ($manage) {
            return $commandGroups;
        }

        $presentationCommands = [];
        $presentations = $this->viewSettings->getSelectablePresentationModes();
        $effectivePresentation = $this->viewSettings->getEffectivePresentationMode();
        // @todo: set checked on $presentation === $effectivePresentation
        foreach ($presentations as $presentation) {
            $this->ctrl->setParameter($this, 'presentation', $presentation);
            $presentationCommands[] = [
                'txt' => $this->lng->txt('pd_presentation_mode_' . $presentation),
                'url' => $this->ctrl->getLinkTarget($this, 'changePDItemPresentation'),
                'xxxasyncUrl' => $this->ctrl->getLinkTarget($this, 'changePDItemPresentation', '', true),
                'active' => $presentation === $effectivePresentation,
            ];
            $this->ctrl->setParameter($this, 'presentation', null);
        }

        if (count($presentationCommands) > 1) {
            $commandGroups[] = $presentationCommands;
        }

        $commandGroups[] = [
            [
                'txt' => $this->viewSettings->isSelectedItemsViewActive() ?
                    $this->lng->txt('pd_remove_multiple') :
                    $this->lng->txt('pd_unsubscribe_multiple_memberships'),
                'url' => $this->ctrl->getLinkTarget($this, 'manage'),
                'asyncUrl' => null,
                'active' => false,
            ]
        ];

        return $commandGroups;
    }
        

    /**
     *
     */
    /* not neede anymore?
    protected function setFooterLinks() : void
    {
        if ('' === $this->getContent()) {
            $this->setEnableNumInfo(false);
            return;
        }

        if ($this->blockView->isInManageMode()) {
            return;
        }

        // @todo: handle $command['active']
        $groupedCommands = $this->getGroupedCommandsForView();
        foreach ($groupedCommands as $group) {
            foreach ($group as $command) {
                $this->addBlockCommand(
                    $command['url'],
                    $command['txt'],
                    $command['asyncUrl']
                );
            }
        }
    }*/

    /**
     * Called if the user interacted with the provided sorting options
     */
    public function changePDItemPresentation() : void
    {
        $this->viewSettings->storeActorPresentationMode(
            \ilUtil::stripSlashes((string) ($this->http->request()->getQueryParams()['presentation'] ?? ''))
        );
        $this->initAndShow();
    }

    /**
     * Called if the user interacted with the provided presentation options
     */
    public function changePDItemSorting() : void
    {
        $this->viewSettings->storeActorSortingMode(
            \ilUtil::stripSlashes((string) ($this->http->request()->getQueryParams()['sorting'] ?? ''))
        );

        $this->initAndShow();
    }

    /**
     *
     */
    protected function initAndShow() : void
    {
        $this->initViewSettings();

        if ($this->ctrl->isAsynch()) {
            echo $this->getHTML();
            exit;
        }

        $this->returnToContext();
    }

    /**
     * @return string
     */
    public function manageObject() : string
    {
        $this->main_tpl->setTitle($this->lng->txt("dash_favourites"));

        $this->blockView->setIsInManageMode(true);

        $top_tb = new ilToolbarGUI();
        $top_tb->setFormAction($this->ctrl->getFormAction($this));
        $top_tb->setLeadingImage(ilUtil::getImagePath('arrow_upright.svg'), $this->lng->txt('actions'));

        $button = ilSubmitButton::getInstance();
        if ($this->viewSettings->isSelectedItemsViewActive()) {
            $button->setCaption('remove');
        } else {
            $button->setCaption('pd_unsubscribe_memberships');
        }
        $button->setCommand('confirmRemove');
        $top_tb->addStickyItem($button);

        $button2 = ilSubmitButton::getInstance();
        $button2->setCaption('cancel');
        $button2->setCommand('cancel');
        $top_tb->addStickyItem($button2);

        $top_tb->setCloseFormTag(false);

        $bot_tb = new ilToolbarGUI();
        $bot_tb->setLeadingImage(ilUtil::getImagePath('arrow_downright.svg'), $this->lng->txt('actions'));
        $bot_tb->addStickyItem($button);
        $bot_tb->addStickyItem($button2);
        $bot_tb->setOpenFormTag(false);

        return $top_tb->getHTML() . $this->renderManageList() . $bot_tb->getHTML();
    }

    /**
     * Cancel
     */
    protected function cancel() : void
    {
        $this->ctrl->returnToParent($this);
    }

    /**
     * @return string
     */
    public function confirmRemoveObject() : string
    {
        $this->ctrl->setParameter($this, 'view', $this->viewSettings->getCurrentView());

        $refIds = (array) ($this->http->request()->getParsedBody()['id'] ?? []);
        if (0 === count($refIds)) {
            ilUtil::sendFailure($this->lng->txt('select_one'), true);
            $this->ctrl->redirect($this, 'manage');
        }

        if ($this->viewSettings->isSelectedItemsViewActive()) {
            $question = $this->lng->txt('dash_info_sure_remove_from_favs');
            $cmd = 'confirmedRemove';
        } else {
            $question = $this->lng->txt('mmbr_info_delete_sure_unsubscribe');
            $cmd = 'confirmedUnsubscribe';
        }

        $cgui = new ilConfirmationGUI();
        $cgui->setHeaderText($question);

        $cgui->setFormAction($this->ctrl->getFormAction($this));
        $cgui->setCancel($this->lng->txt('cancel'), 'manage');
        $cgui->setConfirm($this->lng->txt('confirm'), $cmd);

        foreach ($refIds as $ref_id) {
            $obj_id = ilObject::_lookupObjectId((int) $ref_id);
            $title = ilObject::_lookupTitle($obj_id);
            $type = ilObject::_lookupType($obj_id);

            $cgui->addItem(
                'ref_id[]',
                $ref_id,
                $title,
                ilObject::_getIcon($obj_id, 'small', $type),
                $this->lng->txt('icon') . ' ' . $this->lng->txt('obj_' . $type)
            );
        }

        return $cgui->getHTML();
    }


    public function confirmedRemove() : void
    {
        $refIds = (array) ($this->http->request()->getParsedBody()['ref_id'] ?? []);
        if (0 === count($refIds)) {
            $this->ctrl->redirect($this, 'manage');
        }

        foreach ($refIds as $ref_id) {
            $this->favourites->remove($this->user->getId(), $ref_id);
        }

        // #12909
        ilUtil::sendSuccess($this->lng->txt('pd_remove_multi_confirm'), true);
        $this->ctrl->setParameterByClass('ildashboardgui', 'view', $this->viewSettings->getCurrentView());
        $this->ctrl->redirectByClass('ildashboardgui', 'show');
    }
    
    public function confirmedUnsubscribe() : void
    {
        $refIds = (array) ($this->http->request()->getParsedBody()['ref_id'] ?? []);
        if (0 === count($refIds)) {
            $this->ctrl->redirect($this, 'manage');
        }

        foreach ($refIds as $ref_id) {
            if ($this->access->checkAccess('leave', '', (int) $ref_id)) {
                switch (ilObject::_lookupType($ref_id, true)) {
                    case 'crs':
                        // see ilObjCourseGUI:performUnsubscribeObject()
                        $members = new ilCourseParticipants(ilObject::_lookupObjId((int) $ref_id));
                        $members->delete($this->user->getId());

                        $members->sendUnsubscribeNotificationToAdmins($this->user->getId());
                        $members->sendNotification(
                            $members->NOTIFY_UNSUBSCRIBE,
                            $this->user->getId()
                        );
                        break;

                    case 'grp':
                        // see ilObjGroupGUI:performUnsubscribeObject()
                        $members = new ilGroupParticipants(ilObject::_lookupObjId((int) $ref_id));
                        $members->delete($this->user->getId());

                        $members->sendNotification(
                            ilGroupMembershipMailNotification::TYPE_UNSUBSCRIBE_MEMBER,
                            $this->user->getId()
                        );
                        $members->sendNotification(
                            ilGroupMembershipMailNotification::TYPE_NOTIFICATION_UNSUBSCRIBE,
                            $this->user->getId()
                        );
                        break;

                    default:
                        // do nothing
                        continue 2;
                }
        
                ilForumNotification::checkForumsExistsDelete($ref_id, $this->user->getId());
            }
        }

        ilUtil::sendSuccess($this->lng->txt('mmbr_unsubscribed_from_objs'), true);
        $this->ctrl->returnToParent($this);
    }

    //
    // New rendering
    //

    protected $new_rendering = true;


    /**
     * Get items
     *
     * @return \ILIAS\UI\Component\Item\Group[]
     */
    protected function getListItemGroups() : array
    {
        global $DIC;
        $factory = $DIC->ui()->factory();

        $this->list_factory = new ilPDSelectedItemsBlockListGUIFactory($this, $this->blockView);

        $groupedItems = $this->blockView->getItemGroups();
        $groupedCommands = $this->getGroupedCommandsForView();
        foreach ($groupedCommands as $group) {
            foreach ($group as $command) {
                $asynch_url = $command['asyncUrl'] ?? "";
                $this->addBlockCommand(
                    (string) $command['url'],
                    (string) $command['txt'],
                    (string) $asynch_url
                );
            }
        }

        ////
        ///


        $item_groups = [];
        $list_items = [];

        foreach ($groupedItems as $group) {
            $list_items = [];

            foreach ($group->getItems() as $item) {
                try {
                    $itemListGUI = $this->list_factory->byType($item['type']);
                    ilObjectActivation::addListGUIActivationProperty($itemListGUI, $item);

                    $list_items[] = $this->getListItemForData($item);
                } catch (ilException $e) {
                    continue;
                }
            }
            if (count($list_items) > 0) {
                $item_groups[] = $factory->item()->group($group->getLabel(), $list_items);
            }
        }

        return $item_groups;
    }


    /**
     * @inheritdoc
     */
    protected function getListItemForData(array $data) : ?\ILIAS\UI\Component\Item\Item
    {
        $listFactory = $this->list_factory;

        /** @var ilObjectListGUI $itemListGui */
        $itemListGui = $listFactory->byType($data['type']);
        ilObjectActivation::addListGUIActivationProperty($itemListGui, $data);

        $list_item = $itemListGui->getAsListItem(
            (int) $data['ref_id'],
            (int) $data['obj_id'],
            (string) $data['type'],
            (string) $data['title'],
            (string) $data['description']
        );

        return $list_item;
    }

    /**
     * @inheritdoc
     */
    protected function getCardForData(array $item) : \ILIAS\UI\Component\Card\RepositoryObject
    {
        $listFactory = $this->list_factory;

        /** @var ilObjectListGUI $itemListGui */
        $itemListGui = $listFactory->byType($item['type']);
        ilObjectActivation::addListGUIActivationProperty($itemListGui, $item);

        $card = $itemListGui->getAsCard(
            (int) $item['ref_id'],
            (int) $item['obj_id'],
            (string) $item['type'],
            (string) $item['title'],
            (string) $item['description']
        );

        return $card;
    }

    /**
     * @inheritdoc
     */
    protected function getLegacyContent() : string
    {
        $renderer = $this->ui->renderer();
        $factory = $this->ui->factory();

        $this->list_factory = new ilPDSelectedItemsBlockListGUIFactory($this, $this->blockView);

        $groupedCommands = $this->getGroupedCommandsForView();
        foreach ($groupedCommands as $group) {
            foreach ($group as $command) {
                $asynch_url = $command['asyncUrl'] ?? "";
                $this->addBlockCommand(
                    (string) $command['url'],
                    (string) $command['txt'],
                    (string) $asynch_url
                );
            }
        }

        $groupedItems = $this->blockView->getItemGroups();

        $subs = [];
        foreach ($groupedItems as $group) {
            $cards = [];

            foreach ($group->getItems() as $item) {
                try {
                    $itemListGUI = $this->list_factory->byType($item['type']);
                    ilObjectActivation::addListGUIActivationProperty($itemListGUI, $item);

                    $cards[] = $this->getCardForData($item);
                } catch (ilException $e) {
                    continue;
                }
            }
            if (count($cards) > 0) {
                $subs[] = $factory->panel()->sub(
                    $group->getLabel(),
                    $factory->deck($cards)->withNormalCardsSize()
                );
            }
        }


        return $renderer->render($subs);
    }

    protected function renderManageList() : string
    {
        $ui = $this->ui;

        $this->ctrl->setParameter($this, "manage", "1");
        $groupedCommands = $this->getGroupedCommandsForView(true);
        foreach ($groupedCommands as $group) {
            foreach ($group as $command) {
                $this->addBlockCommand(
                    (string) $command['url'],
                    (string) $command['txt'],
                    (string) $command['asyncUrl']
                );
            }
        }

        // action drop down
        if (is_array($groupedCommands[0])) {
            $actions = array_map(function ($item) use ($ui) {
                return $ui->factory()->link()->standard($item["txt"], $item["url"]);
            }, $groupedCommands[0]);
            if (count($actions) > 0) {
                $dd = $this->ui->factory()->dropdown()->standard($actions);
                $this->main_tpl->setHeaderActionMenu($ui->renderer()->render($dd));
            }
        }

        $grouped_items = $this->blockView->getItemGroups();

        $renderer = new ilDashObjectsTableRenderer($this);

        return $renderer->render($grouped_items);
    }

    /**
     * No item entry
     *
     * @return string
     */
    public function getNoItemFoundContent() : string
    {
        $txt = $this->lng->txt("rep_fav_intro1") . "<br>";
        $txt .= sprintf(
            $this->lng->txt('rep_fav_intro2'),
            $this->getRepositoryTitle()
        ) . "<br>";
        $txt .= $this->lng->txt("rep_fav_intro3");
        $mbox = $this->ui->factory()->messageBox()->info($txt);
        $mbox = $mbox->withLinks([$this->ui->factory()->link()->standard($this->getRepositoryTitle(), ilLink::_getStaticLink(1, 'root', true))]);
        return $this->ui->renderer()->render($mbox);
    }

    /**
     * @return string
     */
    protected function getRepositoryTitle() : string
    {
        $nd = $this->tree->getNodeData($this->tree->getRootId());
        $title = $nd['title'];

        if ($title == 'ILIAS') {
            $title = $this->lng->txt('repository');
        }

        return $title;
    }
}
