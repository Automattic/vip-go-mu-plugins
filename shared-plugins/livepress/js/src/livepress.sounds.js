/*jslint vars:true */
/*global LivepressConfig, Livepress, soundManager, console */
Livepress.sounds = (function () {
	var soundsBasePath = LivepressConfig.lp_plugin_url + "sounds/";
	var soundOn = ( 1 == LivepressConfig.sounds_default );
	var sounds = {};

	// Sound files
	var vibeslr = 'vibes_04-04LR_02-01.mp3';
	var vibesshort = 'vibes-short_09-08.mp3';
	var piano16 = 'piano_w-pad_01-16M_01-01.mp3';
	var piano17 = 'piano_w-pad_01-17M_01.mp3';

	createjs.Sound.alternateExtensions = ["mp3"];
	createjs.Sound.registerSound(soundsBasePath + vibeslr, "commentAdded");
	createjs.Sound.registerSound(soundsBasePath + vibeslr, "firstComment");
	createjs.Sound.registerSound(soundsBasePath + vibesshort, "commentReplyToUserReceived");
	createjs.Sound.registerSound(soundsBasePath + vibeslr, "commented");
	createjs.Sound.registerSound(soundsBasePath + piano17, "newPost");
	createjs.Sound.registerSound(soundsBasePath + piano16, "postUpdated");

	sounds.on = function () {
		soundOn = true;
	};

	sounds.off = function () {
		soundOn = false;
	};

	sounds.play = function(sound){
		if ( soundOn ){
			createjs.Sound.play(sound);
		}
	};

	return sounds;
}());
