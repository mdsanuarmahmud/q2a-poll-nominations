<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}
class list_polls
{
    function load_module($directory, $urltoroot)
    {
        $this->directory = $directory;
        $this->urltoroot = $urltoroot;
    }

    function suggest_requests()
    {
        return array(
            array(
				'label' => 'Polls Page',
                'request' => 'list-polls',
                'nav' => 'footer',
            ),
        );
    }

    function match_request($request)
    {
        return ($request === 'list-polls');
    }
	function process_request($request)
    {
		
		require_once QA_INCLUDE_DIR . 'db/selects.php';
        require_once QA_INCLUDE_DIR . 'qa-db.php';
		$content = qa_content_prepare();
		
		if (qa_get('created') == '1') {
			echo '<script>alert("Poll created successfully!");</script>';
		}
		if (qa_get('deleted') == '1') {
			echo '<script>alert("Poll deleted successfully!");</script>';
		}
		$allowed_user=((qa_get_logged_in_level()<QA_USER_LEVEL_ADMIN)?0:1);
		$html='';
		if($allowed_user)
			$html.='<a href="'.qa_path_html("create-poll").'"><h1>Click here to create a new poll.</h1></a><div style="height:30px;"></div>';
		$result=qa_db_query_sub('SELECT * FROM ^poll where nomination = "y" and nopendate<=CURDATE() and nclosedate >=CURDATE() and listing=$','y');		
		$polls_nomination=[];
		$i=0;	
		foreach ($result as $poll) {
			$poll_info=[];
			$poll_info['heading']=$poll['heading'];
			$poll_info['nopendate']=$poll['nopendate'];
			$poll_info['nclosedate']=$poll['nclosedate'];
			$poll_info['nminpoints']=(int)$poll['nminpoints'];
			$poll_info['id']=(int)$poll['id'];
			$polls_nomination[$i]=$poll_info;
			$i++;
		}
		
		$result=qa_db_query_sub('SELECT * FROM ^poll where popendate<=CURDATE() and pclosedate >=CURDATE() and listing=$','y');
		$polls_casting=[];
		$i=0;	
		foreach ($result as $poll) {
			$poll_info=[];
			$poll_info['heading']=$poll['heading'];
			$poll_info['popendate']=$poll['popendate'];
			$poll_info['pclosedate']=$poll['pclosedate'];
			$poll_info['pminpoints']=(int)$poll['pminpoints'];
			$poll_info['id']=(int)$poll['id'];
			$polls_casting[$i]=$poll_info;
			$i++;
		}

		$result=qa_db_query_sub('SELECT * FROM ^poll where pclosedate < CURDATE() and listing=$','y');
		$polls_casted=[];
		$i=0;	
		foreach ($result as $poll) {
			$poll_info=[];
			$poll_info['heading']=$poll['heading'];
			$poll_info['popendate']=$poll['popendate'];
			$poll_info['pclosedate']=$poll['pclosedate'];
			$poll_info['pminpoints']=(int)$poll['pminpoints'];
			$poll_info['id']=(int)$poll['id'];
			$polls_casted[$i]=$poll_info;
			$i++;
		}

		$html.='<div id="polls-nomination"><h1> List of available polls for enrolling Nomination: </h1><table id="nomination-table"><thead><tr><td>Title</td><td>Open Date</td><td>Close Date</td><td>Minimum Points</td><td>Link</td>';
		if($allowed_user)
			$html .= '<td>Update</td>';
		$html.='</tr></thead><tbody>';
		foreach ($polls_nomination as $poll) {
			$html .= '<tr><td>'.$poll['heading'].'</td><td>'.$poll['nopendate'].'</td><td>'.$poll['nclosedate'].'</td><td>'.$poll['nminpoints'].'</td><td><a href="'.qa_path_html("nomination", ["poll" => $poll["id"]]).'">Enroll</a></td>';
			if($allowed_user)
				$html .= '<td><a href="'.qa_path_html("update-poll", ["poll" => $poll["id"]]).'">Update</a></td>';
			$html.='</tr>';
		}
		$html .= '</tbody></table></div><div style="height:30px;"></div><div id="polls-casting" style="width:100%"><h1> List of available polls for casting votes: </h1><table id="polling-table"><thead><tr><td>Title</td><td>Open Date</td><td>Close Date</td><td>Minimum Points</td><td>Link</td>';
		if($allowed_user)
			$html .= '<td>Update</td>';
		$html.='</tr></thead><tbody>';
		foreach ($polls_casting as $poll) {
			$html .= '<tr><td>'.$poll['heading'].'</td><td>'.$poll['popendate'].'</td><td>'.$poll['pclosedate'].'</td><td>'.$poll['pminpoints'].'</td><td><a href="'.qa_path_html("casting", ["poll" => $poll["id"]]).'">Cast Your Vote</a></td>';
			if($allowed_user)
				$html .= '<td><a href="'.qa_path_html("update-poll", ["poll" => $poll["id"]]).'">Update</a></td>';
			$html.='</tr>';
		}
		$html .= '</tbody></table></div><div style="height:30px;"></div><div id="polls-casted" style="width:100%"><h1> List of completed polls: </h1><table id="complete-table"><thead><tr><td>Title</td><td>Open Date</td><td>Close Date</td><td>Minimum Points</td><td>Link</td>';
		if($allowed_user)
			$html .= '<td>Update Poll</td>';
		$html.='</tr></thead><tbody>';
		foreach ($polls_casted as $poll) {
			$html .= '<tr><td>'.$poll['heading'].'</td><td>'.$poll['popendate'].'</td><td>'.$poll['pclosedate'].'</td><td>'.$poll['pminpoints'].'</td><td><a href="'.qa_path_html("poll-stats", ["poll" => $poll["id"]]).'">Stats</a></td>';
			if($allowed_user)
				$html .= '<td><a href="'.qa_path_html("update-poll", ["poll" => $poll["id"]]).'">Update</a></td>';
			$html.='</tr>';
		}
		$html .= '</tbody></table></div><div style="height:30px;"></div>';
		

		$content['custom'] = $html;
        $content['title'] = 'Polls';
		$content['heading'] = '';   
		 

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
				myJQ(\'#complete-table\').DataTable({
					"pageLength": 5,
					"lengthMenu": [ [5, 10, 25, 50, -1], [5, 10, 25, 50, "All"] ]
				});
			});
			</script>';
        return $content;
	}	
}