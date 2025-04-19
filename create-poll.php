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
					Heading VARCHAR(3000) NOT NULL,
					Description VARCHAR(3000) NOT NULL,
					Nomination CHAR NOT NULL,
					NopenDate DATE, 
					NcloseDate DATE, 
					NMinPoints INT NOT NULL,
					PopenDate DATE,
					PcloseDate DATE, 
					PMinPoints INT NOT NULL,
					Pselection CHAR NOT NULL,
					Ppoints INT NOT NULL,
					VotesDisplay CHAR NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
        }
		if (!in_array(qa_db_add_table_prefix('pnomination'), $tablesexisting)) {
            return
                'CREATE TABLE IF NOT EXISTS ^pnomination (
                    id INT AUTO_INCREMENT PRIMARY KEY,
					pid INT NOT NULL,
					uid INT NOT NULL,
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
			return qa_redirect('login');
		}
        $content = qa_content_prepare();		
		if(qa_get_logged_in_level()<QA_USER_LEVEL_ADMIN)
		{
			$content['error'] = 'You must be Admin or Above to access this page.';
            return $content;			
		}
		require_once QA_INCLUDE_DIR . 'db/metas.php';
		require_once QA_INCLUDE_DIR . 'qa-app-users.php';
		require_once QA_INCLUDE_DIR . 'qa-db.php';
		$userid = qa_get_logged_in_userid();
		$level=qa_get_logged_in_level();
		$content['title'] = 'Create a Poll';
        
		$saved = false;

        if (qa_post_text('create-poll-page-form')) {
            // Collect inputs
			$heading = qa_html(qa_post_text('heading'));
			$description = qa_post_text('description');
			$nomination = qa_post_text('nomination');
			$nopen = qa_post_text('nopen');
			$nclose = qa_post_text('nclose');
			$nminpoints = (int) qa_post_text('nminpoints');
			$popen = qa_post_text('popen');
			$pclose = qa_post_text('pclose');
			$pminpoints = (int) qa_post_text('pminpoints');
			$pselection = qa_post_text('pselection');
			$ppoints = (int) qa_post_text('ppoints');
			$votesdisplay = qa_post_text('votesdisplay');

            if($nomination=='y'){
            qa_db_query_sub(
                'INSERT INTO ^poll(Heading, Description, Nomination, NopenDate, NcloseDate, NMinPoints, PopenDate, PcloseDate, PMinPoints, Pselection, Ppoints, VotesDisplay) VALUES ($, $, $, $, $, #, $, $, #, $, #, $)',$heading, $description, $nomination,$nopen, $nclose, $nminpoints,$popen, $pclose, $pminpoints,$pselection, $ppoints, $votesdisplay
            );
			}
			else
			{
				qa_db_query_sub(
                'INSERT INTO ^poll(Heading, Description, Nomination, NopenDate, NcloseDate, NMinPoints, PopenDate, PcloseDate, PMinPoints, Pselection, Ppoints, VotesDisplay) VALUES ($, $, $, $, $, #, $, $, #, $, #, $)',$heading, $description, $nomination,$nopen, $nclose, $nminpoints,$popen, $pclose, $pminpoints,$pselection, $ppoints, $votesdisplay
				);				
				$pollid = qa_db_last_insert_id();
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
        $fields = [
            ['label' => 'Title of the Poll', 'type' => 'text', 'tags' => 'NAME="heading"'],
            ['label' => 'Description', 'type' => 'text', 'tags' => 'NAME="description"'],
            ['label' => 'Allow nomination from Users ?', 'type' => 'custom','html' => '<label><input type="radio" name="nomination" value="y" checked> Yes</label>
        <label><input type="radio" name="nomination" value="n">NO</label>', 'tags' => 'NAME="nomination" id="NOMIN"'],
            ['label' => 'Nomination Open Date', 'type' => 'date', 'tags' => 'NAME="nopen" id="NOP"','value' => $today],
            ['label' => 'Nomination Close Date', 'type' => 'date', 'tags' => 'NAME="nclose" id="NOC"', 'value' => $today],
			['label' => 'Options for users (separated by commas)', 'type' => 'text', 'tags' => 'NAME="Noptions" id="Noptions"'],
            ['label' => 'Minimum Points for Nominations', 'type' => 'number', 'tags' => 'NAME="nminpoints"', 'value' =>100],
            ['label' => 'Polling Open Date', 'type' => 'date', 'tags' => 'NAME="popen"', 'value' => date('Y-m-d', strtotime($today . ' +1 day'))],
            ['label' => 'Polling Close Date', 'type' => 'date', 'tags' => 'NAME="pclose"','value' => date('Y-m-d', strtotime($today . ' +2 day'))],
            ['label' => 'Minimum Points for Casting Votes', 'type' => 'number', 'tags' => 'NAME="pminpoints"', 'value' =>100],
            ['label' => 'Allow Multiple Selection ?', 'type' => 'custom','html' => '<label><input type="radio" name="pselection" value="m"> Yes</label>
        <label><input type="radio" name="pselection" value="s" checked>NO</label>', 'tags' => 'NAME="pselection"'],
            //['label' => 'Total Points given to Users', 'type' => 'number', 'tags' => 'NAME="ppoints"', 'value' =>1],
            ['label' => 'Display Voting stats to users while polling?', 'type' => 'custom','html' => '<label><input type="radio" name="votesdisplay" value="y"> Yes</label>
        <label><input type="radio" name="votesdisplay" value="n" checked>NO</label>', 'tags' => 'NAME="votesdisplay"']
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
        }
   $content['script'][] = '
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const nominationRadios = document.querySelectorAll("input[name=\'nomination\']");
                const nopField = document.getElementById("NOP");
                const nocField = document.getElementById("NOC");
				const optionsField = document.getElementById("Noptions");

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

            function toggleFields() {
                const selected = document.querySelector("input[name=\'nomination\']:checked");
                if (!selected) return;

                if (selected.value === "y") {
                    showField(nopField);
                    showField(nocField);
					hideField(optionsField);
					optionsField.value="";
                } else if (selected.value === "n") {
                    hideField(nopField);
					nopField.value=new Date().toISOString().split("T")[0];
					hideField(nocField);
					nocField.value=new Date().toISOString().split("T")[0];
                    showField(optionsField);
                }
            }
                // Initial state
                toggleFields();

                // On change
                nominationRadios.forEach(radio => {
                    radio.addEventListener("change", toggleFields);
                });
            });
        </script>
    ';



        return $content;
    }
	

}