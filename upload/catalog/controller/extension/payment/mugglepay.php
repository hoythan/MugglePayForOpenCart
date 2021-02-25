<?php

class ControllerExtensionPaymentMugglepay extends Controller
{
    /** @var string MugglePay API url. */
    public $api_url = 'https://api.mugglepay.com/v1';

    public function index() {
        $data['button_confirm'] = $this->language->get('button_confirm');

        // Load Model
        $this->load->model('checkout/order');
		$this->load->model('extension/payment/mugglepay');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['action'] = '$redirect_url'; 
        try {
            $redirect_url = $this->get_payment_url($order_info);
            $data['action'] = $redirect_url; 
            return $this->load->view('extension/payment/mugglepay', $data);
        } catch (Exception $e) {
            return '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
        }
    }

    /**
     * Payment Callback (Webhook)
     */
    public function callback() {
        $this->load->model('checkout/order');
		$this->load->model('extension/payment/mugglepay');

        try {
            // /**
            //     stdClass Object
            //     (
            //         [order_id] => 334c0207-f09d-47ea-ad38-8d00bc9e2389
            //         [merchant_order_id] => 85
            //         [status] => PAID
            //         [price_amount] => 0.2
            //         [price_currency] => USD
            //         [pay_amount] => 0.2
            //         [pay_currency] => USD
            //         [created_at] => 2021-02-24T08:43:36.289Z
            //         [created_at_t] => 1614156216289
            //         [meta] => stdClass Object
            //             (
            //             )

            //         [token] => d202a0da832f8f7aa4f15b3f16d4e6c01069f4d841b89a8e802835ca9404b49a
            //     )
            // */
            $posted = json_decode(file_get_contents('php://input'));

            if (! empty($posted) && ! empty($posted->merchant_order_id) && $posted->token) { // CSRF ok.
                $order = $this->model_checkout_order->getOrder($posted->merchant_order_id);
                $order_id = (int)$order['order_id'];

                // Checking response
                $this->record_log('Test Order:'.print_r($order, true), 'error');
                $this->record_log('Test:'.$posted->order_id, 'error');
                $this->record_log('Test Meta:'.$this->model_extension_payment_mugglepay->get_metadata($order_id, 'payment_transaction_id', true), 'error');
                if (! $this->model_extension_payment_mugglepay->check_order_token($order_id, $posted->token) || $posted->order_id !== $this->model_extension_payment_mugglepay->get_metadata($order_id, 'payment_transaction_id', true)) {
                    $this->record_log('Checking IPN response is valid: ', 'error', false);
                    $this->record_log(print_r($posted, true), 'error', false);
                    $this->record_log(print_r($order, true), 'error');
                    throw new Exception('Checking IPN response is valid');
                }

                if ($order['order_status_id'] === $this->config->get('payment_mugglepay_order_status_id')) {
                    $this->record_log('Aborting, Order #' . $order_id. ' is already complete.', 'error');
                } else {
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_mugglepay_order_status_id'), 'MugglePay Voucher:'.$posted->order_id, true);
                    $this->record_log('Complete order payment, Order #' . $order_id, 'success');
                }
                
                header( 'Content-Type: application/json;' );
                status_header( 200 );
                echo json_decode(array( 'status' => 200 ));
                exit;
            }
            $this->record_log('Failed to check response order callback : ', 'error', false);
            $this->record_log(print_r($posted, true), 'error', false);
            throw new Exception('MugglePay IPN Request Failure');
        } catch (Exception $e) {
            $this->record_log($e->getMessage(), 'error');
        }
    }

    /**
     * Get the MugglePay request URL for an order.
     *
     * @param  object $order Order object.
     * @return string
     */
    public function get_payment_url( $order ) {
        $this->load->model('checkout/order');
		$this->load->model('extension/payment/mugglepay');

        $partnerOrderNo = trim($order['order_id']);
        $order_product = $this->model_checkout_order->getOrderProducts($partnerOrderNo);
   
        // Create description for charge based on order's products. Ex: 1 x Product1, 2 x Product2
        try {
            $order_items = array_map(function ($item) {
                return $item['name'] . ' x ' . $item['quantity'];
            }, $order_product );

            $description = mb_substr(implode(', ', $order_items), 0, 200);
        } catch (Exception $e) {
            $description = null;
        }

        /**
         * Order Request
         */
        $currency = $order['currency_code'];
        $amount = trim($this->currency->format($order['total'], $currency, '', false));
        $mugglepay_args = array(
            'merchant_order_id'	=> $partnerOrderNo,
            'price_amount'		=> $amount,
            'price_currency'	=> $order['currency_code'],
            'title'				=> sprintf('Payment order #%s', $order['order_id']),
            'description'		=> $description,
            'callback_url'		=> $this->url->link('extension/payment/mugglepay/callback', '', true),
            'cancel_url'		=> $this->url->link('checkout/checkout', '', true),
            'success_url'		=> $this->url->link('checkout/success', '', true),
            // 'mobile'			=> false,
            // 'fast'				=> '',
            'token'				=> md5($partnerOrderNo.$this->config->get('payment_mugglepay_api_key'))
        );

        $raw_response = $this->send_request('/orders', $mugglepay_args);

        $this->record_log('Create Payment Url: ', 'info', false);
        $this->record_log(print_r($raw_response, true), 'info');

        if (
            (($raw_response['status'] === 200 || $raw_response['status'] === 201) && $raw_response['payment_url']) ||
            (($raw_response['status'] === 400 && $raw_response['error_code'] === 'ORDER_MERCHANTID_EXIST') && $raw_response['payment_url'])
        ) {
            // Save payment order id
            $this->model_extension_payment_mugglepay->update_metadata($partnerOrderNo, 'payment_transaction_id', $raw_response['order']['order_id']);

            return $raw_response['payment_url'];
        } elseif (!empty($raw_response['error_code'])) {
            throw new Exception($this->get_error_str($raw_response['error_code']));
        }

        throw new Exception($raw_response['error']);
    }

    /**
     * Get the response from an API request.
     * @param  string $endpoint
     * @param  array  $params
     * @param  string $method
     * @return array
     */
	public function send_request($endpoint, $params, $method = 'POST'){
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL               => $this->api_url . $endpoint,
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_ENCODING          => '',
            CURLOPT_MAXREDIRS         => 10,
            CURLOPT_TIMEOUT           => 15, // 超时时间（单位:s）
            CURLOPT_FOLLOWLOCATION    => true,
            CURLOPT_HTTP_VERSION      => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST     => $method,
            CURLOPT_POSTFIELDS        => json_encode($params),
            CURLOPT_HTTPHEADER        => array(
                'Content-Type: application/json',
                'token:'. $this->config->get('payment_mugglepay_api_key')
            )
        ));
        
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
	}

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     * @param boolean $is_end insert log end flag
     */
    public function record_log($message, $level = 'info', $is_end = true)
    {
        $this->log->write($level . ':' .$message);
        if ($is_end) {
            $this->log->write('=========================================== ↑↑↑ END ↑↑↑ ===========================================');
        }
    }

    /**
     * HTTP Response and Error Codes
     * Most common API errors are as follows, including message, reason and status code.
     */
    public function get_error_str($code)
    {
        switch ($code) {
            case 'AUTHENTICATION_FAILED':
                return 'Authentication Token is not set or expired.';
            case 'INVOICE_NOT_EXIST':
                return 'Invoice does not exist.';
            case 'INVOICE_VERIFIED_ALREADY':
                return 'It has been verified already.';
            case 'INVOICE_CANCELED_FAIILED':
                return 'Invoice does not exist, or it cannot be canceled.';
            case 'ORDER_NO_PERMISSION':
                return 'Order does not exist or permission denied.';
            case 'ORDER_CANCELED_FAIILED':
                return 'Order does not exist, or it cannot be canceled.';
            case 'ORDER_REFUND_FAILED':
                return 'Order does not exist, or it`s status is not refundable.';
            case 'ORDER_VERIFIED_ALREADY':
                return 'Payment has been verified with payment already.';
            case 'ORDER_VERIFIED_PRICE_NOT_MATCH':
                return 'Payment money does not match the order money, please double check the price.';
            case 'ORDER_VERIFIED_MERCHANT_NOT_MATCH':
                return 'Payment money does not the order of current merchant , please double check the order.';
            case 'ORDER_NOT_VALID':
                return 'Order id is not valid.';
            case 'ORDER_PAID_FAILED':
                return 'Order not exist or is not paid yet.';
            case 'ORDER_MERCHANTID_EXIST':
                return 'Order with same merchant_order_id exisits.';
            case 'ORDER_NOT_NEW':
                return 'The current order is not new, and payment method cannot be switched.';
            case 'PAYMENT_NOT_AVAILABLE':
                return 'The payment method is not working, please retry later.';
            case 'MERCHANT_CALLBACK_STATUS_WRONG':
                return 'The current payment status not ready to send callback.';
            case 'PARAMETERS_MISSING':
                return 'Missing parameters.';
            case 'PAY_PRICE_ERROR':
                // switch ($this->current_method) {
                //     case 'WECHAT':
                //     case 'ALIPAY':
                //     case 'ALIGLOBAL':
                //         return 'The payment is temporarily unavailable, please use another payment method';
                // }
                return 'Price amount or currency is not set correctly.';
            case 'CREDENTIALS_NOT_MATCH':
                return 'The email or password does not match.';
            case 'USER_NOT_EXIST':
                return 'The user does not exist or no permission.';
            case 'USER_FAILED':
                return 'The user operatioin failed.';
            case 'INVITATION_FAILED':
                return 'The invitation code is not filled correctly.';
            case 'ERROR':
                return 'Error.';
            case '(Unauthorized)':
                return 'API credentials are not valid';
            case '(Not Found)':
                return 'Page, action not found';
            case '(Too Many Requests)':
                return 'API request limit is exceeded';
            case '(InternalServerError)':
                return 'Server error in MugglePay';
        }
        return 'Server error in MugglePay';
    }
}
