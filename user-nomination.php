<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}
class user_nomination
{
    function load_module($directory, $urltoroot)
    {
        $this->directory = $directory;
        $this->urltoroot = $urltoroot;
    }

    function match_request($request)
    {
        return ($request === 'nomination');
    }
	function process_request($request)
    {
		date_default_timezone_set('Asia/Kolkata');
		require_once QA_INCLUDE_DIR . 'db/selects.php';
        require_once QA_INCLUDE_DIR . 'qa-db.php';
		require_once QA_INCLUDE_DIR . 'db/metas.php';
		require_once QA_INCLUDE_DIR . 'qa-app-users.php';

		if (!qa_is_logged_in()) {
			qa_redirect('login', ['to' => qa_path(qa_request(), ['poll' => qa_get("poll")])]);
		}	

        $content = qa_content_prepare();		
		$pollid=(int)qa_get("poll");
		$userid = qa_get_logged_in_userid();
		$userpoints=qa_get_logged_in_points();
		$handle = qa_get_logged_in_handle();
		$profile_fields = qa_db_select_with_pending(
				qa_db_user_profile_selectspec($handle, false)
			);
		$name = isset($profile_fields['name']) ? trim($profile_fields['name']==='')?$handle:$profile_fields['name'] : $handle;

		$poll=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^poll where id=#',$pollid),true);
		
		if($poll==Null)
		{
			$content['error'] = "There is no such poll available. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}
		$allowed_user=((qa_get_logged_in_level()<QA_USER_LEVEL_ADMIN)?0:1);
		if((!$allowed_user)&& ($poll['listing']=='n'))
		{
			$content['error'] = "There is no such poll available. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}
		if($poll['nomination'] != 'y')
		{
			$content['error'] = "Nominations are not allowed for this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}
		if($poll['nclosedate']<date('Y-m-d'))
		{
			$content['error'] = "Nominations are closed for this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;			
		}
		if(date('Y-m-d')<$poll['nopendate'])
		{
			$content['error'] = "Nominations are not open for this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;			
		}

		if($userpoints<$poll['nminpoints'])
		{
			$content['error'] = "You are not eligible for nomination of this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;			
		}
		if (qa_get('enrolled') == '1') {
			echo '<script>alert("Your nomination has been submitted successfully");</script>';
		}
		if (qa_get('withdrawn') == '1') {
			echo '<script>alert("Your nomination has been withdrawn successfully");</script>';
		}
		if (qa_get('interested') == '0') {
			echo '<script>alert("Your decision has been recorded successfully");</script>';
		}		
		$nominations=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and uid=#',$pollid,$userid),true);
		$nomination_submitted=0;
		($nominations==Null)?$nomination_submitted=0:(($nominations['nomination']=='y')?$nomination_submitted=1:(($nominations['nomination']=='n')?$nomination_submitted=2:$nomination_submitted=3));

		$nominated=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and nid=#',$pollid,$userid),true);
		$nominated_by_others=0;
		($nominated==Null)?$nominated_by_others=0:$nominated_by_others=1;

		
		$content['custom'] = '<div><p>Description of the poll: '.$poll['description'].'</p><p>Last Date for Submission of Nomination: '.$poll['nclosedate'].'</p><p>Opening Date of Poll: '.$poll['popendate'].'</p><p>Closing Date of Poll: '.$poll['pclosedate'].'</p><p>Nomination Status: '.((($nomination_submitted==0)&&($nominated_by_others==0))?"Yet to submit nomination":((($nomination_submitted==0)&&($nominated_by_others==1))?"Other user nominated you":(($nomination_submitted==1)?"Nomination submitted":(($nomination_submitted==2)?"Not interested in Nomination":"Nomination Withdrawn")))).'</p>'.($poll["nothers"]=='y'?"<p>You can nominate upto ".$poll["notherscount"]." users (excluding you)</p>":"").'<p>Stats of this poll:<a href="'.qa_path_html("poll-stats", ["poll" => $poll["id"]]).'"> click here </a></p><p>List of available polls:<a href="'.qa_path_html("list-polls").'"> click here </a></p></div>';
		
		$saved = false;
		$field_errors = [];
		
		if (qa_post_text('nomination-page-form')) {
			// Collect inputs
			$participation = qa_html(qa_post_text('participation'));
			$uname = trim(qa_html(qa_post_text('fname')));
			$otheruser= qa_html(qa_post_text('otheruser'));
			$participation_value = qa_html(qa_post_text('participation')) ? qa_html(qa_post_text('participation')) : ((($nomination_submitted == 0) && ($nominated_by_others == 0)) ? 'y' : 'w');

			$is_default_yes = (($nomination_submitted == 0) && ($nominated_by_others == 0)) ? 'y' : 'w';
			$is_default_no  = (($nomination_submitted == 0) && ($nominated_by_others == 0)) ? 'n' : 'y';
			if (($participation=='y')&& empty($uname)) {
				$field_errors['fname'] = 'Name cannot be empty.';
			}
			
			$nominatedusers = qa_post_text('nominatedusers') ? qa_post_text('nominatedusers') : "";
			$nominateduserslist = array_map('trim', explode(',', $nominatedusers));
			//Remove the spaces
			$nominateduserslist=array_values(array_filter($nominateduserslist, fn($v) => trim($v) !== ''));
			$nominateduserslist = array_unique($nominateduserslist);
			sort($nominateduserslist);
			
			if(($otheruser=='y')&&(sizeof($nominateduserslist)==0))
			{
				$field_errors['nominatedusers'] = 'Atleast nominate one user.';
			}
			else if(($otheruser=='y')&&(sizeof($nominateduserslist)>$poll["notherscount"]))
			{
				$field_errors['nominatedusers'] = "You can not nominate more than ".$poll["notherscount"]." users for this poll.";
			}			
			//
			else{
				foreach ($nominateduserslist as $nominateduser) {
					$nominated_user_id=qa_handle_to_userid($nominateduser);
					if($nominated_user_id==null){
						$field_errors['nominatedusers'] = "User '".$nominateduser."' is not found in the database.";
					}
					else{
						$check_interested=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and uid=#',$pollid,$nominated_user_id),true);
						$user_interested=($check_interested==Null)?1:($check_interested['nomination']=='y'?1:0);
						if(!$user_interested){
							$field_errors['nominatedusers'] =  "User '".$nominateduser."' is not interested for this poll.";
						}
					}
				}
			}
			if (!empty($field_errors)) {
				$saved = false;
			}
			else{
	 			if(($nomination_submitted==0)&&($nominated_by_others==0))
				qa_db_query_sub(
					'INSERT INTO ^pnomination(pid, uid, nid, uname, nomination) VALUES (#, #, #,$, $)',$pollid,$userid,$userid, $uname, $participation
				);
	 			else if(($nomination_submitted==0)&&($nominated_by_others==1)){
				qa_db_query_sub(
					'INSERT INTO ^pnomination(pid, uid, nid, uname, nomination) VALUES (#, #, #,$, $)',$pollid,$userid,$userid, $uname, $participation
				);
				qa_db_query_sub(
					'UPDATE ^pnomination SET nomination=$, uname=$ where pid = # and nid =#',$participation,$uname, $pollid,$userid
				);}
				else
					qa_db_query_sub(
					'UPDATE ^pnomination SET nomination=$, uname=$ where pid = # and nid =#',$participation,$uname, $pollid,$userid
				);
				
				if($otheruser=='y'){
					foreach ($nominateduserslist as $nominateduser) {
						$nominated_user_id=qa_handle_to_userid($nominateduser);
						$profile_fields_user = qa_db_select_with_pending(qa_db_user_profile_selectspec($nominateduser, false));
						$name_nominateduser = isset($profile_fields_user['name']) ? trim($profile_fields_user['name']==='')?$nominateduser:$profile_fields_user['name'] :$nominateduser;
						qa_db_query_sub('INSERT INTO ^pnomination(pid, uid, nid, uname, nomination) VALUES (#, #, #,$, $)',$pollid,$userid,$nominated_user_id,$name_nominateduser, 'y');
					}
				}		
				$saved = true;
			}
		}
		else
		{
			$participation_value = qa_html(qa_post_text('participation')) ? qa_html(qa_post_text('participation')) : ((($nomination_submitted == 0) && ($nominated_by_others == 0)) ? 'y' : 'w');

			$is_default_yes = (($nomination_submitted == 0) && ($nominated_by_others == 0)) ? 'y' : 'w';
			$is_default_no  = (($nomination_submitted == 0) && ($nominated_by_others == 0)) ? 'n' : 'y';

			$uname = ((($nomination_submitted == 0) && ($nominated_by_others == 0)) ? $name : (($nomination_submitted == 1) ? $nominations['uname'] : $nominated['uname']));
			$otheruser="n";
			$nominatedusers="";
		}
        // Form fields
			$fields = [
				[
					'label' => (($nomination_submitted == 0) && ($nominated_by_others == 0)) ? 'Want to list your name in the Poll?' : 'Want to withdraw your nomination?',
					'type' => 'custom',
					'html' => '<label><input type="radio" name="participation" value="' . $is_default_yes . '" ' . ($participation_value == $is_default_yes ? 'checked' : '') . '> Yes</label>
					<label><input type="radio" name="participation" value="' . $is_default_no . '" ' . ($participation_value == $is_default_no ? 'checked' : '') . '> No</label>',
					'tags' => 'NAME="participation" id="participation"',
					'error' => isset($field_errors['participation']) ? $field_errors['participation'] : null,
				],
				[
					'label' => 'Your name to be displayed in the poll',
					'type' => 'custom',
					'html' => '<label><input type="text" name="fname" id="participationname" value="' . htmlspecialchars($uname) . '"></label>',
					'error' => isset($field_errors['fname']) ? $field_errors['fname'] : null,
				],
			];

			// If nomination of others is allowed and nomination not submitted yet
			if ($poll["nothers"] == 'y' && ($nomination_submitted == 0)) {
				$fields[] = [
					'label' => 'Want to nominate other users?',
					'type' => 'custom',
					'html' => '<label><input type="radio" name="otheruser" value="y" ' . (($otheruser == 'y') ? 'checked' : '') . '> Yes</label>
					<label><input type="radio" name="otheruser" value="n" ' . (($otheruser == 'n') ? 'checked' : '') . '> No</label>',
					'tags' => 'NAME="otheruser"',
					'error' => isset($field_errors['otheruser']) ? $field_errors['otheruser'] : null,
				];
				

				$fields[] = [
					'label' => 'Search users and click to add into the nomination list:',
					'type' => 'custom',
					'html' => '<label><input type="text" id="q2apro_usersearch" name="q2apro_usersearch" autofocus></label><div class="q2apro_us_progress"><div>Loading…</div></div>
						<div id="q2apro_ajaxsearch_results"></div>',

				];
				$fields[] = [
					'label' => 'Enter the username(s) you want to nominate (separated by commas)',
					'type' => 'custom',
					'html' => '<label><input type="text" name="nominatedusers" value="'.$nominatedusers.'"></label>',
					'error' => isset($field_errors['nominatedusers']) ? $field_errors['nominatedusers'] : null,
				];
			}

		if($nomination_submitted<2){
			$content['form'] = [
				'style' => 'wide',
				'tags' => 'method="post" action="' . qa_path_html(qa_request(), ['poll' => $pollid]) . '"',
				'fields' => $fields,
				'buttons' => [
					['label' => 'Submit', 'tags' => 'name="nomination-page-form" onclick="return confirm(\'Are you sure you want to save?\')"', ]
				],
			];
		}
        if ($saved) {
			if($participation=='y')
				qa_redirect(qa_request(), ['poll' => $pollid, 'enrolled' => 1]);
			else if($participation=='w')
				qa_redirect(qa_request(), ['poll' => $pollid, 'withdrawn' => 1]);
			else
				qa_redirect(qa_request(), ['poll' => $pollid, 'interested' => 0]);
        }
				 
		$content['title'] = 'Nomination page for '.$poll['heading'].' poll';
		$plugin_url = qa_path_to_root() . 'qa-plugin/' . basename(dirname(__FILE__)) . '/';
		$content['head_lines'][] = '<link rel="stylesheet" href="'.$plugin_url.'style/usersearch.css">';
		$content['script'][] = '
        <script>
		document.addEventListener("DOMContentLoaded", function () {

			function getFieldByName(name) {
				return document.querySelector(\'[name="\' + name + \'"]\');
			}
			function getFieldByclassName(name) {
				return document.querySelector(\'.\' + name);
			}

			function getRow(field) {
				let el = field;
				while (el && el.tagName !== "TR") {
					el = el.parentElement;
				}
				return el;
			}

			function showField(field) {
				const row = getRow(field);
				if (row) row.style.display = "table-row";
			}

			function hideField(field) {
				const row = getRow(field);
				if (row) row.style.display = "none";
			}

			function toggleFields(radioName, selectedValue) {
				const config = fieldMap[radioName]?.[selectedValue];
				if (!config) return;

				// Hide fields
				(config.hide || []).forEach(name => {
					
					const field = (getFieldByName(name));
					if (field) {hideField(field);}
					else if(getFieldByclassName(name)){getFieldByclassName(name).style.display = "none";}
				});

				// Show fields
				(config.show || []).forEach(name => {
					
					field = getFieldByName(name);
					if (field) {showField(field);}
					else if(getFieldByclassName(name)){getFieldByclassName(name).style.display = "";}
				});
			}

			// Define field toggle behavior per radio group and value
			const fieldMap = {
				participation: {
					y: {
						show: ["fname"]
					},
					n: {						
						hide: ["fname"]
					}
				},
				otheruser: {
					y: {
						show: ["nominatedusers","q2apro_usersearch"]
					},
					n: {						
						hide: ["nominatedusers","q2apro_usersearch"]
					}
				},
			};

			// Attach event listeners for all radio groups defined in fieldMap
			Object.keys(fieldMap).forEach(radioGroupName => {
				const radios = document.querySelectorAll(\'input[name="\' + radioGroupName + \'"]\');
				radios.forEach((radio, index) => {
					radio.addEventListener("change", () => {
						if (radio.checked) {
							toggleFields(radioGroupName, radio.value);
						}
					});
				});
			});
			
			// Initial call to toggleFields for each radio group
			Object.keys(fieldMap).forEach(radioGroupName => {
				const selected = document.querySelector(\'input[name="\' + radioGroupName + \'"]:checked\');
				if (selected) {
					toggleFields(radioGroupName, selected.value);
				}
			});
			startsearching();
		});
		function to_add_username(username){
			const target = document.querySelector(\'[name="nominatedusers"]\');
			target.value += username + ",";
		}

		function startsearching() {
			document.querySelectorAll(".q2apro_us_progress").forEach(function(el) {
				el.style.display = "none";
			});

			const userSearchInput = document.getElementById("q2apro_usersearch");
			if (userSearchInput) {
				userSearchInput.addEventListener("keyup", function() {
					const us_username = this.value.trim();
					if (us_username !== "") {
						q2apro_ajaxsearch(us_username);
					} else {
						const results = document.getElementById("q2apro_ajaxsearch_results");
						if (results) {
							results.style.display = "none";
						}
					}
				});
			}
		}

		function q2apro_ajaxsearch(us_username) {
			document.querySelectorAll(".q2apro_us_progress").forEach(function(el) {
				el.style.display = "block";
			});

			const xhr = new XMLHttpRequest();
			xhr.open("POST", "'.qa_path('usersearch').'", true);
			xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

			xhr.onerror = function() {
				const results = document.getElementById("q2apro_ajaxsearch_results");
				if (results) {
					results.innerHTML = "bad bad server...";
					document.querySelectorAll(".q2apro_us_progress").forEach(function(el) {
						el.style.display = "none";
					});
				}
			};

			xhr.onload = function() {
				const results = document.getElementById("q2apro_ajaxsearch_results");
				if (results) {
					let response = xhr.responseText;
					if (response.trim() === "") {
						response = "No user found";
					}
					results.style.display = "block";
					results.innerHTML = JSON.parse(response);
				}
				document.querySelectorAll(".q2apro_us_progress").forEach(function(el) {
					el.style.display = "none";
				});
			};

			xhr.send("ajaxw=" + encodeURIComponent(us_username));
		}
        </script>';

        return $content;
    }
}