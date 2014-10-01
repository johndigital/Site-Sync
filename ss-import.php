<?php 

	class Funkimporter {

		public $source_url;
		public $author;
		public $page;
		public $category;

		private $error_log;

		/*
		 * Helper function used to gather all requested posts
		 *
		 * @Returns array of all posts
		 */
			private function gatherPosts() {

				// Set post container
				$all_posts = array();

				// Convert string of categories into array
				$target_cats = trim( preg_replace('/\s+/','', $this->categories) );
				$target_cats = explode(',', $target_cats);
				$target_cats = array_filter($target_cats);

				// Pull json from api and decode
				$url = $this->source_url;

				$requestArgs = array(
				    'timeout'     => 20,
				);

				// Get contents of URL and if successful...
				$response = wp_remote_retrieve_body( wp_remote_get($url, $requestArgs) );

				if ( $response ) {

					// Decode json into array
					$response = json_decode($response, true);

					if ( $response['posts'] ) {
						// Loop through posts in current page load them all into array
						foreach ($response['posts'] as $single_post) {

							// Push post into all_posts array
							$all_posts[] = $single_post;

						}
					}

					if ( $response['pages'] ) {
						foreach ($response['pages'] as $single_page) {

							// Push page into all_posts array
							$all_posts[] = $single_page;

						}
					}

				} else {

					// If initial response unsuccessful, log it and move on
					$this->error_log[] = 'Error: failed to get any content from server';

				}

				return $all_posts;

			}

		/* 
		 * The following code has been taken from
		 * the media_handle_sideload() reference page 
		 * http://codex.wordpress.org/Function_Reference/media_handle_sideload 
		 *
		 * Sets URL into a useable $_FILE array, and attaches it to page
		 *
		 * @Param STRING, url of image to download and import
		 * @Param INT, ID of post to attach image to
		 * @Returns image ID on success, or false on failure
		 *
		 */
			private function sideLoad($url, $post_id) {

				// If image uploading dependencies have not been added, add them now
				if ( ! function_exists('download_url') ) {
					require_once('wp-admin/includes/image.php');
					require_once('wp-admin/includes/file.php');
					require_once('wp-admin/includes/media.php');
				}

				//download image from url
				$tmp = download_url( $url );

				// Set variables for storage
				// fix file filename for query strings
				preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
				$file_array['name'] = basename($matches[0]);
				$file_array['tmp_name'] = $tmp;

				// If error storing temporarily, unlink
				if ( is_wp_error( $tmp ) ) {
					@unlink($file_array['tmp_name']);
					$file_array['tmp_name'] = '';
				}

				// do the validation and storage stuff,
				// Set ID
				$id = media_handle_sideload( $file_array, $post_id );

				// If error storing permanently, unlink and return false
				if ( is_wp_error($id) ) {
					@unlink($file_array['tmp_name']);
					return false;
				}

				return $id;

			}

		/* 
		 * Helper function used to check if imported post already exists,
		 * and if not converts post info from api format into post array usable with wp_insert_post()
		 * 
		 * @Param ARRAY, metadata from single post from api
		 * @Returns array of formatted post info formatted for wp_insert_post() on success
		 * @Returns false if post already exists
		 *
		 */
			private function buildPost($post){

					wp_reset_postdata();
					// Check for any existing posts with this post's imported_id
					$args = array(
						'posts_per_page'	=> -1,
						'post_type'			=> $post["post_info"]["post_type"],
						'meta_key'			=> 'imported_id',
						'meta_value'		=> $post["post_info"]["ID"]
					);
					$existing_posts = get_posts($args);

					wp_reset_postdata();
					// Check for any existing pages with this page's slug
					$pageargs = array(
						'name'				=> $post["post_info"]["post_name"],
						'posts_per_page'	=> 1,
						'post_type'			=> $post["post_info"]["post_type"]
					);
					$existing_pages = get_posts($pageargs);

					// If any matching posts are found,
					// then skip this post, because it already exists
					if (
						$existing_posts 												 || // matching old IDs
						( $post["post_info"]["post_type"] == 'page' && $existing_pages ) || // matching slugs
						$post["meta"]["imported_id"] // This post was imported
					) {
						return false;
					}

					// If this is a post...
					if ( $post["post_info"]["post_type"] == 'post' ) {

						// Build array of category IDs
						$newCats = array();
						foreach ( $post["categories"] as $category ) {

							// Attempt to find a matching category by slug
							$cat_id = get_category_by_slug( $category["slug"] );

							// If category match was found...
							if ($cat_id) {

								// Push new category ID into array
								$newCats[] = $cat_id->cat_ID;

							}

						}

						// If a default category was specified...
						if ( $this->category ) {

							// Add default category to post categories
							$newCats[] = $this->category;

						}

						// Build array of tags
						$newTags = array();
						foreach ( $post["tags"] as $tag ) {

							// Push each tag slug into array
							$newTags[] = $tag["slug"];

						}

					}

					if ( $this->page && $post["post_info"]["post_type"] =='page' ) {
						$parent = $this->page;
					} else {
						$parent = 0;
					}

					// Set up data for new post
					$newPost = array(
						'post_content'   => $post["post_info"]["post_content"],
						'post_name'      => $post["post_info"]["post_name"],
						'post_title'     => $post["post_info"]["post_title"],
						'post_status'    => $post["post_info"]["post_status"],
						'post_author'    => $this->author_id,
						'ping_status'    => $post["post_info"]["ping_status"],
						'post_excerpt'   => $post["post_info"]["post_excerpt"],
						'post_date_gmt'      => $post["post_info"]["post_date_gmt"],
						'post_type'		 => $post["post_info"]["post_type"],
						'post_parent'	 => $parent,
						'comment_status' => $post["post_info"]["comment_status"],
						'post_category'  => $newCats,
						'tags_input'     => $newTags
					);

					return $newPost;

			}

		/*
		 * Helper function used to replace IDs in post shortcode
		 *
		 * @Param STRING, content of post that contains shortcodes
		 * @Param ARRAY, set of $key => $value pairs where each $key is an old ID and each $value is a new ID
		 *
		 * @Returns full content with the IDs replaced
		 */
			private function transplantIds( $content, $loadedIds ) {

				// Loop through ID pairs
				foreach ($loadedIds as $oldId => $newId) {

					// Find and replace for each ID
					$content = str_replace($oldId, $newId, $content);

				}

				return $content;

			}

		/*
		 * Main function used to import feeds and attach them to page
		 */
			public function import() {

				// Get all gathered posts
				$posts = $this->gatherPosts();

				// Set counters
				$createdCount = 0;
				$attachedCount = 0;

				// Loop through each post
				foreach ( $posts as $post ) {

					// Format post for wp_insert_post()
					$newPost = $this->buildPost($post);

					// If post info is ready...
					if ( $newPost ) {

						// Attempt to create new post
						$new_id = wp_insert_post( $newPost );

					} else {

						// If buildPost() came back false, skip to next post
						continue;

					}

					// If post successfully created...
					if ( $new_id ) {

						// Increment counter
						$createdCount++;

						// Add old ID meta field
						add_post_meta($new_id, 'imported_id', $post["post_info"]["ID"]);

						// If old post had any meta fields, add them now
						if ( $post["meta"] ) {
							foreach ( $post["meta"] as $metaKey => $metaValue ) {

								$metaValue = unserialize($metaValue[0]);
								update_post_meta($new_id, $metaKey, $metaValue);

							}
						}

						$featured_id = false;
						// If old post had any attachments...
						if ( !empty($post["attached_images"]) ) {

							$all_attached = array();

							// Loop through all attachments
							foreach ( $post["attached_images"] as $attachment ) {

								// Attempt to sideLoad image and attach to post
								$attached_id = $this->sideLoad($attachment["post_url"], $new_id);

								// If attachment successful...
								if ( $attached_id ) {

									// increment counter
									$attachedCount++;

									// Push old ID into $all_attached array
									$all_attached[] = $attachment["ID"];

									// Check if this attachment is the featured image
									if ( $post["featured_image"]["id"] == $attachment["ID"] ) {

										// If so, remember the ID
										$featured_id = $attached_id;

									}

								} else {

									$this->error_log[] = 'Error attaching image ID: ' . $attachment["ID"];

								}
							}
						}

						// Check if post has a featured image...
						if ( !empty($post["featured_image"]["id"]) ) {

							// If featured image didn't get loaded with attachments, load it now
							if ( !$featured_id ) {

								$featured_id = $this->sideLoad( $post["featured_image"]["url"], $new_id );

							}

							// Set featured image by ID
							set_post_thumbnail( $new_id, $featured_id );

						}

						// If post content has any gallery shortcodes...
						if ( $post["galleries"] ) {

							// Loop through gallery images
							$loaded_images = array();
							foreach ( $post["galleries"] as $gal_image ) {

								// If image has already been loaded, skip it
								if ( !$gal_image || in_array($gal_image, $all_attached) ) {
									continue;
								}

								// Attempt to sideload and attach this image
								$new_att_id = $this->sideLoad( $gal_image["guid"], $new_id );

								// If successful...
								if ( $new_att_id ) {

									// Keep record of new ID matched with old ID
									$loaded_images[$gal_image["ID"]] = $new_att_id;

								}

							}

							// Get newly created post
							$new_post = get_post($new_id);

							// get IDs from shortcode (or false if none specified)
							$gallery_codes = get_shortcode_att($new_post->post_content, 'gallery', 'ids');

							// Change IDs in shortcode
							$new_content = $this->transplantIds($new_post->post_content, $loaded_images);
							$new_post->post_content = $new_content;

							// Update post
							wp_update_post($new_post);

						}

					} else {

						// If post creation unsuccessful, log it
						$this->error_log[] = 'Post creation was unsuccessful for post ID: ' . $post["post_info"]["id"];
						continue;

					}

				}

				// Output any/all errors logged along the way
				$total_errors = count($this->error_log);
				$output = '<br><br><strong>' . $total_errors . ' errors returned:<br>';
				$output .= $createdCount . ' new posts found and uploaded</strong><br><br>';

				if ( $total_errors ) {

					foreach ( $this->error_log as $error ) {
						$output .= $error . '<br>';
					}

				} else {
					$output .= 'Feed load was successful!';
				}

				echo $output;
				die;

			}
	}

?>