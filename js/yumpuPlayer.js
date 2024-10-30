/**
 * Yumpu-Player Script
 * @author Roberto Cornice <cornice@lemm.de> * 
 * @returns {yumpuPlayer}
 */
function yumpuPlayer()	{
	/* global yumpuepaperlister_bob */
	var me = this;
	
	/**
	 * fullscreen-container-id
	 */
	this.fsId;
	
	/**
	 * Initializing by creating display containers and binding the onclicks
	 */
	this.init	= function(){
		me.fsId	= "yumpuPlayerOverlay_"+jQuery.now();
		me.createNearlyFullScreenContainer();
		jQuery(".yumpuPlayerDocs a").on("click", function(event)	{
			event.preventDefault();
			me.playYumpu( jQuery(this) );
		});
	};
	
	/**
	 * "Fullscreen"-Container und Close-Button erzeugen
	 * @returns {undefined}
	 */
	this.createNearlyFullScreenContainer	= function()	{
		var fsContainerContainer	= jQuery("<div></div>");
		jQuery(fsContainerContainer).attr("id", me.fsId+"Container").css({
			position	: "fixed",
			top			: "0",
			left		: "0",
			width		: "100%",
			height		: "100%",
			margin		: "0",
			padding		: "0 0 0 0",
			opacity		: 1,
			background	: "#000000",
			zIndex		: 20000,
			display		: "none"
		});
		jQuery("body").append(jQuery(fsContainerContainer));
		var fsContainer	= jQuery("<div></div>");
		jQuery(fsContainer).attr("id",  me.fsId+"Document").css({
			position	: "relative",
			top			: "0",
			left		: "0",
			width		: "100%",
			height		: "calc( 100% - 50px )",
			margin		: "50px 0 0 0",
			padding		: "0 0 0 0",
			opacity		: 1,
			background	: "#FFFFFF"
		});
		jQuery("#"+me.fsId+"Container").append(jQuery(fsContainer));

		jQuery("body").append(
			'<button class="closeFSYumpuPlayer"><i class="far fa-times-circle"></i></button>'
		);
		jQuery(".closeFSYumpuPlayer").css({
			position		: "fixed",
			right			: "20px",
			top				: "0px",
			zIndex			: 20001,
			width			: "50px",
			height			: "50px",
			fontSize		: "46px",
			display			: "none",
			padding			: "0",
			margin			: "0",
			borderRadius	: "50%",
			border			: "0",
			color			: "#303030",
			lineHeight		: "0"
		});
	};
	
	/**
	 * Yumpu-Prospekt "abspielen"
	 * @param {jQuery} obj
	 */
	this.playYumpu	= function( obj )	{
		var docId		= jQuery(obj).attr("data-id");
		var playerId	= me.fsId+"Document";
		jQuery("#"+me.fsId+"Container > div").html("");
		jQuery("#"+me.fsId+"Container").show().fadeTo(250,1);
		jQuery(".closeFSYumpuPlayer").attr("data-id", playerId).fadeTo(1,1).show().on("click", function()	{
			var closePlayerId	= me.fsId+"-"+jQuery(this).attr("data-id");;
			jQuery(".closeFSYumpuPlayer").fadeTo(250,0, function()	{
				jQuery(this).hide();
			});
			jQuery("#"+me.fsId+"Container").fadeTo(250,0, function()	{
				jQuery(this).hide();
				jQuery("#"+closePlayerId).html("");
			});
		});
		jQuery.ajax({
			url			: yumpuepaperlister_bob.ajaxurl,
			type		: "POST",
			data		: {
				action		: "yumpuEpaperListerAjax",
				documentid	: docId,
				mode		: "getEmbedUrl"
			},
			success		: function(data)	{
				if( data.success )	{
					jQuery("#"+playerId).html(
						'<iframe width="100%" height="100%" src="'+data.url+'" frameborder="0" allowfullscreen="false" allowtransparency="true"></iframe>'
					);
				} else {
					jQuery(".closeFSYumpuPlayer").trigger("click");
				}
			},
			error	: function()	{
				jQuery(".closeFSYumpuPlayer").trigger("click");
			}
		});
		
	};
	me.init();
}

/**
 * Autostart
 */
var yumpuPlayer;
jQuery(document).on("ready", function () {
	yumpuPlayer = new yumpuPlayer();
});