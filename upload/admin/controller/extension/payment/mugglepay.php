<?php
class ControllerExtensionPaymentMugglepay extends Controller
{
    private $error = array();

    private $paramKeys = [
        'payment_mugglepay_enabled',
        'payment_mugglepay_api_key',
        'payment_mugglepay_order_status_id',
        'payment_mugglepay_status',
        'payment_mugglepay_sort_order'
    ];

    /**
     * Install MugglePay Plugin Event
     */
	public function install() {
		$this->load->model('extension/payment/mugglepay');
		$this->model_extension_payment_mugglepay->install();
	}

    /**
     * UnInstall MugglePay Plugin Event
     */
    public function uninstall() {
		$this->load->model('extension/payment/mugglepay');
		$this->model_extension_payment_mugglepay->uninstall();
    }

    /**
     * Mugglepay Config Page
     */
    public function index()
    {

        $this->load->language('extension/payment/mugglepay');
		$this->load->model('extension/payment/mugglepay');
        $this->load->model('setting/setting');

        $this->document->setTitle($this->language->get('heading_title'));

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            // 提交编辑参数
            $this->model_setting_setting->editSetting('payment_mugglepay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_api_key'] = isset($this->error['api_key']) ? $this->error['api_key'] : '';

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/mugglepay', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['action'] = $this->url->link('extension/payment/mugglepay', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $data = $this->setData($data);

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // $this->load->model('localisation/geo_zone');
        // $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/mugglepay', $data));

    }

    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/mugglepay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        if (!$this->request->post['payment_mugglepay_api_key']) {
            $this->error['api_key'] = $this->language->get('error_key');
        }

        return !$this->error;
    }

    public function setData($data)
    {
        foreach ($this->paramKeys as $key) {
            $data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
        }
        return $data;
    }
}
