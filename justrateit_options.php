<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?=_e("JustRateIt Settings", "justrateit")?></h2>

	<form method="post" action="options.php"> 

		<?php settings_fields('justrateit_settings_group');?>
		<?php do_settings_fields('justrateit_settings_group');?>
		<table>
			<tr valign="top">
				<th scope="row"><?=_e("Max votes per user", "justrateit")?></th>
				<td><input type="text" name="max_per_user" value="<?php echo get_option('max_per_user'); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row"><?=_e("Max votes per ip", "justrateit")?></th>
				<td><input type="text" name="max_per_ip" value="<?php echo get_option('max_per_ip'); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row"><?=_e("Allow anonymous votes ", "justrateit")?></th>
				<td>
					<select name="allow_anonymous_votes">
						<option value="1" <?php echo get_option("allow_anonymous_votes") == 1 ? "selected=selected" : ""?>><?=_e("Yes", "justrateit")?></option>
						<option value="0" <?php echo get_option("allow_anonymous_votes") == 0 ? "selected=selected" : ""?>><?=_e("No", "justrateit")?></option>
					</select>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"></th>
				<td><?php submit_button(); ?></td>
			</tr>
		</table>
	</form>
</div>