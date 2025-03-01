<?php declare(strict_types=1);
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilAuthProviderSaml
 */
class ilAuthProviderSaml extends ilAuthProvider implements ilAuthProviderInterface, ilAuthProviderAccountMigrationInterface
{
    /** @var string */
    protected $uid = '';
    /** @var array */
    protected $attributes = [];
    /** @var string */
    protected $return_to = '';
    /** @var ilSamlIdp */
    protected $idp;
    /** @var bool */
    protected $force_new_account = false;
    /** @var string */
    protected $migration_account = '';

    /**
     * ilAuthProviderSaml constructor.
     * @param ilAuthFrontendCredentials $credentials
     * @param int|null $a_idp_id
     * @throws ilSamlException
     */
    public function __construct(ilAuthFrontendCredentials $credentials, ?int $a_idp_id = null)
    {
        parent::__construct($credentials);

        if (null === $a_idp_id || 0 === $a_idp_id) {
            $this->idp = ilSamlIdp::getFirstActiveIdp();
        } else {
            $this->idp = ilSamlIdp::getInstanceByIdpId($a_idp_id);
        }

        if ($credentials instanceof ilAuthFrontendCredentialsSaml) {
            $this->attributes = $credentials->getAttributes();
            $this->return_to = $credentials->getReturnTo();
        }
    }

    /**
     * @throws ilException
     */
    private function determineUidFromAttributes() : void
    {
        if (
            !array_key_exists($this->idp->getUidClaim(), $this->attributes) ||
            !is_array($this->attributes[$this->idp->getUidClaim()]) ||
            !array_key_exists(0, $this->attributes[$this->idp->getUidClaim()]) ||
            0 === strlen($this->attributes[$this->idp->getUidClaim()][0])
        ) {
            throw new ilException(sprintf(
                'Could not find unique SAML attribute for the configured identifier: %s',
                print_r($this->idp->getUidClaim(), true)
            ));
        }

        $this->uid = $this->attributes[$this->idp->getUidClaim()][0];
    }

    /**
     * @inheritdoc
     */
    public function doAuthentication(ilAuthStatus $status)
    {
        if (!is_array($this->attributes) || 0 === count($this->attributes)) {
            $this->getLogger()->warning('Could not parse any attributes from SAML response.');
            $this->handleAuthenticationFail($status, 'err_wrong_login');

            return false;
        }

        try {
            $this->determineUidFromAttributes();

            return $this->handleSamlAuth($status);
        } catch (ilException $e) {
            $this->getLogger()->warning($e->getMessage());
            $this->handleAuthenticationFail($status, 'err_wrong_login');

            return false;
        }
    }

    /**
     * @param ilAuthStatus $status
     * @return bool
     */
    public function handleSamlAuth(ilAuthStatus $status) : bool
    {
        $update_auth_mode = false;

        ilLoggerFactory::getLogger('auth')->debug(sprintf(
            'Login observer called for SAML authentication request of ext_account "%s" and auth_mode "%s".',
            $this->uid,
            $this->getUserAuthModeName()
        ));
        ilLoggerFactory::getLogger('auth')->debug(sprintf('Target set to: %s', print_r($this->return_to, true)));
        ilLoggerFactory::getLogger('auth')->debug(sprintf(
            'Trying to find ext_account "%s" for auth_mode "%s".',
            $this->uid,
            $this->getUserAuthModeName()
        ));

        $internal_account = ilObjUser::_checkExternalAuthAccount(
            $this->getUserAuthModeName(),
            $this->uid,
            false
        );

        if (!is_string($internal_account) || 0 === strlen($internal_account)) {
            $update_auth_mode = true;

            ilLoggerFactory::getLogger('auth')->debug(sprintf(
                'Could not find ext_account "%s" for auth_mode "%s".',
                $this->uid,
                $this->getUserAuthModeName()
            ));

            $fallback_auth_mode = 'local';
            ilLoggerFactory::getLogger('auth')->debug(sprintf(
                'Trying to find ext_account "%s" for auth_mode "%s".',
                $this->uid,
                $fallback_auth_mode
            ));
            $internal_account = ilObjUser::_checkExternalAuthAccount($fallback_auth_mode, $this->uid, false);

            $defaultAuth = AUTH_LOCAL;
            if ($GLOBALS['DIC']['ilSetting']->get('auth_mode')) {
                $defaultAuth = $GLOBALS['DIC']['ilSetting']->get('auth_mode');
            }

            if ((!is_string($internal_account) || 0 === strlen($internal_account)) && ($defaultAuth == AUTH_LOCAL || $defaultAuth == $this->getTriggerAuthMode())) {
                ilLoggerFactory::getLogger('auth')->debug(sprintf(
                    'Could not find ext_account "%s" for auth_mode "%s".',
                    $this->uid,
                    $fallback_auth_mode
                ));

                $fallback_auth_mode = 'default';
                ilLoggerFactory::getLogger('auth')->debug(sprintf(
                    'Trying to find ext_account "%s" for auth_mode "%s".',
                    $this->uid,
                    $fallback_auth_mode
                ));
                $internal_account = ilObjUser::_checkExternalAuthAccount($fallback_auth_mode, $this->uid, false);
            }
        }

        if (is_string($internal_account) && strlen($internal_account) > 0) {
            ilLoggerFactory::getLogger('auth')->debug(sprintf(
                'Found user "%s" for ext_account "%s" in ILIAS database.',
                $internal_account,
                $this->uid
            ));

            if ($this->idp->isSynchronizationEnabled()) {
                ilLoggerFactory::getLogger('auth')->debug(sprintf(
                    'SAML user synchronisation is enabled, so update existing user "%s" with ext_account "%s".',
                    $internal_account,
                    $this->uid
                ));
                $internal_account = $this->importUser($internal_account, $this->uid, $this->attributes);
            }

            if ($update_auth_mode) {
                $usr_id = ilObjUser::_loginExists($internal_account);
                if ($usr_id > 0) {
                    ilObjUser::_writeAuthMode($usr_id, $this->getUserAuthModeName());
                    ilLoggerFactory::getLogger('auth')->debug(sprintf(
                        'SAML Switched auth_mode of user with login "%s" and ext_account "%s" to "%s".',
                        $internal_account,
                        $this->uid,
                        $this->getUserAuthModeName()
                    ));
                } else {
                    ilLoggerFactory::getLogger('auth')->debug(sprintf(
                        'SAML Could not switch auth_mode of user with login "%s" and ext_account "%s" to "%s".',
                        $internal_account,
                        $this->uid,
                        $this->getUserAuthModeName()
                    ));
                }
            }

            ilLoggerFactory::getLogger('auth')->debug(sprintf(
                'Authentication succeeded: Found internal login "%s for ext_account "%s" and auth_mode "%s".',
                $internal_account,
                $this->uid,
                $this->getUserAuthModeName()
            ));

            $status->setStatus(ilAuthStatus::STATUS_AUTHENTICATED);
            $status->setAuthenticatedUserId(ilObjUser::_lookupId($internal_account));
            ilSession::set('used_external_auth', true);

            return true;
        } else {
            ilLoggerFactory::getLogger('auth')->debug(sprintf(
                'Could not find an existing user for ext_account "%s" for any relevant auth_mode.',
                $this->uid
            ));
            if ($this->idp->isSynchronizationEnabled()) {
                ilLoggerFactory::getLogger('auth')->debug(sprintf(
                    'SAML user synchronisation is enabled, so determine action for ext_account "%s" and auth_mode "%s".',
                    $this->uid,
                    $this->getUserAuthModeName()
                ));
                if ($this->idp->isAccountMigrationEnabled() && !$this->force_new_account) {
                    ilSession::set('tmp_attributes', $this->attributes);
                    ilSession::set('tmp_return_to', $this->return_to);

                    ilLoggerFactory::getLogger('auth')->debug(sprintf(
                        'Account migration is enabled, so redirecting ext_account "%s" to account migration screen.',
                        $this->uid
                    ));

                    $this->setExternalAccountName($this->uid);
                    $status->setStatus(ilAuthStatus::STATUS_ACCOUNT_MIGRATION_REQUIRED);

                    return false;
                }

                $new_name = $this->importUser(null, $this->uid, $this->attributes);
                ilLoggerFactory::getLogger('auth')->debug(sprintf(
                    'Created new user account with login "%s" and ext_account "%s".',
                    $new_name,
                    $this->uid
                ));

                ilSession::set('tmp_attributes', null);
                ilSession::set('tmp_return_to', null);
                ilSession::set('used_external_auth', true);

                if (strlen($this->return_to)) {
                    $_GET['target'] = $this->return_to;
                }

                $status->setStatus(ilAuthStatus::STATUS_AUTHENTICATED);
                $status->setAuthenticatedUserId(ilObjUser::_lookupId($new_name));

                return true;
            } else {
                ilLoggerFactory::getLogger('auth')->debug("SAML user synchronisation is not enabled, auth failed.");
                $this->handleAuthenticationFail($status, 'err_auth_saml_no_ilias_user');

                return false;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function migrateAccount(ilAuthStatus $status)
    {
    }

    /**
     * @inheritdoc
     */
    public function createNewAccount(ilAuthStatus $status)
    {
        if (
            0 === strlen($this->getCredentials()->getUsername()) ||
            !is_array(ilSession::get('tmp_attributes')) ||
            0 === count(ilSession::get('tmp_attributes'))
        ) {
            $this->getLogger()->warning('Cannot find user id for external account: ' . $this->getCredentials()->getUsername());
            $this->handleAuthenticationFail($status, 'err_wrong_login');

            return false;
        }

        $this->uid = $this->getCredentials()->getUsername();
        $this->attributes = ilSession::get('tmp_attributes');
        $this->return_to = ilSession::get('tmp_return_to');

        $this->force_new_account = true;

        return $this->handleSamlAuth($status);
    }

    /**
     * Set external account name
     * @param string $a_name
     */
    public function setExternalAccountName(string $a_name) : void
    {
        $this->migration_account = $a_name;
    }

    /**
     * @inheritdoc
     */
    public function getExternalAccountName()
    {
        return $this->migration_account;
    }

    /**
     * @inheritdoc
     */
    public function getTriggerAuthMode()
    {
        return AUTH_SAML . '_' . $this->idp->getIdpId();
    }

    /**
     * @inheritdoc
     */
    public function getUserAuthModeName()
    {
        return 'saml_' . $this->idp->getIdpId();
    }

    /**
     * @param string|null $a_internal_login
     * @param string $a_external_account
     * @param array $a_user_data
     * @return string
     */
    public function importUser(?string $a_internal_login, string $a_external_account, array $a_user_data = [])
    {
        $mapping = new ilExternalAuthUserAttributeMapping('saml', $this->idp->getIdpId());

        $xml_writer = new ilXmlWriter();
        $xml_writer->xmlStartTag('Users');
        if (null === $a_internal_login) {
            $login = $a_user_data[$this->idp->getLoginClaim()][0];
            $login = ilAuthUtils::_generateLogin($login);

            $xml_writer->xmlStartTag('User', ['Action' => 'Insert']);
            $xml_writer->xmlElement('Login', [], $login);

            $xml_writer->xmlElement('Role', [
                'Id' => $this->idp->getDefaultRoleId(),
                'Type' => 'Global',
                'Action' => 'Assign'
            ]);

            $xml_writer->xmlElement('Active', [], "true");
            $xml_writer->xmlElement('TimeLimitOwner', [], USER_FOLDER_ID);
            $xml_writer->xmlElement('TimeLimitUnlimited', [], 1);
            $xml_writer->xmlElement('TimeLimitFrom', [], time());
            $xml_writer->xmlElement('TimeLimitUntil', [], time());
            $xml_writer->xmlElement(
                'AuthMode',
                ['type' => $this->getUserAuthModeName()],
                $this->getUserAuthModeName()
            );
            $xml_writer->xmlElement('ExternalAccount', [], $a_external_account);

            $mapping = new ilExternalAuthUserCreationAttributeMappingFilter($mapping);
        } else {
            $login = $a_internal_login;
            $usr_id = ilObjUser::_lookupId($a_internal_login);

            $xml_writer->xmlStartTag('User', ['Action' => 'Update', 'Id' => $usr_id]);

            $loginClaim = $a_user_data[$this->idp->getLoginClaim()][0];
            if ($login !== $loginClaim) {
                $login = ilAuthUtils::_generateLogin($loginClaim);
                $xml_writer->xmlElement('Login', [], $login);
            }

            $mapping = new ilExternalAuthUserUpdateAttributeMappingFilter($mapping);
        }

        foreach ($mapping as $rule) {
            try {
                $attributeValueParser = new ilSamlMappedUserAttributeValueParser($rule, $a_user_data);
                $value = $attributeValueParser->parse();
                $this->buildUserAttributeXml($xml_writer, $rule, $value);
            } catch (ilSamlException $e) {
                $this->getLogger()->warning($e->getMessage());
                continue;
            }
        }

        $xml_writer->xmlEndTag('User');
        $xml_writer->xmlEndTag('Users');

        ilLoggerFactory::getLogger('auth')->debug(sprintf(
            'Started import of user "%s" with ext_account "%s" and auth_mode "%s".',
            $login,
            $a_external_account,
            $this->getUserAuthModeName()
        ));
        $importParser = new ilUserImportParser();
        $importParser->setXMLContent($xml_writer->xmlDumpMem(false));
        $importParser->setRoleAssignment([
            $this->idp->getDefaultRoleId() => $this->idp->getDefaultRoleId(),
        ]);
        $importParser->setFolderId(USER_FOLDER_ID);
        $importParser->setUserMappingMode(IL_USER_MAPPING_ID);
        $importParser->startParsing();

        return $login;
    }

    /**
     * @param ilXmlWriter $xml_writer
     * @param ilExternalAuthUserAttributeMappingRule $rule
     * @param string $value
     */
    protected function buildUserAttributeXml(
        ilXmlWriter $xml_writer,
        ilExternalAuthUserAttributeMappingRule $rule,
        string $value
    ) {
        switch (strtolower($rule->getAttribute())) {
            case 'gender':
                switch (strtolower($value)) {
                    case 'n':
                    case 'neutral':
                        $xml_writer->xmlElement('Gender', [], 'n');
                        break;

                    case 'm':
                    case 'male':
                        $xml_writer->xmlElement('Gender', [], 'm');
                        break;

                    case 'f':
                    case 'female':
                    default:
                        $xml_writer->xmlElement('Gender', [], 'f');
                        break;
                }
                break;

            case 'firstname':
                $xml_writer->xmlElement('Firstname', [], $value);
                break;

            case 'lastname':
                $xml_writer->xmlElement('Lastname', [], $value);
                break;

            case 'email':
                $xml_writer->xmlElement('Email', [], $value);
                break;

            case 'second_email':
                $xml_writer->xmlElement('SecondEmail', [], $value);
                break;

            case 'institution':
                $xml_writer->xmlElement('Institution', [], $value);
                break;

            case 'department':
                $xml_writer->xmlElement('Department', [], $value);
                break;

            case 'hobby':
                $xml_writer->xmlElement('Hobby', [], $value);
                break;

            case 'title':
                $xml_writer->xmlElement('Title', [], $value);
                break;

            case 'street':
                $xml_writer->xmlElement('Street', [], $value);
                break;

            case 'city':
                $xml_writer->xmlElement('City', [], $value);
                break;

            case 'zipcode':
                $xml_writer->xmlElement('PostalCode', [], $value);
                break;

            case 'country':
                $xml_writer->xmlElement('Country', [], $value);
                break;

            case 'phone_office':
                $xml_writer->xmlElement('PhoneOffice', [], $value);
                break;

            case 'phone_home':
                $xml_writer->xmlElement('PhoneHome', [], $value);
                break;

            case 'phone_mobile':
                $xml_writer->xmlElement('PhoneMobile', [], $value);
                break;

            case 'fax':
                $xml_writer->xmlElement('Fax', [], $value);
                break;

            case 'referral_comment':
                $xml_writer->xmlElement('Comment', [], $value);
                break;

            case 'matriculation':
                $xml_writer->xmlElement('Matriculation', [], $value);
                break;

            case 'birthday':
                $xml_writer->xmlElement('Birthday', [], $value);
                break;

            default:
                if (substr($rule->getAttribute(), 0, 4) !== 'udf_') {
                    break;
                }

                $udf_data = explode('_', $rule->getAttribute());
                if (!isset($udf_data[1])) {
                    break;
                }

                $definition = ilUserDefinedFields::_getInstance()->getDefinition($udf_data[1]);
                $xml_writer->xmlElement(
                    'UserDefinedField',
                    ['Id' => $definition['il_id'], 'Name' => $definition['field_name']],
                    $value
                );
                break;
        }
    }
}
