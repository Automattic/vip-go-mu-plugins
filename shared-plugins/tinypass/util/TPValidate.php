<?php

class TPValidate {

	const PRICE_FAILED_MSG = "Price is blank or incorrectly formatted. <a target='_blank' href='http://developer.tinypass.com/main/integration#price_format'>Click here</a> to read about formatting rules.";
	const TIME_FAILED_MSG = "You have to specify a valid date.";
	const ACCESS_PERIOD_FAILED_MSG = 'Access Period must be a number greater then 1 or empty';
	const NUMBER_FAILED_MSG = ' must be a valid number';

	public static function validatePrice($price) {

		$price = trim($price);

		if (empty($price))
			return false; // Price cannot be empty

		if (preg_match('/^[>]?\s?\d*[.,]?\d+$/', $price) || preg_match('/^[>]?\s?\d*[.,]?\d+\s[a-z]{3}$/i', $price))
			return true;

		return false;
	}

	public static function validateTime($time) {

		if (!empty($time) && strtotime($time) <= 0) {
			return false;
		}

		return true;
	}

	public static function validateAccessPeriod($ap) {

		if ($ap == "")
			return true;

		if (!is_numeric($ap)) {
			return false;
		}

		if ($ap < 1) {
			return false;
		}

		return true;
	}

	public static function validateNumber($num) {
		if ($num == null || $num == '' || is_numeric($num) == false)
			return false;

		return true;
	}

}

?>