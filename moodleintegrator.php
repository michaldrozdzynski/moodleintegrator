<?php
/**
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Moodleintegrator extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'moodleintegrator';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Michał Drożdżyński';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Moodle Integrator');
        $this->description = $this->l('Integrate prestashop product with moodle course');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('MOODLE_ROLE_ID', 5);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('actionPaymentConfirmation');
    }

    public function uninstall()
    {
        Configuration::deleteByName('MOODLE_ROLE_ID');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitMoodleintegratorModule')) == true) {
            $this->postProcess();
        }

        if (Tools::isSubmit('addCourse')) {
            return $this->addCourse();
        }

        if (Tools::isSubmit('submitAddCourse')) {
            $productId = Tools::getValue('productId');
            $courseId = Tools::getValue('courseId');

            $db = Db::getInstance();
            $db->insert('moodleintegrator', [
                'id_product' => $productId,
                'moodle_course_id' => $courseId,
            ]);
        }

        if (Tools::isSubmit('deletemoodleintegrator')) {
            $id_moodleintegrator = Tools::getValue('id_moodleintegrator');
            $db = Db::getInstance();

            $db->delete('moodleintegrator', 'id_moodleintegrator = ' . $id_moodleintegrator);
        }

        return $this->renderForm() . $this->renderList();
    }

    protected function addCourse() {
        $groups = Group::getGroups(Context::getContext()->language->id);
        $options = [];
        $i = 0;
        foreach ($groups as $group) {
             $options[$i] = [
                'id_option' => $group['id_group'],
                'name' => $group['name'],
              ];
              $i++;
         }

            $fields_form = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Configuration'),
                        'icon' => 'icon-link',
                    ],
                    'input' => [
                        [
                            'type' => 'text',
                            'label' => $this->l('Product ID'),
                            'name' => 'productId',
                            'size' => 1,
                            'required' => true,
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Course ID'),
                            'name' => 'courseId',
                            'required' => true,
                        ]
                    ],
                    'submit' => [
                        'name' => 'submitAddCourse',
                        'title' => $this->trans('Save', [], 'Admin.Actions'),
                    ],
                ],
            ];
       

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    protected function renderList() {
        $query = 'SELECT * FROM  `' . _DB_PREFIX_ . 'moodleintegrator` WHERE 1=1';
        $moodleIntegrator = Db::getInstance()->executeS($query);

        $fields_list = array(
            'id_moodleintegrator' => array(
                'title' => "ID",
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'search' => false,
            ),
            'id_product' => array(
                'title' => $this->l('Product'),
                'orderby' => false,
                'class' => 'fixed-width-xxl',
                'search' => false,
                'callback' => 'displayProductName',
                'callback_object' => $this,
            ),
            'moodle_course_id' => array(
                'title' => $this->l('Moodle Course'),
                'orderby' => false,
                'class' => 'fixed-width-xs',
                'search' => false,
                'align' => 'center',
            ),
        );
  
        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->identifier = 'id_moodleintegrator';
        $helper->table = 'moodleintegrator';
        $helper->listTotal = count($moodleIntegrator);
        $helper->toolbar_btn['new'] = [
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name, 'addCourse' => true]),
            'desc' => $this->trans('Add New Criterion', [], 'Modules.Productcomments.Admin'),
        ];
        $helper->actions = ['delete'];
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->_default_pagination = 10;
        $helper->_pagination = array(5, 10, 50, 100);
        $helper->title = $this->l('Moodle Integrator');
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $page = ( $page = Tools::getValue( 'submitFilter' . $helper->table ) ) ? $page : 1;
        $pagination = ( $pagination = Tools::getValue( $helper->table . '_pagination' ) ) ? $pagination : 10;
        $content = $this->paginate_content( $moodleIntegrator, $page, $pagination );

        return $helper->generateList($content, $fields_list);
    }
    
    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMoodleintegratorModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    public function displayProductName($id) {
        $product = new Product($id, false, $this->context->language->id);

        return $product->name;
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function paginate_content( $content, $page = 1, $pagination = 10 ) {

        if( count($content) > $pagination ) {
             $content = array_slice( $content, $pagination * ($page - 1), $pagination );
        }
     
        return $content;
    }

    public function hookActionPaymentConfirmation($params) {
        $order = new Order((int) $params['id_order']);

        $id_customer = $order->id_customer;

        $customer = new Customer((int) $id_customer);

        $email = $customer->email;
        $firstname = $customer->firstname;
        $lastname = $customer->lastname;

        $orderDetails = OrderDetail::getList((int)$params['id_order']);

        $productFiles = [];

        foreach ($orderDetails as $detail) {
            $id_product = $detail['product_id'];

            $query = 'SELECT * FROM  `' . _DB_PREFIX_ . 'moodleintegrator` WHERE id_product = ' . $id_product;
            $moodleIntegrator = Db::getInstance()->getRow($query);
            
            if (count($moodleIntegrator) == 0) {
                continue;
            } else {
                $courseId = $moodleIntegrator['moodle_course_id'];
                $userId =  $this->checkUserExist($email);
                dump($userId);
                
                if ($userId === 0) {
                    $userId = $this->createUser($firstname, $lastname, $email);
                }

                $roleId = Configuration::get('MOODLE_ROLE_ID');

                $this->addUserToCourse($userId, $roleId, $courseId);
            }
        }
    }

    protected function addUserToCourse($userId, $roleId, $courseId) {
        $post = [
            'enrolments[0][roleid]' => $roleId,
            'enrolments[0][userid]' => $userId,
            'enrolments[0][courseid]' => $courseId,
        ];

        $ch = curl_init(Configuration::get('MOODLE_LINK') . '/webservice/rest/server.php?wstoken=' . Configuration::get('MOODLE_API_TOKEN') . '&wsfunction=enrol_manual_enrol_users');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        curl_exec($ch);
        curl_close($ch);
    }

    protected function createUser($firstname, $lastname, $email) {
        $post = [
            'users[0][createpassword]' => 1,
            'users[0][username]' => $email,
            'users[0][firstname]' => $firstname,
            'users[0][lastname]' => $lastname,
            'users[0][email]' => $email,
        ];
        
        $ch = curl_init(Configuration::get('MOODLE_LINK') . '/webservice/rest/server.php?wstoken=' . Configuration::get('MOODLE_API_TOKEN') . '&wsfunction=core_user_create_users');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        
        // execute!
        $data = curl_exec($ch);
        curl_close($ch);
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $data);
        $xml = new \SimpleXMLElement($response);
        $array = json_decode(json_encode((array)$xml), TRUE);
        
        
        $userId = $array['MULTIPLE']['SINGLE']['KEY'][0]['VALUE'];
    
        return $userId;
    }
    
    protected function checkUserExist($email) {
        $post = [
            'field' => 'email',
            'values[0]' => $email
        ];

        $url = Configuration::get('MOODLE_LINK') . '/webservice/rest/server.php?wstoken=' . Configuration::get('MOODLE_API_TOKEN') . "&wsfunction=core_user_get_users_by_field&field=email&values[0]=" . $email;
    
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
        $data = curl_exec($curl);
        curl_close($curl);
    
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $data);
        $xml = new \SimpleXMLElement($response);
        
        $array = json_decode(json_encode((array)$xml), TRUE);
        if (array_key_exists('SINGLE', $array['MULTIPLE'])) {
            $userId = $array['MULTIPLE']['SINGLE']['KEY'][0]['VALUE'];

            return $userId;
        } else return 0;
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'MOODLE_API_TOKEN',
                        'label' => $this->l('Moodle API Token'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'MOODLE_LINK',
                        'label' => $this->l('Moodle Link'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'MOODLE_API_TOKEN' => Configuration::get('MOODLE_API_TOKEN'),
            'MOODLE_LINK' => Configuration::get('MOODLE_LINK'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }
}
