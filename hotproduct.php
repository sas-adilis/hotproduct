<?php
class HotProduct extends Module implements PrestaShop\PrestaShop\Core\Module\WidgetInterface
{
    public function __construct()
    {
        $this->name = 'hotproduct';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Adilis';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Hot Product');
        $this->description = $this->l('Displays a message on the product page when the product is frequently purchased.');
    }

    public function install()
    {
        Configuration::updateValue('HP_MINIMUM_SALES', 10);
        Configuration::updateValue('HP_NB_DAYS', 30 * 6);

        return
            parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayProductAdditionalInfo')
        ;
    }

    public function hookDisplayHeader($params)
    {
        $this->context->controller->addCSS($this->_path . 'views/css/front.css');
    }

    public function getContent()
    {
        if (Tools::isSubmit('submit' . $this->name . 'Module')) {
            if ((int) Tools::getValue('HP_MINIMUM_SALES') <= 0) {
                $this->context->controller->errors[] = $this->l('Minimum Sales is required and must be a number.');
            }

            if ((int) Tools::getValue('HP_NB_DAYS') <= 0) {
                $this->context->controller->errors[] = $this->l('Period (days) is required and must be a number.');
            }

            /* TODO: form validation * */
            if (!count($this->context->controller->errors)) {
                $redirect_after = $this->context->link->getAdminLink('AdminModules', true);
                $redirect_after .= '&conf=4&configure=' . $this->name . '&module_name=' . $this->name;

                Configuration::updateValue('HP_MINIMUM_SALES', (int) Tools::getValue('HP_MINIMUM_SALES'));
                Configuration::updateValue('HP_NB_DAYS', (int) Tools::getValue('HP_NB_DAYS'));

                Tools::redirectAdmin($redirect_after);
            }
        }

        return $this->renderForm();
    }

    private function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name . 'Module';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false);
        $helper->currentIndex .= '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            'fields_value' => [
                'HP_MINIMUM_SALES' => Tools::getValue('HP_MINIMUM_SALES', Configuration::get('HP_MINIMUM_SALES')),
                'HP_NB_DAYS' => Tools::getValue('HP_NB_DAYS', Configuration::get('HP_NB_DAYS')),
            ],
        ];

        return $helper->generateForm([
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Display conditions'),
                        'icon' => 'icon-cogs',
                    ],
                    'input' => [
                        [
                            'type' => 'text',
                            'name' => 'HP_MINIMUM_SALES',
                            'label' => $this->l('Minimum number of sales'),
                            'desc' => $this->l('The minimum number of sales for a product to be considered "hot".'),
                            'class' => 'fixed-width-sm',
                            'required' => true,
                        ],
                        [
                            'type' => 'text',
                            'name' => 'HP_NB_DAYS',
                            'label' => $this->l('Period (days)'),
                            'desc' => $this->l('The number of days to look back for sales data.'),
                            'class' => 'fixed-width-sm',
                            'required' => true,
                            'suffix' => $this->l('days'),
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                    ],
                ],
            ],
        ]);
    }

    protected function getCacheId($name = null)
    {
        $cache_array = [];
        $cache_array[] = $name !== null ? $name : $this->name;
        if (Shop::isFeatureActive()) {
            $cache_array[] = (int) $this->context->shop->id;
        }
        if (Language::isMultiLanguageActivated()) {
            $cache_array[] = (int) $this->context->language->id;
        }
        $cache_array[] = (int) $this->context->country->id;

        return implode('|', $cache_array);
    }

    public function renderWidget($hookName, array $configuration): string
    {
        if (!isset($configuration['product']) || !Validate::isLoadedObject($configuration['product'])) {
            return '';
        }

        $id_product = (int) $configuration['product']->id;
        if ($id_product <= 0) {
            return '';
        }

        $template = 'module:' . $this->name . '/views/templates/hook/widget.tpl';

        if (!$this->isCached($template, $this->getCacheId('hotproduct|' . $id_product))) {
            $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        }

        return $this->fetch($template, $this->getCacheId('hotproduct|' . $id_product));
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $id_product = (int) Tools::getValue('id_product');
        $min_sales = (int) Configuration::get('HP_MINIMUM_SALES');
        $nb_days = (int) Configuration::get('HP_NB_DAYS');
        $id_shop = (int) $this->context->shop->id;

        $nb_sales = (int) Db::getInstance()->getValue('
            SELECT SUM(od.product_quantity) as total_sales
            FROM ' . _DB_PREFIX_ . 'order_detail od
            INNER JOIN ' . _DB_PREFIX_ . 'orders o ON od.id_order = o.id_order 
            WHERE od.product_id = ' . (int) $id_product . ' 
            AND o.valid = 1 
            AND o.id_shop = ' . (int) $id_shop . '
            AND o.date_add >= DATE_SUB(NOW(), INTERVAL ' . (int) $nb_days . ' DAY)'
        );

        $nb_sales_rounded = floor(($nb_sales -1) / 10) * 10;

        return [
            'is_hot' => $nb_sales > $min_sales,
            'nb_sales' => $nb_sales,
            'nb_sales_rounded' => $nb_sales_rounded,
            'min_sales' => $min_sales,
            'nb_days' => $nb_days,
        ];
    }
}
