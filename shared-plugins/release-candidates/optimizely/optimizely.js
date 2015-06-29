(function( $ ) {

	/*
	The OptimizelyAPI class provides a connection to the API via Javascript 
	and lets you make authenticated calls without repeating yourself.
	We store the API token in each instance of the object, 
	and we can connect to multiple different accounts by creating new instances of the OptimizelyAPI class.
	Finally, we keep track of how many requests are outstanding so we can tell when all the calls are complete.
	*/
	OptimizelyAPI = function(token) {
		this.outstandingRequests = 0;
		this.token = token;
	}

	/*
	To call the API, we use jQuery's `$.ajax` function, which sends an asynchronous request based on a set of `options`.

	Our function takes four arguments:

	* The request `type`, like GET or POST
	* The `endpoint` to hit, like `projects/27`
	* The `data` to send along with a POST or PUT request
	* A `callback` function to run when the operation is done. The callback should take one argument, the `response`.

	We construct the URL by appending the endpoint to the base API link, and we authenticate by adding the token in the headers section.
	To send data, we set content type to JSON and encode the array as a JSON string to send over the wire.
	*/
	OptimizelyAPI.prototype.call = function( type, endpoint, data, callback ) {
		var self = this;

		var options = {
			url: 'https://www.optimizelyapis.com/experiment/v1/' + endpoint,
			type: type,
			headers: { 'Token': this.token },
			contentType: 'application/json',
			success: function( response ) {
				self.outstandingRequests -= 1;
				callback( response );
			}
		};

		if ( data ) {
			options.data = JSON.stringify( data );
			options.dataType = 'json';
		}

		this.outstandingRequests += 1;
		$.ajax( options );
	}

	// Using our `call` function, we can define convenience functions for GETs, POSTs, PUTs.
	OptimizelyAPI.prototype.get = function( endpoint, callback ) {
		this.call( 'GET', endpoint, '', callback );
	}

	OptimizelyAPI.prototype.post = function( endpoint, data, callback ) {
		this.call( 'POST', endpoint, data, callback );
	}

	OptimizelyAPI.prototype.put = function( endpoint, data, callback ) {
		this.call( 'PUT', endpoint, data, callback );
	}

	/*
	We've also added an extra convenience function, `patch`, that updates a model by changing only the specified fields. 
	The function works by reading in an entity, changing a few keys, and then sending it back to Optimizely.
	*/
	OptimizelyAPI.prototype.patch = function( endpoint, data, callback ) {
		var self = this;
		
		self.get( endpoint, function( base ) {
			for ( var key in data ) {
				base[ key ] = data[ key ];
			}
			
			self.put( endpoint, base, callback );
		});
	}
})(  jQuery  );
