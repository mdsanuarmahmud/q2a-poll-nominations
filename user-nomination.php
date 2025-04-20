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
			return qa_redirect('login');
		}
        $content = qa_content_prepare();		
		$pollid=(int)qa_get("poll");
		$userid = qa_get_logged_in_userid();
		$userpoints=qa_get_logged_in_points();
		$handle = qa_get_logged_in_handle();
		$profile_fields = qa_db_select_with_pending(
				qa_db_user_profile_selectspec($handle, false)
			);
		$name = isset($profile_fields['name']) ? trim($profile_fields['name']==='')?'':$profile_fields['name'] : '';

		$poll=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^poll where id=#',$pollid),true);
		
		if($poll==Null)
		{
			$content['error'] = "There is no such poll available. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}
		$allowed_user=((qa_get_logged_in_level()<QA_USER_LEVEL_ADMIN)?0:1);
		if((!$allowed_user)&& ($poll['Listing']=='n'))
		{
			$content['error'] = "There is no such poll available. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}
		if($poll['Nomination'] != 'y')
		{
			$content['error'] = "Nominations are not allowed for this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}
		if($poll['NcloseDate']<date('Y-m-d'))
		{
			$content['error'] = "Nominations are closed for this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;			
		}
		if(date('Y-m-d')<$poll['NopenDate'])
		{
			$content['error'] = "Nominations are not open for this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;			
		}

		if($userpoints<$poll['NMinPoints'])
		{
			$content['error'] = "You are not eligible for nomination of this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;			
		}

		$nominations=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and uid=#',$pollid,$userid),true);

		$nominated=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and nid=#',$pollid,$userid),true);

		$nomination_submitted=0;
		$nominated_by_others=0;
		($nominations==Null)?$nomination_submitted=0:(($nominations['nomination']=='y')?$nomination_submitted=1:(($nominations['nomination']=='n')?$nomination_submitted=2:$nomination_submitted=3));
		($nominated==Null)?$nominated_by_others=0:$nominated_by_others=1;

		
		$content['custom'] = '<div><p>Description of the poll: '.$poll['Description'].'</p><p>Last Date for Submission of Nomination: '.$poll['NcloseDate'].'</p><p>Opening Date of Poll: '.$poll['PopenDate'].'</p><p>Closing Date of Poll: '.$poll['PcloseDate'].'</p><p>Username: '.$handle.'</p><p>Nomination Status: '.((($nomination_submitted==0)&&($nominated_by_others==0))?"Yet to submit nomination":((($nomination_submitted==0)&&($nominated_by_others==1))?"Other user nominated you":(($nomination_submitted==1)?"Nomination submitted":(($nomination_submitted==2)?"Not interested in Nomination":"Nomination Withdrawn")))).'</p>'.($poll["Nothers"]=='y'?"<p>You can nominate upto ".$poll["Notherscount"]." users (excluding you)</p>":"").'<p>'."Stats of this poll:<a href='" .qa_path_html("poll-stats", ["poll" => $poll["id"]])."'> click here </a> ".'</p></div>';
		
		$saved = false;
		if (qa_post_text('nomination-page-form')) {
			// Collect inputs
			$validation=true;
			$participation = qa_html(qa_post_text('participation'));
			$uname = trim(qa_html(qa_post_text('fname')));
			$otheruser= qa_html(qa_post_text('otheruser'));
			if(($participation=='y')&&($uname === ""))
			{
				echo "<script>alert('Your name should not be empty');</script>";
				$validation=false;
			}
	
			$nominatedusers = isset($_POST['nominatedusers']) ? $_POST['nominatedusers'] : "";
			$nominateduserslist = array_map('trim', explode(',', $nominatedusers));
			//Remove the spaces
			$nominateduserslist=array_values(array_filter($nominateduserslist, fn($v) => trim($v) !== ''));
			$nominateduserslist = array_unique($nominateduserslist);
			
			if(($otheruser=='y')&&(sizeof($nominateduserslist)==0))
			{
				echo "<script>alert('Uname \'".$uname."\' ');alert('Atleast nominate one user');</script><br>";
				$validation=false;
			}
			else if(($otheruser=='y')&&(sizeof($nominateduserslist)>$poll["Notherscount"]))
			{
				echo "<script>alert('You can not nominate more than \'".$poll["Notherscount"]."\' users for this poll.');</script>";
				$validation=false;
			}			
			//
			else{
				foreach ($nominateduserslist as $nominateduser) {
					$nominated_user_id=qa_handle_to_userid($nominateduser);
					if($nominated_user_id==null){
						echo "<script>alert('User \'".$nominateduser."\' is not found in the database.');</script>";
						$validation=false;
					}
					else{
						$check_interested=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and uid=#',$pollid,$nominated_user_id),true);
						$user_interested=($check_interested==Null)?1:($check_interested['nomination']=='y'?1:0);
						if(!$user_interested){
							echo "<script>alert('User \'".$nominateduser."\' is not interested for this poll.');</script>";
							$validation=false;
						}
					}
				}
			}
			if($validation){
				echo "<script>alert('Uname \'".$uname."\' ');</script>";
	 			if(($nomination_submitted==0)&&($nominated_by_others==0))
				qa_db_query_sub(
					'INSERT INTO ^pnomination(pid, uid, nid, uname, nomination) VALUES (#, #, #,$, $)',$pollid,$userid,$userid, $uname, $participation
				);
	 			else if(($nomination_submitted==0)&&($nominated_by_others==1)){
				qa_db_query_sub(
					'INSERT INTO ^pnomination(pid, uid, nid, uname, nomination) VALUES (#, #, #,$, $)',$pollid,$userid,$userid, $uname, $participation
				);
				qa_db_query_sub(
					'UPDATE ^pnomination SET nomination=$ where pid = # and nid =#',$participation, $pollid,$userid
				);}
				else
					qa_db_query_sub(
					'UPDATE ^pnomination SET nomination=$ where pid = # and nid =#',$participation, $pollid,$userid
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
        // Form fields
			$fields = [
				
				['label' => ((($nomination_submitted==0)&&($nominated_by_others==0))?'Want to list your name in the Poll ?':'Want to withdraw your nomination ?'), 'type' => 'custom',
				'html' => '<label><input type="radio" name="participation" value="'.((($nomination_submitted==0)&&($nominated_by_others==0))?'y':'w').'" checked> yes</label><label><input type="radio" name="participation" value="'.((($nomination_submitted==0)&&($nominated_by_others==0))?'n':'y').'"> No</label>', 'tags' => 'NAME="participation" id="participation"'],
				['label' => 'Your name to be displayed in the poll', 'type'=> ((($nomination_submitted==0)&&($nominated_by_others==0))?'text':'text'), 'tags' => 'NAME="fname" id="participationname"', 'value'=>((($nomination_submitted==0)&&($nominated_by_others==0))?$name:(($nomination_submitted==1)?$nominations['uname']:$nominated['uname']))]];

			if($poll["Nothers"]=='y' && ($nomination_submitted==0)){
				$fields []=['label' => 'Want to nominate other users ?', 'type' => 'custom',
				'html' => '<label><input type="radio" name="otheruser" value="y"> yes</label><label><input type="radio" name="otheruser" value="n" checked> No</label>', 'tags' => 'NAME="otheruser"'];
				$fields []=['label' => 'Enter the username of member to whom you want to nominate (separated by commas)', 'type'=> 'text', 'tags' => 'NAME="nominatedusers"'];
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
			qa_redirect(qa_request(), ['poll' => $pollid]);
        } 	

		$content['title'] = 'Nomination page for '.$poll['Heading'].' poll';

		$content['script'][] = '
        <script>
		document.addEventListener("DOMContentLoaded", function () {

			function getFieldByName(name) {
				return document.querySelector(\'[name="\' + name + \'"]\');
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
					const field = getFieldByName(name);
					if (field) hideField(field);
				});

				// Show fields
				(config.show || []).forEach(name => {
					const field = getFieldByName(name);
					if (field) showField(field);
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
						show: ["nominatedusers"]
					},
					n: {						
						hide: ["nominatedusers"]
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

		});

        </script>';		
        return $content;
    }
}