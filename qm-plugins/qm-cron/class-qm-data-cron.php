<?php

class QM_Data_Cron extends QM_Data {
	/**
	 * @var bool
	 */
	public $doing_cron;

	/**
	 * @var array
	 */
	public $crons;

	/**
	 * @var int
	 */
	public $interval;

	/**
	 * @var array
	 */
	public $schedules;
}
