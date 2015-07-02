<?php
class ShoutemCalendarsController extends ShoutemController {

	/**
	 * Called automatically before methods
	 */
	public function doBefore() {
		//Events only work with events manager plugin
		$this->validate_required_plugins('events-manager/events-manager.php');
	}

	/**
	 * MAPS_TO calendars/find
	 * Optional params: session_id, offset, limit
	 */
	public function find() {
		$this->request->use_default_params($this->default_paging_params());

		$dao = $this->dao_factory->get_calendars_dao();
		$data = $dao->find($this->request->params);
		$this->view->show_recordset($data);
	}

}
