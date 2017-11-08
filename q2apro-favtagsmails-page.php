<?php

/*
	Plugin Name: Newsletter FavTags
	Plugin URI: http://www.q2apro.com/plugins/newsletter
*/

	class q2apro_favtagsmails_page
	{

		var $directory;
		var $urltoroot;

		function load_module($directory, $urltoroot)
		{
			$this->directory=$directory;
			$this->urltoroot=$urltoroot;
		}

		// for display in admin interface under admin/pages
		function suggest_requests()
		{
			return array(
				array(
					'title' => 'Favorite Tags Mails', // title of page
					'request' => 'Favorite Tags Mails', // request name
					'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				),
			);
		}

		// for url query
		function match_request($request)
		{
			if ($request=='favtagsmails')
			{
				return true;
			}

			return false;
		}

		function process_request($request)
		{

			/* Implemented for better performance and because of mysql/q2a core bug from https://github.com/q2a/question2answer/issues/47
			* 1. create extra table qa_userfavtags: userid, tagname --- then we also need tags on favorite-event to this table
			* 2. on favorite event update the table
			* 3. use this plugin here to get all recent questions
			* 4. check questions against tags and if match, add them to usermailbody
			*/

			if(qa_opt('q2apro_favtagsmails_enabled')!=1)
			{
				$qa_content = qa_content_prepare();
				$qa_content['error'] = '<div>'.qa_lang_html('q2apro_favtagsmails_lang/plugin_disabled').'</div>';
				return $qa_content;
			}
			// return if permission level is not sufficient
			if(qa_user_permit_error('q2apro_favtagsmails_permission'))
			{
				$qa_content = qa_content_prepare();
				$qa_content['error'] = qa_lang_html('q2apro_favtagsmails_lang/access_forbidden');
				return $qa_content;
			}

			// TRACK EXECUTION TIME
			$time_start = microtime(true);

			// already sent today? this replaces a cronjob
			$date = date('Y-m-d');
			if(true) // $date != qa_opt('q2apro_favtagsmails_checkdate') )
			{
				// DEV SWITCH ON WHEN LIVE
				qa_opt('q2apro_favtagsmails_checkdate', $date);

				require_once QA_INCLUDE_DIR.'qa-app-posts.php';
				require_once QA_INCLUDE_DIR.'qa-app-emails.php';

				// start content
				$qa_content = qa_content_prepare();

				// set CSS class in body tag
				qa_set_template('favtagsmails-page');

				// page title
				$qa_content['title'] = 'Fav Tags Mails (Admin)';
				$qa_content['custom'] = ''; // init

				// get all recent questions from within last 3 days
				$questions = qa_db_read_all_assoc(
									qa_db_query_sub('SELECT postid,tags,title FROM `^posts`
													WHERE `tags` != ""
													AND type="Q"
													AND DATE(`created`) > CURDATE() - INTERVAL 3 DAY
													') );
													// memo: twice listing can happen if tags are "nekilnojamas,turtas" which is found twice in "nekilnojamas-turtas"
				// holds all questions with <li> elements
				$nlQuestions = array();

				$newsletter_out = '';

				$qa_content['custom'] .= '<p>';
				foreach($questions as $question)
				{
					// save questions in array
					if(isset($question['postid']))
					{
						$newsletter_out .= '
							<h3 style="background:#EEE;padding:7px;">
								Tag: <a href="'.qa_path('tag').'/'.$question['tags'].'">'.$question['tags'].'</a> | Question: <a target="_blank" href="./'.$question['postid'].'">'.$question['postid'].'</a>
							</h3>
						';
						$postid = $question['postid'];
						$qTitle = $question['title'];

						// explode tags string into array
						$tagsArr = explode(',', $question['tags']);

						// we use the tagid as the array index and add the questions as <li> to it
						foreach($tagsArr as $qTag)
						{
							$qu_url = qa_path_html(qa_q_request($postid, $qTitle), null, qa_opt('site_url'), null, null);
							/*$tagid = qa_db_read_one_value(
											qa_db_query_sub('SELECT wordid FROM `^words`
															WHERE word = #
															', $qTag), true);
															*/
							if(!isset($nlQuestions[$qTag]))
							{
								$nlQuestions[$qTag] = '<ol><li><a href="'.$qu_url.'">'.htmlspecialchars($qTitle).'</a></li>';
							}
							else
							{
								$nlQuestions[$qTag] .= ' <li><a href="'.$qu_url.'">'.htmlspecialchars($qTitle).'</a></li>';
							}
						}
					}
				}
				$newsletter_out .= '</p>';
				// close all list elements
				foreach($nlQuestions as $q => $val)
				{
					$nlQuestions[$q] = $val.'</ol>';
				}
				// var_dump($nlQuestions);
				// return $qa_content;

				// BIG arrray that holds all data
				$userdata = array();
				$countmails = 0;

				// get all user favorites
				$queryFavoriteTags = qa_db_query_sub('SELECT userid, tagwords FROM `^favtags`');

				// get all tagids and assign them to userid
				while ( ($row = qa_db_read_one_assoc($queryFavoriteTags,true)) !== null )
				{
					$userdata[$row['userid']]['userid'] = $row['userid'];
					$userdata[$row['userid']]['tagwords'] = $row['tagwords'];
				}

				// assign questions from last 3 days to user, regarding the user's tagwords
				$questionFound = false;
				foreach($userdata as $user)
				{
					$userid = $user['userid'];
					$usertags = explode(',', $user['tagwords']);
					// receive questions for each tag (tag is key of associative array)
					foreach($usertags as $qtag)
					{
						// does question with tag exist
						if(isset($nlQuestions[$qtag]))
						{
							$questionFound = true;
							// save all questions to userid
							$userdata[$userid]['questions'][] = array(
								'quList' => $nlQuestions[$qtag],
								'tagword' => $qtag,
							);
						}
					}
				}
				if(!$questionFound)
				{
					$qa_content['error'] = 'no question found for any user';
					return $qa_content;
				}
				// return $qa_content;

				// assemble email body
				foreach($userdata as $user)
				{
					$emailbody = '';
					$userid = $user['userid'];
					// var_dump($user);

					$recentTagWord = '';
					$questionList = '';
					$nquestion = 0;
					if(isset($userdata[$user['userid']]['questions']))
					{
						// get number of questions
						$nquestion = count($userdata[$user['userid']]['questions']);
						// go over all questions (hold quList and tagword)
						foreach($userdata[$user['userid']]['questions'] as $quwithtag)
						{
							if(isset($quwithtag['tagword']))
							{
								$questionList .= '<h3 style="margin-left:20px;text-transform:uppercase;">
									<a href="'.qa_opt('site_url').'tag/'.$quwithtag['tagword'].'" style="color:#555;">'.$quwithtag['tagword'].'</a>
								</h3>';
								$questionList .= $quwithtag['quList'];
							}
						}
					}

					// get userdata
					$userinfo = qa_db_select_with_pending(qa_db_user_account_selectspec($userid, true));
					$emailbody .= '<p>'.qa_lang('q2apro_favtagsmails_lang/mail_hello').' '.$userinfo['handle'].',</p>';
					$emailbody .= '<p>'.qa_lang('q2apro_favtagsmails_lang/mail_intro').'</p>';
					$emailbody .= $questionList;
					$emailbody .= '<p>'.qa_lang('q2apro_favtagsmails_lang/mail_greeting').'<br />'.qa_lang('q2apro_favtagsmails_lang/mail_greetername').'</p>';
					$emailbody .= '<p>&nbsp;</p>';
					$subscribehint = strtr( qa_lang('q2apro_favtagsmails_lang/mail_subscribehint'), array(
							'^1' => '<a target="_blank" style="color:#999 !important;text-decoration:underline;" href="'.qa_opt('site_url').'newsletter?userid='.$userid.'">',
							'^2' => '</a>'
						  )).'</span>';
					$emailbody .= '<p style="font-size:12px;color:#999;">'.$subscribehint.'</p>';
					// DEV
					// $emailbody .= '<hr style="margin-bottom:40px;">';

					if($nquestion>0)
					{
						$countmails++;
						$newsletter_out .= '<p style="font-weight:bold;padding-top:50px;border-top:1px solid #005;">Recipient: '.$userinfo['handle'].' &lt;'.$userinfo['email'].'&gt;</p>';
						$newsletter_out .= $emailbody;
					}

					// we do not send emails here
					/*
						if($nquestion>0 && !($userinfo['flags'] & QA_USER_FLAGS_NO_MAILINGS)) {
							$qa_content['custom'] .= '<p style="color:#F00;">SENDING MAIL TO: '.$userinfo['handle'].'</p>';
							qa_send_email(array(
								'fromemail' => qa_opt('mailing_from_email'),
								'fromname' => qa_opt('mailing_from_name'),
								'toemail' => $userinfo['email'],
								'toname' => $userinfo['handle'],
								'subject' => qa_lang('q2apro_favtagsmails_lang/mail_subject'),
								'body' => trim($emailbody),
								'html' => true,
							));
						}
					*/
				}

				$qa_content['custom'] .= '
					<h2 style="margin:40px 0;color:#33F;">
						Mails to be sent (regarding last 3 days): '.$countmails.'
					</h2>
				';
				$qa_content['custom'] .= $newsletter_out;

				// TRACK EXECUTION TIME
				$time_end = microtime(true);
				// dividing with 60 will give the execution time in minutes other wise seconds
				$execution_time = ($time_end - $time_start);
				// execution time of the script
				$qa_content['custom'] .= '<p style="margin-top:50px;">Script Execution Time: <b>'.$execution_time.' sec</b></p>';

				return $qa_content;
			}
			else
			{
				$qa_content=qa_content_prepare();
				$qa_content['error'] = qa_lang('q2apro_favtagsmails_lang/mailed_already');
				return $qa_content;
			}
		} // process_request

	} // end class

/*
	Omit PHP closing tag to help avoid accidental output
*/
