/*
 * dependencies: jquery, backbonejs, underscorejs, window.sliMpd.router, window.sliMpd.modules.AbstractView
 */
(function() {
    "use strict";
    
    var $ = window.jQuery,
        _ = window._;
    window.sliMpd = $.extend(true, window.sliMpd, {
        modules : {}
    });
    window.sliMpd.modules.XwaxView = window.sliMpd.modules.AbstractView.extend({

        rendered : false,
        tabAutocomplete : false,
        
        totalDecks : 3, // TODO: get available decks from server
        
        xwaxRunning : false,
        
        visible : false,
        
        deckViews : [],
        
        lastDeckTracks : [],
        
        lastTimecodes : [],
        
        toggler : false,
        
        showWaveform : true,
        
        intervalActive : 3000,
		intervalInactive : 6000,

		notrunningTolerance : 2,
		notrunningCounter : 0,
        
        initialize : function(options) {
        	this.showWaveform = options.showWaveform;
            window.sliMpd.modules.AbstractView.prototype.initialize.call(this, options);
        },

        render : function() {
        	// only render page once (to prevent multiple click listeners)
            if (this.rendered) {
                return;
            }
            //console.log('calling XwaxGui::render()');
            this.toggler = $('.xwax-gui-toggler');
            this.toggler.off('click', this.toggleXwaxGui).on('click', this.toggleXwaxGui);
            window.sliMpd.modules.AbstractView.prototype.render.call(this);
            this.rendered = true;
		},
		
		toggleXwaxGui : function() {
			if(this.visible === false) {
				this.showXwaxGui();
			} else {
				this.hideXwaxGui();
			}
		},
		
		showXwaxGui : function() {
			for(var i=0; i< this.totalDecks; i++) {
				var deckView = new window.sliMpd.modules.XwaxPlayer({
			    	el : '.xwax-deck-'+i,
			    	deckIndex : i,
			    	showWaveform : this.showWaveform
			    });
        		this.deckViews.push(deckView); 
			    if(this.xwaxRunning === true) {
			    	deckView.redraw();
			    }
			    this.visible = true;
			}
			$('body').addClass('slimplayer xwax-enabled');
			$('.xwax-error').removeClass('hidden');
			this.toggler.removeClass('btn-default').addClass('btn-success');
			this.poll();
		},
		
		hideXwaxGui : function() {
		    this.lastDeckTracks = [];
		    this.lastTimecodes = [];
		    clearTimeout(this.poller);
		    //console.log('hideXwaxGui()');
		    this.deckViews.forEach(function (view){
		    	//console.log('destroying view ' + view.deckIndex);
	    		view.close();
	    		
		    });
		    $('body').removeClass('slimplayer xwax-enabled');
		    this.toggler.removeClass('btn-success').removeClass('btn-danger').addClass('btn-default');
		    this.xwaxRunning = false;
		    this.deckViews = [];
		    this.visible = false;
		},
		
		processXwaxNotRunning : function() {
			//console.log('processXwaxNotRunning()');

			// sometimes we have connection errors with xwax's socket
			// instead of disabling xwax stuff immediatly wait for x more poll request
			this.notrunningCounter++;
			if(this.notrunningCounter < this.notrunningTolerance) {
				clearTimeout(this.poller);
				this.poller = setTimeout(this.poll, this.intervalActive);
				return;
			}

			this.toggler.removeClass('btn-success').addClass('btn-danger');
			this.xwaxRunning = false;
		    this.deckViews.forEach(function (deckView){
	    		deckView.$el.addClass('no-connection');
		    });
		    clearTimeout(this.poller);
		    this.poller = setTimeout(this.poll, this.intervalInactive);
		},
		
		// IMPORTANT TODO: how to avoid growing memory consumption on those frequent poll-requests?
		poll : function (){
			if(this.visible === false) {
				return;
			}
			var that = this;
		    $.get('/xwaxstatus', function(data) {
		    	
		    	try {
		        	var xwaxStatus = JSON.parse(data);
		        	if (typeof xwaxStatus.notify !== 'undefined') {
		        		that.processXwaxNotRunning();
		        		return;
		        	}
			    } catch(e) {
			    	that.processXwaxNotRunning();
		        	return;
				}
				that.notrunningCounter = 0;
				if(that.xwaxRunning === false) {
					that.toggler.removeClass('btn-danger').addClass('btn-success');
					that.deckViews.forEach(function (deckView){
						deckView.$el.removeClass('no-connection');
				    });
				}
				
				that.xwaxRunning = true;
				/*
		    	console.log('pitch ' + xwaxStatus[0].pitch);
		    	console.log('player_diff ' + xwaxStatus[0].player_diff);
		    	console.log('player_sync_pitch ' + xwaxStatus[0].player_sync_pitch);
		    	console.log('player_target_position ' + xwaxStatus[0].player_target_position);
		    	console.log('timecode_control ' + xwaxStatus[0].timecode_control);
		    	console.log('timecode_speed ' + xwaxStatus[0].timecode_speed);
		    	console.log('-----------------------------');
		    	*/
		    	
		    	for(var i=0; i< that.totalDecks; i++) {
		    		
		    		
		    		that.deckViews[i].nowPlayingPercent = xwaxStatus[i].percent;
		    		that.deckViews[i].nowPlayingState = xwaxStatus[i].state;
		    		
		    		try {
		    			that.deckViews[i].nowPlayingDuration = xwaxStatus[i].item.miliseconds/1000;
		    		} catch(e) {
		    			that.deckViews[i].nowPlayingDuration = xwaxStatus[i].length;
		    		}
		    		if(xwaxStatus[i].length > that.deckViews[i].nowPlayingDuration) {
		    			that.deckViews[i].nowPlayingDuration = xwaxStatus[i].length;
		    			that.deckViews[i].onRedrawComplete();
		    		}
		    		that.deckViews[i].nowPlayingDuration = xwaxStatus[i].length;
		    		
		    		that.deckViews[i].nowPlayingElapsed = xwaxStatus[i].position;
			    	
			    	if(this.showWaveform == true) {
			    		that.deckViews[i].timelineSetValue(xwaxStatus[i].percent);
			    	}
			    	that.deckViews[i].updateStateIcons();
			    	if(that.lastDeckTracks[i] !== xwaxStatus[i].path) {
		    			that.lastDeckTracks[i] = xwaxStatus[i].path;
		    			that.deckViews[i].nowPlayingItem = xwaxStatus[i].path;
		    			var hash = (xwaxStatus[i].item === null) ? '0000000' : xwaxStatus[i].item.relativePathHash;
		    			that.deckViews[i].redraw({hash: hash});
		    			//console.log('redraw deck ' + i);
		    		}
		    		if(that.lastTimecodes[i] !== xwaxStatus[i].timecode) {	
		    			that.lastTimecodes[i] = xwaxStatus[i].timecode;
		    			that.deckViews[i].updateTimecode(xwaxStatus[i].timecode);
		    		}
		    		
		    		that.deckViews[i].nowPlayingItem = xwaxStatus[i].path;
			    	
			    	//console.log(xwaxStatus);
		    		//that.deckViews[i].onRedrawComplete();
		    	}
		        that.poller = setTimeout(that.poll, that.intervalActive);
		    });
		}

    });
    
})();
