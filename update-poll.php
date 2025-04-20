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
			return qa_redirect('login');
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
		$content['title'] = 'Update a Poll';
 		$poll=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^poll where id=#',$pollid),true);
		if($poll==Null)
		{
			$content['error'] = "There is no such poll available. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}       
		$saved = false;

        if (qa_post_text('update-poll-page-form')) {
            // Collect inputs
			$updateoption=qa_html(qa_post_text('updateoption'));
			$heading = qa_html(qa_post_text('heading'));
			$description = qa_post_text('description');
			$nomination = qa_post_text('nomination');
			$nopen = qa_post_text('nopen');
			$nclose = qa_post_text('nclose');
			$nminpoints = (int) qa_post_text('nminpoints');
			$nothers = qa_post_text('Nothers');
			$notherscount = (int) qa_post_text('Notherscount');		
			$popen = qa_post_text('popen');
			$pclose = qa_post_text('pclose');
			$pminpoints = (int) qa_post_text('pminpoints');
			$pselection = qa_post_text('pselection');
			$ppoints = (int) qa_post_text('ppoints');
			$votesdisplay = qa_post_text('votesdisplay');

			if($updateoption=='d'){
				qa_db_query_sub('delete from ^poll where id=#',$pollid);
				qa_db_query_sub('delete from ^pnomination where pid=#',$pollid);
				qa_db_query_sub('delete from ^pcasting where pid=#',$pollid);
				echo '<script>alert("Poll deleted successfully!");</script>';

			}
			else if($updateoption=='v'){
				qa_db_query_sub('update ^poll set Listing=$ where id=#','y',$pollid);
			}
			else if($updateoption=='h'){
				qa_db_query_sub('update ^poll set Listing=$ where id=#','n',$pollid);
			}
            else if($nomination=='y'){
				qa_db_query_sub(
					'update ^poll set Heading=$, Description=$, Nomination=$, NopenDate=$, NcloseDate=$, NMinPoints=#, PopenDate=$, PcloseDate=$, PMinPoints=#, Pselection=$, Ppoints=#, VotesDisplay=$, Nothers=$, Notherscount=# where id=#',$heading, $description, $nomination,$nopen, $nclose, $nminpoints,$popen, $pclose, $pminpoints,$pselection, $ppoints, $votesdisplay,$nothers,$notherscount, $pollid 
				);
				
				if($poll['Nomination']=='n'){
					qa_db_query_sub(
						'delete from ^pnomination where pid=#',$pollid);
				}
			}
			else
			{
				qa_db_query_sub(
                'update ^poll set Heading=$, Description=$, Nomination=$, NopenDate=$, NcloseDate=$, NMinPoints=#, PopenDate=$, PcloseDate=$, PMinPoints=#, Pselection=$, Ppoints=#, VotesDisplay=$ where id=#',$heading, $description, $nomination,$nopen, $nclose, $nminpoints,$popen, $pclose, $pminpoints,$pselection, $ppoints, $votesdisplay,$pollid 
				);
			if($poll['Nomination']=='n' || $poll['Nomination']=='y'){
				qa_db_query_sub(
					'delete from ^pnomination where pid=#',$pollid);
			}				
				$unames = qa_post_text('Noptions');
				$unameArray = array_map('trim', explode(',', $unames)); 
				$userid = 1;
				foreach ($unameArray as $uname) {
					if (!empty($uname)) {
						qa_db_query_sub(
							'INSERT INTO ^pnomination (pid, uid, uname, nomination) VALUES (#, #, $, $)',
							$pollid, $userid, $uname, 's'
						);
						$userid++;
					}
				}
			}
            $saved = true;
			
        }
		$today = date('Y-m-d');
        // Form fields
		
		$poll=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^poll where id=#',$pollid),true);
		$Noptions="";
		$remove_comma=false;
		if($poll['Nomination']=='n'){
			$nominations=qa_db_read_all_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and nomination=$',$pollid,'s'));
			foreach ($nominations as $options) {
				$remove_comma=true;
				$Noptions.=$options["uname"].",";
			}
		}
		if($remove_comma)
		{
			$Noptions=substr($Noptions, 0, -1);
		}
		
        $fields = [
			['label' => 'Choose One Action:', 'type' => 'custom','html' => '<label><input type="radio" name="updateoption" value="u" checked> Update</label>
			<label><input type="radio" name="updateoption" value='.(($poll['Listing']=='n')?'"v" >Show in the Listing':'"h" >Hide from Listing').'</label><label><input type="radio" name="updateoption" value="d">Delete</label>', 'tags' => 'NAME="updateoption"'],
            ['label' => 'Title of the Poll', 'type' => 'text', 'tags' => 'NAME="heading"', 'value'=>$poll['Heading']],
            ['label' => 'Description', 'type' => 'text', 'tags' => 'NAME="description"', 'value'=>$poll['Description']],
            ['label' => 'Allow nomination from Users ?', 'type' => 'custom','html' => '<label><input type="radio" name="nomination" value="y" '.(($poll['Nomination']=='y')?"checked":"").'> Yes</label>
        <label><input type="radio" name="nomination" value="n" '.(($poll['Nomination']=='n')?"checked":"").'>NO</label>', 'tags' => 'NAME="nomination" id="NOMIN"'],
            ['label' => 'Nomination Open Date', 'type' => 'date', 'tags' => 'NAME="nopen" id="NOP"','value' =>$poll['NopenDate'] ],
            ['label' => 'Nomination Close Date', 'type' => 'date', 'tags' => 'NAME="nclose" id="NOC"', 'value' =>$poll['NcloseDate'] ],
			['label' => 'Options for users (separated by commas)', 'type' => 'text', 'tags' => 'NAME="Noptions" id="Noptions"', 'value'=>$Noptions],
            ['label' => 'Minimum Points for Nominations', 'type' => 'number', 'tags' => 'NAME="nminpoints"', 'value' =>$poll['NMinPoints']],
			['label' => 'Allow users to nominate other users?', 'type' => 'custom','html' => '<label><input type="radio" name="Nothers" value="y" '.(($poll['Nothers']=='y')?"checked":"").'> Yes</label><label><input type="radio" name="Nothers" value="n" '.(($poll['Nothers']=='n')?"checked":"").'>NO</label>', 'tags' => 'NAME="Nothers"'],
		    ['label' => 'Maximum other nominations by a user ', 'type' => 'number', 'tags' => 'NAME="Notherscount"',  'value' =>$poll['Notherscount']],

            ['label' => 'Polling Open Date', 'type' => 'date', 'tags' => 'NAME="popen"', 'value' =>$poll['PopenDate']],
            ['label' => 'Polling Close Date', 'type' => 'date', 'tags' => 'NAME="pclose"','value' =>$poll['PcloseDate']],
            ['label' => 'Minimum Points for Casting Votes', 'type' => 'number', 'tags' => 'NAME="pminpoints"', 'value' =>$poll['PMinPoints']],
            ['label' => 'Allow Multiple Selection ?', 'type' => 'custom','html' => '<label><input type="radio" name="pselection" value="m" '.(($poll['Pselection']=='m')?"checked":"").'> Yes</label>
        <label><input type="radio" name="pselection" value="s" '.(($poll['Pselection']=='s')?"checked":"").'>NO</label>', 'tags' => 'NAME="pselection"'],
            //['label' => 'Total Points given to Users', 'type' => 'number', 'tags' => 'NAME="ppoints"', 'value' =>1],
            ['label' => 'Display Voting stats to users while polling?', 'type' => 'custom','html' => '<label><input type="radio" name="votesdisplay" value="y" '.(($poll['VotesDisplay']=='y')?"checked":"").'> Yes</label>
        <label><input type="radio" name="votesdisplay" value="n" '.(($poll['VotesDisplay']=='n')?"checked":"").'>NO</label>', 'tags' => 'NAME="votesdisplay"']
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
				});
			}

			// Define field toggle behavior per radio group and value
			const fieldMap = {
				nomination: {
					y: {
						show: ["nopen", "nclose", "nminpoints","Nothers"],
						hide: ["Noptions"]
					},
					n: {
						show: ["Noptions"],
						hide: ["nopen", "nclose", "nminpoints","Nothers"]
					}
				},
				
				Nothers: {
					y: {
						show: ["Notherscount"]
					},
					n: {
						hide: ["Notherscount"]
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