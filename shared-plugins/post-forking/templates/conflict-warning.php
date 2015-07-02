<div class="error" id="conflict-warning">
	<p>
		<?php
			printf(
				__( 'This fork cannot be merged with its parent post. Please resolve the conflict below before attempting to publish again, or use the <a href="%s">interactive merge tool.</a>', 'post-forking' ),
				admin_url( 'revision.php?page=fork-diff&right=' . intval( $_GET['post'] ) )
			); 
		?>
	</p>
</div>
