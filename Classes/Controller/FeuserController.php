<?php
namespace Evoweb\SfRegister\Controller;

/***************************************************************
 * Copyright notice
 *
 * (c) 2011-17 Sebastian Fischer <typo3@evoweb.de>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Evoweb\SfRegister\Domain\Model\FrontendUser;
use Evoweb\SfRegister\Domain\Repository\FrontendUserGroupRepository;
use Evoweb\SfRegister\Domain\Repository\FrontendUserRepository;
use Evoweb\SfRegister\Property\TypeConverter\DateTimeConverter;
use Evoweb\SfRegister\Property\TypeConverter\UploadedFileReferenceConverter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;

/**
 * An frontend user controller
 */
class FeuserController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * User repository
     *
     * @var FrontendUserRepository
     */
    protected $userRepository;

    /**
     * Usergroup repository
     *
     * @var FrontendUserGroupRepository
     */
    protected $userGroupRepository;

    /**
     * File service
     *
     * @var \Evoweb\SfRegister\Services\File
     */
    protected $fileService;

    /**
     * The current view, as resolved by resolveView()
     *
     * @var \TYPO3\CMS\Fluid\View\TemplateView
     * @api
     */
    protected $view;

    /**
     * The current request.
     *
     * @var \TYPO3\CMS\Extbase\Mvc\Web\Request
     * @api
     */
    protected $request;

    /**
     * Active if autologgin was set.
     *
     * Used to define of on page redirect an additional
     * query parameter should be set.
     *
     * @var bool
     */
    protected $autoLoginTriggered = false;


    public function injectUserRepository(FrontendUserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function injectUserGroupRepository(FrontendUserGroupRepository $userGroupRepository)
    {
        $this->userGroupRepository = $userGroupRepository;
    }

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager)
    {
        $this->configurationManager = $configurationManager;
        $this->settings = $this->configurationManager->getConfiguration(
	    ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
	);
        $frameworkConfiguration = $this->configurationManager->getConfiguration(
	    ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
	);
        $GLOBALS['sf_register_controllerConfiguration'] = $frameworkConfiguration['controllerConfiguration'];
    }

    /**
     * Disable flash messages
     *
     * @return bool
     */
    protected function getErrorFlashMessage(): bool
    {
        return false;
    }

    protected function initializeAction()
    {
        $this->fileService = $this->objectManager->get(\Evoweb\SfRegister\Services\File::class);
        $this->setTypeConverter();

        if ($this->settings['processInitializeActionSignal']) {
            $this->signalSlotDispatcher->dispatch(
                __CLASS__,
                __FUNCTION__,
                [
                    'controller' => $this,
                    'settings' => $this->settings,
                ]
            );
        }

        if ($this->request->getControllerActionName() != 'removeImage'
            && $this->request->hasArgument('removeImage')
            && $this->request->getArgument('removeImage')
        ) {
            $this->forward('removeImage');
        }
    }

    protected function setTypeConverter()
    {
        $argumentName = 'user';
        if ($this->request->hasArgument($argumentName)) {
            /** @var PropertyMappingConfiguration $configuration */
            $configuration = $this->arguments[$argumentName]->getPropertyMappingConfiguration();
            /** @var array $user */
            $user = $this->request->getArgument('user');

            $this->getPropertyMappingConfiguration(
                $configuration,
                $user
            );
        }
    }

    /**
     * @param PropertyMappingConfiguration|null $configuration
     * @param array|array $userData
     *
     * @return PropertyMappingConfiguration
     */
    protected function getPropertyMappingConfiguration(
        PropertyMappingConfiguration $configuration = null,
        $userData = []
    ): PropertyMappingConfiguration {
        if (is_null($configuration)) {
            $configuration = $this->objectManager->get(PropertyMappingConfiguration::class);
        }

        $configuration->allowAllProperties();
        $configuration->forProperty('usergroup')->allowAllProperties();
        $configuration->forProperty('moduleSysDmailCategory')->allowAllProperties();
        $configuration->forProperty('image')->allowAllProperties();
        $configuration->setTypeConverterOption(
            PersistentObjectConverter::class,
            PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED,
            true
        );

        $folder = $this->fileService->getTempFolder();
        $uploadConfiguration = [
            UploadedFileReferenceConverter::CONFIGURATION_ALLOWED_FILE_EXTENSIONS =>
                $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],
            UploadedFileReferenceConverter::CONFIGURATION_UPLOAD_FOLDER =>
                $folder->getStorage()->getUid() . ':' . $folder->getIdentifier(),
        ];

        $configuration->forProperty('image.0')
            ->setTypeConverterOptions(
                UploadedFileReferenceConverter::class,
                $uploadConfiguration
            );

        $configuration->forProperty('dateOfBirth')
            ->setTypeConverterOptions(
                DateTimeConverter::class,
                [
                    DateTimeConverter::CONFIGURATION_USER_DATA => $userData,
                ]
            );

        return $configuration;
    }

    /**
     * Inject an view object to be able to set templateRootPath from flex form
     *
     * @param \TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view
     */
    protected function initializeView(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view)
    {
        if (isset($this->settings['templateRootPath']) && !empty($this->settings['templateRootPath'])) {
            $templateRootPath = GeneralUtility::getFileAbsFileName($this->settings['templateRootPath']);
            if (GeneralUtility::isAllowedAbsPath($templateRootPath)) {
                $this->view->setTemplateRootPaths([$templateRootPath]);
            }
        }
    }


    /**
     * Proxy action
     *
     * @param FrontendUser $user
     *
     * @validate $user Evoweb.SfRegister:User
     */
    public function proxyAction(FrontendUser $user)
    {
        $action = 'save';

        if ($this->request->hasArgument('form')) {
            $action = 'form';
        }

        $this->forward($action);
    }

    /**
     * Remove an image and forward to the action where it was called
     *
     * @param FrontendUser $user
     *
     * @ignorevalidation $user
     */
    protected function removeImageAction(FrontendUser $user)
    {
        /** @var \TYPO3\CMS\Extbase\Domain\Model\FileReference $image */
        $image = $user->getImage()->current();

        $this->fileService->removeFile($image);
        $this->removeImageFromUserAndRequest($user);

        $this->request->setArgument('removeImage', false);

        $referrer = $this->request->getReferringRequest();
        if ($referrer !== null) {
            $this->forward(
                $referrer->getControllerActionName(),
                $referrer->getControllerName(),
                $referrer->getControllerExtensionName(),
                $this->request->getArguments()
            );
        }
    }

    protected function removeImageFromUserAndRequest(FrontendUser $user): FrontendUser
    {
        if ($user->getUid() !== null) {
            /** @var FrontendUser $localUser */
            $localUser = $this->userRepository->findByUid($user->getUid());
            $localUser->removeImage();
            $this->userRepository->update($localUser);

            $this->persistAll();
        }

        $user->removeImage();

        /** @var array $requestUser */
        $requestUser = $this->request->getArgument('user');
        $requestUser['image'] = $user->getImage();
        $this->request->setArgument('user', $requestUser);

        return $user;
    }

    public function encryptPassword(string $password, array $settings): string
    {
        if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('saltedpasswords')
            && \TYPO3\CMS\Saltedpasswords\Utility\SaltedPasswordsUtility::isUsageEnabled('FE')
        ) {
            $saltObject = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getSaltingInstance(null);
            if ($saltObject instanceof \TYPO3\CMS\Saltedpasswords\Salt\SaltInterface) {
                $password = $saltObject->getHashedPassword($password);
            }
        } elseif ($settings['encryptPassword'] === 'md5') {
            $password = md5($password);
        } elseif ($settings['encryptPassword'] === 'sha1') {
            GeneralUtility::deprecationLog(
                'sha1 password encryption is deprecated and will be removed after 2018.02.01'
            );
            $password = sha1($password);
        }

        return $password;
    }

    protected function persistAll()
    {
        $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class)->persistAll();
    }

    protected function redirectToPage(int $pageId)
    {
        if ($this->autoLoginTriggered) {
            $statusField = $this->getTypoScriptFrontendController()->fe_user->formfield_permanent;
            $this->uriBuilder->setAddQueryString('&' . $statusField . '=login');
        }

        $url = $this->uriBuilder
            ->setTargetPageUid($pageId)
            ->build();

        $this->redirectToUri($url);
    }


    protected function sendEmails(FrontendUser $user, string $type): FrontendUser
    {
        /** @var \Evoweb\SfRegister\Services\Mail $mailService */
        $mailService = $this->objectManager->get(\Evoweb\SfRegister\Services\Mail::class);

        if ($this->isNotifyAdmin($type)) {
            $user = $mailService->sendAdminNotification($user, $type);
        }

        if ($this->isNotifyUser($type)) {
            $user = $mailService->sendUserNotification($user, $type);
        }

        return $user;
    }

    protected function isNotifyAdmin(string $type): bool
    {
        return isset($this->settings['notifyAdmin' . $type]) && !empty($this->settings['notifyAdmin' . $type]);
    }

    protected function isNotifyUser(string $type): bool
    {
        return isset($this->settings['notifyUser' . $type]) && !empty($this->settings['notifyUser' . $type]);
    }


    /**
     * Determines whether a user is in a given user group.
     *
     * @param FrontendUser $user
     * @param \Evoweb\SfRegister\Domain\Model\FrontendUserGroup|string|int $userGroup
     *
     * @return bool
     */
    protected function isUserInUserGroup(FrontendUser $user, $userGroup): bool
    {
        $return = false;

        if ($userGroup instanceof \Evoweb\SfRegister\Domain\Model\FrontendUserGroup) {
            $return = $user->getUsergroup()->contains($userGroup);
        } elseif (!empty($userGroup)) {
            $userGroupUids = $this->getEntityUids(
                $user->getUsergroup()->toArray()
            );

            $return = in_array($userGroup, $userGroupUids);
        }

        return $return;
    }

    /**
     * Determines whether a user is in a given user group.
     *
     * @param FrontendUser $user
     * @param array|\Evoweb\SfRegister\Domain\Model\FrontendUserGroup[] $userGroups
     *
     * @return bool
     */
    protected function isUserInUserGroups(FrontendUser $user, array $userGroups): bool
    {
        $return = false;

        foreach ($userGroups as $userGroup) {
            if ($this->isUserInUserGroup($user, $userGroup)) {
                $return = true;
            }
        }

        return $return;
    }

    protected function getFollowingUserGroups(int $currentUserGroup): array
    {
        $userGroups = $this->getUserGroupIds();
        $currentIndex = array_search((int)$currentUserGroup, array_values($userGroups));

        $reducedUserGroups = [];
        if ($currentIndex !== false && $currentIndex < count($userGroups)) {
            $reducedUserGroups = array_slice($userGroups, $currentIndex);
        }

        return $reducedUserGroups;
    }

    protected function getUserGroupIds(): array
    {
        $settingsUserGroupKeys = $this->getUserGroupIdSettingKeys();

        $userGroups = [];
        foreach ($settingsUserGroupKeys as $settingsUserGroupKey) {
            $userGroup = (int) $this->settings[$settingsUserGroupKey];
            if ($userGroup) {
                $userGroups[$settingsUserGroupKey] = $userGroup;
            }
        }

        return $userGroups;
    }

    /**
     * Gets the uid of each given entity.
     *
     * @param array|\TYPO3\CMS\Extbase\DomainObject\AbstractEntity[] $entities
     *
     * @return array
     */
    protected function getEntityUids(array $entities): array
    {
        $entityUids = [];

        foreach ($entities as $entity) {
            $entityUids[] = $entity->getUid();
        }

        return $entityUids;
    }


    protected function changeUsergroup(
        FrontendUser $user,
        int $userGroupIdToAdd
    ): Frontenduser {
        $this->removePreviousUserGroups($user);

        $userGroupIdToAdd = (int) $userGroupIdToAdd;
        if ($userGroupIdToAdd) {
            /** @var \Evoweb\SfRegister\Domain\Model\FrontendUserGroup $userGroupToAdd */
            $userGroupToAdd = $this->userGroupRepository->findByUid($userGroupIdToAdd);
            $user->addUsergroup($userGroupToAdd);
        }

        return $user;
    }

    protected function removePreviousUserGroups(FrontendUser $user)
    {
        $userGroupIds = $this->getUserGroupIds();
        $assignedUserGroups = $user->getUsergroup();
        foreach ($assignedUserGroups as $singleUserGroup) {
            if (\in_array($singleUserGroup->getUid(), $userGroupIds)) {
                $assignedUserGroups->detach($singleUserGroup);
            }
        }
        $user->setUsergroup($assignedUserGroups);
    }


    protected function autoLogin(FrontendUser $user, int $redirectPageId)
    {
        session_start();
        $this->autoLoginTriggered = true;

        $_SESSION['sf-register-user'] = GeneralUtility::hmac('auto-login::' . $user->getUid(), $GLOBALS['EXEC_TIME']);

        /** @var \TYPO3\CMS\Core\Registry $registry */
        $registry = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Registry::class);
        $registry->set('sf-register', $_SESSION['sf-register-user'], $user->getUid());

        // if redirect was empty by now set it to current page
        if (intval($redirectPageId) == 0) {
            $redirectPageId = $this->getTypoScriptFrontendController()->id;
        }

        // get configured redirect page id if given
        $userGroups = $user->getUsergroup();
        /** @var \Evoweb\SfRegister\Domain\Model\FrontendUserGroup $userGroup */
        foreach ($userGroups as $userGroup) {
            if ($userGroup->getFeloginRedirectPid()) {
                $redirectPageId = $userGroup->getFeloginRedirectPid();
                break;
            }
        }

        if ($redirectPageId > 0) {
            $this->redirectToPage((int) $redirectPageId);
        }
    }

    protected function userIsLoggedIn(): bool
    {
        /** @noinspection PhpInternalEntityUsedInspection */
        return is_array($this->getTypoScriptFrontendController()->fe_user->user);
    }

    /**
     * Determines the frontend user, either if it's
     * already submitted, or by looking up the mail hash code.
     *
     * @param NULL|FrontendUser $user
     * @param NULL|string $hash
     *
     * @return NULL|FrontendUser
     */
    protected function determineFrontendUser(FrontendUser $user = null, string $hash = null)
    {
        $frontendUser = null;

        $requestArguments = $this->request->getArguments();
        if ($user !== null && $hash !== null) {
            $calculatedHash = GeneralUtility::hmac(
                $requestArguments['action'] . '::' . $user->getUid()
            );
            if ($hash === $calculatedHash) {
                $frontendUser = $user;
            }
        }

        return $frontendUser;
    }

    protected function getTypoScriptFrontendController(): \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * Return the keys of the TypoScript configuration in the order which is relevant for the configured
     * registration workflow
     *
     * @return array
     */
    protected function getUserGroupIdSettingKeys(): array
    {
        $defaultOrder = [
            'usergroup',
            'usergroupPostSave',
            'usergroupPostConfirm',
            'usergroupPostAccept',
        ];

        // Admin    [plugin.tx_sfregister.settings.acceptEmailPostCreate]
        $confirmEmailPostCreate = (bool)$this->settings['confirmEmailPostCreate'];
        // User     [plugin.tx_sfregister.settings.confirmEmailPostAccept]
        $acceptEmailPostCreate = (bool)$this->settings['acceptEmailPostCreate'];
        // Admin    [plugin.tx_sfregister.settings.acceptEmailPostConfirm]
        $confirmEmailPostAccept = (bool)$this->settings['confirmEmailPostAccept'];
        // User     [plugin.tx_sfregister.settings.confirmEmailPostCreate]
        $acceptEmailPostConfirm = (bool)$this->settings['acceptEmailPostConfirm'];

        // First User:confirm then Admin:accept
        if ($confirmEmailPostCreate && $acceptEmailPostConfirm) {
            return $defaultOrder;
        }

        // First Admin:accept then User:confirm
        if ($acceptEmailPostCreate && $confirmEmailPostAccept) {
            return [
                'usergroup',
                'usergroupPostSave',
                'usergroupPostAccept',
                'usergroupPostConfirm',
            ];
        }

        return $defaultOrder;
    }
}
