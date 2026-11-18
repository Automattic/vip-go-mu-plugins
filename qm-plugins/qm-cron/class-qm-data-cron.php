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

	/**
	 * @var int
	 */
	public $total_crons = 0;

	/**
	 * @var int
	 */
	public $total_core_crons = 0;

	/**
	 * @var int
	 */
	public $total_user_crons = 0;

	/**
	 * @var array
	 */
	public $core_crons = [];

	/**
	 * @var array
	 */
	public $user_crons = [];
}
