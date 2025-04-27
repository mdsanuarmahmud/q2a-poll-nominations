<?php

/*
	Plugin Name: q2apro-ajax-usersearch
	Plugin URI: http://www.q2apro.com/plugins/usersearch
*/


	class usersearch {
		
		var $directory;
		var $urltoroot;
		
		function load_module($directory, $urltoroot)
		{
			$this->directory = $directory;
			$this->urltoroot = $urltoroot;
		}
		
		// for display in admin interface under admin/pages
		function suggest_requests() 
		{	
			return array(
				array(
					'title' => 'Ajax Usersearch Page', // title of page
					'request' => 'usersearch', // request name
					'nav' => null, // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				),
			);
		}
		
		// for url query
		function match_request($request)
		{
			if ($request=='usersearch') {
				return true;
			}
			return false;
		}

		function process_request($request)
		{
		
			// we received post data, it is the ajax call with the username
			$transferString = qa_post_text('ajaxw');
			if($transferString !== null) {
				
				// this is echoed via ajax success data
				$output = '';
				
				$username = $transferString;
				
				// ajax return all user events
				$potentials = qa_db_read_all_assoc(
					qa_db_query_sub(
						'SELECT userid FROM ^users 
						 WHERE `handle` LIKE #
						 LIMIT #',
						'%'.$username.'%',
						10
					)
				);

				foreach($potentials as $user) {
					if(isset($user['userid'])) {
						// get userdata
						$userdata = qa_db_read_one_assoc(qa_db_query_sub('SELECT handle,avatarblobid FROM ^users 
																		WHERE userid = #', 
																		$user['userid']));
						$imgsize = 100;
						if(isset($userdata['avatarblobid'])) {
							$avatar = './?qa=image&qa_blobid='.$userdata['avatarblobid'].'&qa_size='.$imgsize;
						}
						else {
							$avatar = "";
						}
						$handledisplay = qa_html($userdata['handle']);
						// user item
						// Escape for JavaScript and HTML separately
						$escapedForJS = htmlspecialchars(json_encode($handledisplay), ENT_QUOTES, 'UTF-8'); // safe inside HTML attribute
						$escapedForHTML = htmlspecialchars($handledisplay, ENT_QUOTES, 'UTF-8'); // safe for visible display

						$output .= '<div class="q2apro_usersearch_resultfield">
							
								<img src="'.$avatar.'" alt="'.$escapedForHTML.'" onclick="to_add_username('.$escapedForJS.')" >
							
							<br />
							<p class="q2apro_us_link" onclick="to_add_username('.$escapedForJS.')">'.$escapedForHTML.'</p>
						</div>';

					} // end isset userid
				} // end foreach
			
				header('Access-Control-Allow-Origin: '.qa_path(null));
				echo $output;
				
				exit(); 
			} // END AJAX RETURN
			else {
				echo 'Unexpected problem detected. No transfer string.';
				exit();
			}
			
			
			/* start */
			$qa_content = qa_content_prepare();

			$qa_content['title'] = ''; // page title

			// return if not admin!
			if(qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN) {
				$qa_content['error'] = '<p>Access denied</p>';
				return $qa_content;
			}
			else {
				$qa_content['custom'] = '<p>Hi Admin, it actually makes no sense to call the Ajax URL directly.</p>';
			}

			return $qa_content;
		} // end process_request
		
	}; // end class
	
/*
	Omit PHP closing tag to help avoid accidental output
*/