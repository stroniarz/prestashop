<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class StrDuoBanerTop extends Module implements WidgetInterface
{
    /**
     * @var string Name of the module running on PS 1.6.x. Used for data migration.
     */
    public const PS_16_EQUIVALENT_MODULE = 'blockbanner';

    private $templateFile;

    public function __construct()
    {
        $this->name = 'strduobanertop';
        $this->version = '2.1.2';
        $this->author = 'Stroniarz.pl';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Dual top banner', array(), 'Modules.StrDuoBanerTop.Admin');
        $this->description = $this->trans('Add a banner to the homepage of your store to highlight your sales and new products in a visual and friendly way.', array(), 'Modules.StrDuoBanerTop.Admin');

        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);

        $this->templateFile = 'module:strduobanertop/strduobanertop.tpl';
    }

    public function install()
    {
        return (parent::install() &&
            $this->registerHook('displayHome') &&
            $this->registerHook('actionObjectLanguageAddAfter') &&
            $this->installFixtures() &&
            $this->uninstallPrestaShop16Module() &&
            $this->disableDevice(Context::DEVICE_MOBILE));
    }

    /**
     * Migrate data from 1.6 equivalent module (if applicable), then uninstall
     */
    public function uninstallPrestaShop16Module()
    {
        if (!Module::isInstalled(self::PS_16_EQUIVALENT_MODULE)) {
            return true;
        }

        // Data migration
        Configuration::updateValue('BANNERTOP1_IMG', Configuration::getInt('BLOCKBANNERTOP1_IMG'));
        Configuration::updateValue('BANNERTOP1_LINK', Configuration::getInt('BLOCKBANNERTOP1_LINK'));


        Configuration::updateValue('BANNERTOP2_IMG', Configuration::getInt('BLOCKBANNERTOP2_IMG'));

        $oldModule = Module::getInstanceByName(self::PS_16_EQUIVALENT_MODULE);
        if ($oldModule) {
            $oldModule->uninstall();
        }
        return true;
    }

    public function hookActionObjectLanguageAddAfter($params)
    {
        return $this->installFixture((int)$params['object']->id, Configuration::get('BANNERTOP1_IMG', (int)Configuration::get('PS_LANG_DEFAULT')));
    }

    protected function installFixtures()
    {
        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            $this->installFixture((int)$lang['id_lang'], 'sale70.png');
        }

        return true;
    }

    protected function installFixture($id_lang, $image = null)
    {
        $values['BANNERTOP1_IMG'][(int)$id_lang] = $image;
        $values['BANNERTOP2_IMG'][(int)$id_lang] = $image;
        $values['BANNERTOP1_LINK'][(int)$id_lang] = '';
        $values['BANNERTOP2_LINK'][(int)$id_lang] = '';

        Configuration::updateValue('BANNERTOP1_IMG', $values['BANNERTOP1_IMG']);
        Configuration::updateValue('BANNERTOP1_LINK', $values['BANNERTOP1_LINK']);
        Configuration::updateValue('BANNERTOP2_LINK', $values['BANNERTOP2_LINK']);

        Configuration::updateValue('BANNERTOP2_IMG', $values['BANNERTOP2_IMG']);
    }

    public function uninstall()
    {
        Configuration::deleteByName('BANNERTOP1_IMG');
        Configuration::deleteByName('BANNERTOP1_LINK');
        Configuration::deleteByName('BANNERTOP2_LINK');

        Configuration::deleteByName('BANNERTOP2_IMG');

        return parent::uninstall();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitStoreConf')) {
            $languages = Language::getLanguages(false);
            $values = array();
            $update_images_values = false;
            $update_images_values2 = false;

            foreach ($languages as $lang) {
                if (isset($_FILES['BANNERTOP2_IMG_'.$lang['id_lang']])
                  && isset($_FILES['BANNERTOP2_IMG_'.$lang['id_lang']]['tmp_name'])
                  && !empty($_FILES['BANNERTOP2_IMG_'.$lang['id_lang']]['tmp_name'])) {
                    if ($error2 = ImageManager::validateUpload($_FILES['BANNERTOP2_IMG_'.$lang['id_lang']], 4000000)) {
                        echo $this->displayError($error2);
                    } else {
                        $ext2 = substr($_FILES['BANNERTOP2_IMG_'.$lang['id_lang']]['name'], strrpos($_FILES['BANNERTOP2_IMG_'.$lang['id_lang']]['name'], '.') + 1);
                        $file_name2 = md5($_FILES['BANNERTOP2_IMG_'.$lang['id_lang']]['name']).'.'.$ext2;

                        if (!move_uploaded_file($_FILES['BANNERTOP2_IMG_'.$lang['id_lang']]['tmp_name'], dirname(__FILE__).DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$file_name2)) {
                            echo $this->displayError($this->trans('An error occurred while attempting to upload the file.', array(), 'Admin.Notifications.Error'));
                        } else {
                            if (Configuration::hasContext('BANNERTOP2_IMG', $lang['id_lang'], Shop::getContext())
                              && Configuration::get('BANNERTOP2_IMG', $lang['id_lang']) != $file_name2) {
                                @unlink(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . Configuration::get('BANNERTOP2_IMG', $lang['id_lang']));
                            }

                            $values['BANNERTOP2_IMG'][$lang['id_lang']] = $file_name2;
                        }
                    }

                    $update_images_values2 = true;
                }

                if (isset($_FILES['BANNERTOP1_IMG_'.$lang['id_lang']])
                    && isset($_FILES['BANNERTOP1_IMG_'.$lang['id_lang']]['tmp_name'])
                    && !empty($_FILES['BANNERTOP1_IMG_'.$lang['id_lang']]['tmp_name'])) {
                    if ($error = ImageManager::validateUpload($_FILES['BANNERTOP1_IMG_'.$lang['id_lang']], 4000000)) {
                        echo $this->displayError($error);
                    } else {
                        $ext = substr($_FILES['BANNERTOP1_IMG_'.$lang['id_lang']]['name'], strrpos($_FILES['BANNERTOP1_IMG_'.$lang['id_lang']]['name'], '.') + 1);
                        $file_name = md5($_FILES['BANNERTOP1_IMG_'.$lang['id_lang']]['name']).'.'.$ext;

                        if (!move_uploaded_file($_FILES['BANNERTOP1_IMG_'.$lang['id_lang']]['tmp_name'], dirname(__FILE__).DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$file_name)) {
                            echo $this->displayError($this->trans('An error occurred while attempting to upload the file.', array(), 'Admin.Notifications.Error'));
                        } else {
                            if (Configuration::hasContext('BANNERTOP1_IMG', $lang['id_lang'], Shop::getContext())
                                && Configuration::get('BANNERTOP1_IMG', $lang['id_lang']) != $file_name) {
                                @unlink(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . Configuration::get('BANNERTOP1_IMG', $lang['id_lang']));
                            }

                            $values['BANNERTOP1_IMG'][$lang['id_lang']] = $file_name;
                        }
                    }

                    $update_images_values = true;
                }



                $values['BANNERTOP1_LINK'][$lang['id_lang']] = Tools::getValue('BANNERTOP1_LINK_'.$lang['id_lang']);
                $values['BANNERTOP2_LINK'][$lang['id_lang']] = Tools::getValue('BANNERTOP2_LINK_'.$lang['id_lang']);
            }

            if ($update_images_values) {
                Configuration::updateValue('BANNERTOP1_IMG', $values['BANNERTOP1_IMG']);
            }

            if ($update_images_values2) {
                Configuration::updateValue('BANNERTOP2_IMG', $values['BANNERTOP2_IMG']);
            }

            Configuration::updateValue('BANNERTOP1_LINK', $values['BANNERTOP1_LINK']);
            Configuration::updateValue('BANNERTOP2_LINK', $values['BANNERTOP2_LINK']);

            $this->_clearCache($this->templateFile);

            return $this->displayConfirmation($this->trans('The settings have been updated.', array(), 'Admin.Notifications.Success'));
        }

        return '';
    }

    public function getContent()
    {
        return $this->postProcess().$this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Settings', array(), 'Admin.Global'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'file_lang',
                        'label' => $this->trans('Banner image', array(), 'Modules.Banner.Admin'),
                        'name' => 'BANNERTOP1_IMG',
                        'desc' => $this->trans('Upload an image for your top banner. The recommended dimensions are 680px x 228 px if you are using the default swiat supli theme for 2 image in row.', array(), 'Modules.Banner.Admin'),
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'lang' => true,
                        'label' => $this->trans('Banner Link', array(), 'Modules.Banner.Admin'),
                        'name' => 'BANNERTOP1_LINK',
                        'desc' => $this->trans('Enter the link associated to your banner. When clicking on the banner, the link opens in the same window. If no link is entered, it redirects to the homepage.', array(), 'Modules.Banner.Admin')
                    ),

                    array(
                        'type' => 'file_lang',
                        'label' => $this->trans('Banner image', array(), 'Modules.Banner.Admin'),
                        'name' => 'BANNERTOP2_IMG',
                        'desc' => $this->trans('Upload an image for your top banner. The recommended dimensions are 680px x 228px if you are using the default theme.', array(), 'Modules.Banner.Admin'),
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'lang' => true,
                        'label' => $this->trans('Banner Link', array(), 'Modules.Banner.Admin'),
                        'name' => 'BANNERTOP2_LINK',
                        'desc' => $this->trans('Enter the link associated to your banner. When clicking on the banner, the link opens in the same window. If no link is entered, it redirects to the homepage.', array(), 'Modules.Banner.Admin')
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions')
                )
            ),
        );

        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitStoreConf';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'uri' => $this->getPathUri(),
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        $languages = Language::getLanguages(false);
        $fields = array();

        foreach ($languages as $lang) {
            $fields['BANNERTOP1_IMG'][$lang['id_lang']] = Tools::getValue('BANNERTOP1_IMG_'.$lang['id_lang'], Configuration::get('BANNERTOP1_IMG', $lang['id_lang']));
            $fields['BANNERTOP1_LINK'][$lang['id_lang']] = Tools::getValue('BANNERTOP1_LINK_'.$lang['id_lang'], Configuration::get('BANNERTOP1_LINK', $lang['id_lang']));
            $fields['BANNERTOP2_LINK'][$lang['id_lang']] = Tools::getValue('BANNERTOP2_LINK_'.$lang['id_lang'], Configuration::get('BANNERTOP1_LINK', $lang['id_lang']));

            $fields['BANNERTOP2_IMG'][$lang['id_lang']] = Tools::getValue('BANNERTOP2_IMG'.$lang['id_lang'], Configuration::get('BANNERTOP2_IMG', $lang['id_lang']));
        }

        return $fields;
    }

    public function renderWidget($hookName, array $params)
    {
        if (!$this->isCached($this->templateFile, $this->getCacheId('StrDuoBanerTop'))) {
            $this->smarty->assign($this->getWidgetVariables($hookName, $params));
        }

        return $this->fetch($this->templateFile, $this->getCacheId('StrDuoBanerTop'));
    }

    public function getWidgetVariables($hookName, array $params)
    {
        $imgname = Configuration::get('BANNERTOP1_IMG', $this->context->language->id);

        if ($imgname && file_exists(_PS_MODULE_DIR_.$this->name.DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$imgname)) {
            $this->smarty->assign('BANNERTOP1_img', $this->context->link->protocol_content . Tools::getMediaServer($imgname) . $this->_path . 'img/' . $imgname);
        } else {
            $this->smarty->assign('BANNERTOP1_img', '#');
        }
        $imgname2 = Configuration::get('BANNERTOP2_IMG', $this->context->language->id);

        if ($imgname2 && file_exists(_PS_MODULE_DIR_.$this->name.DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$imgname2)) {
            $this->smarty->assign('BANNERTOP2_img', $this->context->link->protocol_content . Tools::getMediaServer($imgname2) . $this->_path . 'img/' . $imgname2);
        } else {
            $this->smarty->assign('BANNERTOP2_img', '#');
        }

        $banner_link1 = Configuration::get('BANNERTOP1_LINK', $this->context->language->id);
        if (!$banner_link1) {
            $banner_link1 = $this->context->link->getPageLink('index');
        }
        $banner_link2 = Configuration::get('BANNERTOP2_LINK', $this->context->language->id);
        if (!$banner_link2) {
            $banner_link2 = $this->context->link->getPageLink('index');
        }


        return array(
            'banner_link1' => $this->updateUrl($banner_link1),
            'banner_link2' => $this->updateUrl($banner_link2),

        );
    }

    private function updateUrl($link)
    {
        if (substr($link, 0, 7) !== "http://" && substr($link, 0, 8) !== "https://") {
            $link = "http://" . $link;
        }

        return $link;
    }
}
