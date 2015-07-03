//** Form field Limiter v2.0- (c) Dynamic Drive DHTML code library: http://www.dynamicdrive.com
//** Feb 25th, 09'- Script creation date
//** This notice must stay intact for legal use

var fieldlimiter = {

	defaultoutput: "<b>[int]</b> characters remaining in your input limit.", //default message that gets output to statusid element

	uncheckedkeycodes: /(8)|(13)|(16)|(17)|(18)/, //keycodes that are not checked, even when limit has been reached. See http://www.javascriptkit.com/jsref/eventkeyboardmouse.shtml for avail keycodes

	limitinput: function(e, config) {
		var e = window.event || e
		var thefield = config.thefield
		var keyunicode = e.charCode || e.keyCode
		if (!this.uncheckedkeycodes.test(keyunicode)) {
			if (thefield.value.length >= config.maxlength) {
				if (e.preventDefault)
					e.preventDefault()
				return false
			}
		}
	},

	showlimit: function(config) {
		var thefield = config.thefield
		var statusids = config.statusids
		var charsleft = config.maxlength - thefield.value.length
		if (charsleft < 0) //if user has exceeded input limit (possible if cut and paste text into field)
			thefield.value = thefield.value.substring(0, config.maxlength) //trim input
		for (var i = 0; i < statusids.length; i++) {
			var statusdiv = document.getElementById(statusids[i])
			if (statusdiv) //if status DIV defined
				statusdiv.innerHTML = this.defaultoutput.replace("[int]", Math.max(0, charsleft))
		}
		config.onkeypress.call(thefield, config.maxlength, thefield.value.length)
	},

	cleanup: function(config) {
		for (var prop in config) {
			config[prop] = null
		}
	},


	addEvent: function(targetarr, functionref, tasktype) {
		if (targetarr.length > 0) {
			var target = targetarr.shift()
			if (target.addEventListener)
				target.addEventListener(tasktype, functionref, false)
			else if (target.attachEvent)
				target.attachEvent('on' + tasktype, function() { return functionref.call(target, window.event) })
			this.addEvent(targetarr, functionref, tasktype)
		}
	},

	setup: function(config) {
		if (config.thefield) { //if form field exists
			config.onkeypress = config.onkeypress || function() { }
			config.thefield.value = config.thefield.value
			this.showlimit(config)
			this.addEvent([window], function(e) { fieldlimiter.showlimit(config) }, "load")
			this.addEvent([window], function(e) { fieldlimiter.cleanup(config) }, "unload")
			this.addEvent([config.thefield], function(e) { return fieldlimiter.limitinput(e, config) }, "keypress")
			this.addEvent([config.thefield], function() { fieldlimiter.showlimit(config) }, "keyup")
		}
	}

}