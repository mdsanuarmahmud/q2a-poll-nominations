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
		date_default_timezone_set('Asia/Kolkata');
		require_once QA_INCLUDE_DIR . 'db/selects.php';
        require_once QA_INCLUDE_DIR . 'qa-db.php';
		require_once QA_INCLUDE_DIR . 'db/metas.php';
		require_once QA_INCLUDE_DIR . 'qa-app-users.php';
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
		$allowed_user=((qa_get_logged_in_level()<QA_USER_LEVEL_ADMIN)?0:1);
		if((!$allowed_user)&& ($poll['listing']=='n'))
		{
			$content['error'] = "No such poll available. Please <a href='" .qa_path_html("list-polls")."'> click here </a> to know the available polls for nomination.";
            return $content;
		}
		$polltype=($poll['pselection']=='s')?0:1;
		$nominations=qa_db_read_all_assoc(qa_db_query_sub('SELECT distinct nid, uname FROM qa_pnomination where pid=# and nomination=$ and nid not in (select nid from qa_pnomination where pid=# and (nomination=$ or nomination=$))',$pollid,'y',$pollid,'w','n'));
		
		
		$nominationcount=qa_db_read_all_assoc(qa_db_query_sub('SELECT count(distinct nid) as count FROM qa_pnomination where pid=# and nomination=$ and nid not in (select nid from qa_pnomination where pid=# and (nomination=$ or nomination=$))',$pollid,'y',$pollid,'w','n'));
		
		
		
		//SELECT count(distinct nid) as count FROM qa_pnomination where pid=3 and nomination='y' and nid not in (select nid from qa_pnomination where pid=3 and (nomination='w' or nomination='n'));
		
		$castings=qa_db_read_all_assoc(qa_db_query_sub('SELECT nid, sum(points) as votes FROM ^pcasting where pid=# group by nid',$pollid));
		
		$castingscount=qa_db_read_all_assoc(qa_db_query_sub('SELECT count( distinct(uid)) as count FROM ^pcasting where pid=#',$pollid));

		$n=0;

		$pollnomination=$poll['nomination'];
		$content['custom'] = '<div><p>Description of the poll: '.$poll['description'].'</p><p>Opening Date of Poll: '.$poll['popendate'].'</p><p>Closing Date of Poll: '.$poll['pclosedate'].'</p><p>Polling type: '.(($polltype==0)?'Single Selection':'Multi Selection (use ctrl)').'</p><p>'.(($pollnomination=='y')?"Total Nominations Received: ":"Total options provided to users: ")." ".$nominationcount[0]['count'].'</p><p>Total Users Voted: '.$castingscount[0]['count'].'</p></div>';

		

		$html = "<hr><div id='nomination-stats'><h1>".		
		(($pollnomination=='y')?"Nominations Summary:":"Options provided to users:")."</h1><table id='nomination-table'><thead><tr>".(($pollnomination=='y')?"<th>User ID</th><th>Name</th>":"<th>Option</th>")."</tr></thead><tbody>";

		foreach ($nominations as $nomination) {
			if($pollnomination=='y'){
				$html .= "<tr><td>".qa_userid_to_handle((int)($nomination['nid']))."</td><td>".$nomination['uname']."</td></tr>";
			}
			else
				$html .= "<td>".$nomination['uname']."</td></tr>";
		}
		$html .= "</tbody></table></div>";

		if((qa_get_logged_in_level()>=QA_USER_LEVEL_ADMIN)||($poll['votesdisplay']=='y')||($poll['pclosedate']<date('Y-m-d')))
		{
			$html .= "<hr><div id='polling-stats'><h1>Polling Summary:</h1><table id='polling-table'><thead><tr><th>".(($pollnomination=='y')?"User ID":"Option")."</th><th>No.of Votes</th></tr></thead><tbody>";

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

			$html .= "</tbody></table></div>";
			
		}
		else
			$html .= "<hr><div id='polling-stats'><h2>Polling Summary will be displayed after completion of polling</h2></div>";

		$content['custom'] .= $html;
		$content['title'] = 'Stats of '.$poll['heading'].' poll';		
		$plugin_url = qa_path_to_root() . 'qa-plugin/' . basename(dirname(__FILE__)) . '/';

		$content['head_lines'][] =
    '<link rel="stylesheet" href="'.$plugin_url.'js/jquery.dataTables.min.css">';
	
		$content['script'][] = '<script src="' . $plugin_url . 'js/jquery-3.5.1.min.js"></script><script src="' . $plugin_url . 'js/jquery.dataTables.min.js"></script><script type="text/javascript">
			var myJQ = jQuery.noConflict(true);
			myJQ(document).ready(function() {
				myJQ(\'#nomination-table\').DataTable({
					"pageLength": 5,
					"lengthMenu": [ [5, 10, 25, 50, -1], [5, 10, 25, 50, "All"] ]
				});
				myJQ(\'#polling-table\').DataTable({
					"pageLength": 5,
					"lengthMenu": [ [5, 10, 25, 50, -1], [5, 10, 25, 50, "All"] ]
				});
			});
			</script>';
        return $content;
    }
}