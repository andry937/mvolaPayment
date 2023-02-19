<?php
class MvolaPaymentController extends ModuleFrontController
{
    public function postProcess()
    {
        if (!Module::isEnabled('mvolapayment')) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        if (Tools::isSubmit('submitPayment')) {
            $client_number = Tools::getValue('mvola_number');
            $cart = $this->context->cart;

            if (!$this->isValidClientNumber($client_number)) {
                $this->errors[] = $this->trans('Invalid client number or mvola number.', [], 'Modules.MvolaPayment.Shop');
            } else {
                $mvolaService = new MvolaPaymentService();
                $transactionData = array(
                    'amount' => $cart->getOrderTotal(),
                    'client_reference' => $client_number,
                    'currency' => 'Ar',
                    'descriptionText' => $this->getDescription(),
                );
                $transactionResponse = $mvolaService->initiateTransaction($transactionData);


                $this->context->smarty->assign([
                    'payment_status' => 'success',
                    'client_number' => $client_number,
                    'mvola_number' => Configuration::get('MVOLA_CLIENT_NUMBER'),
                    'owner_name' => Configuration::get('MVOLA_PAYMENT_CLIENT_OWNER'),
                    'cart' => $cart,
                ]);
            }
        }
    }

    private function getDescription()
    {
        $cart = Context::getContext()->cart;
        $products = $cart->getProducts();
        $description = '';

        foreach ($products as $product) {
            $description .= $product['quantity'] . 'x ' . $product['name'] . ' (' . $product['price'] . ') ';
        }
        $description = substr($description, 0, 50); 
        return  preg_replace("/[^a-zA-Z0-9\._,\-]+/", " ", $description);
    }

    public function handleTransactionCallback()
    {
        // Traitement des informations de la transaction ici.
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign([
            'action_link' => $this->context->link->getModuleLink($this->module->name, 'payment', [], true),
            'errors' => $this->errors,
            'client_number' => Configuration::get('MVOLA_CLIENT_NUMBER'),
            'owner_name' => Configuration::get('MVOLA_PAYMENT_CLIENT_OWNER'),
        ]);
        $this->setTemplate('module:mvolapayment/views/templates/front/payment.tpl');
    }

    private function isValidClientNumber($client_number)
    {
        return preg_match('/^034\d{7}$/', $client_number);
    }
}
