<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}
class casting
{
    function load_module($directory, $urltoroot)
    {
        $this->directory = $directory;
        $this->urltoroot = $urltoroot;
    }
	function match_request($request)
    {
        return ($request === 'casting');
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
		$pollid=qa_get("poll");
		if(!(isset($pollid)))
		{
			$content['error'] = "Poll ID missing in the URL. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the polls";
            return $content;
		}		
		$userpoints=qa_get_logged_in_points();
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
		$polltype=($poll['Pselection']=='s')?0:1;

		if($poll['PcloseDate']<date('Y-m-d'))
		{
			$content['error'] = "Polling is closed for this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for polling.";
            return $content;			
		}
		if(date('Y-m-d')<$poll['PopenDate'])
		{
			$content['error'] = "Polling is not open for this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for polling.";
            return $content;			
		}

		if($userpoints<$poll['PMinPoints'])
		{
			$content['error'] = "You are not eligible for casting your vote in this poll. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for polling.";
            return $content;			
		}
		
		$userid = qa_get_logged_in_userid();
		
		$nominations=qa_db_read_all_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and nomination=$',$pollid,'y'));
				
		$castings=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^pcasting where pid=$ and uid=$',$pollid,$userid),true);
		$n=(($castings==Null)?0:1);

		$content['custom'] = '<div><p>Description of the poll: '.$poll['Description'].'</p><p>Closing Date of Poll: '.$poll['PcloseDate'].'</p><p>Polling type: '.(($polltype==0)?'Single Selection':'Multi Selection (use ctrl)').'</p><p>Casting Status: '.(($n==0)?"Yet to Caste Vote":"Vote Casted").'</p><p>'."Stats of this poll:<a href='" .qa_path_html("poll-stats", ["poll" => $poll["id"]])."'> click here </a> ".'</p></div>';
		
		$saved = false;
		if (qa_post_text('polling-page-form')) {
			// Collect inputs
			$points = 1;
			$selected = isset($_POST['nid']) ? $_POST['nid'] : [];	
			if($n==0){
				foreach ($selected as $id) {
					qa_db_query_sub('INSERT INTO ^pcasting(pid, uid, nid, points) VALUES (#, #, $, $)',$pollid,$userid, (int)$id, $points);
				}
			}			
			/* else
				qa_db_query_sub('UPDATE ^pnomination SET nomination=$ where pid = # and uid =#',$participation, $pollid,$userid); */

			$saved = true;
		}
		$options = [];
		$pollnomination=$poll['Nomination'];
		if($pollnomination=='y'){
			foreach ($nominations as $nomination) {
				$options[$nomination['nid']] = $nomination['uname']." (userid: ".qa_userid_to_handle($nomination['nid']).")";
			}
		}
		else{
			foreach ($nominations as $nomination) {
				$options[$nomination['uid']] = $nomination['uname'];
			}
		}
        // Form fields
        $fields = [
            
            ['label' => 'I would like to cast my vote to: ', 
			'type' => 'select',
			'options' => $options,
			'tags' => 'name="nid[]" '. (($polltype==0)?'':'multiple size="5"')]
        ];

		if($n<1){
			$content['form'] = [
				'style' => 'wide',
				'tags' => 'method="post" action="' . qa_path_html(qa_request(), ['poll' => $pollid]) . '"',
				'fields' => $fields,
				'buttons' => [
					['label' => 'Submit', 'tags' => 'name="polling-page-form" onclick="return confirm(\'Are you sure you want to save?\')"', ]
				]];
		}
        if ($saved) {
			qa_redirect(qa_request(), ['poll' => $pollid]);
        } 	
		$content['title'] = 'Caste your vote in '.$poll['Heading'].' poll';		
        return $content;
    }
}