/*inform JS*/

var InformTagger = function () {
	
	'use strict';
	
	var $ = jQuery,
		oData = {},
		self = this;
	
	// *****************************************************************************
	// INIT
	
	// create Inform tag area within WP tag meta box
	self.init = function () {
		
		//console.log('Inform.init()');
		
		var aEvents;
		
		// create Inform tag area at bottom of WP tag meta box
		self.metaboxTags().append('<div id="inform-tags" class="panel tags">' +
			'<h4 class="title"><span class="inform-logo">Inform</span> suggested tags</h4>' +
			'<p class="empty">Process post to see suggestions</p>' +
		'</div>');
		self.wpTags($('#inform-tags'));
		
		// move iab meta box below tags
		self.metaboxTags().parents(self.wpMetaboxSelector()).after(self.metaboxIab().parents(self.wpMetaboxSelector()));
		
		// process (main) button
		self.btnProcess().bind('click', function () {
			self.process();
		});
		
		// required, bind handler, move to top of stack
		if (self.required() && (aEvents = jQuery('#publish').bind('click', self.publish).data('events')['click']) && aEvents.length > 1) {
			aEvents.unshift(aEvents.pop());
		}
		
		// init existing tags
		$(self.wpTagChecklistSelector() + ' ' + self.wpTagChecklistItemSelector(), self.metaboxIab()).each(self.tagAddHandler);
		setTimeout(function () {
			$(self.wpTagChecklistSelector() + ' ' + self.wpTagChecklistItemSelector(), self.metaboxTags()).each(self.tagAddHandler);
		}, 100);
		
		//console.log('Inform.init() - done.');
	};
	
	// *****************************************************************************
	// GETTER/SETTERS
	
	self.articles = function (input) {
		return self.getSet('iArticles', input);
	};
	self.blogs = function (input) {
		return self.getSet('iBlogs', input);
	};
	self.btnProcess = function (input) {
		return self.getSet('oBtnProcess', input);
	};
	self.iabDelim = function (input) {
		return self.getSet('sIabDelim', input);
	};
	self.iabTags = function (input) {
		return self.getSet('aIabTags', input);
	};
	self.iabTagsOn = function (input) {
		return self.getSet('bIabTagsOn', input);
	};
	self.informDelim = function (input) {
		return self.getSet('sInformDelim', input);
	};
	self.informTags = function (input) {
		return self.getSet('aInformTags', input);
	};
	self.informTagsOn = function (input) {
		return self.getSet('bInformTagsOn', input);
	};
	self.inputTagsIab = function (input) {
		return self.getSet('oIabInput', input);
	};
	self.inputTagsInform = function (input) {
		return self.getSet('oInformInput', input);
	};
	self.inputTagsWp = function (input) {
		return self.getSet('oInputTagsWp', input);
	};
	self.metaboxIab = function (input) {
		return self.getSet('oMetaboxIab', input);
	};
	self.metaboxTags = function (input) {
		return self.getSet('oMetaboxTags', input);
	};
	self.pairDelim = function (input) {
		return self.getSet('sPairDelim', input);
	};
	self.required = function (input) {
		return self.getSet('bRequired', input);
	};
	self.searchPrefix = function (input) {
		return self.getSet('sSearchPrefix', input);
	};
	self.tagDelim = function (input) {
		return self.getSet('sTagDelim', input);
	};
	self.tagsIab = function (input) {
		return self.getSet('oWpIabs', input);
	};
	self.videos = function (input) {
		return self.getSet('iVideos', input);
	};
	self.wpAjaxProxy = function (input) {
		return self.getSet('sWpProxy', input);
	};
	self.wpMetaboxSelector = function (input) {
		return self.getSet('sWpMetaboxSelector', input);
	};
	self.wpMetaboxContentsSelector = function (input) {
		return self.getSet('sWpMetaboxContentsSelector', input);
	};
	self.wpTagChecklistSelector = function (input) {
		return self.getSet('sWpTagChecklistSelector', input);
	};
	self.wpTagChecklistItemSelector = function (input) {
		return self.getSet('sWpTagChecklistItemSelector', input);
	};
	self.wpTags = function (input) {
		return self.getSet('oWpTags', input);
	};
	self.wpUpdateTags = function (input) {
		return self.getSet('fWpUpdateTags', input);
	};
	
	// *****************************************************************************
	// GETTERS
	
	// post body content
	self.postContent = function () {
		return tinyMCE.activeEditor !== null && tinyMCE.activeEditor.isHidden() === false ? tinyMCE.get('content').getContent() : $('#content').val();
	};
	
	// post tags as array
	self.postTags = function () {
		
		var aTagsIn = self.inputTagsWp().val().split(','),
			aTags = [],
			i;
		
		for (i = 0; i < aTagsIn.length; i += 1) {
			if (aTagsIn[i].trim().length) {
				aTags.push(aTagsIn[i].trim());
			}
		}
		
		return aTags;
	};
	
	// post IAB tags as array
	self.postTagsIab = function () {
		
		var oTags = $(self.wpTagChecklistSelector() + ' ' + self.wpTagChecklistItemSelector(), self.metaboxIab()),
			aTagsClean = [];
		
		oTags.each(function () {
			
			var oTag = $(this).clone(),
				sLabel;
			
			// get tag
			oTag.find('a').remove();
			sLabel = oTag.text().trim();
			
			// add to array
			if (sLabel.length) {
				aTagsClean.push(sLabel);
			}
		});
		
		return aTagsClean;
	};
	
	// *****************************************************************************
	// TAG MANIPULATION
	
	// add tag to selected list
	self.tagAdd = function (oTag) {
		
		var bExists,
			bIsInformTag = self.tagIsInform(oTag),
			iScore,
			oParent,
			sTag = self.tagLabel(oTag),
			sTags = self.inputTagsWp().val();
		
		// Inform
		if (bIsInformTag) {
			
			// add if not already present
			self.inputTagsWp().val(sTags + ((',' + sTags + ',').indexOf(',' + sTag) < 0 ? ',' + sTag : ''));
			
			// update WP UI
			self.wpUpdateTags()();
			
			// add handlers
			$(self.wpTagChecklistSelector() + ' ' + self.wpTagChecklistItemSelector(), self.metaboxTags()).each(self.tagAddHandler);
			
			// update selection state of Inform tag list
			self.tagsUpdateState($('ul li', self.wpTags()), sTag, true);
			
		// IAB
		} else {
			
			// determine score
			iScore = Number(oTag.parent().find('.score').text());
			
			// determine if already added
			bExists = false;
			self.metaboxIab().find(self.wpTagChecklistSelector() + ' ' + self.wpTagChecklistItemSelector()).each(function () {
				var o = $(this).clone();
				o.find('a').remove();
				if (o.text().trim() === sTag) {
					bExists = true;
				}
			});
			
			// add tag to inputs
			if (!bExists) {
				oParent = $('<span><a class="ntdelbutton">X</a>&nbsp;' + sTag + '</span>').appendTo($(self.wpTagChecklistSelector(), self.metaboxIab()));
				
				// add handler
				oParent.each(self.tagAddHandler);
			}
			
			// update selection state of Inform tag list
			self.tagsUpdateState($('ul li', self.tagsIab()), sTag, true);
		}
	};
	
	// add handler and meta data (relevance scores) to tags
	self.tagAddHandler = function () {
		//console.log('Inform.tagAddHandler()');
		$(this).find('a').unbind().bind('click', function () {
			self.tagRemove($(this));
		});
	};
	
	// determine if tag is inform (true) or iab (false)
	self.tagIsInform = function (oTag) {
		return !oTag.parents(self.wpMetaboxContentsSelector()).is(self.metaboxIab());
	};
	
	// add handler to tag list items
	self.tagLabel = function (oTag) {
		
		var oParent,
			sTag;
		
		if (oTag.is(':input')) {
			sTag = oTag.val().trim();
		} else {
			oParent = oTag.parent().clone();
			oParent.find('a').remove();
			sTag = oParent.text().trim();
		}
		return sTag;
	};
	
	// remove tag from selected list
	self.tagRemove = function (oTag) {
		
		//console.log('Inform.tagRemove()');
		//console.log(oTag);
		
		var aTags,
			bIsInformTag = self.tagIsInform(oTag),
			i,
			sTag = self.tagLabel(oTag);
		
		// Inform
		if (bIsInformTag) {
			
			// remove from post tags
			aTags = self.postTags();
			for (i = 0; i < aTags.length; i += 1) {
				if (aTags[i].trim().toLowerCase() === sTag.toLowerCase()) {
					delete aTags[i];
				}
			}
			
			// update post tags
			self.inputTagsWp().val(aTags.join(','));
			self.wpUpdateTags()();
			
			// re-bind handlers
			$(self.wpTagChecklistSelector() + ' ' + self.wpTagChecklistItemSelector(), self.metaboxTags()).each(self.tagAddHandler);
			
			// update UI
			self.tagsUpdateState($('ul li', self.wpTags()), sTag);
			
			// remove from hidden input
			self.tagRemoveFromInput(self.inputTagsInform(), sTag);
			
		// IAB
		} else {
			
			// check supplied tags for matching label
			$(self.wpTagChecklistSelector(), self.metaboxIab()).find('span').each(function () {
				
				var oTag = $(this),
					oLabel = oTag.clone();
				
				// found; remove
				oLabel.find('a').remove();
				if (oLabel.text().trim().toLowerCase() === sTag.toLowerCase()) {
					oTag.remove();
				}
			});
			
			// update UI
			self.tagsUpdateState($('ul li', self.tagsIab()), sTag);
			
			// remove from hidden input
			self.tagRemoveFromInput(self.inputTagsIab(), sTag);
		}
	};
	
	// remove tag from hidden input
	self.tagRemoveFromInput = function (oInput, sTag) {
		
		var aPair,
			aTags = [],
			aTagsIn = oInput.val().split(self.tagDelim()),
			i;
		
		// loop through tags
		for (i = 0; i < aTagsIn.length; i += 1) {
			aPair = aTagsIn[i].split(self.pairDelim());
			
			// re-add if not specified
			if (aPair[0] !== sTag) {
				aTags.push(aTagsIn[i]);
			}
		}
		
		oInput.val(aTags.join(self.tagDelim()));
	};
	
	self.tagsRender = function () {
		
		var aTags = self.informTags() || [],
			aTagsExisting = self.postTags(),
			aTagsIab = self.iabTags() || [],
			aTagsIabExisting = self.postTagsIab(),
			bActive,
			i,
			oLi,
			oUl;
		
		// Inform
		self.wpTags().find('ul').remove();
		if (aTags.length) {
			self.wpTags().find('p.empty').remove();
			oUl = $('<ul class="tag_list"></ul>').appendTo(self.wpTags());
			for (i = 0; i < aTags.length; i += 1) {
				bActive = $.inArray(aTags[i].tag.toLowerCase(), aTagsExisting.toLowerCase()) >= 0;
				oLi = $('<li' + (bActive ? ' class="active"' : '') + '><input type="button" class="button" value="' + aTags[i].tag + '" /><span class="score">' + aTags[i].score + '</span></li>').appendTo(oUl);
				oLi.find(':input').bind('click', self.tagToggle);
				
				// turn on
				if (self.informTagsOn()) {
					self.tagAdd(oLi.find(':input'));
				}
			}
			
			// assign handlers to existing
			$(self.wpTagChecklistSelector() + ' ' + self.wpTagChecklistItemSelector(), self.metaboxTags()).each(self.tagAddHandler);
		}
		
		// IAB tags
		self.tagsIab().find('ul').remove();
		if (aTagsIab.length) {
			self.tagsIab().find('p.empty').remove();
			oUl = $('<ul class="tag_list"></ul>').appendTo(self.tagsIab());
			for (i = 0; i < aTagsIab.length; i += 1) {
				bActive = $.inArray(aTagsIab[i].tag.toLowerCase(), aTagsIabExisting.toLowerCase()) >= 0;
				oLi = $('<li' + (bActive ? ' class="active"' : '') + '><input type="button" class="button" value="' + aTagsIab[i].tag + '" /><span class="score">' + aTagsIab[i].score + '</span></li>').appendTo(oUl);
				oLi.find(':input').bind('click', self.tagToggle);
				
				// turn on
				if (self.iabTagsOn()) {
					self.tagAdd(oLi.find(':input'));
				}
			}
			
			// assign handlers to existing
			$(self.wpTagChecklistSelector() + ' ' + self.wpTagChecklistItemSelector(), self.metaboxIab()).each(self.tagAddHandler);
		}
		
		// update tags
		self.tagsUpdateInputs($('ul li', self.wpTags()));
		self.tagsUpdateInputs($('ul li', self.tagsIab()));
	};
	
	self.tagToggle = function () {
		
		var bIsInformTag,
			oTag = $(this),
			sTag = self.tagLabel(oTag);
		
		bIsInformTag = self.tagIsInform(oTag);
		
		// Inform
		if (bIsInformTag) {
			
			// add or remove
			if ($.inArray(sTag.toLowerCase(), self.postTags().toLowerCase()) >= 0) {
				self.tagRemove(oTag);
			} else {
				self.tagAdd(oTag);
				self.tagsUpdateInputs($('ul li', self.wpTags()));
			}
		
		// IAB
		} else {
			
			// add or remove
			if ($.inArray(sTag.toLowerCase(), self.postTagsIab().toLowerCase()) >= 0) {
				self.tagRemove(oTag);
			} else {
				self.tagAdd(oTag);
				self.tagsUpdateInputs($('ul li', self.tagsIab()));
			}
		}
	};
	
	// update hidden inputs
	self.tagsUpdateInputs = function (oTags) {
		
		//console.log('Inform.tagsUpdateInputs()');
		//console.log(oTags);
		
		var bIsInform = self.tagIsInform(oTags),
			oInput = bIsInform ? self.inputTagsInform() : self.inputTagsIab();
		
		// add selected to inputs
		oInput.val('');
		oTags.each(function () {
			
			var oTag = $(this),
				sValues = oInput.val();
			
			if (oTag.hasClass('active')) {
				oInput.val(sValues + (sValues.length > 0 ? self.tagDelim() : '') + oTag.find(':input').val() + self.pairDelim() + oTag.find('.score').text());
			}
		});
	};
	
	// set active state of Inform-supplied tags
	self.tagsUpdateState = function (oTags, sTag, bActive) {
		
		// update UI
		oTags.each(function () {
			
			var oTag = $(this);
			
			if (oTag.find(':input').val().toLowerCase() === sTag.toLowerCase()) {
				if (!!bActive) {
					oTag.addClass('active');
				} else {
					oTag.removeClass('active');
				}
			}
		});
	};
	
	// *****************************************************************************
	// REQUEST/RESPONSE HANDLING
	
	// send post content to Inform for processing
	self.process = function () {
		
		var sContent = self.postContent(),
			oData;
		
		if (!sContent.length) {
			return;
		}
		
		self.btnProcess().val('Processing article...');
		
		oData = {
			inform_action: 'extract',
			content: sContent,
			searchPrefix: self.searchPrefix(),
			// options //
			username: '',
			password: '',
			num_of_articles: self.articles(),
			num_of_blogs: self.blogs(),
			num_of_videos: self.videos()
		};
		
		$.post(ajaxurl + '?action=' + self.wpAjaxProxy(), oData, function (sResponse) {
			self.parseResponse(sResponse);
			self.btnProcess().val('Get tags').after('<input type="hidden" name="inform_processed" value="1" />');
		});
	};
	
	// processing is required, check for input
	self.publish = function (e) {
		if (!$('#inform_metabox :input[name = "inform_processed"]').length) {
			alert('Please process your post with Inform before saving.');
			e.stopImmediatePropagation();
			e.preventDefault();
			return false;
		}
		return true;
	};
	
	// extract Inform/IAB tag arrays from response
	self.parseResponse = function (sResponse) {
		
		var aPair,
			aTagPairs,
			aTags,
			i,
			sInformDelim = self.informDelim(),
			sIabDelim = self.iabDelim(),
			sPairDelim = self.pairDelim(),
			sTagDelim = self.tagDelim();
		
		// no results
		if (sResponse.indexOf(sInformDelim) < 0 && sResponse.indexOf(sIabDelim) < 0) {
			return false;
		}
		
		// Inform tags
		if (sResponse.indexOf(sInformDelim) >= 0) {
			aTagPairs = sResponse.substring(sResponse.indexOf(sInformDelim) + sInformDelim.length + sTagDelim.length, sResponse.indexOf(sIabDelim) >= 0 ? sResponse.indexOf(sIabDelim) - 1 : sResponse.length).split(sTagDelim);
			aTags = [];
			for (i = 0; i < aTagPairs.length; i += 1) {
				if (aTagPairs[i].indexOf(sPairDelim) > 0) {
					aPair = aTagPairs[i].trim().split(sPairDelim);
					aTags.push({tag: aPair[0].trim(), score: aPair[1]});
				}
			}
			self.informTags(aTags);
		}
		
		// IAB tags
		if (sResponse.indexOf(sIabDelim) >= 0) {
			aTagPairs = sResponse.substring(sResponse.indexOf(sIabDelim) + sIabDelim.length + sTagDelim.length).split(sTagDelim);
			aTags = [];
			for (i = 0; i < aTagPairs.length; i += 1) {
				if (aTagPairs[i].indexOf(sPairDelim) > 0) {
					aPair = aTagPairs[i].trim().split(sPairDelim);
					aTags.push({tag: aPair[0].trim(), score: aPair[1]});
				}
			}
			self.iabTags(aTags);
		}
		
		self.tagsRender();
	};
	
	// *****************************************************************************
	// UTILS
	
	// getter/setter
	self.getSet = function (sKey, input) {
		if (typeof input !== 'undefined') {
			oData[sKey] = input;
		}
		return oData[sKey];
	};
};

// *****************************************************************************
// EXTEND

// make array values lowercase
Array.prototype.toLowerCase = function () {
	
	'use strict';
	
	var aLowered = this,
		i;
	
	for (i in this) {
		if (this.hasOwnProperty(i) && typeof this[i].toLowerCase === 'function') {
			aLowered[i] = this[i].toLowerCase();
		}
	}
	
	return aLowered;
};

// remove leading/trailing whitespace
String.prototype.trim = function () {
	'use strict';
	return this.replace(/^\s*([\s\S]*?)\s*$/, '$1');
};
