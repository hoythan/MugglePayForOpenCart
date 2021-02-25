<?php
class ModelExtensionPaymentMugglePay extends Model {
	public $dbTable = DB_PREFIX . 'mugglepay_order_options';

	public function getMethod($address, $total) {
		$this->load->language('extension/payment/mugglepay');

		$type = $this->config->get('payment_mugglepay_type');

		$method_data = array(
			'code'       => 'mugglepay',
			'title'      => $this->language->get('text_title'),
			'terms'      => '',
			'sort_order' => $this->config->get('payment_mugglepay_sort_order')
		);
		return $method_data;
	}


    /**
     * Check Order token to validate Payment
     */
	public function check_order_token ($order_id, $token) {
		return md5($order_id.$this->config->get('payment_mugglepay_api_key')) === $token;
	}


	public function update_metadata($objectId, $metaKey, $metaValue, $prevValue = '') {
		$query = $this->db->query("SELECT * FROM `" . $this->dbTable . "` WHERE `order_id` = ". (int)$objectId ." AND `meta_key` = '" . $metaKey . "'");

		if (!$query->num_rows) {
			return $this->add_metadata($objectId, $metaKey, $metaValue);
		}

		// update
		$metaValue = $this->db->escape(serialize($metaValue));

		if ($prevValue === '') {
			$meta_id = $query->row['meta_id'];

			return $this->db->query("UPDATE `". $this->dbTable ."` SET `meta_value` = '". $metaValue . "' WHERE `meta_id` = ". (int)$meta_id .";");
		} else {
			foreach($query->rows as $row) {
				$meta_id = $row['meta_id'];

				if ($prevValue && $row['meta_value'] === serialize($prevValue)) {
					$this->db->query("UPDATE `". $this->dbTable ."` SET `meta_value` = '". $metaValue . "' WHERE `meta_id` = ". (int)$meta_id .";");
				}
			}
		}
		return true;
	}

	public function add_metadata($objectId, $metaKey, $metaValue) {
		$metaValue = $this->db->escape(serialize($metaValue));
		$this->db->query("INSERT INTO `". $this->dbTable ."` (`meta_id`, `order_id`, `meta_key`, `meta_value`) VALUES (NULL, " . (int)$objectId . ", '" . $metaKey . "', '" . $metaValue . "');");
	}

	public function get_metadata ($objectId, $metaKey = '', $single = false) {
		$query = $this->db->query("SELECT * FROM `" . $this->dbTable . "` WHERE `order_id` = ". (int)$objectId ." AND `meta_key` = '" . $metaKey . "'");

		if (!$query->num_rows) return false;

		if ($single) {
			return unserialize($query->row['meta_value']);
		}
		
		return array_map(function($row) {
			return unserialize($row['meta_value']);
		}, $query->rows);
	}
}
