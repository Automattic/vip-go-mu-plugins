/**
 * Brightcove JavaScript MAPI Wrapper 1.2 (16 FEBRUARY 2011)
 * (Formerly known as Kudos)
 *
 * REFERENCES:
 *	 Website: http://opensource.brightcove.com
 *	 Source: http://github.com/brightcoveos
 *
 * AUTHORS:
 *	 Brian Franklin <bfranklin@brightcove.com>
 *	 Matthew Congrove <mcongrove@brightcove.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, alter, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to
 * whom the Software is furnished to do so, subject to the following conditions:
 *   
 * 1. The permission granted herein does not extend to commercial use of
 * the Software by entities primarily engaged in providing online video and
 * related services.
 *  
 * 2. THE SOFTWARE IS PROVIDED "AS IS", WITHOUT ANY WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, SUITABILITY, TITLE,
 * NONINFRINGEMENT, OR THAT THE SOFTWARE WILL BE ERROR FREE. IN NO EVENT
 * SHALL THE AUTHORS, CONTRIBUTORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY WHATSOEVER, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH
 * THE SOFTWARE OR THE USE, INABILITY TO USE, OR OTHER DEALINGS IN THE SOFTWARE.
 *  
 * 3. NONE OF THE AUTHORS, CONTRIBUTORS, NOR BRIGHTCOVE SHALL BE RESPONSIBLE
 * IN ANY MANNER FOR USE OF THE SOFTWARE.  THE SOFTWARE IS PROVIDED FOR YOUR
 * CONVENIENCE AND ANY USE IS SOLELY AT YOUR OWN RISK.  NO MAINTENANCE AND/OR
 * SUPPORT OF ANY KIND IS PROVIDED FOR THE SOFTWARE.
 */

var BCMAPI = new function () {
	this.token = "";
	this.callback = "BCMAPI.flush";
	this.url = "https://api.brightcove.com/services/library";
	this.calls = [
		{ "command" : "find_all_videos", "def" : false },
		{ "command" : "find_video_by_id", "def" : "video_id" },
		{ "command" : "find_video_by_id_unfiltered", "def" : "video_id" },
		{ "command" : "find_videos_by_ids", "def" : "video_ids" },
		{ "command" : "find_videos_by_ids_unfiltered", "def" : "video_ids" },
		{ "command" : "find_video_by_reference_id", "def" : "reference_id" },
		{ "command" : "find_video_by_reference_id_unfiltered", "def" : "reference_id" },
		{ "command" : "find_videos_by_reference_ids", "def" : "reference_ids" },
		{ "command" : "find_videos_by_reference_ids_unfiltered", "def" : "reference_ids" },
		{ "command" : "find_videos_by_campaign_id", "def" : "campaign_id" },
		{ "command" : "find_videos_by_tags", "def" : "or_tags" },
		{ "command" : "find_videos_by_text", "def" : "text" },
		{ "command" : "find_videos_by_user_id", "def" : "user_id" },
		{ "command" : "find_modified_videos", "def" : "from_date" },
		{ "command" : "find_related_videos", "def" : "video_id" },
		{ "command" : "find_all_playlists", "def" : false },
		{ "command" : "find_playlist_by_id", "def" : "playlist_id" },
		{ "command" : "find_playlists_by_ids", "def" : "playlist_ids" },
		{ "command" : "find_playlist_by_reference_id", "def" : "reference_id" },
		{ "command" : "find_playlists_by_reference_ids", "def" : "reference_ids" },
		{ "command" : "find_playlists_for_player_id", "def" : "player_id" },
		{ "command" : "search_videos", "def" : "all" }
	];

	/**
	 * Injects API calls into the head of a document
	 * @since 0.1
	 * @param string [pQuery] The query string for the API call to inject
	 * @return true
	 */
	this.inject = function (pQuery) {
		var pElement = document.createElement("script");
		pElement.setAttribute("src", this.url + "?" + pQuery);
		pElement.setAttribute("type", "text/javascript");
		document.getElementsByTagName("head")[0].appendChild(pElement);
		
		return true;
	};

	/**
	 * Performs an API query.
	 * @since 1.0
	 * @param string [pCommand] A Brightcove API method
	 * @param mixed [pParams] Either an object containing the API parameters to apply to the given command, or a single value which is applied to the command's default selector
	 * @return true
	 */
	this.find = function (pCommand, pParams) {
		pCommand = pCommand.toLowerCase().replace(/(find_)|(_)|(get_)/g, "");
		pParams = pParams || null;
		var pDefault = null;
		var pQuery = "";

		for (var pCall in this.calls) {
			if (typeof this.calls[pCall].command == "undefined") {
				continue;
			}
			
			if (pCommand == this.calls[pCall].command.toLowerCase().replace(/(find_)|(_)|(get_)/g, "")) {
				pCommand = this.calls[pCall].command;
				
				if (typeof this.calls[pCall].def != "undefined") {
					pDefault = this.calls[pCall].def;
				}
				
				break;
			}
		}

		pQuery = "command=" + pCommand;

		if ((typeof pParams == "object") && pParams) {
			for (var pParam in pParams) {
				if (pParam == "selector") {
					pQuery += "&" + pDefault + "=" + encodeURIComponent(pParams[pParam]);
				} else {
					pQuery += "&" + pParam + "=" + encodeURIComponent(pParams[pParam]);
				}
			}

			if (typeof pParams.callback != "string") {
				pQuery += "&callback=" + this.callback;
			}

			if (typeof pParams.token != "string") {
				pQuery += "&token=" + this.token;
			}
		} else if (pParams) {
			pQuery += "&" + pDefault + "=" + encodeURIComponent(pParams) + "&callback=" + this.callback;
			pQuery += "&token=" + this.token;
		} else {
			pQuery += "&token=" + this.token;
			pQuery += "&callback=" + this.callback;
		}

		this.inject(pQuery);

		return true;
	};

	/**
	 * Performs an API search query
	 * @since 1.0
	 * @param mixed [pParams] Either an object containing the API parameters to apply to the given command, or a single value which is applied to the command's default selector
	 * @return true
	 */
	this.search = function (pParams) {
		return this.find("search_videos", pParams);
	};

	/**
	 * Default callback which does nothing
	 * @since 0.1
	 * @param mixed [pData] The data returned from the API
	 * @return true
	 */
	this.flush = function (pData) {
		return true;
	};
}();
