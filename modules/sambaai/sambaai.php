<?php
/**
*  @author    Martin Tomasek
*  @copyright DiffSolutions, s.r.o.
*  @license   https://creativecommons.org/licenses/by-sa/4.0/ CC BY-SA 4.0
*/

if (!defined('_PS_VERSION_')) {
    exit;
}


function generateRandomString($length = 10)
{
    $x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return Tools::substr(str_shuffle(str_repeat($x, ceil($length/Tools::strlen($x)))), 1, $length);
}

function presentProduct($product)
{
    $p = array(
        'cart_quantity' => $product['cart_quantity'],
    'id_product' => $product['id_product'],
    'id_product_attribute' => $product['id_product_attribute']
    );
    return $p;
}

class SambaAi extends Module
{
    public function __construct()
    {
        /*
            throw new Exception('Constructor start');
         */
        $this->name = 'sambaai';
        $this->tab = 'emailing';
        $this->version = '1.1.1';
        $this->author = 'Samba.ai';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '1.7');
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Samba.ai connector');
        $this->description = $this->l('Samba.ai online marketing a.i. automation connector.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        #Configuration::get('SAMBA_TP'))
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        $this->registerHook('displayBeforeBodyClosingTag');
        $this->registerHook('displayHome');
        $this->registerHook('displayOrderConfirmation');
        $this->registerHook('registerGDPRConsent');

        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $key=generateRandomString(20);

        if (empty(Configuration::get('SAMBA_KEY', $key))) {
            Configuration::updateValue('SAMBA_KEY', $key);
        }

        return true;
    }

    public function uninstall(){
        if (!parent::uninstall()) {
            return false;
        }
/*
      Delete created fields in configuration DB
*/
        if (!Configuration::deleteByName('SAMBA_TP')
          || !Configuration::deleteByName('SAMBA_ORDER_CREATE')
          || !Configuration::deleteByName('SAMBA_ORDER_FINISHED')
          || !Configuration::deleteByName('SAMBA_ORDER_CANCLED')
          || !Configuration::deleteByName('SAMBA_SHOP')
          || !Configuration::deleteByName('SAMBA_LANG')
      // Current Date -2 years
          || !Configuration::deleteByName('SAMBA_DATE')
          || !Configuration::deleteByName('SAMBA_WIDGET_STYLE')){
            #!Configuration::deleteByName('SAMBA_WIDGET')) {
            return false;
        }

        return true;
    }

    public function hookdisplayBeforeBodyClosingTag()
    {

        //GDPR compliance:
        $optin = $this->context->customer->optin;
        if (!isset($optin)) {
            $optin = 1;
        } else {
            $optin = (int)$optin;
        }


        //$optout = (!$optin);
        $optout = false;
        if (array_key_exists('HTTP_DNT', $_SERVER)) {
            $dnt = $_SERVER['HTTP_DNT'];
            if ($dnt == '1') {
                $optout = true;
            } #GDPR
        }

        if ($optout) {
            return '<!-- SAMBA.AI tracking code not active due to user request -->';
        } //GDPR

        $tp = Configuration::get('SAMBA_TP');
        $widget = Configuration::get('SAMBA_WIDGET_STYLE');
        if (!$tp) {
            return '<!-- SAMBA.AI tracking code will go here - please set your trackpoint number -->';
        }
        $id = (int)$this->context->customer->id;
        $this->context->smarty->assign(
            array( 'trackpoint'  => $tp,
                   'customer_id' => $id)
        );
        $this->context->smarty->assign(
            array( 'samba_widget_style'  => $widget)
        );
        return $this->display(__FILE__, 'views/templates/front/tracker.tpl');
    }

    public function hookDisplayHome(){
      $widget = Configuration::get('SAMBA_WIDGET_STYLE');
      if ($widget) {
        $this->context->controller->addJS('modules/sambaai/js/swiperbundle.min.js');
        $this->context->controller->addCSS('modules/sambaai/css/swiperbundle.min.css');
        $this->context->controller->addCSS('modules/sambaai/css/custom.css');
        $this->context->controller->addJS('modules/sambaai/js/sambaw.js');
        Media::addJsDef(array('sambaai' => array('samba_widget_style' => $widget)));

        return $this->display(__FILE__, 'views/templates/front/widget.tpl');
      }
    }

    public function hookHome()
    {
    }


    public function hookdisplayOrderConfirmation($params)
    {
        #$presenter = new OrderPresenter();
        $order = $params['order'];
        #$cart = new Cart($order->id_cart);
        $orderProducts = $order->getCartProducts();
        $this->context->smarty->assign(
            array(
            #'products' => $presenter->present($params['order']),
            'products' => $orderProducts
            )
        );

        return $this->display(__FILE__, 'views/templates/front/thankyou.tpl');
    }



    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $form_values = $this->getConfigurationFormValues();
            $this->removeOldValues($form_values);
            $all_statuses = $this->getOrderStatus();
    /*
    Handle Checkboxes
    */
            $checkbox_samba_create = array();
            $checkbox_samba_cancled = array();
            $checkbox_samba_finished = array();
            foreach ($all_statuses as $chbx_samba) {
              if (Tools::getValue('SAMBA_ORDER_CREATE_'.(int)$chbx_samba['id_order_state']))
               $checkbox_samba_create[] = $chbx_samba['id_order_state'];

              if (Tools::getValue('SAMBA_ORDER_FINISHED_'.(int)$chbx_samba['id_order_state']))
                $checkbox_samba_finished[] = $chbx_samba['id_order_state'];

              if (Tools::getValue('SAMBA_ORDER_CANCLED_'.(int)$chbx_samba['id_order_state']))
               $checkbox_samba_cancled[] = $chbx_samba['id_order_state'];
            }
    /*
    Update Checkboxes to Configuration DB
    */
            Configuration::updateValue('SAMBA_ORDER_FINISHED', implode(',', $checkbox_samba_finished));
            Configuration::updateValue('SAMBA_ORDER_CANCLED', implode(',', $checkbox_samba_cancled));
            Configuration::updateValue('SAMBA_ORDER_CREATE', implode(',', $checkbox_samba_create));

            $tp = Tools::getValue('SAMBA_TP');
            if (!$tp) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('SAMBA_TP', $tp);
                #$widget = (int) (Tools::getValue('SAMBA_WIDGET'));
                #Configuration::updateValue('SAMBA_WIDGET', $widget);
                $shop = (int) (Tools::getValue('SAMBA_SHOP'));
                Configuration::updateValue('SAMBA_SHOP', $shop);
                $lang = (int) (Tools::getValue('SAMBA_LANG'));
                Configuration::updateValue('SAMBA_LANG', $lang);
                $date =  (Tools::getValue('SAMBA_DATE'));
                Configuration::updateValue('SAMBA_DATE', $date);
                $widget_style =  (Tools::getValue('SAMBA_WIDGET_STYLE'));
                Configuration::updateValue('SAMBA_WIDGET_STYLE', $widget_style);

                #$key = (Tools::getValue('SAMBA_KEY'));
                #Configuration::updateValue('SAMBA_KEY', $key);


                $output .= $this->displayConfirmation($this->l('Configuration updated'));
            }
        }
        return $output.$this->displayForm();
    }

    protected static function genFeedUri($shopuri, $key, $feedname)
    {
        return $shopuri."modules/sambaai/feed2.php?key=".
        $key."&feed=".$feedname;
    }

    public function displayForm()
    {
        $output = null;

        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $shops_options = array();
        foreach ($this->shops() as $s) {
            $shops_options[] = array('value'=> $s['id'], 'text' => $s['name']);
        }

        $lang_options = array();
        foreach (Language::getLanguages(true) as $l) { #get all active languages
            $lang_options[] = array('value'=> $l['id_lang'], 'text' => $l['name']);
        }


        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
        'legend' => array(
            'title' => $this->l('Settings'),
        ),
        'input' => array(
            array(
                'type' => 'text',
                'label' => $this->l('Trackpoint'),
                'name' => 'SAMBA_TP',
                'desc' => $this->l('Trackpoint from samba.ai.'),
                'size' => 20,
                'required' => true
            ),
        /*
        array(


        'type' => 'select',
        'name' => 'SAMBA_WIDGET',
        'label' => $this->l('Recommeder widget'),
        'options' => array(
            'query' => array(
                array(
                    'value' => 0,
                    'text' => $this->l('Disabled'),
                ),
                array(
                    'value' => '1',
                    'text' => $this->l('Enabled'),
                ),
            ),
            'id' => 'value',
            'name' => 'text'
        )
        ),*/
        array(

        'type' => 'select',
        'name' => 'SAMBA_SHOP',
        'label' => $this->l('Choose shop for exports: '),
        'desc' => $this->l('Which shop export to samba.ai.'),
        'options' => array(
            'query' => $shops_options,
            'id' => 'value',
            'name' => 'text'
        )
        ),
        array(

        'type' => 'select',
        'name' => 'SAMBA_WIDGET_STYLE',
        'label' => $this->l('Widget recommended products'),
        'desc' => $this->l('Disable or choose CSS style'),
        'options' => array(
            'query' => array(
                array(
                    'value' => '0',
                    'text' => $this->l('Disabled'),
                ),
                array(
                    'value' => '1',
                    'text' => $this->l('Style 1'),
                ),
                array(
                    'value' => '2',
                    'text' => $this->l('Style 2'),
                ),
            ),
            'id' => 'value',
            'name' => 'text'
        )
        ),
        array(
            'type' => 'checkbox',
            'label' => $this->l('Select statuses for Samba Create'),
            'name' => 'SAMBA_ORDER_CREATE',
            'hint' => $this->l('Choose the order status'),
            'is_default' => 0,
            'multiple' => true,
            'expand' => array(
                'default' => 'show',
                'show' => array(
                    'icon' => 'gear',
                    'text' => $this->l('Show CREATE'),
                ),
                'hide' => array(
                    'icon' => 'gear',
                    'text' => $this->l('Hide CREATE'),
                )
            ),
            'values' => array(
                'query' => $this->getOrderStatus(),
                'id' => 'id_order_state',
                'name' => 'name'
            )
        ),
        array(
            'type' => 'checkbox',
            'label' => $this->l('Select statuses for Samba Finished'),
            'name' => 'SAMBA_ORDER_FINISHED',
            'hint' => $this->l('Choose the order status'),
            'is_default' => 0,
            'multiple' => true,
            'expand' => array(
                'default' => 'show',
                'show' => array(
                    'icon' => 'gear',
                    'text' => $this->l('Show FINISHED'),
                ),
                'hide' => array(
                    'icon' => 'gear',
                    'text' => $this->l('Hide FINISHED'),
                )
            ),
            'values' => array(
                'query' => $this->getOrderStatus(),
                'id' => 'id_order_state',
                'name' => 'name'
            )
        ),
        array(
            'type' => 'checkbox',
            'label' => $this->l('Select statuses for Samba Cancled'),
            'name' => 'SAMBA_ORDER_CANCLED',
            'hint' => $this->l('Choose the order status'),
            'is_default' => 0,
            'multiple' => true,
            'expand' => array(
                'default' => 'show',
                'show' => array(
                    'icon' => 'gear',
                    'text' => $this->l('Show CANCLED'),
                ),
                'hide' => array(
                    'icon' => 'gear',
                    'text' => $this->l('Hide CANCLED'),
                )
            ),
            'values' => array(
                'query' => $this->getOrderStatus(),
                'id' => 'id_order_state',
                'name' => 'name'
            )
        ),
        array(


        'type' => 'select',
        'name' => 'SAMBA_LANG',
        'label' => $this->l('Choose language for exports: '),
        'desc' => $this->l('Language for emails and recommendations. All emails will be in this language.'),
        'options' => array(
            'query' => $lang_options,
            'id' => 'value',
            'name' => 'text'
        )
        ),
        /*
        array(
                'type' => 'text',
                'label' => $this->l('feed access key'),
                'name' => 'SAMBA_KEY',
                'size' => 20,
                'required' => true
        ),
        */

        array(
            'type' => 'date',
            'label' => $this->l('Send data from date:'),
            'name' => 'SAMBA_DATE',
            'desc' => $this->l('since when Samba is importing orders'),
            'size' => 20,
            'lang' => false,
            'required' => false
        ),

        array(
                'type' => 'textarea',
                'label' => $this->l('Samba feed URLs'),
                'name' => 'feeds',
                'lang' => false,
                'autoload_rte' => false,
                'hint' => 'Copy these to samba.',
                'cols' => 50,
        'rows' => 4,
            'id' => 'yt_feeds',
    ),
    array(
        "type" => "html",
        "name" => "yt_button",
        "html_content" => '<button type="button" onClick="javascript: copyToClipboard(document.getElementById(\'yt_feeds\').value)">Copy feed URLs</button>',
    ),
    ),
        'submit' => array(
            'title' => $this->l('Save'),
            'class' => 'btn btn-default pull-right'
        )
        );
        $helper = new HelperForm();
        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
        'save' =>
        array(
            'desc' => $this->l('Save'),
            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
            '&token='.Tools::getAdminTokenLite('AdminModules'),
        ),
        'back' => array(
            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Back to list')
        )
        );
        // Load current value to tpl_vars
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigurationFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );


        $this->context->smarty->assign(array(
            'dir' => $this->_path,
        ));
        $output = $output.$this->context->smarty->fetch($this->local_path.'views/templates/admin/config.tpl');
        //
        return $output.$helper->generateForm($fields_form);
    }

    public function shops()
    {
        $l = array();
        foreach (Shop::getShops() as $s) {
            $l[] = array( 'id' => $s['id_shop'], 'name' => $s['name'] );
        }
        return $l;
    }

    protected function getConfigurationFormValues()
    {
      $SAMBA_KEY=Configuration::get('SAMBA_KEY');
      $SHOP_URL = 'https://'.Configuration::get('PS_SHOP_DOMAIN_SSL').'/';
      $feeds = $this->genFeedUri($SHOP_URL, $SAMBA_KEY, 'products')."\n".
      $this->genFeedUri($SHOP_URL, $SAMBA_KEY, 'categories')."\n".
      $this->genFeedUri($SHOP_URL, $SAMBA_KEY, 'orders')."\n".
      $this->genFeedUri($SHOP_URL, $SAMBA_KEY, 'customers')."\n";
 /*
    Assign checkboxes to array's
 */
      $id_checkbox_samba_create = array();
      if ($chbx_samba_create_values = Configuration::get('SAMBA_ORDER_CREATE')){
        $chbx_samba_create_values = explode(',', Configuration::get('SAMBA_ORDER_CREATE'));
        foreach ($chbx_samba_create_values as $v_scr){
          $id_checkbox_samba_create['SAMBA_ORDER_CREATE_'.(int)$v_scr] = true;
        }
      }
      $id_checkbox_samba_cancled = array();
      if ($chbx_samba_cancled_values = Configuration::get('SAMBA_ORDER_CANCLED')){
        $chbx_samba_cancled_values = explode(',', Configuration::get('SAMBA_ORDER_CANCLED'));
        foreach ($chbx_samba_cancled_values as $v_sca){
          $id_checkbox_samba_cancled['SAMBA_ORDER_CANCLED_'.(int)$v_sca] = true;
        }
      }
      $id_checkbox_samba_finished = array();
      if ($chbx_samba_finished_values = Configuration::get('SAMBA_ORDER_FINISHED')){
        $chbx_samba_finished_values = explode(',', Configuration::get('SAMBA_ORDER_FINISHED'));
        foreach ($chbx_samba_finished_values as $v_sf){
            $id_checkbox_samba_finished['SAMBA_ORDER_FINISHED_'.(int)$v_sf] = true;
        }
      }
        $return = array(
            'SAMBA_TP' => Configuration::get('SAMBA_TP'),
            'SAMBA_SHOP' => Configuration::get('SAMBA_SHOP'),
            'SAMBA_LANG' => Configuration::get('SAMBA_LANG'),
            'SAMBA_DATE' => Configuration::get('SAMBA_DATE'),
            'SAMBA_WIDGET_STYLE' => Configuration::get('SAMBA_WIDGET_STYLE'),
            'feeds' => $feeds,
        );

        /*
          Marge all configuration array's
        */
       $return = array_merge($return, $id_checkbox_samba_create, $id_checkbox_samba_cancled,$id_checkbox_samba_finished );
       // $ar = json_encode($return);
       // $this->slack_send($ar,'#test');
       // print_r($return);
       // $return = array_merge($return, $id_checkbox_samba_cancled);
       // $return = array_merge($return, $id_checkbox_samba_finished);
      // echo "<pre>";
        // print_r($return);

        return $return;
    }

    public function getOrderStatus($addempty = true)
    {
        $statuses = OrderState::getOrderStates(Context::getContext()->language->id);
        $status = array();
        if (!$addempty) {
            return $statuses;
        } else {
            return array_merge($status, $statuses);
        }
    }
    public function removeOldValues($config)
    {
        foreach (array_keys($config) as $key) {
            Configuration::deleteByName($key);
        }
    }
    private function updateSelectedStatuses($statuses, $samba_order_type)
    {
        if (empty($statuses)) {
            $statuses = array();
        }
        // if (!is_array($statuses)) {
        //     $statuses = array($statuses);
        //     $statuses = array_merge($statuses, $this->getCarriersCodEof());
        // }
        $statuses = array_filter($statuses, 'strlen');
        $statuses = implode(',', $statuses);
        Configuration::updateValue($samba_order_type, $statuses);
    }
    private function slack_send($message, $channel)
    {
        $ch = curl_init("https://slack.com/api/chat.postMessage");
        $data = http_build_query([
            "token" => "xoxb-775383859264-2826902473234-RhKbmNqMs4RTHfgiaTz2tgSv",
            "channel" => $channel, //"#mychannel",
            "text" => $message, //"Hello, Foo-Bar channel message.",
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
