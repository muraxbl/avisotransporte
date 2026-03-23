<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AvisoTransporte extends Module
{
    public function __construct()
    {
        $this->name = 'avisotransporte';
        $this->tab = 'front_office_features';
        $this->version = '2.1.0';
        $this->author = 'Luis Coves';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Aviso correo cuando no hay transporte');
        $this->description = $this->l('Envía aviso por email cuando un cliente intenta comprar sin transportista');
        $this->confirmUninstall = $this->l('¿Estás seguro de que quieres desinstalarlo?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayAfterCarrier')
            && $this->installConfiguration();
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallConfiguration();
    }

    protected function installConfiguration()
    {
        return Configuration::updateValue('AVISO_NO_TRANSPORTE_COUNTRIES', '')
            && Configuration::updateValue('AVISO_NO_TRANSPORTE_MAIL', Configuration::Get('PS_SHOP_EMAIL'))
            && Configuration::updateValue('AVISO_NO_TRANSPORTE_EMAIL_TEXT', $this->l('El cliente {email} trató de comprar sin transporte.'))
            && Configuration::updateValue('AVISO_NO_TRANSPORTE_TEMPLATE_TEXT', $this->l('Actualmente no hay formas de envío disponibles para tu país.'));
    }

    protected function uninstallConfiguration()
    {
        return Configuration::deleteByName('AVISO_NO_TRANSPORTE_COUNTRIES')
            && Configuration::deleteByName('AVISO_NO_TRANSPORTE_MAIL')
            && Configuration::deleteByName('AVISO_NO_TRANSPORTE_EMAIL_TEXT')
            && Configuration::deleteByName('AVISO_NO_TRANSPORTE_TEMPLATE_TEXT');
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitAvisoTransporte')) {
            Configuration::updateValue(
                'AVISO_NO_TRANSPORTE_COUNTRIES',
                implode(',', (array)Tools::getValue('AVISO_NO_TRANSPORTE_COUNTRIES'))
            );
            Configuration::updateValue('AVISO_NO_TRANSPORTE_MAIL', Tools::getValue('AVISO_NO_TRANSPORTE_MAIL'));
            Configuration::updateValue('AVISO_NO_TRANSPORTE_EMAIL_TEXT', Tools::getValue('AVISO_NO_TRANSPORTE_EMAIL_TEXT'));
            Configuration::updateValue('AVISO_NO_TRANSPORTE_TEMPLATE_TEXT', Tools::getValue('AVISO_NO_TRANSPORTE_TEMPLATE_TEXT'));
            $output .= $this->displayConfirmation($this->l('Configuración actualizada'));
        }

        $countries = Country::getCountries($this->context->language->id, true);
        $selectedCountries = explode(',', Configuration::get('AVISO_NO_TRANSPORTE_COUNTRIES'));
        $formHtml = '<form method="post">';
        $formHtml .= '<label>'.$this->l('Países habilitados para el aviso').'</label><br>';
        $formHtml .= '<select name="AVISO_NO_TRANSPORTE_COUNTRIES[]" multiple style="width:300px">';
        foreach ($countries as $country) {
            $selected = in_array($country['id_country'], $selectedCountries) ? 'selected' : '';
            $formHtml .= "<option value='{$country['id_country']}' $selected>{$country['name']}</option>";
        }
        $formHtml .= '</select><br><br>';

        $formHtml .= '<label>'.$this->l('Correo destinatario').'</label><br>';
        $formHtml .= '<input type="email" name="AVISO_NO_TRANSPORTE_MAIL" value="'.htmlentities(Configuration::get('AVISO_NO_TRANSPORTE_MAIL')).'" style="width:300px;"/><br><br>';

        $formHtml .= '<label>'.$this->l('Texto del email de aviso (Dentro del template ya se envía la tabla de productos y la información del usuario.)').'</label><br>';
        $formHtml .= '<textarea name="AVISO_NO_TRANSPORTE_EMAIL_TEXT" rows="5" style="width:500px">'.htmlentities(Configuration::get('AVISO_NO_TRANSPORTE_EMAIL_TEXT')).'</textarea><br><br>';

        $formHtml .= '<label>'.$this->l('Texto en el checkout si no hay transporte').'</label><br>';
        $formHtml .= '<textarea name="AVISO_NO_TRANSPORTE_TEMPLATE_TEXT" rows="3" style="width:500px">'.htmlentities(Configuration::get('AVISO_NO_TRANSPORTE_TEMPLATE_TEXT')).'</textarea><br><br>';

        $formHtml .= '<button type="submit" name="submitAvisoTransporte" class="btn btn-primary">'.$this->l('Guardar configuración').'</button>';
        $formHtml .= '</form>';

        return $output.$formHtml;
    }

    public function hookDisplayAfterCarrier($params)
    {
        $context = Context::getContext();
        $cart = $context->cart;
        $address = new Address((int)$cart->id_address_delivery);
        $idCountry = (int)$address->id_country;
        $enabledCountries = array_filter(explode(',', Configuration::get('AVISO_NO_TRANSPORTE_COUNTRIES')));

        //Comprobamos si la lista de países está vacía. Si es así asignamos true a la variable $allCountriesActive
        $allCountriesActive = empty($enabledCountries);

        // Si hay al menos un país en el array y el país de la dirección de entrega  no se encuentra en el array retornamos la función sin mostrar mensaje ni enviar email
        if (!$allCountriesActive && !in_array($idCountry, $enabledCountries)) {
            return '';
        }

        if (count($cart->getDeliveryOptionList()) == 0) {
            // Evitar SPAM: sólo avisar 1 vez cada 5 min.
            if (!isset($context->cookie->__aviso_mail_sent) || time() - (int)$context->cookie->__aviso_mail_sent > 300) {
                $to = Configuration::get('AVISO_NO_TRANSPORTE_MAIL');
                $templateVars = [
                    '{shop}'    => $context->shop->name,
                    '{customer}' => $context->customer->id,
                    '{email}'   => $context->customer->email,
                    '{country}' => (new Country($idCountry))->name[$context->language->id],
                    '{cart}'    => $cart->id,
                    '{email_text}' => Configuration::get('AVISO_NO_TRANSPORTE_EMAIL_TEXT'),
                    '{cart_products}' => $this->getCartProductsHtml($cart),
                    '{postcode}' => $address->postcode,
                ];
                $templateVars['{email_text}'] = strtr($templateVars['{email_text}'], $templateVars);

                Mail::Send(
                    (int)$context->language->id,
                    'aviso_no_transporte',
                    'Aviso: '.$context->customer->email.' sin transporte',
                    $templateVars,
                    $to,
                    null,
                    null,
                    null,
                    null,
                    _PS_MODULE_DIR_ . $this->name . '/mails/',
                    false,
                    (int)$context->shop->id
                );
                $context->cookie->__aviso_mail_sent = time();
            }

            // Mostrar template si el mensaje no está vacío
            $tplText = trim(Configuration::get('AVISO_NO_TRANSPORTE_TEMPLATE_TEXT'));
            if (empty($tplText)) {
                return '';
            }
            $this->context->smarty->assign([
                'aviso_no_transporte_message' => $tplText
            ]);
            return $this->display(__FILE__, 'views/templates/hook/aviso_no_transporte.tpl');
        }
        return '';
    }

    private function getCartProductsHtml($cart)
    {
        $productos = $cart->getProducts(true);
        if (!$productos) {
            return '';
        }
        $html = '<table border="1" style="width:100%;text-align:center;"><tr>
            <th>'.$this->l('Producto').'</th>
            <th>'.$this->l('Ref').'</th>
            <th>'.$this->l('Cant.').'</th>
            <th>'.$this->l('Disp.').'</th>
            <th>'.$this->l('Peso Unit.').'</th>
            <th>'.$this->l('Peso línea').'</th>
            <th>'.$this->l('Precio').'</th>
        </tr>';
        foreach ($productos as $line) {
            $peso_linea = $line['weight'] * $line['cart_quantity'];
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%.2f</td><td>%.2f</td><td>%.2f</td></tr>',
                $line['name'],
                $line['reference'],
                $line['cart_quantity'],
                $line['quantity_available'],
                $line['weight'],
                $peso_linea,
                $line['price']
            );
        }
        $html .= '</table>';
        return $html;
    }
}