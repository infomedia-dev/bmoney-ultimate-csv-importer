<<<<<<< HEAD
<h3>Step 1: Upload your CSV</h3>
<form id="bmuci-upload" method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['REQUEST_URI']; ?>" >
	
	<p>Select your formatted CSV. The first row should contain column headings. Each heading must be uniquely named for this to work properly. </p>
	<p>For custom post types, it works best if you already have at least 1 sample post already inserted into the database.</p>
	
	
	<!--TODO: Uploadify-->

	<input type="file" name="csv" />
	
	<?php if($has_previous){ ?>
		<p><label><input type="checkbox" name="bmuci_last_import" value="1"<?php if($this->last_import) echo ' checked="checked"'; ?> /> Use configuration from last import.</label></p>
	<?php } ?>
	
	<p class="import-type">Type of Import: &nbsp; 
		<label>
			<input type="radio" name="bmuci_import_type" value="posts"<?php if($this->import_type == 'posts') echo ' checked="checked"'; ?> />
			Post
		</label> &nbsp;
		<label>
			<input type="radio" name="bmuci_import_type" value="users"<?php if($this->import_type == 'users') echo ' checked="checked"'; ?> />
			User
		</label> &nbsp;
		<label class="disabled">
			<input disabled="disabled" type="radio" name="bmuci_import_type" value="terms"<?php if($this->import_type == 'terms') echo ' checked="checked"'; ?> />
			Taxonomy/Terms (coming soon)
		</label>
		<label>
			<input type="radio" name="bmuci_import_type" value="other"<?php if($this->import_type == 'other') echo ' checked="checked"'; ?> />
			Other (use this option for custom import scripts using hooks)
		</label>
	</p>
	
	<fieldset class="import-options posts-options">
		<legend>Post Defaults</legend>
		
		<label class="block">
			<span class="label">Select Post Type to Import</span>
			<span class="field"><select name="bmuci_post_type">
				<?php $found = false;
				foreach(get_post_types(array(), 'objects') as $post_type){ ?>
					<option value="<?php echo $post_type->name; ?>"<?php if($post_type->name == $this->defaults['post_type']){
						echo ' selected="selected"';
						$found = true;
					} ?>><?php echo $post_type->labels->singular_name; ?></option>
				<?php }
				if(!$found && $this->defaults['post_type']){ ?>
					<option value="<?php echo $this->defaults['post_type']; ?>" selected="selected"><?php echo $this->defaults['post_type']; ?></option>
				<?php } ?>
				<option value="__custom__">Enter a custom post type</option>
			</select></span>
		</label>
		
		<label class="block alt-cpt-field">
			<span class="label">Custom Post Type:</span>
			<span class="field"><input type="text" name="bmuci_custom_post_type" /></span>
		</label>
		
		<label class="block">
			<span class="label">Import Post Status as</span>
			<span class="field"><select name="bmuci_post_status">
				<option value="draft"<?php if($this->defaults['post_status'] == 'draft') echo ' selected="selected"'; ?>>Draft</option>
				<option value="publish"<?php if($this->defaults['post_status']  == 'publish') echo ' selected="selected"'; ?>>Publish</option>
				<option value="pending"<?php if($this->defaults['post_status']  == 'pending') echo ' selected="selected"'; ?>>Pending</option>
				<option value="future"<?php if($this->defaults['post_status'] == 'future') echo ' selected="selected"'; ?>>Future</option>
				<option value="private"<?php if($this->defaults['post_status'] == 'private') echo ' selected="selected"'; ?>>Private</option>
			</select></span>
		</label>
		
		<label class="block">
			<span class="label">Comment Status</span>
			<span class="field"><select name="bmuci_comment_status">
				<option value="open"<?php if($this->defaults['comment_status'] == 'open') echo ' selected="selected"'; ?>>Open</option>
				<option value="closed"<?php if($this->defaults['comment_status'] == 'closed') echo ' selected="selected"'; ?>>Closed</option>
			</select></span>
		</label>
		
		<label class="block">
			<span class="label">Ping Status</span>
			<span class="field"><select name="bmuci_ping_status">
				<option value="open"<?php if($this->defaults['ping_status'] == 'open') echo ' selected="selected"'; ?>>Open</option>
				<option value="closed"<?php if($this->defaults['ping_status'] == 'closed') echo ' selected="selected"'; ?>>Closed</option>
			</select></span>
		</label>
		
		<label class="block">
			<span class="label">Author</span>
			<span class="field"><select name="bmuci_post_author">
				<?php foreach(get_users(array('who' => 'authors')) as $user){ ?>
					<option value="<?php echo $user->ID; ?>"<?php if($this->defaults['post_author'] == $user->ID) echo ' selected="selected"'; ?>><?php echo $user->user_login; ?></option>
				<?php } ?>
			</select></span>
		</label>
	</fieldset>
	
	<fieldset class="import-options users-options">
		<legend>User Defaults</legend>
		
		<label class="block">
			<span class="label">Default User Role</span>
			<span class="field"><select name="bmuci_user_role">
				<?php global $wp_roles;
				foreach($wp_roles->role_objects as $role){ ?>
					<option value="<?php echo $role->name; ?>"<?php if($this->defaults['user_role'] == $role->name) echo ' selected="selected"'; ?>><?php echo $role->name; ?></option>
				<?php } ?>
			</select></span>
		</label>
	</fieldset>

	<div class="buttons">
		<input type="hidden" name="bmuci_process" value="upload" />
		<?php submit_button(__('Upload', 'bmuci')); ?>
	</div>
</form>
=======
<h3>Step 1: Upload your CSV</h3><form id="bmuci-upload" method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['REQUEST_URI']; ?>" >		<p>Select your formatted CSV. The first row should contain column headings. Each heading must be uniquely named for this to work properly. </p>	<p>For custom post types, it works best if you already have at least 1 sample post already inserted into the database.</p>			<!--TODO: Uploadify-->	<input type="file" name="csv" />		<?php if($has_previous){ ?>		<p><label><input type="checkbox" name="bmuci_last_import" value="1"<?php if($this->last_import) echo ' checked="checked"'; ?> /> Use configuration from last import.</label></p>	<?php } ?>		<p class="import-type">Type of Import: &nbsp; 		<label>			<input type="radio" name="bmuci_import_type" value="posts"<?php if($this->import_type == 'posts') echo ' checked="checked"'; ?> />			Post		</label> &nbsp;		<label>			<input type="radio" name="bmuci_import_type" value="users"<?php if($this->import_type == 'users') echo ' checked="checked"'; ?> />			User		</label> &nbsp;		<label class="disabled">			<input disabled="disabled" type="radio" name="bmuci_import_type" value="terms"<?php if($this->import_type == 'terms') echo ' checked="checked"'; ?> />			Taxonomy/Terms (coming soon)		</label>		<label>			<input type="radio" name="bmuci_import_type" value="other"<?php if($this->import_type == 'other') echo ' checked="checked"'; ?> />			Other (use this option for custom import scripts using hooks)		</label>	</p>		<fieldset class="import-options posts-options">		<legend>Post Defaults</legend>				<label class="block">			<span class="label">Select Post Type to Import</span>			<span class="field"><select name="bmuci_post_type">				<?php $found = false;				foreach(get_post_types(array(), 'objects') as $post_type){ ?>					<option value="<?php echo $post_type->name; ?>"<?php if($post_type->name == $this->defaults['post_type']){						echo ' selected="selected"';						$found = true;					} ?>><?php echo $post_type->labels->singular_name; ?></option>				<?php }				if(!$found && $this->defaults['post_type']){ ?>					<option value="<?php echo $this->defaults['post_type']; ?>" selected="selected"><?php echo $this->defaults['post_type']; ?></option>				<?php } ?>				<option value="__custom__">Enter a custom post type</option>			</select></span>		</label>				<label class="block alt-cpt-field">			<span class="label">Custom Post Type:</span>			<span class="field"><input type="text" name="bmuci_custom_post_type" /></span>		</label>				<label class="block">			<span class="label">Import Post Status as</span>			<span class="field"><select name="bmuci_post_status">				<option value="draft"<?php if($this->defaults['post_status'] == 'draft') echo ' selected="selected"'; ?>>Draft</option>				<option value="publish"<?php if($this->defaults['post_status']  == 'publish') echo ' selected="selected"'; ?>>Publish</option>				<option value="pending"<?php if($this->defaults['post_status']  == 'pending') echo ' selected="selected"'; ?>>Pending</option>				<option value="future"<?php if($this->defaults['post_status'] == 'future') echo ' selected="selected"'; ?>>Future</option>				<option value="private"<?php if($this->defaults['post_status'] == 'private') echo ' selected="selected"'; ?>>Private</option>			</select></span>		</label>				<label class="block">			<span class="label">Comment Status</span>			<span class="field"><select name="bmuci_comment_status">				<option value="open"<?php if($this->defaults['comment_status'] == 'open') echo ' selected="selected"'; ?>>Open</option>				<option value="closed"<?php if($this->defaults['comment_status'] == 'closed') echo ' selected="selected"'; ?>>Closed</option>			</select></span>		</label>				<label class="block">			<span class="label">Ping Status</span>			<span class="field"><select name="bmuci_ping_status">				<option value="open"<?php if($this->defaults['ping_status'] == 'open') echo ' selected="selected"'; ?>>Open</option>				<option value="closed"<?php if($this->defaults['ping_status'] == 'closed') echo ' selected="selected"'; ?>>Closed</option>			</select></span>		</label>				<label class="block">			<span class="label">Author</span>			<span class="field"><select name="bmuci_post_author">				<?php foreach(get_users(array('who' => 'authors')) as $user){ ?>					<option value="<?php echo $user->ID; ?>"<?php if($this->defaults['post_author'] == $user->ID) echo ' selected="selected"'; ?>><?php echo $user->user_login; ?></option>				<?php } ?>			</select></span>		</label>	</fieldset>		<fieldset class="import-options users-options">		<legend>User Defaults</legend>				<label class="block">			<span class="label">Default User Role</span>			<span class="field"><select name="bmuci_user_role">				<?php global $wp_roles;				foreach($wp_roles->role_objects as $role){ ?>					<option value="<?php echo $role->name; ?>"<?php if($this->defaults['user_role'] == $role->name) echo ' selected="selected"'; ?>><?php echo $role->name; ?></option>				<?php } ?>			</select></span>		</label>	</fieldset>	<div class="buttons">		<input type="hidden" name="bmuci_process" value="upload" />		<?php submit_button(__('Upload', 'bmuci')); ?>	</div></form>
>>>>>>> 51ba784e3cef9bcb0ef6f3292d1d86c6e935fbe8
