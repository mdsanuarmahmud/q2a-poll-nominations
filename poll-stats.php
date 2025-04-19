<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}
class poll_stats
{
    function load_module($directory, $urltoroot)
    {
        $this->directory = $directory;
        $this->urltoroot = $urltoroot;
    }

    function match_request($request)
    {
        return ($request === 'poll-stats');
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
		$userid = qa_get_logged_in_userid();

		$poll=qa_db_read_one_assoc(qa_db_query_sub('SELECT * FROM ^poll where id=$',$pollid),true);
		if($poll==Null)
		{
			$content['error'] = "No such Poll exist. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the polls";
            return $content;
		}
		$polltype=($poll['Pselection']=='s')?0:1;
		$nominations=qa_db_read_all_assoc(qa_db_query_sub('SELECT * FROM ^pnomination where pid=# and nomination=$',$pollid,'s'));
		
		$nominationcount=qa_db_read_all_assoc(qa_db_query_sub('SELECT count(*) as count FROM ^pnomination where pid=# and nomination=$',$pollid,'s'));
		
		$castings=qa_db_read_all_assoc(qa_db_query_sub('SELECT nid, sum(points) as votes FROM ^pcasting where pid=# group by nid',$pollid));
		
		$castingscount=qa_db_read_all_assoc(qa_db_query_sub('SELECT count( distinct(uid)) as count FROM ^pcasting where pid=#',$pollid));

		$n=0;


		$content['custom'] = '<div><p>Description of the poll: '.$poll['Description'].'</p><p>Opening Date of Poll: '.$poll['PopenDate'].'</p><p>Closing Date of Poll: '.$poll['PcloseDate'].'</p><p>Polling type: '.(($polltype==0)?'Single Selection':'Multi Selection (use ctrl)').'</p><p>Total Nominations Received: '.$nominationcount[0]['count'].'</p><p>Total Users Voted: '.$castingscount[0]['count'].'</p></div>';

		$pollnomination=$poll['Nomination'];

		$html = "<hr><div id='nomination-stats'><h1>".		
		(($pollnomination=='y')?"Nominations Summary:":"Options provided to users:")."</h1><table style='border:1px solid red;'><tr>".(($pollnomination=='y')?"<th>User ID</th><th>Name</th>":"<th>Option</th>")."</tr>";

		foreach ($nominations as $nomination) {
			if($pollnomination=='y'){
				$html .= "<tr><td>".qa_userid_to_handle((int)($nomination['uid']))."</td><td>".$nomination['uname']."</td></tr>";
			}
			else
				$html .= "<td>".$nomination['uname']."</td></tr>";
		}
		$html .= "</table></div>";

		if((qa_get_logged_in_level()>=QA_USER_LEVEL_ADMIN)||($poll['VotesDisplay']=='y')||($poll['PcloseDate']<date('Y-m-d')))
		{
			$html .= "<hr><div id='polling-stats'><h1>Polling Summary:</h1><table style='border:1px solid red;'><tr><th>".(($pollnomination=='y')?"User ID":"Option")."</th><th>No.of Votes</th></tr>";

			foreach ($castings as $casting) {
				$option_name=qa_db_read_one_assoc(qa_db_query_sub('SELECT uname FROM ^pnomination where pid=# and uid=#',$pollid,(int)($casting['nid'])),true);
				//var_dump($option_name);
				//var_dump((int)$casting['nid']);
				if($pollnomination=='y'){
					$html .= "<tr><td>".qa_userid_to_handle((int)($casting['nid']))."</td><td>".$casting['votes']."</td></tr>";
				}
				else
					$html .= "<tr><td>".$option_name["uname"]."</td><td>".$casting['votes']."</td></tr>";
			}

			$html .= "</table></div>";
			
		}
		else
			$html .= "<hr><div id='polling-stats'><h2>Polling Summary will be displayed after completion of polling</h2></div>";

		$content['custom'] .= $html;
		$content['title'] = 'Stats of '.$poll['Heading'].' poll';		
        return $content;
    }
}