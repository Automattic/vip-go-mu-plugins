<?php
class ShoutemEventsController extends ShoutemController {

	/**
	 * Called automatically before methods
	 */
	public function doBefore() {
		//Events only work with events manager plugin
		//$this->validate_required_plugins('events-manager/events-manager.php');
	}

	public function categories() {
		$this->request->use_default_params($this->default_paging_params());
		$this->accept_standard_params_and();
		$params = $this->request->params;

		$dao = $this->dao_factory->get_events_dao();
		$categories = $dao->categories($params);

		$this->view->show_recordset($categories);
	}

	/**
	 * MAPS_TO events/find
	 * Required params: category_id
	 * Optional params: session_id, offset, limit
	 */
	public function find() {
		$params = $this->accept_standard_params_and('category_id','from_time','till_time','name');
		$this->request->use_default_params($this->default_paging_params());
		$this->validate_required_params('category_id');

		$params = $this->request->params;

		$dao = $this->dao_factory->get_events_dao();

		$uid = $this->caching->unique_id($params);
		$cached = $this->caching->get_cached($uid);

		if ($cached) {

			$this->response->send_json($cached);

		} else {

			$result = $dao->find($params); //get result from data access object
			if ($result) {
				$json_string = $this->view->encode_recordset_as_json($result);
				$this->caching->store_to_cache($uid, $json_string);
				$this->response->send_json($json_string);
			} else {
				$this->response->send_error(500);
			}

		}
	}

	/**
	 * MAPS_TO events/get
	 * Required params: event_id
	 * Optional params: session_id
	 */
	public function get() {
		$params = $this->accept_standard_params_and('event_id','post_id','id');
		$this->request->use_default_params($this->default_paging_params());


		$dao = $this->dao_factory->get_events_dao();
		$data = $this->caching->use_cache(
					array($dao,'get'),
					$this->request->params
					);
		$this->view->show_record($data);
	}
}
