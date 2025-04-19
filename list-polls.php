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
		$result=qa_db_query_sub('SELECT * FROM ^poll where Nomination = "y" and NopenDate<=CURDATE() and NcloseDate >=CURDATE()');
		$polls_nomination=[];
		$i=0;	
		foreach ($result as $poll) {
			$poll_info=[];
			$poll_info['Heading']=$poll['Heading'];
			$poll_info['NopenDate']=$poll['NopenDate'];
			$poll_info['NcloseDate']=$poll['NcloseDate'];
			$poll_info['NMinPoints']=(int)$poll['NMinPoints'];
			$poll_info['id']=(int)$poll['id'];
			$polls_nomination[$i]=$poll_info;
			$i++;
		}
		
		$result=qa_db_query_sub('SELECT * FROM ^poll where PopenDate<=CURDATE() and PcloseDate >=CURDATE()');
		$polls_casting=[];
		$i=0;	
		foreach ($result as $poll) {
			$poll_info=[];
			$poll_info['Heading']=$poll['Heading'];
			$poll_info['PopenDate']=$poll['PopenDate'];
			$poll_info['PcloseDate']=$poll['PcloseDate'];
			$poll_info['PMinPoints']=(int)$poll['PMinPoints'];
			$poll_info['id']=(int)$poll['id'];
			$polls_casting[$i]=$poll_info;
			$i++;
		}

		$result=qa_db_query_sub('SELECT * FROM ^poll where PcloseDate < CURDATE()');
		$polls_casted=[];
		$i=0;	
		foreach ($result as $poll) {
			$poll_info=[];
			$poll_info['Heading']=$poll['Heading'];
			$poll_info['PopenDate']=$poll['PopenDate'];
			$poll_info['PcloseDate']=$poll['PcloseDate'];
			$poll_info['PMinPoints']=(int)$poll['PMinPoints'];
			$poll_info['id']=(int)$poll['id'];
			$polls_casted[$i]=$poll_info;
			$i++;
		}

		$html='<div id="polls-nomination" style="width:100%"><h1> List of available polls for enrolling Nomination: </h1><table style=\'border:1px solid red\'><tr><td>Title</td><td>Open Date</td><td>Close Date</td><td>Minimum Points</td><td>Link</td></tr>';
		foreach ($polls_nomination as $poll) {
			$html .= '<tr><td>'.$poll['Heading'].'</td><td>'.$poll['NopenDate'].'</td><td>'.$poll['NcloseDate'].'</td><td>'.$poll['NMinPoints'].'</td><td><a href="'.qa_path_html("nomination", ["poll" => $poll["id"]]).'">Enroll</a></td></tr>';
		}
		$html .= '</table></div><div style="height:30px;"></div><div id="polls-casting" style="width:100%"><h1> List of available polls for casting votes: </h1><table style=\'border:1px solid red\'><tr><td>Title</td><td>Open Date</td><td>Close Date</td><td>Minimum Points</td><td>Link</td></tr>';
		foreach ($polls_casting as $poll) {
			$html .= '<tr><td>'.$poll['Heading'].'</td><td>'.$poll['PopenDate'].'</td><td>'.$poll['PcloseDate'].'</td><td>'.$poll['PMinPoints'].'</td><td><a href="'.qa_path_html("casting", ["poll" => $poll["id"]]).'">Cast Your Vote</a></td></tr>';
		}
		$html .= '</table></div><div style="height:30px;"></div><div id="polls-casted" style="width:100%"><h1> List of completed polls: </h1><table style=\'border:1px solid red\'><tr><td>Title</td><td>Open Date</td><td>Close Date</td><td>Minimum Points</td><td>Link</td></tr>';
		foreach ($polls_casted as $poll) {
			$html .= '<tr><td>'.$poll['Heading'].'</td><td>'.$poll['PopenDate'].'</td><td>'.$poll['PcloseDate'].'</td><td>'.$poll['PMinPoints'].'</td><td><a href="'.qa_path_html("poll-stats", ["poll" => $poll["id"]]).'">Stats</a></td></tr>';
		}
		$html .= '</table></div><div style="height:30px;"></div>';
		
		$content = qa_content_prepare();
		$content['custom'] = $html;
        $content['title'] = 'Polls';
        return $content;
	}	
}