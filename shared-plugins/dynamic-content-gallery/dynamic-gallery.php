<?php
/* This file is part of the DYNAMIC CONTENT GALLERY Plugin Version 2.2
**********************************************************************
Copyright 2008  Ade WALKER  (email : info@studiograsshopper.ch)

 *** Do not edit this block *** */
/* Load options */
$options = get_option('dfcg_plugin_settings');
/* Set up some variables to use in WP_Query */
$dfcg_offset = 1; 
$dfcg_imghome = $options['homeurl'];
$dfcg_imgpath = $options['imagepath'];
$dfcg_imgdefpath = $options['defimagepath'];
$dfcg_imgdefdesc = $options['defimagedesc'];
$dfcg_cat01 = $options['cat01'];
$dfcg_cat02 = $options['cat02'];
$dfcg_cat03 = $options['cat03'];
$dfcg_cat04 = $options['cat04'];
$dfcg_cat05 = $options['cat05'];
$dfcg_off01 = $options['off01']-$dfcg_offset;
$dfcg_off02 = $options['off02']-$dfcg_offset;
$dfcg_off03 = $options['off03']-$dfcg_offset;
$dfcg_off04 = $options['off04']-$dfcg_offset;
$dfcg_off05 = $options['off05']-$dfcg_offset;
$dfcg_gallery_delay = apply_filters( 'dfcg_gallery_delay', 10000 );
?>
<script type="text/javascript">
   function startGallery() {
      var myGallery = new gallery($('myGallery'), {
         timed: true,
         delay: <?php echo intval( $dfcg_gallery_delay ); ?>
      });
   }
   window.addEvent('domready',startGallery);
</script>

<div id="myGallery">

<?php
// *******************************************
// IMAGE ONE
// *******************************************

$recent = new WP_Query("cat=$dfcg_cat01&showposts=1&offset=$dfcg_off01");
if ( $recent ) : while($recent->have_posts()) : $recent->the_post(); ?>
	<div class="imageElement">
	<?php // Now find the cat ID
		foreach((get_the_category()) as $dfcg_category); ?>
         
 		<h3><?php the_title(); ?></h3>
		 								
		<?php if( get_post_meta($post->ID, "dfcg_desc", true) ): ?>
			<p><?php echo get_post_meta($post->ID, "dfcg_desc", true); ?></p>
		<?php elseif (empty($dfcg_category->category_description)): ?>
			<p><?php echo $dfcg_imgdefdesc; ?></p>
		<?php else: ?>
			<p><?php echo $dfcg_category->category_description; ?></p>
		<?php endif; ?>
					
       	<a href="<?php the_permalink() ?>" title="Read More" class="open"></a>
		<?php if( get_post_meta($post->ID, "dfcg_image", true) ): ?>
			<?php $dfcg_imgname = get_post_meta($post->ID, "dfcg_image", true); ?>
        	<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="full" />
        	<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php else: ?>
			<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="full" />
        	<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php endif; ?>
	</div>
	<?php endwhile; endif; ?>
	  
<?php
// *******************************************
// IMAGE TWO
// *******************************************

$recent = new WP_Query("cat=$dfcg_cat02&showposts=1&offset=$dfcg_off02");
if ( $recent ) : while($recent->have_posts()) : $recent->the_post(); ?>
	<div class="imageElement">
	<?php // Now find the cat ID
		foreach((get_the_category()) as $dfcg_category); ?>
         
 		<h3><?php the_title(); ?></h3>
		 								
		<?php if ( get_post_meta($post->ID, "dfcg_desc", true ) ): ?>
			<p><?php echo get_post_meta($post->ID, "dfcg_desc", true); ?></p>
		<?php elseif (empty($dfcg_category->category_description)): ?>
			<p><?php echo $dfcg_imgdefdesc; ?></p>
		<?php else: ?>
			<p><?php echo $dfcg_category->category_description; ?></p>
		<?php endif; ?>
					
   		<a href="<?php the_permalink() ?>" title="Read More" class="open"></a>
		<?php if( get_post_meta($post->ID, "dfcg_image", true) ): ?>
			<?php $dfcg_imgname = get_post_meta($post->ID, "dfcg_image", true); ?>
       		<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="full" />
       		<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php else: ?>
			<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="full" />
       		<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php endif; ?>
   	</div>
	<?php endwhile; endif; ?>
	  
<?php
// *******************************************
// IMAGE THREE
// *******************************************
 
$recent = new WP_Query("cat=$dfcg_cat03&showposts=1&offset=$dfcg_off03");
if ( $recent ) : while($recent->have_posts()) : $recent->the_post(); ?>
	<div class="imageElement">
	<?php // Now find the cat ID
		foreach((get_the_category()) as $dfcg_category); ?>
         
 		<h3><?php the_title(); ?></h3>
		 								
		<?php if( get_post_meta($post->ID, "dfcg_desc", true) ): ?>
			<p><?php echo get_post_meta($post->ID, "dfcg_desc", true); ?></p>
		<?php elseif (empty($dfcg_category->category_description)): ?>
			<p><?php echo $dfcg_imgdefdesc; ?></p>
		<?php else: ?>
			<p><?php echo $dfcg_category->category_description; ?></p>
		<?php endif; ?>
					
        <a href="<?php the_permalink() ?>" title="Read More" class="open"></a>
		<?php if( get_post_meta($post->ID, "dfcg_image", true) ): ?>
			<?php $dfcg_imgname = get_post_meta($post->ID, "dfcg_image", true); ?>
         	<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="full" />
         	<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php else: ?>
			<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="full" />
        	<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php endif; ?>
	</div>
	<?php endwhile; endif; ?>
	  
<?php
// *******************************************
// IMAGE FOUR
// *******************************************

$recent = new WP_Query("cat=$dfcg_cat04&showposts=1&offset=$dfcg_off04");
if ( $recent ) : while($recent->have_posts()) : $recent->the_post(); ?>
	<div class="imageElement">
	<?php // Now find the cat ID
		foreach((get_the_category()) as $dfcg_category); ?>
         
 		<h3><?php the_title(); ?></h3>
		 								
		<?php if( get_post_meta($post->ID, "dfcg_desc", true) ): ?>
			<p><?php echo get_post_meta($post->ID, "dfcg_desc", true); ?></p>
		<?php elseif (empty($dfcg_category->category_description)): ?>
			<p><?php echo $dfcg_imgdefdesc; ?></p>
		<?php else: ?>
			<p><?php echo $dfcg_category->category_description; ?></p>
		<?php endif; ?>
					
   		<a href="<?php the_permalink() ?>" title="Read More" class="open"></a>
		<?php if( get_post_meta($post->ID, "dfcg_image", true) ): ?>
			<?php $dfcg_imgname = get_post_meta($post->ID, "dfcg_image", true); ?>
       		<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="full" />
       		<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php else: ?>
			<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="full" />
       		<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php endif; ?>
   	</div>
	<?php endwhile; endif; ?>
	  
<?php
// *******************************************
// IMAGE FIVE
// *******************************************

$recent = new WP_Query("cat=$dfcg_cat05&showposts=1&offset=$dfcg_off05");
if ( $recent ) : while($recent->have_posts()) : $recent->the_post(); ?>
	<div class="imageElement">
		<?php // Now find the cat ID
		foreach((get_the_category()) as $dfcg_category); ?>
         
		<h3><?php the_title(); ?></h3>
		 								
		<?php if( get_post_meta($post->ID, "dfcg_desc", true) ): ?>
			<p><?php echo get_post_meta($post->ID, "dfcg_desc", true); ?></p>
		<?php elseif (empty($dfcg_category->category_description)): ?>
			<p><?php echo $dfcg_imgdefdesc; ?></p>
		<?php else: ?>
			<p><?php echo $dfcg_category->category_description; ?></p>
		<?php endif; ?>
					
        <a href="<?php the_permalink() ?>" title="Read More" class="open"></a>
		<?php if( get_post_meta($post->ID, "dfcg_image", true) ): ?>
			<?php $dfcg_imgname = get_post_meta($post->ID, "dfcg_image", true); ?>
        	<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="full" />
        	<img src="<?php echo $dfcg_imghome . $dfcg_imgpath . $dfcg_imgname; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php else: ?>
			<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="full" />
        	<img src="<?php echo $dfcg_imghome . $dfcg_imgdefpath . $dfcg_category->cat_ID . '.jpg'; ?>" alt="<?php the_title(); ?>" class="thumbnail" />
		<?php endif; ?>
   	</div>
	<?php endwhile; endif; ?>
      
</div>
