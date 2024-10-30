function epaperlister_backend( collections )	{
	var me	= this;
	
	this.collections	= collections;
	
	this.shortCodeMarkAllOnClick	= function()	{
		jQuery(".yel_shortcode_field").on("click", function()	{
			 jQuery(this).select();
		});
	};
	
	this.setSections	= function()	{
		jQuery("#yumpu_epaper_lister_form fieldset.collection_fieldset").each(function()	{
			me.buildSection(this);
		});
	};
	
	this.buildSection	= function( obj )	{
		var collection_id;
		collection_id	= jQuery(obj).find(".collection_selecter").val();
		if( typeof me.collections[collection_id] !== "undefined" && typeof me.collections[collection_id].sections !== "undefined" )	{
			jQuery(obj).find(".selection_selecter").html("");
			for( var i in me.collections[collection_id].sections )	{
				jQuery(obj).find(".selection_selecter").append("<option value='"+me.collections[collection_id].sections[i].id+"'>"+me.collections[collection_id].sections[i].name+"</option>");
			}
			jQuery(obj).find(".selection_selecter").val(jQuery(obj).find(".selection_selecter").attr("data-id"));
		} else {
			jQuery(obj).find(".selection_selecter").html("<option>zuerst Sammlung auswählen</option>");
		}
	};
	
	this.onCollectionChange	= function()	{
		jQuery("#yumpu_epaper_lister_form fieldset.collection_fieldset .collection_selecter").on("change", function()	{
			me.buildSection(jQuery(this).closest("fieldset"));
		});
	};
	
	this.onSelectionChange	= function()	{
		jQuery("#yumpu_epaper_lister_form fieldset.collection_fieldset .selection_selecter").on("change", function()	{
			jQuery(this).attr("data-id", jQuery(this).val()).attr("data-name", jQuery(this).find(":selected").text() );
		});
	};
	
	this.init	= function()	{
		me.shortCodeMarkAllOnClick();
		me.setSections();
		me.onCollectionChange();
		me.onSelectionChange();
	};
	
	
	me.init();
}

var epaperlister_backend;

