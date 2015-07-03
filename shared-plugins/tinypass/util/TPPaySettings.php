<?php

/**
 * Options Helper Class used generically across PHP plugins
 */
class TPPaySettings {

	const RESOURCE_NAME = 'resource_name';
	const RESOURCE_ID = 'resource_id';
	//MODES
	const ENABLED = 'en';
	const MODE = 'mode';
	const MODE_DONATION_KEY = 'tinypass_mode_donation';
	const MODE_STRICT_KEY = 'tinypass_mode_strict';
	const MODE_METERED_KEY = 'tinypass_mode_metered';
	const MODE_METERED_LIGHT_KEY = 'tinypass_mode_metered_light';
	const MODE_OFF = 0;
	const MODE_PPV = 1;
	const MODE_METERED = 2;
	const MODE_STRICT = 3;
	const MODE_METERED_LIGHT = 4;
	const MODE_OFF_NAME = 'Off';
	const MODE_PPV_NAME = 'Pay-Per-View';
	const MODE_METERED_NAME = 'New York';
	const MODE_STRICT_NAME = 'Boston';
	const MODE_METERED_LIGHT_NAME = 'Metered';
	const MODE_PPV_NAME_REAL = 'Pay-Per-View';
	const MODE_METERED_NAME_REAL = 'Preview';
	const MODE_STRICT_NAME_REAL = 'Strict';
	const MODE_METERED_LIGHT_NAME_REAL = 'Metered';

	//PRICE OPTIONS
	const PO_PRICE = 'po_p';
	const PO_PERIOD = 'po_ap';
	const PO_PERIOD_TYPE = 'po_ap_type';
	const PO_PERIOD_TYPE_V1 = 'po_type';
	const PO_CAPTION = 'po_cap';
	const PO_START = 'po_st';
	const PO_END = 'po_et';
	const PO_RECUR = 'po_recur';
	const METERED = 'metered';
	const HIDE_TEASER = 'ht';
	const METER_LOCKOUT_PERIOD = 'm_lp';
	const METER_LOCKOUT_PERIOD_TYPE = 'm_lp_type';
	const METER_MAX_ACCESS_ATTEMPTS = 'm_maa';
	const METER_TRIAL_PERIOD = 'm_tp';
	const METER_TRIAL_PERIOD_TYPE = 'm_tp_type';
	const PREMIUM_TAGS = 'tags';
	const ENABLE_PER_TAG = 'per_tag';
	const SUBSCRIPTION_PAGE = 'sub_page';
	const SUBSCRIPTION_PAGE_REF = 'sub_page_ref';
	const SUBSCRIPTION_PAGE_SUCCESS = 'sub_page_success';
	const SUBSCRIPTION_PAGE_SUCCESS_REF = 'sub_page_success_ref';
	const PD_DENIED_MSG1 = 'pd_denied_msg1';
	const PD_DENIED_MSG2 = 'pd_denied_msg2';
	const PD_DENIED_SUB1 = 'pd_denied_sub1';
	const PD_DENIED_SUB2 = 'pd_denied_sub2';
	const PD_TYPE = 'pd_type';
	const OFFER_ORDER = 'pd_order';
	const DEFAULT_DENIED_MESSAGE = 'To continue, purchase with Tinypass';
	const DEFAULT_DENIED_MESSAGE2 = 'Get instant access with just a few clicks';

	//MLITE SETTINGS
	const MLITE_PWID_PROD = 'mlite_pwid_prod';
	const MLITE_PWID_SAND = 'mlite_pwid_sand';
	const MLITE_TRACK_HOMEPAGE = 'mlite_track_homepage';
	const MLITE_READON_ENABLED = 'mlite_readon_enabled';
	const MLITE_DISABLED_FOR_ADMINS = 'mlite_disabled_for_admins';

	public static $MODE_NAMES = array(
			self::MODE_PPV => self::MODE_PPV_NAME,
			self::MODE_METERED => self::MODE_METERED_NAME,
			self::MODE_STRICT => self::MODE_STRICT_NAME
	);
	public static $MODE_NAMES_REAL = array(
			self::MODE_PPV => self::MODE_PPV_NAME_REAL,
			self::MODE_METERED => self::MODE_METERED_NAME_REAL,
			self::MODE_STRICT => self::MODE_STRICT_NAME_REAL
	);

	//Appeal fields

	const APP_ENABLED = 'app_en';
	const APP_NUM_VIEWS = 'app_views';
	const APP_FREQUENCY = 'app_freq';
	const APP_MSG1 = 'app_msg1';
	const APP_MSG2 = 'app_msg2';

	//Counter
	const CT_ENABLED = 'ct_en';
	const CT_ONCLICK = 'ct_onclick';
	const CT_POSTION = 'ct_pos';
	const CT_DELAY = 'ct_delay';
	const CT_ONCLICK_NOTHING = 0;
	const CT_ONCLICK_PAGE = 1;
	const CT_ONCLICK_APPEAL = 2;

	//settings
	const TINYPASS_PAYWALL_SETTINGS = 'tinypass_paywall_settings';

	private $data;

	public function __construct($data = null) {
		if ($data == null)
			$data = new NiceArray();


		if ($data instanceof NiceArray)
			$this->data = $data;
		else
			$this->data = new NiceArray($data);

		$count = 0;
		for ($i = 1; $i <= 3; $i++) {
			if ($this->_isset('po_en' . $i))
				$count++;
		}

		$this->num_prices = $count;
	}

	public function isEnabled() {
		return $this->data->isValEnabled(TPPaySettings::ENABLED);
	}

	public function setEnabled($b = 1) {
		$this->data[TPPaySettings::ENABLED] = $b;
	}

	public function getEnabled() {
		return $this->data->val(TPPaySettings::ENABLED, 0);
	}

	public function isMode($type) {
		return $this->data->val(TPPaySettings::MODE, TPPaySettings::MODE_PPV) == $type;
	}

	public function getMode() {
		return $this->data->val(TPPaySettings::MODE, TPPaySettings::MODE_PPV);
	}

	public function getModeName() {
		$mode = $this->data->val(TPPaySettings::MODE, TPPaySettings::MODE_PPV);
		return self::$MODE_NAMES[$mode];
	}

	public function getModeNameReal() {
		$mode = $this->data->val(TPPaySettings::MODE, TPPaySettings::MODE_PPV);
		return self::$MODE_NAMES_REAL[$mode];
	}

	public function setMode($i) {
		$this->data[TPPaySettings::MODE] = $i;
	}

	public function getPremiumTags($delimiter = null) {
		$d = $this->data->val(self::PREMIUM_TAGS, array());
		if ($delimiter && is_array($d))
			return implode($delimiter, $d);
		return $d;
	}

	public function getPremiumTagsArray() {
		$d = $this->data->val(self::PREMIUM_TAGS, array());
		if (is_array($d))
			return $d;
		return array_map('trim', explode(',', $d));
	}

	public function tagMatches($name) {
		return in_array($name, $this->getPremiumTagsArray());
	}

	public function isHideTeaser() {
		return $this->data->isValEnabled(self::HIDE_TEASER);
	}

	private function _isset($field) {
		return isset($this->data[$field]) && ($this->data[$field] || $this->data[$field] == 'on');
	}

	public function getResourceName() {
		return $this->data->val(self::RESOURCE_NAME, '');
	}

	public function setResourceName($s) {
		$this->data[self::RESOURCE_NAME] = $s;
	}

	public function getResourceId() {
		return $this->data->val(self::RESOURCE_ID, '');
	}

	public function setResourceId($s) {
		$this->data[self::RESOURCE_ID] = $s;
	}

	public function getNumPrices() {
		return $this->num_prices;
	}

	public function hasPriceConfig($i) {
		return $this->data->isValEnabled("po_en" . $i);
	}

	public function getPrice($i, $def = null) {
		$value = $this->data->val(self::PO_PRICE . $i);
		if ($value == null)
			return $def;
		return $value;
	}

	public function getAccess($i) {
		if ($this->getAccessPeriod($i) == null)
			return '';
		return $this->getAccessPeriod($i, '') . " " . $this->getAccessPeriodType($i, '');
	}

	public function getAccessFullFormat($i) {
		if ($this->getAccessPeriod($i) == null && $this->getAccessPeriodType($i) == null)
			return '';

		$price = $this->getPrice($i);
		$accessPeriod = $this->getAccessPeriod($i);
		$accessPeriodType = $this->getAccessPeriodType($i);

		if (is_numeric($price)) {
			$price = '$' . $price;
		}

		if ($this->getAccessPeriod($i) != null) {
			return "$price for $accessPeriod $accessPeriodType(s)";
		} else {
			return "$price for unlimited access";
		}
	}

	public function getAccessPeriod($i, $def = null) {
		return $this->data->val(self::PO_PERIOD . $i, $def);
	}

	public function getAccessPeriodType($i, $def = null) {
		if ($this->data[self::PO_PERIOD_TYPE] . $i)
			return $this->data->val(self::PO_PERIOD_TYPE . $i, $def);
		return $this->data->val(self::PO_PERIOD_TYPE_V1 . $i, $def);
	}

	public function getCaption($i) {
		return $this->data[self::PO_CAPTION . $i];
	}

	public function getRecurring($i) {
		return $this->data->val(self::PO_RECUR . $i, '');
	}

	public function isRecurring($i) {
		return $this->data->val(self::PO_RECUR . $i, '') != 0;
	}

	public function getStartDateSec($i) {
		return strtotime($this->data[self::PO_START . $i]);
	}

	public function getEndDateSec($i) {
		return strtotime($this->data[self::PO_END . $i]);
	}

	public function isMetered() {
		if ($this->_isset(self::METERED)) {
			return in_array($this->data[self::METERED], array('count', 'time'));
		}
		return false;
	}

	public function getMetered($def = 'off') {
		return $this->data->val(self::METERED, $def);
	}

	public function isTimeMetered() {
		return $this->isMetered() && $this->data[self::METERED] == 'time';
	}

	public function isCountMetered() {
		return $this->isMetered() && $this->data[self::METERED] == 'count';
	}

	public function isPaymentDisplayDefault() {
		if ($this->data[self::PD_TYPE] == null)
			return TPSiteSettings::PA_EXPANDED;
		return $this->data->valEquals(self::PD_TYPE, TPSiteSettings::PA_DEFAULT);
	}

	public function isPaymentDisplayExpanded() {
		if ($this->data[self::PD_TYPE] == null)
			return TPSiteSettings::PA_EXPANDED;
		return $this->data->valEquals(self::PD_TYPE, TPSiteSettings::PA_EXPANDED);
	}

	public function getPaymentDisplay() {
		return $this->data->val(self::PD_TYPE, TPSiteSettings::PA_DEFAULT);
	}

	/**
	 * Meter fields 
	 */
	public function getMeterMaxAccessAttempts($def = null) {
		return $this->data->val(self::METER_MAX_ACCESS_ATTEMPTS, $def);
	}

	public function getMeterLockoutPeriod($def = null) {
		return $this->data->val(self::METER_LOCKOUT_PERIOD, $def);
	}

	public function getMeterLockoutPeriodType($def = null) {
		return $this->data->val(self::METER_LOCKOUT_PERIOD_TYPE, $def);
	}

	public function getMeterLockoutPeriodFull() {
		return $this->getMeterLockoutPeriod() . " " . $this->getMeterLockoutPeriodType();
	}

	public function getMeterTrialPeriod($def = null) {
		return $this->data->val(self::METER_TRIAL_PERIOD, $def);
	}

	public function getMeterTrialPeriodType($def = null) {
		return $this->data->val(self::METER_TRIAL_PERIOD_TYPE, $def);
	}

	public function getMeterTrialPeriodFull() {
		return $this->getMeterTrialPeriod() . " " . $this->getMeterTrialPeriodType();
	}

	public function getMeterSummary() {
		if ($this->isCountMetered()) {
			return $this->getMeterMaxAccessAttempts() . " views in " . $this->getMeterLockoutPeriodFull();
		} else if ($this->isTimeMetered()) {
			return $this->getMeterTrialPeriodFull();
		} else {
			return 'off';
		}
	}

	/*
	 * Subscription releated fields
	 */

	public function getSubscriptionPage() {
		return $this->data->val(self::SUBSCRIPTION_PAGE, '');
	}

	public function getSubscriptionPageRef() {
		return $this->data->val(self::SUBSCRIPTION_PAGE_REF, '');
	}

	public function hasSubscriptionPage() {
		return $this->getSubscriptionPage() != '';
	}

	public function getSubscriptionPageSuccess() {
		return $this->data->val(self::SUBSCRIPTION_PAGE_SUCCESS, '');
	}

	public function getSubscriptionPageSuccessRef() {
		return $this->data->val(self::SUBSCRIPTION_PAGE_SUCCESS_REF, '');
	}

	public function hasSubscriptionPageSuccess() {
		return $this->getSubscriptionPageSuccess() != '';
	}

	/**
	 * Messaging
	 */
	public function getDeniedMessage1($msg = self::DEFAULT_DENIED_MESSAGE) {
		return $this->data->val(self::PD_DENIED_MSG1, $msg);
	}

	public function getDeniedMessage2($msg = self::DEFAULT_DENIED_MESSAGE) {
		return $this->data->val(self::PD_DENIED_MSG2, $msg);
	}

	public function getDeniedSub1($msg = self::DEFAULT_DENIED_MESSAGE2) {
		return $this->data->val(self::PD_DENIED_SUB1, $msg);
	}

	public function getDeniedSub2() {
		return $this->data->val(self::PD_DENIED_SUB2, self::DEFAULT_DENIED_MESSAGE);
	}

	public function getOfferOrder() {
		return $this->data->val(self::OFFER_ORDER, 0);
	}

	public function isPostFirstInOrder() {
		return $this->data->val(self::OFFER_ORDER, 0) == 1;
	}

	/**
	 * Appeal Configuration
	 */
	public function isAppealEnabled() {
		return $this->data->isValEnabled(self::APP_ENABLED);
	}

	public function getAppealEnabled() {
		return $this->data->val(self::APP_ENABLED, 0);
	}

	public function getAppealMessage1($msg = self::DEFAULT_DENIED_MESSAGE) {
		return $this->data->val(self::APP_MSG1, $msg);
	}

	public function getAppealMessage2($msg = self::DEFAULT_DENIED_MESSAGE) {
		return $this->data->val(self::APP_MSG2, $msg);
	}

	public function getAppealNumViews($d = "") {
		return $this->data->val(self::APP_NUM_VIEWS, $d);
	}

	public function getAppealFrequency($d = '') {
		return $this->data->val(self::APP_FREQUENCY, $d);
	}

	/**
	 * Counter Configuration
	 */
	public function isCounterEnabled() {
		return $this->data->isValEnabled(self::CT_ENABLED);
	}

	public function getCounterEnabled() {
		return $this->data->val(self::CT_ENABLED, 0);
	}

	public function getCounterOnClick($d = self::CT_ONCLICK_NOTHING) {
		return $this->data->val(self::CT_ONCLICK, $d);
	}

	public function isCounterOnClick($i) {
		return $this->getCounterOnClick() == $i;
	}

	public function getCounterPosition($d = 3) {
		return $this->data->val(self::CT_POSTION, 3);
	}

	public function getCounterDelay($def = 0) {
		return $this->data->val(self::CT_DELAY, $def);
	}

	/**
	 * MeteredList settings
	 */

	public function getPaywallID($env = 1) {
		if($env == 1)
			return $this->getPaywallIDProd('');
		return $this->getPaywallIDSand('');
	}

	public function getPaywallIDProd($d = '') {
		return $this->data->val(self::MLITE_PWID_PROD, $d);
	}

	public function getPaywallIDSand($d = '') {
		return $this->data->val(self::MLITE_PWID_SAND, $d);
	}

	public function isTrackHomePage() {
		return $this->data->isValEnabled(self::MLITE_TRACK_HOMEPAGE);
	}

	public function getTrackHomePage() {
		return $this->data->isValEnabled(self::MLITE_TRACK_HOMEPAGE, 0);
	}

	public function isReadOnEnabled() {
		return $this->data->isValEnabled(self::MLITE_READON_ENABLED);
	}

	public function getReadOnEnabled() {
		return $this->data->isValEnabled(self::MLITE_READON_ENABLED, 0);
	}

	public function isDisabledForPriviledgesUsers() {
		return $this->data->isValEnabled(self::MLITE_DISABLED_FOR_ADMINS, 0);
	}

	public function getDisabledForPriviledgesUsers() {
		return $this->data->isValEnabled(self::MLITE_DISABLED_FOR_ADMINS, 0);
	}


	/**
	 * Helper method
	 */
	public function getSummaryFields() {
		$output = array();

		$output['mode'] = $this->getModeName();

		$resource_name = htmlspecialchars(stripslashes($this->getResourceName()));

		$output['rname'] = $resource_name;

		$output['enabled'] = $this->isEnabled() ? __('Yes') : __('No');

		$output['prices'] = array();

		for ($i = 1; $i <= 3; $i++) {

			if ($this->hasPriceConfig($i) == false)
				continue;

			$caption = $this->getCaption($i);

			$line = $this->getAccessFullFormat($i);

			if ($this->getCaption($i)) {
				$line .= " - '" . htmlspecialchars(stripslashes($caption)) . "'";
			}

			$output['prices'][] = $line;
		}

		$output['meter'] = $this->getMeterSummary();

		return $output;
	}

	public function toArray() {
		if (isset($this->data))
			return $this->data->toArray();
		return array();
	}

	/**
	 * Create offer from settings data
	 *  
	 * @param TPPaySettings $ps
	 * @return returns null or a valid TPOffer
	 */
	public static function create_offer(&$ps, $rid, $rname = null) {
		if ($ps == null)
			return null;

		if ($rname == '' || $rname == null)
			$rname = $ps->getResourceName();

		$resource = new TPResource($rid, stripslashes($rname));

		$pos = array();

		for ($i = 1; $i <= $ps->getNumPrices(); $i++) {

			$po = new TPPriceOption($ps->getPrice($i));

			if ($ps->getAccess($i) != '')
				$po->setAccessPeriod($ps->getAccess($i));

			if ($ps->getCaption($i) != '')
				$po->setCaption(stripslashes($ps->getCaption($i)));

			if ($ps->isRecurring($i)) {
				$po->setRecurringBilling($ps->getRecurring($i));
			}

			$pos[] = $po;
		}

		$offer = new TPOffer($resource, $pos);

		return $offer;
	}

}

?>