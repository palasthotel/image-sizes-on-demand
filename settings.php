<?php
/**
 * @var $disable_generating_custom_image_sizes boolean
 */
?>
<h1>Image Sizes On Demand</h1>

<form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>?page=<?php echo $this->namespace?>">

	<table class="form-table">
		<tr>
			<td>
				<label>
					<input type="checkbox"
					       name="<?php echo $this->disable_generating_custom_image_sizes_key; ?>"
					       value="1"
					<?php echo ($disable_generating_custom_image_sizes)?"checked":""; ?>>
					Disable automatic generation of image sizes
				</label>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>

</form>
