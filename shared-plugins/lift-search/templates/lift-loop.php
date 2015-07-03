<div class="lift-loop search-results">
	<?php
	if ( have_posts() ):
		while ( have_posts() ) : the_post();
			?>
			<div class="search-result type-photo">
				<h2>
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h2>
				<?php if ( has_post_thumbnail() ): ?>
					<div class="search-thumb">
						<a href="<?php the_permalink(); ?>" class="post-thumb">
							<?php the_post_thumbnail( 'thumbnail' ); ?>
						</a>
						<i></i>
					</div>
				<?php endif; ?>
				<?php the_excerpt(); ?>
				<p class="search-date">
					<?php the_time( ) ?>  - <a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) )  ?>">By <?php the_author(); ?></a>
				</p>
				<div class="clr"></div>
			</div> <!-- end search-result -->
		<?php endwhile;
		?>
		<div class="search-paging">
			<?php
			global $wp_query;
			$big = 9999999999;
			echo paginate_links( array(
				'base' => str_replace( $big, '%#%', get_pagenum_link( $big ) ),
				'current' => max( 1, get_query_var( 'paged' ) ),
				'total' => $wp_query->max_num_pages,
				'prev_text' => 'Previous',
				'next_text' => 'Next',
			) );
			?>
		</div> <!-- end .pagination -->
	<?php else: ?>
		<div class="search-result">
			<p>No Search Results</p>
		</div>
	<?php endif; ?>
</div> <!-- end search-results -->