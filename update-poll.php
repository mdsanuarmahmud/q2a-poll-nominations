<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}
class update_poll
{
    function load_module($directory, $urltoroot)
    {
        $this->directory = $directory;
        $this->urltoroot = $urltoroot;
    }

    function match_request($request)
    {
        return ($request === 'update-poll');
    }
	
    function process_request($request)
    {

		if (!qa_is_logged_in()) {
			$_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
			qa_redirect('login');
		}
        $content = qa_content_prepare();		
		if(qa_get_logged_in_level()<QA_USER_LEVEL_ADMIN)
		{
			$content['error'] = 'You must be Admin or Above to access this page.';
            return $content;			
		}
		$pollid=qa_get("poll");
		if(!(isset($pollid)))
		{
			$content['error'] = "Poll ID missing in the URL. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the polls";
            return $content;
		}		
		require_once QA_INCLUDE_DIR . 'db/metas.php';
		require_once QA_INCLUDE_DIR . 'qa-app-users.php';
		require_once QA_INCLUDE_DIR . 'qa-db.php';
		$userid = qa_get_logged_in_userid();
		$level=qa_get_logged_in_level();

 		$poll=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^poll where id=#',$pollid),true);
		if($poll==Null)
		{
			$content['error'] = "There is no such poll available. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}       
		$Noptions_old="";
		$remove_comma=false;
		if($poll['nomination']=='n'){
			$nominations=qa_db_read_all_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and nomination=$ order by uname',$pollid,'y'));
			foreach ($nominations as $options) {
				$remove_comma=true;
				$Noptions_old.=$options["uname"].",";
			}
		}
		if($remove_comma)
			$Noptions_old=substr($Noptions_old, 0, -1);
		
		$content['custom'] = '
			<div class="poll-update-warning">
				<strong>Note:</strong>
				<ul>
					<li>If you update a poll from <b>Nominations</b> to <b>Options</b> (and vice-versa), earlier nominations/options and votes will be deleted from the database.</li>
					<li>If you change whether users can nominate others, earlier nominations and votes will be deleted from the database.</li>
					<li>If you add or delete options (users), earlier options and votes will be deleted from the database.</li>
					<li>If you update the maximum number of nominations, earlier options and votes will be deleted from the database.</li>
					<li>If you update the poll selection type, only the votes will be deleted from the database.</li>
				</ul>
			</div>
		';


		$saved = false;
		$field_errors = [];

		if (qa_post_text('update-poll-page-form')) {
			// Collect inputs
			$updateoption = qa_html(qa_post_text('updateoption'));
			$heading = qa_html(qa_post_text('heading'));
			$description = qa_post_text('description');
			$nomination = qa_post_text('nomination');
			$nopen = qa_post_text('nopen');
			$nclose = qa_post_text('nclose');
			$nminpoints = (int) qa_post_text('nminpoints');
			$nothers = qa_post_text('nothers');
			$notherscount = (int) qa_post_text('notherscount');
			$popen = qa_post_text('popen');
			$pclose = qa_post_text('pclose');
			$pminpoints = (int) qa_post_text('pminpoints');
			$pselection = qa_post_text('pselection');
			$ppoints = (int) qa_post_text('ppoints');
			$votesdisplay = qa_post_text('votesdisplay');
			$options_to_users = isset($_POST['Noptions']) ? $_POST['Noptions'] : "";

			// --- VALIDATIONS ---
			if (empty($heading)) {
				$field_errors['heading'] = 'Title cannot be empty.';
			}

			if (empty($description)) {
				$field_errors['description'] = 'Description cannot be empty.';
			}

			if (!in_array($nomination, ['y', 'n'])) {
				$field_errors['nomination'] = 'Invalid nomination selection.';
			}

			if ($nomination=="y" && (empty($nopen) || !strtotime($nopen))) {
				$field_errors['nopen'] = 'Invalid Nomination Open Date.';
			}

			if ($nomination=="y" && (empty($nclose) || !strtotime($nclose))) {
				$field_errors['nclose'] = 'Invalid Nomination Close Date.';
			}

			if ($nomination=="y" && ($nopen && $nclose && strtotime($nopen) > strtotime($nclose))) {
				$field_errors['nopen'] = 'Nomination Open Date must be before Close Date.';
				$field_errors['nclose'] = 'Nomination Close Date must be after Open Date.';
			}

			if ($nomination=="y" && $nminpoints < 0) {
				$field_errors['nminpoints'] = 'Minimum points must be a positive number.';
			}

			if ($nomination=="y" && (!in_array($nothers, ['y', 'n']))) {
				$field_errors['nothers'] = 'Invalid selection for allowing other nominations.';
			}

			if ($nomination=="y" && $nothers == 'y' && ($notherscount <= 0)) {
				$field_errors['notherscount'] = 'Maximum other nominations must be greater than 0.';
			}

			if (empty($popen) || !strtotime($popen)) {
				$field_errors['popen'] = 'Invalid Polling Open Date.';
			}

			if (empty($pclose) || !strtotime($pclose)) {
				$field_errors['pclose'] = 'Invalid Polling Close Date.';
			}
			if ($nomination=="y" && $popen && $pclose && strtotime($nclose) >= strtotime($popen)) {
				$field_errors['popen'] = 'Polling Open Date must be after completion of Nomination date';
			}
			if ($popen && $pclose && strtotime($popen) > strtotime($pclose)) {
				$field_errors['popen'] = 'Polling Open Date must be before Close Date.';
				$field_errors['pclose'] = 'Polling Close Date must be after Open Date.';
			}

			if ($pminpoints < 0) {
				$field_errors['pminpoints'] = 'Minimum points for voting must be a positive number.';
			}

			if (!in_array($pselection, ['m', 's'])) {
				$field_errors['pselection'] = 'Invalid selection for multiple choice.';
			}

			if (!in_array($votesdisplay, ['y', 'n'])) {
				$field_errors['votesdisplay'] = 'Invalid selection for vote stats visibility.';
			}

			if ($nomination == 'n' && empty($options_to_users)) {
				$field_errors['Noptions'] = 'Options must be provided if nomination is not allowed.';
			}
			// --- VALIDATIONS END ---

			if (!empty($field_errors)) {
				$saved = false;
			}
			else{
				if($updateoption=='d'){
					qa_db_query_sub('delete from ^poll where id=#',$pollid);
					qa_db_query_sub('delete from ^pnomination where pid=#',$pollid);
					qa_db_query_sub('delete from ^pcasting where pid=#',$pollid);
				}
				else if($updateoption=='v'){
					qa_db_query_sub('update ^poll set listing=$ where id=#','y',$pollid);
				}
				else if($updateoption=='h'){
					qa_db_query_sub('update ^poll set listing=$ where id=#','n',$pollid);
				}
				else
				{
					qa_db_query_sub(
						'update ^poll set heading=$, description=$, nomination=$, nopendate=$, nclosedate=$, nminpoints=#, popendate=$, pclosedate=$, pminpoints=#, pselection=$, ppoints=#, votesdisplay=$, listing=$, nothers=$, notherscount=# where id=#',$heading, $description, $nomination,$nopen, $nclose, $nminpoints,$popen, $pclose, $pminpoints,$pselection, $ppoints, $votesdisplay,'y', $nothers,$notherscount, $pollid 
					);
				}

				$options_to_users = isset($_POST['Noptions']) ? $_POST['Noptions'] : "";
				$options_to_usersArray = array_map('trim', explode(',', $options_to_users));
				//Remove the spaces
				$options_to_usersArray=array_values(array_filter($options_to_usersArray, fn($v) => trim($v) !== ''));
				$options_to_usersArray = array_unique($options_to_usersArray);
				sort($options_to_usersArray);
				$Noptions_New = implode(',', $options_to_usersArray);

				if(($poll['nomination']!= $nomination) || ($poll['nomination']=='y' && ($poll['nothers']!= $nothers)) || ($poll['nomination']=='y' && ($poll['notherscount']!= $notherscount)) || ($poll['nomination']=='n' && ($Noptions_New!= $Noptions_old)) ){
					//echo "<script>alert('Nomination made different');</script>";
					qa_db_query_sub(
						'delete from ^pnomination where pid=#',$pollid);
					qa_db_query_sub(
						'delete from ^pcasting where pid=#',$pollid);

					if($nomination=='n'){
						$userid = 1;
						foreach ($options_to_usersArray as $uname) {
							if (!empty($uname)) {
								qa_db_query_sub(
									'INSERT INTO ^pnomination (pid, uid,nid, uname, nomination) VALUES (#, #,#, $, $)',
									$pollid, $userid,$userid, $uname, 'y'
								);
								$userid++;
							}
						}
					}
				}
				if($poll['nomination']=='y' && ($poll['pselection']!= $pselection) ){
					qa_db_query_sub('delete from ^pcasting where pid=#',$pollid);
				}
				$saved=true;
			}

			
        }
		else{
			$heading = $poll['heading'];
			$description = $poll['description'];
			$nomination = $poll['nomination'];
			$nopen = $poll['nopendate'];
			$nclose = $poll['nclosedate'];
			$nminpoints = (int) $poll['nminpoints'];
			$nothers = $poll['nothers'];
			$notherscount = (int) $poll['notherscount'];
			$popen = $poll['popendate'];
			$pclose = $poll['pclosedate'];
			$pminpoints = (int) $poll['pminpoints'];
			$pselection = $poll['pselection'];
			//$ppoints = (int) qa_post_text('ppoints');
			$votesdisplay = $poll['votesdisplay'];
			$options_to_users = $Noptions_old ;	
		}
		

		$fields = [
			[
				'label' => 'Choose One Action:',
				'type' => 'custom',
				'html' => '<label><input type="radio" name="updateoption" value="u" checked> Update</label>
						   <label><input type="radio" name="updateoption" value="' . (($poll['listing'] == 'n') ? 'v' : 'h') . '">' . (($poll['listing'] == 'n') ? 'Show in Listing' : 'Hide from Listing') . '</label>
						   <label><input type="radio" name="updateoption" value="d"> Delete</label>',
				'tags' => 'NAME="updateoption"',
			],
			[
				'label' => 'Title of the Poll',
				'type' => 'custom',
				'html' => '<label><input type="text" NAME="heading" value="' . htmlspecialchars($heading) . '"></label>',
				'error' => isset($field_errors['heading']) ? $field_errors['heading'] : null,
			],
			[
				'label' => 'Description',
				'type' => 'custom',
				'html' => '<label><input type="text" NAME="description" value="' . htmlspecialchars($description) . '"></label>',
				'error' => isset($field_errors['description']) ? $field_errors['description'] : null,
			],
			[
				'label' => 'Allow nomination from Users?',
				'type' => 'custom',
				'html' => '<label><input type="radio" name="nomination" id="nomination_yes" value="y" ' . (($nomination == 'y') ? 'checked' : '') . '> Yes</label>
						   <label><input type="radio" name="nomination" id="nomination_no" value="n" ' . (($nomination == 'n') ? 'checked' : '') . '> No</label>',
				'error' => isset($field_errors['nomination']) ? $field_errors['nomination'] : null,
			],
			[
				'label' => 'Nomination Open Date',
				'type' => 'custom',
				'html' => '<label><input type="date" NAME="nopen" value="' . $nopen . '"></label>',
				'error' => isset($field_errors['nopen']) ? $field_errors['nopen'] : null,
			],
			[
				'label' => 'Nomination Close Date',
				'type' => 'custom',
				'html' => '<label><input type="date" NAME="nclose" value="' . $nclose . '"></label>',
				'error' => isset($field_errors['nclose']) ? $field_errors['nclose'] : null,
			],
			[
				'label' => 'Options for users (comma separated)',
				'type' => 'custom',
				'html' => '<label><input type="text" NAME="Noptions" value="' . htmlspecialchars($options_to_users) . '"></label>',
				'error' => isset($field_errors['Noptions']) ? $field_errors['Noptions'] : null,
			],
			[
				'label' => 'Minimum Points for Nominations',
				'type' => 'number',
				'tags' => 'NAME="nminpoints"',
				'value' => $nminpoints,
				'error' => isset($field_errors['nminpoints']) ? $field_errors['nminpoints'] : null,
			],
			[
				'label' => 'Allow users to nominate other users?',
				'type' => 'custom',
				'html' => '<label><input type="radio" name="nothers" id="nothers_yes" value="y" ' . (($nothers == 'y') ? 'checked' : '') . '> Yes</label>
						   <label><input type="radio" name="nothers" id="nothers_no" value="n" ' . (($nothers == 'n') ? 'checked' : '') . '> No</label>',
				'error' => isset($field_errors['nothers']) ? $field_errors['nothers'] : null,
			],
			[
				'label' => 'Maximum other nominations by a user',
				'type' => 'number',
				'tags' => 'NAME="notherscount"',
				'value' => $notherscount,
				'error' => isset($field_errors['notherscount']) ? $field_errors['notherscount'] : null,
			],
			[
				'label' => 'Polling Open Date',
				'type' => 'custom',
				'html' => '<label><input type="date" NAME="popen" value="' . $popen . '"></label>',
				'error' => isset($field_errors['popen']) ? $field_errors['popen'] : null,
			],
			[
				'label' => 'Polling Close Date',
				'type' => 'custom',
				'html' => '<label><input type="date" NAME="pclose" value="' .$pclose. '"></label>',
				'error' => isset($field_errors['pclose']) ? $field_errors['pclose'] : null,
			],
			[
				'label' => 'Minimum Points for Casting Votes',
				'type' => 'number',
				'tags' => 'NAME="pminpoints"',
				'value' => $pminpoints,
				'error' => isset($field_errors['pminpoints']) ? $field_errors['pminpoints'] : null,
			],
			[
				'label' => 'Allow Multiple Selection?',
				'type' => 'custom',
				'html' => '<label><input type="radio" name="pselection" value="m" ' . (($pselection == 'm') ? 'checked' : '') . '> Yes</label>
						   <label><input type="radio" name="pselection" value="s" ' . (($pselection == 's') ? 'checked' : '') . '> No</label>',
				'error' => isset($field_errors['pselection']) ? $field_errors['pselection'] : null,
			],
			[
				'label' => 'Display Voting stats to users while polling?',
				'type' => 'custom',
				'html' => '<label><input type="radio" name="votesdisplay" value="y" ' . (($votesdisplay == 'y') ? 'checked' : '') . '> Yes</label>
						   <label><input type="radio" name="votesdisplay" value="n" ' . (($votesdisplay == 'n') ? 'checked' : '') . '> No</label>',
				'error' => isset($field_errors['votesdisplay']) ? $field_errors['votesdisplay'] : null,
			],
		];


        $content['form'] = [
			'style' => 'wide',
            'tags' => 'method="post" action="' . qa_path_html('update-poll',["poll" => $poll["id"]]) . '"',
            'fields' => $fields,
            'buttons' => [
                ['label' => 'Submit', 'tags' => 'name="update-poll-page-form" onclick="return confirm(\'Are you sure you want to save?\')"']
            ],
        ];

        if ($saved) {
			$content['form']['ok'] = 'Poll Updated successfully!';
			//qa_redirect('update-poll', ['poll' => $pollid]);
			if($updateoption=='d')
				qa_redirect('list-polls', ['deleted' => '1']);
        }
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
					if (name=="nothers" &&fieldMap[name]) {
						if (document.getElementById("nothers_yes").checked) {
							toggleFields(name, "y");
						} else if (document.getElementById("nothers_no").checked) {
							toggleFields(name, "n");
						}
					}
					if (name=="nomination" &&fieldMap[name]) {
						if (document.getElementById("nomination_yes").checked) {
							toggleFields(name, "y");
						} else if (document.getElementById("nomination_no").checked) {
							toggleFields(name, "n");
						}
					}
				});
			}

			// Define field toggle behavior per radio group and value
			const fieldMap = {

				updateoption: {
					u: {
						show: ["heading","description","nomination","popen", "pclose", "pminpoints","pselection","votesdisplay"]
					},
					v: {
						hide: ["heading","description","nomination","nopen", "nclose", "nminpoints","nothers","notherscount","Noptions","popen", "pclose", "pminpoints","pselection","votesdisplay"]
					},
					h: {
						hide: ["heading","description","nomination","nopen", "nclose", "nminpoints","nothers","notherscount","Noptions","popen", "pclose", "pminpoints","pselection","votesdisplay"]
					},
					d: {
						hide: ["heading","description","nomination","nopen", "nclose", "nminpoints","nothers","notherscount","Noptions","popen", "pclose", "pminpoints","pselection","votesdisplay"]
					}
				},
				nothers: {
					y: {
						show: ["notherscount"]
					},
					n: {
						hide: ["notherscount"]
					}
				},
				nomination: {
					y: {
						show: ["nopen", "nclose", "nminpoints","nothers"],
						hide: ["Noptions"]
					},
					n: {
						show: ["Noptions"],
						hide: ["nopen", "nclose", "nminpoints","nothers","notherscount"]
					}
				}
				
				

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
        </script>
    ';


		$content['title'] = 'Update "'.$poll['heading'].'" Poll';
        return $content;
    }
	

}