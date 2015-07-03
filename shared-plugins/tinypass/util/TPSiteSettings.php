<?php

/**
 * TPSettings
 */
class TPSiteSettings {

	const ENV = 'env';
	const AID_SAND = 'aid_sand';
	const SECRET_KEY_SAND = 'secret_key_sand';
	const AID_PROD = 'aid_prod';
	const SECRET_KEY_PROD = 'secret_key_prod';
	const PAYWALLS = 'pws';
	const TINYPASS_SITE_SETTINGS = "tinypass_site_settings";
	const PA_DEFAULT = 0;
	const PA_EXPANDED = 1;
	const ENABLED = 'en';
	const PPP_ENABLED = 'ppv';
	const PD_DENIED_MSG1 = 'pd_denied_msg1';
	const PD_DENIED_SUB1 = 'pd_denied_sub1';

	public static $PA_CHOICES = array(TPSiteSettings::PA_DEFAULT => 'Default', TPSiteSettings::PA_EXPANDED => 'Expanded');
	public static $PERIOD_CHOICES = array('hour' => 'hour(s)', 'day' => 'day(s)', 'week' => 'week(s)', 'month' => 'month(s)');
	public static $OFFER_ORDER_CHOICES = array('0' => 'This purchase option', '1' => 'Pay-per-Post purchase option');

	const MSG_PD_EXPANDED = 'This option will be displayed when TinyPass is enabled at the pay-per-view level at at the tag leve.  The user will be presented with two purchase choices.';
	const MSG_PD_DEFAULT = 'Default payment dispaly option.  Will show a single TinyPass button.';

	private $data;
	private $modes;

	public function __construct($arr = null) {

		$this->modes = array();

		if ($arr != null)
			$this->init($arr);
		else {
			$this->init(array(
					TPSiteSettings::AID_SAND => 'W7JZEZFu2h',
					TPSiteSettings::SECRET_KEY_SAND => 'jeZC9ykDfvW6rXR8ZuO3EOkg9HaKFr90ERgEb3RW',
					TPSiteSettings::AID_PROD => 'GETKEY',
					TPSiteSettings::SECRET_KEY_PROD => 'Retreive your secret key from www.tinypass.com',
					TPSiteSettings::ENV => 0,
					TPSiteSettings::ENABLED => 1,
					TPSiteSettings::PPP_ENABLED => 1,
			));
		}
	}

	public function toArray() {
		return $this->data->toArray();
	}

	public function mergeValues($values) {
		$this->init(array_merge($this->data->toArray(), $values));
	}

	private function init($data) {
		if ($data instanceof NiceArray)
			$this->data = $data;
		else
			$this->data = new NiceArray($data);
	}

	public function isEnabled() {
		return $this->data->isValEnabled(self::ENABLED);
	}

	public function setEnabled($i = 1) {
		$this->data[self::ENABLED] = $i;
	}

	public function isProd() {
		return !$this->isSand();
	}

	public function isSand() {
		if (!isset($this->data[self::ENV]))
			return true;
		return $this->data->valEquals(self::ENV, 0);
	}

	public function setSand() {
		$this->data[self::ENV] = 0;
	}

	public function setProd() {
		$this->data[self::ENV] = 1;
	}

	public function getAIDSand() {
		return $this->data->val(self::AID_SAND, 'GET_AID');
	}

	public function getAIDProd() {
		return $this->data->val(self::AID_PROD, 'GET_AID');
	}

	public function getSecretKeySand() {
		return $this->data->val(self::SECRET_KEY_SAND, 'GET_KEY');
	}

	public function getSecretKeyProd() {
		return $this->data->val(self::SECRET_KEY_PROD, 'GET_KEY');
	}

	public function getAID() {
		if ($this->isSand()) {
			return $this->data->val(self::AID_SAND, 'GET_AID');
		}
		return $this->data->val(self::AID_PROD, 'GET_AID');
	}

	public function getSecretKey() {
		if ($this->isSand()) {
			return $this->data->val(self::SECRET_KEY_SAND, 'GET_KEY');
		}
		return $this->data->val(self::SECRET_KEY_PROD, 'GET_KEY');
	}

	//Paywal Operations
	public function addPaywall(TPPaySettings $ps) {
		$rid = $ps->getResourceId();
		$arr = $this->data[self::PAYWALLS];
		if ($arr == null)
			$arr = array();
		$arr[] = $rid;
		$arr = array_unique($arr);
		$this->data[self::PAYWALLS] = $arr;
	}

	public function removePaywall(TPPaySettings $ps) {
		$rid = $ps->getResourceId();
		$pws = $this->data[self::PAYWALLS];
		if ($pws) {
			foreach ($pws as $i => $value) {
				if ($value == $rid) {
					unset($pws[$i]);
				}
			}
		}
		$this->data[self::PAYWALLS] = $pws;
	}

	public function getPaywalls() {
		return $this->data->val(TPSiteSettings::PAYWALLS, array());
	}

	/**
	 * PPV Settings
	 */
	public function isPPPEnabled() {
		return $this->data->isValEnabled(self::PPP_ENABLED);
	}

	public function getDeniedMessage1() {
		return $this->data->val(self::PD_DENIED_MSG1, TPPaySettings::DEFAULT_DENIED_MESSAGE);
	}

	public function getDeniedSub1() {
		return $this->data->val(self::PD_DENIED_SUB1, TPPaySettings::DEFAULT_DENIED_MESSAGE);
	}

	public function validatePostSettings($form, &$errors) {

		$form = new NiceArray($form);
		$errors = array();

		for ($i = 1; $i <= 3; $i++) {

			if ($form['po_en' . $i] == 0) {
				unset($form["po_en$i"]);
				unset($form["po_p$i"]);
				unset($form["po_ap$i"]);
				unset($form["po_ap_type$i"]);
				unset($form["po_cap$i"]);
				unset($form["po_st$i"]);
				unset($form["po_et$i"]);
				continue;
			}

			$p = $form['po_p' . $i];
			if (!TPValidate::validatePrice($p)) {
				$errors["po_p$i"] = _(TPValidate::PRICE_FAILED_MSG);
			}

			$ap = $form['po_ap' . $i];
			if (!TPValidate::validateAccessPeriod($ap)) {
				$errors["po_ap$i"] = _(TPValidate::ACCESS_PERIOD_FAILED_MSG);
			}
		}

		$ps = new TPPaySettings($form->toArray());
		$ps->setMode(TPPaySettings::MODE_PPV);
		return $ps;
	}

	public function validatePaySettings($form, &$errors) {

		$form = new NiceArray($form);

		$activeMode = $form['mode'];


		if ($activeMode == TPPaySettings::MODE_OFF) {

			$storage = new TPStorage();
			$ps = $storage->getPaywall($form['resource_id']);
			$ps->setMode(TPPaySettings::MODE_OFF);
			return $ps;
		} else if ($activeMode != TPPaySettings::MODE_OFF) {


			if($activeMode != TPPaySettings::MODE_METERED_LIGHT) {
				if (empty($form['resource_name'])) {
					$errors['resource_name'] = "Paywall name must no be empty";
				}
			}

			if (!is_array($form['tags']))
				$form['tags'] = array();

			$form['tags'] = array_unique($form['tags']);

			$tags = array();
			foreach ($form['tags'] as $tag) {
				if (term_exists($tag))
					$tags[] = $tag;
				else
					$errors['tags' . $tag] = "Tag '$tag' does not exist.";
			}
			$form['tags'] = $tags;

			if (count($form['tags']) == 0)
				$errors['tags'] = "Tinypass should be configured for at least 1 tag.  No tags have been specified!";

			for ($i = 1; $i <= 3; $i++) {

				if ($form['po_en' . $i] == 0) {
					unset($form["po_en$i"]);
					unset($form["po_p$i"]);
					unset($form["po_ap$i"]);
					unset($form["po_ap_type$i"]);
					unset($form["po_cap$i"]);
					unset($form["po_st$i"]);
					unset($form["po_et$i"]);
					continue;
				}

				$p = $form['po_p' . $i];
				if (!TPValidate::validatePrice($p)) {
					$errors["po_p$i"] = _(TPValidate::PRICE_FAILED_MSG);
				}

				$ap = $form['po_ap' . $i];
				if (!TPValidate::validateAccessPeriod($ap)) {
					$errors["po_ap$i"] = _(TPValidate::ACCESS_PERIOD_FAILED_MSG);
				}
			}

			if ($form['metered'] && $form->isValEnabled('metered')) {
				if (!TPValidate::validateNumber($form['m_lp']))
					$errors['m_lp'] = _('Lockout period' . TPValidate::NUMBER_FAILED_MSG);
			}

			//validate metered options
			if ($form['metered'] == 'time') {
				if (!TPValidate::validateNumber($form['m_tp']))
					$errors['m_tp'] = _('Preview trial period' . TPValidate::NUMBER_FAILED_MSG);
				if (!TPValidate::validateNumber($form['m_lp']))
					$errors['m_lp'] = _('Preview lockout period' . TPValidate::NUMBER_FAILED_MSG);
			}else if ($form['metered'] == 'count') {
				if (!TPValidate::validateNumber($form['m_maa']))
					$errors['m_maa'] = _('Preview number of views' . TPValidate::NUMBER_FAILED_MSG);
				if (!TPValidate::validateNumber($form['m_lp']))
					$errors['m_lp'] = _('Preview trial period' . TPValidate::NUMBER_FAILED_MSG);
			}

			if (isset($form['sub_page']) && $form['sub_page'] != '') {

				foreach (array('sub_page', 'sub_page_success') as $name) {
					$sub_path = $form[$name];
					$page = get_page_by_path($sub_path, OBJECT, 'page');
					if ($page == null) {
						$errors[$name] = _(esc_js('Could not find valid page for "' . $sub_path . '"'));
						$form[$name] = '';
					} else {
						$form[$name . "_ref"] = $page->ID;
					}
				}


				if (isset($form['sub_page_ref']) && !isset($form['sub_page_success_ref']))
					$errors['sub_page_success'] = "Confirmation page must be defined if dedicated page is created";

				if (isset($form['sub_page_ref']) && isset($form['sub_page_success_ref']))
					if ($form['sub_page_ref'] == $form['sub_page_success_ref'])
						$errors['sub_page_success'] = "Dedicated sign page and confirmation page must be different";
			}
		}

		$ps = new TPPaySettings($form->toArray());

		return $ps;
	}

}

if ( ! class_exists( 'NiceArray' ) ) :
class NiceArray implements ArrayAccess, Iterator, Countable {

	private $data;

	public function __construct($data = null) {
		if ($data == null)
			$data = array();

		$this->data = $data;
	}

	public function offsetSet($offset, $value) {
		$this->data[$offset] = $value;
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		return isset($this->data[$offset]) ? $this->data[$offset] : null;
	}

	public function rewind() {
		reset($this->data);
	}

	public function current() {
		return current($this->data);
	}

	public function key() {
		return key($this->data);
	}

	public function next() {
		return next($this->data);
	}

	public function valid() {
		return $this->current() !== false;
	}

	public function count() {
		return count($this->data);
	}

	public function val($field, $def = null) {
		if ($this[$field] == null)
			return $def;
		return $this[$field];
	}

	public function valEquals($field, $value) {
		return isset($this[$field]) && $this[$field] == $value;
	}

	public function isValEnabled($field) {

		if ($this->val($field) == null)
			return false;
		$val = $this->val($field);

		if (is_string($val)) {
			$val = strtolower($val);
			if ($val == "true" || $val == "on")
				return true;
			if (is_numeric($val) && intval($val) > 0)
				return true;
		}else if (is_numeric($val)) {
			return $val > 0;
		} else {
			return ($val == true);
		}
		return false;
	}

	public function toArray() {
		return $this->data;
	}

}
endif;

?>