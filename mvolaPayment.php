<?php

if (!defined('_PS_VERSION_')) {
    exit;
}


class MvolaPayment extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'mvolapayment';
        $this->tab = 'payments_gateways';
        $this->version = '0.0.1';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Iteo';
        $this->controllers = array('validation');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mvola Payment');
        $this->description = $this->l('Accept payments with Mvola');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Mvola payment module?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (
            !parent::install()
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
        ) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $payment_options = [
            $this->getEmbeddedPaymentOption()
        ];

        return $payment_options;
    }

    protected function generateForm()
    {
        $formattedPhoneNumber = preg_replace("/[^0-9]/", "", Configuration::get('MVOLA_PAYMENT_MVOLA_NUMBER'));
        $formattedPhoneNumber = substr($formattedPhoneNumber, 0, 2) . ' ' . substr($formattedPhoneNumber, 2, 2) . ' ' . substr($formattedPhoneNumber, 4, 3) . ' ' . substr($formattedPhoneNumber, 7, 2);
      
        $this->context->smarty->assign([
            'client_number' => $formattedPhoneNumber,
            'owner_name' => Configuration::get('MVOLA_PAYMENT_CLIENT_OWNER'),
            'mvola_number'=>Tools::getValue('mvola_number')
        ]);

        return $this->context->smarty->fetch('module:mvolapayment/views/templates/front/payment.tpl');
    }

    public function getEmbeddedPaymentOption()
    {
        $embeddedOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $embeddedOption->setCallToActionText($this->l(' Pay withMvola'))
                       ->setForm($this->generateForm())
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));

        return $embeddedOption;
    }


    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if (in_array($state, [Configuration::get('PS_OS_ERROR'), Configuration::get('PS_OS_CANCELED')])) {
            $this->smarty->assign('status', 'error');
        }

        $this->smarty->assign([
            'id_order' => $params['order']->id,
            'reference' => $params['order']->reference,
            'params' => $params,
            'link' => $this->context->link,
        ]);

        return $this->display(__FILE__, '/views/templates/front/payment_return.tpl');
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitMvolaPaymentModule')) {
            $mvola_number = Tools::getValue('MVOLA_PAYMENT_MVOLA_NUMBER');
            $client_id = Tools::getValue('MVOLA_PAYMENT_CLIENT_ID');
            $client_secret = Tools::getValue('MVOLA_PAYMENT_CLIENT_SECRET');
            $ownerName = Tools::getValue('MVOLA_PAYMENT_CLIENT_OWNER');
            $debug = Tools::getValue('MVOLA_PAYMENT_DEBUG');
            if (!$mvola_number || !$client_id || !$client_secret) {
                $output .= $this->displayError($this->l('All fields are required'));
            } else {
                Configuration::updateValue('MVOLA_PAYMENT_MVOLA_NUMBER', $mvola_number);
                Configuration::updateValue('MVOLA_PAYMENT_CLIENT_ID', $client_id);
                Configuration::updateValue('MVOLA_PAYMENT_CLIENT_SECRET', $client_secret);
                Configuration::updateValue('MVOLA_PAYMENT_CLIENT_OWNER', $ownerName);
                Configuration::updateValue('MVOLA_PAYMENT_DEBUG', $debug);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Mvola Payment Configuration'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Owner name'),
                        'name' => 'MVOLA_PAYMENT_CLIENT_OWNER',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Mvola Number'),
                        'name' => 'MVOLA_PAYMENT_MVOLA_NUMBER',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Client ID'),
                        'name' => 'MVOLA_PAYMENT_CLIENT_ID',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Client Secret'),
                        'name' => 'MVOLA_PAYMENT_CLIENT_SECRET',
                        'required' => true
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Mode'),
                        'name' => 'MVOLA_PAYMENT_DEBUG',
                        'is_bool'   => true,
                        'values'    => array(                                
                            array(
                                'id'    => 'active_on',                           
                                'value' => 1,                                  
                                'label' => $this->l('Production')    
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Developpement')
                            )
                        )

                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMvolaPaymentModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        $values = array();

        $values['MVOLA_PAYMENT_MVOLA_NUMBER'] = Tools::getValue('MVOLA_PAYMENT_MVOLA_NUMBER', Configuration::get('MVOLA_PAYMENT_MVOLA_NUMBER'));
        $values['MVOLA_PAYMENT_CLIENT_ID'] = Tools::getValue('MVOLA_PAYMENT_CLIENT_ID', Configuration::get('MVOLA_PAYMENT_CLIENT_ID'));
        $values['MVOLA_PAYMENT_CLIENT_SECRET'] = Tools::getValue('MVOLA_PAYMENT_CLIENT_SECRET', Configuration::get('MVOLA_PAYMENT_CLIENT_SECRET'));
        $values['MVOLA_PAYMENT_CLIENT_OWNER'] = Tools::getValue('MVOLA_PAYMENT_CLIENT_OWNER', Configuration::get('MVOLA_PAYMENT_CLIENT_OWNER'));
        $values['MVOLA_PAYMENT_DEBUG'] = Tools::getValue('MVOLA_PAYMENT_DEBUG', Configuration::get('MVOLA_PAYMENT_DEBUG'));

        if (isset($_POST['submitModule'])) {
            $number = Tools::getValue('MVOLA_PAYMENT_MVOLA_NUMBER');
            if (!preg_match('/^034\d{7}$/', $number)) {
                $this->_errors[] = $this->l('MVola number is invalid');
            }
        }

        return $values;
    }
}
