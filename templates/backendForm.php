<style>
	#yumpu_epaper_lister_form select	{
		width	: 200px;
	}
	#yumpu_epaper_lister_form label.checkboxlabel	{
		line-height: 2em;
		font-size: 1.5em;
	}
	#yumpu_epaper_lister_form #token	{
		width	: 350px;
	}
</style>
<h1>Magazine Lister for Yumpu - <?php echo __("Settings", "magazine-lister-for-yumpu") ?></h1>

<form method="POST" id="yumpu_epaper_lister_form">
    <label for="token">API-Token</label>
    <input type="text" name="token" id="token" value="<?php echo $this->token; ?>">
	
	<br />
    <label for="showtitle" class="checkboxlabel">
    <input type="checkbox" name="showtitle" id="showtitle" value="1" <?php echo $this->checked(1,$this->showtitle, false, 1); ?> />
	<?php echo __("show title", "magazine-lister-for-yumpu") ?></label>
	
	<br />
    <label for="showdate" class="checkboxlabel">
    <input type="checkbox" name="showdate" id="showdate" value="1" <?php echo $this->checked(1,$this->showdate, false, 1); ?>>
	<?php echo __("show validity date", "magazine-lister-for-yumpu") ?></label>
	
	<br />
    <label for="linktext" class="label">
	<?php echo __("set link text", "magazine-lister-for-yumpu") ?></label>
    <input type="text" name="linktext" id="linktext" value="<?php echo $this->linktext; ?>">
	
	
	<?php
	
	if( empty(trim($this->token)) )	{
		echo __("Create shortcodes after entering a valid Yumpu.com API-Key", "magazine-lister-for-yumpu");
	} elseif( !empty($this->token) && !$this->tokenAccepted )	{ 
		echo __("Collections couldn't get retrieved: ", "magazine-lister-for-yumpu").$errMsg;
	} else {
		echo '
			<hr />
			<input type="submit" value="' . __("add Shortcode", "magazine-lister-for-yumpu") . '" name="add_collection" class="button button-primary button-large">
		';
		if( is_array( $this->yumpu_collections ) )	{
			foreach( $this->yumpu_collections AS $id=>$collection )	{
				
				$collectionOptions	= "";
				if( is_array( $this->collections ) )	{
					foreach( $this->collections AS $colls )	{
						$selected	= "";
						if( $colls->id === $collection->collection_id )	{
							$selected	= "selected='seleced'";
							
							if( isset( $colls->sections[$collection->section_id]))	{
							$section_data_id	= $colls->sections[$collection->section_id]->id;
							$section_data_name	= $colls->sections[$collection->section_id]->name;
							}
							
						}
						$collectionOptions	.= "<option ".$selected." value='".$colls->id."'>".$colls->name."</option>";
					}
				}
				
				
				
				echo '
				<fieldset class="collection_fieldset">
					<input type="hidden" name="id['.$id.']" value="'.$id.'" />
					<label for="collection_id">'.__("choose a collection", "magazine-lister-for-yumpu").'</label>
					<select class="collection_selecter" name="collection_id['.$id.']"><option>'.__("Choose a collection.", "magazine-lister-for-yumpu").'</option>'.$collectionOptions.'</select>
					<label for="collection_id">'.__("choose a section", "magazine-lister-for-yumpu").'</label>
					<select class="selection_selecter" name="section_id['.$id.']" data-id="'.$section_data_id.'" data-name="'.$section_data_name.'"></select>
					<input class="yel_shortcode_field" type="text" readonly="readonly" name="shortcode['.$id.']" value="'. htmlspecialchars("[yumpulister id=\"".$id."\"]").'" />
					<button type="submit" name="collection_del" value="'.$id.'">'.__("remove shortcode", "magazine-lister-for-yumpu").'</button>
				</fieldset>
				';
			}
		}
	}
	?>
	<hr />
	
    <input type="submit" name="saveYumpuSettings" value="<?php echo __("save changes", "magazine-lister-for-yumpu"); ?>" class="button button-primary button-large">
</form>
<script>
	jQuery(document).on("ready", function()	{
		epaperlister_backend	= new epaperlister_backend(<?php echo json_encode($this->collections); ?>);
	});
</script>