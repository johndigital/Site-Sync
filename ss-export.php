<?php

	class Funkexporter {

		public $total = -1;
		public $category = NULL;
		public $page = 2;


		private function get_post_attachments($post_id) {

			$args = array(
				'posts_per_page'   => -1,
				'post_type'        => 'attachment',
				'post_parent'      => $post_id
			);
			$attachments = get_posts($args);
			$output = array();

			foreach ( $attachments as $attachment ) {
				$url = wp_get_attachment_image_src($attachment->ID, 'full');
				$attachment->post_url = $url[0];
				$output[] = $attachment;
			}

			return $output;

		}

		private function get_post_cats($post){

			$post_categories = wp_get_post_categories( $post->ID );
			$cats = array();

			foreach($post_categories as $c){
				$cat = get_category( $c );
				$cats[] = $cat;
			}

			return $cats;

		}

		private function get_shortcode_att($post_content, $type = 'gallery', $att = 'ids'){

			// Extract shortcodes from content
			$pattern = get_shortcode_regex();
			$has_shortcodes = preg_match_all( '/'. $pattern .'/s', $post_content, $shortcodes );

			// Return false if no shortcodes found
			if ( !$has_shortcodes ) {
				return false;
			}

			// Build array for each shortcode found
			$shortcode_data = array();
			foreach( $shortcodes as $preg_matches ) {

				foreach( $preg_matches as $shorts_index => $atts ) {
					$shortcode_data[$shorts_index][] = $atts;
					$shortcode_data[$shorts_index] = array_filter($shortcode_data[$shorts_index]);
				}

			}

			// Format attribute string(s)
			$output = array();
			foreach( $shortcode_data as $key => $shortcode ) {

				// Only do this on type specified
				if( strtolower($shortcode[1]) == $type ) {

					// If found attributes match specified attribute...
					if ( strstr($shortcode[2], $att) ) {

						// Remove everything not a number or comma
						$ids = $shortcode[2];
						$ids = preg_replace(
							  array(
							    '/[^\d,]/',    // Matches anything that's not a comma or number.
							    '/(?<=,),+/',  // Matches consecutive commas.
							    '/^,+/',       // Matches leading commas.
							    '/,+$/'        // Matches trailing commas.
							  ),
							  '',              // Remove all matched substrings.
							  $ids
						);

						// Add to output
						$output[] = $ids;

					}
				}
			}

			return $output;

		}

		private function get_gallery_meta($shortcode_ids) {

			// Loop through shortcodes
			$output = array();
			foreach ( $shortcode_ids as $id_string ) {

				// Break string into useable array of IDs
				$ids = explode( ',', $id_string);

				foreach ( $ids as $id ) {

					// Get all meta info by ID
					$meta = get_post($id);

					// Push meta to output
					$output[$id] = $meta;

				}
			}

			return $output;

		}

		private function compile_post($post){

			// Add defualt $post
			$output['post_info'] = $post;

			// Add attached images to this
			$output['attached_images'] = $this->get_post_attachments($post->ID);

			// Set featured image properties and add them to output
			$featured_id = get_post_thumbnail_id( $post->ID );
			$featured_url = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'full');

			$output['featured_image']['url'] = $featured_url[0];
			$output['featured_image']['id'] = $featured_id;

			// Category info for this post
			$output['categories'] = $this->get_post_cats($post);

			// Tag info for this post
			$output['tags'] = wp_get_post_tags( $post->ID );

			// Any meta data for the post
			$output['meta'] = get_post_meta( $post->ID );

			// Check for galleries, and add metadata if needed
			$gallery_ids = $this->get_shortcode_att($post->post_content, 'gallery', 'ids');

			if ( $gallery_ids ) {
				$output['galleries'] = $this->get_gallery_meta($gallery_ids);
			}

			return $output;

		}

		public function export(){

			$args = array(
				'posts_per_page'	=> $this->total,
				'category__in'		=> $this->category
			);
			$posts = get_posts($args);

			// Start building output
			$output = array();
			foreach( $posts as $key => $post ) {

				$output['posts'][$key] = $this->compile_post($post);

			}

			// Check if page option is set
			if ( $this->page ) {

				// Query all children of that page
				$args = array(
					'post_type'			=> 'page',
					'posts_per_page'	=> -1,
					'post_parent'      => $this->page,
				);
				$pages = get_posts($args);

				// For each child
				foreach ( $pages as $key => $page ) {

					// compile object and add to output
					$output['pages'][$key] = $this->compile_post($page);

				}

			}

			header('Content-type: application/json');
			echo json_encode($output);
			die;

		}

	}

?>