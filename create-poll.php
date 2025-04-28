<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}
class create_poll
{
    function load_module($directory, $urltoroot)
    {
        $this->directory = $directory;
        $this->urltoroot = $urltoroot;
    }

    function suggest_requests()
    {
		if (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) {
			return array(
				array(
					'label' => 'Admin Poll Page',
					'request' => 'create-poll',
					'nav' => 'footer',
				),
			);
		}
    }

    function match_request($request)
    {
        return ($request === 'create-poll');
    }
	
	public static function init_queries($tablesexisting)
    {
		require_once QA_INCLUDE_DIR."db/selects.php";
        if (!in_array(qa_db_add_table_prefix('poll'), $tablesexisting)) {
            return
                'CREATE TABLE IF NOT EXISTS ^poll (
                    id INT AUTO_INCREMENT PRIMARY KEY,
					heading VARCHAR(3000) NOT NULL,
					description VARCHAR(3000) NOT NULL,
					nomination CHAR NOT NULL,
					nopendate DATE, 
					nclosedate DATE, 
					nminpoints INT NOT NULL,
					nothers CHAR NOT NULL,
					notherscount INT NOT NULL,
					popendate DATE,
					pclosedate DATE, 
					pminpoints INT NOT NULL,
					pselection CHAR NOT NULL,
					ppoints INT NOT NULL,
					votesdisplay CHAR NOT NULL,
					listing CHAR NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
        }
		if (!in_array(qa_db_add_table_prefix('pnomination'), $tablesexisting)) {
            return
                'CREATE TABLE IF NOT EXISTS ^pnomination (
                    id INT AUTO_INCREMENT PRIMARY KEY,
					pid INT NOT NULL,
					uid INT NOT NULL,
					nid INT NOT NULL,
					uname VARCHAR(300) NOT NULL,
					nomination CHAR NOT NULL					
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
        }
		if (!in_array(qa_db_add_table_prefix('pcasting'), $tablesexisting)) {
            return
                'CREATE TABLE IF NOT EXISTS ^pcasting (
                    id INT AUTO_INCREMENT PRIMARY KEY,
					pid INT NOT NULL,
					uid INT NOT NULL,
					nid INT NOT NULL,
					points INT NOT NULL		
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
        }
		return null;
    }

    function process_request($request)
    {
		if (!qa_is_logged_in()) {
			$_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
			qa_redirect('login');
		}
        $content = qa_content_prepare();
		$allowed_user=((qa_get_logged_in_level()<QA_USER_LEVEL_ADMIN)?0:1);		
		if(!$allowed_user)
		{
			$content['error'] = 'You must be Admin or above to access this page.';
            return $content;			
		}
		require_once QA_INCLUDE_DIR . 'db/metas.php';
		require_once QA_INCLUDE_DIR . 'qa-app-users.php';
		require_once QA_INCLUDE_DIR . 'qa-db.php';
		$userid = qa_get_logged_in_userid();
		$level=qa_get_logged_in_level();
		$content['title'] = 'Create a Poll';
        
		$saved = false;
		$field_errors = [];
		$today = date('Y-m-d');

        if (qa_post_text('create-poll-page-form')) {
            // Collect inputs
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
			$options_to_users = qa_post_text('Noptions') ? qa_post_text('Noptions') : "";

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
				if($nomination=='y'){
					qa_db_query_sub(
					'INSERT INTO ^poll(heading, description, nomination, nopendate, nclosedate, nminpoints, nothers, notherscount,popendate, pclosedate, pminpoints, pselection, ppoints, votesdisplay,listing) VALUES ($, $, $, $, $, #,$,#, $, $, #, $, #, $,$)',$heading, $description, $nomination,$nopen, $nclose, $nminpoints,$nothers, $notherscount,$popen, $pclose, $pminpoints,$pselection, $ppoints, $votesdisplay,'y'
					);
				}
				else
				{
					qa_db_query_sub(
					'INSERT INTO ^poll(heading, description, nomination, nopendate, nclosedate, nminpoints, nothers, notherscount,popendate, pclosedate, pminpoints, pselection, ppoints, votesdisplay,listing) VALUES ($, $, $, $, $, #,$,#, $, $, #, $, #, $,$)',$heading, $description, $nomination,$nopen, $nclose, $nminpoints,$nothers, $notherscount,$popen, $pclose, $pminpoints,$pselection, $ppoints, $votesdisplay,'y'
					);				
					$pollid = qa_db_last_insert_id();
					
					
					$options_to_usersArray = array_map('trim', explode(',', $options_to_users));
					//Remove the spaces
					$options_to_usersArray=array_values(array_filter($options_to_usersArray, fn($v) => trim($v) !== ''));
					$options_to_usersArray = array_unique($options_to_usersArray);
					sort($options_to_usersArray);
					
					$userid = 1;
					foreach ($options_to_usersArray as $uname) {
						if (!empty($uname)) {
							qa_db_query_sub(
								'INSERT INTO ^pnomination (pid, uid, nid, uname, nomination) VALUES (#, #, #,$, $)',
								$pollid, $userid, $userid,$uname, 'y'
							);
							$userid++;
						}
					}
				}
				$saved = true;
			}
		}
		else{
			
			$heading = "";
			$description = "";
			$nomination = "y";
			$nopen = $today;
			$nclose = date('Y-m-d', strtotime($today . ' +1 day'));
			$nminpoints = 100;
			$nothers = "n";
			$notherscount = 2;
			$popen = date('Y-m-d', strtotime($today . ' +2 day'));
			$pclose = date('Y-m-d', strtotime($today . ' +3 day'));
			$pminpoints = 100;
			$pselection = "s";
			//$ppoints = (int) qa_post_text('ppoints');
			$votesdisplay = "n";
			$options_to_users = "";
		}

        // Form fields
        $fields = [
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
            'tags' => 'method="post" action="' . qa_path_html('create-poll') . '"',
            'fields' => $fields,
            'buttons' => [
                ['label' => 'Submit', 'tags' => 'name="create-poll-page-form"']
            ],
        ];

        if ($saved) {
            $content['form']['ok'] = 'Poll Created successfully!';
			qa_redirect('list-polls', ['created' => '1']);
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



        return $content;
    }
	

}
