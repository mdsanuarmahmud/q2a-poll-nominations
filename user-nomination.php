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
		
		$nominations=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and uid=#',$pollid,$userid),true);
		$n=0;
		($nominations==Null)?$n=0:(($nominations['nomination']=='s')?$n=1:(($nominations['nomination']=='n')?$n=2:$n=3));

		if($poll==Null)
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


		$content['custom'] = '<div><p>Description of the poll: '.$poll['Description'].'</p><p>Last Date for Submission of Nomination: '.$poll['NcloseDate'].'</p><p>Opening Date of Poll: '.$poll['PopenDate'].'</p><p>Closing Date of Poll: '.$poll['PcloseDate'].'</p><p>Username: '.$handle.'</p><p>Nomination Status: '.(($n==0)?"Yet to submit nomination":(($n==1)?"Nomination submitted":(($n==2)?"Not interested in Nomination":"Nomination Withdrawn"))).'</p><p>'."Stats of this poll:<a href='" .qa_path_html("poll-stats", ["poll" => $poll["id"]])."'> click here </a> ".'</p></div>';
		
		$saved = false;
		if (qa_post_text('nomination-page-form')) {
			// Collect inputs
			$participation = qa_html(qa_post_text('participation'));
			$uname = qa_html(qa_post_text('fname'));

			if($n==0)
			qa_db_query_sub(
				'INSERT INTO ^pnomination(pid, uid, uname, nomination) VALUES (#, #, $, $)',$pollid,$userid, $uname, $participation
			);
			else
				qa_db_query_sub(
				'UPDATE ^pnomination SET nomination=$ where pid = # and uid =#',$participation, $pollid,$userid
			);
			

			$saved = true;
		}
        // Form fields
        $fields = [
            
            ['label' => ($n==0?'Want to list your name in the Poll ?':'Want to withdraw your nomination ?'), 'type' => 'custom',
			'html' => '<label><input type="radio" name="participation" value="'.($n==0?'s':'w').'" checked> yes</label><label><input type="radio" name="participation" value="'.($n==0?'n':'s').'"> No</label>', 'tags' => 'NAME="participation"'],
            ['label' => 'Name to be displayed in the poll', 'type'=> ($n==0?'text':'static'), 'tags' => 'NAME="fname"', 'value'=>($n==0?$name:$nominations['uname'])]
        ];

		if($n<2){
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
        return $content;
    }
}