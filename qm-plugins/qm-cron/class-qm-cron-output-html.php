<?php

class QM_Output_Html_Cron extends QM_Output_Html {

	private $doing_cron = false;

	public function __construct( \QM_Collector $collector ) {
		parent::__construct( $collector );

		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 110 );
	}

	public function admin_menu( array $menu ) {
		$menu[] = $this->menu( array(
			'id'    => 'qm-cron',
			'href'  => '#qm-cron',
			'title' => esc_html__( 'Cron', 'query-monitor' ),
		));

		return $menu;
	}

	public function output() {
		$data             = $this->collector->get_data();
		$this->doing_cron = $data->doing_cron;
		?>
		<div class="qm qm-non-tabular" id="qm-<?php echo esc_attr( $this->collector->id ); ?>" role="tabpanel" aria-labelledby="qm-<?php echo esc_attr( $this->collector->id ); ?>-caption" tabindex="-1">
		<table id="qm-cron-stats" style="width: 100%">
			<caption>Cron Event Statistics</caption>
			<thead>
				<tr>
					<th><h3><?php esc_html_e( 'Total Events', 'query-monitor' ); ?></h3></th>
					<th><h3><?php esc_html_e( 'Core Events', 'query-monitor' ); ?></h3></th>
					<th><h3><?php esc_html_e( 'Custom Events', 'query-monitor' ); ?></h3></th>
					<th><h3><?php esc_html_e( 'Doing Cron', 'query-monitor' ); ?></h3></th>
					<th><h3><?php esc_html_e( 'Next Event', 'query-monitor' ); ?></h3></th>
					<th><h3><?php esc_html_e( 'Current Time', 'query-monitor' ); ?></h3></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo absint( $data->total_crons ); ?></td>
					<td><?php echo absint( $data->total_core_crons ); ?></td>
					<td><?php echo absint( $data->total_user_crons ); ?></td>
					<td><?php true === $this->doing_cron ? esc_html_e( 'Yes', 'query-monitor' ) : esc_html_e( 'No', 'query-monitor' ); ?></td>
					<td>
						<?php
						if ( isset( $data->next_event_time['human_time'] ) ) {
							echo esc_html( $data->next_event_time['human_time'] );
							echo '<br />';
						}
						if ( isset( $data->next_event_time['unix'] ) ) {
							echo absint( $data->next_event_time['unix'] );
							echo '<br />';
							echo '<i>' . esc_html( $this->display_past_time( human_time_diff( $data->next_event_time['unix'] ), $data->next_event_time['unix'] ) ) . '</i>';
						}
						?>
					</td>
					<td><?php echo esc_html( gmdate( 'H:i:s' ) ); ?></td>
				</tr>
			</tbody>
		</table>
		<br />
		<h3><strong>Schedules</strong></h3>
		<table id="qm-cron-schedules" style="width: 100%">
			<caption>List of set schedules for cron events</caption>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Interval Hook', 'query-monitor' ); ?></span></th>
					<th><?php esc_html_e( 'Interval (S)', 'query-monitor' ); ?></th>
					<th><?php esc_html_e( 'Interval (M)', 'query-monitor' ); ?></th>
					<th><?php esc_html_e( 'Interval (H)', 'query-monitor' ); ?></th>
					<th><?php esc_html_e( 'Display Name', 'query-monitor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( is_array( $data->schedules ) ) {
					foreach ( $data->schedules as $schedule => $value ) {
						echo '<tr>';
						echo '<th>' . esc_html( $schedule ) . '</th>';
						echo '<th>' . esc_html( $value['interval'] ) . '</th>';
						echo '<th>' . esc_html( $this->get_minutes( $value['interval'] ) ) . '</th>';
						echo '<th>' . esc_html( $this->get_hours( $value['interval'] ) ) . '</th>';
						echo '<th>' . esc_html( $value['display'] ) . '</th>';
						echo '</tr>';
					}
				}
				?>
			</tbody>
		</table>
		<br />
		<h3><strong>Custom Events</strong></h3>
		<?php $this->display_events( $data->user_crons, 'qm-cron-user-crons' ); ?>
		<br />
		<h3><strong>Core Events</strong></h3>
		<?php $this->display_events( $data->core_crons, 'qm-cron-core-crons' ); ?>
		</div>
		<?php
	}

	/**
	 * Displays the events in an easy to read table.
	 *
	 * @param array  $events        Array of events.
	 * @param string $no_events_msg Message to display if there are no events.
	 */
	private function display_events( $events, $html_table_id = '', $no_events_msg = 'No events found' ) {
		// Exit early if no events found.
		if ( ! is_array( $events ) || empty( $events ) ) {
			echo '
			<p>', esc_html( $no_events_msg ), '</p>';
			return;
		}

		echo '
			<table class="qm-cron-table qm-cron-event-table">
			<caption>Cron Events Listing</caption>
				<thead><tr>
					<th class="col1">', esc_html__( 'Next Execution', 'query-monitor' ), '</th>
					<th class="col2">', esc_html__( 'Hook', 'query-monitor' ), '</th>
					<th class="col3">', esc_html__( 'Interval Hook', 'query-monitor' ), '</th>
					<th class="col4">', esc_html__( 'Interval Value', 'query-monitor' ), '</th>
					<th class="col5">', esc_html__( 'Args', 'query-monitor' ), '</th>
				</tr></thead>
				<tbody>';

		foreach ( $events as $time => $time_cron_array ) {
			$time        = (int) $time;
			$event_count = $this->get_arg_set_count( $time_cron_array );
			$show_time   = true;

			foreach ( $time_cron_array as $hook => $data ) {
				$row_attributes = $this->get_event_row_attributes( $time, $hook );
				$arg_set_count  = count( $data );
				$show_hook      = true;

				foreach ( $data as $hash => $info ) {
					echo '<tr', $row_attributes, '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

					if ( $show_time ) {
						$this->display_event_time( $time, $event_count );
						$show_time = false;
					}

					if ( true === $show_hook ) {
						$this->display_event_hook( $hook, $arg_set_count );
						$show_hook = false;
					}

					// Report the schedule.
					echo '<td>';
					$this->display_event_schedule( $info );
					echo '</td>';

					// Report the interval.
					echo '<td class="intervals">';
					$this->display_event_intervals( $info );
					echo '</td>';

					// Report the args.
					echo '<td>';
					$this->display_event_cron_arguments( $info['args'] );
					echo '</td></tr>';
				}
				unset( $hash, $info );
			}
			unset( $hook, $data, $row_attributes, $arg_set_count, $show_hook );
		}
		unset( $time, $time_cron_array, $show_time );

		echo '</tbody></table>';
	}

	/**
	 * Count the number of argument sets for a cron time.
	 *
	 * @param array $hook_array Array of hooks with argument sets.
	 *
	 * @return int
	 */
	private function get_arg_set_count( $hook_array ) {
		$count = 0;
		foreach ( $hook_array as $set ) {
			$count += count( $set );
		}
		return $count;
	}

	/**
	* Create a HTML attribute string for an event row.
	*
	* @param int    $time Unix timestamp.
	* @param string $hook Action hook for the cron job.
	*
	* @return string
	*/
	private function get_event_row_attributes( $time, $hook ) {
		$attributes = '';

		// Verify if any events are hooked in.
		if ( false === has_action( $hook ) ) {
			/* translators: This text will display as a tooltip. %1$s will be replaced by a line break. */
			$attributes .= ' title="' . sprintf( esc_attr__( 'No actions are hooked into this event at this time.%1$sThe most likely reason for this is that a plugin or theme was de-activated or uninstalled and didn\'t clean up after itself.%1$sHowever, a number of plugins also use the best practice of lean loading and only hook in conditionally, so check carefully if you intend to remove this event.', 'query-monitor' ), "\n" ) . '"';
		}

		return $attributes;
	}

	/**
	* Display the timing for the event as a date, timestamp and human readable time difference.
	*
	* @param int $time        Timestamp.
	* @param int $event_count Number of events running at this time.
	*/
	private function display_event_time( $time, $event_count ) {
		$row_span = ( $event_count > 1 ) ? ' rowspan="' . esc_attr( $event_count ) . '"' : '';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<td' . $row_span . '>
		   ', esc_html( gmdate( 'Y-m-d H:i:s', $time ) ), '<br />
		   ', esc_html( $time ), '<br />
		   ', '<i>' . esc_html( $this->display_past_time( human_time_diff( $time ), $time ) ) . '</i>', '
	   </td>';
	}


	/**
	* Display the name of the cron job event hook.
	*
	* @param string $hook          Hook name.
	* @param int    $arg_set_count Number of events running at this time and on this hook.
	*/
	private function display_event_hook( $hook, $arg_set_count ) {
		$row_span = ( $arg_set_count > 1 ) ? ' rowspan="' . esc_attr( $arg_set_count ) . '"' : '';

		echo '<td' . $row_span . '>', esc_html( $hook ), '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}


	/**
	* Displays the the event schedule name for recurring events or else 'single event'.
	*
	* @param array $info Event info array.
	*/
	private function display_event_schedule( $info ) {
		if ( ! empty( $info['schedule'] ) ) {
			echo esc_html( $info['schedule'] );
		} else {
			esc_html_e( 'Single Event', 'query-monitor' );
		}
	}


	/**
	* Displays the event interval in seconds, minutes and hours.
	*
	* @param array $info Event info array.
	*/
	private function display_event_intervals( $info ) {
		if ( ! empty( $info['interval'] ) ) {
			$interval = (int) $info['interval'];
			/* translators: %s is number of seconds. */
			printf( esc_html__( '%ss', 'query-monitor' ) . '<br />', esc_html( $interval ) );
			/* translators: %s is number of minutes. */
			printf( esc_html__( '%sm', 'query-monitor' ) . '<br />', esc_html( $this->get_minutes( $interval ) ) );
			/* translators: %s is number of hours. */
			printf( esc_html__( '%sh', 'query-monitor' ), esc_html( $this->get_hours( $interval ) ) );
			unset( $interval );
		} else {
			esc_html_e( 'Single Event', 'query-monitor' );
		}
	}

	/**
	* Displays the cron arguments in a readable format.
	*
	* @param mixed $args Cron argument(s).
	*
	* @return void
	*/
	private function display_event_cron_arguments( $args ) {
		// Arguments defaults to an empty array if no arguments are given.
		if ( is_array( $args ) && empty( $args ) ) {
			esc_html_e( 'No Args', 'query-monitor' );
			return;
		}

		echo wp_json_encode( $args );
	}

	/**
	 * Compares time with current time and adds ' ago' if current time is greater than event time.
	 *
	 * @param string $human_time Human readable time difference.
	 * @param int    $time       Unix time of event.
	 *
	 * @return string
	 */
	private function display_past_time( $human_time, $time ) {
		if ( time() > $time ) {
			/* translators: %s is a human readable time difference. */
			return sprintf( esc_html__( '%s ago', 'query-monitor' ), $human_time );
		} else {
			return $human_time;
		}
	}

	/**
	 * Transform a time in seconds to minutes rounded to 2 decimals.
	 *
	 * @param int $time Unix timestamp.
	 *
	 * @return float
	 */
	private function get_minutes( $time ) {
		return round( ( (int) $time / 60 ), 2 );
	}


	/**
	 * Transform a time in seconds to hours rounded to 2 decimals.
	 *
	 * @param int $time Unix timestamp.
	 *
	 * @return float
	 */
	private function get_hours( $time ) {
		return round( ( (int) $time / 3600 ), 2 );
	}
}
