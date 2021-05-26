/**
 * External dependencies
 */
var React = require( 'react' ),
	ReactDOM = require( 'react-dom' ),
	joinClasses = require( 'fbjs/lib/joinClasses' );

/**
 * Internal dependencies
 */
var Config = require( '../config.js' ),
	Widget = require( '../widget' );

/**
 * Contact Widget Component
 */
var Widget_Contact = React.createClass( {
	getInitialState: function() {
		return {
			user: Config.user,
			useremail: Config.useremail,
			message: '',
			status: '',
			formclass: '',
			cansubmit: true,
			cc: ''
		};
	},

	handleSubmit: function( e ) {
		e.preventDefault();

		this.setState( {
			formclass: 'sending',
			cansubmit: false
		} );

		var name = ReactDOM.findDOMNode( this.refs.user ).value.trim();
		var email = ReactDOM.findDOMNode( this.refs.email ).value.trim();
		var subject = ReactDOM.findDOMNode( this.refs.subject ).value.trim();
		var type = ReactDOM.findDOMNode( this.refs.type ).value.trim();
		var body = ReactDOM.findDOMNode( this.refs.body ).value.trim();
		var priority = ReactDOM.findDOMNode( this.refs.priority ).value.trim();
		var cc = ReactDOM.findDOMNode( this.refs.cc ).value.trim();

		var data = {
			name: name,
			email: email,
			subject: subject,
			type: type,
			body: body,
			priority: priority,
			cc: cc,
			action: 'vip_contact'
		};

		jQuery.ajax( {
			type: 'POST',
			url: Config.ajaxurl,
			data: data,
			success: function( data, textStatus, jqXHR ) {
				if ( textStatus === 'success' ) {
					var result = jQuery.parseJSON( data );

					this.setState( {
						message: result.message,
						status: result.status,
						formclass: 'form-' + result.status,
						cansubmit: true
					} );

					// reset the form
					if ( result.status === 'success' ) {
						ReactDOM.findDOMNode( this.refs.subject ).value = '';
						ReactDOM.findDOMNode( this.refs.body ).value = '';
						ReactDOM.findDOMNode( this.refs.cc ).value = '';
						ReactDOM.findDOMNode( this.refs.type ).value = 'Technical';
						ReactDOM.findDOMNode( this.refs.priority ).value = 'Medium';
					}
				} else {
					this.setState( {
						message: 'Your message could not be sent, please try again.',
						status: 'error',
						cansubmit: true
					} );
				}
			}.bind( this )
		} );

		return;
	},

	maybeRenderFeedback: function() {
		if ( this.state.message ) {
			return <div className={ 'contact-form__' + this.state.status } dangerouslySetInnerHTML={{__html: this.state.message}}></div>;
		}
	},

	render: function() {
		return (
			<Widget className={ joinClasses( this.state.formclass, 'widget__contact-form' ) } title="Contact WordPress.com VIP Support">

				{ this.maybeRenderFeedback() }

				<form className="widget__contact-form" action="submit" method="get" onSubmit={this.handleSubmit}>
					<div className="contact-form__row">
						<div className="contact-form__label">
							<label htmlFor="contact-form__name">Name</label>
						</div>
						<div className="contact-form__input">
							<input type="text" defaultValue={ this.state.user } id="contact-form__name" placeholder="First and last name" ref="user" />
						</div>
					</div>
					<div className="contact-form__row">
						<div className="contact-form__label">
							<label htmlFor="contact-form__email">Email</label>
						</div>
						<div className="contact-form__input">
							<input type="text" defaultValue={ this.state.useremail } id="contact-form__email" placeholder="Email address" ref="email"/>
						</div>
					</div>
					<div className="contact-form__row">
						<div className="contact-form__label">
							<label htmlFor="contact-form__subject">Subject</label>
						</div>
						<div className="contact-form__input">
							<input type="text" defaultValue="" id="contact-form__subject" placeholder="Ticket name" ref="subject" />
						</div>
					</div>
					<div className="contact-form__row">
						<div className="contact-form__label">
							<label htmlFor="contact-form__type">Type</label>
						</div>
						<div className="contact-form__input">
							<select id="contact-form__type" ref="type" defaultValue="Technical">
								<option value="Technical">Technical</option>
								<option value="Business">Business/Project Management</option>
								<option value="Review">Theme/Plugin Review</option>
							</select>
						</div>
					</div>
					<div className="contact-form__row">
						<div className="contact-form__label">
							<label htmlFor="contact-form__details">Details</label>
						</div>
						<div className="contact-form__input">
							<textarea name="details" rows="4" id="contact-form__details" placeholder="Please be descriptive" ref="body"></textarea>
						</div>
					</div>
					<div className="contact-form__row">
						<div className="contact-form__label">
							<label htmlFor="contact-form__priority">Priority</label>
						</div>
						<div className="contact-form__input">
							<select id="contact-form__priority" ref="priority" defaultValue="Medium">
								<optgroup label="Normal Priority">
									<option value="Low">Low</option>
									<option value="Medium">Normal</option>
									<option value="High">High</option>
								</optgroup>
								<optgroup label="Urgent Priority">
									<option value="Emergency">Emergency (Outage, Security, Revert, etc...)</option>
								</optgroup>
							</select>
						</div>
					</div>
					<div className="contact-form__row">
						<div className="contact-form__label">
							<label htmlFor="contact-form__cc">CC:</label>
						</div>
						<div className="contact-form__input">
							<input type="text" defaultValue={ this.state.cc } id="contact-form__cc" placeholder="Comma separated email addresses" ref="cc" />
						</div>
					</div>
					<div className="contact-form__row submit-button">
						<div className="contact-form__label">
							<label></label>
						</div>
						<div className="contact-form__input">
							<input type="submit" value="Send Request" disabled={!this.state.cansubmit} />
						</div>
					</div>
				</form>
			</Widget>
		);
	}
} );

module.exports = Widget_Contact;
