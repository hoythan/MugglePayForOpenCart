<?php

class ModelExtensionPaymentMugglepay extends Model {
	public function install() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `". $this->dbTable . "` (
				`meta_id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
				`order_id` INT(11) NOT NULL ,
				`meta_key` VARCHAR(255) NULL ,
				`meta_value` LONGTEXT NULL ,
				PRIMARY KEY (`meta_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_unicode_ci;");
	}

	public function uninstall() {

	}
}