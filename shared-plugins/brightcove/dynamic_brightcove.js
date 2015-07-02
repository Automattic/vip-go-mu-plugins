//TODO namespace
var BCL = {};

(function ($) {
//brightcove.wordpress = { 
var singlePlayerTemplate = "<div style=\"display:none\"></div><object id=\"myExperienceVideo\" class=\"BrightcoveExperience singlePlayer\"><param name=\"bgcolor\" value=\"#FFFFFF\" /><param name=\"wmode\" value=\"transparent\" /><param name=\"width\" value=\"{{width}}\" /><param name=\"height\" value=\"{{height}}\" /><param name=\"playerID\" value=\"{{playerID}}\" /><param name=\"playerKey\" value=\"{{playerKey}}\" /><param name=\"isVid\" value=\"true\" /><param name=\"isUI\" value=\"true\" /><param name=\"dynamicStreaming\" value=\"true\" /><param name=\"@videoPlayer\" value=\"{{videoID}}\" /><param name='includeAPI' value='true' /><param name='templateReadyHandler' value='BCL.onTemplateReadyVideo' /><param name='templateErrorHandler' value='BCL.onTemplateErrorVideo' /><param name='linkBaseURL' value='{{linkUrl}}' /></object>";
var playlistPlayerTemplate = "<div style=\"display:none\"></div><object id=\"myExperiencePlaylist\" class=\"BrightcoveExperience playlistPlayer\"><param name=\"bgcolor\" value=\"#FFFFFF\" /><param name=\"wmode\" value=\"transparent\" /><param name=\"width\" value=\"{{width}}\" /><param name=\"height\" value=\"{{height}}\" /><param name=\"playerID\" value=\"{{playerID}}\" /><param name=\"playerKey\" value=\"{{playerKey}}\" /><param name=\"isVid\" value=\"true\" /><param name=\"isUI\" value=\"true\" /><param name=\"dynamicStreaming\" value=\"true\" /><param name=\"@playlistTabs\" value=\"{{playlistID}}\" /><param name=\"@videoList\" value=\"{{playlistID}}\" /><param name=\"@playlistCombo\" value=\"{{playlistID}}\" /><param name='includeAPI' value='true' /><param name='templateReadyHandler' value='BCL.onTemplateReadyVideo' /><param name='linkBaseURL' value='{{linkUrl}}' /></object>";


playerDataPlaylist = {
   	"playerKey" : "",
   	"playerID" : "",
    "width" : "", 
    "height" : "",
    "playlistID":"",
    "linkUrl":"",
    "isRef" : false
  };

playerDataPlayer = {
    "playerKey" : "",
    "playerID" : "",
    "width" : "", 
    "height" : "",
    "videoID" : "",
    "linkUrl":"",
    "isRef" : false
  };

  
  //Helper functions for video player
getDefaultHeight = function () {
	return $('#bc-default-height').val();
}
getDefaultWidth = function () {
	return $('#bc-default-width').val();
}

getDefaultPlayerID = function () {
	return $('#bc-default-player').val();
}

//Helper functions for playlist player
getDefaultHeightPlaylist = function () {
	return $('#bc-default-height-playlist').val();
}

getDefaultWidthPlaylist = function () {	
	return $('#bc-default-width-playlist').val();
}

getDefaultPlayerKeyPlaylist = function () {
	return $('#bc-default-player-playlist-key').val();
}
  
getDefaultLinkUrl = function () {
	return $('#bc-default-link').val();
}  

addPlayer = function (typeOfPlayer)	{
	hideErrorMessage();
	var playerHTML;
	
	if (typeOfPlayer == 'video')	{
		playerDataPlayer.linkUrl = getDefaultLinkUrl();
		playerHTML = replaceTokens(singlePlayerTemplate, playerDataPlayer, typeOfPlayer);
		$('#dynamic-bc-placeholder-video').html(playerHTML);
		$('.video-hide').removeClass('hidden');

	} else if (typeOfPlayer == 'playlist') {
		playerDataPlaylist.linkUrl = getDefaultLinkUrl();
		playerHTML = replaceTokens(playlistPlayerTemplate, playerDataPlaylist, typeOfPlayer);
		$('#dynamic-bc-placeholder-playlist').html(playerHTML);	
		$('.playlist-hide').removeClass('hidden');
	} 
	
	brightcove.createExperiences();
	if(typeOfPlayer == 'video'){
		$('#video-shortcode-button').removeAttr('disabled');
	} else if (typeOfPlayer == 'playlist') {
		$('#playlist-shortcode-button').removeAttr('disabled');
	}

}

setPlayerDataExpress = function (typeOfPlayer) {
	getVideoID(typeOfPlayer);
	if (typeOfPlayer == 'video'){
		setAsRef('video',$('#bc-video-ref').is(':checked'));
	} else {
		setAsRef('playlist',$('#bc-playlist-ref').is(':checked'));
	}
	changeHeight(typeOfPlayer);
	changeWidth(typeOfPlayer);
	changePlayerID(typeOfPlayer);
	
}

getVideoID = function (typeOfPlayer) {
	
	if (typeOfPlayer == 'video') {
		playerDataPlayer.videoID = $('#bc-video').val();
	} else if (typeOfPlayer == 'playlist') {;
		playerDataPlaylist.playlistID = parsePlaylistIds($('#bc-playlist').val());
	}
}

parsePlaylistIds = function (listOfIds) {
	var regex = /[\s,]+/g;
	listOfIds = listOfIds.replace(regex, ",");
	return listOfIds;
}

//Helper functions to set height, width and playerID
changeHeight = function (typeOfPlayer) {
	if (typeOfPlayer == 'video') {
		playerDataPlayer.height = $('#bc-height').val();
		//TODO check javascript is value set?
		if (playerDataPlayer.height == '' || playerDataPlaylist.height == undefined){
			playerDataPlayer.height=getDefaultHeight();
		}
	} else if (typeOfPlayer == 'playlist') {
		playerDataPlaylist.height = $('#bc-height-playlist').val();
		if (playerDataPlaylist.height == '' || playerDataPlaylist.height == undefined){
			playerDataPlaylist.height=getDefaultHeightPlaylist();
		}
	}
}

changeWidth = function (typeOfPlayer) {
		
	if (typeOfPlayer == 'video') {
		playerDataPlayer.width = $('#bc-width').val();
		if (playerDataPlayer.width == '' || playerDataPlaylist.width == undefined){
			playerDataPlayer.width=getDefaultWidth();
		}
	} else if (typeOfPlayer == 'playlist') {
		playerDataPlaylist.width = $('#bc-width-playlist').val();
		if (playerDataPlaylist.width == '' || playerDataPlaylist.width == undefined){
			playerDataPlaylist.width=getDefaultWidthPlaylist();
		}
	}
}

setAsRef = function (typeOfPlayer, ifRef) {
	$('#playlist-warning').remove();
	if (ifRef == true){
		if (typeOfPlayer == 'video') {
			playerDataPlayer.videoID = "ref:"+playerDataPlayer.videoID;
		} else if (typeOfPlayer == 'playlist') {
			if (playerDataPlaylist.playlistID.split(',').length > 1) {
				$('#playlist-settings').before("<div id='playlist-warning' class='error'><p> Warning: Playlist players with multiple reference IDs will not render on iOS devices such as iPads and iPhones</p></div>");
			}
			playerDataPlaylist.playlistID = "ref:"+playerDataPlaylist.playlistID;
		}	
	} else {
		if (typeOfPlayer == 'video') {
		getVideoID('video');
	} else if (typeOfPlayer == 'playlist') {
		getVideoID('playlist');
		}
	}	
	
}

changePlayerID = function (typeOfPlayer) {
	if (typeOfPlayer == 'video') {
		playerDataPlayer.playerID = $('#bc-player').val();
		if (playerDataPlayer.playerID == undefined || playerDataPlayer.playerID == ''){
			playerDataPlayer.playerID=getDefaultPlayerID();
		}
	} else if (typeOfPlayer == 'playlist') {
		playerDataPlaylist.playerKey = $('#bc-player-playlist-key').val();
		if (playerDataPlaylist.playerKey == undefined || playerDataPlaylist.playerKey == '') {
		playerDataPlaylist.playerKey=getDefaultPlayerKeyPlaylist();
		}
	}
}

updateTab =function (typeOfPlayer) {	
	if (typeOfPlayer == 'playlist'){
		$('.video-hide').addClass('hidden');
		$('.playlist-hide').removeClass('hidden');
		if ($('#bc-playlist').val() == undefined || $('#bc-playlist').val() == '') {
			$('.playlist-hide.player-preview').addClass('hidden');
		}
		
	} else if (typeOfPlayer == 'video') {
		$('.playlist-hide').addClass('hidden');
		$('.video-hide').removeClass('hidden');
		if ($('#bc-video').val() == undefined || $('#bc-video').val() == '') {
			$('.video-hide.player-preview').addClass('hidden');
		}
	}	
}

insertShortcode = function(typeOfPlayer) {
 var shortcode;
    if (typeOfPlayer == 'video') {
      	shortcode = '[brightcove videoID='+playerDataPlayer.videoID+' playerID='+playerDataPlayer.playerID;
      	if (playerDataPlayer.linkUrl)
      		shortcode += ' link_url='+playerDataPlayer.linkUrl;
   		if (playerDataPlayer.height)
    		shortcode += ' height='+playerDataPlayer.height;
    	if (playerDataPlayer.width)
    		shortcode += ' width='+playerDataPlayer.width;      		     
    } else if (typeOfPlayer == 'playlist') {
      	shortcode = '[brightcove playlistID='+playerDataPlaylist.playlistID+' playerKey='+playerDataPlaylist.playerKey;
    	if (playerDataPlaylist.linkUrl)
    		shortcode += ' link_url='+playerDataPlaylist.linkUrl; 
   		if (playerDataPlaylist.height)
    		shortcode += ' height='+playerDataPlaylist.height;
    	if (playerDataPlaylist.width)
    		shortcode += ' width='+playerDataPlaylist.width;    		     
    }	
 

    shortcode += ']';
    
   	var win = window.dialogArguments || opener || parent || top;
   	win.send_to_editor(shortcode);
}
    
hideSettings = function (typeOfPlayer) {
	if (typeOfPlayer == 'playlist') {
		$('#playlist-settings').addClass('hidden');
	} else { 
		$('#video-settings').addClass('hidden');
	}
}

showSettings = function (typeOfPlayer) {
	if (typeOfPlayer == 'playlist') {
		$('#playlist-settings').removeClass('hidden');
	} else { 
		$('#video-settings').removeClass('hidden');
	}
}

clearPlayerData = function (typeOfPlayer) {
	if (typeOfPlayer == 'playlist') {
		playerDataPlaylist = {
		    "playerID" : "",
		    "width" : "", 
		    "height" : "",
		    "playlistID":"",
		    "isRef" : false
		};
	} else {
		playerDataPlayer = {
		    "playerID" : "",
		    "width" : "", 
		    "height" : "",
		    "videoID":"",
		    "isRef" : false
		};
	}
	
}

/**************************************** Media API Specific Function ***************************************/

formatDate = function (date) {
	return '<td class="title">'+(date.getDay()+1)+'/'+(date.getMonth()+1)+'/'+(date.getFullYear()+1)+'</td>';	
}

setPlayerDataMediaAPI = function (typeOfPlayer, videoID) {

	if (typeOfPlayer == 'playlist') {
		playerDataPlaylist.playlistID = videoID;
	} else {
		playerDataPlayer.videoID = videoID;
	}
	changeHeight(typeOfPlayer);
	changeWidth(typeOfPlayer);
	changePlayerID(typeOfPlayer);

}

/////////////////////// Playlists /////////////////////////

seeAllPlaylists = function(pageNumber) {

	clearPlayerData('playlist');
	if (pageNumber == 0) {
		$('#bc-video-search-playlist').addClass('disable');
		$('#bc-video-search-playlist').prepend("<img class='loading-img-api' src='/wp-includes/js/tinymce/themes/advanced/skins/default/img/progress.gif' />");
	}    
    token = $('#bc-api-key').val();
    /*Create URL that is called to search for videos*/
    var url= [
      "command=find_all_playlists",
      "&token=", encodeURIComponent(token),
      '&page_size=25',
      '&page_number=',encodeURIComponent(pageNumber),
      '&get_item_count=true',
      "&callback=",encodeURIComponent("displayPlaylists")
    ].join("");

    BCMAPI.inject(url);
  };

displayPlaylists = function (pResponse) {
	var innerHTML = playlistResults(pResponse), 
	pageNumber = pResponse.page_number, 
	totalCount = pResponse.total_count, 
	totalNumberOfPages = Math.ceil(totalCount/pResponse.page_size), 
	prevButton ='', 
	nextButton = '',
	pageClass='',
	pagesHTML = "<p class='pageOfPage'>Page " + ( pageNumber + 1 ) + " of " +totalNumberOfPages+"</p>";
	
	$('#bc-video-search-playlist').removeClass('disable');
	$('#playlist-preview').remove();
	
	if (pageNumber > 0) {
		pageClass='hidden';
		prevButton = "<button class='prev-page button' data-prevPage='"+(pageNumber-1)+"'> Previous Page </button>";
	} 
	if ( pageNumber + 1 < totalNumberOfPages ){
		nextButton = "<button class='next-page button' data-nextPage='"+(pageNumber+1)+"'> Next Page </button>";	
	}

	//Add table to window
	if (totalCount == 0) {
		$('#bc-video-search-playlist').html(innerHTML);
	} else {
		innerHTML = "<div id='playlist-page-"+pageNumber+"' class='"+pageClass+"'>"+innerHTML+pagesHTML+"<div class='button-bar'>"+prevButton+nextButton+"</div></div>";
		$('#bc-video-search-playlist').html(innerHTML);
		$('.loading-img-api').remove();
	}
	
	//Add Playlists button
	//Button is intitally disabled until at least one playlist is checked
	$('#bc-video-search-playlist').before("<button class='button' disabled='disabled' id='playlist-preview'>Assign Playlist(s) to Player</button>");

	//Binds the preview playlists function to the Add Playlists button
	$('#playlist-preview').bind('click', function () {
		previewPlaylist();
		$('#playlist-preview').remove();
	});

	//Once a playlist is checked the Add Playlists button is enabled
	$('.playlist-checkbox').bind('change', function () {
		$('#playlist-preview').removeAttr('disabled');
	});

	$('.prev-page').bind('click', function() {
		var pageNumber = $(this).data('prevpage');
		showPage(pageNumber, 'playlist');
	});

	$('.next-page').bind('click', function() {
		var pageNumber = $(this).data('nextpage');
		showPage(pageNumber, 'playlist');
	});

	//Loads the next page of playlist results silently in background
	if (pageNumber+1 < totalNumberOfPages){
		seeAllPlaylists(pageNumber+1);	
	}
};

playlistResults = function (pResponse) {
	
//Defined heading and other variables
  	var heading = '<table class="widefat"><thead><tr><th></th><th></th><th>Name</th><th>Number of Videos</th><th>Last Updated</th></tr></thead>',
	  	lastModifiedDate,numVideos,disable, disableClass, currentName, currentVid, imgSrc, innerHTML='';
	
	//Loop through all playlists in pResponse
	//Gets thumbnail, Name, Number of Videos and Last Updated Date
	for (var pVideo in pResponse.items) {
		
		//Thumbnail 
		if (pResponse.items[pVideo].videos.length > 0) {
          imgSrc=pResponse.items[pVideo].videos[0].thumbnailURL;
        }
        currentVid="<td><img class='pinkynail toggle' src='"+imgSrc+"'/></td>";	

        //Name
        regex=/'/;    
	    currentName=constrain(pResponse.items[pVideo].name,25); 
	    currentTitle = currentName.replace(regex, "");
        currentName="<td class='title'>"+currentName+"</td>";

        //Number Of Videos
        //Disable the checkbox if the number of videos is 0
	    disableClass = '';
		disable = '';
      	numVideos=pResponse.items[pVideo].videos.length;    
        if (numVideos == 0) {
          lastModifiedDate ='<td class="title"></td>';
          disable='disabled=disabled';
          disableClass='disable';
        }
        numVideos='<td class="text-align-center title">'+numVideos+'</td>';

        //Last Updated
        if (pResponse.items[pVideo].videos.length > 0) {
	  	lastModifiedDate = Number.MAX_VALUE;
	    $.each(pResponse.items[pVideo].videos, function(key,value) {
	      tempDate = value.lastModifiedDate;
		  	if (tempDate < lastModifiedDate) {
		    	lastModifiedDate = tempDate;
		  	}
	    });
	    lastModifiedDate = formatDate(new Date(parseInt(lastModifiedDate)));
		}
		//Create table row
        innerHTML = innerHTML+"<tr data-videoID='"+pResponse.items[pVideo].id+"' title='"+currentTitle+"'class='"+disableClass+" media-item child-of-2 preloaded'><td><input "+disable+" class='playlist-checkbox' type='checkbox'/></td>"+currentVid+currentName+numVideos+lastModifiedDate+"</tr>";  
    }
	//Add heading to table and close table tag
	innerHTML = heading + innerHTML +"</table>" ;
	return innerHTML;
}

previewPlaylist = function () {
	showSettings('playlists');
	var playlists = [];
	//Loop through the list of playlists and get all the checked ones
    $('#bc-video-search-playlist tr').each(function() {
      if ($(this).find('input').is(':checked')) {
        playlists.push($(this).data('videoid'));
      }
    });

    if (playlists.length == 0) return;
	//Join them all into a comma seperated list
    playlists = playlists.join(',');
    //Add the HTML to the page so that the player can be added
    generateHTMLForPlaylist();
    //Set the Player Data
    setPlayerDataMediaAPI('playlist', playlists);
    //Add the player
    addPlayer('playlist');
};

generateHTMLForPlaylist = function () {
	$('#bc-video-search-playlist').before("<button class='button see-all-playlists' >See all playlists</button>");
	//Adds the functionality of hiding the settings and showing the playlists when clicked, then removing itself from the DOM
	$('.see-all-playlists').bind('click', function() {
		hideSettings('playlist');
		seeAllPlaylists(0);
		$('.see-all-playlists').remove();
		$('#dynamic-bc-placeholder-playlist').remove();
	})
	
	$('#bc-video-search-playlist').html('<div id="dynamic-bc-placeholder-playlist"></div>');
}

/////////////////////// Videos /////////////////////////

getAllVideos = function (pageNumber)
{
	clearPlayerData('video');
	if (pageNumber == 0) {
		$('#bc-video-search-video').addClass('disable');
		$('#bc-video-search-video').prepend("<img class='loading-img-api' src='/wp-includes/js/tinymce/themes/advanced/skins/default/img/progress.gif' />");
	}    
    token = $('#bc-api-key').val();
    /*Create URL that is called to search for videos*/
    var url= [
      "command=find_all_videos",
      "&token=", encodeURIComponent(token),
      '&page_size=25',
      '&page_number=',encodeURIComponent(pageNumber),
      '&get_item_count=true',
      "&callback=",encodeURIComponent("displayAllVideos")
    ].join("");
    
    BCMAPI.inject(url);
};

displayAllVideos = function (pResponse) {
	displayPagedVideoSearchResults (pResponse, "all"); 
}

displaySearchedVideos = function (pResponse) {
	if (pResponse.items.length == 0) {
		$('#bc-video-search-video').html('<div class="no-results bc-error error clear"><p>No results were found for this search.</p></div>').removeClass('disable');
	} else  {
		displayPagedVideoSearchResults (pResponse, "search");
		createShowAllVideosButton();
	}
	
}

displayPagedVideoSearchResults = function (pResponse, allOrSearch) {
	var html = videoResults (pResponse), 
	pageNumber = pResponse.page_number, 
	totalCount = pResponse.total_count, 
	totalNumberOfPages = Math.ceil(totalCount/pResponse.page_size), 
	prevButton ='', 
	nextButton = '',
	pageClass='',
	pagesHTML = "<p class='pageOfPage'>Page " + ( pageNumber + 1 ) + " of " + totalNumberOfPages + "</p>";

	if (pageNumber > 0) {
		pageClass='hidden';
		prevButton = "<button class='prev-page button' data-prevPage='" + ( pageNumber - 1 ) + "'> Previous Page </button>";
	} 
	if (pageNumber + 1 < totalNumberOfPages ){
		nextButton = "<button class='next-page button' data-nextPage='" + ( pageNumber + 1 ) + "'> Next Page </button>";	
	}

	if (totalCount == 0) {
		$('#bc-video-search-video').html(html).removeClass('disable');
	} else {
		html = "<div id='video-page-" + pageNumber + "' class='video-page " + pageClass + "'>" + html + pagesHTML + "<div class='clearfix button-bar'>"+prevButton+nextButton+"</div></div>";
		$('#bc-video-search-video').append(html).removeClass('disable');
		$('.loading-img-api').remove();
	}
	$('#video-page-'+pageNumber).find('.bc-video').bind('click', function() {
		previewVideo($(this).data('videoid'));

	});
	 
	$('.prev-page').bind('click', function() {
		var pageNumber = $(this).data('prevpage');
		showPage(pageNumber, 'video');
	});

	$('.next-page').bind('click', function() {
		var pageNumber = $(this).data('nextpage');
		showPage(pageNumber, 'video');
	});

	if (allOrSearch == 'all') {
		if (pageNumber+1 < totalNumberOfPages){
		getAllVideos(pageNumber+1);	
		}
	} else if (allOrSearch == 'search') {
		if (pageNumber+1 < totalNumberOfPages){
		searchForVideos(pageNumber+1);	
		}
	}
	showPage( 0, 'video' );
}

showPage = function (pageNumber,videoOrPlaylist) {
	var nextPage = pageNumber + 1, prevPage = pageNumber - 1;
	$('#'+videoOrPlaylist+'-page-' + pageNumber).removeClass('hidden');
	$('#'+videoOrPlaylist+'-page-' + prevPage).addClass('hidden');
	$('#'+videoOrPlaylist+'-page-' + nextPage).addClass('hidden');
}

searchForVideos = function (pageNumber) {
	clearPlayerData('video');
    searchParams = $.trim($('#bc-search-field').val());
    if (!searchParams) return;

	$('#bc-video-search-video').addClass('disable');    
	if (pageNumber == 0) {
    	$('#bc-video-search-video').prepend("<img class='loading-img-api' src='/wp-includes/js/tinymce/themes/advanced/skins/default/img/progress.gif' />");
    }
    token = $('#bc-api-key').val();
    /*Create URL that is called to search for videos*/
    var url= [
      "command=search_videos",
      "&token=", encodeURIComponent(token),
      "&any=search_text:", encodeURIComponent(searchParams),
      "&any=custom_fields:", encodeURIComponent(searchParams),
      "&any=tag:",encodeURIComponent(searchParams),
      '&page_size=25',
      '&page_number=',encodeURIComponent(pageNumber),
      '&get_item_count=true',
      "&callback=",encodeURIComponent("displaySearchedVideos")
    ].join("");
    
    BCMAPI.inject(url);
};


videoResults = function (pResponse) {
 	var currentName, imgSrc, currentVid, lengthMin, lengthSec, length, date, heading, innerHTML = '';
 	
 	//Checks to see if any results are returned, if not display error message
 	if (pResponse.items.length == 0) {
		innerHTML='<div class="no-results bc-error error clear">No results were found for this search.</div>';
		$('#bc-video-search-video').html(innerHTML).removeClass('disable');
    	
    //If results are returned display them
	} else {
		//Set up heading for the table
	 	heading = '<table class="clearfix widefat"><thead><tr><th></th><th>Name</th><th>Duration</th><th>Published Date</th></tr></thead>';
	 	//Loops through the list of returned videos and gets the Thumbnail, Name, Duration and Published Date 
	 	for (var pVideo in pResponse.items) {
	 		//Thumbnail
		    imgSrc=pResponse.items[pVideo].thumbnailURL;
			currentVid = imgSrc ? "<td><img class='pinkynail toggle' src='"+pResponse.items[pVideo].thumbnailURL+"'/></td>" : '<td class="no-thumbnail"></td>';

			//Name
			currentName="<td class='title'>"+constrain(pResponse.items[pVideo].name,25)+"</td>";
		    
		 	//Duration
		 	lengthMin = Math.floor(pResponse.items[pVideo].length/60000);
			lengthSec = Math.floor((pResponse.items[pVideo].length%60000)/1000);
		    if (lengthSec < 10) {
				lengthSec="0"+lengthSec;
			}
			length ="<td class='title'>"+(lengthMin+":"+lengthSec)+"</td>";
		    
		    //Published Date
			date=formatDate(new Date(parseInt(pResponse.items[pVideo].publishedDate)));
			 
			//Combine all data to form a table row
		    innerHTML = innerHTML+"<tr data-videoID='"+pResponse.items[pVideo].id+"' title='"+pResponse.items[pVideo].name+"' class='bc-video media-item child-of-2 preloaded'>"+currentVid+currentName+length+date+"</tr>";  
	    	}
	    //Add the heading to all the table rows and close the table
	    innerHTML = heading + innerHTML +"</table>" ;

	    return innerHTML;
	}
}

previewVideo = function (videoID) {	
	$('.video-page').remove();
	showSettings('video');
	generateHTMLForVideo();
	createShowAllVideosButton();
	setPlayerDataMediaAPI('video',videoID);
	addPlayer('video');
}

createShowAllVideosButton = function () {
	$('#search-form').before('<button class="button see-all-videos">See All Videos</Button>');
	$('.see-all-videos').bind('click', function () {
		$('.see-all-videos').remove();
		$('#dynamic-bc-placeholder-video').remove();
		hideSettings('video');
		$('#bc-search-field').val('');
	  	getAllVideos(0);
	});
}

generateHTMLForVideo = function () {
	$('#bc-video-search-video').html('<div id="dynamic-bc-placeholder-video"></div>');
}

/////////////////// Template Functions ////////////////////////////

BCL.onTemplateErrorVideo = function (event) {
	if (event.errorType != 'serviceUnavailable') {
		$('.video-hide.player-preview').addClass('hidden');
	    var errorType = ("errorType: " + event.errorType)
	 	$('#specific-error').remove();
	    $('#bc-error').removeClass('hidden');
		$('#bc-error').append('<div id="specific-error">'+errorType+'</div>');
	}
  }

BCL.onTemplateErrorPlaylist = function (event) {
	if (event.errorType != 'serviceUnavailable') {
		$('.playlist-hide.player-preview').addClass('hidden');
	    var errorType = ("errorType: " + event.errorType)
	 	$('#specific-error').remove();
	    $('#bc-error').removeClass('hidden');
		$('#bc-error').append('<div id="specific-error">'+errorType+'</div>');
	}  
}
 
BCL.onTemplateReadyVideo = function(event) {  
    player = brightcove.api.getExperience("myExperienceVideo");
    // get a reference to the video player
    videoPlayer = player.getModule(brightcove.api.modules.APIModules.VIDEO_PLAYER);
    videoPlayer.getCurrentVideo(function(videoDTO) {
      currentVideo = videoDTO;
      $('#bc-title-video').html(currentVideo.displayName);
      $('#bc-description-video').html(currentVideo.shortDescription);
    });
  }

  BCL.onTemplateReadyPlaylist = function(event) {  
    player = brightcove.api.getExperience("myExperiencePlaylist");
    // get a reference to the video player
    videoPlayer = player.getModule(brightcove.api.modules.APIModules.VIDEO_PLAYER);
    videoPlayer.getCurrentVideo(function(videoDTO) {
      currentVideo = videoDTO;
      $('#bc-title-playlist').html(currentVideo.displayName);
      $('#bc-description-playlist').html(currentVideo.shortDescription);
    });
  }

////////////////////////General Helper Functions/////////////////

hideErrorMessage = function () {	
	$('#bc-error').addClass('hidden');
}

replaceTokens = function (html, data, type) {
	var m;
	var i = 0;
	var match = html.match(data instanceof Array ? /{{\d+}}/g : /{{\w+}}/g) || [];
	while (m = match[i++]) {
		if(m.substr(2, m.length-4) === 'width' && data[m.substr(2, m.length-4)] === undefined){
			if(type === 'video')
				html = html.replace(m, getDefaultWidth());
			else
				html = html.replace(m, getDefaultWidthPlaylist());
		}
			
		else if(m.substr(2, m.length-4) === 'height' && data[m.substr(2, m.length-4)] === undefined){
			if(type === 'video')
				html = html.replace(m, getDefaultHeight());
			else
				html = html.replace(m, getDefaultHeightPlaylist());
		}
		else
			html = html.replace(m, data[m.substr(2, m.length-4)]);
	}
	return html;
};

constrain = function (str,n){
    if (str.length > n)
      return str.substr(0, n) + '&hellip;';
    return str; 
}

//validation code for player settings
validatePlayerSettings = function (id) {
	
	$(id).validate ({ 
       rules: {
        	bcHeight: 'digits',
        	bcWidth: 'digits',
        	bcPlayer : 'digits'
        }, 
        messages: {
	        bcHeight : "Please enter a valid height",
	        bcWidth : "Please enter a valid width",
	        bcPlayer : "Please enter a valid player number"
        }       
      });
}

//All of the extra validation needed for these forms
validate = function () {

	//Sets up validation messages and rules for the player settings
	validatePlayerSettings('#video-settings');
	validatePlayerSettings('#playlist-settings');

    //Sets up validation for the video so that if reference ID is not checked then it does not have to be a number
      $('#validate-video').validate({
        rules : {
          bcVideo : {
            number : { depends: function(element) {
                if ($("#bc-video-ref").attr('checked') == 'checked'){
                return false;
                } else {
                 return true;
                }
              }
            }
          }
        },//Sets up custom message
        messages: {
          bcVideo: {
          	number:"Please enter a number or check the box for reference ID"
          } 
        }
      });
    //Adds two methods to the validator that deals with a list of playlist IDs and a list of reference IDs
    $.validator.addMethod("listOfIds", function(value, element) {
    	//TODO see if you can remove first bracketed group
    	return (this.optional(element) || /^[^a-z\W][0-9,\s]*$/ig.test(value));
    }, "Please enter a single playlist ID or a list of IDs seperated by commas or spaces.");

    $.validator.addMethod("listOfRefIds", function(value, element) {
    	//TODO see if you can remove first bracketed group
    	return (this.optional(element) || /^[^\W][a-z0-9,\s_]*$/ig.test(value));
    }, "Please enter a single playlist ID or a list of IDs seperated by commas or spaces.");

    //Validates the list of playlist IDs
    $('#validate-playlist').validate({
      rules: {
        bcPlaylist : {
          listOfIds : { 
            depends : function(element) {
              if ($("#bc-playlist-ref").attr('checked') == 'checked'){
                return false;
              } else {
               return true;
              }
            }
          },
          listOfRefIds : { 
            depends : function(element) {
              if ($("#bc-playlist-ref").attr('checked') == '') {
                return false;
              } else {
                return true;
              }
            }
          }
        }
      }
    });

    //Makes it so form validates on the fly on #bc-video-ref changing
    $('#bc-video-ref').bind('change',function() {
      $('#bc-video').removeClass('valid').removeClass('error');
      //TODO check for underscores
      $('#validate-video').valid();
    });

    $('#bc-playlist-ref').bind('change',function() {
      $('#bc-playlist').removeClass('valid').removeClass('error');
      //TODO check for underscores
      $('#validate-playlist').valid();
    });
}

$(function () {

	validate();

	var shortcodeHandlerVideo = function () {
		insertShortcode('video');
		return false;
	}

	var shortcodeHandlerPlaylist = function () {
		insertShortcode('playlist');
		return false;
	}

	var searchForVideosHandler = function () {
		hideSettings('video');
		searchForVideos(0);
		return false;
	}

////////////////////////////Express tab//////////////////////////////


	if ($('#defaults-not-set').data('defaultsset') == false) {
		$('#defaults-not-set').removeClass('hidden');
		$('.no-error').addClass('hidden');
	}


	//Checks to see if we are in express tabs or media API tabs
	if ($('#tabs').length > 0) {
	    $("#tabs").tabs();
	    $('.video-tab').bind('click', function (){
	    	hideErrorMessage();
			updateTab('video');
		});
		$('.playlist-tab').bind('click', function (){
			hideErrorMessage();
			updateTab('playlist');
		})
	}

	//Binds keydown for video tab
	$('#bc-video').keydown( function () {
		window.clearTimeout(this.timeOut);
		this.timeOut = window.setTimeout (function() {
			$('#validate-video').valid();
			setPlayerDataExpress('video');
			addPlayer('video');
		}, 400);
		
	});

	$('#bc-video-ref').bind('change', function () {
		setPlayerDataExpress('video');
		addPlayer('video');
	});

	$('#bc-player').keydown( function () {
		window.clearTimeout(this.timeOut);
		this.timeOut = window.setTimeout (function() {
			changePlayerID('video');
			addPlayer('video');
		}, 400);
	});

	$('#bc-width').keydown( function () {
		window.clearTimeout(this.timeOut);
		this.timeOut = window.setTimeout (function() {
			changeWidth('video');
			addPlayer('video');
		}, 400);

	});

	$('#bc-height').keydown( function () {
		window.clearTimeout(this.timeOut);
		this.timeOut = window.setTimeout (function() {
		changeHeight('video');
		addPlayer('video');
		}, 400);
	});

	$('#video-settings').bind('submit', shortcodeHandlerVideo);
	$('#validate-video').bind('submit', shortcodeHandlerVideo);
	$('#video-shortcode-button').bind('click', shortcodeHandlerVideo);


	//Intitally always hides the playlist settings since video tab is first displayed
	$('#playlist-settings').addClass('hidden');

	///////////////////////////////API TAB/////////////////////////////

	//Checks to see if we are in express tabs or media API tabs
	if ($('#tabs-api').length > 0) {
	    $("#tabs-api").tabs();
	  	hideSettings('video');
	  	getAllVideos(0);
	    $('.video-tab-api').bind('click', function (){
	    	hideSettings('video');
	    	hideErrorMessage();
			updateTab('video');
			if (playerDataPlayer.videoID == '' || playerDataPlayer.videoID == undefined) {
				hideSettings('video');
			}
		});

		$('#search-form').bind('submit', searchForVideosHandler);
		$('#bc-search').bind('click', searchForVideosHandler);		
	}

	//Binds changes for playlist tab

	$('.playlist-tab-api').bind('click', function (){
		hideErrorMessage();
		updateTab('playlist');
		if (playerDataPlaylist.playlistID == '' || playerDataPlaylist.playlistID == undefined) {
			hideSettings('playlist');
			seeAllPlaylists(0);
		}	
	});

	$('#bc-playlist').bind('keydown', function () {
		window.clearTimeout(this.timeOut);
		this.timeOut = window.setTimeout (function() {
			$('#validate-playlist').valid();
			setPlayerDataExpress('playlist');
			addPlayer('playlist');
		}, 400);
	});

	$('#bc-playlist-ref').bind('change', function () {
		setPlayerDataExpress('playlist');
		addPlayer('playlist');
	});

	$('#bc-player-playlist-key').bind('keydown', function () {
		window.clearTimeout(this.timeOut);
		this.timeOut = window.setTimeout (function() {
			changePlayerID('playlist');
			addPlayer('playlist');
		}, 400);
	});

	$('#bc-width-playlist').bind('keydown', function () {
		window.clearTimeout(this.timeOut);
		this.timeOut = window.setTimeout (function() {
			changeWidth('playlist');
			addPlayer('playlist');
		}, 400);
	});

	$('#bc-height-playlist').bind('keydown', function () {
		window.clearTimeout(this.timeOut);
		this.timeOut = window.setTimeout (function() {
			changeHeight('playlist');
			addPlayer('playlist');
		}, 400);
	});

	$('#playlist-settings').bind('submit', shortcodeHandlerPlaylist);
	$('#validate-playlist').bind('submit', shortcodeHandlerPlaylist);
	$('#playlist-shortcode-button').bind('click', shortcodeHandlerPlaylist);

	
	$('.loading-img').remove();
	$('.no-error').css('visibility','visible');
	
	//Fix for IE for placeholder
    $(":input[placeholder]").placeholder();
});

})(jQuery);
